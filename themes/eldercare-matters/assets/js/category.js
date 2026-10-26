/**
 * ECM Category Page — live data from /wp-json/ecm/v1/category
 *
 * Replaces the data.js dependency for providers and category metadata.
 * City images/taglines/nearby still use data.js as geographic reference data.
 */
(function () {
  'use strict';

  var API_BASE = (window.ECM_SEARCH_API && window.ECM_SEARCH_API.root) || '/wp-json/ecm/v1';
  var PROVIDERS_PER_PAGE = 16;
  var STATE_NAMES = [
    'Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware',
    'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky',
    'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi',
    'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico',
    'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania',
    'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont',
    'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming'
  ];
  var nearbyRequestVersion = 0;
  var providerInputTimer = null;

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

  function setText(sel, text) {
    document.querySelectorAll(sel).forEach(function (node) { node.textContent = text; });
  }

  function isStateSelection(city) {
    var normalized = String(city || '').toLowerCase();
    return STATE_NAMES.some(function (stateName) {
      return stateName.toLowerCase() === normalized;
    });
  }

  function clearNearby() {
    nearbyRequestVersion += 1;
    var nearbySection = el('nearby-section');
    var nearbyGrid = el('nearby-grid');
    if (nearbyGrid) nearbyGrid.innerHTML = '';
    if (nearbySection) nearbySection.style.display = 'none';
  }

  function parseJsonResponse(response) {
    return response.text().then(function (text) {
      if (!response.ok) throw new Error('Provider request failed with status ' + response.status);

      try {
        return JSON.parse(text);
      } catch (error) {
        // Local PHP warnings can be printed before an otherwise valid REST payload.
        var jsonStart = text.indexOf('{"category"');
        if (jsonStart !== -1) return JSON.parse(text.slice(jsonStart));
        throw error;
      }
    });
  }

  function showProviderMessage(title, detail) {
    var grid = el('all-providers-results');
    var empty = el('no-providers-msg');
    var emptyTitle = el('no-providers-title');
    var emptyDetail = el('no-providers-detail');

    if (grid) {
      grid.innerHTML = '';
      grid.style.display = 'none';
    }
    if (emptyTitle) emptyTitle.textContent = title;
    if (emptyDetail) emptyDetail.textContent = detail;
    if (empty) empty.style.display = '';
  }

  function providerFiltersActive() {
    var activeTier = document.querySelector('#filter-row .filter-chip.active');
    var activeSubcat = document.querySelector('#subcategory-row .filter-chip.active');

    return !!(
      (activeTier && activeTier.getAttribute('data-filter') !== 'all')
      || (activeSubcat && activeSubcat.getAttribute('data-subcat') !== 'all')
    );
  }

  function getParams() {
    var p = new URLSearchParams(location.search);
    return {
      type:     p.get('type')  || 'home-care',
      cityHint: p.get('city')  || '',
      state:    p.get('state') || '',
    };
  }

  // ── Card renderers ───────────────────────────────────────────────────────────

  // Tier icon on the card photo, same as Find Care: Featured plan = crown
  // ("Sponsored"), Premium plan = Standard, Basic = Basic, Free = none.
  // `tier` is the display tier from the API, so a lapsed or pending-renewal
  // subscription already arrives as "free".
  var TIER_ICONS = {
    sponsored: { label: 'Sponsored', svg: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8z" fill="#E0A800" stroke="#9A6B00" stroke-width="1.2" stroke-linejoin="round"/><circle cx="3" cy="8" r="1.4" fill="#E0A800" stroke="#9A6B00"/><circle cx="12" cy="4.6" r="1.4" fill="#E0A800" stroke="#9A6B00"/><circle cx="21" cy="8" r="1.4" fill="#E0A800" stroke="#9A6B00"/></svg>' },
    basic: { label: 'Basic', svg: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M12 2l8 3v6c0 5-3.4 9.2-8 11-4.6-1.8-8-6-8-11V5l8-3z" fill="#C98B52" stroke="#8A5A2B" stroke-width="1.2" stroke-linejoin="round"/><path d="M8.5 12.2l2.4 2.4 4.6-4.8" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>' },
    standard: { label: 'Standard', svg: '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="#B6BFC8" stroke="#7B8794" stroke-width="1.2"/><path d="M12 7.2l1.5 3.1 3.4.5-2.5 2.4.6 3.4-3-1.6-3 1.6.6-3.4-2.5-2.4 3.4-.5L12 7.2z" fill="#fff"/></svg>' }
  };

  function tierIconBadge(tier) {
    var key = (tier === 'premium' || tier === 'featured') ? 'sponsored'
      : (tier === 'standard' ? 'standard' : (tier === 'basic' ? 'basic' : ''));
    if (!key) return '';
    var t = TIER_ICONS[key];
    return '<span class="provider-tier-badge provider-tier-icon tier-' + key + '" tabindex="0" role="img"'
      + ' aria-label="' + t.label + '" data-tip="' + t.label + '">' + t.svg + '</span>';
  }

  function renderProviderCard(p, displayCity) {
    var logo = p.logo_url
      ? '<img src="' + esc(p.logo_url) + '" alt="' + esc(p.name) + '" style="width:100%;height:100%;object-fit:cover;border-radius:14px" loading="lazy" decoding="async">'
      : esc(p.initials);
    var logoBg  = p.logo_url ? '#fff' : p.color;
    var logoPad = p.logo_url ? 'overflow:hidden;padding:0' : '';

    var tierBadge = tierIconBadge(p.tier);

    var heroImage = '';
    if (p.hero_url) {
      heroImage = '<div class="provider-card-hero provider-card-hero--photo"><img src="' + esc(p.hero_url) + '" alt="" loading="lazy" decoding="async">' + tierBadge + '</div>';
    } else if (p.logo_url) {
      heroImage = '<div class="provider-card-hero provider-card-hero--fallback"><img src="' + esc(p.logo_url) + '" alt="" loading="lazy" decoding="async">' + tierBadge + '</div>';
    } else {
      heroImage = '<div class="provider-card-hero provider-card-hero--fallback"><span style="color:' + esc(p.color) + '">' + esc(p.initials) + '</span>' + tierBadge + '</div>';
    }

    var cityLine = (p.city_label || displayCity)
      ? '<div class="provider-card-city">📍 ' + esc(p.city_label || displayCity) + '</div>'
      : '';

    var ratingBlock = '';
    if (p.rating > 0) {
      ratingBlock = '<div class="provider-card-row">'
        + '<span class="provider-rating">★ ' + p.rating.toFixed(1) + '</span>'
        + (p.reviews ? '<span class="provider-rating-count">(' + p.reviews + ')</span>' : '')
        + (p.cat_name ? '<span>· ' + esc(p.cat_name) + '</span>' : '')
        + '</div>';
    }

    var nonprofitBadge = p.nonprofit
      ? '<span class="provider-nonprofit-badge"><svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="6" cy="4.8" r="2.6" stroke="currentColor" stroke-width="1.2"/><path d="M3.8 7.2L2.5 11l3.5-1.4L9.5 11 8.2 7.2" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round"/></svg>Non-Profit</span>'
      : '';

    return '<a class="provider-card" href="' + esc(p.permalink) + '" data-tier="' + esc(p.tier) + '">'
      + heroImage
      + '<div class="provider-card-header">'
      +   '<div class="provider-logo" style="background:' + esc(logoBg) + ';color:#fff;font-weight:700;' + logoPad + '">' + logo + '</div>'
      +   '<div class="provider-heading">'
      +     '<div class="provider-card-name">' + esc(p.name) + '</div>'
      +     cityLine
      +   '</div>'
      + '</div>'
      + '<div class="provider-card-body">'
      +   (p.tagline ? '<div class="provider-card-tagline">' + esc(p.tagline) + '</div>' : '')
      +   ratingBlock
      +   nonprofitBadge
      + '</div>'
      + '<div class="provider-card-footer">'
      +   '<span class="provider-card-arrow">View details →</span>'
      + '</div>'
      + '</a>';
  }

  function renderNearbyCard(p) {
    var logo = p.logo_url
      ? '<img src="' + esc(p.logo_url) + '" alt="' + esc(p.name) + '" style="width:100%;height:100%;object-fit:cover;border-radius:10px" loading="lazy" decoding="async">'
      : esc(p.initials);
    var logoBg  = p.logo_url ? '#fff' : p.color;
    var logoPad = p.logo_url ? 'overflow:hidden;padding:0' : '';
    return '<a class="nearby-card" href="' + esc(p.permalink) + '">'
      + '<div class="nearby-logo" style="background:' + esc(logoBg) + ';color:#fff;font-weight:700;' + logoPad + '">' + logo + '</div>'
      + '<div class="nearby-body">'
      +   '<div class="nearby-name">' + esc(p.name) + '</div>'
      +   '<div class="nearby-meta">'
      +     (p.city_label ? '<span>📍 ' + esc(p.city_label) + '</span>' : '')
      +     (p.rating > 0 ? '<span class="nearby-meta-sep">·</span><span>★ ' + p.rating.toFixed(1) + '</span>' : '')
      +   '</div>'
      + '</div>'
      + '<div class="nearby-price"><span class="provider-card-arrow">View →</span></div>'
      + '</a>';
  }

  function renderCrossSellCard(c, cityHint, stateHint) {
    var href = home_url('/category/') + '?type=' + encodeURIComponent(c.key)
      + (cityHint ? '&city=' + encodeURIComponent(cityHint) : '')
      + (stateHint ? '&state=' + encodeURIComponent(stateHint) : '');
    return '<a class="cross-sell-card" href="' + esc(href) + '">'
      + '<div class="cross-sell-icon">' + esc(c.icon) + '</div>'
      + '<div class="cross-sell-body">'
      +   '<div class="cross-sell-name">' + esc(c.name) + '</div>'
      +   '<div class="cross-sell-meta">Explore ' + esc(c.name.toLowerCase()) + ' in your area</div>'
      + '</div>'
      + '<span class="cross-sell-arrow">→</span>'
      + '</a>';
  }

  // home_url helper — reads from localized data or falls back to origin.
  function home_url(path) {
    var base = (window.ECM_SEARCH_API && window.ECM_SEARCH_API.home) || window.location.origin;
    return base.replace(/\/$/, '') + path;
  }

  function syncLocationUrl(city, state) {
    if (!window.history || !window.history.replaceState) return;

    var url = new URL(window.location.href);
    if (city) {
      url.searchParams.set('city', city);
    } else {
      url.searchParams.delete('city');
    }

    if (state) {
      url.searchParams.set('state', state);
    } else {
      url.searchParams.delete('state');
    }

    url.searchParams.delete('page');
    window.history.replaceState(window.history.state, '', url.toString());
  }

  // ── Provider grid wiring (search + sort + tier filter) ───────────────────────

  function wireProviders(allProviders, displayCity, fetchPage) {
    var grid      = el('all-providers-results');
    var empty     = el('no-providers-msg');
    var chips     = document.querySelectorAll('#filter-row .filter-chip');
    var searchEl  = el('providers-search');
    var sortEl    = el('providers-sort');
    var activeChip = document.querySelector('#filter-row .filter-chip.active');
    var activeTier   = activeChip ? activeChip.getAttribute('data-filter') : 'all';
    var activeSubcat = 'all';

    // Subcategory chips
    var subcatChips = document.querySelectorAll('#subcategory-row .filter-chip[data-subcat]');
    subcatChips.forEach(function (chip) {
      chip.onclick = function () {
        subcatChips.forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        activeSubcat = chip.getAttribute('data-subcat');
        render();
      };
    });

    function render() {
      var list = allProviders.slice();

      // Tier filter
      if (activeTier !== 'all') {
        var tierMap = { featured: 'premium', premium: 'standard', basic: 'basic' };
        var providerTier = tierMap[activeTier] || activeTier;
        list = list.filter(function (p) { return p.tier === providerTier; });
      }
      // Subcategory filter
      if (activeSubcat !== 'all') {
        var sub = activeSubcat.toLowerCase();
        list = list.filter(function (p) {
          return (p.specialties || []).some(function (s) { return s.toLowerCase() === sub; });
        });
      }
      // Sort (client-side; data already arrives sorted by recommended)
      if (sortEl && sortEl.value === 'rating') {
        list.sort(function (a, b) { return b.rating - a.rating; });
      } else if (sortEl && sortEl.value === 'newest') {
        // already date-sorted from server; no re-sort needed
      }

      grid.innerHTML = list.map(function (p) { return renderProviderCard(p, displayCity); }).join('');
      grid.style.display   = list.length ? '' : 'none';
      var pagination = el('providers-pagination');
      if (pagination) {
        pagination.style.display = (!list.length || providerFiltersActive()) ? 'none' : 'flex';
      }
      if (!list.length && allProviders.length) {
        var tierLabel = activeTier === 'all' ? 'selected' : activeTier;
        showProviderMessage(
          'No providers match these filters',
          'There are no ' + tierLabel + ' providers matching your current search. Try All or clear the search field.'
        );
      } else if (empty) {
        empty.style.display = list.length ? 'none' : '';
      }
    }

    chips.forEach(function (chip) {
      chip.onclick = function () {
        chips.forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        activeTier = chip.getAttribute('data-filter');
        render();
      };
    });

    if (searchEl) {
      searchEl.oninput = function () {
        clearTimeout(providerInputTimer);
        providerInputTimer = setTimeout(function () {
          fetchPage(sortEl ? sortEl.value : 'recommended', 1, searchEl.value.trim());
        }, 250);
      };
    }

    if (sortEl) {
      sortEl.onchange = function () {
        if (sortEl.value === 'recommended' || sortEl.value === 'newest') {
          // re-fetch from server for these sort modes
          fetchPage(sortEl.value);
        } else {
          render();
        }
      };
    }

    render();
    return render; // expose for external re-render
  }

  // ── Pagination renderer ──────────────────────────────────────────────────────

  function renderPagination(total, pages, currentPage, onPageChange) {
    var container = document.getElementById('providers-pagination');
    if (!container) {
      container = document.createElement('div');
      container.id = 'providers-pagination';
      container.className = 'ecm-pagination ecm-provider-pagination';
      var grid = document.getElementById('all-providers-results');
      if (grid && grid.parentNode) grid.parentNode.insertBefore(container, grid.nextSibling);
    }

    if (!pages || pages <= 1) {
      container.innerHTML = '';
      container.style.display = 'none';
      return;
    }

    container.style.display = providerFiltersActive() ? 'none' : 'flex';

    var from = ((currentPage - 1) * PROVIDERS_PER_PAGE) + 1;
    var to   = Math.min(currentPage * PROVIDERS_PER_PAGE, total);

    var prevDisabled = currentPage <= 1 ? ' disabled' : '';
    var nextDisabled = currentPage >= pages ? ' disabled' : '';

    var html =
      '<div class="ecm-pagination-controls">'
      +   '<button class="ecm-page-btn"' + prevDisabled + ' data-page="' + (currentPage - 1) + '">← Previous</button>';

    // Page number buttons (show up to 5 around current page)
    var start = Math.max(1, currentPage - 2);
    var end   = Math.min(pages, currentPage + 2);
    for (var i = start; i <= end; i++) {
      var active = i === currentPage ? ' ecm-page-btn--active' : '';
      html += '<button class="ecm-page-btn' + active + '" data-page="' + i + '">' + i + '</button>';
    }

    html +=
        '<button class="ecm-page-btn"' + nextDisabled + ' data-page="' + (currentPage + 1) + '">Next →</button>'
      + '</div>';
    container.innerHTML = html;

    container.querySelectorAll('.ecm-page-btn:not([disabled])').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var page = parseInt(btn.getAttribute('data-page'), 10);
        if (page >= 1 && page <= pages) {
          onPageChange(page);
          var providerSection = el('all-providers-grid');
          if (providerSection) providerSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  // ── Boot ─────────────────────────────────────────────────────────────────────

  function boot() {
    var params = getParams();
    var type   = params.type;
    var state  = params.state;

    // City used both for the displayed heading and for the provider query below —
    // URL ?city= param, or location.js's stored/default city. Item 102: these two
    // used to come from different sources (cityHint vs loc.city), so the page could
    // show "in Dallas" while fetchPage() sent no city filter at all, returning
    // providers from every city.
    var loc      = (window.ECM && window.ECM.getLocation && window.ECM.getLocation()) || {};
    var cityHint = params.cityHint || loc.city || '';
    var displayCity = cityHint || 'your area';
    // Use state from URL, or from stored location
    if (!state && loc.state) state = loc.state;

    // City hero image/tagline from data.js (geographic reference data — stays static)
    var D = window.ECM_DATA;
    var cityKey = D && D.normalizeCityKey ? D.normalizeCityKey(displayCity) : displayCity.toLowerCase().replace(/\s+/g, '-');
    var cityInfo = (D && D.cities && D.cities[cityKey]) || { name: displayCity, state: state || '', tagline: '', image: '' };
    var cityName = cityInfo.name || displayCity;

    // Update all city spans immediately (before fetch)
    document.querySelectorAll('.js-location-city').forEach(function (node) {
      node.textContent = cityName;
    });

    function setCityImage(img, imageUrl, alt) {
      if (!img || !imageUrl) return;

      img.onerror = function () {
        var fallback = img.dataset.fallbackSrc;
        if (fallback && img.src !== fallback) img.src = fallback;
      };
      img.src = imageUrl;
      img.alt = alt;
    }

    // The location selector supports places beyond the curated city dataset.
    // Find a real landmark image for those locations instead of showing the map placeholder.
    function setDiscoveredLandmarkImage(img, locationName) {
      if (!img || !locationName) return;

      var query = STATE_NAMES.indexOf(locationName) !== -1
        ? locationName + ' State Capitol'
        : locationName + ' skyline landmark';
      var search = encodeURIComponent(query);
      var endpoint = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&origin=*'
        + '&generator=search&gsrnamespace=6&gsrlimit=5&prop=imageinfo&iiprop=url&iiurlwidth=1200'
        + '&gsrsearch=' + search;

      fetch(endpoint)
        .then(function (response) { return response.json(); })
        .then(function (data) {
          var pages = data && data.query && data.query.pages ? Object.keys(data.query.pages) : [];
          var page = pages.map(function (id) { return data.query.pages[id]; }).find(function (result) {
            return result.imageinfo && result.imageinfo[0] && (result.imageinfo[0].thumburl || result.imageinfo[0].url);
          });

          if (page) {
            var image = page.imageinfo[0];
            setCityImage(img, image.thumburl || image.url, locationName);
          }
        })
        .catch(function () {
          // Leave the local placeholder in place if no landmark image is available.
        });
    }

    // City hero image
    var cityImg = el('city-image');
    if (cityImg && cityInfo.image) {
      setCityImage(cityImg, cityInfo.image, cityName);
    } else {
      setDiscoveredLandmarkImage(cityImg, cityName);
    }
    if (el('city-badge-name'))    el('city-badge-name').textContent    = cityName + (cityInfo.state ? ', ' + cityInfo.state : '');
    if (el('city-badge-tagline')) el('city-badge-tagline').textContent = cityInfo.tagline || '';

    // ── Fetch category + providers ──────────────────────────────────────────
    var currentSort = 'recommended';
    var currentPage = 1;
    var currentSearch = '';
    var fetchVersion = 0;

    function fetchPage(sort, page, search) {
      // If sort changes, reset to page 1; otherwise use provided page or keep current
      if (sort && sort !== currentSort) { currentSort = sort; currentPage = 1; }
      if (page) currentPage = page;
      if (typeof search === 'string') currentSearch = search;

      var requestVersion = ++fetchVersion;
      var stateWide = isStateSelection(cityHint);
      var apiParams = new URLSearchParams({ type: type, per_page: String(PROVIDERS_PER_PAGE), sort: currentSort, page: String(currentPage) });
      if (cityHint && !stateWide) apiParams.set('city', cityHint);
      if (state)    apiParams.set('state', state);
      if (currentSearch) apiParams.set('q', currentSearch);

      var geo = window.ECM && window.ECM.getLocation && window.ECM.getLocation();
      if (geo && typeof geo.lat === 'number' && typeof geo.lon === 'number') {
        apiParams.set('lat', geo.lat.toFixed(2));
        apiParams.set('lng', geo.lon.toFixed(2));
      }

      clearNearby();

      fetch(API_BASE + '/category?' + apiParams.toString())
        .then(parseJsonResponse)
        .then(function (data) {
          if (requestVersion !== fetchVersion) return;

          var cat       = data.category || {};
          var providers = data.providers || [];
          var total     = data.total || 0;
          var pages     = data.pages || 1;

          // ── Hero ──
          var catName  = decodeEntities(cat.name || type);
          var catIcon  = cat.icon || '🏥';
          var catBlurb = cat.blurb || '';

          document.title = catName + ' in ' + cityName + ' | ElderCareMatters';
          setText('#breadcrumb-current', catName + ' in ' + cityName);
          setText('#category-icon',      catIcon);
          setText('#category-name',      catName);
          setText('#category-inline',    catName.toLowerCase());
          setText('#category-inline-3',  catName.toLowerCase());
          setText('#category-blurb',     catBlurb);
          setText('#hero-meta-count',    total ? total + ' providers verified' : 'Grow your Business');

          var heroBtn = el('hero-cta-btn');
          if (heroBtn) heroBtn.innerHTML = '📋 Find ' + esc(catName) + ' Providers <span class="btn-arrow">→</span>';

          // ── Providers grid ──
          var grid  = el('all-providers-results');
          var empty = el('no-providers-msg');
          var emptyTitle = el('no-providers-title');
          var emptyDetail = el('no-providers-detail');

          if (emptyTitle) emptyTitle.textContent = "We're still growing in this area";
          if (emptyDetail) {
            emptyDetail.textContent = stateWide
              ? 'No providers are currently listed in ' + cityName + '. Try another location or request help.'
              : 'No direct matches in your city yet — the providers below in nearby cities also serve ' + cityName + '.';
          }

          if (providers.length === 0 && currentPage === 1) {
            if (grid)  grid.style.display  = 'none';
            if (empty) empty.style.display = '';
            // Clear pagination when no results
            var pager = el('providers-pagination');
            if (pager) pager.innerHTML = '';
            if (currentSearch) {
              if (emptyTitle) emptyTitle.textContent = 'No providers match your search';
              if (emptyDetail) emptyDetail.textContent = 'Try a different provider name or specialty.';
            } else {
              // Fetch state-level fallback as "nearby" providers
              fetchNearby(type, cityHint, state);
            }
          } else {
            if (empty) empty.style.display = 'none';
            var nearbySection = el('nearby-section');
            if (nearbySection) nearbySection.style.display = 'none';

            // Subcategory chips (for grief-counselors etc.)
            var SUBCATS = { 'grief-counselors': ['Bereavement Care','Grief Support Group','Grief Counseling','Grief Support Services','Bereavement Counseling'] };
            var subRow = el('subcategory-row');
            if (subRow && SUBCATS[type]) {
              subRow.innerHTML = '<button class="filter-chip active" data-subcat="all">All</button>'
                + SUBCATS[type].map(function (s) {
                    return '<button class="filter-chip" data-subcat="' + esc(s) + '">' + esc(s) + '</button>';
                  }).join('');
              subRow.style.display = 'flex';
            }

            wireProviders(providers, cityName, fetchPage);
            renderPagination(total, pages, currentPage, function (newPage) {
              fetchPage(currentSort, newPage);
            });
          }

          // ── Cross-sell ──
          var crossGrid = el('cross-sell-grid');
          if (crossGrid && cat.cross_sell && cat.cross_sell.length) {
            crossGrid.innerHTML = cat.cross_sell.map(function (c) {
              return renderCrossSellCard(c, cityHint, state);
            }).join('');
            var crossSection = el('cross-sell-section');
            if (crossSection) crossSection.style.display = '';
          } else {
            // Fallback cross-sell from data.js
            if (D && D.getCrossSell) {
              var cross = D.getCrossSell(type);
              if (crossGrid && cross.length) {
                crossGrid.innerHTML = cross.map(function (c) {
                  return renderCrossSellCard(c, cityHint, state);
                }).join('');
              }
            }
          }
        })
        .catch(function (err) {
          if (requestVersion !== fetchVersion) return;
          console.warn('ECM category fetch failed', err);
          // Fall back to data.js if API fails
          if (D) {
            fallbackToDataJs(type, cityKey, cityInfo, cityName, cityHint);
          } else {
            showProviderMessage('Providers could not be loaded', 'Please refresh the page or try again in a moment.');
          }
        });
    }

    fetchPage('recommended', 1);

    // React to city/state changes made via the header location modal.
    // location.js calls window.ECM._onRender(loc) whenever the location is updated.
    if (window.ECM) {
      window.ECM._onRender = function (newLoc) {
        if (!newLoc || (!newLoc.city && !newLoc.state)) return;

        // Update closed-over variables so fetchPage() uses the new location.
        cityHint    = newLoc.city || '';
        displayCity = newLoc.city || newLoc.state || 'your area';
        state       = newLoc.state || '';
        currentPage = 1; // reset to page 1 on location change
        currentSearch = '';
        syncLocationUrl(cityHint, state);

        // Update city image/badge from data.js
        var newKey  = D && D.normalizeCityKey ? D.normalizeCityKey(cityHint) : (cityHint || '').toLowerCase().replace(/\s+/g, '-');
        var newInfo = (D && D.cities && D.cities[newKey]) || { name: cityHint || displayCity, state: newLoc.state || '', tagline: '', image: '' };
        cityName = newInfo.name || cityHint || displayCity;

        clearTimeout(providerInputTimer);
        providerInputTimer = null;
        document.querySelectorAll('#filter-row .filter-chip').forEach(function (chip) {
          chip.onclick = null;
          chip.classList.toggle('active', chip.getAttribute('data-filter') === 'all');
        });
        var searchInput = el('providers-search');
        var sortSelect = el('providers-sort');
        var providerGrid = el('all-providers-results');
        var noProviders = el('no-providers-msg');
        var pagination = el('providers-pagination');
        if (searchInput) {
          searchInput.oninput = null;
          searchInput.value = '';
        }
        if (sortSelect) sortSelect.onchange = null;
        if (providerGrid) {
          providerGrid.style.display = '';
          providerGrid.innerHTML = '<div class="provider-grid-loading" role="status">Loading providers in ' + esc(cityName) + '…</div>';
        }
        if (noProviders) noProviders.style.display = 'none';
        if (pagination) pagination.innerHTML = '';

        document.querySelectorAll('.js-location-city').forEach(function (node) {
          node.textContent = cityName;
        });
        var img = el('city-image');
        if (img && newInfo.image) {
          setCityImage(img, newInfo.image, cityName);
        } else {
          setDiscoveredLandmarkImage(img, cityName);
        }
        if (el('city-badge-name'))    el('city-badge-name').textContent    = cityName + (newInfo.state ? ', ' + newInfo.state : '');
        if (el('city-badge-tagline')) el('city-badge-tagline').textContent = newInfo.tagline || '';

        // Re-fetch providers for the new location (page 1).
        fetchPage(currentSort, 1);
      };
    }
  }

  // ── Nearby providers (shown when city returns 0 results) ──────────────────
  // Fetches state-level providers (or all if no state) as a fallback.

  function fetchNearby(type, excludeCity, stateFilter) {
    var requestVersion = ++nearbyRequestVersion;
    var params = new URLSearchParams({ type: type, per_page: '12', sort: 'recommended' });
    // Filter by state if available — show same-state providers as fallback
    if (stateFilter) params.set('state', stateFilter);
    fetch(API_BASE + '/category?' + params.toString())
      .then(parseJsonResponse)
      .then(function (data) {
        if (requestVersion !== nearbyRequestVersion) return;

        var providers = (data.providers || []).filter(function (p) {
          // Exclude providers from the exact city already shown (if any)
          return !excludeCity || !p.city || p.city.toLowerCase() !== excludeCity.toLowerCase();
        }).slice(0, 9);

        var nearbySection = el('nearby-section');
        var nearbyGrid    = el('nearby-grid');
        if (providers.length && nearbyGrid) {
          nearbyGrid.innerHTML = providers.map(renderNearbyCard).join('');
          if (nearbySection) nearbySection.style.display = '';
        } else {
          if (nearbyGrid) nearbyGrid.innerHTML = '';
          if (nearbySection) nearbySection.style.display = 'none';
        }
      })
      .catch(function () {});
  }

  // ── Fallback to data.js if REST API is unavailable ────────────────────────

  function fallbackToDataJs(type, cityKey, cityInfo, cityName, cityHint) {
    var D = window.ECM_DATA;
    if (!D) {
      showProviderMessage('Providers could not be loaded', 'Please refresh the page or try again in a moment.');
      return;
    }
    var cat       = D.getCategory(type) || D.getCategory('home-care');
    var providers = D.getProvidersByCategoryCity(cat.key, cityKey);

    document.title = cat.name + ' in ' + cityName + ' | ElderCareMatters';
    setText('#breadcrumb-current', cat.name + ' in ' + cityName);
    setText('#category-icon',   cat.icon);
    setText('#category-name',   cat.name);
    setText('#category-inline', cat.name.toLowerCase());
    setText('#category-blurb',  cat.blurb);

    var grid = el('all-providers-results');
    if (grid && providers.length) {
      // data.js providers don't have permalinks; link to find-care instead
      grid.innerHTML = providers.map(function (p) {
        return renderProviderCard(Object.assign({}, p, { permalink: home_url('/find-care/') }), cityName);
      }).join('');
      grid.style.display = '';
      var empty = el('no-providers-msg');
      if (empty) empty.style.display = 'none';
    } else {
      showProviderMessage(
        "We're still growing in this area",
        'No providers are currently listed in ' + cityName + '. Try another location or request help.'
      );
    }
  }

  document.addEventListener('DOMContentLoaded', boot);
})();
