<?php
require '../../classes/Util.php';
require '../../classes/DbConection.php';
require '../../include/generic_validate_session.php';

$validator = array('success' => false, 'messages' => array());

$takePicture = isset($_POST['take_picture']) ? (string) $_POST['take_picture'] : '1';
$foto = isset($_POST['foto']) ? $_POST['foto'] : '';

$zone = isset($_POST['zone']) ? $_POST['zone'] : '';
$actividades = isset($_POST['actividades']) ? $_POST['actividades'] : '';
$observaciones = isset($_POST['observaciones']) ? $_POST['observaciones'] : '';
$tbl_requerimiento_id = isset($_POST['tbl_requerimiento_id']) ? $_POST['tbl_requerimiento_id'] : '';

if ($zone === '' || $actividades === '' || $actividades === null) {
    $validator['messages'] = 'Mandatory information marked with an asterisk is missing.';
    echo json_encode($validator);
    exit();
}

$fotoDir = __DIR__ . DIRECTORY_SEPARATOR . 'foto';
if (!is_dir($fotoDir)) {
    if (!@mkdir($fotoDir, 0755, true) && !is_dir($fotoDir)) {
        $validator['messages'] = 'Photo folder could not be created.';
        echo json_encode($validator);
        exit();
    }
}

$name_photo = 'no_image.png';

if ($takePicture === '1') {
    if ($foto === '' || $foto === null) {
        $validator['messages'] = 'No photo received. Camera permission is required to take a picture.';
        echo json_encode($validator);
        exit();
    }

    $foto = preg_replace('/^data:image\/(png|jpeg|jpg);base64,/', '', $foto);
    $foto = str_replace(' ', '+', $foto);
    $fotoBin = base64_decode($foto, true);

    if ($fotoBin === false || $fotoBin === '') {
        $validator['messages'] = 'Invalid image format';
        echo json_encode($validator);
        exit();
    }

    $key = rand(0, 5000000);
    $date = date('Y_m_d');
    $name_photo = 'f_' . $key . $date . '.jpg';
    $route_photo = $fotoDir . DIRECTORY_SEPARATOR . $name_photo;

    if (file_put_contents($route_photo, $fotoBin) === false) {
        $validator['messages'] = 'Error saving the photo';
        echo json_encode($validator);
        exit();
    }
}

$dtcreate = date('Y-m-d H:i:s');

$db = new DbConection();
$pdo = $db->openConect();

$tbl_usuario_id = $_SESSION['session_user']['id'];

$response = Util::getUnidadByUser($tbl_usuario_id);
$tbl_unidad_id = 0;
if ($response['output']['valid']) {
    $tbl_unidad_id = $response['output']['tbl_unidad_id'];
}

$sql = 'INSERT INTO ' . $db->getTable('tbl_fotos') . '  (zone, actividades, observaciones, estado, foto_antes, dtcreate, tbl_unidad_id, tbl_requerimiento_id, tbl_usuario_id) VALUES (:zone, :actividades, :observaciones, :estado, :foto_antes, :dtcreate, :tbl_unidad_id, :tbl_requerimiento_id, :tbl_usuario_id)';
$result = $pdo->prepare($sql);
$arrparam = array(
    ':zone' => $zone,
    ':actividades' => $actividades,
    ':observaciones' => $observaciones,
    ':estado' => 'creado',
    ':foto_antes' => $name_photo,
    ':dtcreate' => $dtcreate,
    ':tbl_unidad_id' => $tbl_unidad_id,
    ':tbl_requerimiento_id' => $tbl_requerimiento_id,
    ':tbl_usuario_id' => $tbl_usuario_id
);

if ($result->execute($arrparam)) {
    $validator['success'] = true;
    $validator['messages'] = 'Information saved correctly';
    $validator['id'] = (int) $pdo->lastInsertId();
} else {
    $validator['messages'] = 'Error saving data';
}

echo json_encode($validator);
exit();
