(function(){
  'use strict';

  /* ---------------------------------------------------------------------
     Theme (white <-> dark green) — persisted
  --------------------------------------------------------------------- */
  const root = document.documentElement;
  const savedTheme = localStorage.getItem('cs-theme');
  if (savedTheme === 'dark') root.setAttribute('data-theme', 'dark');

  const themeToggle = document.getElementById('themeToggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const isDark = root.getAttribute('data-theme') === 'dark';
      if (isDark) { root.removeAttribute('data-theme'); localStorage.setItem('cs-theme', 'light'); }
      else { root.setAttribute('data-theme', 'dark'); localStorage.setItem('cs-theme', 'dark'); }
      themeToggle.classList.remove('spin');
      void themeToggle.offsetWidth; // restart animation
      themeToggle.classList.add('spin');
      refreshChartTheme();
    });
  }

  /* ---------------------------------------------------------------------
     Top page-loading bar — brief progress flash on real navigations
  --------------------------------------------------------------------- */
  (function pageLoaderInit(){
    const bar = document.getElementById('pageLoader');
    if (!bar) return;
    // On arrival: play the bar out, then fade.
    requestAnimationFrame(() => {
      bar.classList.add('loading');
      setTimeout(() => { bar.classList.remove('loading'); bar.classList.add('done'); }, 260);
      setTimeout(() => { bar.classList.remove('done'); bar.style.width = ''; }, 620);
    });
    // On the way out: fire it again for internal same-tab navigations.
    document.addEventListener('click', (e) => {
      const a = e.target.closest('a[href]');
      if (!a) return;
      const href = a.getAttribute('href') || '';
      const isInternal = href && !href.startsWith('#') && !href.startsWith('http') && !a.target;
      if (isInternal) {
        bar.classList.remove('done');
        bar.classList.add('loading');
      }
    });
  })();

  /* ---------------------------------------------------------------------
     Button / nav ripple feedback
  --------------------------------------------------------------------- */
  document.addEventListener('click', (e) => {
    const host = e.target.closest('.btn, .icon-btn, .nav-item, .tab');
    if (!host || host.disabled) return;
    const rect = host.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height) * 1.4;
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
    host.appendChild(ripple);
    ripple.addEventListener('animationend', () => ripple.remove());
  });

  /* ---------------------------------------------------------------------
     Mobile sidebar
  --------------------------------------------------------------------- */
  const sidebar = document.getElementById('sidebar');
  const scrim = document.getElementById('sidebarScrim');
  const navToggle = document.getElementById('navToggle');
  function closeSidebar(){ sidebar?.classList.remove('open'); scrim?.classList.remove('open'); }
  navToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
    scrim?.classList.toggle('open');
  });
  scrim?.addEventListener('click', closeSidebar);

  /* ---------------------------------------------------------------------
     Animate ring gauges in on load
  --------------------------------------------------------------------- */
  function animateGauges(){
    document.querySelectorAll('[data-gauge] .gauge-fill').forEach((el, i) => {
      const target = el.getAttribute('data-offset');
      setTimeout(() => { el.style.strokeDashoffset = target; }, 80 + i * 60);
    });
  }
  animateGauges();

  /* ---------------------------------------------------------------------
     Reveal-on-load / on-scroll stagger for cards, panels, rows
  --------------------------------------------------------------------- */
  (function revealInit(){
    const groups = [
      document.querySelectorAll('.kpi-card'),
      document.querySelectorAll('.unit-card'),
      document.querySelectorAll('.panel'),
      document.querySelectorAll('.alert-item'),
      document.querySelectorAll('tbody tr'),
    ];
    const seen = new WeakSet();
    const io = ('IntersectionObserver' in window) ? new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }) : null;

    groups.forEach(list => {
      list.forEach((el, i) => {
        if (seen.has(el)) return;
        seen.add(el);
        el.style.setProperty('--reveal-delay', Math.min(i, 10) * 45 + 'ms');
        if (io) io.observe(el); else el.classList.add('is-visible');
      });
    });
    // Above-the-fold elements should already be visible without waiting on scroll —
    // IO fires immediately for anything already in the viewport on observe().
  })();

  /* ---------------------------------------------------------------------
     KPI count-up: animates any .kpi-card .kpi-value from 0 to its
     rendered number (no markup changes needed — reads the number in place)
  --------------------------------------------------------------------- */
  (function countUpInit(){
    const nodes = document.querySelectorAll('.kpi-card .kpi-value, .dh-metric strong');
    if (!nodes.length) return;
    const run = (el) => {
      const raw = el.textContent.trim();
      const match = raw.match(/-?[\d,]+(\.\d+)?/);
      if (!match) return;
      const numStr = match[0];
      const target = parseFloat(numStr.replace(/,/g, ''));
      if (isNaN(target)) return;
      const decimals = (numStr.split('.')[1] || '').length;
      const prefix = raw.slice(0, match.index);
      const suffix = raw.slice(match.index + numStr.length);
      const useComma = numStr.includes(',');
      el.setAttribute('data-counting', '1');
      const duration = 700;
      const start = performance.now();
      function frame(now){
        const p = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3);
        const val = target * eased;
        const formatted = decimals ? val.toFixed(decimals) : Math.round(val).toString();
        el.textContent = prefix + (useComma ? Number(formatted).toLocaleString() : formatted) + suffix;
        if (p < 1) requestAnimationFrame(frame);
        else { el.textContent = raw; el.removeAttribute('data-counting'); }
      }
      requestAnimationFrame(frame);
    };
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) { run(entry.target); io.unobserve(entry.target); }
        });
      }, { threshold: 0.3 });
      nodes.forEach(el => io.observe(el));
    } else {
      nodes.forEach(run);
    }
  })();

  /* ---------------------------------------------------------------------
     Topbar elevation on scroll
  --------------------------------------------------------------------- */
  (function scrollShadowInit(){
    const topbar = document.querySelector('.topbar');
    const scrollEl = document.querySelector('.main') || window;
    if (!topbar) return;
    const target = scrollEl === window ? window : scrollEl;
    const getY = () => scrollEl === window ? window.scrollY : scrollEl.scrollTop;
    const update = () => topbar.classList.toggle('is-scrolled', getY() > 4);
    target.addEventListener('scroll', update, { passive: true });
    update();
  })();

  /* ---------------------------------------------------------------------
     Back-to-top
  --------------------------------------------------------------------- */
  (function backToTopInit(){
    const btn = document.createElement('button');
    btn.className = 'back-to-top';
    btn.setAttribute('aria-label', 'Back to top');
    btn.setAttribute('data-tooltip', 'Back to top');
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    document.body.appendChild(btn);
    const content = document.querySelector('.content');
    btn.addEventListener('click', () => {
      (document.querySelector('.main') || window).scrollTo({ top: 0, behavior: 'smooth' });
    });
    const scrollHost = document.querySelector('.main') || window;
    const getY = () => scrollHost === window ? window.scrollY : scrollHost.scrollTop;
    const toggle = () => btn.classList.toggle('show', getY() > 480);
    scrollHost.addEventListener('scroll', toggle, { passive: true });
    toggle();
  })();

  /* ---------------------------------------------------------------------
     Search: clear button
  --------------------------------------------------------------------- */
  (function searchClearInit(){
    const wrap = document.getElementById('globalSearchWrap');
    const input = document.getElementById('globalSearch');
    const clearBtn = document.getElementById('searchClear');
    if (!wrap || !input || !clearBtn) return;
    const sync = () => wrap.classList.toggle('has-value', input.value.length > 0);
    input.addEventListener('input', sync);
    clearBtn.addEventListener('click', () => {
      input.value = '';
      input.dispatchEvent(new Event('input'));
      input.focus();
      sync();
    });
    sync();
  })();

  /* ---------------------------------------------------------------------
     User menu dropdown
  --------------------------------------------------------------------- */
  const userChip = document.getElementById('userChip');
  const userMenu = document.getElementById('userMenu');
  if (userChip && userMenu) {
    userChip.addEventListener('click', (e) => {
      e.stopPropagation();
      userMenu.classList.toggle('open');
    });
    document.addEventListener('click', () => userMenu.classList.remove('open'));
  }

  /* ---------------------------------------------------------------------
     Show/hide password toggles (login & signup)
  --------------------------------------------------------------------- */
  document.querySelectorAll('[data-pw-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.getAttribute('data-pw-toggle'));
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
    });
  });

  /* ---------------------------------------------------------------------
     Toasts
  --------------------------------------------------------------------- */
  const toastIcons = {
    ok: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    warn: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 9v4M12 16.5h.01" stroke="currentColor" stroke-width="2.4" fill="none" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
    crit: '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" fill="none"/></svg>',
  };
  window.csToast = function(message, kind = 'ok'){
    const stack = document.getElementById('toastStack');
    if (!stack) return;
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = `<span class="toast-icon ${kind}">${toastIcons[kind] || toastIcons.ok}</span><span>${message}</span><span class="toast-progress"></span>`;
    stack.appendChild(el);
    const remove = () => { el.classList.add('leaving'); setTimeout(() => el.remove(), 220); };
    const timer = setTimeout(remove, 3200);
    el.addEventListener('click', () => { clearTimeout(timer); remove(); });
  };

  /* ---------------------------------------------------------------------
     Generic modal open/close (data-modal-open="#id" / data-modal-close)
  --------------------------------------------------------------------- */
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.querySelector(btn.getAttribute('data-modal-open'));
      target?.classList.add('open');
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.modal-backdrop')?.classList.remove('open'));
  });
  document.querySelectorAll('.modal-backdrop').forEach(bd => {
    bd.addEventListener('click', (e) => { if (e.target === bd) bd.classList.remove('open'); });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open'));
  });

  /* ---------------------------------------------------------------------
     Live table / card search filter (data-search-target selector)
  --------------------------------------------------------------------- */
  const searchInput = document.getElementById('globalSearch');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      document.querySelectorAll('[data-searchable]').forEach(node => {
        const haystack = node.getAttribute('data-searchable').toLowerCase();
        node.style.display = (!q || haystack.includes(q)) ? '' : 'none';
      });
    });
  }

  /* ---------------------------------------------------------------------
     Filter chips / selects on units + maintenance + alerts pages
  --------------------------------------------------------------------- */
  document.querySelectorAll('[data-filter-select]').forEach(sel => {
    sel.addEventListener('change', applyFilters);
  });
  function applyFilters(){
    const filters = {};
    document.querySelectorAll('[data-filter-select]').forEach(sel => {
      if (sel.value) filters[sel.getAttribute('data-filter-select')] = sel.value.toLowerCase();
    });
    document.querySelectorAll('[data-searchable]').forEach(node => {
      let visible = true;
      for (const key in filters) {
        const val = (node.getAttribute('data-' + key) || '').toLowerCase();
        if (val !== filters[key]) { visible = false; break; }
      }
      node.style.display = visible ? '' : 'none';
    });
  }

  /* ---------------------------------------------------------------------
     Tabs
  --------------------------------------------------------------------- */
  document.querySelectorAll('.tabs').forEach(tabGroup => {
    const tabs = tabGroup.querySelectorAll('.tab');
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const panelSel = tab.getAttribute('data-tab-target');
        const parentPanel = tabGroup.closest('.panel');
        if (panelSel && parentPanel) {
          parentPanel.querySelectorAll('[data-tab-panel]').forEach(p => p.style.display = 'none');
          parentPanel.querySelector(panelSel).style.display = '';
        }
      });
    });
  });

  /* ---------------------------------------------------------------------
     Chart.js — shared theme + helpers
  --------------------------------------------------------------------- */
  const chartRegistry = [];

  function csColors(){
    const dark = root.getAttribute('data-theme') === 'dark';
    return {
      grid: dark ? 'rgba(234,243,238,.06)' : 'rgba(18,33,27,.06)',
      text: dark ? '#8fb3a0' : '#5e7469',
      forest: dark ? '#5edd99' : '#1b6b4c',
      forestSoft: dark ? 'rgba(94,221,153,.15)' : 'rgba(27,107,76,.10)',
      amber: '#c67c2e',
      rust: '#b4432d',
      mint: dark ? '#4e9575' : '#7fb79b',
    };
  }

  function refreshChartTheme(){
    const c = csColors();
    chartRegistry.forEach(ch => {
      ch.options.scales && Object.values(ch.options.scales).forEach(sc => {
        if (sc.grid) sc.grid.color = c.grid;
        if (sc.ticks) sc.ticks.color = c.text;
      });
      ch.data.datasets.forEach(ds => {
        if (ds._role === 'primary') { ds.borderColor = c.forest; ds.backgroundColor = c.forestSoft; }
      });
      ch.update();
    });
  }

  if (window.Chart) {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 11.5;
  }

  window.csLineChart = function(canvasId, labels, data, opts = {}){
    const canvas = document.getElementById(canvasId);
    if (!canvas || !window.Chart) return null;
    const c = csColors();
    const chart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: opts.label || 'Value',
          data,
          borderColor: c.forest,
          backgroundColor: c.forestSoft,
          borderWidth: 2.2,
          pointRadius: 0,
          pointHoverRadius: 4,
          tension: .35,
          fill: true,
          _role: 'primary',
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: c.forest === '#5edd99' ? '#0e3d2c' : '#0e3d2c',
            titleColor: '#eaf3ee', bodyColor: '#eaf3ee', padding: 10, cornerRadius: 8,
            displayColors: false,
          }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: c.text, maxTicksLimit: 6 } },
          y: { grid: { color: c.grid }, ticks: { color: c.text }, suggestedMin: opts.min, suggestedMax: opts.max }
        }
      }
    });
    chartRegistry.push(chart);
    return chart;
  };

  window.csBarChart = function(canvasId, labels, data, colors){
    const canvas = document.getElementById(canvasId);
    if (!canvas || !window.Chart) return null;
    const c = csColors();
    const chart = new Chart(canvas, {
      type: 'bar',
      data: { labels, datasets: [{ data, backgroundColor: colors || c.forest, borderRadius: 6, maxBarThickness: 28 }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { padding: 10, cornerRadius: 8 } },
        scales: {
          x: { grid: { display: false }, ticks: { color: c.text } },
          y: { grid: { color: c.grid }, ticks: { color: c.text }, beginAtZero: true }
        }
      }
    });
    chartRegistry.push(chart);
    return chart;
  };

  window.csDoughnut = function(canvasId, labels, data, colors){
    const canvas = document.getElementById(canvasId);
    if (!canvas || !window.Chart) return null;
    const chart = new Chart(canvas, {
      type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0, }] },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '72%',
        plugins: { legend: { display: false } }
      }
    });
    chartRegistry.push(chart);
    return chart;
  };

  /* ---------------------------------------------------------------------
     Live monitoring simulation — gently nudges live-tagged values
     so the dashboard feels like it's streaming real sensor data.
  --------------------------------------------------------------------- */
  function jitter(el, min, max, decimals){
    const cur = parseFloat(el.getAttribute('data-live-val'));
    let next = cur + (Math.random() - 0.5) * (max - min) * 0.05;
    next = Math.max(min, Math.min(max, next));
    el.setAttribute('data-live-val', next.toFixed(decimals));
    el.textContent = next.toFixed(decimals) + (el.getAttribute('data-suffix') || '');
  }

  function tickLive(){
    document.querySelectorAll('[data-live]').forEach(el => {
      const min = parseFloat(el.getAttribute('data-min') || '0');
      const max = parseFloat(el.getAttribute('data-max') || '100');
      const decimals = parseInt(el.getAttribute('data-decimals') || '1', 10);
      jitter(el, min, max, decimals);
    });
    // occasionally push a live point onto any registered sparkline
    chartRegistry.forEach(ch => {
      if (!ch._live) return;
      const ds = ch.data.datasets[0];
      const last = ds.data[ds.data.length - 1];
      const next = Math.max(0, last + (Math.random() - 0.5) * 1.4);
      ds.data.push(Number(next.toFixed(2)));
      ds.data.shift();
      ch.update('none');
    });
  }
  setInterval(tickLive, 3500);

  /* ---------------------------------------------------------------------
     Simple client-side "Log Maintenance" form → toast confirmation
     (frontend-only: no persistence layer wired up yet)
  --------------------------------------------------------------------- */
  const maintForm = document.getElementById('maintForm');
  if (maintForm) {
    maintForm.addEventListener('submit', (e) => {
      e.preventDefault();
      document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open'));
      csToast('Maintenance task logged successfully.', 'ok');
      maintForm.reset();
    });
  }

  const ackButtons = document.querySelectorAll('[data-ack-alert]');
  ackButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.alert-item');
      item?.style.setProperty('opacity', '.45');
      btn.textContent = 'Acknowledged';
      btn.disabled = true;
      csToast('Alert acknowledged.', 'ok');
    });
  });

})();
