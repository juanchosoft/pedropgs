<?php

declare(strict_types=1);

require_once './admin/include/generic_classes.php';
require_once './admin/bootstrap_pgs.php';

$p = \Pgs\Core\Web\PgsPage::principal();
$modulo = 'My Profile';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($p->language() ?? 'en'); ?>">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>My Profile - PGS Centrum</title>
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
            <li class="breadcrumb-item active"><a href="javascript:void(0)">My Profile</a></li>
          </ol>
        </div>

        <div class="pgs-ui">
          <!-- HERO -->
          <div class="pgs-hero mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
              <div>
                <h2 class="pgs-hero-title mb-1">
                  <i class="fa fa-user-circle text-danger mr-2"></i> My Profile & Preferences
                </h2>
                <p class="text-muted mb-0">
                  Manage your personal contact details, notification preferences, and voice agent security credentials.
                </p>
              </div>
              <div>
                <span class="badge badge-dark px-3 py-2" id="badgeUserType">User Profile</span>
              </div>
            </div>
          </div>

          <div class="row">
            <!-- LEFT CARD: PROFILE & PREFERENCES -->
            <div class="col-xl-7 col-lg-12">
              <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                  <h5 class="mb-0 font-weight-bold text-dark">
                    <i class="fa fa-sliders text-danger mr-2"></i> Account Information & Preferences
                  </h5>
                </div>
                <div class="card-body">
                  <form id="formProfile">
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label class="font-weight-bold text-dark">Full Name</label>
                        <input type="text" id="profFullName" class="form-control bg-light" readonly>
                      </div>
                      <div class="form-group col-md-6">
                        <label class="font-weight-bold text-dark">Username / System Nickname</label>
                        <input type="text" id="profNickname" class="form-control bg-light" readonly>
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label class="font-weight-bold text-dark">
                          Phone Number (E.164) <span class="text-danger">*</span>
                        </label>
                        <input type="tel" id="profPhone" class="form-control" placeholder="+14075551234" required>
                        <small class="form-text text-muted">Include country code (e.g. +1 for US/PR).</small>
                      </div>
                      <div class="form-group col-md-6">
                        <label class="font-weight-bold text-dark">Notification Email</label>
                        <input type="email" id="profEmail" class="form-control" placeholder="user@pgscentrum.net">
                        <small class="form-text text-muted">Direct address for report & task deliveries.</small>
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="font-weight-bold text-dark">Interface Language</label>
                      <select id="profLang" class="form-control">
                        <option value="en">English (US)</option>
                        <option value="es">Español</option>
                      </select>
                    </div>

                    <div class="form-group mt-4 p-3 rounded" style="background:#f8fafc; border:1px solid #e2e8f0;">
                      <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="profWhatsappOptIn">
                        <label class="custom-control-label font-weight-bold text-dark" for="profWhatsappOptIn">
                          <i class="fa fa-whatsapp text-success mr-1"></i> Receive WhatsApp Notifications
                        </label>
                      </div>
                      <p class="text-muted small mb-1 mt-2">
                        By checking this box, I authorize PGS Centrum to send automated service notifications, emergency dispatch alerts, and document downloads to my registered phone number via WhatsApp.
                      </p>
                      <small class="text-secondary" id="optInTimestampLabel"></small>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                      <button type="submit" class="btn btn-danger px-4" id="btnSaveProfile">
                        <i class="fa fa-check mr-1"></i> Save Changes
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- RIGHT CARD: VOICE PIN SECURITY -->
            <div class="col-xl-5 col-lg-12">
              <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                  <h5 class="mb-0 font-weight-bold text-dark">
                    <i class="fa fa-shield text-danger mr-2"></i> Voice Agent Security (PIN)
                  </h5>
                </div>
                <div class="card-body">
                  <div class="d-flex align-items-center justify-content-between p-3 rounded mb-3" style="background:#0B0F19; color:#fff;">
                    <div>
                      <div class="text-uppercase small font-weight-bold text-secondary">PIN Status</div>
                      <h4 class="mb-0 font-weight-bold text-white" id="voicePinStatusLabel">Loading...</h4>
                    </div>
                    <div>
                      <span class="badge badge-pill badge-warning px-3 py-2" id="voicePinBadge">Checking</span>
                    </div>
                  </div>

                  <p class="text-muted small">
                    Your <strong>Voice PIN</strong> is a 6-digit passcode used to verify your identity when you call PGS Centrum's automated AI voice assistant or request restricted property actions over the phone.
                  </p>

                  <ul class="list-unstyled text-muted small mb-4">
                    <li><i class="fa fa-check text-success mr-2"></i> Exactly 6 numerical digits</li>
                    <li><i class="fa fa-times text-danger mr-2"></i> No repeated digits (e.g. 111111)</li>
                    <li><i class="fa fa-times text-danger mr-2"></i> No sequential numbers (e.g. 123456)</li>
                  </ul>

                  <button type="button" class="btn btn-dark btn-block py-2" id="btnOpenPinModal">
                    <i class="fa fa-key mr-2"></i> Set / Change Voice PIN
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: SET / CHANGE VOICE PIN -->
  <div class="modal fade" id="modalPin" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-dark text-white py-3">
          <h5 class="modal-title font-weight-bold text-white">
            <i class="fa fa-lock text-danger mr-2"></i> Set / Change Voice PIN
          </h5>
          <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form id="formPin">
          <div class="modal-body p-4">
            <div class="form-group">
              <label class="font-weight-bold text-dark">Current Account Password <span class="text-danger">*</span></label>
              <input type="password" id="pinCurrentPassword" class="form-control" required placeholder="Enter login password to verify">
              <small class="form-text text-muted">Your current PGS Centrum login password.</small>
            </div>

            <div class="form-group">
              <label class="font-weight-bold text-dark">New 6-Digit Voice PIN <span class="text-danger">*</span></label>
              <input type="password" id="pinNew" class="form-control text-center font-weight-bold" style="letter-spacing:6px; font-size:1.2rem;" maxlength="6" pattern="\d{6}" required placeholder="••••••">
              <small class="form-text text-muted">Exactly 6 numbers. No repeats (333333) or sequences (123456).</small>
            </div>

            <div class="form-group">
              <label class="font-weight-bold text-dark">Confirm New PIN <span class="text-danger">*</span></label>
              <input type="password" id="pinConfirm" class="form-control text-center font-weight-bold" style="letter-spacing:6px; font-size:1.2rem;" maxlength="6" pattern="\d{6}" required placeholder="••••••">
            </div>
          </div>
          <div class="modal-footer bg-light py-2">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnSubmitPin">Save PIN</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  <script src="admin/js/my_profile.js"></script>
</body>
</html>
