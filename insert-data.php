<?php



//echo round(500.123456789, 6);    // 5.06


require 'admin/classes/Util.php';
require 'admin/classes/DbConection.php';

//$arr = array('id' => 257,  'factura' => 'SETP990000189', 'uuid' => '98e2723bf6d09bf03aa5fbbc0e715a7701de8fd3b268181367e78f29dbfce1cb3c0dd02e127deaa340cb548ed0e03bbf');
//$test = Util::validarEstadoDocumento($arr);

//$arr = array('uuid' => '3a92aeb48af604bf2f60ee79367f9d86259b12c4f87c891eda7e517abfe9b39bbc387ed42468874c49760eaa98e0ee34', 'email' => 'alexlondon07.developer@gmail.com');
//$test = Util::envioCorreo($arr);


/* $data =["Regla: FAU14, Rechazo: Valor a Pagar de Factura es distinto de la Suma de Valor Bruto m\u00e1s tributos - Valor del Descuento Total + Valor del Cargo Total - Valor del Anticipo Total","Regla: FAK55, Notificaci\u00f3n: Correo electr\u00f3nico no informado"];

$separado_por_comas = implode("Regla:", $data);
echo utf8_encode( $separado_por_comas ); */ // apellido,email,teléfono

//require 'admin/classes/Util.php';
//require 'admin/classes/DbConection.php';

//$test = Util::enviarFacturaDIAN(null);

 $arr = '[
        {
            "id": 1,
            "name": "Persona Jurídica y asimiladas",
            "code": "1",
            "deleted_at": null,
            "created_at": "2020-10-23T21:25:14.000000Z",
            "updated_at": "2020-10-23T21:25:14.000000Z"
        },
        {
            "id": 2,
            "name": "Persona Natural y asimiladas",
            "code": "2",
            "deleted_at": null,
            "created_at": "2020-10-23T21:25:14.000000Z",
            "updated_at": "2020-10-23T21:25:14.000000Z"
        }
    ]';

    $db = new DbConection();
    $pdo = $db->openConect();

    $hola = json_decode($arr);
    $c = count($hola);


for ($i = 0; $i < $c; $i++) {
    $q0 = "INSERT INTO " . $db->getTable('tec_type_organizations') . "(dtcreate, nombre, descripcion, codigo )
    VALUES (" . Util::date_now_server() . ", :nombre, :descripcion, :codigo )";
    $result0 = $pdo->prepare($q0);

    $arrparm0 = array(
        ':nombre' => $hola[$i]->name,
        ':descripcion' =>  $hola[$i]->name,
        ':codigo' => $hola[$i]->code
    );


    if (!$result0->execute($arrparm0)) {
        echo "error";
    }else{
        echo "ok";
    }

    $db->closeConect();
} 
