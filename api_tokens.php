<?php

declare(strict_types=1);

require_once './admin/include/generic_classes.php';
require_once './admin/bootstrap_pgs.php';

requirePermission('integraciones.api_tokens.manage');

$p = \Pgs\Core\Web\PgsPage::principal();
$modulo = 'API Tokens';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($p->language() ?? 'en'); ?>">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>API Tokens - PGS Centrum</title>
</head>
<body>
  <div id="preloader">
    <div class="sk-three-bounce">
      <div class="sk-child sk-bounce1"></div>
      <div class="sk-child sk-bounce2"></div>
      <div class="sk-child sk-bounce3"></div>
    </div>
  </div>

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
            <li class="breadcrumb-item"><a href="main.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="javascript:void(0)">Integrations</a></li>
            <li class="breadcrumb-item active"><a href="javascript:void(0)">API Tokens</a></li>
          </ol>
        </div>

        <div class="pgs-ui">
          <!-- HERO -->
          <div class="pgs-hero mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
              <div>
                <h2 class="pgs-hero-title mb-1">
                  <i class="fa fa-key text-danger mr-2"></i> API Access Tokens
                </h2>
                <p class="text-muted mb-0">
                  Manage bearer tokens used by the MCP server, ElevenLabs AI agents, and external webhooks to access the PGS REST API securely.
                </p>
              </div>
              <div>
                <button type="button" class="btn btn-danger px-4" id="btnOpenNewTokenModal">
                  <i class="fa fa-plus mr-1"></i> Generate New Token
                </button>
              </div>
            </div>
          </div>

          <!-- TABLE CARD -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
              <h5 class="mb-0 font-weight-bold text-dark">
                <i class="fa fa-list text-danger mr-2"></i> Active & Historical API Tokens
              </h5>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefreshTokens">
                <i class="fa fa-refresh mr-1"></i> Refresh
              </button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="tokensTable">
                  <thead class="thead-light">
                    <tr>
                      <th class="font-weight-bold">Name</th>
                      <th class="font-weight-bold">Prefix</th>
                      <th class="font-weight-bold">Scopes</th>
                      <th class="font-weight-bold">IP Allowlist</th>
                      <th class="font-weight-bold">Created</th>
                      <th class="font-weight-bold">Last Used</th>
                      <th class="font-weight-bold">Expires</th>
                      <th class="font-weight-bold text-center">Status</th>
                      <th class="font-weight-bold text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="tokensTableBody">
                    <tr>
                      <td colspan="9" class="text-center py-4 text-muted">
                        <i class="fa fa-spinner fa-spin mr-2"></i> Loading API tokens...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: CREATE NEW TOKEN -->
  <div class="modal fade" id="modalNewToken" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-dark text-white py-3">
          <h5 class="modal-title font-weight-bold text-white">
            <i class="fa fa-key text-danger mr-2"></i> Generate API Token
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form id="formNewToken">
          <div class="modal-body p-4">
            <div class="form-group">
              <label class="font-weight-bold text-dark">Token Name / Client Description <span class="text-danger">*</span></label>
              <input type="text" id="tokName" class="form-control" required placeholder="e.g. MCP Server Prod, ElevenLabs Voice Webhook">
            </div>

            <div class="form-group">
              <label class="font-weight-bold text-dark mb-2">Authorized Scopes <span class="text-danger">*</span></label>
              <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox" class="custom-control-input tok-scope" id="scopeVoiceRead" value="voice:read" checked>
                <label class="custom-control-label" for="scopeVoiceRead">
                  <strong>voice:read</strong> - Read tasks, worker availability, schedules & customer units
                </label>
              </div>
              <div class="custom-control custom-checkbox mb-2">
                <input type="checkbox" class="custom-control-input tok-scope" id="scopeVoiceWrite" value="voice:write" checked>
                <label class="custom-control-label" for="scopeVoiceWrite">
                  <strong>voice:write</strong> - Create tasks, book technician reservations & log call transcripts
                </label>
              </div>
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input tok-scope" id="scopeFilesRead" value="files:read" checked>
                <label class="custom-control-label" for="scopeFilesRead">
                  <strong>files:read</strong> - Stream and verify task attachments and report PDFs
                </label>
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold text-dark">IP Allowlist (Optional)</label>
              <input type="text" id="tokIpAllowlist" class="form-control" placeholder="e.g. 192.168.1.50, 10.0.0.0/24">
              <small class="form-text text-muted">Comma-separated IPs or CIDR blocks. Leave empty to allow any IP.</small>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold text-dark">Expiration Date (Optional)</label>
                <input type="date" id="tokExpiresAt" class="form-control">
                <small class="form-text text-muted">Leave empty for no expiration.</small>
              </div>
              <div class="form-group col-md-6">
                <label class="font-weight-bold text-dark">Rate Limit (req/min)</label>
                <input type="number" id="tokRateLimit" class="form-control" value="600" min="10" max="6000">
                <small class="form-text text-muted">Default: 600 requests/minute.</small>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light py-2">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnSubmitToken">Generate Token</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL: TOKEN CREATED DISPLAY -->
  <div class="modal fade" id="modalTokenCreated" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-success text-white py-3">
          <h5 class="modal-title font-weight-bold text-white">
            <i class="fa fa-check-circle mr-2"></i> Token Created Successfully
          </h5>
        </div>
        <div class="modal-body p-4 text-center">
          <p class="text-danger font-weight-bold mb-3">
            <i class="fa fa-exclamation-triangle mr-1"></i> Make sure to copy your API token now. You will not be able to see it again!
          </p>

          <div class="input-group mb-3">
            <input type="text" id="createdFullToken" class="form-control font-weight-bold text-center bg-light text-dark" readonly style="font-family:monospace; font-size:0.95rem;">
            <div class="input-group-append">
              <button class="btn btn-dark" type="button" id="btnCopyCreatedToken">
                <i class="fa fa-copy mr-1"></i> Copy
              </button>
            </div>
          </div>

          <div class="text-left bg-light p-3 rounded small text-muted">
            <div><strong>Token Name:</strong> <span id="createdTokName"></span></div>
            <div><strong>Prefix:</strong> <span id="createdTokPrefix"></span></div>
            <div><strong>Scopes:</strong> <span id="createdTokScopes"></span></div>
          </div>
        </div>
        <div class="modal-footer bg-light py-2">
          <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Done & Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  <script src="admin/js/api_tokens.js"></script>
</body>
</html>
