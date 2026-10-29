<?php
session_start();
$rqst = $_REQUEST;
$op = isset($rqst['op']) ? $rqst['op'] : '';
header("Content-type: application/javascript; charset=utf-8");
header("Cache-Control: max-age=15, must-revalidate");
header('Access-Control-Allow-Origin: *');

include '../classes/DbConection.php';
include '../classes/Util.php';
include '../classes/SessionData.php';
require_once '../classes/PermissionGate.php';

$opsSinAuth = ['pms_usrlogin', ''];
if (!in_array($op, $opsSinAuth, true)) {
    PermissionGate::authorizeOperation($op);
}

if (preg_match('/^(svc|tsk|avl|exp|inv|ntf|prf|tok|itg|rpt|cal|aud|iax)_/', $op)) {
    header_remove('Access-Control-Allow-Origin');
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isWriteVerb = (bool) preg_match('/(save|create|delete|void|send|mark|decide|cancel|toggle|request|reset|revoke|retry|run|adjust|set|link|pay|upload|confirm|reject|approve|assign|status|payment|unmark|invoiced|enqueue|test|merge|import|restore)/', $op);
    $needsToken = $method !== 'GET' || $isWriteVerb;
    if ($needsToken) {
        $csrfSent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($rqst['csrf_token'] ?? '');
        $csrfSession = $_SESSION['csrf_token'] ?? '';
        if ($method !== 'POST' || empty($csrfSession) || !hash_equals((string) $csrfSession, (string) $csrfSent)) {
            http_response_code(403);
            echo json_encode(Util::error_general('Invalid security token.'));
            exit;
        }
    }
}

switch ($op) {




    case 'pms_usrlogin':

        include '../classes/Usuario.php';

        echo json_encode(Usuario::login($rqst));

        break;



        //Llamados AJAX Usuario

    case 'pms_usrsave':

        // Util::verify_user_app_access();

        include '../classes/Usuario.php';

        echo json_encode(Usuario::save($rqst));

        break;



    case 'pms_usrget':

        // Util::verify_user_app_access();

        include '../classes/Usuario.php';

        echo json_encode(Usuario::getAll($rqst));

        break;



    case 'pms_usrdelete':

        // Util::verify_user_app_access();
        include '../classes/Usuario.php';
        echo json_encode(Usuario::delete($rqst));
        break;



    case 'pms_usrenable':
        // Util::verify_user_app_access();
        include '../classes/Usuario.php';
        echo json_encode(Usuario::enable($rqst));
        break;



    case 'pms_usravailable':
        // Util::verify_user_app_access();
        include '../classes/Usuario.php';
        echo json_encode(Usuario::available($rqst));
        break;


        // informe ventas con graficos



    case 'pms_informe_pos':
        // Util::verify_user_app_access();
        include '../classes/Informe.php';
        echo json_encode(Informe::getInformePos($rqst));
        break;



    case 'pms_informe_pospyg':

        include '../classes/Informe.php';

        echo json_encode(Informe::getInformePosPYG($rqst));

        break;



        // informe ventas por categoría

    case 'pms_informe_ventas_x_categoria':

        include '../classes/Informe.php';

        echo json_encode(Informe::getInformeTodasCategorias($rqst));

        //echo json_encode(Informe::getInformexCategoria($rqst));

        break;



        // informe ventas por fecha y usuario

    case 'pms_informev_pos':

        // Util::verify_user_app_access();

        include '../classes/InformeVenta.php';

        echo json_encode(InformeVenta::getVentasXDiaXUsuarioPos($rqst));

        break;



        // Informe de productos que mas se venden y que no

    case 'pms_informe_ventas_productos_generales':

        include '../classes/Informe.php';

        echo json_encode(Informe::getInformeVentasProductosGeneral($rqst));

        break;



    case 'pms_ventaanular':

        // Util::verify_user_app_access();

        include '../classes/Venta.php';

        echo json_encode(Venta::anularVenta($rqst));

        break;



        //Llamados AJAX Categoria

    case 'pms_catdelete':



        include '../classes/Categoria.php';

        echo json_encode(Categoria::delete($rqst));

        break;



    case 'pms_catget':



        include '../classes/Categoria.php';

        echo json_encode(Categoria::getAll($rqst));

        break;



    case 'pms_catsave':



        include '../classes/Categoria.php';

        echo json_encode(Categoria::save($rqst));

        break;



    case 'pms_catenable':



        include '../classes/Categoria.php';

        echo json_encode(Categoria::enable($rqst));

        break;
        /******INVENTARIO */

    case 'pms_inventario_salida':
        include '../classes/Inventario.php';

        echo json_encode(Inventario::saveSalida($rqst));
        break;


    case 'pms_inventario_ajuste':
        include '../classes/Inventario.php';
        echo json_encode(Inventario::saveAjuste($rqst));
        break;

    case 'pms_inventario_detallado':
        include '../classes/Inventario.php';

        echo json_encode(Inventario::getMovimientosDetalladoSalidas($rqst));
        break;

        /**** REQUERIMIENTOS */
    case 'pms_reqget':
        include '../classes/Requerimiento.php';
        echo json_encode(Requerimiento::getAll($rqst));
        break;

    case 'pms_reqsave':
        echo json_encode(Util::error_general('This module moved to Tasks.'));
        break;

    case 'pms_reqdelete':
        echo json_encode(Util::error_general('This module moved to Tasks.'));
        break;

    case 'pms_reqsearch':
        include '../classes/Requerimiento.php';
        echo json_encode(Requerimiento::search($rqst));
        break;

        /*********FIN REQUERIMIENTOS */

        //*************************** */
        //**** UNIDADES */


    case 'pms_uniget':
        include '../classes/Unidades.php';
        echo json_encode(Unidades::getAll($rqst));
        break;


    case 'pms_unisave':
        include '../classes/Unidades.php';
        echo json_encode(Unidades::save($rqst));
        break;

    case 'pms_unidelete':
        include '../classes/Unidades.php';
        echo json_encode(Unidades::deletedata($rqst));
        break;


    case 'pms_unisearch':
        include '../classes/Unidades.php';
        echo json_encode(Unidades::search($rqst));
        break;

    case 'pms_unienable':
        include '../classes/Unidades.php';
        echo json_encode(Unidades::enable($rqst));
        break;

    case 'pms_check_save':
        include '../classes/Check.php';
        echo json_encode(Check::save($rqst));
        break;


    case 'pms_daily_report_save':
        include '../classes/DailyReport.php';
        echo json_encode(DailyReport::save($rqst));
        break;


    case 'pms_job_delete':
        include '../classes/Oficios.php';
        echo json_encode(Oficios::delete($rqst));
        break;


    case 'pms_job_get':
        include '../classes/Oficios.php';
        echo json_encode(Oficios::getAll($rqst));
        break;

    case 'pms_job_save':
        include '../classes/Oficios.php';
        echo json_encode(Oficios::save($rqst));
        break;

    case 'pms_zones_delete':
        include '../classes/Lugar.php';
        echo json_encode(Lugar::delete($rqst));
        break;


    case 'pms_zones_get':
        include '../classes/Lugar.php';
        echo json_encode(Lugar::getAll($rqst));
        break;

    case 'pms_zones_save':
        include '../classes/Lugar.php';
        echo json_encode(Lugar::save($rqst));
        break;

        /*********FIN UNIDADES */

        //Llamados AJAX Permisos

    case 'pms_usrpermission':
        include '../classes/Permiso.php';
        echo json_encode(Permiso::permisos($rqst));
        break;



    case 'pms_usrsavepermission':
        include '../classes/Permiso.php';
        echo json_encode(Permiso::savePermisos($rqst));
        break;

        //productos por categoria en caja
    case 'pms_prodesearchx_categoria':
        include '../classes/Producto.php';
        echo json_encode(Producto::searchByCategoryId($rqst));
        break;


        //Llamados AJAX Proveedor

    case 'pms_prodelete':
        include '../classes/Proveedor.php';
        echo json_encode(Proveedor::delete($rqst));
        break;



    case 'pms_fact_recibidasget':
        include '../classes/Proveedor.php';
        echo json_encode(Proveedor::ordenesRecibidas($rqst));
        break;


    case 'pms_proget':
        include '../classes/Proveedor.php';
        echo json_encode(Proveedor::getAll($rqst));
        break;



    case 'pms_prosave':
        include '../classes/Proveedor.php';
        echo json_encode(Proveedor::save($rqst));

        break;



    case 'pms_proenable':



        include '../classes/Proveedor.php';

        echo json_encode(Proveedor::enable($rqst));

        break;



        //Llamados AJAX empleado

    case 'pms_empleadodelete':



        include '../classes/Empleado.php';

        echo json_encode(Empleado::delete($rqst));

        break;



    case 'pms_empleadoget':



        include '../classes/Empleado.php';

        echo json_encode(Empleado::getAll($rqst));

        break;



    case 'pms_empleadosave':



        include '../classes/Empleado.php';

        echo json_encode(Empleado::save($rqst));

        break;



    case 'pms_empleadoenable':



        include '../classes/Empleado.php';

        echo json_encode(Empleado::enable($rqst));

        break;



    case 'pms_empleadosearch':

        include '../classes/Empleado.php';

        echo json_encode(Empleado::search($rqst));

        break;







        //Llamados AJAX para la nomina

    case 'pms_nominadelete':

        include '../classes/Nomina.php';

        echo json_encode(Nomina::delete($rqst));

        break;



    case 'pms_nominaget':



        include '../classes/Nomina.php';

        echo json_encode(Nomina::getAll($rqst));

        break;



    case 'pms_nominasave':



        include '../classes/Nomina.php';

        echo json_encode(Nomina::save($rqst));

        break;





    case 'pms_nominasearch':

        include '../classes/Nomina.php';

        echo json_encode(Nomina::search($rqst));

        break;



        //Llamados AJAX Producto

    case 'pms_proddelete':

        include '../classes/Producto.php';

        echo json_encode(Producto::delete($rqst));

        break;



    case 'pms_save_combo_producto':

        include '../classes/Producto.php';

        echo json_encode(Producto::saveCombo($rqst));

        break;



    case 'pms_prodget':

        include '../classes/Producto.php';

        echo json_encode(Producto::getAll($rqst));

        break;



    case 'pms_prodsave':

        include '../classes/Producto.php';

        echo json_encode(Producto::save($rqst));

        break;



    case 'pms_prodenable':

        include '../classes/Producto.php';

        echo json_encode(Producto::enable($rqst));

        break;



    case 'pms_prodesearch':

        include '../classes/Producto.php';

        echo json_encode(Producto::search($rqst));

        break;



        //Llamados AJAX reloj entrada y salida

    case 'pms_salidasave':

        include '../classes/Salida.php';

        echo json_encode(Salida::save($rqst));

        break;



    case 'pms_entradasave':

        include '../classes/Entrada.php';

        echo json_encode(Entrada::save($rqst));

        break;

        //Llamados AJAX ingreso vehiculo
    case 'pms_ingresovehiculosave':
        include '../classes/IngresoVehiculo.php';
        echo json_encode(IngresoVehiculo::save($rqst));
        break;

    case 'pms_ingresovehiculoget':
        include '../classes/IngresoVehiculo.php';
        echo json_encode(IngresoVehiculo::getAll($rqst));
        break;

    case 'pms_ingresovehiculoasignar':
        include '../classes/IngresoVehiculo.php';
        echo json_encode(IngresoVehiculo::asignarLavadores($rqst));
        break;

    case 'pms_prodesearch':
        include '../classes/IngresoVehiculo.php';
        echo json_encode(IngresoVehiculo::search($rqst));
        break;




        //Llamados AJAX cliente



    case 'pms_clidelete':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::delete($rqst));

        break;



    case 'pms_cliget':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::getAll($rqst));

        break;



    case 'pms_clisave':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::save($rqst));

        break;



    case 'pms_clienable':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::enable($rqst));

        break;



    case 'pms_clisearch':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::search($rqst));

        break;




    case 'pms_clisave':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::save($rqst));

        break;



    case 'pms_clienable':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::enable($rqst));

        break;




        //Llamados AJAX para verificar si el cliente existe

    case 'pms_cliavailabledocument':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::availableDocument($rqst));

        break;


        //Llamados AJAX PARA LA CONfFIGURACION



    case 'pms_confsave':

        include '../classes/Configuracion.php';

        echo json_encode(Configuracion::save($rqst));

        break;



    case 'pms_getconf':

        include '../classes/Configuracion.php';

        echo json_encode(Configuracion::getAll($rqst));

        break;


    case 'pms_clisearch':

        include '../classes/Cliente.php';

        echo json_encode(Cliente::search($rqst));

        break;


    case 'pms_ciudadget':

        include '../classes/Ciudad.php';

        echo json_encode(Ciudad::getAll($rqst));

        break;





    case 'pms_Departamentoget':

        include '../classes/Departamento.php';

        echo json_encode(Departamento::getAll($rqst));

        break;



    case 'delete_deport':
        include '../classes/Report.php';
        echo json_encode(Report::delete($rqst));
        break;

    case 'get_deport':
        include '../classes/Report.php';
        echo json_encode(Report::getAll($rqst));
        break;

    case 'report_last_unfinished':
        include '../classes/Report.php';
        echo json_encode(Report::getLastUnfinished($rqst));
        break;

    case 'updateFields':
        include '../classes/Report.php';
        echo json_encode(Report::updateFields($rqst));
        break;

    case 'report_finalize':
        include '../classes/Report.php';
        echo json_encode(Report::finalize($rqst));
        break;

    case 'pms_saveentradasalida':
        include '../classes/EntradaSalida.php';
        echo json_encode(EntradaSalida::save($rqst));
        break;

    case 'roleslist':
    case 'roleslistall':
        include '../classes/Role.php';
        echo json_encode(Role::getAll($rqst));
        break;

    case 'roleget':
        include '../classes/Role.php';
        echo json_encode(Role::getById($rqst));
        break;

    case 'rolesave':
        include '../classes/Role.php';
        echo json_encode(Role::save($rqst));
        break;

    case 'roledelete':
        include '../classes/Role.php';
        echo json_encode(Role::delete($rqst));
        break;

    case 'rolepermissionscatalog':
        include '../classes/Role.php';
        echo json_encode(Role::getPermissionsCatalog($rqst));
        break;

    // Services Catalog
    case 'svc_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Services\ServiceCatalogService())->list($p, $r), false);
        break;

    case 'svc_get':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Services\ServiceCatalogService())->get($p, (int) ($r['id'] ?? 0)), true);
        break;

    case 'svc_save':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => ['id' => (new \Pgs\Services\ServiceCatalogService())->save($p, $r)], true);
        break;

    case 'svc_toggle':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Services\ServiceCatalogService())->toggle($p, (int) ($r['id'] ?? 0), !empty($r['active']));
            return ['toggled' => true];
        }, true);
        break;

    case 'svc_delete':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Services\ServiceCatalogService())->softDelete($p, (int) ($r['id'] ?? 0));
            return ['deleted' => true];
        }, true);
        break;

    case 'svc_for_unit':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Services\ServiceCatalogService())->forUnit($p, (int) ($r['unit_id'] ?? 0), (string) ($r['lang'] ?? 'en')), true);
        break;

    case 'svc_request_create':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Services\ServiceRequestService())->create($p, $r), true);
        break;

    // Tasks
    case 'tsk_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Tasks\TaskService())->list($p, $r, (int) ($r['page'] ?? 1), (int) ($r['per_page'] ?? 25)), false);
        break;

    case 'tsk_get':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Tasks\TaskService())->get($p, $r['id'] ?? ($r['code'] ?? '')), true);
        break;

    case 'tsk_save':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $svc = new \Pgs\Tasks\TaskService();
            if (!empty($r['id'])) {
                return $svc->update($p, (int) $r['id'], $r, (int) ($r['row_version'] ?? 1));
            }
            return $svc->create($p, $r);
        }, true);
        break;

    case 'tsk_assign':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $empId = isset($r['assignee_employee_id']) && $r['assignee_employee_id'] !== '' ? (int) $r['assignee_employee_id'] : null;
            return (new \Pgs\Tasks\TaskService())->assign($p, (int) ($r['id'] ?? 0), $empId, $r['note'] ?? null);
        }, true);
        break;

    case 'tsk_status':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Tasks\TaskService())->changeStatus($p, (int) ($r['id'] ?? 0), (string) ($r['status'] ?? ''), $r['note'] ?? null, $r['cancel_reason'] ?? null), true);
        break;

    case 'tsk_comment_add':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Tasks\TaskService())->addComment($p, (int) ($r['id'] ?? 0), (string) ($r['body'] ?? ''), !empty($r['is_internal'])), true);
        break;

    case 'tsk_attach_upload':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Tasks\TaskAttachmentService())->upload($p, (int) ($r['task_id'] ?? 0), $_FILES['file'] ?? []), true);
        break;

    case 'tsk_attach_delete':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Tasks\TaskAttachmentService())->delete($p, (int) ($r['id'] ?? 0));
            return ['deleted' => true];
        }, true);
        break;

    case 'tsk_delete':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Tasks\TaskService())->softDelete($p, (int) ($r['id'] ?? 0));
            return ['deleted' => true];
        }, true);
        break;

    case 'tsk_employees_for_unit':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $unit = isset($r['unit_id']) && $r['unit_id'] !== '' ? (int) $r['unit_id'] : (isset($r['unidad_id']) && $r['unidad_id'] !== '' ? (int) $r['unidad_id'] : null);
            return \Pgs\Workers\EmployeeScope::listFor($p, $unit);
        }, true);
        break;

    case 'tsk_units_for_user':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $pdo = \Pgs\Core\Db::pdo();
            if ($p->isSystemAdmin() || $p->can('tareas.tasks.view_all')) {
                $stmt = $pdo->query('SELECT id, nombre FROM tbl_unidades ORDER BY nombre ASC');
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            $params = [];
            $filter = \Pgs\Auth\UnitScope::sqlFilter($p, 'id', $params);
            $stmt = $pdo->prepare("SELECT id, nombre FROM tbl_unidades WHERE {$filter} ORDER BY nombre ASC");
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }, true);
        break;

    // Availability, Absences & Reservations
    case 'avl_schedule_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $svc = new \Pgs\Availability\ScheduleService();
            if (!empty($r['unidad_id'])) {
                return $svc->listByUnit($p, (int) $r['unidad_id']);
            }
            if (!empty($r['employee_id'])) {
                return $svc->listByEmployee($p, (int) $r['employee_id']);
            }
            return [];
        }, false);
        break;

    case 'avl_schedule_save':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\ScheduleService())->save($p, $r), true);
        break;

    case 'avl_schedule_delete':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Availability\ScheduleService())->delete($p, (int) ($r['id'] ?? 0));
            return ['deleted' => true];
        }, true);
        break;

    case 'avl_workers_for_unit':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\AvailabilityService())->workersForUnit($p, (int) ($r['unidad_id'] ?? 0)), false);
        break;

    case 'avl_availability':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\AvailabilityService())->forEmployee(
            $p,
            (int) ($r['employee_id'] ?? 0),
            (int) ($r['unidad_id'] ?? 0),
            (string) ($r['from_date'] ?? ''),
            (string) ($r['to_date'] ?? '')
        ), false);
        break;

    case 'avl_absence_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\AbsenceService())->list($p, $r), false);
        break;

    case 'avl_absence_request':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\AbsenceService())->request($p, $r), true);
        break;

    case 'avl_absence_decide':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\AbsenceService())->decide($p, (int) ($r['id'] ?? 0), (string) ($r['decision'] ?? ''), $r['note'] ?? null), true);
        break;

    case 'avl_absence_cancel':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Availability\AbsenceService())->cancel($p, (int) ($r['id'] ?? 0));
            return ['cancelled' => true];
        }, true);
        break;

    case 'avl_leave_preview':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\LeaveDayCounter())->count((string) ($r['date_from'] ?? ''), (string) ($r['date_to'] ?? '')), false);
        break;

    case 'avl_leave_my_balance':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\LeaveBalanceService())->myBalance($p, isset($r['year']) ? (int) $r['year'] : null), false);
        break;

    case 'avl_leave_balances':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\LeaveBalanceService())->balances($p, (int) ($r['year'] ?? date('Y'))), false);
        break;

    case 'avl_leave_set_quota':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\LeaveBalanceService())->setQuota(
            $p,
            (int) ($r['employee_id'] ?? 0),
            (int) ($r['year'] ?? date('Y')),
            (int) ($r['days'] ?? 0),
            (int) ($r['row_version'] ?? 1),
            $r['notes'] ?? null
        ), true);
        break;

    case 'avl_leave_adjust':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\LeaveBalanceService())->adjust(
            $p,
            (int) ($r['employee_id'] ?? 0),
            (int) ($r['year'] ?? date('Y')),
            (int) ($r['days'] ?? 0),
            (string) ($r['reason'] ?? '')
        ), true);
        break;

    case 'avl_leave_ledger':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\LeaveBalanceService())->ledger(
            $p,
            (int) ($r['employee_id'] ?? 0),
            (int) ($r['year'] ?? date('Y'))
        ), false);
        break;

    case 'avl_rsv_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Availability\ReservationService())->list($p, $r), false);
        break;

    case 'avl_rsv_create':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $dates = is_array($r['dates'] ?? null) ? $r['dates'] : [($r['work_date'] ?? '')];
            $taskId = !empty($r['task_id']) ? (int) $r['task_id'] : null;
            return (new \Pgs\Availability\ReservationService())->reserve(
                $p,
                (int) ($r['employee_id'] ?? 0),
                (int) ($r['unidad_id'] ?? 0),
                $dates,
                (string) ($r['day_part'] ?? 'full'),
                $r['notes'] ?? null,
                $taskId
            );
        }, true);
        break;

    case 'avl_rsv_cancel':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Availability\ReservationService())->cancel($p, (int) ($r['id'] ?? 0), (string) ($r['reason'] ?? ''));
            return ['cancelled' => true];
        }, true);
        break;

    case 'avl_rsv_fulfill':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Availability\ReservationService())->fulfill($p, (int) ($r['id'] ?? 0));
            return ['fulfilled' => true];
        }, true);
        break;

    // Expenses
    case 'exp_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Expenses\ExpenseService())->list($p, $r, (int) ($r['page'] ?? 1), (int) ($r['per_page'] ?? 25)), false);
        break;

    case 'exp_get':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Expenses\ExpenseService())->get($p, (int) ($r['id'] ?? 0)), false);
        break;

    case 'exp_save':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $svc = new \Pgs\Expenses\ExpenseService();
            if (!empty($r['id'])) {
                return $svc->update($p, (int) $r['id'], $r, (int) ($r['row_version'] ?? 1));
            }
            return $svc->create($p, $r);
        }, true);
        break;

    case 'exp_void':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Expenses\ExpenseService())->void($p, (int) ($r['id'] ?? 0), (string) ($r['reason'] ?? ''));
            return ['voided' => true];
        }, true);
        break;

    case 'exp_export':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        $pExp = \Pgs\Core\Web\PgsPage::principal();
        try {
            $csv = (new \Pgs\Expenses\ExpenseService())->exportCsv($pExp, $rqst);
            header_remove('Content-Type');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="expenses-' . gmdate('Ymd-His') . '.csv"');
            echo "\xEF\xBB\xBF" . $csv;
        } catch (\Pgs\Core\Exceptions\ForbiddenException $e) {
            http_response_code(403);
            echo json_encode(['output' => ['valid' => false, 'response' => ['code' => '403', 'content' => $e->getMessage()]]]);
        }
        break;

    case 'exp_totals':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Expenses\ExpenseService())->totals($p, $r), false);
        break;

    case 'exp_pending_by_unit':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Expenses\ExpenseService())->pendingByUnit($p, (int) ($r['unidad_id'] ?? 0)), false);
        break;

    case 'exp_mark_invoiced':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $ids = is_array($r['expense_ids'] ?? null) ? $r['expense_ids'] : [($r['expense_id'] ?? 0)];
            (new \Pgs\Expenses\ExpenseService())->markInvoiced($p, $ids, (int) ($r['invoice_id'] ?? 0));
            return ['invoiced' => true];
        }, true);
        break;

    case 'exp_unmark_invoiced':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Expenses\ExpenseService())->unmarkInvoiced($p, (int) ($r['expense_id'] ?? 0), (string) ($r['reason'] ?? ''));
            return ['uninvoiced' => true];
        }, true);
        break;

    // Invoices
    case 'inv_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Invoices\InvoiceService())->listForPrincipal($p, $r, (int) ($r['page'] ?? 1), (int) ($r['per_page'] ?? 25)), false);
        break;

    case 'inv_get':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Invoices\InvoiceService())->get($p, (int) ($r['id'] ?? 0)), false);
        break;

    case 'inv_save':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Invoices\InvoiceService())->save($p, $r, $_FILES['pdf_file'] ?? null), true);
        break;

    case 'inv_payment':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Invoices\InvoiceService())->registerPayment($p, (int) ($r['id'] ?? 0), (string) ($r['amount'] ?? '0'), (string) ($r['paid_date'] ?? date('Y-m-d'))), true);
        break;

    case 'inv_void':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Invoices\InvoiceService())->void($p, (int) ($r['id'] ?? 0), (string) ($r['reason'] ?? ''));
            return ['voided' => true];
        }, true);
        break;

    case 'inv_link_expenses':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $ids = is_array($r['expense_ids'] ?? null) ? $r['expense_ids'] : [];
            (new \Pgs\Invoices\InvoiceService())->linkExpenses($p, (int) ($r['id'] ?? 0), $ids);
            return ['linked' => true];
        }, true);
        break;

    // Notifications
    case 'ntf_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Notifications\NotificationService())->list($p, $r, (int) ($r['page'] ?? 1), (int) ($r['per_page'] ?? 20)), false);
        break;

    case 'ntf_unread_count':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => ['unread_count' => (new \Pgs\Notifications\NotificationService())->unreadCount($p)], false);
        break;

    case 'ntf_mark_read':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            (new \Pgs\Notifications\NotificationService())->markRead($p, (int) ($r['id'] ?? 0));
            return ['read' => true];
        }, true);
        break;

    case 'ntf_mark_all_read':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $count = (new \Pgs\Notifications\NotificationService())->markAllRead($p);
            return ['read_count' => $count];
        }, true);
        break;

    case 'ntf_prefs_get':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => ['preferences' => (new \Pgs\Notifications\NotificationService())->getPreferences($p)], false);
        break;

    case 'ntf_prefs_save':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $prefs = is_array($r['preferences'] ?? null) ? $r['preferences'] : [];
            (new \Pgs\Notifications\NotificationService())->savePreferences($p, $prefs);
            return ['saved' => true];
        }, true);
        break;

    case 'ntf_defaults_get':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => ['defaults' => (new \Pgs\Notifications\NotificationService())->getDefaults($p)], false);
        break;

    case 'ntf_defaults_save':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $defs = is_array($r['defaults'] ?? null) ? $r['defaults'] : [];
            (new \Pgs\Notifications\NotificationService())->saveDefaults($p, $defs);
            return ['saved' => true];
        }, true);
        break;

    // Reports
    case 'rpt_catalog':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => [
            'reports' => \Pgs\Reports\ReportCatalog::forPrincipal($p),
        ], false);
        break;

    case 'rpt_generate':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        $key = (string) ($rqst['key'] ?? '');
        $unitId = (int) ($rqst['unit_id'] ?? ($rqst['hoa'] ?? 0));
        $from = (string) ($rqst['from'] ?? ($rqst['f1'] ?? ''));
        $to = (string) ($rqst['to'] ?? ($rqst['f2'] ?? ''));

        if (isset($rqst['json']) && (int) $rqst['json'] === 1) {
            echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) use ($key, $unitId, $from, $to) {
                return (new \Pgs\Reports\ReportDeliveryService())->generate($p, $key, $unitId, $from, $to);
            }, false);
        } else {
            try {
                $p = \Pgs\Core\Web\PgsPage::principal();
                (new \Pgs\Reports\ReportDeliveryService())->stream($p, $key, $unitId, $from, $to);
            } catch (\Pgs\Core\Exceptions\PgsException $e) {
                if (!headers_sent()) {
                    http_response_code($e->httpStatus());
                    header('Content-Type: text/plain; charset=utf-8');
                }
                echo $e->getMessage();
            } catch (\Throwable $e) {
                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: text/plain; charset=utf-8');
                }
                echo 'Report could not be generated';
            }
        }
        break;

    case 'rpt_send':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $key = (string) ($r['key'] ?? '');
            $unitId = (int) ($r['unit_id'] ?? 0);
            $from = (string) ($r['from'] ?? '');
            $to = (string) ($r['to'] ?? '');
            $channel = (string) ($r['channel'] ?? 'whatsapp');
            return (new \Pgs\Reports\ReportDeliveryService())->deliver($p, $key, $unitId, $from, $to, $channel);
        }, true);
        break;

    // Profile & User Settings
    case 'prf_get':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Profile\ProfileService())->get($p), false);
        break;

    case 'prf_save':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Profile\ProfileService())->save($p, $r), true);
        break;

    case 'prf_pin_set':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $pwd = (string) ($r['current_password'] ?? '');
            $pin = (string) ($r['pin'] ?? '');
            $confirm = (string) ($r['confirm_pin'] ?? '');
            return (new \Pgs\Profile\ProfileService())->setPin($p, $pwd, $pin, $confirm);
        }, true);
        break;

    case 'prf_pin_reset':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $targetUid = (int) ($r['user_id'] ?? 0);
            return (new \Pgs\Profile\ProfileService())->resetPinByAdmin($p, $targetUid);
        }, true);
        break;

    case 'prf_employee_link':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $targetUid = (int) ($r['user_id'] ?? 0);
            $empPk = (int) ($r['employee_pk'] ?? 0);
            return (new \Pgs\Profile\ProfileService())->linkEmployee($p, $targetUid, $empPk);
        }, true);
        break;

    case 'prf_internal_staff':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $targetUid = (int) ($r['user_id'] ?? 0);
            $isInt = !empty($r['is_internal_staff']);
            return (new \Pgs\Profile\ProfileService())->setInternalStaff($p, $targetUid, $isInt);
        }, true);
        break;

    // API Tokens
    case 'tok_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => [
            'tokens' => (new \Pgs\Integrations\ApiTokenService())->list($p),
        ], false);
        break;

    case 'tok_create':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Integrations\ApiTokenService())->create($p, $r), true);
        break;

    case 'tok_revoke':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $id = (int) ($r['id'] ?? 0);
            (new \Pgs\Integrations\ApiTokenService())->revoke($p, $id);
            return ['revoked' => true];
        }, true);
        break;

    // Integrations & Outbox
    case 'itg_status':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Integrations\IntegrationStatusService())->status($p), false);
        break;

    case 'itg_outbox_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $table = (string) ($r['table'] ?? 'notification');
            $status = (string) ($r['status'] ?? 'all');
            return [
                'items' => (new \Pgs\Integrations\IntegrationStatusService())->outboxList($p, $table, $status),
            ];
        }, false);
        break;

    case 'itg_retry':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $table = (string) ($r['table'] ?? 'notification');
            $id = (int) ($r['id'] ?? 0);
            (new \Pgs\Integrations\IntegrationStatusService())->retry($p, $table, $id);
            return ['retried' => true];
        }, true);
        break;

    case 'itg_retry_dead':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $table = (string) ($r['table'] ?? 'notification');
            $count = (new \Pgs\Integrations\IntegrationStatusService())->retryAllDead($p, $table);
            return ['retried_count' => $count];
        }, true);
        break;

    case 'itg_sync_all':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $includeMigrated = !empty($r['include_migrated']);
            return (new \Pgs\Integrations\IntegrationStatusService())->syncAll($p, $includeMigrated);
        }, true);
        break;

    case 'itg_wa_test':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Integrations\IntegrationStatusService())->sendTestWhatsApp($p), true);
        break;

    // AI Tool Logs
    case 'iax_logs':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $page = (int) ($r['page'] ?? 1);
            $perPage = (int) ($r['per_page'] ?? 30);
            return (new \Pgs\Logs\LogService())->iaLogs($p, $r, $page, $perPage);
        }, false);
        break;

    // Call Logs & Traces
    case 'aud_calls':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $page = (int) ($r['page'] ?? 1);
            $perPage = (int) ($r['per_page'] ?? 25);
            return (new \Pgs\Logs\LogService())->callLogs($p, $r, $page, $perPage);
        }, false);
        break;

    case 'aud_call_traces':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $sessionId = (int) ($r['session_id'] ?? 0);
            return [
                'traces' => (new \Pgs\Logs\LogService())->callSessionTraces($p, $sessionId),
            ];
        }, false);
        break;

    // Audit Log
    case 'aud_list':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $page = (int) ($r['page'] ?? 1);
            $perPage = (int) ($r['per_page'] ?? 30);
            return (new \Pgs\Logs\LogService())->auditLogs($p, $r, $page, $perPage);
        }, false);
        break;

    // Dashboard & Calendar Feed
    case 'tsk_dashboard':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static fn(\Pgs\Auth\Principal $p, array $r) => (new \Pgs\Dashboard\DashboardService())->summary($p), false);
        break;

    case 'cal_events':
        require_once __DIR__ . '/../bootstrap_pgs.php';
        echo \Pgs\Core\Web\PgsAjax::run($rqst, static function(\Pgs\Auth\Principal $p, array $r) {
            $from = (string) ($r['start'] ?? $r['from'] ?? date('Y-m-01'));
            $to = (string) ($r['end'] ?? $r['to'] ?? date('Y-m-t'));
            // Trim timezone or timestamp from FullCalendar if present
            if (str_contains($from, 'T')) $from = explode('T', $from)[0];
            if (str_contains($to, 'T')) $to = explode('T', $to)[0];
            return (new \Pgs\Calendar\CalendarFeedService())->events($p, $from, $to);
        }, false);
        break;

    default:
        echo 'OPERACION NO DISPONIBLE';

        break;
}

