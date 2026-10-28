/**
 * ECM Find Care — real data from /wp-json/ecm/v1/search
 * Replaces the static data.js dependency with live WP Job Manager results.
 */
(function () {
  'use strict';

  // Base URL for the REST API (works on both local and staging).
  var API_BASE = (window.ECM_SEARCH_API && window.ECM_SEARCH_API.root) || '/wp-json/ecm/v1';

  // State
  var meta         = null;   // { categories, cities }
  var lastTotal    = 0;
  var loadTimer    = null;
  var currentPage  = 1;
  var currentState = '';     // state abbreviation from location (e.g. 'TX')
  var requestSeq   = 0;      // guards against an older in-flight request's response
                              // resolving after a newer one and overwriting it (a real
                              // risk once the city filter fires from async AJAX selection
                              // rather than only a plain <select>'s synchronous change)

  // Whether the visitor has explicitly touched the city select (including
  // picking "All cities" themselves) — kept in sessionStorage so it survives
  // a reload within the same browsing session, but resets on a fresh visit.
  var CITY_EXPLICIT_KEY = 'ecm_city_explicit';
  function getCityExplicit() {
    try { return sessionStorage.getItem(CITY_EXPLICIT_KEY) === '1'; } catch (e) { return false; }
  }
  function setCityExplicit(val) {
    try {
      if (val) sessionStorage.setItem(CITY_EXPLICIT_KEY, '1');
      else sessionStorage.removeItem(CITY_EXPLICIT_KEY);
    } catch (e) {}
  }
  var RESULTS_PER_PAGE = 24;

  // ── Picked providers (Find Care provider-selection list, checklist item 58) ──
  // Persisted across page loads/searches so the list survives filtering and
  // pagination. Providers from any category can be mixed into one list, up
  // to PICK_CAP.
  var PICKED_KEY = 'ecm_picked_providers';
  var PICK_CAP   = 10;

  function getPicked() {
    try {
      var raw = JSON.parse(localStorage.getItem(PICKED_KEY) || 'null');
      // Bug fix: this used to also require raw.category to be truthy. A
      // picked list's own category is derived from its first provider (see
      // togglePick) and is only informational — requiring it to be truthy
      // meant a list that was valid in every other way (a real, non-empty
      // providers array) got silently discarded here whenever category
      // ended up falsy, so the Select button and the picks tray would show
      // nothing even though a pick had actually been saved.
      if (raw && Array.isArray(raw.providers)) return raw;
    } catch (e) {}
    return { category: null, providers: [] };
  }

  function savePicked(state) {
    try { localStorage.setItem(PICKED_KEY, JSON.stringify(state)); } catch (e) {}
    renderPicksBar();
    syncCardPickedStates();
  }

  function isPicked(id) {
    return getPicked().providers.some(function (p) { return p.id === id; });
  }

  function showPickMessage(msg) {
    var m = el('ecm-picks-message');
    if (!m) { return; }
    m.textContent = msg;
    m.hidden = false;
  }

  function clearPickMessage() {
    var m = el('ecm-picks-message');
    if (m) { m.hidden = true; }
  }

  // Toggles a provider's pick state. `p` needs { id, name, category, cat_name }.
  // Returns 'added' | 'removed' | 'capped' so the caller (the checkbox change
  // handler in wire()) knows whether to play the "added" indication or revert
  // a checkbox the browser already checked natively before the cap was hit.
  //
  // Picks are no longer restricted to one category per list — a family can
  // mix providers from any category into the same request. `state.category`
  // is kept only as a prefill hint for the intake form (see intake.js
  // wireFormModal()) and is derived from providers[0] so it can't drift from
  // the actual list.
  function togglePick(p) {
    var state = getPicked();
    clearPickMessage();

    var idx = state.providers.findIndex(function (x) { return x.id === p.id; });
    if (idx !== -1) {
      state.providers.splice(idx, 1);
      state.category = state.providers.length ? state.providers[0].category : null;
      savePicked(state);
      return 'removed';
    }

    if (state.providers.length >= PICK_CAP) {
      showPickMessage("You've picked 10. Remove one to add another.");
      return 'capped';
    }

    state.providers.push({ id: p.id, name: p.name, category: p.category, cat_name: p.cat_name });
    state.category = state.providers[0].category;
    savePicked(state);
    return 'added';
  }

  function clearPicked() {
    savePicked({ category: null, providers: [] });
  }

  // Re-applies checked/selected visual state to already-rendered cards
  // without a full re-render (renderCard() handles it on fresh renders).
  function syncCardPickedStates() {
    var grid = el('search-grid');
    if (!grid) { return; }
    var picked = getPicked();
    grid.querySelectorAll('.provider-pick-btn').forEach(function (label) {
      var id = parseInt(label.getAttribute('data-id'), 10);
      var on = picked.providers.some(function (p) { return p.id === id; });
      var name = label.getAttribute('data-name') || '';
      var checkbox = label.querySelector('.provider-pick-checkbox');
      if (checkbox) {
        checkbox.checked = on;
        checkbox.setAttribute('aria-label', (on ? 'Remove ' : 'Select ') + name);
      }
      label.classList.toggle('is-picked', on);
      var textEl = label.querySelector('.provider-pick-label');
      if (textEl) { textEl.textContent = on ? 'Deselect' : 'Select'; }
      var card = label.closest('.provider-card');
      if (card) { card.classList.toggle('is-picked', on); }
    });
  }

  // Brief pulse on the sticky picks bar so a newly-added pick is obviously
  // reflected at the bottom of the page, not just on the card itself.
  function bumpPicksBar() {
    var bar = el('ecm-picks-bar');
    if (!bar) { return; }
    bar.classList.remove('is-added');
    void bar.offsetWidth; // restart the animation if it's already mid-flight
    bar.classList.add('is-added');
    setTimeout(function () { bar.classList.remove('is-added'); }, 700);
  }

  // Sends a small "ghost" of the provider's name flying from its card down
  // to the picks bar, so it's visually obvious *where* the pick landed, not
  // just that something changed. Purely decorative — removed from the DOM
  // once the flight finishes.
  function flyNameToPicksBar(cardEl, name) {
    var bar = el('ecm-picks-bar');
    if (!bar || !cardEl || bar.hidden) { return; }
    var nameEl = cardEl.querySelector('.provider-card-name') || cardEl;
    var startRect = nameEl.getBoundingClientRect();
    var endRect = bar.getBoundingClientRect();
    if (!startRect.width || !endRect.width) { return; }

    var ghost = document.createElement('div');
    ghost.className = 'ecm-fly-pick';
    ghost.textContent = name;
    ghost.style.left = startRect.left + 'px';
    ghost.style.top = startRect.top + 'px';
    document.body.appendChild(ghost);

    var dx = (endRect.left + 28) - startRect.left;
    var dy = (endRect.top + endRect.height / 2) - (startRect.top + startRect.height / 2);

    requestAnimationFrame(function () {
      void ghost.offsetWidth; // ensure the start position paints before animating
      ghost.style.transform = 'translate(' + dx + 'px, ' + dy + 'px) scale(0.55)';
      ghost.style.opacity = '0';
    });

    setTimeout(function () {
      if (ghost.parentNode) { ghost.parentNode.removeChild(ghost); }
    }, 650);
  }

  function renderPicksBar() {
    var bar = el('ecm-picks-bar');
    if (!bar) { return; }
    var state = getPicked();
    var count = state.providers.length;

    var countEl = el('ecm-picks-count');
    if (countEl) { countEl.textContent = count; }

    var pickBtn = el('ecm-picks-btn');
    if (pickBtn) {
      pickBtn.textContent = 'Request Info from ' + count + (count === 1 ? ' Provider' : ' Providers');
    }

    var chips = el('ecm-picks-chips');
    if (chips) {
      chips.innerHTML = state.providers.map(function (p) {
        return '<span class="ecm-pick-chip">' + esc(p.name)
          + '<button type="button" class="ecm-pick-chip-remove" data-id="' + p.id + '" aria-label="Remove ' + esc(p.name) + '">&times;</button></span>';
      }).join('');
    }

    bar.hidden = count === 0;
  }

  // Exposed so intake.js (loaded separately) can read/clear the picked list
  // when rendering step 7 and after a successful submission.
  window.ECM_PICKS = {
    get: getPicked,
    clear: clearPicked,
    cap: PICK_CAP
  };

  // ── Utilities ────────────────────────────────────────────────────────────────

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (ch) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
    });
  }

  function decodeEntities(s) {
    var textarea = document.createElement('textarea');
    textarea.innerHTML = String(s || '');
    return textarea.value;
  }

  function el(id) { return document.getElementById(id); }

  // ── Card rendering ───────────────────────────────────────────────────────────

  // Tier icon on the card photo. Paid placements get a golden crown with the
  // "Sponsored" disclosure (Remaining Pages item 113). Basic and Standard
  // listings get their own icon in the same style. The label is the tooltip
  // (hover, keyboard focus or tap) and the accessible name.
  var TIER_ICONS = {
    sponsored: {
      label: 'Sponsored',
      svg: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8z" fill="#E0A800" stroke="#9A6B00" stroke-width="1.2" stroke-linejoin="round"/><circle cx="3" cy="8" r="1.4" fill="#E0A800" stroke="#9A6B00"/><circle cx="12" cy="4.6" r="1.4" fill="#E0A800" stroke="#9A6B00"/><circle cx="21" cy="8" r="1.4" fill="#E0A800" stroke="#9A6B00"/></svg>'
    },
    basic: {
      label: 'Basic',
      svg: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M12 2l8 3v6c0 5-3.4 9.2-8 11-4.6-1.8-8-6-8-11V5l8-3z" fill="#C98B52" stroke="#8A5A2B" stroke-width="1.2" stroke-linejoin="round"/><path d="M8.5 12.2l2.4 2.4 4.6-4.8" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    },
    standard: {
      label: 'Standard',
      svg: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="#B6BFC8" stroke="#7B8794" stroke-width="1.2"/><path d="M12 7.2l1.5 3.1 3.4.5-2.5 2.4.6 3.4-3-1.6-3 1.6.6-3.4-2.5-2.4 3.4-.5L12 7.2z" fill="#fff"/></svg>'
    }
  };

  // API tiers: premium/featured = Featured plan, standard = Premium plan, basic, free.
  // Free listings carry no icon.
  function tierIconBadge(tier) {
    var key = (tier === 'premium' || tier === 'featured') ? 'sponsored'
      : (tier === 'standard' ? 'standard' : (tier === 'basic' ? 'basic' : ''));
    if (!key) return '';
    var t = TIER_ICONS[key];
    return '<span class="provider-tier-badge provider-tier-icon tier-' + key + '" tabindex="0" role="img"'
      + ' aria-label="' + t.label + '" data-tip="' + t.label + '">' + t.svg + '</span>';
  }

  function renderCard(p) {
    var logo = p.logo_url
      ? '<img src="' + esc(p.logo_url) + '" alt="' + esc(p.name) + '" style="width:100%;height:100%;object-fit:contain;padding:6px;border-radius:14px" loading="lazy" decoding="async">'
      : esc(p.initials);

    var logoBg = p.logo_url ? '#fff' : p.color;
    var logoPad = p.logo_url ? 'overflow:hidden;padding:0' : '';

    // Remaining Pages Changes doc item 113: every paid-placement card needs
    // a visible "Sponsored" label — "featured" tier was missing from this
    // check, so the Featured Directory Listings page (all featured-tier
    // cards) rendered with no label on any of them.
    var tierBadge = tierIconBadge(p.tier);

    var heroImage = '';
    if (p.hero_url) {
      heroImage = '<div class="provider-card-hero provider-card-hero--photo"><img src="' + esc(p.hero_url) + '" alt="" loading="lazy" decoding="async">' + tierBadge + '</div>';
    } else if (p.logo_url) {
      heroImage = '<div class="provider-card-hero provider-card-hero--fallback"><img src="' + esc(p.logo_url) + '" alt="" loading="lazy" decoding="async">' + tierBadge + '</div>';
    } else {
      heroImage = '<div class="provider-card-hero provider-card-hero--fallback"><span style="color:' + esc(p.color) + '">' + esc(p.initials) + '</span>' + tierBadge + '</div>';
    }

    var cityLine = p.city_label
      ? '<div class="provider-card-city">📍 ' + esc(p.city_label) + '</div>'
      : '';

    var tagline = p.tagline
      ? '<div class="provider-card-tagline">' + esc(p.tagline) + '</div>'
      : '';

    var ratingBlock = '';
    if (p.rating > 0) {
      ratingBlock = '<div class="provider-card-row">'
        + '<span class="provider-rating">★ ' + p.rating.toFixed(1) + '</span>'
        + (p.reviews ? '<span class="provider-rating-count">(' + p.reviews + ')</span>' : '')
        + '</div>';
    }

    var nonprofitBadge = p.nonprofit
      ? '<span class="provider-nonprofit-badge">'
        + '<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">'
        + '<circle cx="6" cy="4.8" r="2.6" stroke="currentColor" stroke-width="1.2"/>'
        + '<path d="M3.8 7.2L2.5 11l3.5-1.4L9.5 11 8.2 7.2" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round"/>'
        + '</svg>Non-Profit</span>'
      : '';

    var verifiedBadge = p.verified ? '<span class="ecm-verified-wrap"><span class="ecm-verified-badge ecm-verified-badge--card">' + esc(p.verified) + '</span>' + (p.verified_hint ? '<span class="ecm-verified-tip" role="tooltip">' + esc(p.verified_hint) + '</span>' : '') + '</span>' : '';

    var picked = isPicked(p.id);
    var pickBtn = '<label class="provider-pick-btn' + (picked ? ' is-picked' : '') + '"'
      + ' data-id="' + esc(p.id) + '" data-name="' + esc(p.name) + '" data-category="' + esc(p.category) + '" data-cat-name="' + esc(p.cat_name) + '">'
      + '<input type="checkbox" class="provider-pick-checkbox"' + (picked ? ' checked' : '')
      +   ' aria-label="' + (picked ? 'Remove ' : 'Select ') + esc(p.name) + '">'
      + '<span class="provider-pick-check" aria-hidden="true"></span>'
      + '<span class="provider-pick-label">' + (picked ? 'Deselect' : 'Select') + '</span>'
      + '</label>';

    return '<div class="provider-card' + (picked ? ' is-picked' : '') + '" data-provider-id="' + esc(p.id) + '">'
      + pickBtn
      + '<a class="provider-card-link" href="' + esc(p.permalink) + '">'
      + heroImage
      + '<div class="provider-card-header">'
      +   '<div class="provider-logo" style="background:' + esc(logoBg) + ';color:#fff;font-weight:700;' + logoPad + '">' + logo + '</div>'
      +   '<div class="provider-heading">'
      +     '<div class="provider-card-name">' + esc(p.name) + '</div>'
      +     cityLine
      +   '</div>'
      + '</div>'
      + '<div class="provider-card-body">'
      +   tagline
      +   ratingBlock
      +   nonprofitBadge
      +   verifiedBadge
      + '</div>'
      + '<div class="provider-card-footer">'
      +   '<span class="provider-card-arrow">View details →</span>'
      + '</div>'
      + '</a>'
      + '</div>';
  }

  // ── Grid update ──────────────────────────────────────────────────────────────

  function setLoading(on) {
    var grid = el('search-grid');
    var empty = el('empty-state');
    var refresh = el('search-refresh-indicator');
    var emptyShown = !!empty && empty.style.display !== 'none';
    if (on) {
      grid.style.opacity = '0.5';
      grid.style.pointerEvents = 'none';
      if (emptyShown) empty.style.opacity = '0.5';
      if (refresh && (grid.children.length || emptyShown)) refresh.style.display = '';
    } else {
      grid.style.opacity = '';
      grid.style.pointerEvents = '';
      if (empty) empty.style.opacity = '';
      if (refresh) refresh.style.display = 'none';
    }
  }

  // Reveals the count line (hidden via visibility:hidden in the server-
  // rendered HTML, so it never flashes "0 providers" before real data
  // exists) and hides the single centered grid spinner. Idempotent — safe
  // to call whether this is the very first load or a later re-search.
  function hideInitialLoading() {
    var metaCount   = el('search-meta-count');
    var loadingGrid = el('search-loading-grid');
    if (metaCount)   metaCount.style.visibility = '';
    if (loadingGrid) loadingGrid.style.display = 'none';
  }

  function showResults(data) {
    var grid  = el('search-grid');
    var empty = el('empty-state');
    var count = el('result-count');

    lastTotal = data.total || 0;
    if (count) count.textContent = lastTotal;

    setLoading(false);
    hideInitialLoading();

    if (!data.providers || data.providers.length === 0) {
      grid.innerHTML = '';
      grid.style.display = 'none';
      if (empty) {
        empty.style.display = '';
        // Zero results with a city filter applied reads as "the page is
        // broken" unless it's clear the city is why — e.g. Featured
        // Directory Listings defaults to the visitor's city, so "0 featured
        // providers" alone doesn't explain that it's just none in Dallas.
        var emptyLocation = el('empty-state-location');
        if (emptyLocation) {
          // The select's own rendered text, not its raw value — a state
          // pick's value is the "state:PA" sentinel (see getFilters()),
          // which isn't fit to show a visitor.
          var citySel = el('search-city');
          var rendered = document.querySelector('.select2-selection__rendered');
          var city = (rendered && citySel && citySel.value) ? rendered.textContent.trim() : '';
          emptyLocation.textContent = city ? ' in ' + city : '';
        }
      }
    } else {
      grid.style.display = '';
      if (empty) empty.style.display = 'none';
      grid.innerHTML = data.providers.map(renderCard).join('');
    }
  }

  // ── Filters ──────────────────────────────────────────────────────────────────

  var STATE_PICK_PREFIX = 'state:';

  function getFilters() {
    var cityRaw = (el('search-city') || {}).value || '';
    var isStatePick = 0 === cityRaw.indexOf(STATE_PICK_PREFIX);
    return {
      q:           (el('search-q')          || {}).value || '',
      category:    (el('search-category')   || {}).value || '',
      subcategory: (el('search-subcategory')|| {}).value || '',
      city:        isStatePick ? '' : cityRaw,
      statePick:   isStatePick ? cityRaw.slice(STATE_PICK_PREFIX.length) : '',
      sort:        (el('sort-select')       || {}).value || 'recommended',
    };
  }

  function syncFilterURL(filters, page) {
    var params = new URLSearchParams(location.search);
    ['type', 'q', 'category', 'subcategory', 'city', 'state', 'page'].forEach(function (key) {
      params.delete(key);
    });

    if (filters.q)           params.set('q', filters.q);
    if (filters.category)    params.set('category', filters.category);
    if (filters.subcategory) params.set('subcategory', filters.subcategory);
    if (filters.city)        params.set('city', filters.city);
    // An explicit state pick (from the city picker's state entries) is kept
    // in the URL so it survives a refresh/share — unlike the soft state
    // default below, which is only ever an implicit fallback and shouldn't
    // be written to the URL as if the visitor had chosen it.
    else if (filters.statePick) params.set('state', filters.statePick);
    if (page > 1)            params.set('page', String(page));

    var query = params.toString();
    history.replaceState(null, '', location.pathname + (query ? '?' + query : '') + location.hash);
  }

  function run(pageOverride) {
    // Filter changes reset to page 1; explicit page argument navigates to that page.
    currentPage = (typeof pageOverride === 'number') ? pageOverride : 1;
    var f = getFilters();
    syncFilterURL(f, currentPage);
    var params = new URLSearchParams();
    if (f.q)           params.set('q',           f.q);
    if (f.category)    params.set('category',    f.category);
    if (f.subcategory) params.set('subcategory', f.subcategory);
    if (f.city) {
      params.set('city', f.city);
    } else if (f.statePick) {
      params.set('state', f.statePick);
    } else if (!getCityExplicit() && currentState) {
      // No city or state explicitly chosen: default to the visitor's state,
      // UNLESS they explicitly picked "All cities" themselves — that choice
      // means show everything.
      params.set('state', currentState);
    }
  if (f.sort)        params.set('sort',        f.sort);
    params.set('per_page', String(RESULTS_PER_PAGE));
    params.set('page', String(currentPage));

    var geo = window.ECM && window.ECM.getLocation && window.ECM.getLocation();
    if (geo && typeof geo.lat === 'number' && typeof geo.lon === 'number') {
      params.set('lat', geo.lat.toFixed(2));
      params.set('lng', geo.lon.toFixed(2));
    }

    // Fixed params a specific page always applies on top of whatever the
    // visitor searches within it (e.g. Featured Directory Listings scoping
    // every request to featured=1) — set by that page before this script runs.
    if (window.ECM_SEARCH_FIXED_PARAMS) {
      Object.keys(window.ECM_SEARCH_FIXED_PARAMS).forEach(function (key) {
        params.set(key, window.ECM_SEARCH_FIXED_PARAMS[key]);
      });
    }

    setLoading(true);

    var thisRequest = ++requestSeq;
    var requestedPage = currentPage;

    fetch(API_BASE + '/search?' + params.toString())
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (thisRequest !== requestSeq) return; // a newer request has since superseded this one
        showResults(data);
        renderPagination(data.total || 0, data.pages || 1, requestedPage);
      })
      .catch(function () {
        if (thisRequest !== requestSeq) return;
        setLoading(false);
        hideInitialLoading(); // Don't leave "Loading providers…" stuck forever on a failed fetch.
      });
  }

  function debounceRun() {
    clearTimeout(loadTimer);
    loadTimer = setTimeout(run, 200);
  }

  // ── Pagination ───────────────────────────────────────────────────────────────

  function renderPagination(total, pages, page) {
    var container = el('search-pagination');
    if (!container) {
      container = document.createElement('div');
      container.id = 'search-pagination';
      container.className = 'ecm-pagination';
      var grid = el('search-grid');
      if (grid && grid.parentNode) grid.parentNode.insertBefore(container, grid.nextSibling);
    }

    if (!pages || pages <= 1) { container.innerHTML = ''; return; }

    var from = ((page - 1) * RESULTS_PER_PAGE) + 1;
    var to   = Math.min(page * RESULTS_PER_PAGE, total);

    var hideTotals = !!(window.ECM_CATEGORY_PREF && window.ECM_CATEGORY_PREF.required);
    var html = (hideTotals ? '' : '<div class="ecm-pagination-info">Showing ' + from + '–' + to + ' of ' + total + ' providers</div>')
      + '<div class="ecm-pagination-controls">'
      + '<button class="ecm-page-btn"' + (page <= 1 ? ' disabled' : '') + ' data-page="' + (page - 1) + '">← Previous</button>';

    var start = Math.max(1, page - 2);
    var end   = Math.min(pages, page + 2);
    for (var i = start; i <= end; i++) {
      html += '<button class="ecm-page-btn' + (i === page ? ' ecm-page-btn--active' : '') + '" data-page="' + i + '">' + i + '</button>';
    }
    html += '<button class="ecm-page-btn"' + (page >= pages ? ' disabled' : '') + ' data-page="' + (page + 1) + '">Next →</button>'
      + '</div>';
    container.innerHTML = html;

    container.querySelectorAll('.ecm-page-btn:not([disabled])').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var p = parseInt(btn.getAttribute('data-page'), 10);
        if (p >= 1 && p <= pages) {
          window.scrollTo({ top: 0, behavior: 'smooth' });
          run(p);
        }
      });
    });
  }

  // ── Chip row ─────────────────────────────────────────────────────────────────

  function buildChips(categories) {
    var chipRow = el('category-chip-row');
    if (!chipRow) return;
    chipRow.innerHTML = '';

    var allChip = document.createElement('button');
    allChip.className = 'filter-chip active';
    allChip.dataset.cat = '';
    allChip.textContent = 'All';
    chipRow.appendChild(allChip);

    // Show top 10 categories by count to keep the chip row manageable.
    categories.slice(0, 10).forEach(function (c) {
      var chip = document.createElement('button');
      chip.className = 'filter-chip';
      chip.dataset.cat = c.key;
      chip.textContent = (c.icon || '') + ' ' + decodeEntities(c.name);
      chipRow.appendChild(chip);
    });

    chipRow.addEventListener('click', function (e) {
      var chip = e.target.closest('.filter-chip');
      if (!chip) return;
      chipRow.querySelectorAll('.filter-chip').forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      var catSel = el('search-category');
      if (catSel) catSel.value = chip.dataset.cat || '';
      buildSubcategorySelect(catSel ? catSel.value : '');
      if (categoryGateActive()) { applyChosenCategory((meta && meta.categories) || [], chip.dataset.cat || '', false); }
      run();
    });
  }

  function syncChipRow(catValue) {
    var chipRow = el('category-chip-row');
    if (!chipRow) return;
    chipRow.querySelectorAll('.filter-chip').forEach(function (c) {
      c.classList.toggle('active', c.dataset.cat === catValue);
    });
  }

  // ── Populate selects ─────────────────────────────────────────────────────────

  function buildSubcategorySelect(categoryValue, selectedValue) {
    var subcatSel = el('search-subcategory');
    if (!subcatSel) return;

    var selectedCategory = (meta && meta.categories || []).find(function (c) {
      return c.key === categoryValue;
    });
    var subcategories = (selectedCategory && selectedCategory.subcategories) || [];

    while (subcatSel.options.length > 1) subcatSel.remove(1);
    subcategories.forEach(function (c) {
      var opt = document.createElement('option');
      opt.value = c.key;
      opt.textContent = decodeEntities(c.name);
      subcatSel.appendChild(opt);
    });

    subcatSel.disabled = !subcategories.length;
    subcatSel.value = selectedValue || '';
  }

  function buildSelects(categories) {
    var catSel = el('search-category');
    if (catSel) {
      // Keep the first "All categories" option
      while (catSel.options.length > 1) catSel.remove(1);
      categories.forEach(function (c) {
        var opt = document.createElement('option');
        opt.value = c.key;
        opt.textContent = (c.icon || '') + ' ' + decodeEntities(c.name);
        catSel.appendChild(opt);
      });
    }
    buildSubcategorySelect(catSel ? catSel.value : '');
  }

  // ── City picker (selectWoo, AJAX-backed) ────────────────────────────────────
  // Every city with a real listing (no fixed top-N cap) — searched via
  // /search/cities rather than loaded all at once into a plain <select>.

  var CITY_PAGE_SIZE = 20;
  var localCityQuery = null;

  function foldText(s) {
    s = String(s).toLowerCase();
    return typeof s.normalize === 'function' ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s;
  }

  function initLocalCityQuery() {
    var data = window.ECM_SEARCH_API && window.ECM_SEARCH_API.cityData;
    if (!data || !data.cities || !data.cities.length) return;

    var states = data.states || [];
    var byCount = data.cities.map(function (name) {
      return { id: name, text: name, key: foldText(name) };
    });
    var alpha = byCount.slice().sort(function (a, b) {
      return a.text < b.text ? -1 : (a.text > b.text ? 1 : 0);
    });

    localCityQuery = function (term, page) {
      var q = String(term || '').trim();
      var folded = foldText(q);
      var offset = (page - 1) * CITY_PAGE_SIZE;
      var list = q ? alpha.filter(function (c) { return c.key.indexOf(folded) !== -1; }) : byCount;

      var results = list.slice(offset, offset + CITY_PAGE_SIZE).map(function (c) {
        return { id: c.id, text: c.text };
      });

      if (1 === page && states.length) {
        var lower = q.toLowerCase();
        var stateMatches = states.filter(function (s) {
          return !q || s.text.toLowerCase().indexOf(lower) !== -1;
        }).map(function (s) {
          return { id: s.id, text: s.text };
        });
        results = q ? stateMatches.concat(results) : results.concat(stateMatches);
      }

      if (!q && 1 === page) {
        results.unshift({ id: CITY_ALL_SENTINEL, text: 'All cities' });
      }

      return { results: results, pagination: { more: list.length > offset + CITY_PAGE_SIZE } };
    };
  }

  function initCitySelect() {
    var citySel = el('search-city');
    if (!citySel || !window.jQuery || !jQuery.fn.selectWoo) return;

    var ajaxConfig = {
      url: API_BASE + '/search/cities',
      dataType: 'json',
      delay: localCityQuery ? 0 : 200,
      data: function (params) {
        return { q: params.term || '', page: params.page || 1 };
      },
      processResults: function (data, params) {
        params.page = params.page || 1;
        return { results: data.results, pagination: data.pagination };
      },
      cache: true
    };
    if (localCityQuery) {
      ajaxConfig.transport = function (params, success) {
        success(localCityQuery(params.data.q, params.data.page));
      };
    }

    jQuery(citySel).selectWoo({
      placeholder: 'All cities',
      // No clear ("×") icon — "All cities" is offered as a real, selectable
      // first result instead (see /search/cities), so there's one single
      // path back to it rather than two.
      allowClear: false,
      width: '100%',
      minimumInputLength: 0,
      // Select2/selectWoo detach the open dropdown out to <body> rather than
      // nesting it under .search-bar, so scoping its CSS by DOM ancestry
      // wouldn't match — tag this one instance instead, to keep the styling
      // below from touching any other selectWoo/select2 use on the site
      // (WooCommerce checkout, WPJM forms, the provider dashboard, etc).
      dropdownCssClass: 'ecm-search-city-dropdown',
      // Add CSS hooks so state entries and the "All cities" sentinel
      // can be styled differently from regular city names.
      templateResult: function (data) {
        if (!data.id) return data.text; // "Searching…" / loading placeholders
        var id = String(data.id);
        var $el = jQuery('<span>').text(data.text);
        if (id.startsWith('state:')) $el.addClass('ecm-city-opt--state');
        if (id === '__all__')        $el.addClass('ecm-city-opt--all');
        return $el;
      },
      ajax: ajaxConfig
    });
  }

  // The "All cities" result comes back with a non-empty sentinel id (see
  // /search/cities) since select2 won't render a real option for an
  // empty-string id. Called from the change handler below, before run() —
  // translates it back to "" so getFilters() and everything else only ever
  // sees a real city or nothing, never the sentinel.
  var CITY_ALL_SENTINEL = '__all__';
  function resolveCitySentinel() {
    var citySel = el('search-city');
    if (citySel && CITY_ALL_SENTINEL === citySel.value) {
      citySel.value = '';
    }
  }

  // Ensures citySel has an <option> for this exact value (selectWoo/select2
  // can only select a value that already exists as an option), then applies
  // it and notifies selectWoo's UI + any 'change' listeners. label overrides
  // the option's display text (used for a state pick, whose value is the
  // "state:PA" sentinel — label carries the real "Pennsylvania" text).
  function setCitySelectValue(city, label) {
    var citySel = el('search-city');
    if (!citySel || !city) return;

    var existingOpt = Array.from(citySel.options).filter(function (opt) {
      return opt.value === city;
    })[0];
    if (!existingOpt) {
      existingOpt = document.createElement('option');
      existingOpt.value = city;
      citySel.appendChild(existingOpt);
    }
    existingOpt.textContent = label || city;

    if (window.jQuery && jQuery.fn.selectWoo) {
      jQuery(citySel).val(city).trigger('change');
    } else {
      citySel.value = city;
    }
  }

  function clearCitySelectValue() {
    var citySel = el('search-city');
    if (!citySel) return;
    if (window.jQuery && jQuery.fn.selectWoo) {
      jQuery(citySel).val('').trigger('change');
    } else {
      citySel.value = '';
    }
  }

  // ── Wiring ───────────────────────────────────────────────────────────────────

  // Bug fix: boot() can run more than once for a single page view (e.g. a
  // bfcache restore re-firing DOMContentLoaded), and boot() calls wire()
  // unconditionally once its async setup resolves. Every listener wire()
  // attaches is delegated on persistent container nodes (#search-grid,
  // #ecm-picks-bar, etc.) rather than on the cards themselves, so a second
  // call doesn't replace anything — it adds a second, independent listener
  // alongside the first. A single click then fires its handler twice: for
  // the picker this meant add-then-immediately-remove, corrupting the
  // picked list (a provider left behind while the list's own category got
  // reset to null) instead of doing nothing. Guard so wire() can only ever
  // run once per page.
  var wired = false;
  function wire() {
    if (wired) { return; }
    wired = true;
    var q       = el('search-q');
    var qClear  = el('search-q-clear');
    var catSel  = el('search-category');
    var subcatSel = el('search-subcategory');
    var citySel = el('search-city');
    var sort    = el('sort-select');
    var btn     = el('search-btn');

    // Provider-selection UI (checklist item 58): pick/unpick cards, remove
    // chips from the picks bar. Delegated on the grid/bar containers since
    // showResults() replaces grid.innerHTML on every search.
    var grid = el('search-grid');
    if (grid) {
      // The browser toggles the checkbox itself before 'change' fires, so on
      // a 'capped' result (cap already hit) it has to be reverted here —
      // togglePick() didn't record the pick, so the control shouldn't look
      // checked either.
      grid.addEventListener('change', function (e) {
        var checkbox = e.target.closest('.provider-pick-checkbox');
        if (!checkbox) { return; }
        var pickBtn = checkbox.closest('.provider-pick-btn');
        if (!pickBtn) { return; }
        var result = togglePick({
          id: parseInt(pickBtn.getAttribute('data-id'), 10),
          name: pickBtn.getAttribute('data-name'),
          category: pickBtn.getAttribute('data-category'),
          cat_name: pickBtn.getAttribute('data-cat-name')
        });
        if (result === 'capped') {
          checkbox.checked = false;
          return;
        }
        if (result === 'added') {
          pickBtn.classList.remove('just-added');
          void pickBtn.offsetWidth;
          pickBtn.classList.add('just-added');
          setTimeout(function () { pickBtn.classList.remove('just-added'); }, 500);
          flyNameToPicksBar(pickBtn.closest('.provider-card'), pickBtn.getAttribute('data-name'));
          bumpPicksBar();
        }
      });
    }
    var picksBar = el('ecm-picks-bar');
    if (picksBar) {
      picksBar.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('.ecm-pick-chip-remove');
        if (!removeBtn) { return; }
        var state = getPicked();
        var id = parseInt(removeBtn.getAttribute('data-id'), 10);
        state.providers = state.providers.filter(function (p) { return p.id !== id; });
        state.category = state.providers.length ? state.providers[0].category : null;
        savePicked(state);
      });
    }
    renderPicksBar();

    if (btn)     btn.addEventListener('click',  run);
    if (q)       q.addEventListener('input',    debounceRun);
    if (q)       q.addEventListener('keydown',  function (e) { if (e.key === 'Enter') run(); });

    // Visible "×" to clear the keyword field in one click — matches the
    // real-time filtering already wired above (typing/deleting text already
    // re-runs the search on its own; this button just saves selecting the
    // text manually). Shown only once there's something to clear.
    if (q && qClear) {
      var syncClear = function () { qClear.style.display = q.value ? 'flex' : 'none'; };
      syncClear();
      q.addEventListener('input', syncClear);
      qClear.addEventListener('click', function () {
        q.value = '';
        syncClear();
        q.focus();
        run();
      });
    }
    if (subcatSel) subcatSel.addEventListener('change', run);
    // selectWoo's own selection handling only dispatches a jQuery-internal
    // 'change' — a plain addEventListener never sees it (confirmed live: the
    // underlying <select>'s value updates correctly, but no native change
    // event follows) — so this one listener has to be bound via jQuery too.
    if (citySel && window.jQuery) {
      jQuery(citySel).on('change', function () {
        if (CITY_ALL_SENTINEL === citySel.value) {
          // Re-selecting '' updates both the underlying value and selectWoo's
          // own displayed pill text (its rendered text otherwise stays on the
          // sentinel's own label). This re-fires 'change' once more, but by
          // then the value is '' — the guard above only matches the sentinel,
          // so it just proceeds straight to run() on that second pass.
          jQuery(citySel).val('').trigger('change');
          return;
        }
        setCityExplicit(true);
        updateLocationHeadingFromCitySelect(citySel);
        run();
      });
    } else if (citySel) {
      citySel.addEventListener('change', function () {
        resolveCitySentinel();
        setCityExplicit(true);
        updateLocationHeadingFromCitySelect(citySel);
        run();
      });
    }
    if (sort)    sort.addEventListener('change', run);

    if (catSel) {
      catSel.addEventListener('change', function () {
        syncChipRow(catSel.value);
        buildSubcategorySelect(catSel.value);
        if (categoryGateActive()) { applyChosenCategory((meta && meta.categories) || [], catSel.value, false); }
        run();
      });
    }
  }

  // ── Prefill from URL / location ───────────────────────────────────────────────

  function resolveCategorySelection(categories, categoryValue, subcategoryValue) {
    var parent = categories.find(function (category) {
      return category.key === categoryValue;
    });

    if (parent) {
      var hasSubcategory = (parent.subcategories || []).some(function (subcategory) {
        return subcategory.key === subcategoryValue;
      });
      return {
        category: parent.key,
        subcategory: hasSubcategory ? subcategoryValue : ''
      };
    }

    var childKey = subcategoryValue || categoryValue;
    for (var i = 0; i < categories.length; i++) {
      var child = (categories[i].subcategories || []).find(function (subcategory) {
        return subcategory.key === childKey;
      });
      if (child) {
        return { category: categories[i].key, subcategory: child.key };
      }
    }

    return { category: '', subcategory: '' };
  }

  function prefillFromURL(categories) {
    var params = new URLSearchParams(location.search);
    var urlCat = params.get('type') || params.get('category') || '';
    var urlSubcat = params.get('subcategory') || '';
    var urlQ   = params.get('q') || '';
    var urlPage = parseInt(params.get('page') || '1', 10);
    currentPage = Number.isFinite(urlPage) && urlPage > 0 ? urlPage : 1;

    if (urlQ) {
      var q = el('search-q');
      if (q) q.value = urlQ;
    }

    var selection = resolveCategorySelection(categories, urlCat, urlSubcat);
    var catSel = el('search-category');
    if (catSel) catSel.value = selection.category;
    syncChipRow(selection.category);
    buildSubcategorySelect(selection.category, selection.subcategory);
  }

  // A city filter can arrive two ways — a ?city= URL param, or the stored
  // location from the header's location picker — and neither is trustworthy
  // as-is: a URL can carry anything (typos, or a stale link from before the
  // isRealCity fix, e.g. ?city=Pennsylvania), and an already-known-real
  // stored location skips the round-trip entirely. This checks the
  // untrusted candidate against real listing data before treating it as a
  // hard city filter, and falls back to a state-level soft scope when it
  // turns out to actually be a state name — syncing the header text either
  // way, so it always matches whatever filter is really applied instead of
  // showing a stale/default location.
  function resolveCityFilter(urlCity, storedLoc) {
    var candidate = urlCity || ((!getCityExplicit() && storedLoc && storedLoc.city && !storedLoc.auto) ? storedLoc.city : '');
    var candidateIsKnownReal = !urlCity && storedLoc && storedLoc.isRealCity;

    if (!candidate) return Promise.resolve();
    if (candidateIsKnownReal) {
      setCitySelectValue(candidate);
      return Promise.resolve();
    }

    var lookup = localCityQuery
      ? Promise.resolve(localCityQuery(candidate, 1))
      : fetch(API_BASE + '/search/cities?q=' + encodeURIComponent(candidate)).then(function (r) { return r.json(); });

    return lookup
      .then(function (data) {
        var lower = candidate.toLowerCase();
        var results = data.results || [];

        var cityMatch = results.find(function (r) {
          return r.id && 0 !== r.id.indexOf(STATE_PICK_PREFIX) && r.id.toLowerCase() === lower;
        });
        if (cityMatch) {
          setCitySelectValue(cityMatch.id);
          setLocationHeaderText(cityMatch.id);
          return;
        }

        // Prefer a state match already offered by /search/cities itself —
        // properly selects it in the dropdown too, not just the header.
        // Falls back to a local, always-on check (independent of the
        // ECM_SEARCH_CITIES_MATCH_STATES toggle) so a state name still
        // resolves to at least the correct soft state-scope even with that
        // feature turned off — just without a dropdown selection to show.
        var stateMatch = results.find(function (r) {
          return r.id && 0 === r.id.indexOf(STATE_PICK_PREFIX) && r.text.toLowerCase() === lower;
        });
        if (stateMatch) {
          currentState = stateMatch.id.slice(STATE_PICK_PREFIX.length);
          setCitySelectValue(stateMatch.id, stateMatch.text);
          setLocationHeaderText(stateMatch.text);
          return;
        }

        var stateAbbr = window.ECM && window.ECM.STATE_ABBR;
        var stateKey = stateAbbr && Object.keys(stateAbbr).find(function (name) {
          return name.toLowerCase() === lower;
        });
        if (stateKey) {
          currentState = stateAbbr[stateKey];
          setLocationHeaderText(stateKey);
        }
        // Neither a real city nor a recognizable state name — quietly drop
        // it rather than filtering by something no listing can ever match.
      })
      .catch(function () {});
  }

  // The page heading ("Find the right care in X") otherwise keeps showing
  // whatever the header's stored/detected location was, even after the
  // visitor explicitly picks a different (or no) city in the search filters
  // — confirmed live: picking "All cities" still showed the old state name,
  // with 1715 unfiltered results underneath it. Reads the select's own
  // current option text, since that already carries the right label for
  // every case this control supports (a real city, a state entry picked
  // from search, or the "All cities" placeholder).
  function updateLocationHeadingFromCitySelect(citySel) {
    if (!citySel) return;
    var opt = citySel.options[citySel.selectedIndex];
    var text = (opt && opt.value) ? opt.text : 'your area';
    setLocationHeaderText(text);
  }

  // Updates just the header's location text — not window.ECM.setLocation(),
  // which would also persist to localStorage and fire _onRender (which
  // clears the very city selection this is meant to reflect, undoing it).
  function setLocationHeaderText(text) {
    document.querySelectorAll('.js-location-city').forEach(function (node) {
      node.textContent = text;
    });
  }

  // ── Mandatory category (Find Care page only) ───────────────────────────────
  // Results never load without a category. A category already in the URL, or
  // one saved from an earlier visit (localStorage, or user meta when logged
  // in), is used straight away; otherwise a dialog asks for one. The choice is
  // saved and can be changed from the "Showing" line under the heading.

  var CAT_PREF = window.ECM_CATEGORY_PREF || {};
  var CAT_STORE = window.ECM_SAVED_CATEGORY || { get: function () { return ''; }, set: function () {} };

  function categoryGateActive() {
    return !!CAT_PREF.required && !window.ECM_SEARCH_FIXED_PARAMS;
  }

  function categoryName(categories, key) {
    var hit = (categories || []).filter(function (c) { return c.key === key; })[0];
    return hit ? decodeEntities(hit.name) : '';
  }

  // `persist` is true only for an explicit choice (the first-visit dialog or
  // "Change"). Filtering with the dropdown or chips, or arriving with a
  // category in the URL, does not overwrite the saved category.
  function applyChosenCategory(categories, key, persist) {
    var catSel = el('search-category');
    if (catSel) catSel.value = key;
    syncChipRow(key);
    buildSubcategorySelect(key);
    if (persist) CAT_STORE.set(key);
    updateCategoryLine(categories, key);
  }

  // "Showing: Memory Care · Change" under the heading.
  function updateCategoryLine(categories, key) {
    var hero = document.querySelector('.search-hero');
    if (!hero) return;
    var line = el('ecm-cat-line');
    if (!line) {
      line = document.createElement('p');
      line.id = 'ecm-cat-line';
      line.className = 'ecm-cat-line';
      var sub = hero.querySelector('p');
      if (sub && sub.parentNode) sub.parentNode.insertBefore(line, sub.nextSibling);
      else hero.appendChild(line);
    }
    line.innerHTML = 'Showing: <strong></strong> <button type="button" class="ecm-cat-change">Change</button>';
    line.querySelector('strong').textContent = key ? (categoryName(categories, key) || 'your category') : 'All categories';
    line.querySelector('button').addEventListener('click', function () {
      askCategory(categories, key, true).then(function (picked) {
        if (!picked || picked === key) return;
        applyChosenCategory(categories, picked, true);
        run();
      });
    });
  }

  function askCategory(categories, current, dismissible) {
    return new Promise(function (resolve) {
      var previous = document.activeElement;
      // Same look as the category step of the request form: modal panel,
      // step title and the icon grid of care types.
      var overlay = document.createElement('div');
      overlay.className = 'modal-overlay form-modal active ecm-cat-gate';
      overlay.innerHTML =
        '<div class="modal modal-lg" role="dialog" aria-modal="true" aria-labelledby="ecm-cat-gate-title" aria-describedby="ecm-cat-gate-desc">'
        + '<button class="modal-close ecm-cat-gate-close" type="button" aria-label="Close">&times;</button>'
        + '<div class="intake-stepper">'
        +   '<h4 class="step-title" id="ecm-cat-gate-title">What kind of help do you need?</h4>'
        +   '<p class="step-hint" id="ecm-cat-gate-desc">Choose one to see providers near you. You can change it later.</p>'
        +   '<div class="care-type-grid ecm-cat-gate-grid" role="group" aria-labelledby="ecm-cat-gate-title"></div>'
        +   (dismissible ? '<button type="button" class="ecm-cat-gate-cancel">Keep current category</button>' : '')
        + '</div></div>';
      var grid = overlay.querySelector('.ecm-cat-gate-grid');

      function close(value) {
        document.removeEventListener('keydown', onKey, true);
        document.body.classList.remove('ecm-cat-gate-open');
        if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
        if (previous && previous.focus && document.contains(previous)) previous.focus({ preventScroll: true });
        resolve(value);
      }

      (categories || []).forEach(function (c) {
        var name = decodeEntities(c.name);
        var icon = window.ECM_CARE_ICON ? window.ECM_CARE_ICON(c.key, name) : '';
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'care-type-btn' + (c.key === current ? ' selected is-current' : '');
        b.innerHTML = '<span class="care-type-icon">' + icon + '</span><span></span>';
        b.lastChild.textContent = name;
        b.addEventListener('click', function () { close(c.key); });
        grid.appendChild(b);
      });
      var cancel = overlay.querySelector('.ecm-cat-gate-cancel');
      if (cancel) cancel.addEventListener('click', function () { close(null); });
      // Close (x): keeps the current category when changing; on first load it
      // shows all categories (nothing is saved).
      var closeBtn = overlay.querySelector('.ecm-cat-gate-close');
      if (closeBtn) closeBtn.addEventListener('click', function () { close(dismissible ? null : ''); });

      function onKey(e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          close(dismissible ? null : '');   // same as the close button
          return;
        }
        if (e.key !== 'Tab') return;
        var items = overlay.querySelectorAll('button');
        if (!items.length) return;
        var first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus({ preventScroll: true }); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus({ preventScroll: true }); }
        else if (!overlay.contains(document.activeElement)) { e.preventDefault(); first.focus({ preventScroll: true }); }
      }
      document.addEventListener('keydown', onKey, true);

      document.body.appendChild(overlay);
      document.body.classList.add('ecm-cat-gate-open');
      window.scrollTo(0, 0);   // never leave the page scrolled down behind the dialog
      var focusTarget = grid.querySelector('.is-current') || grid.querySelector('button');
      if (focusTarget) focusTarget.focus({ preventScroll: true });
    });
  }

  // Resolves once a category is set. "All" stays available afterwards as a
  // browsing view; it is never saved.
  function ensureCategory(categories, earlyPick) {
    if (!categoryGateActive()) return Promise.resolve();

    var catSel = el('search-category');

    var known = function (k) { return !!k && (categories || []).some(function (c) { return c.key === k; }); };
    var current = catSel ? catSel.value : '';
    if (known(current)) { applyChosenCategory(categories, current, false); return Promise.resolve(); }

    var saved = CAT_STORE.get();
    if (known(saved)) { applyChosenCategory(categories, saved, false); return Promise.resolve(); }

    // The dialog was already opened at page load, before the category list
    // came back from the API; use its answer.
    if (earlyPick) {
      return earlyPick.then(function (picked) {
        applyChosenCategory(categories, picked || '', !!picked);
      });
    }
    return askCategory(categories, '', false).then(function (picked) {
      applyChosenCategory(categories, picked || '', !!picked);
    });
  }

  // ── Boot ─────────────────────────────────────────────────────────────────────

  function boot() {
    initLocalCityQuery();
    initCitySelect();

    var urlCity = new URLSearchParams(location.search).get('city') || '';
    var storedLoc = null;
    try { storedLoc = JSON.parse(localStorage.getItem('eldercare_location') || 'null'); } catch(e) {}
    if (!storedLoc && window.ECM && window.ECM.getLocation) {
      storedLoc = window.ECM.getLocation();
    }
    if (storedLoc && storedLoc.state) {
      currentState = storedLoc.state;
    }

    // Find Care: with no category in the URL and none saved, open the picker
    // immediately from the category names the page already carries, instead of
    // waiting for /search/meta (which made the listing loader show first).
    var earlyPick = null;
    if (categoryGateActive()) {
      var bootParams = new URLSearchParams(location.search);
      var bootCategory = bootParams.get('category') || bootParams.get('type') || '';
      var bootSaved = CAT_STORE.get();
      var bootNames = CAT_PREF.names || {};
      if (!bootCategory && !(bootSaved && bootNames[bootSaved])) {
        var bootCats = Object.keys(bootNames).map(function (k) { return { key: k, name: bootNames[k] }; });
        if (bootCats.length) {
          earlyPick = askCategory(bootCats, '', false);
        }
      }
    }

    // Metadata (categories): embedded in the page when available, so the saved or
    // URL category is applied at once; otherwise fetched from the API.
    var embeddedMeta = window.ECM_SEARCH_API && window.ECM_SEARCH_API.meta;
    var metaPromise = (embeddedMeta && embeddedMeta.categories && embeddedMeta.categories.length)
      ? Promise.resolve(embeddedMeta)
      : fetch(API_BASE + '/search/meta').then(function (r) { return r.json(); });
    metaPromise
      .then(function (data) {
        meta = data;
        buildSelects(data.categories || []);
        buildChips(data.categories || []);
        prefillFromURL(data.categories);
        return ensureCategory(data.categories || [], earlyPick).then(function () {
          return resolveCityFilter(urlCity, storedLoc);
        });
      })
      .then(function () {
        wire();
        run(currentPage);
      })
      .catch(function (err) {
        console.warn('ECM search meta failed, running with empty filters.', err);
        wire();
        run();
      });

    // React to location changes from the header location modal.
    if (window.ECM) {
      window.ECM._onRender = function (newLoc) {
        if (!newLoc) return;
        currentState = newLoc.state || '';
        // A location change is a fresh default, not an explicit "All cities"
        // choice — go back to scoping by the new state until the visitor
        // touches the city select themselves.
        setCityExplicit(false);
        // Clear explicit city selection — a stale pick shouldn't linger after
        // the visitor changes location.
        clearCitySelectValue();
        // Update the hero city label.
        document.querySelectorAll('.js-location-city').forEach(function (node) {
          node.textContent = newLoc.city || newLoc.state || 'your area';
        });
        run(); // resets to page 1
      };
    }
  }

  document.addEventListener('DOMContentLoaded', boot);

})();
