var PGS = {
  csrf: function () {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  },

  call: function (op, data, cb, opts) {
    opts = opts || {};
    var payload = Object.assign({}, data || {}, { op: op });
    if (!opts.silent && typeof UTIL !== 'undefined' && UTIL.cursorBusy) {
      UTIL.cursorBusy();
    }
    return $.ajax({
      url: 'admin/ajax/rqst.php',
      type: 'POST',
      dataType: 'json',
      data: payload,
      headers: {
        'X-CSRF-Token': PGS.csrf()
      },
      success: function (resp) {
        if (!opts.silent && typeof UTIL !== 'undefined' && UTIL.cursorNormal) {
          UTIL.cursorNormal();
        }
        if (typeof UTIL !== 'undefined' && UTIL.handlePermissionResponse && UTIL.handlePermissionResponse(resp)) {
          return;
        }
        if (typeof cb === 'function') {
          cb(resp);
        }
      },
      error: function (xhr) {
        if (!opts.silent && typeof UTIL !== 'undefined' && UTIL.cursorNormal) {
          UTIL.cursorNormal();
        }
        if (typeof UTIL !== 'undefined' && UTIL.handleAjaxTransportError) {
          UTIL.handleAjaxTransportError(xhr);
        } else {
          PGS.error('Request failed with status ' + xhr.status);
        }
      }
    });
  },

  upload: function (op, formData, cb, onProgress) {
    formData.append('op', op);
    formData.append('csrf_token', PGS.csrf());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin/ajax/rqst.php', true);
    xhr.setRequestHeader('X-CSRF-Token', PGS.csrf());
    if (onProgress && xhr.upload) {
      xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
          onProgress(Math.round((e.loaded / e.total) * 100));
        }
      };
    }
    xhr.onload = function () {
      var resp = null;
      try {
        resp = JSON.parse(xhr.responseText);
      } catch (e) {}
      if (typeof UTIL !== 'undefined' && UTIL.handlePermissionResponse && UTIL.handlePermissionResponse(resp)) {
        return;
      }
      if (typeof cb === 'function') {
        cb(resp);
      }
    };
    xhr.onerror = function () {
      PGS.error('Upload failed due to network error');
    };
    xhr.send(formData);
  },

  esc: function (s) {
    if (s === null || s === undefined) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  },

  badge: function (status) {
    var s = String(status || '').toLowerCase();
    var labels = {
      'pending': 'Pending',
      'assigned': 'Assigned',
      'in_progress': 'In progress',
      'completed': 'Completed',
      'cancelled': 'Cancelled'
    };
    var label = labels[s] || status;
    return '<span class="pgs-badge-' + PGS.esc(s) + '">' + PGS.esc(label) + '</span>';
  },

  prio: function (p) {
    var pr = String(p || '').toLowerCase();
    var labels = {
      'low': 'Low',
      'normal': 'Normal',
      'high': 'High',
      'urgent': 'Urgent'
    };
    var label = labels[pr] || p;
    return '<span class="pgs-prio-' + PGS.esc(pr) + '">' + PGS.esc(label) + '</span>';
  },

  money: function (amount, currency) {
    var cur = currency || 'USD';
    var num = Number(amount || 0);
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + cur;
  },

  dateLocal: function (utc) {
    if (!utc) return '';
    if (typeof moment !== 'undefined') {
      var m = moment.utc(utc);
      if (window.PGS_TZ) {
        return m.tz(window.PGS_TZ).format('YYYY-MM-DD HH:mm');
      }
      return m.local().format('YYYY-MM-DD HH:mm');
    }
    return utc;
  },

  error: function (data) {
    var msg = 'An error occurred';
    if (typeof data === 'string') {
      msg = data;
    } else if (data && data.message) {
      msg = data.message;
    } else if (data && data.output && data.output.response && data.output.response.content) {
      msg = data.output.response.content;
    }
    if (typeof swal === 'function') {
      swal('Error', msg, 'error');
    } else {
      alert(msg);
    }
  },

  notify: function (message) {
    var text = String(message);
    var isError = /fail|error|invalid|cannot|could not|not allowed|denied|expired|incorrect|wrong|required|must|too |already|locked|unable|not linked|insufficient/i.test(text);
    if (window.Swal && typeof Swal.fire === 'function') {
      Swal.fire({ icon: isError ? 'error' : 'success', title: isError ? 'Error' : 'Done', text: text, confirmButtonColor: '#E10600' });
    } else if (window.console) {
      console.log(text);
    }
  },

  confirm: function (title, text, cb) {
    if (typeof swal === 'function') {
      swal({
        title: title,
        text: text,
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E10600',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
      }).then(function (result) {
        if (result && (result.value || result === true)) {
          cb();
        }
      });
    } else if (confirm(title + '\n' + text)) {
      cb();
    }
  }
};

window.PGS = PGS;

window.Pgs = (function () {
  function toast(message, type) {
    var icon = { success: 'success', error: 'error', warning: 'warning', info: 'info' }[type] || 'info';
    if (window.Swal && typeof Swal.fire === 'function') {
      Swal.fire({ toast: true, position: 'top-end', icon: icon, title: String(message), showConfirmButton: false, timer: 2500 });
    } else if (window.console) {
      console.log('[' + icon + '] ' + message);
    }
  }

  function ajax(op, data, opts) {
    opts = opts || {};
    var payload = Object.assign({}, data || {}, { op: op });
    return $.ajax({
      url: 'admin/ajax/rqst.php',
      type: 'POST',
      dataType: 'json',
      data: payload,
      headers: { 'X-CSRF-Token': PGS.csrf() },
      success: function (res) {
        if (res && res.ok === false) {
          if (opts.onError) opts.onError({ message: res.error || 'Request failed', response: res });
          else toast(res.error || 'Request failed', 'error');
          return;
        }
        if (opts.onSuccess) opts.onSuccess(res);
      },
      error: function (xhr) {
        var body = xhr.responseJSON || {};
        var message = body.error || (body.output && body.output.response && body.output.response.content) || ('Request failed (' + xhr.status + ')');
        if (opts.onError) opts.onError({ message: message, status: xhr.status, response: body });
        else toast(message, 'error');
      }
    });
  }

  return {
    ajax: ajax,
    toast: toast,
    api: {
      post: function (op, payload) {
        return new Promise(function (resolve, reject) {
          ajax(op, payload, { onSuccess: resolve, onError: reject });
        });
      }
    }
  };
})();
