(function () {
  'use strict';

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, ch => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[ch]));
  }

  function setText(sel, text) {
    document.querySelectorAll(sel).forEach(el => { el.textContent = text; });
  }

  function priceLabel(pkg) {
    if (pkg.unit === 'covered') return '<span class="package-price-covered">Medicare covered</span>';
    return `<span class="package-price-val">${pkg.price.toLocaleString()}</span><span class="package-price-unit">${pkg.unit}</span>`;
  }

  function renderPackage(pkg) {
    return `
      <div class="package-card${pkg.popular ? ' package-card--popular' : ''}">
        ${pkg.popular ? '<span class="package-popular-badge">Most Popular</span>' : ''}
        <div class="package-tier-label">${escapeHtml(pkg.tier)}</div>
        <div class="package-name">${escapeHtml(pkg.name)}</div>
        <div class="package-price">${priceLabel(pkg)}</div>
        <ul class="package-features">
          ${pkg.features.map(f => `<li>${escapeHtml(f)}</li>`).join('')}
        </ul>
        <button type="button" class="package-cta" data-open-form-modal>Request this package</button>
      </div>
    `;
  }

  function renderReview(r) {
    const stars = '★'.repeat(r.rating) + '☆'.repeat(5 - r.rating);
    return `
      <div class="review-card">
        <div class="review-header">
          <div class="review-author">${escapeHtml(r.author)}</div>
          <div class="review-date">${escapeHtml(r.date)}</div>
        </div>
        <div class="review-stars">${stars}</div>
        <div class="review-text">"${escapeHtml(r.text)}"</div>
      </div>
    `;
  }

  function renderCrossSell(categories, cityKey) {
    return categories.map(c => `
      <a class="cross-sell-card" href="category.html?type=${encodeURIComponent(c.key)}&city=${encodeURIComponent(cityKey)}">
        <div class="cross-sell-icon">${c.icon}</div>
        <div class="cross-sell-body">
          <div class="cross-sell-name">${escapeHtml(c.name)}</div>
          <div class="cross-sell-meta">Explore ${escapeHtml(c.name.toLowerCase())} in your area</div>
        </div>
        <span class="cross-sell-arrow">→</span>
      </a>
    `).join('');
  }

  function boot() {
    const D = window.ECM_DATA;
    if (!D) return;

    const id = new URLSearchParams(location.search).get('id');
    const provider = id ? D.getProvider(id) : null;

    if (!provider) {
      // Fallback: send user to the search page.
      document.querySelector('.provider-hero-main').innerHTML =
        '<h1 class="provider-hero-title">Provider not found</h1>' +
        '<p class="provider-hero-about">This provider may have been removed. <a href="search.html" style="color:var(--sage);font-weight:600">Browse all providers →</a></p>';
      return;
    }

    const category = D.categories[provider.category];
    const city = D.cities[provider.city];

    // Title + breadcrumb
    document.title = `${provider.name} | ElderCareMatters`;
    setText('#breadcrumb-current', provider.name);
    const breadCat = document.getElementById('breadcrumb-category');
    if (breadCat) {
      breadCat.textContent = category.name;
      breadCat.href = `category.html?type=${encodeURIComponent(category.key)}&city=${encodeURIComponent(city.key)}`;
    }

    // Hero
    const logo = document.getElementById('provider-logo');
    logo.textContent = provider.initials;
    logo.style.background = provider.color;

    setText('#provider-name', provider.name);
    setText('#provider-name-short', provider.name);
    setText('#cta-provider-name', provider.name);
    setText('#provider-rating', provider.rating.toFixed(1));
    setText('#provider-reviews', `(${provider.reviews} reviews)`);
    setText('#provider-city', `${city.name}, ${city.state}`);
    setText('#provider-category', category.name);
    setText('#provider-tagline', provider.tagline);
    setText('#provider-about', provider.about);
    setText('#cross-category', category.name.toLowerCase());

    const tierEl = document.getElementById('provider-tier');
    tierEl.textContent = provider.tier;
    tierEl.className = `provider-tier-badge tier-${provider.tier}`;
    tierEl.style.position = 'static';

    document.getElementById('provider-specialties').innerHTML =
      (provider.specialties || []).map(s => `<span class="specialty-chip">${escapeHtml(s)}</span>`).join('');

    setText('#side-response', provider.response.replace('Responds within ', ''));
    // Fake years-in-business stat: pull a sensible number from about text where possible; else default.
    const yearsMatch = (provider.about.match(/(\d{2,3})\s+years?/i) || [])[1];
    setText('#side-years', yearsMatch ? `${yearsMatch}+` : '10+');

    setText('#reviews-rating', provider.rating.toFixed(1));
    setText('#reviews-count', provider.reviews);

    // Packages
    document.getElementById('package-grid').innerHTML = provider.packages.map(renderPackage).join('');

    // Reviews
    document.getElementById('review-grid').innerHTML = (provider.reviews_list || []).map(renderReview).join('');

    // Cross-sell (related categories)
    const cross = D.getCrossSell(category.key);
    document.getElementById('cross-sell-grid').innerHTML = renderCrossSell(cross, city.key);

    // Wire newly-rendered "Request this package" buttons to open the form modal.
    document.querySelectorAll('.package-cta[data-open-form-modal]').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('formModal').classList.add('active');
      });
    });
  }

  document.addEventListener('DOMContentLoaded', boot);
})();
