<?php
require '../../classes/Util.php';
require '../../classes/DbConection.php';
require '../../include/generic_validate_session.php';

$validator = array('success' => false, 'messages' => array());
$foto = $_POST["foto"];


$foto = str_replace('data:image/png;base64,', '', $foto);
$foto = str_replace(' ', '+', $foto);
$foto = base64_decode($foto);

$zone = $_POST["zone"];
$actividades = $_POST["actividades"];
$observaciones = $_POST["observaciones"];
$tbl_requerimiento_id = $_POST["tbl_requerimiento_id"];

if (($zone) == "" || $actividades  == "" || $actividades  == null) {
	$validator['messages'] = "Mandatory information marked with an asterisk is missing.";
	echo json_encode($validator);
	exit();
}

$key = rand(0, 5000000);
$dtcreate = date('Y-m-d H:i:m', time());
$date = date('Y_m_d');
$name_photo = "f_" . $key . $date . ".jpg";

$route_photo = "./foto/" . $name_photo;

if (file_put_contents($route_photo, $foto)) {

	$db = new DbConection();
	$pdo = $db->openConect();

	$tbl_usuario_id = $_SESSION['session_user']['id'];

	$response = Util::getUnidadByUser($tbl_usuario_id);
	$tbl_unidad_id = 0;
	$tbl_unidad_id = 0;
	if ($response['output']['valid']) {
		$tbl_unidad_id = $response['output']['tbl_unidad_id'];
	}


	$sql = "INSERT INTO " . $db->getTable('tbl_fotos') . "  (zone, actividades, observaciones, foto_antes, dtcreate, tbl_unidad_id, tbl_requerimiento_id, tbl_usuario_id) VALUES (:zone, :actividades, :observaciones, :foto_antes, :dtcreate, :tbl_unidad_id, :tbl_requerimiento_id, :tbl_usuario_id)";
	$result = $pdo->prepare($sql);
	$arrparam = array(
		':zone' => $zone,
		':actividades' => $actividades,
		':observaciones' => $observaciones,
		':foto_antes' => $name_photo,
		':dtcreate' => $dtcreate,
		':tbl_unidad_id' => $tbl_unidad_id,
		':tbl_requerimiento_id' => $tbl_requerimiento_id,
		':tbl_usuario_id' => $tbl_usuario_id
	);



	if ($result->execute($arrparam)) {
		$validator['success'] = true;
		$validator['messages'] = "Information saved correctly";
	} else {
		$validator['messages'] = "Error saving data";
	}
} else {
	$validator['messages'] = "Error saving the photo";
}
echo json_encode($validator);
exit();
