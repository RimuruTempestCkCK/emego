<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pemilik') {
    header('Location: ../login.php');
    exit;
}

// ── HELPERS ───────────────────────────────────────────────────
function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}
function formatRupiah(float $n): string
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// ── STAT CARDS ────────────────────────────────────────────────
$totalUserStmt   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pelanggan'");
$totalUser       = (int) $totalUserStmt->fetchColumn();

$pendapatanStmt  = $pdo->query("SELECT COALESCE(SUM(total_harga),0) FROM transaksi WHERE status = 'divalidasi'");
$totalPendapatan = (float) $pendapatanStmt->fetchColumn();

$trxStmt         = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status = 'divalidasi'");
$totalTrx        = (int) $trxStmt->fetchColumn();

$pendingStmt     = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE status = 'pending'");
$totalPending    = (int) $pendingStmt->fetchColumn();

// ── GRAFIK 6 BULAN ────────────────────────────────────────────
$graf6Stmt = $pdo->query("
    SELECT DATE_FORMAT(validated_at,'%b %Y') AS label,
           DATE_FORMAT(validated_at,'%Y-%m') AS bulan,
           COALESCE(SUM(total_harga),0)       AS pendapatan
    FROM transaksi
    WHERE status='divalidasi'
      AND validated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(validated_at,'%Y-%m')
    ORDER BY bulan ASC
");
$graf6 = $graf6Stmt->fetchAll();

// ── GRAFIK 12 BULAN ───────────────────────────────────────────
$graf12Stmt = $pdo->query("
    SELECT DATE_FORMAT(validated_at,'%b %Y') AS label,
           DATE_FORMAT(validated_at,'%Y-%m') AS bulan,
           COALESCE(SUM(total_harga),0)       AS pendapatan
    FROM transaksi
    WHERE status='divalidasi'
      AND validated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(validated_at,'%Y-%m')
    ORDER BY bulan ASC
");
$graf12 = $graf12Stmt->fetchAll();

// ── AKTIVITAS TERBARU ─────────────────────────────────────────
$aktivitasStmt = $pdo->query("
    SELECT t.kode_transaksi, t.total_harga, t.status, t.created_at,
           u.name AS nama_user
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    ORDER BY t.created_at DESC
    LIMIT 5
");
$aktivitas = $aktivitasStmt->fetchAll();

// ── TABEL TRANSAKSI TERKINI ───────────────────────────────────
$transaksiStmt = $pdo->query("
    SELECT t.id, t.kode_transaksi, t.total_harga, t.status, t.created_at,
           u.name AS nama_user,
           GROUP_CONCAT(p.nama_barang ORDER BY ti.id SEPARATOR ', ') AS produk_list
    FROM transaksi t
    LEFT JOIN users u           ON u.id  = t.user_id
    LEFT JOIN transaksi_item ti ON ti.transaksi_id = t.id
    LEFT JOIN produk p          ON p.id  = ti.produk_id
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 10
");
$transaksiList = $transaksiStmt->fetchAll();

// ── STOK KRITIS ───────────────────────────────────────────────
$stokKritisStmt = $pdo->query("
    SELECT nama_barang, jumlah, satuan, status
    FROM produk
    WHERE status IN ('terbatas','habis')
    ORDER BY jumlah ASC
    LIMIT 5
");
$stokKritis = $stokKritisStmt->fetchAll();

$avatarColors = ['#10b981','#6366f1','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#ec4899'];
// ambil data admin dari database
$userId = $_SESSION['user_id'] ?? null;

$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$namaAdminFull = $user['name'] ?? 'Pemilik';
$namaAdmin = explode(' ', $namaAdminFull)[0];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />
</head>

<body>

    <?php include '../layout/sidebar.php'; ?>

    <!-- OVERLAY (mobile) -->
    <div class="overlay" id="overlay"></div>

    <!-- MAIN WRAPPER -->
    <div class="main-wrapper" id="mainWrapper">

        <!-- NAVBAR -->
        <header class="navbar">
            <div class="navbar-left">
                <button class="btn-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="breadcrumb">
                    <span class="breadcrumb-root">E-MEGO</span>
                    <i class="fa-solid fa-chevron-right"></i>
                    <span class="breadcrumb-current" id="breadcrumbCurrent">Dashboard</span>
                </div>
            </div>

            <div class="navbar-center"></div>

            <div class="navbar-right">
                <button class="icon-btn" title="Mode Gelap" id="themeToggle">
                    <i class="fa-solid fa-moon"></i>
                </button>

                <div class="profile-dropdown" id="profileDropdown">
                    <button class="profile-trigger" id="profileTrigger">
                        <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'AD') ?></div>
                        <span class="profile-name"><?= htmlspecialchars($namaAdminFull) ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'AD') ?></div>
                            <div>
                                <p><?= htmlspecialchars($namaAdminFull) ?></p>
                                <small><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php" class="dropdown-item danger">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT AREA -->
        <main class="content" id="contentArea">

            <!-- PAGE: DASHBOARD -->
            <section class="page active" id="page-dashboard">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Dashboard</h1>
                        <p class="page-subtitle">Selamat datang kembali, <?= htmlspecialchars($namaAdminFull) ?> 👋</p>
                    </div>
                </div>

                <!-- STAT CARDS -->
                <div class="stats-grid">
                    <div class="stat-card" style="--card-accent: #6366f1">
                        <div class="stat-icon" style="background:rgba(99,102,241,.12);color:#6366f1">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="stat-body">
                            <span class="stat-label">Total Pelanggan</span>
                            <span class="stat-value"><?= number_format($totalUser) ?></span>
                            <span class="stat-change positive">
                                <i class="fa-solid fa-user-check"></i> Pengguna terdaftar
                            </span>
                        </div>
                    </div>

                    <div class="stat-card" style="--card-accent: #10b981">
                        <div class="stat-icon" style="background:rgba(16,185,129,.12);color:#10b981">
                            <i class="fa-solid fa-sack-dollar"></i>
                        </div>
                        <div class="stat-body">
                            <span class="stat-label">Total Pendapatan</span>
                            <span class="stat-value" style="font-size:.95rem"><?= formatRupiah($totalPendapatan) ?></span>
                            <span class="stat-change positive">
                                <i class="fa-solid fa-circle-check"></i> Transaksi divalidasi
                            </span>
                        </div>
                    </div>

                    <div class="stat-card" style="--card-accent: #f59e0b">
                        <div class="stat-icon" style="background:rgba(245,158,11,.12);color:#f59e0b">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </div>
                        <div class="stat-body">
                            <span class="stat-label">Transaksi Berhasil</span>
                            <span class="stat-value"><?= number_format($totalTrx) ?></span>
                            <span class="stat-change positive">
                                <i class="fa-solid fa-arrow-trend-up"></i> Sudah divalidasi
                            </span>
                        </div>
                    </div>

                    <div class="stat-card" style="--card-accent: #ef4444">
                        <div class="stat-icon" style="background:rgba(239,68,68,.12);color:#ef4444">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="stat-body">
                            <span class="stat-label">Transaksi Pending</span>
                            <span class="stat-value"><?= number_format($totalPending) ?></span>
                            <span class="stat-change <?= $totalPending > 0 ? 'negative' : 'positive' ?>">
                                <i class="fa-solid fa-<?= $totalPending > 0 ? 'triangle-exclamation' : 'circle-check' ?>"></i>
                                <?= $totalPending > 0 ? 'Menunggu validasi' : 'Semua tertangani' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- CHART + ACTIVITY -->
                <div class="dashboard-grid">
                    <div class="card chart-card">
                        <div class="card-header">
                            <h3>Tren Pendapatan</h3>
                            <div class="chart-tabs">
                                <button class="chart-tab active" id="tab6">6 Bulan</button>
                                <button class="chart-tab" id="tab12">1 Tahun</button>
                            </div>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <div class="card activity-card">
                        <div class="card-header">
                            <h3>Transaksi Terbaru</h3>
                            <a href="laporan_penjualan.php" class="link-sm">Lihat Semua</a>
                        </div>
                        <ul class="activity-list">
                            <?php if (empty($aktivitas)): ?>
                                <li class="activity-item" style="justify-content:center;color:var(--text-muted);font-size:.85rem">
                                    <i class="fa-solid fa-inbox" style="margin-right:.5rem;opacity:.4"></i> Belum ada transaksi.
                                </li>
                            <?php else: ?>
                                <?php foreach ($aktivitas as $idx => $a):
                                    $color = $avatarColors[$idx % count($avatarColors)];
                                    $diff  = time() - strtotime($a['created_at']);
                                    if ($diff < 3600)      $timeStr = round($diff / 60) . 'm lalu';
                                    elseif ($diff < 86400) $timeStr = round($diff / 3600) . 'j lalu';
                                    else                   $timeStr = date('d M', strtotime($a['created_at']));
                                    $stTxt = match ($a['status']) {
                                        'divalidasi' => '#10b981', 'ditolak' => '#ef4444', default => '#f59e0b'
                                    };
                                    $stLabel = match ($a['status']) {
                                        'divalidasi' => 'Divalidasi', 'ditolak' => 'Ditolak', default => 'Pending'
                                    };
                                ?>
                                    <li class="activity-item">
                                        <div class="activity-avatar" style="background:<?= $color ?>">
                                            <?= initials($a['nama_user'] ?? '?') ?>
                                        </div>
                                        <div class="activity-info">
                                            <span class="activity-name"><?= htmlspecialchars($a['nama_user'] ?? '—') ?></span>
                                            <span class="activity-desc">
                                                <?= htmlspecialchars($a['kode_transaksi']) ?> ·
                                                <strong><?= formatRupiah((float) $a['total_harga']) ?></strong>
                                                · <span style="color:<?= $stTxt ?>;font-weight:600"><?= $stLabel ?></span>
                                            </span>
                                        </div>
                                        <span class="activity-time"><?= $timeStr ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>

                        <?php if (!empty($stokKritis)): ?>
                            <div style="border-top:1px solid var(--border-color);padding:1rem 1.25rem 0.5rem">
                                <p style="font-size:.78rem;font-weight:600;color:var(--text-muted);margin:0 0 .6rem;display:flex;align-items:center;gap:.4rem">
                                    <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b"></i>
                                    Stok Perlu Perhatian
                                </p>
                                <?php foreach ($stokKritis as $sk):
                                    $skTxt = $sk['status'] === 'habis' ? '#ef4444' : '#f59e0b';
                                ?>
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.45rem;font-size:.82rem">
                                        <span style="font-weight:500"><?= htmlspecialchars($sk['nama_barang']) ?></span>
                                        <span style="color:<?= $skTxt ?>;font-family:'JetBrains Mono',monospace;font-weight:700">
                                            <?= number_format($sk['jumlah']) ?> <?= htmlspecialchars($sk['satuan']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- DATA TABLE -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Transaksi Terkini</h3>
                        <div class="table-controls">
                            <div class="search-mini">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" placeholder="Cari transaksi…" id="tableSearch" />
                            </div>
                            <select class="select-sm" id="statusFilter">
                                <option value="">Semua Status</option>
                                <option value="divalidasi">Divalidasi</option>
                                <option value="pending">Pending</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table" id="transactionTable">
                            <thead>
                                <tr>
                                    <th>Kode Transaksi</th>
                                    <th>Pelanggan</th>
                                    <th>Produk</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <?php if (empty($transaksiList)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3"></i>
                                            Belum ada transaksi.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksiList as $t):
                                        $stBg = match ($t['status']) {
                                            'divalidasi' => 'rgba(16,185,129,.15)',
                                            'ditolak'    => 'rgba(239,68,68,.15)',
                                            default      => 'rgba(245,158,11,.15)',
                                        };
                                        $stTxt = match ($t['status']) {
                                            'divalidasi' => '#10b981',
                                            'ditolak'    => '#ef4444',
                                            default      => '#f59e0b',
                                        };
                                        $stLabel = match ($t['status']) {
                                            'divalidasi' => 'Divalidasi',
                                            'ditolak'    => 'Ditolak',
                                            default      => 'Pending',
                                        };
                                    ?>
                                        <tr data-status="<?= $t['status'] ?>">
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:600">
                                                <?= htmlspecialchars($t['kode_transaksi']) ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:.6rem">
                                                    <div class="activity-avatar" style="background:#6366f1;flex-shrink:0">
                                                        <?= initials($t['nama_user'] ?? '?') ?>
                                                    </div>
                                                    <span style="font-size:.86rem;font-weight:500">
                                                        <?= htmlspecialchars($t['nama_user'] ?? '—') ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td style="font-size:.82rem;color:var(--text-muted);max-width:200px">
                                                <?= $t['produk_list']
                                                    ? htmlspecialchars(mb_strimwidth($t['produk_list'], 0, 50, '…'))
                                                    : '<span style="opacity:.4">—</span>' ?>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:700;color:#10b981">
                                                <?= formatRupiah((float) $t['total_harga']) ?>
                                            </td>
                                            <td>
                                                <span style="background:<?= $stBg ?>;color:<?= $stTxt ?>;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600">
                                                    <?= $stLabel ?>
                                                </span>
                                            </td>
                                            <td style="font-size:.82rem;color:var(--text-muted)">
                                                <?= date('d M Y', strtotime($t['created_at'])) ?>
                                                <span style="display:block;font-size:.74rem">
                                                    <?= date('H:i', strtotime($t['created_at'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <span class="table-info" id="tableInfo">
                            Menampilkan <?= count($transaksiList) ?> transaksi terbaru
                        </span>
                        <a href="kelola_transaksi.php" class="link-sm" style="font-size:.82rem">
                            Lihat Semua <i class="fa-solid fa-arrow-right" style="font-size:.75rem"></i>
                        </a>
                    </div>
                </div>
            </section>

            <!-- PAGE: USERS -->
            <section class="page" id="page-users">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Data User</h1>
                        <p class="page-subtitle">Kelola seluruh pengguna sistem</p>
                    </div>
                </div>
                <div class="card" style="padding:2rem;text-align:center;color:var(--text-muted)">
                    <i class="fa-solid fa-users" style="font-size:3rem;margin-bottom:1rem;opacity:.3"></i>
                    <p>Halaman Data User — Konten dapat dikembangkan di sini.</p>
                </div>
            </section>

            <!-- PAGE: REPORTS -->
            <section class="page" id="page-reports">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Laporan</h1>
                        <p class="page-subtitle">Ringkasan dan analitik bisnis</p>
                    </div>
                </div>
                <div class="card" style="padding:2rem;text-align:center;color:var(--text-muted)">
                    <i class="fa-solid fa-chart-line" style="font-size:3rem;margin-bottom:1rem;opacity:.3"></i>
                    <p>Halaman Laporan — Konten dapat dikembangkan di sini.</p>
                </div>
            </section>

            <!-- PAGE: SETTINGS -->
            <section class="page" id="page-settings">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Pengaturan</h1>
                        <p class="page-subtitle">Konfigurasi sistem dan akun</p>
                    </div>
                </div>
                <div class="card" style="padding:2rem;text-align:center;color:var(--text-muted)">
                    <i class="fa-solid fa-sliders" style="font-size:3rem;margin-bottom:1rem;opacity:.3"></i>
                    <p>Halaman Pengaturan — Konten dapat dikembangkan di sini.</p>
                </div>
            </section>

        </main>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="../js/script.js"></script>
    <script>
    (() => {
        // ── Data grafik dari PHP ──────────────────────────────
        const data6  = {
            labels : <?= json_encode(array_column($graf6,  'label')) ?>,
            values : <?= json_encode(array_map(fn($r) => (float)$r['pendapatan'], $graf6)) ?>
        };
        const data12 = {
            labels : <?= json_encode(array_column($graf12, 'label')) ?>,
            values : <?= json_encode(array_map(fn($r) => (float)$r['pendapatan'], $graf12)) ?>
        };

        // ── Deteksi tema ──────────────────────────────────────
        const isDark  = () => document.documentElement.getAttribute('data-theme') === 'dark';
        const gridClr = () => isDark() ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
        const txtClr  = () => isDark() ? '#94a3b8' : '#64748b';

        // ── Inisialisasi chart ────────────────────────────────
        const ctx = document.getElementById('revenueChart');
        let chart;

        function buildChart(d) {
            if (chart) chart.destroy();
            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: d.labels,
                    datasets: [{
                        label: 'Pendapatan',
                        data: d.values,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,.12)',
                        borderWidth: 2.5,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#6366f1',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: c => 'Rp ' + Number(c.parsed.y).toLocaleString('id-ID')
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: gridClr() },
                            ticks: { color: txtClr(), font: { family: 'Sora', size: 11 } }
                        },
                        y: {
                            grid: { color: gridClr() },
                            ticks: {
                                color: txtClr(),
                                font: { family: 'JetBrains Mono', size: 10 },
                                callback: v => 'Rp ' + (v >= 1000000
                                    ? (v / 1000000).toLocaleString('id-ID') + 'Jt'
                                    : (v / 1000).toLocaleString('id-ID') + 'K')
                            }
                        }
                    }
                }
            });
        }

        buildChart(data6);

        // ── Tab 6 / 12 bulan ─────────────────────────────────
        const tab6  = document.getElementById('tab6');
        const tab12 = document.getElementById('tab12');
        if (tab6 && tab12) {
            tab6.addEventListener('click', () => {
                tab6.classList.add('active'); tab12.classList.remove('active');
                buildChart(data6);
            });
            tab12.addEventListener('click', () => {
                tab12.classList.add('active'); tab6.classList.remove('active');
                buildChart(data12);
            });
        }

        // ── Update warna chart saat tema berubah ──────────────
        document.getElementById('themeToggle')?.addEventListener('click', () => {
            setTimeout(() => {
                if (!chart) return;
                chart.options.scales.x.grid.color = gridClr();
                chart.options.scales.x.ticks.color = txtClr();
                chart.options.scales.y.grid.color = gridClr();
                chart.options.scales.y.ticks.color = txtClr();
                chart.update();
            }, 100);
        });

        // ── Filter tabel ──────────────────────────────────────
        const searchInput  = document.getElementById('tableSearch');
        const statusSelect = document.getElementById('statusFilter');
        const rows         = document.querySelectorAll('#tableBody tr[data-status]');

        function filterTable() {
            const q  = (searchInput?.value ?? '').toLowerCase();
            const st = statusSelect?.value ?? '';
            rows.forEach(row => {
                const matchQ  = q  === '' || row.textContent.toLowerCase().includes(q);
                const matchSt = st === '' || row.dataset.status === st;
                row.style.display = matchQ && matchSt ? '' : 'none';
            });
        }

        searchInput?.addEventListener('input',  filterTable);
        statusSelect?.addEventListener('change', filterTable);
    })();
    </script>
</body>

</html>