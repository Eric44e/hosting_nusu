/* ============================================================
   ElectroServe ERP — Main JS
   ============================================================ */

// ── AJAX Helper ──────────────────────────────────────────────
const Ajax = {
  async post(url, data = {}, isFormData = false) {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };
    let body;
    if (isFormData) {
      body = data;
    } else {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(data);
    }
    const res = await fetch(url, { method: 'POST', headers, body });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Invalid JSON response:', text);
      throw new Error('Invalid server response');
    }
  },
  async get(url, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`${url}${qs ? '?' + qs : ''}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('Invalid JSON response:', text);
      throw new Error('Invalid server response');
    }
  }
};

// ── Debounce Helper ──────────────────────────────────────────
function debounce(fn, delay = 300) {
  let timeoutId;
  return function(...args) {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => fn.apply(this, args), delay);
  };
}

// ── SweetAlert Helpers ───────────────────────────────────────
const Notify = {
  _swalBase: {
    background: '#1a2235',
    color: '#e2e8f0',
    confirmButtonColor: '#1a6cff',
    cancelButtonColor: '#374151',
  },
  success(title, text = '') {
    return Swal.fire({ ...this._swalBase, icon: 'success', title, text, timer: 2000, showConfirmButton: false });
  },
  error(title, text = '') {
    return Swal.fire({ ...this._swalBase, icon: 'error', title, text });
  },
  warning(title, text = '') {
    return Swal.fire({ ...this._swalBase, icon: 'warning', title, text });
  },
  async confirm(title, text = '', confirmText = 'Yes, proceed') {
    const r = await Swal.fire({
      ...this._swalBase, icon: 'question', title, text,
      showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: 'Cancel'
    });
    return r.isConfirmed;
  },
  async confirmDelete(name = 'this record') {
    const r = await Swal.fire({
      ...this._swalBase, icon: 'warning',
      title: 'Are you sure?',
      text: `You are about to delete ${name}. This cannot be undone.`,
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      confirmButtonColor: '#ef4444',
      cancelButtonText: 'Cancel'
    });
    return r.isConfirmed;
  },
  loading(title = 'Processing...') {
    Swal.fire({ ...this._swalBase, title, allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });
  },
  close() { Swal.close(); }
};

// ── Sidebar Toggle ───────────────────────────────────────────
(function initSidebar() {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (!toggle || !sidebar) return;

  const collapsed = localStorage.getItem('sidebar_collapsed') === '1';
  if (collapsed) { document.body.classList.add('sidebar-collapsed'); sidebar.classList.add('collapsed'); }

  toggle.addEventListener('click', () => {
    const isNowCollapsed = document.body.classList.toggle('sidebar-collapsed');
    sidebar.classList.toggle('collapsed', isNowCollapsed);
    localStorage.setItem('sidebar_collapsed', isNowCollapsed ? '1' : '0');
    // Mobile overlay
    if (window.innerWidth <= 768) {
      sidebar.classList.toggle('mobile-open');
      document.body.classList.remove('sidebar-collapsed');
      sidebar.classList.remove('collapsed');
    }
  });

  // Nav group toggles
  document.querySelectorAll('.nav-group-toggle').forEach(el => {
    el.addEventListener('click', () => {
      const group = el.closest('.nav-group');
      group.classList.toggle('open');
    });
  });
})();

// ── Global Search shortcut ───────────────────────────────────
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === '/') {
    e.preventDefault();
    document.getElementById('globalSearch')?.focus();
  }
});

// ── Modal helpers ────────────────────────────────────────────
const Modal = {
  open(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
  },
  close(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
  },
  closeAll() {
    document.querySelectorAll('.modal-overlay.open').forEach(el => {
      el.classList.remove('open');
    });
    document.body.style.overflow = '';
  }
};

// Close modal on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) Modal.closeAll();
  if (e.target.classList.contains('modal-close')) Modal.closeAll();
});

// ── Logout handler ───────────────────────────────────────────
document.getElementById('logoutBtn')?.addEventListener('click', async function(e) {
  e.preventDefault();
  const confirmed = await Notify.confirm('Sign out?', 'You will be returned to the login page.', 'Sign Out');
  if (confirmed) window.location.href = 'logout.php';
});

// ── Form submit via AJAX (auto-bind forms with data-ajax) ────
document.querySelectorAll('form[data-ajax]').forEach(form => {
  let isSubmitting = false;
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    if (isSubmitting) return; // Prevent double submission
    isSubmitting = true;
    const btn = form.querySelector('[type=submit]');
    const originalText = btn?.innerHTML;
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner" style="display:inline-block"></span>'; }
    try {
      const fd = new FormData(form);
      const res = await Ajax.post(form.action || window.location.href, fd, true);
      if (res.success) {
        Notify.success('Success!', res.message);
        if (res.redirect) setTimeout(() => window.location.href = res.redirect, 1200);
        if (res.reload)   setTimeout(() => window.location.reload(), 1200);
        if (res.closeModal) Modal.closeAll();
        if (res.callback && window[res.callback]) window[res.callback](res);
      } else {
        Notify.error('Error', res.message);
      }
    } catch (err) {
      Notify.error('Network Error', 'Please check your connection.');
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = originalText; }
      setTimeout(() => { isSubmitting = false; }, 500); // Brief cooldown
    }
  });
});

// ── Donut chart SVG builder ──────────────────────────────────
function buildDonut(container, segments, totalLabel = '') {
  const size = 140, cx = size / 2, cy = size / 2, r = 52, strokeW = 20;
  const circumference = 2 * Math.PI * r;
  let offset = 0;
  const total = segments.reduce((s, seg) => s + seg.value, 0);
  let svgPaths = '';
  segments.forEach(seg => {
    const frac = total ? seg.value / total : 0;
    const dash = circumference * frac;
    const gap  = circumference - dash;
    svgPaths += `<circle cx="${cx}" cy="${cy}" r="${r}"
      fill="none" stroke="${seg.color}" stroke-width="${strokeW}"
      stroke-dasharray="${dash.toFixed(2)} ${gap.toFixed(2)}"
      stroke-dashoffset="${(-offset).toFixed(2)}"
      stroke-linecap="round" transform="rotate(-90 ${cx} ${cy})"/>`;
    offset += dash;
  });
  container.innerHTML = `
    <svg viewBox="0 0 ${size} ${size}" width="${size}" height="${size}">${svgPaths}</svg>
    <div class="donut-center">
      <div class="dc-num">${total.toLocaleString()}</div>
      <div class="dc-label">${totalLabel}</div>
    </div>`;
}

// ── Mini sparkline SVG ───────────────────────────────────────
function buildSparkline(container, data, color = '#1a6cff') {
  if (!data || !data.length) return;
  const w = 120, h = 36, pad = 2;
  const max = Math.max(...data), min = Math.min(...data);
  const range = max - min || 1;
  const pts = data.map((v, i) => {
    const x = pad + (i / (data.length - 1)) * (w - pad * 2);
    const y = h - pad - ((v - min) / range) * (h - pad * 2);
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  });
  const area = `M${pts[0]} ` + pts.slice(1).map(p => `L${p}`).join(' ') +
    ` L${w - pad},${h} L${pad},${h} Z`;
  container.innerHTML = `<svg viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" preserveAspectRatio="none">
    <defs><linearGradient id="sg${color.replace('#','')}" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="${color}" stop-opacity=".3"/>
      <stop offset="100%" stop-color="${color}" stop-opacity="0"/>
    </linearGradient></defs>
    <path d="${area}" fill="url(#sg${color.replace('#','')})" />
    <polyline points="${pts.join(' ')}" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>`;
}

// ── Number counter animation ─────────────────────────────────
function animateCounter(el, target, prefix = '', suffix = '', duration = 1000) {
  const start = 0, startTime = performance.now();
  function step(ts) {
    const progress = Math.min((ts - startTime) / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3);
    const current = Math.round(start + (target - start) * ease);
    el.textContent = prefix + current.toLocaleString() + suffix;
    if (progress < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

// ── Bar chart SVG ────────────────────────────────────────────
function buildBarChart(container, data, labels, color = '#1a6cff') {
  const w = container.clientWidth || 400, h = 160, pad = {l:30,r:10,t:10,b:30};
  const max = Math.max(...data) || 1;
  const bw = Math.max(4, (w - pad.l - pad.r) / data.length * 0.6);
  let bars = '', xlabels = '';
  data.forEach((v, i) => {
    const bh = ((v / max) * (h - pad.t - pad.b)) || 2;
    const x = pad.l + (i + .5) * (w - pad.l - pad.r) / data.length - bw / 2;
    const y = h - pad.b - bh;
    bars += `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${bw}" height="${bh.toFixed(1)}" rx="3" fill="${color}" opacity=".8"/>`;
    if (labels && labels[i]) {
      xlabels += `<text x="${(x + bw/2).toFixed(1)}" y="${h - 4}" text-anchor="middle" font-size="9" fill="#6b7a99">${labels[i]}</text>`;
    }
  });
  container.innerHTML = `<svg viewBox="0 0 ${w} ${h}" width="100%" height="${h}">${bars}${xlabels}</svg>`;
}

// ── Utility: format currency ─────────────────────────────────
function fmt(n) { return '$' + Number(n).toLocaleString('en-US', {minimumFractionDigits:2,maximumFractionDigits:2}); }

// ── Datepicker placeholder ───────────────────────────────────
document.querySelectorAll('input[type=date]').forEach(el => {
  if (!el.value) el.valueAsDate = new Date();
});

// ── Table search filter ──────────────────────────────────────
function tableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}
