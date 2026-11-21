/* PGS Centrum: acciones robustas. Enlaces GET para abrir cada informe;
 * JS solo se necesita al GUARDAR datos, finalizar o eliminar. */
(function () {
  'use strict';
  function el(id) { return document.getElementById(id); }
  function notify(title, detail, type) {
    if (window.Swal && typeof Swal.fire === 'function') {
      Swal.fire({title: title, text: detail, icon: type || 'info'});
    } else if (typeof window.swal === 'function') {
      window.swal(title, detail, type || 'info');
    } else { alert(title + '\n' + detail); }
  }
  function setText(id, value) { if (el(id)) el(id).textContent = String(value || '—'); }
  function setImage(id, emptyId, source) {
    var image = el(id), empty = el(emptyId);
    if (!image || !empty) return;
    if (!source) { image.hidden = true; image.removeAttribute('src'); empty.classList.remove('d-none'); return; }
    image.hidden = false;
    empty.classList.add('d-none');
    image.onerror = function () { image.hidden = true; empty.classList.remove('d-none'); empty.textContent = 'Image unavailable'; };
    image.src = source;
  }
  var opened = window.PGS_SERVER_OPEN || null;
  if (opened && opened.row) {
    if (opened.action === 'after') {
      if (el('id')) el('id').value = String(opened.id);
      setText('item', opened.id);
      setText('pgsAfterContext', 'Report #' + opened.id + ' · ' + opened.row.unidad + ' · ' + opened.row.actividades);
    }
    if (opened.action === 'detail') {
      setText('view_detail_item', '#' + opened.id);
      ['estado', 'fecha', 'unidad', 'zone', 'actividades', 'observaciones'].forEach(function (field, index) {
        var properties = ['estado', 'dtcreate', 'unidad', 'zone', 'actividades', 'observaciones'];
        setText('view_detail_' + field, opened.row[properties[index]]);
      });
      setImage('view_detail_foto_antes', 'view_detail_foto_antes_empty', opened.row.before);
      setImage('view_detail_foto_despues', 'view_detail_foto_despues_empty', opened.row.after);
    }
  }
  var busy = false;
  async function save(params, button) {
    if (busy) return;
    busy = true;
    if (button) { button.disabled = true; button.dataset.oldText = button.textContent; button.textContent = 'Saving…'; }
    try {
      var res = await fetch('admin/ajax/rqst.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
        body: new URLSearchParams(params).toString(), cache: 'no-store'
      });
      var json;
      try { json = await res.json(); } catch (_) { throw new Error('Server did not return JSON (HTTP ' + res.status + ')'); }
      if (!res.ok || !json.output || json.output.valid !== true) {
        var reason = json && json.output && json.output.response && json.output.response.content;
        throw new Error(typeof reason === 'string' ? reason : 'The operation was rejected by the server');
      }
      notify('Saved', 'The report was updated successfully.', 'success');
      window.setTimeout(function () { window.location.href = 'report-list.php'; }, 850);
    } catch (err) {
      notify('Could not save', err.message || 'Connection error. Please verify the report before retrying.', 'error');
    } finally {
      busy = false;
      if (button) { button.disabled = false; button.textContent = button.dataset.oldText || 'Save'; }
    }
  }
  document.addEventListener('click', function (ev) {
    var saveButton = ev.target.closest('#pgsUpdateFields');
    if (saveButton) {
      ev.preventDefault();
      var rid = el('edit_report_id'), activities = el('actividades'), notes = el('observaciones');
      if (!rid || !activities || !notes || !activities.value.trim()) {
        notify('Required information', 'Complete the activity description before saving.', 'warning'); return;
      }
      save({op: 'updateFields', id: rid.value, actividades: activities.value.trim(), observaciones: notes.value.trim()}, saveButton);
      return;
    }
    var confirmButton = ev.target.closest('#pgsConfirmServerAction');
    if (confirmButton) {
      ev.preventDefault();
      var op = confirmButton.dataset.action === 'delete' ? 'delete_deport' : 'report_finalize';
      save({op: op, id: confirmButton.dataset.id}, confirmButton);
      return;
    }
    var close = ev.target.closest('.pgs-fallback-modal [data-dismiss="modal"]');
    if (close) { ev.preventDefault(); window.location.href = 'report-list.php'; }
  });
  window.PGS_REPORT_ACTIONS_READY = true;
})();
