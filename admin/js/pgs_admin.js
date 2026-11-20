var PGSA = {
  esc: function (value) {
    return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
  },

  call: function (op, data) {
    return $.ajax({
      url: 'admin/ajax/rqst.php',
      type: 'POST',
      dataType: 'json',
      data: $.extend({ op: op }, data || {}),
      headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') }
    });
  },

  message: function (xhr) {
    var body = xhr && xhr.responseJSON;
    if (body && body.output && body.output.response && body.output.response.content) {
      var fields = body.output.response.fields || {};
      var first = Object.keys(fields)[0];
      return first ? fields[first] : body.output.response.content;
    }
    if (xhr && xhr.status === 403) {
      return 'Your session expired or you do not have access. Reload the page.';
    }
    return 'Something went wrong. Please try again.';
  },

  fail: function (xhr) {
    swal('Error', PGSA.message(xhr), 'error');
  },

  toast: function (title) {
    swal({ title: title, type: 'success', timer: 1200, showConfirmButton: false });
  }
};
