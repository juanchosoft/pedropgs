<?php
require './admin/include/generic_classes.php';
include './admin/classes/Report.php';
include './admin/classes/Zona.php';
//Permisos
$view = SessionData::getPermission(7);
$create = SessionData::getPermission(8);
$edit = SessionData::getPermission(19);
$delete = SessionData::getPermission(10);
//Validación
if (!$view) {
    require 'permiso_denegado.php';
}
$modulo = 'Activities report list - Edit Report';

$arrZonas = Zona::getAll(null);
$arrZonas = $arrZonas['output']['response'];
$option = '<option value="seleccione">Seleccione...</option>';
foreach ($arrZonas as $val) {
    $option .= "<option value='" . $val['id'] . "'>" . $val['zona'] . "</option>";
}

$arr = Report::getAll(null);
$isvalid = $arr['output']['valid'];
$arrReportData = [];

$userUnidad = SessionData::getUnidadUser(); // Obtener la unidad del usuario
$userType = SessionData::getUserType(); // Obtener el tipo de usuario

if ($isvalid) {
    $arr = $arr['output']['response'];

    foreach ($arr as $report) {
        // SuperAdmin ve todos los reportes
        if ($userType == Util::SuperAdmin()) {
            $arrReportData[] = $report;
            continue;
        }

        // Manager y Staff ven solo los reportes de su unidad
        if (($userType == Util::Manager() || $userType == Util::Staff()) && $userUnidad == $report['tbl_unidad_id']) {
            $arrReportData[] = $report;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include './admin/include/generic_head.php'; ?>
    <style>
    @media only screen and (max-width: 700px) {
        video {
            max-width: 100%;
        }
    }
    </style>
</head>
<?php date_default_timezone_set('America/Bogota'); ?>

<body>
    <div id="preloader">
        <div class="sk-three-bounce">
            <div class="sk-child sk-bounce1"></div>
            <div class="sk-child sk-bounce2"></div>
            <div class="sk-child sk-bounce3"></div>
        </div>
    </div>
    <?php include './admin/include/menu_movil_vistas.php'; ?> 
    <div id="main-wrapper">
        <?php include './admin/include/generic_header.php'; ?>
        <div class="deznav">
            <div class="deznav-scroll">
                <?php include './admin/include/generic_navbar.php'; ?>

            </div>
        </div>

        <div class="content-body">
            <div class="container-fluid">
                <div class="page-titles">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="javascript:void(0)">Activities</a></li>
                        <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo ?></a></li>
                    </ol>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Activities report list in the system</h4>
                                <div class="d-flex mt-3 mt-sm-0">
                                    <!--                   <?php if ($create) { ?>
                    <button class="btn btn-primary btn-rounded ml-3" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false"> New User</button>
                  <?php } ?> -->
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="dynamictable" class="table table-hover table-responsive-sm">
                                        <thead>
                                            <th>ITEM</th>
                                            <th>Activities</th>
                                            <th>Observations</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $c = count($arrReportData);
                                            if ($isvalid) {
                                                for ($i = 0; $i < $c; $i++) {

                                            ?>
                                            <tr>
                                                <td class="text-primary"><?php echo $arrReportData[$i]['id']; ?></td>
                                                <td class="text-primary">
                                                    <?php echo $arrReportData[$i]['actividades']; ?></td>
                                                <td class="text-primary">
                                                    <?php echo $arrReportData[$i]['observaciones']; ?></td>
                                                <td class="text-primary"><?php echo $arrReportData[$i]['dtcreate']; ?>
                                                </td>
                                                <td>

                                                    <?php
                                                            if ($edit) {
                                                            ?>
                                                    <button
                                                        onclick="REPORT.editPhotoAfter(<?php echo $arrReportData[$i]['id']; ?>);"
                                                        type="button" class="btn btn-outline-info btn-sm"
                                                        data-original-title="" title="Edit Photo after">
                                                        <svg width="20" height="20" fill="currentColor"
                                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M12 15.75a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path>
                                                            <path
                                                                d="M21.375 6.75h-3.89c-.141 0-.316-.09-.452-.234l-1.278-2.007c-.52-.759-.755-.759-1.599-.759H9.844c-.844 0-1.125 0-1.597.76l-1.28 2.006c-.104.113-.25.234-.404.234V6a.375.375 0 0 0-.375-.375H4.313A.375.375 0 0 0 3.938 6v.75H2.625A1.125 1.125 0 0 0 1.5 7.875v11.25a1.125 1.125 0 0 0 1.125 1.125h18.75a1.125 1.125 0 0 0 1.125-1.125V7.875a1.125 1.125 0 0 0-1.125-1.125Zm-9.164 10.495a4.5 4.5 0 1 1-.422-8.99 4.5 4.5 0 0 1 .422 8.99Z">
                                                            </path>
                                                        </svg>

                                                    </button>

                                                    <button type="button"
                                                        onclick="REPORT.editdata(<?php echo $arrReportData[$i]['id']; ?>);"
                                                        class="btn btn-outline-primary btn-sm" data-original-title=""
                                                        title="Edit">
                                                        <svg width="20" height="20" fill="currentColor"
                                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path
                                                                d="M4.828 21.754H2.25v-2.579L16.788 4.602l2.614 2.614L4.828 21.754Z">
                                                            </path>
                                                            <path
                                                                d="m19.956 6.656-2.612-2.612 1.484-1.437c.229-.23.58-.357.906-.357a1.214 1.214 0 0 1 .864.357l.797.797a1.213 1.213 0 0 1 .355.862c0 .328-.127.677-.357.907l-1.437 1.483Z">
                                                            </path>
                                                        </svg>
                                                    </button>


                                                    <?php
                                                            }

                                                            if ($delete) {
                                                            ?>



                                                    <button
                                                        onclick="REPORT.deletedata(<?php echo $arrReportData[$i]['id']; ?>);"
                                                        type="button" class="btn btn-outline-danger btn-sm"
                                                        data-original-title="" title="Delete">
                                                        <svg width="20" height="20" fill="currentColor"
                                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                            <path
                                                                d="M20.979 4.5H15.75V2.25A.75.75 0 0 0 15 1.5H9a.75.75 0 0 0-.75.75V4.5H3.021L3 6.375h1.547l.942 14.719A1.5 1.5 0 0 0 6.984 22.5h10.032a1.5 1.5 0 0 0 1.496-1.404l.941-14.721H21L20.979 4.5ZM8.25 19.5l-.422-12h1.547l.422 12H8.25Zm4.5 0h-1.5v-12h1.5v12Zm1.125-15h-3.75V3.187A.188.188 0 0 1 10.313 3h3.374a.188.188 0 0 1 .188.188V4.5Zm1.875 15h-1.547l.422-12h1.547l-.422 12Z">
                                                            </path>
                                                        </svg>
                                                    </button>

                                                    <?php
                                                            } ?>
                                                </td>
                                            </tr>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal editar solo campos -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header card-header card-header-danger">
                    <h4 class="modal-title">Edit report</h4>
                    <button type="button" onclick="UTIL.clearForm('formcreate');" class="close"
                        data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <form id="formcreate" autocomplete="off">
                        <input type="hidden" name="op" id="op" />
                        <input type="hidden" name="id" id="id" />
                        <div class="row">

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="bmd-label-floating">Activities<b class="errLbl">*</b></label>
                                    <input type="text" value="" style="text-transform: uppercase" id="actividades"
                                        name="actividades" class="form-control" placeholder="Detail activity">
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label class="bmd-label-floating">Observations</label>
                                    <input type="text" class="form-control" id="observaciones" name="observaciones"
                                        placeholder="write if you have any comments">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark btn-rounded"
                        onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancelar</button>
                    <button type="button" onclick="REPORT.updateFields();"
                        class="btn btn-primary btn-rounded">Guardar</button>
                </div>

            </div>
        </div>
    </div>


    <div class="modal fade" id="myModalAfterPhoto" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="frm_foto">
                    <div class="modal-header card-header card-header-danger">
                        <h4 class="modal-title">Edit photo after Item <span id="item"></span></h4>
                        <button type="button" onclick="UTIL.clearForm('frm_foto');" class="close"
                            data-dismiss="modal">&times;</button>
                    </div>

                    <div class="modal-body">
                        <div class="col-md-12">
                            <div class="card">

                                <div class="card-body table-responsive">
                                    <input type="hidden" name="op" id="op" />
                                    <input type="hidden" name="id" id="id" />

                                    <div class="row">
                                        <div class="col-sm-6">
                                            <div class="form-group">
                                                <p id="estado"></p>
                                                <div class="form-check radio_check">
                                                    <input class="form-check-input" type="radio" name="radio_select"
                                                        id="radiosfoto" value="1" checked>
                                                    <label class="form-check-label" for="radiosfoto">No picture</label>
                                                </div>
                                                <div class="form-check radio_check">
                                                    <input class="form-check-input" type="radio" name="radio_select"
                                                        id="radiotfoto" value="0">
                                                    <label class="form-check-label" for="radiotfoto">Take a
                                                        Picture</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12 text-center">
                                            <img class="defaultavatar img-fluid" src="assets/images/no-image.png"
                                                alt="">
                                            <video id="video" width="100%" autoplay="autoplay"
                                                class="video_container none mb-3"></video>

                                            <div id="selectcamdevice" style="display: none;">
                                                <h3>Select Cam</h3>
                                                <select name="listaDeDispositivos" id="listaDeDispositivos"></select>
                                            </div>
                                            <div>
                                                <canvas id="canvas" style="display: none;"></canvas>
                                                <button class="btn btn-primary btn-sm" type="submit"
                                                    id="btn_save">Save</button>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Modal footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-dark btn-rounded"
                            onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
                        <!-- <button type="submit" id="btn_save" class="btn btn-primary btn-rounded">Save</button> -->
                    </div>
                </form>
            </div>
        </div>
    </div>


    <?php include './admin/include/gerenic_footer.php'; ?>
    <?php include './admin/include/generic_search.php'; ?>

    <!-- Script -->
    <?php include './admin/include/gerenic_script.php'; ?>
    <?php include './admin/include/generic_dataTables.php'; ?>

    <script type="text/javascript" src="./admin/js/camara/camara.js"></script>
    <script type="text/javascript" src="./admin/js/camara/update.js"></script>
    <script type="text/javascript" src="./admin/js/report.js"></script>

    <!-- Script -->
</body>

</html>