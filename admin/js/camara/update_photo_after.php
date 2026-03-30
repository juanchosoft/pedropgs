<?php
require '../../classes/Util.php';
require '../../classes/DbConection.php';

$validator = array('success' => false, 'messages' => array());
$id = $_POST["id"];
$foto = $_POST["foto"];
$foto = str_replace('data:image/png;base64,', '', $foto);
$foto = str_replace(' ', '+', $foto);
$foto = base64_decode($foto);

if (intval($id) == 0 || $foto  == "" || $foto  == null) {
    $validator['messages'] = "Mandatory information marked with an asterisk is missing.";
    echo json_encode($validator);
    exit();
}

$key = rand(0, 5000000);
$dtcreate = date('Y-m-d H:i:m', time());
$date = date('Y_m_d');
$name_photo = "f_" . $key . $date . ".jpg";

$route_photo = "./foto/" . $name_photo;

if (file_put_contents($route_photo, $foto) && $id > 0) {

    $db = new DbConection();
    $pdo = $db->openConect();

    $q = "SELECT id  FROM " . $db->getTable('tbl_fotos') . " WHERE id = " . $id;
    $result = $pdo->query($q);
    if ($result) {
        $table = $db->getTable('tbl_fotos');
        $arrfieldscomma = array('foto_despues' => $name_photo);
        $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
        $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
        $result = $pdo->query($q);
        if (!$pdo->query($q)) {
            $validator['messages'] = "Error updating data";
        } else {
            $validator['success'] = true;
            $validator['messages'] = "Information saved correctly";
        }
    } else {
        $validator['messages'] = "Error getting data";
    }
} else {
    $validator['messages'] = "Error missing data";
}
echo json_encode($validator);
exit();
