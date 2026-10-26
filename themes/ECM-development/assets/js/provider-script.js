/* =============================================
   ECM Provider Script — provider-script.js
   Used by: all provider dashboard pages
   ============================================= */

/* ── TOAST NOTIFICATION ─────────────────────────────── */
(function () {
  const container = document.createElement('div');
  container.id = 'toast-container';
  document.body.appendChild(container);
})();

function showToast(message, type) {
  type = type || 'success';
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = 'toast toast--' + type;
  const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
  toast.innerHTML = '<span>' + icon + '</span> ' + message;
  container.appendChild(toast);
  setTimeout(function () {
    toast.style.animation = 'fadeOut 0.3s ease forwards';
    setTimeout(function () { toast.remove(); }, 300);
  }, 3000);
}

/* ── SCROLL HANDLER (topbar shadow) ─────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  var topbar = document.querySelector('.prov-topbar');
  if (topbar) {
    window.addEventListener('scroll', function () {
      topbar.style.boxShadow = window.scrollY > 10
        ? '0 2px 14px rgba(0,0,0,0.25)'
        : 'none';
    });
  }

  /* WC/Subscriptions render the status as plain text directly inside the
     <td>, with no inner element to style as a pill without also stretching
     it to the row's full height. Wrap the text so the pill can size to it
     while the <td> itself stays a normal, vertically-centered table cell. */
  document.querySelectorAll(
    '.dash-main__body .pd-card td.subscription-status, ' +
    '.dash-main__body .pd-card td.woocommerce-subscription-status'
  ).forEach(function (td) {
    var text = td.textContent.trim();
    td.textContent = '';
    var pill = document.createElement('span');
    pill.className = 'ecm-status-pill';
    pill.textContent = text;
    td.appendChild(pill);
  });
});

/* ── REQUIRED WP-EDITOR FIELDS (e.g. Description) ─────
   WPJM marks empty required wp-editor fields invalid via
   setCustomValidity(), but their <textarea> stays display:none while
   TinyMCE is active. A browser can't show its validation bubble on a
   hidden field, so on submit it forces the textarea visible without
   hiding the TinyMCE iframe/toolbar above it - both render at once.
   Check these fields ourselves before the browser's native validation
   ever runs, so that reveal never happens; show our own inline message
   instead. */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.job-manager-form').forEach(function (form) {
    var submitBtn = form.querySelector('input[type="submit"], button[type="submit"]');
    if (!submitBtn) return;

    submitBtn.addEventListener('click', function (e) {
      var firstInvalid = null;

      form.querySelectorAll('.wp-editor-wrap').forEach(function (wrap) {
        if (firstInvalid) return;
        if (!wrap.closest('.required-field')) return;
        var textarea = wrap.querySelector('textarea');
        if (!textarea) return;

        var ed = window.tinymce && tinymce.get(textarea.id);
        var content = ed ? ed.getContent({ format: 'text' }).trim() : textarea.value.trim();
        if (content) return;

        firstInvalid = { wrap: wrap, textarea: textarea };
      });

      if (!firstInvalid) return;

      e.preventDefault();
      e.stopImmediatePropagation();

      var existing = firstInvalid.wrap.parentNode.querySelector('.ecm-field-error');
      if (!existing) {
        var msg = document.createElement('div');
        msg.className = 'ecm-field-error';
        msg.textContent = 'This field is required.';
        firstInvalid.wrap.insertAdjacentElement('afterend', msg);
      }
      firstInvalid.wrap.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, true);
  });
});

/* ── COUNTDOWN TIMER ─────────────────────────────────── */
/**
 * startCountdown(selector, totalSeconds)
 * Looks for .h, .m, .s spans inside the matched element and ticks down.
 * @param {string} selector  CSS selector for the countdown container
 * @param {number} totalSeconds  Starting value in seconds
 */
function startCountdown(selector, totalSeconds) {
  var el = document.querySelector(selector);
  if (!el) return;
  var remaining = totalSeconds;

  function pad(n) { return String(n).padStart(2, '0'); }

  function tick() {
    var h = Math.floor(remaining / 3600);
    var m = Math.floor((remaining % 3600) / 60);
    var s = remaining % 60;

    var hEl = el.querySelector('.h');
    var mEl = el.querySelector('.m');
    var sEl = el.querySelector('.s');

    if (hEl) hEl.textContent = pad(h);
    if (mEl) mEl.textContent = pad(m);
    if (sEl) sEl.textContent = pad(s);

    if (remaining > 0) {
      remaining--;
      setTimeout(tick, 1000);
    } else {
      el.textContent = 'Window closed';
    }
  }

  tick();
}

/* ── LEAD INBOX — "Request Contact Details" (unmask) ────────────────── */
/**
 * Two-step flow per doc 5.1: clicking the button shows the permitted-use
 * text and a Confirm/Cancel pair FIRST; the actual REST call only fires
 * after that confirm click.
 */
document.addEventListener('DOMContentLoaded', function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.pd-unmask-btn');
    if (btn) {
      var card = document.getElementById(btn.dataset.card);
      if (!card) return;
      btn.style.display = 'none';
      var confirmBlock = card.querySelector('.pd-unmask-confirm');
      if (confirmBlock) confirmBlock.style.display = '';
      return;
    }

    var cancelBtn = e.target.closest('.pd-unmask-cancel-btn');
    if (cancelBtn) {
      var cCard = cancelBtn.closest('.pd-lead-card');
      if (!cCard) return;
      cCard.querySelector('.pd-unmask-confirm').style.display = 'none';
      var origBtn = cCard.querySelector('.pd-unmask-btn');
      if (origBtn) origBtn.style.display = '';
      return;
    }

    var confirmBtn = e.target.closest('.pd-unmask-confirm-btn');
    if (confirmBtn) {
      var fCard = confirmBtn.closest('.pd-lead-card');
      if (!fCard) return;
      var requestBtn = fCard.querySelector('.pd-unmask-btn');
      var requestId = requestBtn ? requestBtn.dataset.requestId : null;
      if (!requestId || !window.ECM_PD) return;

      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Requesting…';

      fetch(ECM_PD.root + '/unmask', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-WP-Nonce': ECM_PD.nonce
        },
        body: 'request_id=' + encodeURIComponent(requestId)
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok) {
            var msg = (result.data && result.data.message) ? result.data.message : 'Unable to unmask this request right now.';
            showToast(msg, 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm — Request Contact Details';
            return;
          }
          var c = result.data;
          var body = document.createElement('div');
          body.className = 'pd-contact-block';
          var html = '<p style="margin:0 0 4px;"><strong>' + escapeHtml((c.first_name + ' ' + c.last_name).trim()) + '</strong></p>';
          if (c.email) html += '<p style="margin:0 0 4px;">✉️ <a href="mailto:' + escapeHtml(c.email) + '">' + escapeHtml(c.email) + '</a></p>';
          if (c.phone) html += '<p style="margin:0 0 4px;">📞 <a href="tel:' + escapeHtml(c.phone) + '">' + escapeHtml(c.phone) + '</a></p>';
          if (c.description) html += '<p style="margin:8px 0 0;color:var(--gray-700,#374151);">' + escapeHtml(c.description) + '</p>';
          body.innerHTML = html;

          var confirmBlock = fCard.querySelector('.pd-unmask-confirm');
          confirmBlock.parentNode.insertBefore(body, confirmBlock);
          var note = document.createElement('p');
          note.className = 'pd-permitted-use-note';
          note.textContent = "Use these details only to respond to this family's request. Do not add them to marketing lists, sell them or share them. See the Terms.";
          confirmBlock.parentNode.insertBefore(note, confirmBlock);
          confirmBlock.remove();

          var badge = fCard.querySelector('.pd-badge');
          if (badge) {
            badge.className = 'pd-badge pd-badge--green';
            badge.textContent = 'Contact revealed';
          }
          showToast('Contact details revealed.', 'success');
        })
        .catch(function () {
          showToast('Network error. Please try again.', 'error');
          confirmBtn.disabled = false;
          confirmBtn.textContent = 'Confirm — Request Contact Details';
        });
    }
  });
});

function escapeHtml(str) {
  var div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
}
