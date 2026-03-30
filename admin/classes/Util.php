<?php
class Util
{

    // Para enviar la peticion de la DIAN
    public $SYNC_API = true;

    public $THE_KEY = '8da48890b622b4d9a340d2a5c261b1b1';
    /**
     * Valor de un KB = 1024 bytes
     * @var int
     */
    public $KB_BYTE = 1024;

    /**
     * Valor de un MB = 1024 KB
     * @var int
     */
    public $MB_BYTE = 1048576;

    /**
     * Url de la raiz de la aplicación
     * @var string
     */
    public static function URL_ROOT_HOST()
    {
        $URL_ROOT_HOST = "";
        return $URL_ROOT_HOST;
    }

    public function __construct()
    {
        //contructor que no tiene ninguna funcion, por ahora
    }

    public static function get_app_id()
    {
        return '8da48890b622b4d9a340d2a5c261b1b1';
    }
    /**
     * Método para capturar la Ip del cliente
     * @return string Ip del cliente
     */
    public static function get_real_ipaddress()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP']; //check ip from share internet
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR']; //to check ip is pass from proxy
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * Método para hacer POST desde PHP
     * @param string $url
     * @param array $data
     * @param string $referer
     * @return array ['status', 'header', 'content']
     */
    public static function post_request($url, $data, $referer = '')
    {
        // Convert the data array into URL Parameters like a=b&foo=bar etc.
        $data = http_build_query($data);
        // parse the given URL
        $url = parse_url($url);
        if ($url['scheme'] != 'http') {
            die('Error: Only HTTP request are supported !');
        }
        // extract host and path:
        $host = $url['host'];
        $path = $url['path'];
        //		echo '<br/>';
        //		echo '<br/>'.$host;
        //		echo '<br/>'.$path;
        //		echo '<br/>';
        if (function_exists('fsockopen')) {
            //echo 'open a socket connection on port 80 - timeout: 30 sec';
            $fp = fsockopen($host, 80, $errno, $errstr, 30);
            if ($fp) {
                // send the request headers:
                fputs($fp, "POST $path HTTP/1.1\r\n");
                fputs($fp, "Host: $host\r\n");

                if ($referer != '') {
                    fputs($fp, "Referer: $referer\r\n");
                }

                fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
                fputs($fp, "Content-length: " . strlen($data) . "\r\n");
                fputs($fp, "Connection: close\r\n\r\n");
                fputs($fp, $data);

                $result = '';
                while (!feof($fp)) {
                    // receive the results of the request
                    $result .= fgets($fp, 128);
                }
            } else {
                return array(
                    'status' => 'err',
                    'error' => '$errstr ($errno)'
                );
            }
        } else {
            echo "No fsockopen, please config php.ini <br />\n";
        }

        // close the socket connection:
        fclose($fp);

        // split the result header from the content
        $result = explode("\r\n\r\n", $result, 2);

        $header = isset($result[0]) ? $result[0] : '';
        $content = isset($result[1]) ? $result[1] : '';

        // return as structured array:
        return array(
            'status' => 'ok',
            'header' => $header,
            'content' => $content
        );
    }

    /**
     * Metodo que implementa la funcion <i>post_request</i> cuando se utiliza de la siguiente manera:
     * <code>
      $post_data = array('param1' => 'some1','param2' => $some2 );
      $result = $util->post_request($util->URL_BLAST24WS, $post_data);
      if ($result['status'] == 'ok'){
      ___$json_decoded = json_decode($result['content']);
      ___if ($json_decoded->output->valid) {
      ______$_SESSION['okSessionVarName'] = $json_decoded->output->response;
      ______$_SESSION['location'] = 'okLocation';
      ___} else {
      ______$_SESSION['json_error'] = $json_decoded->output->response;
      ______$_SESSION['location'] = 'errorLocation';
      ______}
      ___} else {echo 'A error occured: ' . $result['error']; }
      </code>
     * @param array $post_data datos para hacer post
     * @param string $okLocation ubicacion a setear en $_SESSION['location'] si la peticion es la esperada
     * @param string $errorLocation ubicacion a setear en $_SESSION['location'] si la peticion devuelve error
     * @param string $okSessionVarName nombre de la variable de sesion que se inicia
     * @return array resultado del post
     */
    public function post_request_common($post_data, $okLocation = "", $errorLocation = "", $okSessionVarName = "")
    {
        $result = $this->post_request($this->URL_BLAST24WS, $post_data);
        if ($result['status'] == 'ok') {
            $json_decoded = json_decode($result['content']);
            if ($json_decoded->output->valid) {
                if ($okLocation != "") {
                    $_SESSION['location'] = $okLocation;
                }
                if ($okSessionVarName != "") {
                    $_SESSION[$okSessionVarName] = $json_decoded->output->response;
                }
            } else {
                if ($errorLocation != "") {
                    $_SESSION['location'] = $errorLocation;
                }
                $_SESSION['json_error'] = $json_decoded->output->response;
            }
        } else {
            echo 'A error occured: ' . $result['error'];
        }
        return $result;
    }

    /**
     * Mètodo para eliminar caracteres especiales que puedan modificar las consultas SQL.
     * Una función para evitar SQL Injection.
     * @param string $str
     * @return string Cadena de carateres segura
     */
    public static function remove_special_char($str)
    {
        if ($str == null || count($str) <= 0) {
            return $str;
        }
        $realstr = str_replace("'", "", $str);
        $realstr = str_replace("&", "", $realstr);
        //$realstr = str_replace("\n","",$realstr);
        //$realstr = str_replace("\r","",$realstr);
        $realstr = str_replace("<", "", $realstr);
        $realstr = str_replace(">", "", $realstr);
        $realstr = str_replace("\"", "", $realstr);
        $realstr = str_replace("drop", "", $realstr);
        $realstr = str_replace("DROP", "", $realstr);
        $realstr = str_replace("delete", "", $realstr);
        $realstr = str_replace("DELETE", "", $realstr);
        // ESTOS SE INHABILITAN PARA PODER ALMACENAR DIRECCIONES EN LA BASE DE DATOS
        // $realstr = str_replace("/","",$realstr);
        // $realstr = str_replace("/\/","",$realstr);
        //$realstr = str_replace("|","",$realstr);
        return $realstr;
    }

    public static function remove_weird_char($str)
    {
        if ($str == null || count($str) <= 0) {
            return $str;
        }
        $realstr = str_replace("Ã¡", "a", $str);
        $realstr = str_replace("Ã©", "e", $realstr);
        $realstr = str_replace("Ã­", "i", $realstr);
        $realstr = str_replace("Ã³", "o", $realstr);
        $realstr = str_replace("Ãº", "u", $realstr);
        return $realstr;
    }

    public static function convert_special_char($str)
    {
        if ($str == null || count($str) <= 0) {
            return $str;
        }
        $realstr = htmlspecialchars($str, ENT_QUOTES);
        return $realstr;
    }

    public static function convert_pathtourl($str)
    {
        if ($str == null || count($str) <= 0) {
            return $str;
        }
        $realstr = str_replace(DIRECTORY_SEPARATOR, "/", $str);
        return $realstr;
    }

    public static function remove_repeatslash($str)
    {
        if ($str == null || count($str) <= 0) {
            return $str;
        }
        $realstr = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $str);
        for ($i = 0; $i < 2; $i++) {
            $realstr = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $realstr);
        }
        return $realstr;
    }

    /**
     * Metodo para generar el hash de un password en una serie de encriptaciones del Blast24
     * @param string $type
     * @param string $data1
     * @param string $data2
     * @return string hash del password
     */
    public static function create_passhash($type = "", $data1 = "", $data2 = "")
    {
        if ($type == 'send') {
            $hash = sha1($data1 . $data2);
            //		echo '<br/>send '.$data1.' - '.$data2;
        } else if ($type == 'receive') {
            $hash = strtoupper(sha1($data1 . "unabobada"));
            //		echo '<br/>receive '.$data1;
        } else if ($type == 'store') {
            $hash = strtoupper(sha1(sha1($data1 . $data2) . "unabobada"));
            //echo '<br/>store '.$data1.' - '.$data2;
        }
        return $hash;
    }

    /**
     * Metodo para escribir sobre archivos.
     * @param string $data El dato a escribir en el archivo.
     * @param string $pathFile La ubicacion fisica del archivo.
     * @param int $isNew 0 es para escribir sobre un archivo existente. 1 para crear uno nuevo.
     */
    public static function make_file($data, $pathFile, $isNew = 0)
    {
        $filesize = 0;
        if (file_exists($pathFile)) {
            if ($isNew) {
                unlink($pathFile);
            }
            $filesize = filesize($pathFile); //bytes
        }
        //$maxSize = 1 * 1024;//KB
        $maxSize = 1 * 1048576; //MB
        if ($filesize > $maxSize) {
            rename($pathFile, $pathFile . date("YmdHis"));
        }
        $fh = fopen($pathFile, 'a+') or die("Can't use file.<BR/>Need to apply read-write permissions.<BR/>$ sudo chmod 777 /var/www/s24/blast24/web/log/debug_file.txt or " . $pathFile);
        $arrStr = explode(";", $data);
        foreach ($arrStr as $str) {
            $str = date("Y-m-d H:i:s") . " # " . $str . "\n";
            fwrite($fh, $str);
        }
        fclose($fh);
    }

    /**
     * Metodo para escribir sobre archivos.
     * @param string $str El dato a escribir en el archivo.
     * @param int $isNew 0 es para escribir sobre un archivo existente. 1 para crear uno nuevo.
     * @param string $pathFile La ubicacion fisica del archivo.
     */
    public static function make_debug_file($str, $file, $line, $isNew = 0, $pathFile = "log/debug_file.txt")
    {
        $filesize = 0;
        if (file_exists($pathFile)) {
            if ($isNew) {
                unlink($pathFile);
            }
            $filesize = filesize($pathFile); //bytes
        }
        //$maxSize = 1 * 1024;//KB
        $maxSize = 1 * 1048576; //MB
        if ($filesize > $maxSize) {
            rename($pathFile, $pathFile . date("YmdHis"));
        }
        $fh = fopen($pathFile, 'a+') or die("Can't use file.<BR/>Need to apply read-write permissions.<BR/>$ sudo chmod 777 /var/www/s24/blast24/web/log/debug_file.txt or " . $pathFile);
        //	    $str = date("Y-m-d H:i:s")." # ".__FILE__." Linea: ".__LINE__."\n".$str."\n";
        //$str = date("H:i:s.m")." # ".__FILE__." Linea: ".__LINE__."\n--->".$str."\n\n";
        $str = date("H:i:s.m") . " # Linea: " . $line . " # " . $file . "\n--->" . $str . "\n\n";
        fwrite($fh, $str);
        fclose($fh);
    }

    public static function session_chainstring($nameSessionVar, $str)
    {
        $_SESSION[$nameSessionVar] .= $str . '*';
    }


    /**
     * Metodo para construir un UPDATE.
     * @param string $table nombre de la tabla a escribir
     * @param string $where condicion para actualizar
     * @param array $arrfieldscomma campos y valores tipo STRING, que requieren comma
     * @param array $arrfieldsnocomma campos y valores que no requieren comma
     * @return string consulta construida
      <code>
      include 'classes/Util.php';
      $table = "mi_tabla";
      $where = "(id = 0) and (tipo='cadena')";
      $arrfieldscomma = array('campo1' => 'valor1', 'campo2' => 'valor2', 'campo3' => 'valor3');
      $arrfieldsnocomma = array('campoA' => 'NOW()', 'campoB' => '2', 'campoC' => 'GET');
      echo Util::make_query_insert($table, $arrfieldscomma, $arrfieldsnocomma);
      </code>
     *
     */
    public static function make_query_update($table, $where, $arrfieldscomma, $arrfieldsnocomma)
    {
        $query = "UPDATE ";
        if ($table == null || strlen($table) < 1) {
            return "***Falta nombre de la tabla***";
        }
        if ($where == null || strlen($where) < 1) {
            return "***Falta WHERE id=?? del registro***";
        }
        $query .= $table . " SET ";
        $fields = "";
        foreach ($arrfieldscomma as $f => $v) {
            if (strlen($v) >= 1) {
                $fields .= " " . $f . " = '" . $v . "',";
            }
        }
        foreach ($arrfieldsnocomma as $f2 => $v2) {
            if ($v2 > 0) {
                $fields .= " " . $f2 . " = " . $v2 . ",";
            }
        }
        $fields = rtrim($fields, ",");
        $query .= $fields . " WHERE " . $where;
        return $query;
    }

    /**
     * Metodo para encriptar password
     */
    public static function make_hash_pass($pass)
    {
        $r = strtoupper(md5(md5($pass) . sha1($pass) . md5(sha1('---Mede--2020**llin'))));
        return $r;
    }

    public static function validate_key($key1, $random = '', $param = '')
    {
        $key2 = '';
        $response = false;
        if (strlen($param) > 0) {
            $key2 = sha1($param . $this->THE_KEY . $random);
            if ($key1 == $key2) {
                $response = true;
            }
        } else {
            $key2 = sha1($this->THE_KEY . $random);
            if ($key1 == $key2) {
                $response = true;
            }
        }
        return $response;
    }

    /** Checks is the provided email address is formally valid
     *  @param string $email email address to be checked
     *  @return true if the email is valid, false otherwise
     */
    public static function validate_email($email)
    {
        $regexp = "/^[a-z0-9]+([_\\.-][a-z0-9]+)*@([a-z0-9]+([\.-][a-z0-9]+)*)+\\.[a-z]{2,}$/i";
        if (preg_match($regexp, $email)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Metodo para generar un codigo aleatorio
     * @param int longitud del codigo
     * @return string Codigo generado
     */
    public static function generate_code($length)
    {
        $key = '';
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyz';
        $max = strlen($pattern) - 1;
    
        for ($i = 0; $i < $length; $i++) {
            $key .= $pattern[mt_rand(0, $max)];
        }
    
        return $key;
    }
    public static function rounding($numero, $decimales)
    {
        $factor = pow(10, $decimales);
        return (round($numero * $factor) / $factor);
    }

    public static function error_invalid_method_called()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '101', 'content' => ' Metodo no existe.')));
    }

    public static function error_invalid_authorization()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '102', 'content' => ' No se encuentra autorizado para ejecutar la operación.')));
    }

    public static function error_missing_data()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '103', 'content' => ' Faltan datos que son requeridos.')));
    }

    public static function error_general($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '000', 'content' => ' Ha ocurrido un error. ' . $description)));
    }

    public static function error_general2()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '0000', 'content' => ' Ha ocurrido un error 2. ')));
    }

    public static function info_general($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '0000', 'content' => ' Importante a tener en cuenta. ' . $description)));
    }

    public static function error_no_result()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '104', 'content' => ' Sin resultados.')));
    }

    public static function error_no_credits()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '105', 'content' => ' Creditos insuficientes.')));
    }

    public static function error_user_already_exist()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '106', 'content' => ' El correo ingresado ya lo utiliza otro usuario.')));
    }

    public static function error_wrong_data_login()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '107', 'content' => ' Usuario o Contraseña Incorrectos.')));
    }

    public static function error_wrong_email()
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '108', 'content' => ' Email incorrecto.')));
    }

    public static function error_sending_email($content = NULL)
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '109', 'content' => $content)));
    }

    public static function error_subirarchivo($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '112', 'content' => ' Ha ocurrido un error al subir el archivo. ' . $description)));
    }

    public static function error_registroduplicado($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '116', 'content' => ' La información a ingresar ya fue registrada anteriormente. ' . $description)));
    }

    public static function error_generaldelete($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '117', 'content' => ' No se puede eliminar el item seleccionado, ya que ha sido relacionado con otros registros previamente. ' . $description)));
    }

    public static function error_subirarchivoTam($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '118', 'content' => ' El peso del archivo exece al limite especifico. ' . $description)));
    }

    public static function error_existencia($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '119', 'content' => 'Lo sentimos, no contamos en el inventario con la cantidad que requieres. ' . $description)));
    }

    public static function cart_empty($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '120', 'content' => ' El carrito de compra está vacío.' . $description)));
    }

    public static function error_telefonoduplicado($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '121', 'content' => ' El número de teléfono ingresado ya existe en el sistema ' . $description)));
    }

    public static function error_documentoduplicado($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '122', 'content' => ' El número de documento ingresado ya existe en el sistema ' . $description)));
    }

    public static function error_estadooduplicado($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '123', 'content' => ' El estado que desea registrar ya ha sido guardado previamente, favor selecionar otro estado ' . $description)));
    }
    public static function error_prodcantidadnovalidad($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '124', 'content' => ' La cantidad y/o producto  no fue solicitado por el cliente. ' . $description)));
    }

    public static function error_documentonoexiste($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '125', 'content' => ' El número ingresado no existe por favor verifique ' . $description)));
    }

    public static function error_session_finished($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '126', 'content' => ' The session has expired. ' . $description)));
    }

    public static function error_cantidad_no_valida($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '127', 'content' => $description)));
    }

    public static function error_general_dian($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '999', 'content' => ' Ha ocurrido un error con la factura. ' . $description)));
    }

    public static function error_general_dian_campos_erroneos($description = '')
    {
        return array('output' => array('valid' => false, 'response' => array('code' => '1000', 'content' => ' Validación contiene errores en campos mandatorios. ' . $description)));
    }


    public static function date_now_server()
    {
        return 'DATE_ADD(NOW(),INTERVAL 0 HOUR)';
    }

    public static function date()
    {
        return date('Y-m-d H:i:m', time());
    }

    public static function getDateCurrently()
    {
        return date('Y-m-d');
    }

    public static function verify_user_app_access()
    {
        //se valida que pueda utilizar este tipo de acceso
        //para una futura conversion en un API solo se nececida validar daots de usuario
        if (!isset($_SESSION['session_user'])) {
            echo 'OPERACION NO DISPONIBLE. CODIGO 001. USUARIO SIN PRIVILEGIOS';
            die();
        }
        //se pregunta si tiene acceso a esta aplicacion
        if (isset($_SESSION['session_user']['application'])) {
            if (!(in_array(Util::get_app_id(), $_SESSION['session_user']['application']))) {
                echo 'OPERACION NO DISPONIBLE. CODIGO 002. APLICACION NO ASIGNADA';
                die();
            }
        } else {
            echo 'OPERACION NO DISPONIBLE. CODIGO 003. APLICACION NO AUTORIZADA';
            die();
        }
    }
    public static function verify_user_session()
    {
        return isset($_SESSION['session_user']) ? TRUE : FALSE;
    }


    /**
     * Funcion para el ingreso de la foto o archivo
     * @param type $id
     * @param type $field
     * @param type $table
     * @return type
     */
    public static function upload($id, $field, $table)
    {
        //Informacion del Archivo.
        $file_name = isset($_SESSION['pms_archivo']['nombrearchivo']) ? ($_SESSION['pms_archivo']['nombrearchivo']) : '';
        $file_type = isset($_SESSION['pms_archivo']['tipoarchivo']) ? ($_SESSION['pms_archivo']['tipoarchivo']) : '';
        $contenido = isset($_SESSION['pms_archivo']['contenidooarchivo']) ? ($_SESSION['pms_archivo']['contenidooarchivo']) : '';
        $file_size = isset($_SESSION['pms_archivo']['tamanio']) ? ($_SESSION['pms_archivo']['tamanio']) : '';
        $file_error = isset($_SESSION['pms_archivo']['error']) ? ($_SESSION['pms_archivo']['error']) : '';

        $peso = 1000000; //1MB

        $db = new DbConection();
        $pdo = $db->openConect();

        //Verifico si tiene foto asociada
        $q_1 = "SELECT COUNT(*)  FROM " . $db->getTable($table) . " WHERE $field = " . $id;
        $result_1 = $pdo->query($q_1);
        $c = $result_1->fetchColumn();
        if ($c == 0) {
            if ($file_name != '') {
                //Si va a actualizar y no tiene foto asociada y selecciona una imagen se procede a ingresar el registro
                if ($file_error == 0 && $file_size > 0 && $file_size < $peso) {
                    $q1 = "INSERT INTO " . $db->getTable($table) . " ($field, images_nombre,images_tipo,images_contenido) VALUES ('" . $id . "', '" . $file_name . "', '" . $file_type . "', '" . $contenido . "')";
                    $result = $pdo->query($q1);
                    if ($result) {
                        $_SESSION['pms_archivo']["nombrearchivo"] = "";
                        $_SESSION['pms_archivo']["tipoarchivo"] = "";
                        $_SESSION['pms_archivo']["contenidooarchivo"] = "";
                        $_SESSION['pms_archivo']["tamanio"] = "";
                        $_SESSION['pms_archivo']["error"] = "";
                        $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                    } else {
                        $_SESSION['session_user'] = NULL;
                        $arrjson = Util::error_subirarchivo();
                    }
                } else {
                    $_SESSION['session_user'] = NULL;
                    $arrjson = Util::error_subirarchivoTam();
                }
            }
        } else {
            if ($file_name != '') {
                //Aqui se actualizan los archivos que suben
                $q1 = "UPDATE  " . $db->getTable($table) . " SET images_nombre='" . $file_name . "' ,images_tipo='" . $file_type . "',images_contenido='" . $contenido . "' WHERE $field=" . $id;
                $result = $pdo->query($q1);
                if ($file_error == 0 && $file_size > 0 && $file_size < $peso) {
                    if ($result) {
                        $_SESSION['pms_archivo']["nombrearchivo"] = "";
                        $_SESSION['pms_archivo']["tipoarchivo"] = "";
                        $_SESSION['pms_archivo']["contenidooarchivo"] = "";
                        $_SESSION['pms_archivo']["tamanio"] = "";
                        $_SESSION['pms_archivo']["error"] = "";
                        $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                    } else {
                        $_SESSION['session_user'] = NULL;
                        $arrjson = Util::error_subirarchivo();
                    }
                } else {
                    $_SESSION['session_user'] = NULL;
                    $arrjson = Util::error_subirarchivoTam();
                }
            } else {
                $arrjson = array('output' => array('valid' => true));
            }
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * metodo para eliminar un archivo
     */
    public static function unlinkFile($file)
    {
        unlink($file);
    }

    public static function getInicialFactura()
    {
        return 'FFV-';
    }

    public static function getInicialFacturaVehiculo()
    {
        return 'VEH-';
    }

    public static function getIdClienteGenerico()
    {
        return 1;
    }

    public static function getIdCajaDefault()
    {
        return 1;
    }

    /**
     * Metodo para conocer el consecutivo de la factura
     */
    public static function getConsecutivoFactura()
    {
        return 40000;
    }


    /**
     * Metodo para obtener el valor maximo de la tabla
     */
    public static function getMaxIdFromTable($table)
    { // Fecha actual

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT MAX(id) as max FROM " . $db->getTable($table) . " WHERE YEAR(dtcreate) = YEAR(CURRENT_DATE()) ";
        $result = $pdo->query($q);
        $id = 0;
        foreach ($result as $valor) {
            $id = $valor['max'];
        }
        $db->closeConect();
        return $id;
    }

    /**
     * Metodo para obtener el valor maximo de la tabla
     */
    public static function getDataCajaById($id)
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_cashier') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        $arr = array();
        foreach ($result as $valor) {
            $arr[] = $valor;
        }
        $db->closeConect();
        return $arr;
    }

    /**
     * obtener la resolucion de la empresa y facturacion
     */
    public static function getResolucionOld()
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_resoluciones') . " LIMIT 1 ";
        $result = $pdo->query($q);
        $arr = array();
        foreach ($result as $valor) {
            $arr[] = $valor;
        }
        $resolucion = '';
        if (count($arr) > 0) {
            $resolucion =  "Resolución facturación " . $arr[0]['resolucion'] .
                " Fecha " . $arr[0]['fecha'] .
                " Vigencia " . $arr[0]['vigencia'] .
                " Autoriza número desde " . $arr[0]['desde_fac'] . " hasta " . $arr[0]['hasta_fac'];
        }
        $db->closeConect();
        return $resolucion;
    }


    /**
     * obtener la resolucion de la empresa y facturacion (DIAN)
     */
    public static function getResolucion($tabla)
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable($tabla) . " WHERE activo = 'si' LIMIT 1 ";
        $result = $pdo->query($q);
        $arr = array();
        foreach ($result as $valor) {
            $arr[] = $valor;
        }
        $resolucion = '';
        if (count($arr) > 0) {
            $resolucion =  "Resolución facturación " . $arr[0]['resolucion'] .
                " \n Fecha de Resolución " . $arr[0]['fecha_exp'] .
                " \n Autoriza número desde " . $arr[0]['desde'] . " hasta " . $arr[0]['hasta'];
        }
        $db->closeConect();
        return $resolucion;
    }


    /**
     * Metodo para obtener el consecutivo actual de la resolucion
     */
    public static function getConsecutivoResolucionDian($tabla)
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable($tabla) . " WHERE activo = 'si' LIMIT 1 ";
        $result = $pdo->query($q);
        $consecutivo = 0;
        if ($result) {
            foreach ($result as $valor) {
                $consecutivo = $valor['consecutivo_actual'];
            }
        }
        $db->closeConect();
        return $consecutivo;
    }

    /**
     * Metodo para obtener el consecutivo actual de la nota credito y nota debito
     */
    public static function getConsecutivoActualNotaCreditoNotaDebito($tabla)
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable($tabla) . " WHERE activo = 'si' LIMIT 1 ";
        $result = $pdo->query($q);
        $consecutivo = 0;
        if ($result) {
            foreach ($result as $valor) {
                $consecutivo = intval($valor['consecutivo_actual']) + 1;
            }
        }
        $db->closeConect();
        return $consecutivo;
    }

    /**
     * Metodo para obtener el prefijo actual de la resolucion
     */
    public static function getPrefijoResolucion($tabla)
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT prefijo FROM " . $db->getTable($tabla) . " WHERE activo = 'si' LIMIT 1 ";
        $result = $pdo->query($q);
        $prefijo = "";
        if ($result) {
            foreach ($result as $valor) {
                $prefijo = $valor['prefijo'];
            }
        }
        $db->closeConect();
        return $prefijo;
    }

    /**
     * Método para poder actualizar el consecutivo actual de la resolución vigente
     */
    public static function actualizarConsecutivoDIAN($tabla)
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "UPDATE " . $db->getTable($tabla) . "  SET consecutivo_actual = (consecutivo_actual + 1)  WHERE activo = 'si' LIMIT 1 ";
        $result = $pdo->query($q);
        if (!$result) {
            $arrjson = Util::error_general('Actualizando el consecutivo actual ');
        } else {
            $arrjson = array('output' => array('valid' => true, 'response' => true));
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Método para poder actualizar el consecutivo actual de la nota credito o nota debito
     */
    public static function actualizarConsNotaCredNotaDebDIAN($tabla)
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "UPDATE " . $db->getTable($tabla) . "  SET consecutivo_actual = (consecutivo_actual + 1)  WHERE activo = 'si' LIMIT 1 ";
        $result = $pdo->query($q);
        if (!$result) {
            $arrjson = Util::error_general('Actualizando el consecutivo actual ');
        } else {
            $arrjson = array('output' => array('valid' => true, 'response' => true));
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para validar que el consectivo que se consulta sea valida para la resolucion vigente para el comercio
     */
    public static function validarResolucionYFacturaDIAN($consecutivo, $tabla)
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable($tabla) . " WHERE activo = 'si' LIMIT 1 ";
        $result = $pdo->query($q);
        $desde = 0;
        $hasta = 0;
        $arr = array();
        foreach ($result as $valor) {
            $arr[] = $valor;
            $desde = intval($valor['desde']);
            $hasta = intval($valor['hasta']);
        }
        if (count($arr) === 0) {
            $arrjson = Util::error_general('No existe una resolución configurada en el sistema para el comercio');
        } else {

            // Se valida que la resolucion si este en el rango de facturas
            if ($consecutivo >= $desde && $consecutivo <= $hasta) {
                $arrjson = array('output' => array('valid' => true, 'response' => true));
            } else {
                $arrjson = Util::error_general('La factura que va a realizar no aplica para la resolución vigente para el comercio.');
            }
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * obtener la Configuracion de la empresa
     */
    public static function getConfiguracion()
    {

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_config') . " ORDER BY id DESC LIMIT 1 ";
        $result = $pdo->query($q);
        $arr = array();
        foreach ($result as $valor) {
            $arr[] = $valor;
        }
        $arrjson = array('output' => array('valid' => true, 'response' => $arr));
        $db->closeConect();
        return $arrjson;
    }

    public static function trace_log($rqst, $controlador = '')
    {
        $db = new DbConection();
        $pdo = $db->openConect();
        $op = isset($rqst['op']) ? $rqst['op'] : '';
        $usuario_id = 0;
        $administrador = '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $phpsessionid = isset($_REQUEST['PHPSESSID']) ? $_REQUEST['PHPSESSID'] : '';
        if (isset($_SESSION['session_user'])) {
            $usuario_id = $_SESSION['session_user']['id'];
            $administrador = $_SESSION['session_user']['tipo'];
        }
        $q = "INSERT INTO " . $db->getTable('tec_log_auditoria') . "  (dtcreate, ip, usuario_id, administrador, op, controlador, rqst, user_agent, phpsessionid) VALUES (" . Util::date_now_server() . ", '" . Util::get_real_ipaddress() . "', '" . $usuario_id . "', '" . $administrador . "', '" . $op . "', '" . $controlador . "', '" .  json_encode($rqst) . "', '" . $user_agent . "', '" . $phpsessionid . "') ";
        $pdo->query($q);
        $db->closeConect();
    }

    public static function trace_log_error($rqst, $controlador = '', $error= '')
    {
        $db = new DbConection();
        $pdo = $db->openConect();

        $error = json_encode(UTIL::remove_special_char($error), true);

        $op = isset($rqst['op']) ? $rqst['op'] : '';
        $usuario_id = 0;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $phpsessionid = isset($_REQUEST['PHPSESSID']) ? $_REQUEST['PHPSESSID'] : '';
        if (isset($_SESSION['session_user'])) {
            $usuario_id = $_SESSION['session_user']['id'];
        }
        $q = "INSERT INTO " . $db->getTable('tec_log_errores') . " (dtcreate, ip, usuario_id, op, controlador, rqst, user_agent, phpsessionid, error) VALUES (" . Util::date_now_server() . ", '" . Util::get_real_ipaddress() . "', '" . $usuario_id . "', '" . $op . "', '" . $controlador . "', '" .  json_encode($rqst) . "', '" . $user_agent . "', '" . $phpsessionid . "', '" . $error . "') ";
        $pdo->query($q);
        $db->closeConect();
    }

    /**
     * Calcular la fecha actual con una fecha que se quiera validar el numero de dias trasncurridos
     */
    public static function getCalcularDiasEntreFechaActual($fechaFin)
    {
        date_default_timezone_set('America/Mexico_City');
        $fecha = Date("Y-m-d"); // Fecha actual

        $dt = new DateTime($fechaFin);
        $fechaFinal =  $dt->format('Y-m-d');

        $dias    = (strtotime($fecha) - strtotime($fechaFinal)) / 86400;
        $dias = abs($dias);
        $dias = floor($dias);

        return $dias;
    }

    public static function calcularMinutosEntreDosFechas($fecha_i, $fecha_f)
    {
        if ($fecha_i != "" && $fecha_f != "") {
            $minutos = (strtotime($fecha_i) - strtotime($fecha_f)) / 60;
            $minutos = abs($minutos);
            $minutos = floor($minutos);
            return $minutos;
        }
        return 0;
    }

    public static function calcularHorasEntreDosFechas($fecha_i, $fecha_f)
    {
        return Util::calcularMinutosEntreDosFechas($fecha_i, $fecha_f) / 60;
    }

    /**
     * Metodo para actualizar la cantidad actual del inventario del producto actual seleccionado
     */
    public static function actualizarInventario($rqst)
    {

        $tec_product_id = isset($rqst['tec_product_id']) ? intval($rqst['tec_product_id']) : 0;
        $cantidad = isset($rqst['cantidad']) ? intval($rqst['cantidad']) : 0;
        $caja = isset($rqst['caja']) ? ($rqst['caja']) : 'no';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($tec_product_id > 0 && $cantidad > 0) {

            if ($caja == 'si') {
                $qUpdateProd = "UPDATE " . $db->getTable('tec_products') . "
                SET cant_actual = (cant_actual) - $cantidad
                WHERE id = " . $tec_product_id;
            } else {
                $qUpdateProd = "UPDATE " . $db->getTable('tec_products') . "
                SET cant_actual = (cant_actual) + $cantidad
                WHERE id = " . $tec_product_id;
            }


            $result = $pdo->query($qUpdateProd);
            if (!$result) {
                $db->closeConect();
                return $arrjson = Util::error_general('Actualizando inventario');
            }
            $arrjson = array('output' => array('valid' => true, 'response' => $tec_product_id));
        } else {
            $arrjson = Util::error_missing_data();
        }

        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para validar la información de domicilio ingresado con la venta a realizar
     * Con objetivo que sea correcto lo que el cliente pidió
     */
    public static function validarVentaDomicilio($rqst)
    {

        $tec_product_id = isset($rqst['tec_product_id']) ? intval($rqst['tec_product_id']) : 0;
        $cantidad = isset($rqst['cantidad']) ? ($rqst['cantidad']) : 0;
        $tec_delivery_id = isset($rqst['tec_delivery_id']) ? intval($rqst['tec_delivery_id']) : 0;

        $formatted_number = number_format((float)$cantidad, 2, '.', '');

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($tec_product_id > 0 && $cantidad > 0 &&  $tec_delivery_id > 0) {

            $q = "SELECT * FROM " . $db->getTable('tec_delivery') . " WHERE id = " . $tec_delivery_id;
            $result = $pdo->query($q);
            $tec_delivery_id = 0;
            $tec_customer_id = 0;
            if ($result) {
                foreach ($result as $valor) {
                    $tec_delivery_id = $valor['id'];
                    $tec_customer_id = $valor['tec_customer_id'];
                }

                if ($tec_delivery_id == 0) {
                    $db->closeConect();
                    return $arrjson = Util::error_general('Número de domicilio no existe');
                } else {
                    // Validamos el id del producto y la cantidad
                    $qProd = "SELECT * FROM " . $db->getTable('tec_delivery_product') . " WHERE tec_delivery_id = " . $tec_delivery_id . " AND cantidad = '$formatted_number' AND tec_product_id = $tec_product_id";
                    $result = $pdo->query($qProd);
                    $arr = array();
                    foreach ($result as $valor) {
                        $arr[] = $valor;
                    }
                    // print_r($qProd);
                    // exit();
                    if (count($arr) == 0) {
                        $db->closeConect();
                        return $arrjson = Util::error_prodcantidadnovalidad();
                    }
                    $arrjson = array('output' => array('valid' => true, 'response' => $tec_customer_id));
                }
            } else {
                $arrjson = Util::error_general('Consultando la información del domicilio');
            }
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function nombreImpresoraPOS()
    {
        //return 'Generic';
        if (isset($_SESSION['session_user']) && isset($_SESSION['session_user']['caja'])) {
            return ($_SESSION['session_user']['caja'][0]['nombre_impresora']);
        } else {
            return null;
        }
    }

    public static function infoSpiderSoftware()
    {
        return "Spider Software POS Inteligente" . "\n" . " www.spidersoftware.co " . "\n";
    }

    public static function getDiasEntreDosFechas($f1, $f2)
    {
        if ($f1 != "" && $f2 != "") {
            $fecha1 = new DateTime($f1); // 2017-08-01
            $fecha2 = new DateTime($f2);
            $diff = $fecha1->diff($fecha2);
            return $diff->days;
        }
        return null;
    }



    /*/**----------------------------------------------------------------------------------------------------
    * *                    logica para la Facturación Elctrónica
    *-------------------------------------------------------------------------------------------------------**/

    public static function apiDIAN()
    {
        return "https://mtjd.apifacturacionelectronica.xyz/api/ubl2.1";
    }

    public static function codigoComercio()
    {
        return "afb02602-8077-4a87-be79-e8fb58186982";
    }

    public static function tokenComercioDIAN()
    {
        // Pruebas pos spidersoftware
        return "FfnCtpuVJ61AzC9RRM4MAuToO8Xh0tS7bXPOxCr6fvEK1tSkFRAopaJ1GgdohpmBvmlzWENaxGYanbZN";
    }

    public static function rutaCatalogoVpfeDIAN()
    {
        return "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=";
    }

    /**---------------------------------------------------------------------------------------
     **                           callApiDIAN
     *?  Método para llamar una api con curl
     *@param method type
     *@param url type
     *@return result
     *---------------------------------------------------------------------------------------**/
    public static function callApiDIAN($method, $url, $data)
    {
        $curl = curl_init();
        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }
        // OPTIONS:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . Util::tokenComercioDIAN(),
            'Content-Type: application/json',
            'Accept: application/json',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // EXECUTE:
        $result = curl_exec($curl);

        if (!$result) {
            die("Connection Failure");
        }
        curl_close($curl);
        return $result;
    }

    /**
     * Método para calcular porcentaje de IVA
     */
    public static function getPorcentajeIva($porcentaje)
    {
        if (intval($porcentaje) > 0) {
            switch (intval($porcentaje)) {
                case 19:
                    $iva = 1.19;
                    break;
                case 10:
                    $iva = 1.1;
                    break;
                case 5:
                    $iva = 1.05;
                    break;
            }
            return $iva;
        }
    }

    /** Método calcular el valor del iva de un producto */
    public static function calcularValorIvaProducto($precio, $porcentaje)
    {
        if (intval($porcentaje) > 0 && floatval($precio) > 0) {
            switch (intval($porcentaje)) {
                case 19:
                    $iva = 19;
                    break;
                case 10:
                    $iva = 10;
                    break;
                case 5:
                    $iva = 5;
                    break;
            }
            return ($precio * $iva) / 100;
        } else {
            return 0;
        }
    }

    /**---------------------------------------------------------------------------------------
     **                           callApiDIAN
     *?  Método para construir el JSON el cual se envia a la DIAN para validar la factura
     *@param id Identificador de la venta
     *@return result
     *---------------------------------------------------------------------------------------**/
    public static function construirJsonFacturaDIAN($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {

            // Informacion de la venta y cliente
            $qtec_sales_electronic = "SELECT tec_sales_electronic.*,
            tec_customers_electronic.nombre as cliente,
            tec_customers_electronic.email as email,
            tec_customers_electronic.identificacion_num as identificacion_num,
            tec_customers_electronic.identificacion_tipo as identificacion_tipo,
            tec_customers_electronic.nombre_comercial as nombre_comercial,
            tec_customers_electronic.telefono as telefono,
            tec_customers_electronic.direccion as direccion,
            tec_customers_electronic.registro_mercantil,
            tec_customers_electronic.tec_type_organization_id,
            tec_customers_electronic.tec_type_liability_id,
            tec_customers_electronic.tec_type_regimen_id
            FROM " . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
            WHERE tec_sales_electronic.tec_customers_electronic_id = tec_customers_electronic.id AND  tec_sales_electronic.id = " . $id;
            $resultOrder = $pdo->query($qtec_sales_electronic);
            $order = array();
            foreach ($resultOrder as $valor1) {
                $order[] = $valor1;
            }

            if (count($order) == 0) {
                return Util::error_general('No existe información del cliente y/o venta.');
                $db->closeConect();
            }

            // Información del cliente
            $number = $order[0]['factura'];
            $identification_number = $order[0]['identificacion_num'];
            $email = $order[0]['email'];
            $name = $order[0]['cliente'];
            $phone = $order[0]['telefono'];
            $address = $order[0]['direccion'];
            $identificacion_tipo = $order[0]['identificacion_tipo'];
            $trade_name = $order[0]['nombre_comercial'];

            $registro_mercantil = $order[0]['registro_mercantil'];
            $tec_type_organization_id = $order[0]['tec_type_organization_id'];
            $tec_type_liability_id = $order[0]['tec_type_liability_id'];
            $tec_type_regimen_id = $order[0]['tec_type_regimen_id'];
            $due_date = $order[0]['due_date'];
            $duration_measure = Util::getDiasEntreDosFechas($due_date, Util::getDateCurrently()); // Se debe calcular

            $configuracion = Util::getConfiguracion();
            $configuracion = $configuracion['output']['response'][0];
            $notas_facturacion_electronica = $configuracion['notas_factura_electronica'] ? $configuracion['notas_factura_electronica'] : '';

            if ($tec_type_organization_id == 0) {
                return Util::error_general('El cliente no tiene un código de tipo de organización');
            }
            if ($tec_type_liability_id == 0) {
                return Util::error_general('El cliente no tiene un código de responsabilidad');
            }
            if ($tec_type_regimen_id == 0) {
                return Util::error_general('El cliente no tiene código de régimen');
            }

            switch ($identificacion_tipo) {
                case 'NIT':
                    $type_document_identification_id = 6;
                    break;
                case 'CE':
                    $type_document_identification_id = 5;
                    break;
                default:
                    $type_document_identification_id = 3; // Cédula de ciudadanía
                    break;
            }

            // Información de productos
            $productos = "SELECT tec_sales_tec_products_electronic.*,tec_products.nombre_prod,tec_products.codigo, tec_products.impuesto as iva_producto
            FROM " . $db->getTable('tec_sales_tec_products_electronic') . "," . $db->getTable('tec_products') . "
            WHERE tec_sales_tec_products_electronic.tec_product_id = tec_products.id AND tec_sales_tec_products_electronic.tec_sale_id = " . $id . " ORDER BY tec_sales_tec_products_electronic.id DESC";
            $resultProd = $pdo->query($productos);

            // Declaramos la variables
            $arrProd = array();
            $totalPedido = 0;
            $totalIva = 0;
            $subTotalPedido = 0;

            // Total Impuesto generado (IVA)
            $ivaTotal = 0;

            $invoice_lines = array();
            $tax_exclusive_amount = 0; // Sumatoria de todos los items de impuestos, pero del campo (taxable_amount) en tax_totals
            foreach ($resultProd as $valor) {

                if ($valor['precio_unitario'] > 0) {

                    $ivaTotal += $valor['impuesto'];

                    $arrProd[] = $valor;
                    $totalPedido += $valor['total'];
                    $totalIva +=  ($valor['subtotal'] -  $valor['precio_unitario']) *  $valor['cantidad'];
                    $line_extension_amount = $valor['precio_unitario'] * $valor['cantidad'];

                    if (intval($valor['impuesto']) == 0) {
                        $invoice_lines[] = array(
                            "unit_measure_id" => 642, //  cada EA (DEFAULT)
                            "invoiced_quantity" =>  $valor['cantidad'], // "1.000000",
                            "line_extension_amount" => $line_extension_amount, // "300000.00",
                            //"line_extension_amount" => round($valor['precio_unitario'],2), // "300000.00", DUDAAAAAAAAAAAAAA
                            "free_of_charge_indicator" => false,
                            "description" => $valor['nombre_prod'],
                            "code" =>  $valor['codigo'],
                            "type_item_identification_id" => 4, // Estándar de adopción del contribuyente (DEFAULT)
                            "price_amount" => $valor['precio_unitario'],
                            "base_quantity" => "1.000000"
                        );
                    } else {
                        $invoice_lines[] = array(
                            "unit_measure_id" => 642, //  cada EA (DEFAULT)
                            "invoiced_quantity" =>  $valor['cantidad'], // "1.000000",
                            "line_extension_amount" => round($line_extension_amount, 2), // "300000.00",
                            "free_of_charge_indicator" => false,
                            "tax_totals" => [
                                array(
                                    "tax_id" => 1, // IVA (DEFAULT)
                                    "tax_amount" => $valor['impuesto'], // Valor del IVA del producto
                                    "taxable_amount" => round($line_extension_amount, 2), // "300000.00",
                                    "percent" =>  $valor['iva'], // Porcentaje de IVA
                                ),
                            ],
                            "description" => $valor['nombre_prod'],
                            "code" =>  $valor['codigo'],
                            "type_item_identification_id" => 4, // Estándar de adopción del contribuyente (DEFAULT)
                            "price_amount" => $valor['precio_unitario'],
                            "base_quantity" => "1.000000"
                        );
                        $tax_exclusive_amount +=  $line_extension_amount;
                    }
                }
            }
            $data_array =  array(
                "number" => $number,
                "sync"   => true,
                "type_document_id"  => 1, // Factura electrónica de venta (Default)
                "due_date" => $due_date, // Fecha de Vencimiento de la Factura
                "payment_forms" => [
                    array(
                        "payment_form_id" => 1, // Código de forma de pago (1 Contado)
                        "payment_method_id" => 10, // Código del método de pago (10, Efectivo)
                        "payment_due_date" => $due_date, // Fecha de vencimiento del pago
                        "duration_measure" => $duration_measure //Medida de duración en dias
                    )
                ],
                "notes" => [
                    array(
                        "text" => $notas_facturacion_electronica
                    )
                ],
                // "resolution_id"  => 8, // PRUEBAS
                "resolution_id"  => 15, // Produccion
                "customer"  => array(
                    "identification_number" => $identification_number,
                    "name"  => $name,
                    "email" => $email,
                    "phone" => $phone,
                    "address" => $address,
                    "country_id" => 46, // Pais
                    "type_document_identification_id" => $type_document_identification_id,
                    "trade_name" => $trade_name,
                    "municipality_id" => 1, // MEDELLIN
                    "type_organization_id" => $tec_type_organization_id,
                    "type_regime_id" => $tec_type_regimen_id,
                    "type_liability_id" => $tec_type_liability_id,
                    "merchant_registration" => $registro_mercantil,
                ),
                "legal_monetary_totals" => array(
                    // Estos son los valores totales generales
                    "line_extension_amount" => round($totalPedido, 2), // "300000.00",
                    "tax_exclusive_amount" => round($tax_exclusive_amount, 2),
                    "tax_inclusive_amount" => round(($totalPedido + $totalIva), 2), // "357000.00",
                    "allowance_total_amount" => "0.00",
                    "charge_total_amount" => "0.00", // No lo manejamos
                    "payable_amount" => round(($totalPedido + $totalIva), 2) // "357000.00"
                ),
                "invoice_lines" => $invoice_lines
            );

            /*   print_r(json_encode($data_array));
             exit();*/
            $db->closeConect();
            return Util::enviarFacturaDIAN($data_array);
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**---------------------------------------------------------------------------------------
     **                           jsonNotaCreditoFacturacionElectronica
     *?  Método para construir el JSON  de nota credito para enviar a la DIAN
     *@param
     *@return result
     *---------------------------------------------------------------------------------------**/
    public static function jsonNotaCreditoFacturacionElectronica($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $reintento = isset($rqst['reintento']) ? ($rqst['reintento']) : 'no';
        $tec_credit_note_electronic_id = isset($rqst['tec_credit_note_electronic_id']) ? intval($rqst['tec_credit_note_electronic_id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {

            // Informacion de la factura electronica y cliente
            $qtec_sales_electronic = "SELECT tec_sales_electronic.*,
            tec_customers_electronic.nombre as cliente,
            tec_customers_electronic.email as email,
            tec_customers_electronic.identificacion_num as identificacion_num,
            tec_customers_electronic.identificacion_tipo as identificacion_tipo,
            tec_customers_electronic.nombre_comercial as nombre_comercial,
            tec_customers_electronic.telefono as telefono,
            tec_customers_electronic.direccion as direccion,
            tec_customers_electronic.registro_mercantil,
            tec_customers_electronic.tec_type_organization_id,
            tec_customers_electronic.tec_type_liability_id,
            tec_customers_electronic.tec_type_regimen_id
            FROM " . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
            WHERE tec_sales_electronic.tec_customers_electronic_id = tec_customers_electronic.id AND  tec_sales_electronic.id = " . $id;
            $resultOrder = $pdo->query($qtec_sales_electronic);
            $order = array();
            foreach ($resultOrder as $valor1) {
                $order[] = $valor1;
            }
            // Información del cliente
            $number = $order[0]['inicial_factura'] . $order[0]['factura'];
            $identification_number = $order[0]['identificacion_num'];
            $email = $order[0]['email'];
            $name = $order[0]['cliente'];
            $phone = $order[0]['telefono'];
            $address = $order[0]['direccion'];
            $identificacion_tipo = $order[0]['identificacion_tipo'];
            $trade_name = $order[0]['nombre_comercial'];
            $issue_date = $order[0]['issue_date'];
            $uuid = $order[0]['uuid'];

            $registro_mercantil = $order[0]['registro_mercantil'];
            $tec_type_organization_id = $order[0]['tec_type_organization_id'];
            $tec_type_liability_id = $order[0]['tec_type_liability_id'];
            $tec_type_regimen_id = $order[0]['tec_type_regimen_id'];

            if ($tec_type_organization_id == 0) {
                return Util::error_general('El cliente no tiene un código de tipo de organización');
            }
            if ($tec_type_liability_id == 0) {
                return Util::error_general('El cliente no tiene un código de responsabilidad');
            }
            if ($tec_type_regimen_id == 0) {
                return Util::error_general('El cliente no tiene código de régimen');
            }

            switch ($identificacion_tipo) {
                case 'NIT':
                    $type_document_identification_id = 6;
                    break;
                case 'CE':
                    $type_document_identification_id = 5;
                    break;
                default:
                    $type_document_identification_id = 3; // Cédula de ciudadanía
                    break;
            }

            $consecutivo = Util::getConsecutivoActualNotaCreditoNotaDebito('tec_dian_consecutive_note_credite');

            // Se valida el rango de consecutivo de nota credito
            $q = "SELECT * FROM " . $db->getTable('tec_dian_consecutive_note_credite') . " WHERE activo = 'si' LIMIT 1 ";
            $result = $pdo->query($q);
            $desde = 0;
            $hasta = 0;
            $arrConsecutivo = array();
            foreach ($result as $valor) {
                $arrConsecutivo[] = $valor;
                $desde = intval($valor['desde']);
                $hasta = intval($valor['hasta']);
            }
            if (count($arrConsecutivo) === 0) {
                $db->closeConect();
                $arrjson = Util::error_general('No existe una rango de consecutivos configurados');
            } else {

                // Se valida que si este en el rango de consecutivo
                if ($consecutivo > $desde && $consecutivo > $hasta) {
                    $db->closeConect();
                    return Util::error_general('La nota crédito que va a realizar no aplica para el rango de consecutivos configurados para el comercio.');
                }
            }


            // Información de productos de la nota credito
            $productos = "SELECT tec_credit_note_electronic_tec_products.*,tec_products.nombre_prod,tec_products.codigo, tec_products.impuesto as iva_producto
            FROM " . $db->getTable('tec_credit_note_electronic_tec_products') . "," . $db->getTable('tec_products') . "
            WHERE tec_credit_note_electronic_tec_products.tec_product_id = tec_products.id AND
            tec_credit_note_electronic_tec_products.tec_credit_note_electronic_id = " . $tec_credit_note_electronic_id . "
            ORDER BY tec_credit_note_electronic_tec_products.id DESC";
            $resultProd = $pdo->query($productos);

            // Declaramos la variables
            $arrProd = array();
            $totalPedido = 0;
            $totalIva = 0;
            $subTotalPedido = 0;

            $tax_exclusive_amount = 0; // Sumatoria de todos los items de impuestos, pero del campo (taxable_amount) en tax_totals
            foreach ($resultProd as $valor) {
                $arrProd[] = $valor;
                $totalPedido += $valor['total'];
                $totalIva +=  ($valor['subtotal'] -  $valor['precio_unitario']) *  $valor['cantidad'];
                $line_extension_amount = round($valor['precio_unitario'] * $valor['cantidad'], 2);

                if (intval($valor['iva']) == 0) {
                    $credit_note_lines[] = array(
                        "unit_measure_id" => 642, //  cada EA (DEFAULT)
                        "invoiced_quantity" =>  $valor['cantidad'], // "1.000000",
                        //"line_extension_amount" => $valor['precio_unitario'], // "300000.00"
                        "line_extension_amount" => $line_extension_amount,
                        "free_of_charge_indicator" => false,
                        "description" => $valor['nombre_prod'],
                        "code" =>  $valor['codigo'],
                        "type_item_identification_id" => 4, // Estándar de adopción del contribuyente (DEFAULT)
                        "price_amount" => $valor['precio_unitario'],
                        "base_quantity" => "1.000000"
                    );
                } else {
                    $credit_note_lines[] = array(
                        "unit_measure_id" => 642, //  cada EA (DEFAULT)
                        "invoiced_quantity" =>  $valor['cantidad'], // "1.000000",
                        "line_extension_amount" => $line_extension_amount, // "300000.00",
                        "free_of_charge_indicator" => false,
                        "tax_totals" => [
                            array(
                                "tax_id" => 1, // IVA (DEFAULT)
                                "tax_amount" => $valor['impuesto'], // Valor del IVA del producto
                                "taxable_amount" => $line_extension_amount, // "300000.00",
                                "percent" =>  $valor['iva'], // Porcentaje de IVA
                            ),
                        ],
                        "description" => $valor['nombre_prod'],
                        "code" =>  $valor['codigo'],
                        "type_item_identification_id" => 4, // Estándar de adopción del contribuyente (DEFAULT)
                        "price_amount" => $valor['precio_unitario'],
                        "base_quantity" => "1.000000"
                    );
                    $tax_exclusive_amount +=  $line_extension_amount;
                }
            }
            $data_array =  array(
                "billing_reference"  => array(
                    "number" => $number,
                    "uuid" => $uuid,
                    "issue_date" => $issue_date
                ),
                "discrepancy_response" => array(
                    "correction_concept_id" => 2 // Concepto por el cual se va hacer  "Anulación de factura electrónica"
                ),
                "number" => $consecutivo + 1,
                "sync" => true,
                "send" => true,
                "type_document_id" => 5, // Default Nota de crédito electrónica
                "resolution_id"  => 12, // Produccion
                "customer"  => array(
                    "identification_number" => $identification_number,
                    "name"  => $name,
                    "email" => $email,
                    "phone" => $phone,
                    "address" => $address,
                    "country_id" => 46, // Pais
                    "type_document_identification_id" => $type_document_identification_id,
                    "trade_name" => $trade_name,
                    "municipality_id" => 1, // MEDELLIN
                    "type_organization_id" => $tec_type_organization_id,
                    "type_regime_id" => $tec_type_regimen_id,
                    "type_liability_id" => $tec_type_liability_id,
                    "merchant_registration" => $registro_mercantil,
                ),
                "legal_monetary_totals" => array(
                    // Estos son los valores totales generales
                    "line_extension_amount" => $totalPedido, // "300000.00",
                    "tax_exclusive_amount" => $tax_exclusive_amount, // "300000.00",
                    "tax_inclusive_amount" => ($totalPedido + $totalIva), // "357000.00",
                    "allowance_total_amount" => "0.00",
                    "charge_total_amount" => "0.00", // No lo manejamos
                    "payable_amount" => ($totalPedido + $totalIva) // "357000.00"
                ),
                "credit_note_lines" => $credit_note_lines
            );

            /* print_r(json_encode($data_array));
            exit();*/
            $db->closeConect();
            return Util::enviarNotaCreditoDIAN($data_array);
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**---------------------------------------------------------------------------------------
     **                           jsonNotaDebitoFacturacionElectronica
     *?  Método para construir el JSON  de nota debito para enviar a la DIAN
     *@param
     *@return result
     *---------------------------------------------------------------------------------------**/
    public static function jsonNotaDebitoFacturacionElectronica($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $tec_debit_note_electronic_id = isset($rqst['tec_debit_note_electronic_id']) ? intval($rqst['tec_debit_note_electronic_id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {

            // Informacion de la factura electronica y cliente
            $qtec_sales_electronic = "SELECT tec_sales_electronic.*,
            tec_customers_electronic.nombre as cliente,
            tec_customers_electronic.email as email,
            tec_customers_electronic.identificacion_num as identificacion_num,
            tec_customers_electronic.identificacion_tipo as identificacion_tipo,
            tec_customers_electronic.nombre_comercial as nombre_comercial,
            tec_customers_electronic.telefono as telefono,
            tec_customers_electronic.direccion as direccion,
            tec_customers_electronic.registro_mercantil,
            tec_customers_electronic.tec_type_organization_id,
            tec_customers_electronic.tec_type_liability_id,
            tec_customers_electronic.tec_type_regimen_id
            FROM " . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
            WHERE tec_sales_electronic.tec_customers_electronic_id = tec_customers_electronic.id AND  tec_sales_electronic.id = " . $id;
            $resultOrder = $pdo->query($qtec_sales_electronic);
            $order = array();
            foreach ($resultOrder as $valor1) {
                $order[] = $valor1;
            }
            // Información del cliente
            $number = $order[0]['inicial_factura'] . $order[0]['factura'];
            $identification_number = $order[0]['identificacion_num'];
            $email = $order[0]['email'];
            $name = $order[0]['cliente'];
            $phone = $order[0]['telefono'];
            $address = $order[0]['direccion'];
            $identificacion_tipo = $order[0]['identificacion_tipo'];
            $trade_name = $order[0]['nombre_comercial'];
            $issue_date = $order[0]['issue_date'];
            $uuid = $order[0]['uuid'];

            $registro_mercantil = $order[0]['registro_mercantil'];
            $tec_type_organization_id = $order[0]['tec_type_organization_id'];
            $tec_type_liability_id = $order[0]['tec_type_liability_id'];
            $tec_type_regimen_id = $order[0]['tec_type_regimen_id'];

            if ($tec_type_organization_id == 0) {
                return Util::error_general('El cliente no tiene un código de tipo de organización');
            }
            if ($tec_type_liability_id == 0) {
                return Util::error_general('El cliente no tiene un código de responsabilidad');
            }
            if ($tec_type_regimen_id == 0) {
                return Util::error_general('El cliente no tiene código de régimen');
            }

            switch ($identificacion_tipo) {
                case 'NIT':
                    $type_document_identification_id = 6;
                    break;
                case 'CE':
                    $type_document_identification_id = 5;
                    break;
                default:
                    $type_document_identification_id = 3; // Cédula de ciudadanía
                    break;
            }

            $consecutivo = Util::getConsecutivoActualNotaCreditoNotaDebito('tec_dian_consecutive_note_debit');

            // Se valida el rango de consecutivo de nota debito
            $q = "SELECT * FROM " . $db->getTable('tec_dian_consecutive_note_debit') . " WHERE activo = 'si' LIMIT 1 ";
            $result = $pdo->query($q);
            $desde = 0;
            $hasta = 0;
            $arrConsecutivo = array();
            foreach ($result as $valor) {
                $arrConsecutivo[] = $valor;
                $desde = intval($valor['desde']);
                $hasta = intval($valor['hasta']);
            }
            if (count($arrConsecutivo) === 0) {
                $db->closeConect();
                $arrjson = Util::error_general('No existe una rango de consecutivos configurados');
            } else {

                // Se valida que si este en el rango de consecutivo
                if ($consecutivo > $desde && $consecutivo > $hasta) {
                    $db->closeConect();
                    return Util::error_general('La nota débito que va a realizar no aplica para el rango de consecutivos configurados para el comercio.');
                }
            }


            // Información de productos de la nota debito
            $productos = "SELECT tec_debit_note_electronic_tec_products.*,tec_products.nombre_prod,tec_products.codigo, tec_products.impuesto as iva_producto
            FROM " . $db->getTable('tec_debit_note_electronic_tec_products') . "," . $db->getTable('tec_products') . "
            WHERE tec_debit_note_electronic_tec_products.tec_product_id = tec_products.id AND
            tec_debit_note_electronic_tec_products.tec_debit_note_electronic_id = " . $tec_debit_note_electronic_id . "
            ORDER BY tec_debit_note_electronic_tec_products.id DESC";
            $resultProd = $pdo->query($productos);

            // Declaramos la variables
            $arrProd = array();
            $totalPedido = 0;
            $totalIva = 0;
            $subTotalPedido = 0;

            $tax_exclusive_amount = 0; // Sumatoria de todos los items de impuestos, pero del campo (taxable_amount) en tax_totals
            foreach ($resultProd as $valor) {
                $arrProd[] = $valor;
                $totalPedido += $valor['total'];
                $totalIva +=  ($valor['subtotal'] -  $valor['precio_unitario']) *  $valor['cantidad'];
                $line_extension_amount = $valor['precio_unitario'] * $valor['cantidad'];

                if (intval($valor['iva']) == 0) {
                    $debit_note_lines[] = array(
                        "unit_measure_id" => 642, //  cada EA (DEFAULT)
                        "invoiced_quantity" =>  $valor['cantidad'], // "1.000000",
                        //"line_extension_amount" => $valor['precio_unitario'], // "300000.00"
                        "line_extension_amount" => $line_extension_amount,
                        "free_of_charge_indicator" => false,
                        "description" => $valor['nombre_prod'],
                        "code" =>  $valor['codigo'],
                        "type_item_identification_id" => 4, // Estándar de adopción del contribuyente (DEFAULT)
                        "price_amount" => $valor['precio_unitario'],
                        "base_quantity" => "1.000000"
                    );
                } else {
                    $debit_note_lines[] = array(
                        "unit_measure_id" => 642, //  cada EA (DEFAULT)
                        "invoiced_quantity" =>  $valor['cantidad'], // "1.000000",
                        "line_extension_amount" => $line_extension_amount, // "300000.00",
                        "free_of_charge_indicator" => false,
                        "tax_totals" => [
                            array(
                                "tax_id" => 1, // IVA (DEFAULT)
                                "tax_amount" => $valor['impuesto'], // Valor del IVA del producto
                                "taxable_amount" => $line_extension_amount, // "300000.00",
                                "percent" =>  $valor['iva'], // Porcentaje de IVA
                            ),
                        ],
                        "description" => $valor['nombre_prod'],
                        "code" =>  $valor['codigo'],
                        "type_item_identification_id" => 4, // Estándar de adopción del contribuyente (DEFAULT)
                        "price_amount" => $valor['precio_unitario'],
                        "base_quantity" => "1.000000"
                    );
                    $tax_exclusive_amount +=  $line_extension_amount;
                }
            }
            $data_array =  array(
                "billing_reference"  => array(
                    "number" => $number,
                    "uuid" => $uuid,
                    "issue_date" => $issue_date
                ),
                "discrepancy_response" => array(
                    "correction_concept_id" => 10 // Concepto por el cual se va hacer  "Otros"
                ),
                "number" => $consecutivo + 1,
                "sync" => true,
                "send" => true,
                "type_document_id" => 6, // Default Nota de debito electrónica
                "resolution_id"  => 13, // Produccion
                "customer"  => array(
                    "identification_number" => $identification_number,
                    "name"  => $name,
                    "email" => $email,
                    "phone" => $phone,
                    "address" => $address,
                    "country_id" => 46, // Pais
                    "type_document_identification_id" => $type_document_identification_id,
                    "trade_name" => $trade_name,
                    "municipality_id" => 1, // MEDELLIN
                    "type_organization_id" => $tec_type_organization_id,
                    "type_regime_id" => $tec_type_regimen_id,
                    "type_liability_id" => $tec_type_liability_id,
                    "merchant_registration" => $registro_mercantil,
                ),
                "requested_monetary_totals" => array(
                    // Estos son los valores totales generales
                    "line_extension_amount" => $totalPedido, // "300000.00",
                    "tax_exclusive_amount" => $tax_exclusive_amount, // "300000.00",
                    "tax_inclusive_amount" => ($totalPedido + $totalIva), // "357000.00",
                    "allowance_total_amount" => "0.00",
                    "charge_total_amount" => "0.00", // No lo manejamos
                    "payable_amount" => ($totalPedido + $totalIva) // "357000.00"
                ),
                "debit_note_lines" => $debit_note_lines
            );
            /*
            print_r(json_encode( $data_array ));
            exit(); */
            $db->closeConect();
            return Util::enviarNotaDebitoDIAN($data_array);
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**------------------------------------------------------------------------
     **                           enviarNotaCreditoDIAN
     *?  Método para enviar la nota credito a la DIAN, facturación electronica
     *@param data_array Objecto tipo JSON con toda la información de la nota credito
     *@return response
     *------------------------------------------------------------------------**/
    public static function enviarNotaCreditoDIAN($data_array)
    {
        try {
            //$url  = Util::apiDIAN() . "/credit-note/" . Util::codigoComercio(); // Pruebas
            $url  = Util::apiDIAN() . "/credit-note";
            $make_call = Util::callApiDIAN('POST', $url, json_encode($data_array));
            $response = json_decode($make_call, true);
            Util::saveLogsDIAN($response, json_encode($data_array));
            return array('output' => array('valid' => true, 'response' => $response, 'request' => $data_array));
        } catch (Exception $e) {
            $error = json_encode(array(
                'error' => array(
                    'msg' => $e->getMessage(),
                    'code' => $e->getCode(),
                ),
            ));
            $factura = $data_array['billing_reference']['number'];
            Util::trace_log_error_dian(json_encode($data_array), 'enviarNotaCreditoDIAN', json_encode($error), $factura);
            return array('output' => array('valid' => true, 'response' => $response, 'request' => $data_array));
        }
    }

    /**------------------------------------------------------------------------
     **                           enviarNotaDebitoDIAN
     *?  Método para enviar la nota credito a la DIAN, facturación electronica
     *@param data_array Objecto tipo JSON con toda la información de la nota credito
     *@return response
     *------------------------------------------------------------------------**/
    public static function enviarNotaDebitoDIAN($data_array)
    {
        try {
            //$url  = Util::apiDIAN() . "/debit-note/" . Util::codigoComercio(); // Pruebas
            $url  = Util::apiDIAN() . "/debit-note";
            $make_call = Util::callApiDIAN('POST', $url, json_encode($data_array));
            $response = json_decode($make_call, true);
            Util::saveLogsDIAN($response, json_encode($data_array));
            return array('output' => array('valid' => true, 'response' => $response, 'request' => $data_array));
        } catch (Exception $e) {
            $error = json_encode(array(
                'error' => array(
                    'msg' => $e->getMessage(),
                    'code' => $e->getCode(),
                ),
            ));
            $factura = $data_array['billing_reference']['number'];
            Util::trace_log_error_dian(json_encode($data_array), 'enviarNotaDebitoDIAN', json_encode($error), $factura);
            return array('output' => array('valid' => true, 'response' => $response, 'request' => $data_array));
        }
    }

    /**------------------------------------------------------------------------
     **                           enviarFacturaDIAN
     *?  Método para enviar la factura a la DIAN, facturación electronica
     *@param data_array Objecto tipo JSON con toda la información de la venta
     *@return response
     *------------------------------------------------------------------------**/
    public static function enviarFacturaDIAN($data_array)
    {
        try {
            //$url  = Util::apiDIAN() . "/invoice/" . Util::codigoComercio(); // Pruebas
            $url  = Util::apiDIAN() . "/invoice";
            $make_call = Util::callApiDIAN('POST', $url, json_encode($data_array));
            $response = json_decode($make_call, true);
            //throw new Exception("Value must be 1 or below");
            /* print_r(json_encode($response));
            exit(); */
            Util::saveLogsDIAN($response, json_encode($data_array));
            return array('output' => array('valid' => true, 'response' => $response, 'request' => $data_array));
        } catch (Exception $e) {
            $error = json_encode(array(
                'error' => array(
                    'msg' => $e->getMessage(),
                    'code' => $e->getCode(),
                ),
            ));
            $factura = $data_array['number'];
            Util::trace_log_error_dian(json_encode($data_array), 'enviarFacturaDIAN', json_encode($error), $factura);
            return array('output' => array('valid' => true, 'response' => $response, 'request' => $data_array));
        }
    }


    /**------------------------------------------------------------------------
     **                           consultarLogsDian
     *?  Método para consultar los logs de la factura a la DIAN, facturación electronica
     *@param factura Número de la factura de venta
     *@return response
     *------------------------------------------------------------------------**/
    public static function consultarLogsDian($factura)
    {
        try {
            $url  = Util::apiDIAN() . "/logs/" . $factura;
            $make_call = Util::callApiDIAN('POST', $url, $factura);
            $response = json_decode($make_call, true);
            Util::saveLogsDIAN($response, $factura);
            return array('output' => array('valid' => true, 'response' => $response));
        } catch (Exception $e) {
            $error = json_encode(array(
                'error' => array(
                    'msg' => $e->getMessage(),
                    'code' => $e->getCode(),
                ),
            ));
            Util::trace_log_error_dian($factura, 'consultarLogsDian', json_encode($error), $factura);
            return array('output' => array('valid' => true, 'response' => $response));
        }
    }

    /**----------------------------------------------------------------------------------
     **                           validacionEstadoDocumento
     *?  Método para consultar si efectivamente la factura o documento ya fue procesada
     *@param
     *@return response
     *----------------------------------------------------------------------------------**/
    public static function validarEstadoDocumento($rqst)
    {

        $factura = isset($rqst['factura']) ? ($rqst['factura']) : '';
        $uuid = isset($rqst['uuid']) ? ($rqst['uuid']) : '';

        if ($factura == '' || $uuid == '') {
            return Util::error_missing_data();
        }

        try {
            $url  = Util::apiDIAN() . "/status/document/" . $uuid;
            $make_call = Util::callApiDIAN('POST', $url, $uuid);
            $response = json_decode($make_call, true);

            Util::saveLogsDIAN($response, $factura);
            return array('output' => array('valid' => true, 'response' => $response));
        } catch (Exception $e) {
            $error = json_encode(array(
                'error' => array(
                    'msg' => $e->getMessage(),
                    'code' => $e->getCode(),
                ),
            ));
            // print_r( json_encode( $error ) );
            Util::trace_log_error_dian($factura, 'validacionEstadoDocumento', json_encode($error), $factura);
            return array('output' => array('valid' => true, 'response' => $response));
        }
    }

    /**------------------------------------------------------------------------
     **                           saveLogsDIAN
     *?  Método para guardar la respuesta de la factura a la DIAN, facturación electronica
     *@param data Data response del servicio de la DIAN
     *@param json Objecto JSON enviado al servicio de la DIAN
     *@return response
     *------------------------------------------------------------------------**/
    public static function saveLogsDIAN($data,  $jsonEnviado)
    {

        if ($data != "" && $jsonEnviado != "") {

            $db = new DbConection();
            $pdo = $db->openConect();

            $q = "INSERT INTO " . $db->getTable('tec_logs_dian') . " (dtcreate, request, response, is_valid, number, uuid, issue_date, expedition_date, zip_key, status_code, status_description, status_message, xml_file_name, zip_name, batch, url_acceptance, url_rejection, xml_bytes, errors_messages, qr_data, application_response_base64_bytes, attached_document_base64_bytes, pdf_base64_bytes, zip_base64_bytes, dian_response_base64_bytes)
            VALUES ( " . Util::date_now_server() . ", :request, :response, :is_valid, :number, :uuid, :issue_date, :expedition_date, :zip_key, :status_code, :status_description, :status_message, :xml_file_name, :zip_name, :batch, :url_acceptance, :url_rejection, :xml_bytes, :errors_messages,  :qr_data, :application_response_base64_bytes, :attached_document_base64_bytes, :pdf_base64_bytes, :zip_base64_bytes, :dian_response_base64_bytes)";
            $result = $pdo->prepare($q);
            $arrparam = array(
                ':request' => $jsonEnviado,
                ':response' => json_encode($data),
                ':is_valid' => $data['is_valid'],
                ':number' => $data['number'],
                ':uuid' => $data['uuid'],
                ':issue_date' => $data['issue_date'],
                ':expedition_date' => $data['expedition_date'],
                ':zip_key' => $data['zip_key'],
                ':status_code' => $data['status_code'],
                ':status_description' => $data['status_description'],
                ':status_message' => $data['status_message'],
                ':xml_file_name' => $data['xml_file_name'],
                ':zip_name' => $data['zip_name'],
                ':batch' => $data['batch'],
                ':url_acceptance' => $data['url_acceptance'],
                ':url_rejection' => $data['url_rejection'],
                ':xml_bytes' => $data['xml_bytes'],
                ':errors_messages' => json_encode($data['errors_messages']),
                ':qr_data' => $data['qr_data'],
                ':application_response_base64_bytes' => $data['application_response_base64_bytes'],
                ':attached_document_base64_bytes' => $data['attached_document_base64_bytes'],
                ':pdf_base64_bytes' => $data['pdf_base64_bytes'],
                ':zip_base64_bytes' => $data['zip_base64_bytes'],
                ':dian_response_base64_bytes' => $data['dian_response_base64_bytes']
            );
            if ($result->execute($arrparam)) {
                $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
            } else {
                Util::trace_log_error_dian($data, 'Util::saveLogsDIAN', json_encode($data), $data['number']);
                $arrjson = Util::error_general();
            }
            $db->closeConect();
            return $arrjson;
        }
    }

    /**------------------------------------------------------------------------
     **                           trace_log_error_dian
     *
     *?  Metodo que guarda los errores que sucerdieron con una factura
     *@return type
     *------------------------------------------------------------------------**/
    public static function trace_log_error_dian($rqst, $controlador = '', $error = '', $number = '')
    {
        $db = new DbConection();
        $pdo = $db->openConect();

        $op = isset($rqst['op']) ? $rqst['op'] : '';
        $usuario_id = 0;
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $phpsessionid = isset($_REQUEST['PHPSESSID']) ? $_REQUEST['PHPSESSID'] : '';
        if (isset($_SESSION['session_user'])) {
            $usuario_id = $_SESSION['session_user']['id'];
        }

        // Se guarda en la tabla de errores
        $q = "INSERT INTO " . $db->getTable('tec_log_errores') . " (dtcreate, ip, usuario_id, op, controlador, rqst, user_agent, phpsessionid, error, tipoerror, number) VALUES (" . Util::date_now_server() . ", '" . Util::get_real_ipaddress() . "', '" . $usuario_id . "', '" . $op . "', '" . $controlador . "', '" .  json_encode($rqst) . "', '" . $user_agent . "', '" . $phpsessionid . "', '" . $error . "', 'API', '" . $number . "') ";
        $pdo->query($q);

        // Se guarda las facturas que se consumió el consecutivo
        //$q2 = "INSERT INTO " . $db->getTable('tec_invoices_pending_dian') . " (dtcreate, number, request, response, estado) VALUES (" . Util::date_now_server() . ", '".$number."', '".json_encode($rqst)."', '".$error."' , 'PENDIENTE') ";
        //$pdo->query($q2);

        $db->closeConect();
    }


    /**------------------------------------------------------------------------
     **                           actualizarFacturaReintentoExitoso
     *
     *?  Metodo que actualiza la factura a proceso exitoso ante la DIAN
     * Actualiza el inventario de los productos cuando sea procesa y autorizada
     *@return type
     *------------------------------------------------------------------------**/
    public static function actualizarFacturaReintentoExitoso($rqst)
    {

        $idSale = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $json = isset($rqst['json']) ? ($rqst['json']) : '';
        $request = isset($rqst['request']) ? ($rqst['request']) : '';

        $uuid = $json['uuid'];
        $issue_date = $json['issue_date'];
        $expedition_date = $json['expedition_date'];
        $number = $json['number'];

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($uuid != "" && $idSale > 0) {

            // Informacion email
            $resultFactura = "SELECT tec_customers_electronic.email
            FROM " . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
            WHERE tec_sales_electronic.tec_customers_electronic_id = tec_customers_electronic.id AND  tec_sales_electronic.id = " . $idSale;
            $resultFactura = $pdo->query($resultFactura);
            $emailCliente = "";
            foreach ($resultFactura as $valor) {
                $emailCliente = $valor['email'];
            }

            $qUpdate = "UPDATE " . $db->getTable('tec_sales_electronic') . "
            SET issue_date =  '$issue_date',
            expedition_date =  '$expedition_date',
            estado = 'PROCESADA_Y_AUTORIZADA_ANTE_LA_DIAN',
            uuid  = '$uuid'
            WHERE id = " . $idSale;
            $result = $pdo->query($qUpdate);

            // Se guarda request y response exitoso
            $q1 = "INSERT INTO " . $db->getTable('tec_requests_dian') . " (dtcreate, number, request, response)  VALUES (" . Util::date_now_server() . ", '" . $number . "', '" . json_encode($request) . "', '" . json_encode($json) . "') ";
            $pdo->query($q1);

            // Actualizamos el inventario del producto despues de aprobada la factrura ante la DIAN
            $qAct = "SELECT * FROM " . $db->getTable('tec_sales_tec_products_electronic') . " WHERE tec_sale_id = " . $idSale;
            $resultAct = $pdo->query($qAct);
            if ($resultAct) {
                foreach ($resultAct as $arrProd) {
                    Util::actualizarInventario(array('tec_product_id' => $arrProd['tec_product_id'], 'cantidad' => $arrProd['cantidad'], 'caja' => 'si'));
                }
            }

            // Se consume el servicio para el envio de correos
            Util::envioCorreo(array('uuid' => $uuid, 'email' =>  $emailCliente));

            $arrjson = array('output' => array('valid' => true, 'id' => $idSale));
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**------------------------------------------------------------------------
     **                          actualizarNotCreditoReintentoExitoso
     *
     *?  Metodo que actualiza la nota credito  a proceso exitoso ante la DIAN
     *@return type
     *------------------------------------------------------------------------**/
    public static function actualizarNotCreditoReintentoExitoso($rqst)
    {

        $tec_credit_note_electronic_id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $json = isset($rqst['json']) ? ($rqst['json']) : '';
        $request = isset($rqst['request']) ? ($rqst['request']) : '';

        $uuid = $json['uuid'];
        $issue_date = $json['issue_date'];
        $expedition_date = $json['expedition_date'];
        $number = $json['number'];

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($uuid != "" && $tec_credit_note_electronic_id > 0) {


            // Información del email del cliente
            $qFactura = "SELECT tec_customers_electronic.email
            FROM " . $db->getTable('tec_credit_note_electronic') . "," . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
            WHERE tec_sales_electronic.id = tec_credit_note_electronic.tec_sales_electronic_id AND
            tec_customers_electronic.id = tec_sales_electronic.tec_customers_electronic_id AND
            tec_credit_note_electronic.id =  " . $tec_credit_note_electronic_id;
            $resultFactura = $pdo->query($qFactura);
            $emailCliente = "";
            foreach ($resultFactura as $valor) {
                $emailCliente = $valor['email'];
            }

            // Actualiza la informacion de la nota
            $qUpdate = "UPDATE " . $db->getTable('tec_credit_note_electronic') . "
                SET issue_date =  '$issue_date',
                expedition_date =  '$expedition_date',
                number =  '$number',
                estado = 'PROCESADA_Y_AUTORIZADA_ANTE_LA_DIAN',
                uuid  = '$uuid'
            WHERE id = " . $tec_credit_note_electronic_id;
            $result = $pdo->query($qUpdate);

            // Se guarda request y response exitoso
            $q1 = "INSERT INTO " . $db->getTable('tec_requests_dian') . " (dtcreate, number, request, response)  VALUES (" . Util::date_now_server() . ", '" . $number . "', '" . json_encode($request) . "', '" . json_encode($json) . "') ";
            $pdo->query($q1);

            // Se consume el servicio para el envio de correos
            Util::envioCorreo(array('uuid' => $uuid, 'email' =>  $emailCliente));

            $arrjson = array('output' => array('valid' => true, 'id' => $tec_credit_note_electronic_id));
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**------------------------------------------------------------------------
     **                          actualizarNotDebitoReintentoExitoso
     *
     *?  Metodo que actualiza la nota debito  a proceso exitoso ante la DIAN
     *@return type
     *------------------------------------------------------------------------**/
    public static function actualizarNotDebitoReintentoExitoso($rqst)
    {

        $tec_debit_note_electronic_id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $json = isset($rqst['json']) ? ($rqst['json']) : '';
        $request = isset($rqst['request']) ? ($rqst['request']) : '';

        $uuid = $json['uuid'];
        $issue_date = $json['issue_date'];
        $expedition_date = $json['expedition_date'];
        $number = $json['number'];

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($uuid != "" && $tec_debit_note_electronic_id > 0) {

            // Información del email del cliente
            $qFactura = "SELECT tec_customers_electronic.email FROM " . $db->getTable('tec_debit_note_electronic') . "," . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
            WHERE tec_sales_electronic.id = tec_debit_note_electronic.tec_sales_electronic_id AND
            tec_customers_electronic.id = tec_sales_electronic.tec_customers_electronic_id AND
            tec_debit_note_electronic.id =  " . $tec_debit_note_electronic_id;
            $resultFactura = $pdo->query($qFactura);
            $emailCliente = "";
            foreach ($resultFactura as $valor) {
                $emailCliente = $valor['email'];
            }

            $qUpdate = "UPDATE " . $db->getTable('tec_debit_note_electronic') . "
                SET issue_date =  '$issue_date',
                expedition_date =  '$expedition_date',
                number =  '$number',
                estado = 'PROCESADA_Y_AUTORIZADA_ANTE_LA_DIAN',
                uuid  = '$uuid'
            WHERE id = " . $tec_debit_note_electronic_id;
            $result = $pdo->query($qUpdate);

            // Se guarda request y response exitoso
            $q1 = "INSERT INTO " . $db->getTable('tec_requests_dian') . " (dtcreate, number, request, response)  VALUES (" . Util::date_now_server() . ", '" . $number . "', '" . json_encode($request) . "', '" . json_encode($json) . "') ";
            $pdo->query($q1);

            // Se consume el servicio para el envio de correos
            Util::envioCorreo(array('uuid' => $uuid, 'email' =>  $emailCliente));

            $arrjson = array('output' => array('valid' => true, 'id' => $tec_debit_note_electronic_id));
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo que actualiza los datos de una nota que ya fue procesado y la informacion esta en  logs
     */
    public static function actualizarNumberNotaCredito($rqst)
    {

        $tec_credit_note_electronic_id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $number = isset($rqst['number']) ? ($rqst['number']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($tec_credit_note_electronic_id > 0 && $number != "") {

            // Consultamos si en log está procesado anteriormente correctamente
            $q = "SELECT * FROM " . $db->getTable('tec_logs_dian') . " WHERE number =  '$number'  AND status_code = '02' LIMIT 1 ";
            $result = $pdo->query($q);

            $arr = array();
            $request = "";
            $response = "";
            $status_message = "";

            if ($result) {
                foreach ($result as $valor) {
                    $arr[] = $valor;
                    $request = $valor['request'];
                    $response = $valor['response'];
                    $status_message = $valor['status_message'];
                }

                // Validamos que se obtenga información
                if (count($arr)  > 0) {

                    $uuid = $arr[0]['uuid'];
                    $issue_date = $arr[0]['issue_date'];
                    $expedition_date = $arr[0]['expedition_date'];

                    // Información del email del cliente
                    $qFactura = "SELECT tec_customers_electronic.email
                    FROM " . $db->getTable('tec_credit_note_electronic') . "," . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
                    WHERE tec_sales_electronic.id = tec_credit_note_electronic.tec_sales_electronic_id AND
                    tec_customers_electronic.id = tec_sales_electronic.tec_customers_electronic_id AND
                    tec_credit_note_electronic.id =  " . $tec_credit_note_electronic_id;
                    $resultFactura = $pdo->query($qFactura);
                    $emailCliente = "";
                    foreach ($resultFactura as $valor) {
                        $emailCliente = $valor['email'];
                    }

                    // Se actualiza la informacion de la nota crédito
                    $qUpdate = "UPDATE " . $db->getTable('tec_credit_note_electronic') . "
                        SET issue_date =  '$issue_date',
                        expedition_date =  '$expedition_date',
                        number =  '$number',
                        estado = 'PROCESADA_Y_AUTORIZADA_ANTE_LA_DIAN',
                        uuid  = '$uuid'
                    WHERE id = " . $tec_credit_note_electronic_id;
                    $result = $pdo->query($qUpdate);

                    // Se guarda request y response exitoso
                    $q1 = "INSERT INTO " . $db->getTable('tec_requests_dian') . " (dtcreate, number, request, response) VALUES (" . Util::date_now_server() . ", '" . $number . "', '" . json_encode($request) . "', '" . json_encode($response) . "') ";
                    $pdo->query($q1);

                    // Se consume el servicio para el envio de correos
                    Util::envioCorreo(array('uuid' => $uuid, 'email' =>  $emailCliente));

                    $db->closeConect();
                    return array('output' => array('valid' => true, 'response' => $response, 'request' => $request, 'status_message' => $status_message));
                } else {
                    $arrjson = Util::error_no_result();
                }
            }
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo que actualiza los datos de una nota que ya fue procesado y la informacion esta en  logs
     */
    public static function actualizarNumberNotaDebito($rqst)
    {

        $tec_debit_note_electronic_id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $number = isset($rqst['number']) ? ($rqst['number']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($tec_debit_note_electronic_id > 0 && $number != "") {

            // Consultamos si en log está procesado anteriormente correctamente
            $q = "SELECT * FROM " . $db->getTable('tec_logs_dian') . " WHERE number =  '$number'  AND status_code = '02' LIMIT 1 ";
            $result = $pdo->query($q);
            $arr = array();
            $request = "";
            $response = "";
            $status_message = "";

            if ($result) {
                foreach ($result as $valor) {
                    $arr[] = $valor;
                    $request = $valor['request'];
                    $response = $valor['response'];
                    $status_message = $valor['status_message'];
                }

                // Validamos que se obtenga información
                if (count($arr)  > 0) {

                    $uuid = $arr[0]['uuid'];
                    $issue_date = $arr[0]['issue_date'];
                    $expedition_date = $arr[0]['expedition_date'];


                    // Información del email del cliente
                    $qFactura = "SELECT tec_customers_electronic.email FROM " . $db->getTable('tec_debit_note_electronic') . "," . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
                    WHERE tec_sales_electronic.id = tec_debit_note_electronic.tec_sales_electronic_id AND
                    tec_customers_electronic.id = tec_sales_electronic.tec_customers_electronic_id AND
                    tec_debit_note_electronic.id =  " . $tec_debit_note_electronic_id;
                    $resultFactura = $pdo->query($qFactura);
                    $emailCliente = "";
                    foreach ($resultFactura as $valor) {
                        $emailCliente = $valor['email'];
                    }

                    // Se actualiza la nota debito
                    $qUpdate = "UPDATE " . $db->getTable('tec_debit_note_electronic') . "
                        SET issue_date =  '$issue_date',
                        expedition_date =  '$expedition_date',
                        number =  '$number',
                        estado = 'PROCESADA_Y_AUTORIZADA_ANTE_LA_DIAN',
                        uuid  = '$uuid'
                    WHERE id = " . $tec_debit_note_electronic_id;
                    $result = $pdo->query($qUpdate);

                    // Se guarda request y response exitoso
                    $q1 = "INSERT INTO " . $db->getTable('tec_requests_dian') . " (dtcreate, number, request, response) VALUES (" . Util::date_now_server() . ", '" . $number . "', '" . json_encode($request) . "', '" . json_encode($response) . "') ";
                    $pdo->query($q1);

                    // Se consume el servicio para el envio de correos
                    Util::envioCorreo(array('uuid' => $uuid, 'email' =>  $emailCliente));

                    return array('output' => array('valid' => true, 'response' => $response, 'request' => $request, 'status_message' => $status_message));
                } else {
                    $arrjson = Util::error_no_result();
                }
            }
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }


    /**------------------------------------------------------------------------------------------------------
     **                           envioCorreo
     * Método para enviar el correo la información del documento (Factura, Nota Credito, Nota Debito)
     *------------------------------------------------------------------------------------------------------------**/
    public static function envioCorreo($rqst)
    {
        $uuid = isset($rqst['uuid']) ? ($rqst['uuid']) : '';
        $email = isset($rqst['email']) ? ($rqst['email']) : '';
        try {
            $data_array =   array(
                "to"  => array(
                    array("email" => $email),
                ),
                "cc" => array(
                    //array("email" => "sistemas@marcazeta.com" ),
                    array("email" => "juan.mejia@spidersoftware.co"),
                    //array("email" => "alexlondon07@gmail.com"),
                    array("email" => "alex.londono@spidersoftware.co"),
                ),
            );

            $url  = Util::apiDIAN() . "/mail/send/" . $uuid;
            $make_call = Util::callApiDIAN('POST', $url, json_encode($data_array));
            $response = json_decode($make_call, true);
            return array('output' => array('valid' => true, 'response' => $response));
        } catch (Exception $e) {
            $error = json_encode(array(
                'error' => array(
                    'msg' => $e->getMessage(),
                    'code' => $e->getCode(),
                ),
            ));
        }
    }


    public static function reenviarCorreoFactura($rqst)
    {
        $id = isset($rqst['id']) ? ($rqst['id']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        // Informacion de la venta y cliente
        $q = "SELECT tec_sales_electronic.*, tec_customers_electronic.email as email
        FROM " . $db->getTable('tec_sales_electronic') . "," . $db->getTable('tec_customers_electronic') . "
        WHERE tec_sales_electronic.tec_customers_electronic_id = tec_customers_electronic.id AND  tec_sales_electronic.id = " . $id;
        $result = $pdo->query($q);
        $email = "";
        $uuid = "";
        if ($result) {

            foreach ($result as $valor) {
                $uuid = $valor['uuid'];
                $email = $valor['email'];
            }

            if ($uuid != "" && $email != "") {
                if (Util::validate_email($email)) {
                    $arrjson = array('output' => array('valid' => true, 'response' => $id, 'email' => $email));
                    Util::envioCorreo(array('uuid' => $uuid, 'email' =>  $email));
                } else {
                    $arrjson = Util::error_general('El email del cliente no es valido');
                }
            } else {
                $arrjson = Util::error_general('El email o uuid de la factura no pueden estar vacio');
            }
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function porcentajePorNumeroDeCarros($cantidad)
    {
        $porcentaje = 40;
        if ($cantidad > 10) {
            $porcentaje = 50;
        }
        return $porcentaje;
    }

    public static function valorVavadaTroque()
    {
        return 6000;
    }

    public static function rutaFotosCamara()
    {
        return "admin/js/camara/foto/";
    }

    public static function Staff()
    {
        return "Staff";
    }
    public static function Manager()
    {
        return "Administrador";
    }

    public static function SuperAdmin()
    {
        return "SuperAdministrador";
    }
    
    public static function porcentajeDeUnValor($valor, $porciento)
    {
        return  $valor * $porciento / 100;
    }
    public static function colorEstado()
    {
        return array(
            'EN PROCESO' => '#ff830f',
            'ACTIVO' => '#0040FF',
            'CANCELADO' => '#ff3c33',
            'LIQUIDADO' => '#33e9ff',
            'ASIGNADO' => '#044f0d',
        );
    }

    public static function getUser($rqst)
    {


        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_usuarios') . " WHERE id = " . $id;
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
        } else {
            return Util::error_no_result();
        }
    }

    public static function getUnidadByUser($id)
    {

        $tbl_usuario_id = $id;

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($tbl_usuario_id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_usuarios') . " WHERE id = " . $tbl_usuario_id;
            $result = $pdo->query($q);
            $arr = array();
            $tbl_unidad_id = 0;
            if ($result) {
                foreach ($result as $valor) {
                    $arr[] = $valor;
                    $tbl_unidad_id = intval($valor['tbl_unidad_id']);
                }
                if (count($arr) == 0) {
                    $db->closeConect();
                    return Util::error_no_result();
                }
                if ($tbl_unidad_id == 0) {
                    $db->closeConect();
                    return Util::error_general(' The user does not have an associated unit, you must assign to which unit it belongs.');
                }

                $arrjson = array('output' => array('valid' => true, 'response' => $arr, 'tbl_unidad_id' => $tbl_unidad_id));
            } else {
                $arrjson = Util::error_no_result();
            }
            $db->closeConect();
            return $arrjson;
        } else {
            return Util::error_no_result();
        }
    }
}
