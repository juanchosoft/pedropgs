<?php

/**

 * Clase que contiene toda la informacion para utilizar

 * durante una sesion de session_user activo

 */

class SessionData
{



    public static function getKey()
    {

        return 'e1ca41c9c29a354fea64d33228f45503';
    }



    public static function getRandom()
    {

        if (isset($_SESSION['random'])) {

            $_SESSION['random'] = sha1(rand(100, 2000));
        }

        return $_SESSION['random'];
    }



    public static function getPermission($id)
    {

        if (isset($_SESSION['session_user'])) {

            $permisos = $_SESSION['session_user']['permisos'];

            return (in_array($id, $permisos));
        } else {

            return false;
        }
    }



    public static function getUserId()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['id'];
        } else {

            return sha1(rand(100, 2000));
        }
    }

    public static function getUserType()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['tipo'];
        } else {

            return "";
        }
    }



    public static function getKeyUser()
    {

        if (isset($_SESSION['session_user'])) {

            $userid = $_SESSION['session_user']['id'];

            return md5($userid . SessionData::getKey() . SessionData::getRandom());
        } else {

            return md5(rand(100, 2000));
        }
    }



    public static function getKeyGeneric()
    {

        return md5(SessionData::getKey() . SessionData::getRandom());
    }



    public static function getUserFullName()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['nombre'] . ' ' . $_SESSION['session_user']['apellido'];
        } else {

            return "";
        }
    }
    public static function getUnidadUser()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['tbl_unidad_id'];
        } else {

            return 0;
        }
    }



    public static function getAvatar()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['img'] != "" ? 'assets/img/admin/' . $_SESSION['session_user']['img'] : 'assets/img/logo-spiderP.png';
        }
    }



    public static function superAdministrador()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['tipo'] == "SuperAdministrador" ? true : false;
        }
    }



    public static function getNombreCaja()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['caja'][0]['codigo'];
        } else {

            return "";
        }
    }



    public static function getIdCaja()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['caja'][0]['id'];
        } else {

            return "";
        }
    }



    public static function getAvatarGeneric()
    {

        return 'dist/img/user.svg';
    }



    public static function getImageProduct($img)
    {

        if ($img != "" && file_exists("assets/img/admin/" . $img)) {

            return 'assets/img/admin/' . $img;
        } else {

            return 'assets/img/logo1.png';
        }
    }



    /**

     * CONFIGURACION DEL SISTEMA DE VARIABLES IMPORTANTES

     */



    public static function getLogoEmpresa()
    {



        $img = isset($_SESSION['session_user']) ? $_SESSION['session_user']['config'][0]['img'] : "assets/img/logo1.png";



        if ($img != "" && file_exists("assets/img/admin/" . $img)) {

            return 'assets/img/admin/' . $img;
        } else {

            return $img;
        }
    }



    public static function getConfigSistema()
    {

        return isset($_SESSION['session_user']) ? $_SESSION['session_user']['config'][0] : "";
    }



    public static function getConfigPrecioProd()
    {

        return isset($_SESSION['session_user']) ? $_SESSION['session_user']['config'][0]['config_precio_productos'] : "1";
    }



    public static function getConfigImpresionPOS()
    {

        return isset($_SESSION['session_user']) && $_SESSION['session_user']['config'][0]['impresion_termica'] == 'si'

            ? 'si' : 'no';
    }



    public static function getConfigPrecioBolsa()
    {

        return isset($_SESSION['session_user']) ? $_SESSION['session_user']['config'][0]['valor_bolsa'] : 0;
    }

    public static function getTelefonoEmergencia()
    {

        return isset($_SESSION['session_user']) ? $_SESSION['session_user']['telefono_emergencia'] : '';
    }
}
