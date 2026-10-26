(function () {
  'use strict';

  var CARE_TYPES = [
    { key: 'home-care',        label: 'Home Care' },
    { key: 'assisted-living',  label: 'Assisted Living' },
    { key: 'memory-care',      label: 'Memory Care' },
    { key: 'elder-law',        label: 'Elder Law' },
    { key: 'care-management',  label: 'Care Management' },
    { key: 'hospice',          label: 'Hospice' },
    { key: 'grief-counselors', label: 'Grief Counselors' }
  ];
  if (window.ECM_INTAKE && Array.isArray(window.ECM_INTAKE.careTypes) && window.ECM_INTAKE.careTypes.length) {
    CARE_TYPES = window.ECM_INTAKE.careTypes.filter(function (c) {
      return c && c.key && c.label;
    }).map(function (c) {
      return {
        key: c.key,
        label: decodeEntities(c.label)
      };
    });
  }
  CARE_TYPES.forEach(function (careType) {
    careType.icon = careTypeIcon(careType.key, careType.label);
  });
  window.ECM_CARE_ICON = careTypeIcon;   // reused by the Find Care category dialog

  var URGENCY_OPTIONS = [
    { key: 'right-away',    label: 'Right away' },
    { key: 'within-month',  label: 'Within a month' },
    { key: 'just-planning', label: 'Just planning' }
  ];

  var RELATIONSHIP_OPTIONS = [
    { key: 'parent',        label: 'Parent' },
    { key: 'spouse',        label: 'Spouse' },
    { key: 'grandparent',   label: 'Grandparent' },
    { key: 'myself',        label: 'Myself' },
    { key: 'someone-else',  label: 'Other' }
  ];

  var CROSS_SELL = {
    'home-care':        ['care-management', 'elder-law'],
    'assisted-living':  ['care-management', 'elder-law'],
    'memory-care':      ['home-care', 'hospice'],
    'elder-law':        ['home-care', 'care-management'],
    'care-management':  ['home-care', 'elder-law'],
    'hospice':          ['grief-counselors', 'care-management'],
    'grief-counselors': ['hospice', 'care-management']
  };

  var FORM_TOTAL = 7;

  // ── Shared utilities ────────────────────────────────────────────────────────

  function currentLocation() {
    return (window.ECM && window.ECM.getLocation && window.ECM.getLocation()) || { city: 'your area', state: '' };
  }

  function careTypeLabel(key) {
    var t = CARE_TYPES.find(function (c) { return c.key === key; });
    return t ? t.label : key;
  }

  function decodeEntities(s) {
    var textarea = document.createElement('textarea');
    textarea.innerHTML = String(s || '');
    return textarea.value;
  }

  function svgIcon(content) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
      + content + '</svg>';
  }

  function careTypeIcon(key, label) {
    var category = (key + ' ' + label).toLowerCase().replace(/&amp;/g, '&');

    if (/financial|finance|insurance/.test(category)) {
      return svgIcon('<path d="M3 20h18"/><path d="M5 17V9h3v8"/><path d="M10.5 17V5h3v12"/><path d="M16 17v-6h3v6"/><path d="m4 7 5-4 4 3 6-4"/>');
    }
    if (/health|medical|therapy|nursing|rehab/.test(category)) {
      return svgIcon('<path d="M9 3h6v5h5v6h-5v5H9v-5H4V8h5z"/>');
    }
    if (/litigation|advocacy|advocate/.test(category)) {
      return svgIcon('<path d="m14 6 4 4"/><path d="m5 15 4 4"/><path d="m7 17 9-9"/><path d="m12 5 3-3 5 5-3 3"/><path d="m2 20 3-3"/><path d="M13 21h8"/>');
    }
    if (/legal|elder-law|estate/.test(category)) {
      return svgIcon('<path d="M12 3v18"/><path d="M5 6h14"/><path d="m5 6-3 6h6L5 6Z"/><path d="m19 6-3 6h6l-3-6Z"/><path d="M8 21h8"/>');
    }
    if (/memory|dementia|special-needs/.test(category)) {
      return svgIcon('<path d="M9.5 4.5A3 3 0 0 0 5 7v1a3 3 0 0 0-1 5.2A3 3 0 0 0 7 18h2.5"/><path d="M14.5 4.5A3 3 0 0 1 19 7v1a3 3 0 0 1 1 5.2A3 3 0 0 1 17 18h-2.5"/><path d="M12 3v18"/><path d="M8 9h4"/><path d="M12 14h4"/>');
    }
    if (/move|organize|property|relocation/.test(category)) {
      return svgIcon('<path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="m3 7 9 4 9-4"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>');
    }
    if (/senior-living|communities|assisted-living|facility/.test(category)) {
      return svgIcon('<path d="M4 21V6l8-3 8 3v15"/><path d="M9 21v-4h6v4"/><path d="M8 8h.01"/><path d="M12 8h.01"/><path d="M16 8h.01"/><path d="M8 12h.01"/><path d="M12 12h.01"/><path d="M16 12h.01"/>');
    }
    if (/in-home|home-care|daily-care/.test(category)) {
      return svgIcon('<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M12 17s-4-2.2-4-5a2.2 2.2 0 0 1 4-1.3A2.2 2.2 0 0 1 16 12c0 2.8-4 5-4 5Z"/>');
    }
    if (/care-management|coordination/.test(category)) {
      return svgIcon('<path d="M9 5h6"/><path d="M9 3h6v4H9z"/><path d="M7 5H5v16h14V5h-2"/><path d="m8 13 2 2 5-5"/>');
    }
    if (/hospice|grief|bereavement/.test(category)) {
      return svgIcon('<path d="M12 21S4 16.5 4 10a4 4 0 0 1 7-2.7L12 8.5l1-1.2A4 4 0 0 1 20 10c0 6.5-8 11-8 11Z"/>');
    }
    return svgIcon('<circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/>');
  }

  function escHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
  }

  function isValidPhone(value) {
    var digits = value.replace(/\D/g, '');
    return /^[0-9+().\s-]+$/.test(value) && digits.length >= 7 && digits.length <= 15;
  }

  function phoneValueIsAllowed(value) {
    return value === '' || isValidPhone(value);
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/.test(value);
  }

  function reportEmailValidity(input) {
    var value = input.value.trim();
    var valid = input.checkValidity() && isValidEmail(value);
    input.setCustomValidity(valid ? '' : 'Enter a valid email address, including a domain extension.');
    if (!valid) {
      input.reportValidity();
    }
    return valid;
  }

  function leadBody() {
    var loc    = currentLocation();
    var picked = window.ECM_PICKS ? window.ECM_PICKS.get() : { providers: [] };
    return {
      careType:          fState.careType,
      relationship:      fState.relationship || '',
      firstName:         fState.firstName,
      lastName:          fState.lastName,
      city:              loc.city,
      state:             loc.state || '',
      urgency:           fState.urgency,
      email:             fState.email,
      phone:             fState.phone || '',
      description:       fState.notes || fState.description,
      pickedProviderIds: picked.providers.map(function (p) { return p.id; }),
      expeditedOptIn:    !!fState.expeditedOptIn
    };
  }

  function postLead(path, body) {
    var apiRoot = (window.ECM_INTAKE && window.ECM_INTAKE.root) || '/wp-json/ecm/v1';
    return fetch(apiRoot + path, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(body)
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) {
        if (!r.ok) {
          var d   = data.data || {};
          var err = new Error(data.message || 'Request failed');
          err.reason       = d.reason;
          err.retryAfter   = d.retry_after;
          err.locked       = !!d.locked;
          err.unlockIn     = d.unlock_in;
          err.unlockAt     = d.unlock_at;
          err.attemptsLeft = d.attempts_left;
          err.expiresIn    = d.expires_in;
          err.resendIn     = d.resend_in;
          throw err;
        }
        return data;
      });
    });
  }

  // Email verification (items 14-16): the contact step verifies the address; the final submit only works for a verified email.
  function emailStart(email) {
    return postLead('/email/start', { email: email, category: careTypeLabel(fState.careType), city: currentLocation().city }).then(function (data) {
      fState.verifyToken = data.token;
      fState.verifyEmailSent = email;
      return data;
    });
  }

  // A verified email is remembered on this browser (signed token, checked by the server) so the same address isn't asked again.
  var REMEMBER_KEY = 'ecm_verified_email';
  function readRemembered() {
    try {
      var v = JSON.parse(window.localStorage.getItem(REMEMBER_KEY) || 'null');
      return v && v.email && v.remember ? v : null;
    } catch (e) { return null; }
  }
  function writeRemembered(email, remember) {
    try { if (remember) window.localStorage.setItem(REMEMBER_KEY, JSON.stringify({ email: email, remember: remember })); } catch (e) {}
  }
  function forgetRemembered() {
    try { window.localStorage.removeItem(REMEMBER_KEY); } catch (e) {}
  }

  function emailVerify(code) {
    return postLead('/email/verify', { token: fState.verifyToken, code: code }).then(function (data) {
      fState.emailVerified = true;
      fState.verifiedEmail = String(fState.verifyEmailSent || '').toLowerCase();
      writeRemembered(fState.verifyEmailSent, data.remember);
      return data;
    });
  }

  function emailRestore(email, remember) {
    return postLead('/email/restore', { email: email, remember: remember }).then(function (data) {
      fState.verifyToken = data.token;
      fState.verifyEmailSent = email;
      fState.emailVerified = true;
      fState.verifiedEmail = email.toLowerCase();
      return data;
    });
  }

  function emailResend() {
    return postLead('/email/resend', { token: fState.verifyToken });
  }

  function submitLead() {
    var body = leadBody();
    body.token = fState.verifyToken;
    return postLead('/lead/submit', body).then(function (data) {
      if (window.ECM_PICKS) { window.ECM_PICKS.clear(); }
      fState.verifyToken = null;
      fState.emailVerified = false;
      fState.verifiedEmail = '';
      return data;
    });
  }

  function crossSellHtml(careKey) {
    var keys = (CROSS_SELL[careKey] || []).slice(0, 2);
    var items = keys.map(function (k) { return CARE_TYPES.find(function (c) { return c.key === k; }); }).filter(Boolean);
    if (!items.length) return '';
    var loc = currentLocation();
    return '<div class="crosssell-section">'
      + '<p class="crosssell-label">You may also need…</p>'
      + '<div class="crosssell-cards">'
      + items.map(function (c) {
          return '<a href="/category/?type=' + c.key
            + '&city=' + encodeURIComponent(loc.city)
            + '&state=' + encodeURIComponent(loc.state || '')
            + '" class="crosssell-card">' + c.icon + ' ' + escHtml(c.label) + '</a>';
        }).join('')
      + '</div></div>';
  }

  // ── CHAT ────────────────────────────────────────────────────────────────────

  // undefined = not yet reached; null = skipped; string = provided
  var chatState = {
    careType: null,
    firstName: '',
    lastName: '',
    confirmedLocation: false,
    urgency: null,
    email: '',
    phone: undefined
  };

  function resetChatState() {
    chatState = {
      careType: null, firstName: '', lastName: '',
      confirmedLocation: false, urgency: null,
      email: '', phone: undefined
    };
  }

  function chatStep() {
    if (!chatState.careType)                                   return 1;
    if (!chatState.firstName)                                  return 2;
    if (!chatState.confirmedLocation)                          return 3;
    if (!chatState.urgency)                                    return 4;
    if (!chatState.email)                                      return 5;
    if (chatState.phone === undefined)                         return 6;
    return 7;
  }

  function renderChat() {
    var popup = document.getElementById('chatPopup');
    if (!popup) return;
    var badge = popup.querySelector('.chat-step-badge');
    var body  = popup.querySelector('.chat-body');
    if (!body) return;

    var step = chatStep();
    if (badge) badge.textContent = 'Step ' + step + ' of 7';
    body.innerHTML = '';

    // ── Step 1: Care type ────────────────────────────────────────────────────
    bubble(body, 'Hi! What kind of help are you looking for? Pick one so I can match you with the right local provider 💚');
    if (chatState.careType) {
      bubble(body, escHtml(careTypeLabel(chatState.careType)), true);
    } else {
      options(body, CARE_TYPES.map(function (c) { return { icon: c.icon, label: c.label, value: c.key }; }), function (v) {
        chatState.careType = v; renderChat();
      });
      scrollBottom(body); return;
    }

    // ── Step 2: Name ─────────────────────────────────────────────────────────
    bubble(body, 'Got it — <strong>' + escHtml(careTypeLabel(chatState.careType)) + "</strong>. What's your name?");
    if (chatState.firstName) {
      bubble(body, escHtml(chatState.firstName + (chatState.lastName ? ' ' + chatState.lastName : '')), true);
    } else {
      var nameWrap = el('div', 'chat-name-wrap');
      var firstInp = inp('text', 'First name', 'given-name');
      var lastInp  = inp('text', 'Last name',  'family-name');
      var nameBtn  = btn('Next →', 'chat-name-submit chat-text-submit');
      nameBtn.addEventListener('click', function () {
        var first = firstInp.value.trim();
        if (!first) { firstInp.focus(); firstInp.classList.add('shake'); setTimeout(function () { firstInp.classList.remove('shake'); }, 300); return; }
        chatState.firstName = first;
        chatState.lastName  = lastInp.value.trim();
        renderChat();
      });
      [firstInp, lastInp].forEach(function (i) {
        i.addEventListener('keydown', function (e) { if (e.key === 'Enter') nameBtn.click(); });
      });
      nameWrap.appendChild(firstInp);
      nameWrap.appendChild(lastInp);
      nameWrap.appendChild(nameBtn);
      body.appendChild(nameWrap);
      requestAnimationFrame(function () { firstInp.focus(); });
      scrollBottom(body); return;
    }

    // ── Step 3: Location ─────────────────────────────────────────────────────
    var loc = currentLocation();
    var locStr = escHtml(loc.city) + (loc.state ? ', ' + escHtml(loc.state) : '');
    bubble(body, 'Hi ' + escHtml(chatState.firstName) + "! I see you're in <strong>" + locStr + '</strong>. Is that where you need care?');
    if (chatState.confirmedLocation) {
      bubble(body, "Yes, that's right", true);
    } else {
      options(body, [
        { label: "Yes, that's right", value: 'yes' },
        { label: 'No, change location',   value: 'change' }
      ], function (v) {
        if (v === 'change') {
          var lm = document.getElementById('locationModal');
          if (lm) lm.classList.add('active');
          return;
        }
        chatState.confirmedLocation = true; renderChat();
      }, true);
      scrollBottom(body); return;
    }

    // ── Step 4: Urgency ──────────────────────────────────────────────────────
    bubble(body, 'How soon do you need help?');
    if (chatState.urgency) {
      var uLabel = (URGENCY_OPTIONS.find(function (u) { return u.key === chatState.urgency; }) || {}).label || '';
      bubble(body, escHtml(uLabel), true);
    } else {
      options(body, URGENCY_OPTIONS.map(function (u) { return { label: u.label, value: u.key }; }), function (v) {
        chatState.urgency = v; renderChat();
      }, true);
      scrollBottom(body); return;
    }

    // ── Step 5: Email ────────────────────────────────────────────────────────
    bubble(body, "To connect you with providers, what's your email?");
    if (chatState.email) {
      bubble(body, escHtml(chatState.email), true);
    } else {
      var emailWrap = el('div', 'chat-text-input-wrap');
      var emailRow  = el('div', 'chat-text-input-row');
      var emailInp  = inp('email', 'your@email.com', 'email');
      emailInp.required = true;
      var emailBtn  = btn('Continue →', 'chat-text-submit');
      emailBtn.addEventListener('click', function () {
        var val = emailInp.value.trim();
        if (!reportEmailValidity(emailInp)) { return; }
        chatState.email = val;
        renderChat();
      });
      emailInp.addEventListener('input', function () { emailInp.setCustomValidity(''); });
      emailInp.addEventListener('keydown', function (e) { if (e.key === 'Enter') emailBtn.click(); });
      emailRow.appendChild(emailInp);
      emailRow.appendChild(emailBtn);
      emailWrap.appendChild(emailRow);
      body.appendChild(emailWrap);
      requestAnimationFrame(function () { emailInp.focus(); });
      scrollBottom(body); return;
    }

    // ── Step 6: Phone (optional) ─────────────────────────────────────────────
    bubble(body, 'Optional: add your phone number so providers can contact you by text or phone.');
    if (chatState.phone !== undefined) {
      if (chatState.phone) {
        bubble(body, escHtml(chatState.phone), true);
      } else {
        bubble(body, 'Skipped', true);
      }
    } else {
      var phoneWrap = el('div', 'chat-text-input-wrap');
      var phoneRow  = el('div', 'chat-text-input-row');
      var phoneInp  = inp('tel', '(555) 000-0000', 'tel');
      phoneInp.inputMode = 'tel';
      phoneInp.maxLength = 20;
      var phoneBtn  = btn('Continue →', 'chat-text-submit');
      phoneBtn.addEventListener('click', function () {
        var val = phoneInp.value.trim();
        if (!phoneValueIsAllowed(val)) { phoneInp.setCustomValidity('Enter a valid phone number.'); phoneInp.reportValidity(); phoneInp.setCustomValidity(''); return; }
        chatState.phone = val || null;
        renderChat();
      });
      phoneRow.appendChild(phoneInp);
      phoneRow.appendChild(phoneBtn);
      phoneWrap.appendChild(phoneRow);
      body.appendChild(phoneWrap);
      requestAnimationFrame(function () { phoneInp.focus(); });
      scrollBottom(body); return;
    }

    // ── Step 7: Success ──────────────────────────────────────────────────────
    bubble(body, 'All set, ' + escHtml(chatState.firstName) + "! We're connecting you with providers now.");
    appendChatSuccess(body);
    scrollBottom(body);
  }

  function appendChatSuccess(container) {
    var loc = currentLocation();
    var urgLabel = (URGENCY_OPTIONS.find(function (u) { return u.key === chatState.urgency; }) || {}).label || '';
    var card = el('div', 'intake-complete');
    card.innerHTML = ''
      + '<div class="intake-complete-icon">✓</div>'
      + '<h4>Request submitted!</h4>'
      + '<div class="intake-summary">'
      + '<div><strong>Need:</strong> ' + escHtml(careTypeLabel(chatState.careType)) + '</div>'
      + '<div><strong>Location:</strong> ' + escHtml(loc.city) + (loc.state ? ', ' + escHtml(loc.state) : '') + '</div>'
      + '<div><strong>Urgency:</strong> ' + escHtml(urgLabel) + '</div>'
      + '<div><strong>Contact:</strong> ' + escHtml(chatState.email) + (chatState.phone ? ' · ' + escHtml(chatState.phone) : '') + '</div>'
      + '</div>'
      + '<p style="margin:0.75rem 0 0.5rem;font-size:0.85rem;color:var(--muted);">Providers will reach out within 24 hours.</p>'
      + crossSellHtml(chatState.careType)
      + '<button class="form-submit" type="button" data-chat-restart style="margin-top:14px;">New request</button>';
    container.appendChild(card);
    card.querySelector('[data-chat-restart]').addEventListener('click', function () {
      resetChatState(); renderChat();
    });
  }

  // chat DOM helpers
  function bubble(container, html, isUser) {
    var d = el('div', 'chat-bubble' + (isUser ? ' chat-bubble-user' : ''));
    d.innerHTML = html;
    container.appendChild(d);
    return d;
  }
  function options(container, list, onPick, row) {
    var wrap = el('div', row ? 'chat-option-row' : 'chat-options');
    list.forEach(function (o) {
      var b = el('button', 'chat-option');
      b.type = 'button';
      if (o.icon) {
        b.classList.add('has-icon');
        b.innerHTML = '<span class="chat-option-icon">' + o.icon + '</span><span>' + escHtml(o.label) + '</span>';
      } else {
        b.textContent = o.label;
      }
      b.addEventListener('click', function () { onPick(o.value); });
      wrap.appendChild(b);
    });
    container.appendChild(wrap);
  }
  function scrollBottom(body) {
    requestAnimationFrame(function () { body.scrollTop = body.scrollHeight; });
  }

  // ── FORM MODAL ───────────────────────────────────────────────────────────────

  var formStep = 1;
  var formRedirectUrl = '';
  var fState = {
    careType: null,
    relationship: null,
    confirmedLocation: false,
    urgency: null,
    notes: '',
    firstName: '', lastName: '',
    email: '', phone: '',
    description: '',
    expeditedOptIn: false,
    verifyToken: null,
    emailVerified: false,
    verifiedEmail: '',
    verifyEmailSent: ''
  };
  var verifyTick = null;

  function resetFormState() {
    fState = {
      careType: null,
      relationship: null,
      confirmedLocation: false,
      urgency: null,
      notes: '',
      firstName: '', lastName: '',
      email: '', phone: '',
      description: '',
      expeditedOptIn: false,
      verifyToken: null,
      emailVerified: false,
      verifiedEmail: '',
      verifyEmailSent: ''
    };
    formStep = 1;
  }

  function renderFormStep(modal) {
    var content = modal.querySelector('#intakeStepContent');
    if (!content) return;
    clearInterval(verifyTick);

    // progress bar
    var fill  = modal.querySelector('#stepBarFill');
    var label = modal.querySelector('#stepLabel');
    if (fill)  fill.style.width = Math.round((formStep / FORM_TOTAL) * 100) + '%';
    if (label) label.textContent = 'Step ' + formStep + ' of ' + FORM_TOTAL;

    content.innerHTML = '';
    var loc = currentLocation();

    if (formStep > 1 && fState.careType) {
      var catBar = el('div', 'intake-cat-bar');
      var catText = el('span', 'intake-cat-bar-text');
      catText.appendChild(document.createTextNode('Category: '));
      var catName = document.createElement('strong');
      catName.textContent = careTypeLabel(fState.careType);
      catText.appendChild(catName);
      var catChange = btn('Change', 'intake-cat-change');
      catChange.addEventListener('click', function () { formStep = 1; renderFormStep(modal); });
      catBar.appendChild(catText);
      catBar.appendChild(catChange);
      content.appendChild(catBar);
    }

    // ── Step 1: Care type (prefilled from search category / picks) ─────────
    if (formStep === 1) {
      var t1 = el('h4', 'step-title'); t1.textContent = 'What kind of help do you need?';
      t1.id = 'intakeCareTypeTitle';
      content.appendChild(t1);
      var grid = el('div', 'care-type-grid');
      CARE_TYPES.forEach(function (c) {
        var b = el('button', 'care-type-btn' + (fState.careType === c.key ? ' selected' : ''));
        b.type = 'button';
        b.innerHTML = '<span class="care-type-icon">' + c.icon + '</span><span>' + escHtml(c.label) + '</span>';
        b.addEventListener('click', function () {
          fState.careType = c.key; saveCategory(c.key); formStep = 2; renderFormStep(modal);
        });
        grid.appendChild(b);
      });
      content.appendChild(grid);
      if (fState.careType) {
        var contNav = el('div', 'step-nav');
        var cont = btn('Continue →', 'form-submit step-next');
        cont.addEventListener('click', function () { formStep = 2; renderFormStep(modal); });
        contNav.appendChild(cont);
        content.appendChild(contNav);
      }

    // ── Step 2: Who needs care? ────────────────────────────────────────────
    } else if (formStep === 2) {
      addStepTitle(content, 'Who needs care?');
      var relChips = el('div', 'urgency-chips');
      RELATIONSHIP_OPTIONS.forEach(function (r) {
        var b = el('button', 'urgency-chip' + (fState.relationship === r.key ? ' selected' : ''));
        b.type = 'button'; b.textContent = r.label;
        b.setAttribute('data-value', r.key);
        b.addEventListener('click', function () {
          fState.relationship = r.key;
          relChips.querySelectorAll('.urgency-chip').forEach(function (c) { c.classList.remove('selected'); });
          b.classList.add('selected');
        });
        relChips.appendChild(b);
      });
      content.appendChild(relChips);
      var relError2 = el('p', 'form-field-error'); relError2.textContent = 'Please select one option.'; relError2.hidden = true;
      content.appendChild(relError2);
      addStepNav(content, modal, function () {
        if (!fState.relationship) { relError2.hidden = false; return; }
        relError2.hidden = true;
        formStep = 3; renderFormStep(modal);
      });

    // ── Step 3: Location (prefilled from search) ───────────────────────────
    } else if (formStep === 3) {
      addStepTitle(content, 'Where do they live?');
      var locBox = el('div', 'location-confirm-box');
      locBox.innerHTML = '<span class="loc-pin">📍</span>'
        + '<span class="loc-text">' + escHtml(loc.city) + (loc.state ? ', ' + escHtml(loc.state) : '') + '</span>'
        + '<button type="button" class="location-change-link" data-open-location-modal>Change</button>';
      content.appendChild(locBox);
      var hint3 = el('p', 'step-hint');
      hint3.textContent = 'Based on your search. Change it if the care location is different.';
      content.appendChild(hint3);
      addStepNav(content, modal, function () { fState.confirmedLocation = true; formStep = 4; renderFormStep(modal); });

    // ── Step 4: Urgency ────────────────────────────────────────────────────
    } else if (formStep === 4) {
      addStepTitle(content, 'How soon do you need help?');
      var chips = el('div', 'urgency-chips');
      URGENCY_OPTIONS.forEach(function (u) {
        var b = el('button', 'urgency-chip' + (fState.urgency === u.key ? ' selected' : ''));
        b.type = 'button'; b.textContent = u.label;
        b.setAttribute('data-value', u.key);
        b.addEventListener('click', function () {
          fState.urgency = u.key;
          chips.querySelectorAll('.urgency-chip').forEach(function (c) { c.classList.remove('selected'); });
          b.classList.add('selected');
        });
        chips.appendChild(b);
      });
      content.appendChild(chips);
      var urgError4 = el('p', 'form-field-error'); urgError4.textContent = 'Please select one option.'; urgError4.hidden = true;
      content.appendChild(urgError4);
      addStepNav(content, modal, function () {
        if (!fState.urgency) { urgError4.hidden = false; return; }
        urgError4.hidden = true;
        formStep = 5; renderFormStep(modal);
      });

    // ── Step 5: Optional notes ────────────────────────────────────────────
    } else if (formStep === 5) {
      addStepTitle(content, 'Anything providers should know?');
      var hint5 = el('p', 'step-hint');
      hint5.textContent = 'Optional. Your notes stay hidden until a provider requests your details.';
      content.appendChild(hint5);
      var ta5 = document.createElement('textarea');
      ta5.className = 'form-input'; ta5.rows = 4;
      ta5.placeholder = 'E.g. dementia patient, need overnight care, specific budget concerns…';
      ta5.setAttribute('aria-label', 'Anything providers should know? (optional)');
      ta5.value = fState.notes;
      content.appendChild(ta5);
      addStepNav(content, modal, function () {
        fState.notes = ta5.value.trim();
        fState.description = fState.notes; // keep API field in sync
        formStep = 6; renderFormStep(modal);
      });
      setTimeout(function () { ta5.focus(); }, 30);

    // ── Step 6: Contact details (name, email, phone) ───────────────────────
    } else if (formStep === 6) {
      addStepTitle(content, 'How can providers reach you?');
      var hint6 = el('p', 'step-hint');
      hint6.textContent = 'Your contact details stay hidden until a provider asks to help.';
      content.appendChild(hint6);

      var nameRow = el('div', 'name-field-row');
      var fInp = inp('text', 'First name', 'given-name'); fInp.id = 'sf-first'; fInp.value = fState.firstName;
      var lInp = inp('text', 'Last name',  'family-name'); lInp.id = 'sf-last';  lInp.value = fState.lastName;
      nameRow.appendChild(fInp);
      nameRow.appendChild(lInp);
      content.appendChild(nameRow);

      var nameError6 = el('p', 'form-field-error'); nameError6.textContent = 'Please enter your first name.'; nameError6.hidden = true;
      content.appendChild(nameError6);

      var emailRow6 = el('div', 'intake-email-row');
      emailRow6.style.cssText = 'display:flex;gap:8px;align-items:stretch;';
      var emailWrap6 = el('div', 'intake-email-wrap');
      emailWrap6.style.cssText = 'position:relative;flex:1;';
      var emailInp6 = inp('email', 'your@email.com', 'email');
      emailInp6.required = true; emailInp6.value = fState.email;
      emailInp6.style.width = '100%';
      var emailTick = el('span', 'intake-email-tick');
      emailTick.textContent = '✓';
      emailTick.hidden = true;
      emailTick.setAttribute('role', 'img');
      emailTick.setAttribute('aria-label', 'Email verified');
      emailTick.style.cssText = 'position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#166534;font-weight:700;font-size:1.15rem;line-height:1;';
      emailWrap6.appendChild(emailInp6);
      emailWrap6.appendChild(emailTick);
      var verifyEmailBtn = btn('Verify', 'form-submit intake-verify-btn');
      emailRow6.appendChild(emailWrap6);
      emailRow6.appendChild(verifyEmailBtn);
      content.appendChild(emailRow6);

      var emailError6 = el('p', 'form-field-error'); emailError6.textContent = 'Please enter a valid email address.'; emailError6.hidden = true;
      content.appendChild(emailError6);

      var emailStateNote = el('p', 'intake-email-state');
      emailStateNote.setAttribute('role', 'status');
      content.appendChild(emailStateNote);

      var priorityRow = el('div', 'intake-priority-row');
      var priorityNote = el('p', 'step-hint intake-email-priority');
      priorityNote.textContent = 'Verified email addresses will get priority response.';
      var skipBtn = btn('Skip', 'intake-link-btn intake-skip-btn');
      skipBtn.setAttribute('aria-label', 'Skip email verification and continue');
      priorityRow.appendChild(priorityNote);
      priorityRow.appendChild(skipBtn);
      content.appendChild(priorityRow);

      // Wording from section 10.3 of the spec ("Code entry screen label" / "Code entry help text"); server messages come from ecm_lead_verification_messages().
      var vStatus = el('p', 'step-hint');
      vStatus.setAttribute('role', 'status');
      content.appendChild(vStatus);

      var codeBox = el('div', 'intake-code-box');
      codeBox.hidden = true;
      var codeRow = el('div', 'intake-code-row');
      codeRow.style.cssText = 'display:flex;gap:8px;align-items:center;';
      var codeInp = inp('text', '6-digit code', 'one-time-code');
      codeInp.inputMode = 'numeric'; codeInp.maxLength = 6;
      // Match the email and phone boxes: type=text inputs otherwise pick up the taller, tinted name-field style.
      codeInp.style.cssText = 'flex:1;margin:0;padding:9px 12px;font-size:0.9rem;background:#fff;border-radius:8px;';
      codeInp.setAttribute('aria-label', '6-digit verification code');
      var confirmBtn = btn('Confirm code', 'form-submit intake-verify-btn');
      codeRow.appendChild(codeInp);
      codeRow.appendChild(confirmBtn);
      var expiryLine = el('p', 'step-hint');
      var helpLine = el('p', 'step-hint');
      helpLine.textContent = "Didn't get it? Check your spam folder, or send a new code.";
      var codeMsg = el('p', 'intake-submit-error');
      codeMsg.setAttribute('role', 'alert');
      codeMsg.style.cssText = 'color:#b91c1c;font-size:0.85rem;margin-top:6px;';
      var resendBtn = btn('Send a new code', 'intake-link-btn');
      codeBox.appendChild(codeRow);
      codeBox.appendChild(expiryLine);
      codeBox.appendChild(helpLine);
      codeBox.appendChild(codeMsg);
      codeBox.appendChild(resendBtn);
      content.appendChild(codeBox);

      var expiresAt = 0, resendAt = 0, lockedUntil = 0, lockedAtLabel = '';

      function fmtClock(sec) {
        sec = Math.max(0, sec);
        var m = Math.floor(sec / 60), s = sec % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
      }
      function setControls(disabled) {
        codeInp.disabled = disabled; confirmBtn.disabled = disabled; verifyEmailBtn.disabled = disabled;
      }
      function applyTimers(src) {
        var now = Date.now();
        if (src.expiresIn != null) expiresAt = now + src.expiresIn * 1000;
        if (src.resendIn != null)  resendAt  = now + src.resendIn * 1000;
        if (src.retryAfter != null) resendAt = now + src.retryAfter * 1000;
        if (src.locked && src.unlockIn) {
          lockedUntil   = now + src.unlockIn * 1000;
          lockedAtLabel = src.unlockAt || '';
        }
      }
      function renderTimers() {
        var now = Date.now();
        if (lockedUntil > now) {
          codeMsg.textContent = 'Too many attempts. Please try again in ' + fmtClock(Math.ceil((lockedUntil - now) / 1000)) + (lockedAtLabel ? ' (at ' + lockedAtLabel + ')' : '') + '.';
          expiryLine.textContent = '';
          setControls(true); resendBtn.disabled = true;
          resendBtn.textContent = 'Send a new code';
          return;
        }
        if (lockedUntil) { lockedUntil = 0; codeMsg.textContent = ''; setControls(false); }
        if (expiresAt) {
          var left = Math.ceil((expiresAt - now) / 1000);
          expiryLine.textContent = left > 0 ? 'Code expires in ' + fmtClock(left) : 'This code has expired. Send a new code.';
        }
        var wait = Math.ceil((resendAt - now) / 1000);
        resendBtn.disabled = wait > 0;
        resendBtn.textContent = wait > 0 ? 'Send a new code (' + fmtClock(wait) + ')' : 'Send a new code';
      }
      function startTick() {
        clearInterval(verifyTick);
        renderTimers();
        verifyTick = setInterval(renderTimers, 1000);
      }
      function updateSkip() {
        var has = !!emailInp6.value.trim();
        var done = !!(fState.emailVerified && fState.verifiedEmail === emailInp6.value.trim().toLowerCase());
        skipBtn.hidden = !has || done;
        // Visible status: verified or not.
        if (!has) {
          emailStateNote.textContent = '';
        } else if (done) {
          emailStateNote.textContent = '✓ Email verified';
          emailStateNote.className = 'intake-email-state is-verified';
        } else {
          emailStateNote.textContent = 'Email not verified';
          emailStateNote.className = 'intake-email-state is-unverified';
        }
      }
      function showVerified() {
        clearInterval(verifyTick);
        skipBtn.hidden = true;
        emailStateNote.textContent = '✓ Email verified';
        emailStateNote.className = 'intake-email-state is-verified';
        codeBox.hidden = true;
        vStatus.textContent = '';
        verifyEmailBtn.style.display = 'none';
        emailTick.hidden = false;
        emailInp6.style.paddingRight = '34px';
        emailError6.hidden = true;
      }
      function resetVerifyUi() {
        clearInterval(verifyTick);
        expiresAt = resendAt = lockedUntil = 0;
        codeBox.hidden = true; codeMsg.textContent = ''; codeInp.value = '';
        vStatus.style.color = ''; vStatus.textContent = '';
        setControls(false);
        verifyEmailBtn.textContent = 'Verify';
        verifyEmailBtn.style.display = '';
        emailTick.hidden = true;
        emailInp6.style.paddingRight = '';
        updateSkip();
      }

      if (fState.emailVerified && fState.verifiedEmail === emailInp6.value.trim().toLowerCase()) { showVerified(); }

      // Same email this browser already verified: skip the code (the server checks the signed token).
      var restoring = false;
      function maybeRestore() {
        var stored = readRemembered();
        var val = emailInp6.value.trim();
        if (restoring || fState.emailVerified || !stored || !val || !isValidEmail(val)) return;
        if (stored.email.toLowerCase() !== val.toLowerCase()) return;
        restoring = true;
        emailRestore(val, stored.remember)
          .then(function () {
            restoring = false;
            if (emailInp6.value.trim().toLowerCase() === val.toLowerCase()) { showVerified(); }
          })
          .catch(function () { restoring = false; forgetRemembered(); });
      }

      verifyEmailBtn.addEventListener('click', function () {
        var val = emailInp6.value.trim();
        if (!isValidEmail(val)) { emailError6.textContent = 'Please enter a valid email address.'; emailError6.hidden = false; emailInp6.focus(); return; }
        emailError6.hidden = true;
        verifyEmailBtn.disabled = true; verifyEmailBtn.textContent = 'Sending…';
        emailStart(val)
          .then(function (d) {
            applyTimers({ expiresIn: d.expires_in, resendIn: d.resend_in });
            vStatus.style.color = '';
            vStatus.textContent = 'Enter the 6-digit code we emailed to ' + val + '.';
            codeBox.hidden = false; codeMsg.textContent = ''; codeInp.value = '';
            verifyEmailBtn.textContent = 'Verify'; verifyEmailBtn.disabled = false;
            startTick();
            codeInp.focus();
          })
          .catch(function (e) {
            verifyEmailBtn.disabled = false; verifyEmailBtn.textContent = 'Verify';
            if (e && e.locked) {
              applyTimers(e); codeBox.hidden = false; startTick();
            } else {
              vStatus.style.color = '#b91c1c';
              vStatus.textContent = (e && e.reason && e.message) ? e.message : 'Something went wrong — please try again.';
            }
          });
      });

      function confirmCode() {
        var digits = codeInp.value.replace(/\D/g, '');
        if (digits.length !== 6) { codeMsg.textContent = 'Enter the 6-digit code from your email.'; return; }
        codeMsg.textContent = '';
        confirmBtn.disabled = true; confirmBtn.textContent = 'Checking…';
        emailVerify(digits)
          .then(function () { confirmBtn.textContent = 'Confirm code'; showVerified(); })
          .catch(function (e) {
            confirmBtn.disabled = false; confirmBtn.textContent = 'Confirm code';
            applyTimers(e || {});
            codeMsg.textContent = (e && e.reason && e.message) ? e.message : 'Something went wrong — please try again.';
            if (e && (e.reason === 'locked' || e.reason === 'expired')) { codeInp.value = ''; }
            if (e && e.locked) { startTick(); } else { renderTimers(); codeInp.focus(); }
          });
      }
      confirmBtn.addEventListener('click', confirmCode);
      codeInp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); confirmCode(); } });

      resendBtn.addEventListener('click', function () {
        codeMsg.textContent = '';
        resendBtn.disabled = true;
        emailResend()
          .then(function (d) {
            applyTimers({ expiresIn: d.expires_in, resendIn: d.resend_in });
            codeInp.value = ''; codeMsg.textContent = 'A new code was sent.'; renderTimers(); codeInp.focus();
          })
          .catch(function (e) {
            applyTimers(e || {});
            codeMsg.textContent = (e && e.reason && e.message) ? e.message : 'Something went wrong — please try again.';
            if (e && e.locked) { startTick(); } else { renderTimers(); }
          });
      });

      emailInp6.addEventListener('input', function () {
        if (fState.verifyToken || fState.emailVerified) {
          if (emailInp6.value.trim().toLowerCase() !== String(fState.verifiedEmail || fState.verifyEmailSent || '').toLowerCase()) {
            fState.emailVerified = false; fState.verifiedEmail = ''; fState.verifyToken = null;
            resetVerifyUi();
          }
        }
        maybeRestore();
      });
      maybeRestore();

      var phoneInp6 = inp('tel', '(555) 000-0000', 'tel');
      phoneInp6.inputMode = 'tel'; phoneInp6.maxLength = 20; phoneInp6.value = fState.phone;
      content.appendChild(phoneInp6);

      var phoneError6 = el('p', 'form-field-error'); phoneError6.textContent = 'Please enter a 10-digit phone number.'; phoneError6.hidden = true;
      content.appendChild(phoneError6);

      emailInp6.addEventListener('input', function () { emailInp6.setCustomValidity(''); emailError6.hidden = true; updateSkip(); });
      updateSkip();
      phoneInp6.addEventListener('input', function () {
        if (phoneValueIsAllowed(phoneInp6.value.trim())) { phoneError6.hidden = true; }
      });

      // The email is required; verifying it is optional and can be skipped.
      function proceedFromStep6() {
        var first = fInp.value.trim();
        var emailVal = emailInp6.value.trim();
        var phoneVal = phoneInp6.value.trim();
        var ok = true;
        if (!first) { nameError6.hidden = false; fInp.focus(); ok = false; }
        if (!isValidEmail(emailVal)) {
          emailError6.textContent = 'Please enter a valid email address.'; emailError6.hidden = false;
          if (ok) { emailInp6.focus(); ok = false; }
        }
        if (phoneVal && !isValidPhone(phoneVal)) {
          phoneError6.textContent = 'Please enter a 10-digit phone number.'; phoneError6.hidden = false;
          if (ok) { phoneInp6.focus(); ok = false; }
        }
        if (!ok) return;
        fState.firstName = first;
        fState.lastName  = lInp.value.trim();
        fState.email     = emailVal;
        fState.phone     = phoneVal;
        clearInterval(verifyTick);
        formStep = 7; renderFormStep(modal);
      }
      skipBtn.addEventListener('click', proceedFromStep6);
      addStepNav(content, modal, proceedFromStep6);
      setTimeout(function () { fInp.focus(); }, 30);

    // ── Step 7: Review, picked providers, consent, submit ─────────────────
    } else if (formStep === 7) {
      var picked7 = window.ECM_PICKS ? window.ECM_PICKS.get().providers : [];
      var n7      = picked7.length;

      addStepTitle(content, n7
        ? 'Ready to send your request to ' + n7 + (n7 === 1 ? ' provider' : ' providers') + '?'
        : 'Ready to send your request?');

      if (n7) {
        var pickedList7 = el('ul', 'intake-picked-list');
        picked7.forEach(function (p) {
          var li = document.createElement('li');
          li.textContent = p.name;
          pickedList7.appendChild(li);
        });
        content.appendChild(pickedList7);
      }

      var consent7 = el('p', 'step-hint');
      consent7.textContent = n7
        ? 'By submitting, you agree that the providers you selected may request your contact details to reach you.'
        : 'By submitting, you agree that the ElderCareMatters team may contact you about your request.';
      content.appendChild(consent7);

      // Expedited matching — unchecked by default, standalone control.
      var expWrap7 = document.createElement('label');
      expWrap7.className = 'intake-expedited-row';
      var expCb7 = document.createElement('input');
      expCb7.type = 'checkbox';
      expCb7.id = 'sf-expedited';
      expCb7.checked = !!fState.expeditedOptIn;
      expCb7.addEventListener('change', function () { fState.expeditedOptIn = expCb7.checked; });
      var expText7 = document.createElement('span');
      expText7.textContent = "If the providers I selected don't respond within 72 hours, or sooner if none are responsive, I direct ElderCareMatters to share my request with additional verified providers near me, with my contact details hidden until a provider requests them.";
      expWrap7.appendChild(expCb7);
      expWrap7.appendChild(expText7);
      content.appendChild(expWrap7);

      // Terms/Privacy assent — body copy above submit, both titles linked.
      if (window.ECM_INTAKE && window.ECM_INTAKE.assentHtml) {
        var assent7 = el('p', 'step-hint intake-assent-line');
        assent7.innerHTML = window.ECM_INTAKE.assentHtml;
        content.appendChild(assent7);
      }

      var submitLabel = formRedirectUrl ? 'Submit to View Website →' : 'Send My Request →';
      var submitBtn7 = btn(submitLabel, 'form-submit');
      content.appendChild(submitBtn7);
      addStepNav(content, modal, null);
      submitBtn7.addEventListener('click', function () {
        submitBtn7.disabled = true; submitBtn7.textContent = 'Submitting…';
        submitLead()
          .then(function () {
            if (formRedirectUrl) {
              window.location.href = formRedirectUrl;
            } else {
              showFormSuccess(modal);
            }
          })
          .catch(function (e) {
            if (e && e.reason === 'unverified') {
              // The verified email expired or changed — send them back to verify it again.
              fState.emailVerified = false; fState.verifiedEmail = ''; fState.verifyToken = null;
              formStep = 6; renderFormStep(modal);
              var note = modal.querySelector('#intakeStepContent .form-field-error');
              if (note) { note.textContent = e.message; note.hidden = false; }
              return;
            }
            submitBtn7.disabled = false;
            submitBtn7.textContent = submitLabel;
            var err = content.querySelector('.intake-submit-error');
            if (!err) {
              err = document.createElement('p');
              err.className = 'intake-submit-error';
              err.style.cssText = 'color:#b91c1c;font-size:0.85rem;margin-top:8px;';
              content.appendChild(err);
            }
            err.textContent = (e && e.reason && e.message) ? e.message : 'Something went wrong — please try again or email us directly.';
          });
      });
    }
  }

  function addStepTitle(container, text) {
    var h = el('h4', 'step-title'); h.textContent = text;
    container.appendChild(h);
  }

  function addStepNav(container, modal, onNext) {
    var nav = el('div', 'step-nav');
    if (formStep > 1) {
      var back = btn('← Back', 'step-back');
      back.addEventListener('click', function () { formStep--; renderFormStep(modal); });
      nav.appendChild(back);
    }
    if (onNext) {
      var next = btn('Next →', 'form-submit step-next');
      next.addEventListener('click', onNext);
      nav.appendChild(next);
    }
    if (nav.children.length) container.appendChild(nav);
  }

  function showFormSuccess(modal) {
    var content = modal.querySelector('#intakeStepContent');
    var prog    = modal.querySelector('.intake-stepper-progress');
    if (prog) prog.style.display = 'none';
    var loc = currentLocation();
    var urgLabel = (URGENCY_OPTIONS.find(function (u) { return u.key === fState.urgency; }) || {}).label || '';
    content.innerHTML = '<div class="intake-complete">'
      + '<div class="intake-complete-icon">✓</div>'
      + '<h4>Request submitted, ' + escHtml(fState.firstName) + '!</h4>'
      + '<p>Verified providers in ' + escHtml(loc.city) + ' will reach out within 24 hours.</p>'
      + '<div class="intake-summary">'
      + '<div><strong>Need:</strong> ' + escHtml(careTypeLabel(fState.careType)) + '</div>'
      + '<div><strong>Location:</strong> ' + escHtml(loc.city) + (loc.state ? ', ' + escHtml(loc.state) : '') + '</div>'
      + '<div><strong>Urgency:</strong> ' + escHtml(urgLabel) + '</div>'
      + '</div>'
      + crossSellHtml(fState.careType)
      + '<button class="form-submit" type="button" id="formNewReq" style="margin-top:16px;">New request</button>'
      + '</div>';
    content.querySelector('#formNewReq').addEventListener('click', function () {
      resetFormState();
      if (prog) prog.style.display = '';
      renderFormStep(modal);
    });
  }

  // ── Shared DOM helpers ───────────────────────────────────────────────────────

  function el(tag, cls) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    return e;
  }
  function inp(type, placeholder, autocomplete) {
    var i = el('input', 'chat-text-input');
    i.type = type; i.placeholder = placeholder;
    i.setAttribute('aria-label', placeholder);
    if (autocomplete) i.autocomplete = autocomplete;
    return i;
  }
  function btn(text, cls) {
    var b = el('button', cls);
    b.type = 'button'; b.textContent = text;
    return b;
  }

  // ── Chat FAB wiring ──────────────────────────────────────────────────────────

  function wireChatFab() {
    var fab   = document.getElementById('chatFab');
    var popup = document.getElementById('chatPopup');
    if (!fab || !popup) return;

    function open()   { fab.classList.add('open'); popup.classList.add('open'); }
    function close()  { fab.classList.remove('open'); popup.classList.remove('open'); }
    function toggle() { if (popup.classList.contains('open')) close(); else open(); }

    fab.addEventListener('click', toggle);
    var closeBtn = popup.querySelector('[data-close-chat]');
    if (closeBtn) closeBtn.addEventListener('click', close);
    document.querySelectorAll('[data-open-chat]').forEach(function (b) {
      b.addEventListener('click', open);
    });

    // Re-render chat location step when location modal closes
    var lm = document.getElementById('locationModal');
    if (lm) {
      new MutationObserver(function () {
        if (!lm.classList.contains('active') && popup.classList.contains('open') && chatStep() === 3) {
          renderChat();
        }
      }).observe(lm, { attributes: true, attributeFilter: ['class'] });
    }
  }

  // ── Form modal wiring ────────────────────────────────────────────────────────

  function wireFormModal() {
    var modal = document.getElementById('formModal');
    if (!modal) return;

    function openModal() {
      resetFormState();
      // Default care type: the first VALID category from, in order:
      //   1. the category selected on the Find Care page (search bar),
      //   2. the category of the page being viewed (?category= / ?type= in the URL,
      //      e.g. the Senior Living category page),
      //   3. the saved category (the visitor's last explicit choice),
      //   4. the picked providers' category.
      // The page's own category must beat the saved one: the saved category is only
      // a default for pages that have no category of their own (home, a provider
      // page). A picked provider's category is often a subcategory slug that is not
      // a care type, so every source is checked against the care types instead of
      // stopping at the first value.
      var picks = window.ECM_PICKS && window.ECM_PICKS.get();
      var sp = new URLSearchParams(location.search);
      var searchSel = document.getElementById('search-category');
      var prefillCat = '';
      [
        searchSel ? searchSel.value : '',
        sp.get('category'),
        sp.get('type'),
        savedCategory(),
        picks && picks.category
      ].some(function (candidate) {
        if (candidate && CARE_TYPES.some(function (c) { return c.key === candidate; })) {
          prefillCat = candidate;
          return true;
        }
        return false;
      });
      if (prefillCat) {
        fState.careType = prefillCat;
        formStep = 2; // category already known: skip the care-type step
      }
      modal.classList.add('active');
      var prog = modal.querySelector('.intake-stepper-progress');
      if (prog) prog.style.display = '';
      renderFormStep(modal);
    }
    var lastTrigger = null;
    function closeModal() {
      modal.classList.remove('active');
      if (lastTrigger && document.contains(lastTrigger)) { lastTrigger.focus(); }
      lastTrigger = null;
    }

    document.addEventListener('keydown', function (e) {
      if (!modal.classList.contains('active')) return;
      if (e.key === 'Backspace') {
        var bt = e.target || {};
        var btag = (bt.tagName || '').toLowerCase();
        var editable = (btag === 'textarea' && !bt.readOnly && !bt.disabled)
          || (btag === 'input' && !bt.readOnly && !bt.disabled
              && !/^(button|checkbox|radio|submit|reset|image|file|range|color)$/i.test(bt.type || 'text'))
          || bt.isContentEditable;
        if (!editable) { e.preventDefault(); }
        return;
      }
      if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
      if (e.key !== 'Tab') return;
      var items = Array.prototype.filter.call(
        modal.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])'),
        function (n) { return !n.disabled && n.offsetParent !== null; }
      );
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-open-form-modal]');
      if (!trigger) return;
      formRedirectUrl = trigger.dataset.redirectUrl || '';
      lastTrigger = trigger;
      openModal();
    });
    var closeBtn = modal.querySelector('[data-close-form-modal]');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    // Re-render location step when location modal closes while form is open
    var lm = document.getElementById('locationModal');
    if (lm) {
      new MutationObserver(function () {
        if (!lm.classList.contains('active') && modal.classList.contains('active') && formStep === 3) {
          renderFormStep(modal);
        }
      }).observe(lm, { attributes: true, attributeFilter: ['class'] });
    }
  }

  // ── Saved category ──────────────────────────────────────────────────────────
  // The category chosen on Find Care is remembered in localStorage and, for
  // logged-in users, as user meta (inc/saved-category.php). The request form
  // and the plan-purchase forms open with it already selected.
  var CAT_KEY  = 'ecm_selected_category';
  var CAT_PREF = window.ECM_CATEGORY_PREF || {};

  function savedCategory() {
    var v = '';
    try { v = (JSON.parse(localStorage.getItem(CAT_KEY) || 'null') || {}).key || ''; } catch (e) {}
    return v || CAT_PREF.saved || '';
  }

  function saveCategory(key) {
    if (!key) return;
    try { localStorage.setItem(CAT_KEY, JSON.stringify({ key: key, t: Date.now() })); } catch (e) {}
    if (CAT_PREF.loggedIn && CAT_PREF.root && window.fetch) {
      fetch(CAT_PREF.root + '/category', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CAT_PREF.nonce || '' },
        body: JSON.stringify({ category: key })
      }).catch(function () {});
    }
  }

  window.ECM_SAVED_CATEGORY = { get: savedCategory, set: saveCategory };

  // Preselect the saved category on forms that already exist in the page:
  // the listing form in the plan-purchase flow (WP Job Manager category field,
  // options carry data-slug) and other "category" selects on provider forms.
  function applySavedCategoryToForms() {
    var key = savedCategory();
    if (!key) return;
    document.querySelectorAll('select.ecm-cascade-parent').forEach(function (sel) {
      if (sel.value) return;
      var opt = Array.prototype.filter.call(sel.options, function (o) { return o.getAttribute('data-slug') === key; })[0];
      if (opt) {
        sel.value = opt.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
    var name = ((CAT_PREF.names || {})[key] || '').toLowerCase();
    if (!name) return;
    document.querySelectorAll('.wpcf7 select, form.ecm-category-form select').forEach(function (sel) {
      if (!/categor/i.test((sel.name || '') + ' ' + (sel.id || ''))) return;
      var opt = Array.prototype.filter.call(sel.options, function (o) {
        return o.value === key || o.value.trim().toLowerCase() === name || o.text.trim().toLowerCase() === name;
      })[0];
      if (opt) {
        sel.value = opt.value;
        sel.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  // ── Boot ─────────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    wireChatFab();
    wireFormModal();
    renderChat();
    applySavedCategoryToForms();
  });

})();
