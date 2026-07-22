<?php
/**
 * Botón flotante + modales propios para editar el último reporte no finalizado.
 * IDs únicos (pgs*) para no chocar con myModal de otras pantallas.
 */
if (!class_exists('SessionData') || !SessionData::getPermission(9)) {
    return;
}
?>
<style>
  .pgs-fab-wrap{
    position: fixed;
    right: 18px;
    bottom: 18px;
    z-index: 1050;
    display: none;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
  }
  .pgs-fab-wrap.is-visible{ display: flex; }
  .pgs-fab-panel{
    width: min(320px, calc(100vw - 36px));
    background: #fff;
    border: 1px solid #e7eaf1;
    border-radius: 18px;
    box-shadow: 0 18px 50px rgba(2,6,23,.18);
    padding: 14px;
  }
  .pgs-fab-panel .t{ font-weight: 1000; font-size: 14px; margin: 0 0 4px; color:#0B0F19; }
  .pgs-fab-panel .s{ font-size: 12px; color:#64748b; font-weight: 700; margin: 0 0 12px; }
  .pgs-fab-actions{ display:flex; gap:8px; flex-wrap:wrap; }
  .pgs-fab-actions .btn{ flex:1; border-radius: 12px; font-weight: 900; font-size: 12px; }
  .pgs-fab-main{
    width: 58px; height: 58px; border-radius: 999px; border: none;
    background: linear-gradient(135deg, #E10600, #B30500);
    color: #fff; box-shadow: 0 14px 30px rgba(225,6,0,.35);
    font-size: 22px; cursor: pointer;
  }
  .pgs-fab-close{
    border: none; background: transparent; color: #64748b;
    font-weight: 900; float: right; line-height: 1; padding: 0;
  }
</style>

<div class="pgs-fab-wrap" id="pgsReportFab" aria-live="polite">
  <div class="pgs-fab-panel" id="pgsReportFabPanel" style="display:none;">
    <button type="button" class="pgs-fab-close" id="pgsReportFabHide" title="Hide">&times;</button>
    <p class="t">Last unfinished report</p>
    <p class="s" id="pgsReportFabLabel">Item #—</p>
    <div class="pgs-fab-actions">
      <button type="button" class="btn btn-outline-primary" id="pgsReportFabEditDesc">Edit description</button>
      <button type="button" class="btn btn-outline-info" id="pgsReportFabEditPhoto">Edit photo</button>
      <button type="button" class="btn btn-outline-success" id="pgsReportFabFinalize">Finalize</button>
    </div>
  </div>
  <button type="button" class="pgs-fab-main" id="pgsReportFabToggle" title="Edit last report">
    <i class="fa fa-pencil"></i>
  </button>
</div>

<div class="modal fade" id="pgsReportEditModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header card-header card-header-danger">
        <h4 class="modal-title">Edit report</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="pgsReportEditForm" autocomplete="off">
          <input type="hidden" name="id" id="pgs_report_edit_id" />
          <div class="row">
            <div class="col-sm-6">
              <div class="form-group">
                <label class="bmd-label-floating">Activities<b class="errLbl">*</b></label>
                <input type="text" style="text-transform: uppercase" id="pgs_report_actividades" class="form-control" placeholder="Detail activity">
              </div>
            </div>
            <div class="col-sm-6">
              <div class="form-group">
                <label class="bmd-label-floating">Observations</label>
                <input type="text" class="form-control" id="pgs_report_observaciones" placeholder="write if you have any comments">
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark btn-rounded" data-dismiss="modal">Cancel</button>
        <button type="button" id="pgsReportEditSave" class="btn btn-primary btn-rounded">Save</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="pgsReportPhotoModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="pgsReportPhotoForm">
        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">Edit photo after Item <span id="pgs_report_photo_item"></span></h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="pgs_report_photo_id" />
          <div class="form-check radio_check">
            <input class="form-check-input" type="radio" name="pgs_radio_photo" id="pgs_radiosfoto" value="1" checked>
            <label class="form-check-label" for="pgs_radiosfoto">No picture</label>
          </div>
          <div class="form-check radio_check">
            <input class="form-check-input" type="radio" name="pgs_radio_photo" id="pgs_radiotfoto" value="0">
            <label class="form-check-label" for="pgs_radiotfoto">Take a Picture</label>
          </div>
          <div class="text-center mt-3">
            <img class="pgs-defaultavatar img-fluid" src="assets/images/no-image.png" alt="">
            <video id="pgs_video" width="100%" autoplay playsinline class="none mb-3"></video>
            <div id="pgs_selectcam" style="display:none;">
              <h3>Select Cam</h3>
              <select id="pgs_listaDeDispositivos"></select>
            </div>
            <canvas id="pgs_canvas" style="display:none;"></canvas>
            <button class="btn btn-primary btn-sm" type="submit" id="pgs_btn_save_photo">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
window.PGS_CAN_EDIT_REPORT = true;
</script>
<script src="./admin/js/report_quick_edit.js"></script>
