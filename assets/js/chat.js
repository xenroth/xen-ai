/* global jQuery, xenAI, turnstile */
(function ($) {
  'use strict';

  var XenChat = {

    sessionId      : null,
    isOpen         : false,
    greeted        : false,
    sending        : false,
    noticeTimer    : null,
    cooldownTimer  : null,
    turnstileToken : '',   // populated by CF Turnstile callback
    turnstileWidget: null, // Turnstile widget ID for reset

    /* ── Init ──────────────────────────────────────────── */
    init: function () {
      this.applyAccentColor();
      this.bindEvents();
      this.startSession();
      this.initTurnstile();

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

    /* ── Cloudflare Turnstile setup ─────────────────────── */
    initTurnstile: function () {
      if (!xenAI.turnstileKey) return;

      // Global callback registered on window so Turnstile's JS can invoke it.
      window.xenAITurnstileCallback = function (token) {
        XenChat.turnstileToken = token;
      };

      // Render the invisible widget once the Turnstile script has loaded.
      var checkTurnstile = setInterval(function () {
        if (typeof turnstile !== 'undefined' && $('#xen-ai-turnstile').length) {
          clearInterval(checkTurnstile);
          XenChat.turnstileWidget = turnstile.render('#xen-ai-turnstile', {
            sitekey  : xenAI.turnstileKey,
            callback : window.xenAITurnstileCallback,
            size     : 'invisible',
          });
        }
      }, 200);
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
          // Pro feature: show page-contextual greeting instead of static one
          if (res.data.pro_greeting) {
            XenChat.appendMessage('assistant', res.data.pro_greeting);
          }
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

      // Auto-resize textarea + live char counter
      $('#xen-ai-input').on('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        XenChat.updateCharCount(this.value.length);
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

    /* ── Character counter ──────────────────────────────── */
    updateCharCount: function (len) {
      var max     = xenAI.maxChars || 2000;
      var $counter = $('#xen-ai-char-count');
      if (!$counter.length) {
        $counter = $('<span id="xen-ai-char-count" style="font-size:11px;color:#9ca3af;align-self:center;margin-left:4px;flex-shrink:0;"></span>');
        $('#xen-ai-input').after($counter);
      }
      var remaining = max - len;
      $counter.text(remaining + ' left');
      $counter.css('color', remaining < 200 ? '#ef4444' : remaining < 500 ? '#f59e0b' : '#9ca3af');
    },

    /* ── Cooldown (rate-limit) UX ───────────────────────── */
    startCooldown: function (seconds) {
      var self      = this;
      var remaining = seconds || 60;
      var $btn      = $('#xen-ai-send');
      var $input    = $('#xen-ai-input');

      $input.prop('disabled', true);
      clearInterval(this.cooldownTimer);

      var tick = function () {
        $btn.text('Wait ' + remaining + 's');
        if (remaining <= 0) {
          clearInterval(self.cooldownTimer);
          self.cooldownTimer = null;
          $input.prop('disabled', false);
          $btn.prop('disabled', false).html(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" aria-hidden="true"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>'
          );
          $input.focus();
          self.appendMessage('assistant', "You're all set! What else would you like to know?");
        }
        remaining--;
      };

      tick();
      $btn.prop('disabled', true);
      this.cooldownTimer = setInterval(tick, 1000);
    },

    /* ── Send a message ─────────────────────────────────── */
    sendMessage: function () {
      if (this.sending || this.cooldownTimer) return;

      var message = $.trim($('#xen-ai-input').val());
      if (!message) return;

      // Client-side character cap (mirrors server-side limit)
      var maxChars = xenAI.maxChars || 2000;
      if (message.length > maxChars) {
        this.appendMessage('assistant', 'Your message is too long. Please keep it under ' + maxChars + ' characters.');
        return;
      }

      if (!this.sessionId) {
        this.appendMessage('assistant', 'Session not ready. Please refresh the page.');
        return;
      }

      // Clear input and reset char counter
      $('#xen-ai-input').val('').css('height', 'auto');
      this.updateCharCount(0);
      this.appendMessage('user', message);
      this.showTyping();

      this.sending = true;
      $('#xen-ai-send').prop('disabled', true);

      var self     = this;
      var postData = {
        action                : 'xen_ai_chat',
        nonce                 : xenAI.nonce,
        message               : message,
        session_id            : self.sessionId,
        xen_hp                : $('#xen-ai-hp').val(), // honeypot (must stay empty)
        cf_turnstile_response : self.turnstileToken,
      };

      $.post(xenAI.ajaxUrl, postData)
      .done(function (res) {
        self.hideTyping();
        if (res.success) {
          self.appendMessage('assistant', res.data.reply);

          // Reset Turnstile token after a successful call so the next
          // message gets a fresh token (invisible widgets re-execute automatically).
          if (xenAI.turnstileKey && typeof turnstile !== 'undefined' && self.turnstileWidget !== null) {
            self.turnstileToken = '';
            turnstile.reset(self.turnstileWidget);
          }
        } else if (res.data && res.data.rate_limited) {
          // Show a countdown instead of a raw error string
          self.appendMessage('assistant', res.data.message || 'Please wait a moment before sending more messages.');
          self.startCooldown(res.data.cooldown || 60);
          return; // skip the .always re-enable — cooldown handles it
        } else {
          self.appendMessage('assistant', (res.data && res.data.message) || 'Sorry, something went wrong. Please try again.');
        }
      })
      .fail(function () {
        self.hideTyping();
        self.appendMessage('assistant', 'Sorry, I could not reach the server. Please check your connection and try again.');
      })
      .always(function () {
        // Only re-enable if no cooldown is running
        if (!self.cooldownTimer) {
          self.sending = false;
          $('#xen-ai-send').prop('disabled', false);
          $('#xen-ai-input').focus();
        }
        self.sending = false;
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
          // Pro feature: show page-contextual greeting instead of static one
          if (res.data.pro_greeting) {
            XenChat.appendMessage('bot', res.data.pro_greeting);
          }
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
