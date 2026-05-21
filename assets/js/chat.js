/* global jQuery, xenAI */
(function ($) {
  'use strict';

  var XenChat = {

    sessionId   : null,
    isOpen      : false,
    greeted     : false,
    sending     : false,
    noticeTimer : null,

    /* ── Init ──────────────────────────────────────────── */
    init: function () {
      this.applyAccentColor();
      this.bindEvents();
      this.startSession();

      // Show notification bubble after configured delay
      if (xenAI.notifyDelay >= 0) {
        setTimeout(function () { XenChat.showNotification(); }, parseInt(xenAI.notifyDelay, 10) || 4000);
      }
    },

    /* ── Apply accent colour via CSS custom property ───── */
    applyAccentColor: function () {
      var hex = xenAI.accentColor || '#4f46e5';
      var rgb = XenChat.hexToRgb(hex);
      var el  = document.getElementById('xen-ai-widget');
      if (!el) return;
      el.style.setProperty('--xen-accent',      hex);
      el.style.setProperty('--xen-accent-dark',  XenChat.darken(hex));
      el.style.setProperty('--xen-user-bubble',  hex);
      el.style.setProperty('--xen-header-bg', 'linear-gradient(135deg,' + hex + ',' + XenChat.darken(hex, 30) + ')');
      if (rgb) el.style.setProperty('--xen-accent-rgb', rgb.r + ',' + rgb.g + ',' + rgb.b);
    },

    /* ── Start a server-side session ───────────────────── */
    startSession: function () {
      $.post(xenAI.ajaxUrl, {
        action   : 'xen_ai_init_session',
        nonce    : xenAI.nonce,
        page_url : window.location.href,
      })
      .done(function (res) {
        if (res.success && res.data.session_id) {
          XenChat.sessionId = res.data.session_id;
        }
      });
    },

    /* ── Event bindings ─────────────────────────────────── */
    bindEvents: function () {
      $('#xen-ai-toggle').on('click',      function () { XenChat.toggle(); });
      $('.xen-ai-win-close').on('click',   function () { XenChat.close(); });
      $('.xen-ai-notification-close').on('click', function (e) {
        e.stopPropagation();
        XenChat.hideNotification();
      });

      // Clicking the notification bubble opens the chat
      $('#xen-ai-notification').on('click', function () { XenChat.open(); });

      // Send on button click
      $('#xen-ai-send').on('click', function () { XenChat.sendMessage(); });

      // Send on Enter (shift+enter = newline)
      $('#xen-ai-input').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          XenChat.sendMessage();
        }
      });

      // Auto-resize textarea
      $('#xen-ai-input').on('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
      });

      // Close on ESC
      $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && XenChat.isOpen) XenChat.close();
      });
    },

    /* ── Open / close / toggle ──────────────────────────── */
    toggle: function () {
      this.isOpen ? this.close() : this.open();
    },

    open: function () {
      this.isOpen = true;
      this.hideNotification();

      var $win = $('#xen-ai-window');
      $win.removeClass('xen-closing').addClass('xen-open').attr('aria-hidden', 'false');

      $('#xen-ai-toggle')
        .attr('aria-expanded', 'true')
        .attr('aria-label', 'Close chat');
      $('.xen-icon-open').hide();
      $('.xen-icon-close').show();

      // Show greeting only once
      if (!this.greeted) {
        this.greeted = true;
        var self = this;
        setTimeout(function () {
          self.appendMessage('assistant', xenAI.greeting);
        }, 350);
      }

      setTimeout(function () { $('#xen-ai-input').focus(); }, 400);
    },

    close: function () {
      this.isOpen = false;

      var $win = $('#xen-ai-window');
      $win.addClass('xen-closing');
      setTimeout(function () { $win.removeClass('xen-open xen-closing').attr('aria-hidden', 'true'); }, 220);

      $('#xen-ai-toggle')
        .attr('aria-expanded', 'false')
        .attr('aria-label', 'Open chat');
      $('.xen-icon-open').show();
      $('.xen-icon-close').hide();
    },

    /* ── Notification ───────────────────────────────────── */
    showNotification: function () {
      if (this.isOpen) return;
      $('#xen-ai-notification-text').text(xenAI.notifyMsg);
      $('#xen-ai-notification').fadeIn(300);

      clearTimeout(this.noticeTimer);
      this.noticeTimer = setTimeout(function () {
        XenChat.hideNotification();
      }, 8000);
    },

    hideNotification: function () {
      clearTimeout(this.noticeTimer);
      $('#xen-ai-notification').fadeOut(250);
    },

    /* ── Messages ───────────────────────────────────────── */
    appendMessage: function (role, text) {
      var isUser  = role === 'user';
      var $msg    = $('<div class="xen-ai-message ' + role + '"></div>');
      var $bubble = $('<div class="xen-ai-msg-bubble"></div>').html(this.escapeText(text));

      if (!isUser) {
        var $avatar = $('<div class="xen-ai-msg-avatar"></div>');
        if (xenAI.botLogoUrl) {
          $avatar.addClass('xen-avatar-img').html('<img src="' + xenAI.botLogoUrl + '" alt="' + xenAI.botName + '" loading="lazy">');
        } else {
          $avatar.addClass('xen-avatar-emoji').html('&#x26A1;');
        }
        $msg.append($avatar);
      }
      $msg.append($bubble);

      $('#xen-ai-messages').append($msg);
      this.scrollDown();
    },

    escapeText: function (text) {
      // Escape HTML, then convert newlines to <br>
      return $('<div>').text(text).html().replace(/\n/g, '<br>');
    },

    scrollDown: function () {
      var el = document.getElementById('xen-ai-messages');
      if (el) el.scrollTop = el.scrollHeight;
    },

    showTyping: function () {
      var $t = $('#xen-ai-typing');
      $t.html('<div class="xen-ai-typing-inner"><span></span><span></span><span></span></div>');
      $t.show();
      this.scrollDown();
    },

    hideTyping: function () { $('#xen-ai-typing').hide().empty(); },

    /* ── Send a message ─────────────────────────────────── */
    sendMessage: function () {
      if (this.sending) return;

      var message = $.trim($('#xen-ai-input').val());
      if (!message) return;
      if (!this.sessionId) {
        this.appendMessage('assistant', 'Session not ready. Please refresh the page.');
        return;
      }

      // Clear input
      $('#xen-ai-input').val('').css('height', 'auto');
      this.appendMessage('user', message);
      this.showTyping();

      this.sending = true;
      $('#xen-ai-send').prop('disabled', true);

      var self = this;
      $.post(xenAI.ajaxUrl, {
        action     : 'xen_ai_chat',
        nonce      : xenAI.nonce,
        message    : message,
        session_id : self.sessionId,
      })
      .done(function (res) {
        self.hideTyping();
        if (res.success) {
          self.appendMessage('assistant', res.data.reply);
        } else {
          self.appendMessage('assistant', res.data.message || 'Sorry, something went wrong. Please try again.');
        }
      })
      .fail(function () {
        self.hideTyping();
        self.appendMessage('assistant', 'Sorry, I could not reach the server. Please check your connection and try again.');
      })
      .always(function () {
        self.sending = false;
        $('#xen-ai-send').prop('disabled', false);
        $('#xen-ai-input').focus();
      });
    },

    /* ── Colour helpers ─────────────────────────────────── */
    hexToRgb: function (hex) {
      var r = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
      return r ? { r: parseInt(r[1], 16), g: parseInt(r[2], 16), b: parseInt(r[3], 16) } : null;
    },

    darken: function (hex, amount) {
      amount = amount || 20;
      var rgb = this.hexToRgb(hex);
      if (!rgb) return hex;
      var r = Math.max(0, rgb.r - amount);
      var g = Math.max(0, rgb.g - amount);
      var b = Math.max(0, rgb.b - amount);
      return '#' + [r, g, b].map(function (v) { return ('0' + v.toString(16)).slice(-2); }).join('');
    },
  };

  $(document).ready(function () {
    if ($('#xen-ai-widget').length) {
      XenChat.init();
    }
  });

})(jQuery);
