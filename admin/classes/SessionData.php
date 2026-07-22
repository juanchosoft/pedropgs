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



    /**
     * @deprecated Prefer hasPermission('module.action') — bridges legacy numeric IDs to KEYs.
     */
    public static function getPermission($id)
    {
        if (SessionData::superAdministrador()) {
            return true;
        }

        require_once __DIR__ . '/PermissionLegacyMap.php';
        $key = PermissionLegacyMap::idToKey((int) $id);
        if ($key !== null) {
            return SessionData::hasPermissionKey($key);
        }

        // Fallback: session still has legacy ID array during transition
        if (isset($_SESSION['session_user']['permisos']) && is_array($_SESSION['session_user']['permisos'])) {
            return in_array((int) $id, array_map('intval', $_SESSION['session_user']['permisos']), true);
        }

        return false;
    }

    public static function hasPermissionKey(string $key): bool
    {
        if (!isset($_SESSION['session_user']['permission_keys']) || !is_array($_SESSION['session_user']['permission_keys'])) {
            return false;
        }
        return in_array($key, $_SESSION['session_user']['permission_keys'], true);
    }

    public static function hasPermission(string $key): bool
    {
        if (SessionData::superAdministrador()) {
            return true;
        }
        return SessionData::hasPermissionKey($key);
    }

    /** @return string[] */
    public static function permissionKeys(): array
    {
        if (!isset($_SESSION['session_user']['permission_keys']) || !is_array($_SESSION['session_user']['permission_keys'])) {
            return [];
        }
        return $_SESSION['session_user']['permission_keys'];
    }

    /** @return array{id:int,key:string,name:string}|null */
    public static function getRole(): ?array
    {
        if (!isset($_SESSION['session_user']['role']) || !is_array($_SESSION['session_user']['role'])) {
            return null;
        }
        return $_SESSION['session_user']['role'];
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

    /**
     * CC del empleado vinculado (tec_usuarios.employee_id = tec_employee.cc).
     */
    public static function getEmployeeCc(): int
    {
        if (!isset($_SESSION['session_user']['employee_id'])) {
            return 0;
        }
        $cc = $_SESSION['session_user']['employee_id'];
        if ($cc === '' || $cc === null || $cc === '0' || $cc === 0) {
            return 0;
        }
        return (int) $cc;
    }

    public static function getUnidadUser()
    {

        if (isset($_SESSION['session_user'])) {

            return $_SESSION['session_user']['tbl_unidad_id'];
        } else {

            return 0;
        }
    }

    public static function getUnidadesUser()
    {
        if (isset($_SESSION['session_user']) && isset($_SESSION['session_user']['unidades'])) {

            return $_SESSION['session_user']['unidades'];
        }

        // Backward compat: if no unidades array, return single unidad as array
        $single = self::getUnidadUser();
        return $single > 0 ? [$single] : [];
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
