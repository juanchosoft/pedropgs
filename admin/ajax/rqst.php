<?php
session_start();
$rqst = $_REQUEST;
$op = isset($rqst['op']) ? $rqst['op'] : '';
header("Content-type: application/javascript; charset=utf-8");
header("Cache-Control: max-age=15, must-revalidate");
header('Access-Control-Allow-Origin: *');



include '../classes/DbConection.php';
include '../classes/Util.php';


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
        include '../classes/Requerimiento.php';
        echo json_encode(Requerimiento::save($rqst));
        break;

    case 'pms_reqdelete':
        include '../classes/Requerimiento.php';
        echo json_encode(Requerimiento::delete($rqst));
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

    case 'updateFields':
        include '../classes/Report.php';
        echo json_encode(Report::updateFields($rqst));
        break;

    case 'pms_saveentradasalida':
        include '../classes/EntradaSalida.php';
        echo json_encode(EntradaSalida::save($rqst));
        break;


    default:
        echo 'OPERACION NO DISPONIBLE';

        break;
}
