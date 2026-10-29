/**
 * PGS Centrum - API Tokens Management
 */
$(document).ready(function() {
  'use strict';

  loadTokens();

  $('#btnRefreshTokens').on('click', function() {
    loadTokens();
  });

  function loadTokens() {
    var $tbody = $('#tokensTableBody');
    $tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i> Loading API tokens...</td></tr>');

    $.ajax({
      url: 'admin/ajax/rqst.php?op=tok_list',
      type: 'GET',
      dataType: 'json',
      success: function(resp) {
        if (!resp || !resp.output || !resp.output.valid) {
          $tbody.html('<tr><td colspan="9" class="text-center py-4 text-danger">Failed to load tokens: ' + (resp?.output?.response || 'Unknown error') + '</td></tr>');
          return;
        }

        var tokens = resp.output.response.tokens || [];
        if (tokens.length === 0) {
          $tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted">No API tokens found. Click "Generate New Token" to create one.</td></tr>');
          return;
        }

        var html = '';
        tokens.forEach(function(tok) {
          var scopesArr = tok.scopes ? tok.scopes.split(',') : [];
          var scopesBadges = scopesArr.map(function(s) {
            return '<span class="badge badge-light border text-dark mr-1 mb-1" style="font-size:0.75rem;">' + escapeHtml(s.trim()) + '</span>';
          }).join('');

          var statusBadge = '';
          if (tok.status === 'active') {
            statusBadge = '<span class="badge badge-success px-2 py-1">Active</span>';
          } else if (tok.status === 'expired') {
            statusBadge = '<span class="badge badge-warning px-2 py-1">Expired</span>';
          } else {
            statusBadge = '<span class="badge badge-secondary px-2 py-1">Revoked</span>';
          }

          var ipText = tok.ip_allowlist ? escapeHtml(tok.ip_allowlist) : '<span class="text-muted">Any IP</span>';
          var lastUsed = tok.last_used_at ? escapeHtml(tok.last_used_at.substring(0, 16)) : '<span class="text-muted">Never</span>';
          var expires = tok.expires_at ? escapeHtml(tok.expires_at.substring(0, 10)) : '<span class="text-muted">Never</span>';

          var actions = '';
          if (tok.status === 'active') {
            actions = '<button type="button" class="btn btn-outline-danger btn-sm btn-revoke-token" data-id="' + tok.id + '" data-name="' + escapeHtml(tok.name) + '">' +
                      '<i class="fa fa-ban mr-1"></i> Revoke</button>';
          } else {
            actions = '<span class="text-muted small">Inactive</span>';
          }

          html += '<tr>' +
            '<td class="font-weight-bold text-dark">' + escapeHtml(tok.name) + '</td>' +
            '<td><code class="text-danger font-weight-bold">' + escapeHtml(tok.token_prefix) + '...</code></td>' +
            '<td>' + scopesBadges + '</td>' +
            '<td class="small">' + ipText + '</td>' +
            '<td class="small text-muted">' + escapeHtml(tok.created_at.substring(0, 10)) + '</td>' +
            '<td class="small">' + lastUsed + '</td>' +
            '<td class="small">' + expires + '</td>' +
            '<td class="text-center">' + statusBadge + '</td>' +
            '<td class="text-right">' + actions + '</td>' +
          '</tr>';
        });

        $tbody.html(html);
      },
      error: function() {
        $tbody.html('<tr><td colspan="9" class="text-center py-4 text-danger">Server connection error while loading tokens.</td></tr>');
      }
    });
  }

  // Open modal
  $('#btnOpenNewTokenModal').on('click', function() {
    $('#formNewToken')[0].reset();
    $('#modalNewToken').modal('show');
  });

  // Submit new token
  $('#formNewToken').on('submit', function(e) {
    e.preventDefault();

    var name = $('#tokName').val().trim();
    if (!name) {
      PGS.notify('Token name is required.');
      return;
    }

    var selectedScopes = [];
    $('.tok-scope:checked').each(function() {
      selectedScopes.push($(this).val());
    });

    if (selectedScopes.length === 0) {
      PGS.notify('Please select at least one scope.');
      return;
    }

    var ipAllowlist = $('#tokIpAllowlist').val().trim();
    var expiresAt = $('#tokExpiresAt').val().trim();
    var rateLimit = parseInt($('#tokRateLimit').val(), 10) || 600;

    var btn = $('#btnSubmitToken');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Generating...');

    var csrf = $('meta[name="csrf-token"]').attr('content') || '';
    $.ajax({
      url: 'admin/ajax/rqst.php?op=tok_create',
      type: 'POST',
      data: {
        name: name,
        scopes: selectedScopes.join(','),
        ip_allowlist: ipAllowlist,
        expires_at: expiresAt,
        rate_limit_per_minute: rateLimit
      },
      headers: { 'X-CSRF-Token': csrf },
      dataType: 'json',
      success: function(resp) {
        if (!resp || !resp.output || !resp.output.valid) {
          PGS.notify('Failed to generate token: ' + (resp?.output?.response || 'Validation failed'));
          return;
        }

        var t = resp.output.response;
        $('#modalNewToken').modal('hide');

        // Populate created token display
        $('#createdFullToken').val(t.full_token);
        $('#createdTokName').text(t.name);
        $('#createdTokPrefix').text(t.token_prefix);
        $('#createdTokScopes').text(t.scopes);

        $('#modalTokenCreated').modal('show');
        loadTokens();
      },
      error: function(xhr) {
        var msg = 'Error generating API token.';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.error) msg = j.error;
          else if (j.output && j.output.response) msg = j.output.response;
        } catch(e) {}
        PGS.notify(msg);
      },
      complete: function() {
        btn.prop('disabled', false).html('Generate Token');
      }
    });
  });

  // Copy token button
  $('#btnCopyCreatedToken').on('click', function() {
    var input = document.getElementById('createdFullToken');
    input.select();
    input.setSelectionRange(0, 99999);
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(input.value).then(function() {
        PGS.notify('API token copied to clipboard!');
      });
    } else {
      document.execCommand('copy');
      PGS.notify('API token copied to clipboard!');
    }
  });

  // Revoke token
  $(document).on('click', '.btn-revoke-token', function() {
    var id = $(this).data('id');
    var name = $(this).data('name');
    if (!id) return;

    if (!confirm('Are you sure you want to revoke token "' + name + '"? Any service using this token will immediately lose access.')) {
      return;
    }

    var csrf = $('meta[name="csrf-token"]').attr('content') || '';
    $.ajax({
      url: 'admin/ajax/rqst.php?op=tok_revoke',
      type: 'POST',
      data: { id: id },
      headers: { 'X-CSRF-Token': csrf },
      dataType: 'json',
      success: function(r) {
        if (r && r.output && r.output.valid) {
          PGS.notify('Token "' + name + '" has been revoked.');
          loadTokens();
        } else {
          PGS.notify('Could not revoke token: ' + (r?.output?.response || 'Unknown error'));
        }
      },
      error: function() {
        PGS.notify('Server connection error while revoking token.');
      }
    });
  });

  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
});
