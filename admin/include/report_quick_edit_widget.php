<?php
/**
 * Barra de notificación + modales para editar el último reporte no finalizado.
 * Se incluye solo en main.php (dashboard). IDs pgs* para no chocar con otras pantallas.
 */
if (!class_exists('SessionData') || !SessionData::getPermission(9)) {
    return;
}
?>
<style>
  .pgs-report-bar{
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin: 0 0 14px;
    padding: 12px 14px;
    border-radius: 16px;
    border: 1px solid rgba(225,6,0,.22);
    background:
      linear-gradient(135deg, rgba(225,6,0,.08), rgba(255,255,255,.96)),
      #fff;
    box-shadow: 0 10px 28px rgba(2,6,23,.08);
  }
  .pgs-report-bar.is-visible{ display: flex; }
  .pgs-report-bar-left{
    display:flex;
    align-items:flex-start;
    gap:10px;
    min-width: 0;
    flex: 1 1 240px;
  }
  .pgs-report-bar-icon{
    width: 38px; height: 38px; border-radius: 12px; flex: 0 0 auto;
    display:inline-flex; align-items:center; justify-content:center;
    background: linear-gradient(135deg, #E10600, #B30500);
    color:#fff; font-size: 16px;
    box-shadow: 0 10px 20px rgba(225,6,0,.25);
  }
  .pgs-report-bar-copy{ min-width:0; }
  .pgs-report-bar-copy .t{
    margin:0;
    font-weight: 1000;
    font-size: 14px;
    color:#0B0F19;
    line-height:1.2;
  }
  .pgs-report-bar-copy .s{
    margin: 4px 0 0;
    font-size: 12px;
    color:#64748b;
    font-weight: 700;
    word-break: break-word;
  }
  .pgs-report-bar-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
  }
  .pgs-report-bar-actions .btn{
    border-radius: 12px;
    font-weight: 900;
    font-size: 12px;
    white-space: nowrap;
  }
  @media (max-width: 576px){
    .pgs-report-bar-actions{ width:100%; }
    .pgs-report-bar-actions .btn{ flex: 1 1 auto; }
  }
</style>

<div class="pgs-report-bar" id="pgsReportBar" aria-live="polite" style="display:none;">
  <div class="pgs-report-bar-left">
    <div class="pgs-report-bar-icon" aria-hidden="true"><i class="fa fa-pencil"></i></div>
    <div class="pgs-report-bar-copy">
      <p class="t">Unfinished report pending</p>
      <p class="s" id="pgsReportBarLabel">Item #—</p>
    </div>
  </div>
  <div class="pgs-report-bar-actions">
    <button type="button" class="btn btn-outline-primary btn-sm" id="pgsReportFabEditDesc">Edit description</button>
    <button type="button" class="btn btn-outline-info btn-sm" id="pgsReportFabEditPhoto">Edit photo</button>
    <button type="button" class="btn btn-outline-success btn-sm" id="pgsReportFabFinalize">Finalize</button>
  </div>
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
