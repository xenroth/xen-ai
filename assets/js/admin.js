/* global jQuery, xenAIAdmin */
(function ($) {
  'use strict';

  /* ── Helpers ──────────────────────────────────────────── */
  function showNotice ($el, msg, type) {
    $el.removeClass('xen-ai-notice-warn xen-ai-notice-ok xen-ai-notice-error')
       .addClass('xen-ai-notice-' + (type || 'ok'))
       .html(msg)
       .slideDown(200);
    setTimeout(function () { $el.slideUp(300); }, 5000);
  }

  /* ─────────────────────────────────────────────────────── */
  /* Provider tab toggle                                     */
  /* ─────────────────────────────────────────────────────── */
  $(document).on('change', 'input[name="provider"]', function () {
    var isGitHub = $(this).val() === 'github';
    $('#xen-openai-fields').toggle(!isGitHub);
    $('#xen-github-fields').toggle(isGitHub);
    // Update tab active state
    $('.xen-provider-tab').removeClass('active');
    $(this).closest('.xen-provider-tab').addClass('active');
  });

  /* ─────────────────────────────────────────────────────── */
  /* Bot logo uploader (WP Media Library)                   */
  /* ─────────────────────────────────────────────────────── */
  var xenLogoFrame;

  $(document).on('click', '#xen-upload-logo-btn', function (e) {
    e.preventDefault();
    if (xenLogoFrame) { xenLogoFrame.open(); return; }

    xenLogoFrame = wp.media({
      title:    'Choose Bot Logo',
      button:   { text: 'Use this image' },
      library:  { type: 'image' },
      multiple: false
    });

    xenLogoFrame.on('select', function () {
      var attachment = xenLogoFrame.state().get('selection').first().toJSON();
      var url = attachment.url;
      $('#xen-bot-logo-url').val(url);
      $('#xen-logo-preview')
        .html('<img src="' + $('<div>').text(url).html() + '" alt="Bot logo">')
        .find('img').css({ width: '100%', height: '100%', objectFit: 'cover', borderRadius: '50%' });
      $('#xen-remove-logo-btn').show();
    });

    xenLogoFrame.open();
  });

  $(document).on('click', '#xen-remove-logo-btn', function () {
    $('#xen-bot-logo-url').val('');
    $('#xen-logo-preview').html('<span class="xen-logo-placeholder">&#x26A1;</span>');
    $(this).hide();
  });

  /* ─────────────────────────────────────────────────────── */
  /* Settings form                                           */
  /* ─────────────────────────────────────────────────────── */
  $(document).on('submit', '#xen-settings-form', function (e) {
    e.preventDefault();

    var $form    = $(this);
    var $btn     = $form.find('[type="submit"]');
    var $spinner = $('#xen-settings-spinner');
    var $notice  = $('#xen-settings-notice');
    var data     = $form.serializeArray();

    // Inject nonce
    data.push({ name: 'action', value: 'xen_ai_save_settings' });
    data.push({ name: 'nonce',  value: $('#xen-settings-nonce').val() });

    $btn.prop('disabled', true);
    $spinner.show();

    $.post(xenAIAdmin.ajaxUrl, data)
      .done(function (res) {
        if (res.success) {
          showNotice($notice, '✔ ' + (res.data.message || xenAIAdmin.i18n.saved), 'ok');
        } else {
          showNotice($notice, '✘ ' + (res.data.message || xenAIAdmin.i18n.error), 'error');
        }
      })
      .fail(function () { showNotice($notice, '✘ ' + xenAIAdmin.i18n.error, 'error'); })
      .always(function () { $btn.prop('disabled', false); $spinner.hide(); });
  });

  /* ─────────────────────────────────────────────────────── */
  /* Knowledge Base — file upload                           */
  /* ─────────────────────────────────────────────────────── */
  var $dropZone    = $('#xen-drop-zone');
  var $fileInput   = $('#xen-file-input');
  var $progressBox = $('#xen-upload-progress');
  var $progressFill = $('#xen-progress-fill');
  var $progressLbl = $('#xen-progress-label');
  var $kbNotice    = $('#xen-kb-notice');

  // Click-to-browse
  $('#xen-browse-btn').on('click', function () { $fileInput.trigger('click'); });
  $dropZone.on('click', function (e) {
    if (!$(e.target).is('button')) $fileInput.trigger('click');
  });

  // Drag & drop events
  $dropZone
    .on('dragover dragenter', function (e) { e.preventDefault(); $(this).addClass('xen-drag-over'); })
    .on('dragleave drop',     function (e) { e.preventDefault(); $(this).removeClass('xen-drag-over'); if (e.type === 'drop') uploadFile(e.originalEvent.dataTransfer.files[0]); });

  $fileInput.on('change', function () {
    if (this.files && this.files[0]) uploadFile(this.files[0]);
  });

  function uploadFile (file) {
    if (!file) return;

    var fd = new FormData();
    fd.append('action', 'xen_ai_upload_kb');
    fd.append('nonce',  xenAIAdmin.nonce);
    fd.append('kb_file', file);

    $dropZone.hide();
    $progressBox.show();
    $progressLbl.text(xenAIAdmin.i18n.processing + ' ' + file.name);

    // Fake progress animation while waiting
    var pct = 0;
    var ticker = setInterval(function () {
      pct = Math.min(pct + 4, 90);
      $progressFill.css('width', pct + '%');
    }, 120);

    $.ajax({
      url:         xenAIAdmin.ajaxUrl,
      method:      'POST',
      data:        fd,
      processData: false,
      contentType: false,
    })
    .done(function (res) {
      clearInterval(ticker);
      $progressFill.css('width', '100%');
      setTimeout(function () {
        $progressBox.hide();
        $dropZone.show();
        $progressFill.css('width', '0');
        if (res.success) {
          showNotice($kbNotice, '✔ ' + res.data.message, 'ok');
          appendKbRow(res.data.id, res.data.title, 'FILE', '—');
        } else {
          showNotice($kbNotice, '✘ ' + (res.data.message || xenAIAdmin.i18n.error), 'error');
        }
        $fileInput.val('');
      }, 500);
    })
    .fail(function () {
      clearInterval(ticker);
      $progressBox.hide();
      $dropZone.show();
      $progressFill.css('width', '0');
      showNotice($kbNotice, '✘ ' + xenAIAdmin.i18n.error, 'error');
      $fileInput.val('');
    });
  }

  /* ── URL add ────────────────────────────────────────────── */
  $('#xen-add-url-btn').on('click', function () {
    var url = $('#xen-url-input').val().trim();
    if (!url) return;

    var $btn    = $(this);
    var $status = $('#xen-url-status');

    $btn.prop('disabled', true).text(xenAIAdmin.i18n.processing);
    $status.text('Fetching content…');

    $.post(xenAIAdmin.ajaxUrl, {
      action : 'xen_ai_add_url_kb',
      nonce  : xenAIAdmin.nonce,
      url    : url,
    })
    .done(function (res) {
      if (res.success) {
        showNotice($kbNotice, '✔ ' + res.data.message, 'ok');
        appendKbRow(res.data.id, res.data.title, 'URL', url);
        $('#xen-url-input').val('');
        $status.text('');
      } else {
        $status.css('color', '#ef4444').text('Error: ' + (res.data.message || xenAIAdmin.i18n.error));
      }
    })
    .fail(function () { $status.css('color','#ef4444').text(xenAIAdmin.i18n.error); })
    .always(function () { $btn.prop('disabled', false).text('Fetch & Add'); });
  });

  /* ── KB row delete ──────────────────────────────────────── */
  $(document).on('click', '.xen-kb-delete', function () {
    if (!confirm(xenAIAdmin.i18n.confirmDelete)) return;

    var id   = $(this).data('id');
    var $row = $('#xen-kb-row-' + id);
    var $btn = $(this);

    $btn.prop('disabled', true).text('…');

    $.post(xenAIAdmin.ajaxUrl, {
      action : 'xen_ai_delete_kb',
      nonce  : xenAIAdmin.nonce,
      id     : id,
    })
    .done(function (res) {
      if (res.success) {
        $row.fadeOut(300, function () { $(this).remove(); });
        showNotice($kbNotice, '✔ ' + res.data.message, 'ok');
      } else {
        showNotice($kbNotice, '✘ ' + (res.data.message || xenAIAdmin.i18n.error), 'error');
        $btn.prop('disabled', false).text('Delete');
      }
    });
  });

  /* ── Append a new row to the KB table ────────────────────── */
  function appendKbRow (id, title, type, source) {
    var $tbody = $('#xen-kb-table tbody');

    // Create table if it doesn't exist yet
    if (!$tbody.length) {
      var html = '<table class="xen-ai-table" id="xen-kb-table"><thead><tr>'
        + '<th>#</th><th>Title</th><th>Type</th><th>Source</th><th>Added</th><th>Action</th>'
        + '</tr></thead><tbody></tbody></table>';
      $('.xen-ai-empty-msg').replaceWith(html);
      $tbody = $('#xen-kb-table tbody');
    }

    var rowNum = $tbody.find('tr').length + 1;
    var src    = (source && source !== '—')
      ? '<a href="' + $('<span>').text(source).html() + '" target="_blank" rel="noopener noreferrer">' + $('<span>').text(source).html() + '</a>'
      : '<em>—</em>';

    var $row = $('<tr id="xen-kb-row-' + id + '">'
      + '<td>' + rowNum + '</td>'
      + '<td>' + $('<span>').text(title).html() + '</td>'
      + '<td><span class="xen-ai-badge xen-ai-badge-' + type.toLowerCase() + '">' + type + '</span></td>'
      + '<td class="xen-ai-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + src + '</td>'
      + '<td class="xen-ai-muted">Just now</td>'
      + '<td><button class="xen-ai-btn xen-ai-btn-danger xen-ai-btn-sm xen-kb-delete" data-id="' + id + '">Delete</button></td>'
      + '</tr>');

    $row.hide();
    $tbody.prepend($row);
    $row.fadeIn(300);
  }

  /* ─────────────────────────────────────────────────────── */
  /* Leads — delete conversation                            */
  /* ─────────────────────────────────────────────────────── */
  $(document).on('click', '.xen-delete-convo', function () {
    if (!confirm(xenAIAdmin.i18n.confirmConvo)) return;

    var id   = $(this).data('id');
    var $row = $('#xen-lead-row-' + id);
    var $btn = $(this);

    $btn.prop('disabled', true).text('…');

    $.post(xenAIAdmin.ajaxUrl, {
      action : 'xen_ai_delete_convo',
      nonce  : xenAIAdmin.nonce,
      id     : id,
    })
    .done(function (res) {
      if (res.success) {
        $row.fadeOut(300, function () { $(this).remove(); });
        showNotice($('#xen-leads-notice'), '✔ ' + res.data.message, 'ok');
      } else {
        $btn.prop('disabled', false).text('Delete');
        showNotice($('#xen-leads-notice'), '✘ ' + (res.data.message || xenAIAdmin.i18n.error), 'error');
      }
    });
  });

  /* ─────────────────────────────────────────────────────── */
  /* Leads — view conversation messages (AJAX)              */
  /* ─────────────────────────────────────────────────────── */
  // The AJAX action is handled inline in leads.php for the modal.
  // Here we register the server-side handler registration note:
  // action = xen_ai_get_messages (registered in class-admin.php below)

})(jQuery);
