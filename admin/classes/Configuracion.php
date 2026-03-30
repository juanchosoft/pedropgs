<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Configuracion
{

    public function __construct()
    {
    }


    /**
     * Metodo para recuperar todos los registros
     * @return array de las config
     */
    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_config') . " ORDER BY id DESC LIMIT 1";

        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_config') . " WHERE id = " . $id . " LIMIT 1";
        }
        $result = $pdo->query($q);
        $arr = array();
        if ($result) {
            foreach ($result as $valor) {
                $arr[] = $valor;
            }
            $arrjson = array('output' => array('valid' => true, 'response' => $arr));
        } else {
            $arrjson = Util::error_no_result();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para guardar o actualizar un registro
     * @param REQUEST $rqst
     * @return array de Resoluciones
     * 
     */
    public static function save($rqst){
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $empresa = isset($rqst['empresa']) ? ($rqst['empresa']) : '0';
        $pass1 = isset($rqst['pass1']) ? ($rqst['pass1']) : '0';
        $pass2 = isset($rqst['pass2']) ? ($rqst['pass2']) : '0';
        $pass3 = isset($rqst['pass3']) ? ($rqst['pass3']) : '0';
        $email = isset($rqst['email']) ? ($rqst['email']) : '0';
        $encabezado_fac = isset($rqst['encabezado_fac']) ? ($rqst['encabezado_fac']) : '0';
        $pie_fac = isset($rqst['pie_fac']) ? ($rqst['pie_fac']) : '0';
        $nit = isset($rqst['nit']) ? ($rqst['nit']) : '';
        $razon_social = isset($rqst['razon_social']) ? ($rqst['razon_social']) : '';
        $telefono = isset($rqst['telefono']) ? ($rqst['telefono']) : '';
        $direccion = isset($rqst['direccion']) ? ($rqst['direccion']) : '';
        $comentarios = isset($rqst['comentarios']) ? ($rqst['comentarios']) : '';
        $config_precio_productos = isset($rqst['config_precio_productos']) ? ($rqst['config_precio_productos']) : '1';
        $caja_recibe_pagos = isset($rqst['caja_recibe_pagos']) ? ($rqst['caja_recibe_pagos']) : 'no';
        $impresion_termica = isset($rqst['impresion_termica']) ? ($rqst['impresion_termica']) : 'no';
        $valor_bolsa = isset($rqst['valor_bolsa']) ? ($rqst['valor_bolsa']) : 0;
        $texto_descripcion_larga_pie_pagina = isset($rqst['texto_descripcion_larga_pie_pagina']) ? ($rqst['texto_descripcion_larga_pie_pagina']) : '';
        $texto_resolucion = isset($rqst['texto_resolucion']) ? ($rqst['texto_resolucion']) : '';
        $imagefileToUpload = isset($_SESSION['file']['nombrearchivo']) ? ($_SESSION['file']['nombrearchivo']) : '';
        
        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id, img  FROM " . $db->getTable('tec_config') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tec_config');
                $arrfieldscomma = array(
                    'empresa' => $empresa,
                    'pass1' => $pass1,
                    'pass2' => $pass2,
                    'pass3' => $pass3,
                    'email' => $email,
                    'img' => $imagefileToUpload,
                    'encabezado_fac' => $encabezado_fac,
                    'pie_fac' => $pie_fac,
                    'nit' => $nit,
                    'razon_social' => $razon_social,
                    'direccion' => $direccion,
                    'comentarios' => $comentarios,
                    'config_precio_productos' => $config_precio_productos,
                    'caja_recibe_pagos' => $caja_recibe_pagos,
                    'telefono' => $telefono,
                    'impresion_termica' => $impresion_termica,
                    'valor_bolsa' => $valor_bolsa,
                    'texto_descripcion_larga_pie_pagina' => $texto_descripcion_larga_pie_pagina,
                    'texto_resolucion' => $texto_resolucion,
                );
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                if (!$pdo->query($q)) {

                    // Obtemos el valor de la imagen del producto
                    $file = "";
                    foreach ($result as $valor) {
                        $file = $valor['image'];
                    }
                    // Eliminamos el archivo anterior
                    if($imagefileToUpload != ""){
                        if ($file != "" && file_exists($file)) {
                            unlink("../assets/img/admin/" . $file);
                        }
                    }
                    $arrjson = Util::error_general('Actualizando las Configuraciones del sistema');
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }
        }
        $db->closeConect();
        return $arrjson;
    }
}
