/**
 * AdminNexus — script.js
 * Fitur: Sidebar toggle, Dark mode, Navigation, Chart, Table dengan pagination & sorting
 */

/* ============================================
   DATA DUMMY
   ============================================ */
const transactions = [
  { id: 'TRX-8821', user: 'Budi Santoso',   initials: 'BS', color: '#10b981', product: 'Paket Premium', amount: 1250000, status: 'success', date: '16 Apr 2025' },
  { id: 'TRX-8820', user: 'Sari Rahayu',    initials: 'SR', color: '#10b981', product: 'Langganan Bulanan', amount: 499000, status: 'success', date: '16 Apr 2025' },
  { id: 'TRX-8819', user: 'Dian Jaya',      initials: 'DJ', color: '#f59e0b', product: 'Paket Bisnis',   amount: 3750000, status: 'pending', date: '15 Apr 2025' },
  { id: 'TRX-8818', user: 'Maya Putri',     initials: 'MP', color: '#8b5cf6', product: 'Paket Starter',  amount: 199000,  status: 'success', date: '15 Apr 2025' },
  { id: 'TRX-8817', user: 'Rizky Hadi',     initials: 'RH', color: '#ef4444', product: 'Paket Premium',  amount: 1250000, status: 'failed',  date: '14 Apr 2025' },
  { id: 'TRX-8816', user: 'Ayu Lestari',    initials: 'AL', color: '#06b6d4', product: 'Langganan Tahunan', amount: 4999000, status: 'success', date: '14 Apr 2025' },
  { id: 'TRX-8815', user: 'Hendra Kurnia',  initials: 'HK', color: '#84cc16', product: 'Paket Bisnis',   amount: 3750000, status: 'pending', date: '13 Apr 2025' },
  { id: 'TRX-8814', user: 'Fitri Handayani',initials: 'FH', color: '#f97316', product: 'Paket Starter',  amount: 199000,  status: 'success', date: '13 Apr 2025' },
  { id: 'TRX-8813', user: 'Bayu Pratama',   initials: 'BP', color: '#10b981', product: 'Paket Premium',  amount: 1250000, status: 'success', date: '12 Apr 2025' },
  { id: 'TRX-8812', user: 'Dewi Anggraini', initials: 'DA', color: '#10b981', product: 'Langganan Bulanan', amount: 499000, status: 'failed',  date: '12 Apr 2025' },
  { id: 'TRX-8811', user: 'Fajar Nugroho',  initials: 'FN', color: '#8b5cf6', product: 'Paket Bisnis',   amount: 3750000, status: 'success', date: '11 Apr 2025' },
  { id: 'TRX-8810', user: 'Gita Permata',   initials: 'GP', color: '#f59e0b', product: 'Langganan Tahunan', amount: 4999000, status: 'pending', date: '11 Apr 2025' },
  { id: 'TRX-8809', user: 'Ilham Saputra',  initials: 'IS', color: '#ef4444', product: 'Paket Starter',  amount: 199000,  status: 'success', date: '10 Apr 2025' },
  { id: 'TRX-8808', user: 'Jeni Susanti',   initials: 'JS', color: '#06b6d4', product: 'Paket Premium',  amount: 1250000, status: 'success', date: '10 Apr 2025' },
  { id: 'TRX-8807', user: 'Kevin Adiputra',  initials: 'KA', color: '#84cc16', product: 'Paket Bisnis',  amount: 3750000, status: 'success', date: '09 Apr 2025' },
];

/* ============================================
   STATE
   ============================================ */
let state = {
  sidebarOpen: window.innerWidth > 900,
  sidebarCollapsed: false,
  darkMode: localStorage.getItem('theme') === 'dark',
  currentPage: 1,
  rowsPerPage: 8,
  sortCol: -1,
  sortAsc: true,
  searchQuery: '',
  filteredData: [...transactions],
};

/* ============================================
   DOM REFS
   ============================================ */
const sidebar      = document.getElementById('sidebar');
const overlay      = document.getElementById('overlay');
const mainWrapper  = document.getElementById('mainWrapper');
const sidebarToggle= document.getElementById('sidebarToggle');
const themeToggle  = document.getElementById('themeToggle');
const profileDropdown = document.getElementById('profileDropdown');
const profileTrigger  = document.getElementById('profileTrigger');
const dropdownMenu    = document.getElementById('dropdownMenu');
const tableBody    = document.getElementById('tableBody');
const pagination   = document.getElementById('pagination');
const tableInfo    = document.getElementById('tableInfo');
const tableSearch  = document.getElementById('tableSearch');

/* ============================================
   INIT
   ============================================ */
document.addEventListener('DOMContentLoaded', () => {
  applyTheme();
  initSidebar();
  initNavigation();
  initChart();
  renderTable();
  animateStatCounters();
  initEventListeners();
});

/* ============================================
   THEME
   ============================================ */
function applyTheme() {
  if (state.darkMode) {
    document.documentElement.setAttribute('data-theme', 'dark');
    themeToggle.querySelector('i').className = 'fa-solid fa-sun';
  } else {
    document.documentElement.removeAttribute('data-theme');
    themeToggle.querySelector('i').className = 'fa-solid fa-moon';
  }
}

themeToggle.addEventListener('click', () => {
  state.darkMode = !state.darkMode;
  localStorage.setItem('theme', state.darkMode ? 'dark' : 'light');
  applyTheme();
  rebuildChart();
});

/* ============================================
   SIDEBAR
   ============================================ */
function initSidebar() {
  if (window.innerWidth <= 900) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  }
}

sidebarToggle.addEventListener('click', () => {
  if (window.innerWidth <= 900) {
    // Mobile: slide in/out
    const isOpen = sidebar.classList.toggle('open');
    overlay.classList.toggle('active', isOpen);
  } else {
    // Desktop: collapse
    state.sidebarCollapsed = !state.sidebarCollapsed;
    document.body.classList.toggle('sidebar-collapsed', state.sidebarCollapsed);
  }
});

overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('active');
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 900) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  }
});

/* ============================================
   NAVIGATION
   ============================================ */
function initNavigation() {
  const navItems = document.querySelectorAll('.nav-item[data-page]');
  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const page = item.dataset.page;
      navigateTo(page, item);

      // Close on mobile
      if (window.innerWidth <= 900) {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
      }
    });
  });
}

function navigateTo(page, navItem) {
  // Update nav items
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  navItem.classList.add('active');

  // Switch pages
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById(`page-${page}`);
  if (target) target.classList.add('active');

  // Breadcrumb
  document.getElementById('breadcrumbCurrent').textContent = navItem.querySelector('span:not(.nav-badge):not(.nav-indicator)').textContent;
}

/* ============================================
   PROFILE DROPDOWN
   ============================================ */
profileTrigger.addEventListener('click', (e) => {
  e.stopPropagation();
  profileDropdown.classList.toggle('open');
});

document.addEventListener('click', () => {
  profileDropdown.classList.remove('open');
});

dropdownMenu.addEventListener('click', e => e.stopPropagation());

/* ============================================
   STAT COUNTER ANIMATION
   ============================================ */
function animateStatCounters() {
  const statValues = document.querySelectorAll('.stat-value[data-target]');
  statValues.forEach(el => {
    const target = parseInt(el.dataset.target);
    const prefix = el.dataset.prefix || '';
    const duration = 1200;
    const start = performance.now();

    function update(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(ease * target);
      el.textContent = prefix + formatNumber(current);
      if (progress < 1) requestAnimationFrame(update);
    }

    requestAnimationFrame(update);
  });
}

function formatNumber(n) {
  if (n >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'jt';
  return n.toLocaleString('id-ID');
}

/* ============================================
   CHART
   ============================================ */
let chart = null;

const chartData = {
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun'],
  data:   [185000, 202000, 195000, 225000, 210000, 242000],
};

function getChartColors() {
  const isDark = state.darkMode;
  return {
    line:   '#10b981',
    fill:   isDark ? 'rgba(16,185,129,0.12)' : 'rgba(16,185,129,0.08)',
    grid:   isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)',
    text:   isDark ? '#64748b' : '#94a3b8',
    point:  '#059669',
  };
}

function initChart() {
  const ctx = document.getElementById('revenueChart').getContext('2d');
  const colors = getChartColors();

  chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartData.labels,
      datasets: [{
        label: 'Pendapatan',
        data: chartData.data,
        borderColor: colors.line,
        backgroundColor: colors.fill,
        borderWidth: 2.5,
        pointBackgroundColor: colors.point,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
        fill: true,
        tension: 0.4,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { intersect: false, mode: 'index' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: state.darkMode ? '#1e293b' : '#0f172a',
          titleColor: '#94a3b8',
          bodyColor: '#f1f5f9',
          borderColor: 'rgba(255,255,255,0.1)',
          borderWidth: 1,
          padding: 12,
          callbacks: {
            label: (ctx) => `  Rp ${ctx.raw.toLocaleString('id-ID')}`,
          }
        }
      },
      scales: {
        x: {
          grid: { color: colors.grid, drawBorder: false },
          ticks: { color: colors.text, font: { family: 'Sora', size: 12 } },
        },
        y: {
          grid: { color: colors.grid, drawBorder: false },
          ticks: {
            color: colors.text,
            font: { family: 'JetBrains Mono', size: 11 },
            callback: (v) => 'Rp ' + (v / 1000) + 'k'
          },
        }
      }
    }
  });

  // Chart tab switching
  document.querySelectorAll('.chart-tab').forEach(tab => {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
      this.classList.add('active');
    });
  });
}

function rebuildChart() {
  if (chart) {
    const colors = getChartColors();
    chart.data.datasets[0].borderColor = colors.line;
    chart.data.datasets[0].backgroundColor = colors.fill;
    chart.data.datasets[0].pointBackgroundColor = colors.point;
    chart.options.scales.x.grid.color = colors.grid;
    chart.options.scales.y.grid.color = colors.grid;
    chart.options.scales.x.ticks.color = colors.text;
    chart.options.scales.y.ticks.color = colors.text;
    chart.options.plugins.tooltip.backgroundColor = state.darkMode ? '#1e293b' : '#0f172a';
    chart.update();
  }
}

/* ============================================
   TABLE
   ============================================ */
function renderTable() {
  const start = (state.currentPage - 1) * state.rowsPerPage;
  const end   = start + state.rowsPerPage;
  const pageData = state.filteredData.slice(start, end);

  tableBody.innerHTML = pageData.map(row => `
    <tr>
      <td><input type="checkbox" /></td>
      <td><span class="trans-id">${row.id}</span></td>
      <td>
        <div class="user-cell">
          <div class="activity-avatar" style="background:${row.color};width:28px;height:28px;font-size:11px">${row.initials}</div>
          ${row.user}
        </div>
      </td>
      <td>${row.product}</td>
      <td><span class="amount">Rp ${row.amount.toLocaleString('id-ID')}</span></td>
      <td>${badgeHTML(row.status)}</td>
      <td>${row.date}</td>
      <td>
        <div class="action-btns">
          <button class="action-btn" title="Lihat Detail"><i class="fa-solid fa-eye"></i></button>
          <button class="action-btn" title="Edit"><i class="fa-solid fa-pen"></i></button>
          <button class="action-btn danger" title="Hapus"><i class="fa-solid fa-trash"></i></button>
        </div>
      </td>
    </tr>
  `).join('');

  updatePagination();
  updateTableInfo(start, Math.min(end, state.filteredData.length));
}

function badgeHTML(status) {
  const map = {
    success: ['badge-success', 'Berhasil'],
    pending: ['badge-warning', 'Pending'],
    failed:  ['badge-danger',  'Gagal'],
  };
  const [cls, label] = map[status] || ['', status];
  return `<span class="badge ${cls}">${label}</span>`;
}

function updateTableInfo(start, end) {
  tableInfo.textContent = `Menampilkan ${start + 1}–${end} dari ${state.filteredData.length} data`;
}

function updatePagination() {
  const totalPages = Math.ceil(state.filteredData.length / state.rowsPerPage);
  const pages = [];

  pages.push(`<button class="page-btn" ${state.currentPage === 1 ? 'disabled' : ''} data-page="${state.currentPage - 1}">
    <i class="fa-solid fa-chevron-left" style="font-size:10px"></i>
  </button>`);

  for (let i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || Math.abs(i - state.currentPage) <= 1) {
      pages.push(`<button class="page-btn ${i === state.currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`);
    } else if (Math.abs(i - state.currentPage) === 2) {
      pages.push(`<button class="page-btn" disabled>…</button>`);
    }
  }

  pages.push(`<button class="page-btn" ${state.currentPage === totalPages ? 'disabled' : ''} data-page="${state.currentPage + 1}">
    <i class="fa-solid fa-chevron-right" style="font-size:10px"></i>
  </button>`);

  pagination.innerHTML = pages.join('');

  pagination.querySelectorAll('.page-btn:not([disabled])').forEach(btn => {
    btn.addEventListener('click', () => {
      state.currentPage = parseInt(btn.dataset.page);
      renderTable();
    });
  });
}

/* ============================================
   TABLE SEARCH
   ============================================ */
tableSearch.addEventListener('input', debounce(function() {
  state.searchQuery = this.value.toLowerCase();
  state.currentPage = 1;
  state.filteredData = transactions.filter(row =>
    row.user.toLowerCase().includes(state.searchQuery) ||
    row.id.toLowerCase().includes(state.searchQuery) ||
    row.product.toLowerCase().includes(state.searchQuery)
  );
  renderTable();
}, 250));

/* ============================================
   TABLE SORT
   ============================================ */
document.querySelectorAll('.sortable').forEach(th => {
  th.addEventListener('click', function() {
    const col = parseInt(this.dataset.col);
    if (state.sortCol === col) {
      state.sortAsc = !state.sortAsc;
    } else {
      state.sortCol = col;
      state.sortAsc = true;
    }

    const keys = ['id', 'user', 'product', 'amount', 'status', 'date'];
    state.filteredData.sort((a, b) => {
      const va = a[keys[col]];
      const vb = b[keys[col]];
      const cmp = typeof va === 'number'
        ? va - vb
        : String(va).localeCompare(String(vb), 'id');
      return state.sortAsc ? cmp : -cmp;
    });

    document.querySelectorAll('.sortable i').forEach(i => {
      i.className = 'fa-solid fa-sort';
    });
    this.querySelector('i').className = `fa-solid fa-sort-${state.sortAsc ? 'up' : 'down'}`;

    state.currentPage = 1;
    renderTable();
  });
});

/* ============================================
   CHECK ALL
   ============================================ */
document.getElementById('checkAll').addEventListener('change', function() {
  document.querySelectorAll('#tableBody input[type="checkbox"]').forEach(cb => {
    cb.checked = this.checked;
  });
});

/* ============================================
   GENERAL EVENT LISTENERS
   ============================================ */
function initEventListeners() {
  // Search shortcut (Cmd/Ctrl + K)
  document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      document.getElementById('searchInput').focus();
    }
    if (e.key === 'Escape') {
      profileDropdown.classList.remove('open');
    }
  });
}

/* ============================================
   UTILITY
   ============================================ */
function debounce(fn, delay) {
  let timer;
  return function(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}
