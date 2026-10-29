<?php
/**
 * PGS Centrum - AI Assistant Widget Markup
 */
if (!defined('PGS_IA_WIDGET_RENDERED')) {
    return;
}
?>
<div id="pgsIaContainer">
  <!-- Trigger Button -->
  <button type="button" id="pgsIaBtn" title="PGS AI Assistant" aria-label="Open AI Assistant">
    <i class="fa fa-comments"></i>
  </button>

  <!-- Assistant Panel -->
  <div id="pgsIaPanel" style="display: none;">
    <!-- Panel Header -->
    <div class="pgs-ia-header">
      <div class="pgs-ia-title">
        <i class="fa fa-cogs"></i>
        <span>PGS Assistant</span>
      </div>
      <div class="pgs-ia-header-actions">
        <button type="button" id="pgsIaHistBtn" title="Conversation History" aria-label="History">
          <i class="fa fa-history"></i>
        </button>
        <button type="button" id="pgsIaNewBtn" title="New Conversation" aria-label="New Conversation">
          <i class="fa fa-plus"></i>
        </button>
        <button type="button" id="pgsIaCloseBtn" title="Close" aria-label="Close">
          <i class="fa fa-times"></i>
        </button>
      </div>
    </div>

    <!-- History Drawer (collapsible) -->
    <div id="pgsIaHistDrawer" style="display: none;">
      <div class="pgs-ia-hist-head">
        <span>History</span>
      </div>
      <div id="pgsIaHistList"></div>
    </div>

    <!-- Messages Container -->
    <div id="pgsIaMsgs"></div>

    <!-- Drag & Drop Overlay -->
    <div id="pgsIaDrop" style="display: none;">
      <div class="pgs-ia-drop-inner">
        <i class="fa fa-cloud-upload"></i>
        <span>Drop files here to attach</span>
      </div>
    </div>

    <!-- Pending Attachment Chips -->
    <div id="pgsIaChips"></div>

    <!-- Footer / Input Form -->
    <div class="pgs-ia-footer">
      <?php if ($pgsP->can('ia.adjuntos.use')): ?>
      <button type="button" id="pgsIaAttach" title="Attach file" aria-label="Attach file">
        <i class="fa fa-paperclip"></i>
      </button>
      <input type="file" id="pgsIaFileInput" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.csv,.md,.docx" style="display:none">
      <?php endif; ?>

      <textarea id="pgsIaText" placeholder="Type a message... (Shift+Enter for newline)" rows="1"></textarea>

      <?php if ($pgsP->can('ia.voz.use')): ?>
      <button type="button" id="pgsIaMic" title="Voice message" aria-label="Voice input">
        <i class="fa fa-microphone"></i>
      </button>
      <?php endif; ?>

      <button type="button" id="pgsIaSend" title="Send" aria-label="Send">
        <i class="fa fa-paper-plane"></i>
      </button>
    </div>
  </div>
</div>

<script>
window.PGS_IA_CFG = {
    voz: <?= json_encode($pgsP->can('ia.voz.use')) ?>,
    adjuntos: <?= json_encode($pgsP->can('ia.adjuntos.use')) ?>,
    csrf: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>,
    maxFiles: <?= (int) \Pgs\Core\Config::get('ia_attachments', 'max_files_per_message', 5) ?>,
    maxBytes: {
        image: <?= (int) \Pgs\Core\Config::get('ia_attachments', 'image_max_bytes', 10485760) ?>,
        pdf: <?= (int) \Pgs\Core\Config::get('ia_attachments', 'pdf_max_bytes', 15728640) ?>,
        text: <?= (int) \Pgs\Core\Config::get('ia_attachments', 'txt_max_bytes', 1048576) ?>,
        docx: <?= (int) \Pgs\Core\Config::get('ia_attachments', 'docx_max_bytes', 10485760) ?>
    }
};
</script>
<link rel="stylesheet" href="assets/css/pgs-ia-widget.css">
<script src="assets/js/ia-widget.js"></script>
