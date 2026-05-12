<?php
session_start();
require_once __DIR__ . '/../config.php';

// Pastikan hanya pelanggan yang bisa akses
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pelanggan') {
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

$userId = $_SESSION['user_id'];

// Ambil data profil pelanggan
$stmtUser = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch();
$namaUserFull = $userData['name'] ?? 'Pelanggan';
$emailUser = $userData['email'] ?? '';

// ── STAT CARDS (Khusus Pelanggan) ─────────────────────────────
$totalPesananStmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE user_id = ?");
$totalPesananStmt->execute([$userId]);
$totalPesanan = (int) $totalPesananStmt->fetchColumn();

$pengeluaranStmt = $pdo->prepare("SELECT COALESCE(SUM(total_harga),0) FROM transaksi WHERE user_id = ? AND status = 'divalidasi'");
$pengeluaranStmt->execute([$userId]);
$totalPengeluaran = (float) $pengeluaranStmt->fetchColumn();

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE user_id = ? AND status = 'pending'");
$pendingStmt->execute([$userId]);
$totalPending = (int) $pendingStmt->fetchColumn();

$ditolakStmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE user_id = ? AND status = 'ditolak'");
$ditolakStmt->execute([$userId]);
$totalDitolak = (int) $ditolakStmt->fetchColumn();

// ── GRAFIK PENGELUARAN 6 BULAN (Opsional jika JS dipertahankan)
$graf6Stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS label,
           DATE_FORMAT(created_at,'%Y-%m') AS bulan,
           COALESCE(SUM(total_harga),0)       AS pendapatan
    FROM transaksi
    WHERE status='divalidasi' AND user_id = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY bulan ASC
");
$graf6Stmt->execute([$userId]);
$graf6 = $graf6Stmt->fetchAll();

// ── GRAFIK PENGELUARAN 12 BULAN ───────────────────────────────
$graf12Stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS label,
           DATE_FORMAT(created_at,'%Y-%m') AS bulan,
           COALESCE(SUM(total_harga),0)       AS pendapatan
    FROM transaksi
    WHERE status='divalidasi' AND user_id = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at,'%Y-%m')
    ORDER BY bulan ASC
");
$graf12Stmt->execute([$userId]);
$graf12 = $graf12Stmt->fetchAll();

// ── AKTIVITAS TERBARU (5 Transaksi Terakhir Pelanggan) ────────
$aktivitasStmt = $pdo->prepare("
    SELECT kode_transaksi, total_harga, status, created_at
    FROM transaksi 
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$aktivitasStmt->execute([$userId]);
$aktivitas = $aktivitasStmt->fetchAll();

// ── TABEL TRANSAKSI TERKINI ───────────────────────────────────
$transaksiStmt = $pdo->prepare("
    SELECT t.id, t.kode_transaksi, t.total_harga, t.status, t.created_at,
           GROUP_CONCAT(p.nama_barang ORDER BY ti.id SEPARATOR ', ') AS produk_list
    FROM transaksi t
    LEFT JOIN transaksi_item ti ON ti.transaksi_id = t.id
    LEFT JOIN produk p          ON p.id  = ti.produk_id
    WHERE t.user_id = ?
    GROUP BY t.id
    ORDER BY t.created_at DESC
    LIMIT 10
");
$transaksiStmt->execute([$userId]);
$transaksiList = $transaksiStmt->fetchAll();

$avatarColors = ['#10b981','#6366f1','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#ec4899'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Dashboard Pelanggan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />
</head>

<body>

    <?php include '../layout/sidebar.php'; ?>

    <div class="overlay" id="overlay"></div>

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
                        <div class="user-avatar"><?= initials($namaUserFull) ?></div>
                        <span class="profile-name"><?= htmlspecialchars($namaUserFull) ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-avatar"><?= initials($namaUserFull) ?></div>
                            <div>
                                <p><?= htmlspecialchars($namaUserFull) ?></p>
                                <small><?= htmlspecialchars($emailUser) ?></small>
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

            <section class="page active" id="page-dashboard">
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Dashboard Pelanggan</h1>
                        <p class="page-subtitle">Selamat datang kembali, <?= htmlspecialchars(explode(' ', $namaUserFull)[0]) ?> 👋 Berikut ringkasan aktivitas belanja Anda.</p>
                    </div>
                </div>

                <!-- STAT CARDS -->
                <div class="stats-grid">
                    <div class="stat-card" style="--card-accent: #6366f1">
                        <div class="stat-icon" style="background:rgba(99,102,241,.12);color:#6366f1">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <div class="stat-body">
                            <span class="stat-label">Total Pesanan</span>
                            <span class="stat-value"><?= number_format($totalPesanan) ?></span>
                            <span class="stat-change positive">
                                <i class="fa-solid fa-cart-arrow-down"></i> Seluruh transaksi
                            </span>
                        </div>
                    </div>

                    <div class="stat-card" style="--card-accent: #10b981">
                        <div class="stat-icon" style="background:rgba(16,185,129,.12);color:#10b981">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                        <div class="stat-body">
                            <span class="stat-label">Total Pengeluaran</span>
                            <span class="stat-value" style="font-size:.95rem"><?= formatRupiah($totalPengeluaran) ?></span>
                            <span class="stat-change positive">
                                <i class="fa-solid fa-circle-check"></i> Transaksi divalidasi
                            </span>
                        </div>
                    </div>

                    <div class="stat-card" style="--card-accent: #f59e0b">
                        <div class="stat-icon" style="background:rgba(245,158,11,.12);color:#f59e0b">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div class="stat-body">
                            <span class="stat-label">Menunggu Validasi</span>
                            <span class="stat-value"><?= number_format($totalPending) ?></span>
                            <span class="stat-change <?= $totalPending > 0 ? 'negative' : 'positive' ?>">
                                <i class="fa-solid fa-<?= $totalPending > 0 ? 'triangle-exclamation' : 'circle-check' ?>"></i>
                                <?= $totalPending > 0 ? 'Pesanan diproses' : 'Tidak ada pesanan tertunda' ?>
                            </span>
                        </div>
                    </div>

                    <div class="stat-card" style="--card-accent: #ef4444">
                        <div class="stat-icon" style="background:rgba(239,68,68,.12);color:#ef4444">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </div>
                        <div class="stat-body">
                            <span class="stat-label">Pesanan Ditolak</span>
                            <span class="stat-value"><?= number_format($totalDitolak) ?></span>
                            <span class="stat-change <?= $totalDitolak > 0 ? 'negative' : 'positive' ?>">
                                <i class="fa-solid fa-<?= $totalDitolak > 0 ? 'xmark' : 'check' ?>"></i>
                                <?= $totalDitolak > 0 ? 'Pesanan dibatalkan' : 'Tidak ada pesanan ditolak' ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- CHART + ACTIVITY -->
                <div class="dashboard-grid">
                    <div class="card chart-card">
                        <div class="card-header">
                            <h3>Tren Pengeluaran</h3>
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
                            <a href="riwayat.php" class="link-sm">Lihat Semua</a>
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
                                        'divalidasi' => 'Berhasil', 'ditolak' => 'Ditolak', default => 'Diproses'
                                    };
                                ?>
                                    <li class="activity-item">
                                        <div class="activity-avatar" style="background:<?= $color ?>">
                                            <i class="fa-solid fa-receipt" style="color:#fff;"></i>
                                        </div>
                                        <div class="activity-info">
                                            <span class="activity-name"><?= htmlspecialchars($a['kode_transaksi']) ?></span>
                                            <span class="activity-desc">
                                                <strong><?= formatRupiah((float) $a['total_harga']) ?></strong>
                                                · <span style="color:<?= $stTxt ?>;font-weight:600"><?= $stLabel ?></span>
                                            </span>
                                        </div>
                                        <span class="activity-time"><?= $timeStr ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- DATA TABLE -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Daftar Transaksi Anda</h3>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table" id="transactionTable">
                            <thead>
                                <tr>
                                    <th>Kode Transaksi</th>
                                    <th>Produk</th>
                                    <th>Total Harga</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transaksiList)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem">Belum ada riwayat transaksi.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksiList as $t): 
                                         $stBadge = match($t['status']) {'divalidasi'=>'#10b981','ditolak'=>'#ef4444',default=>'#f59e0b'};
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($t['kode_transaksi']) ?></strong></td>
                                        <td><small><?= htmlspecialchars($t['produk_list'] ?? '-') ?></small></td>
                                        <td><?= formatRupiah((float) $t['total_harga']) ?></td>
                                        <td><span style="color:<?= $stBadge ?>;font-weight:600"><?= ucfirst($t['status']) ?></span></td>
                                        <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- Script Chart & Dashboard -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="../js/script.js"></script>
    
    <!-- Pass PHP data ke script.js jika file js Anda mengharapkannya -->
    <script>
        // Data chart disiapkan jika script.js memerlukannya untuk merender Chart.js
        const chartData6 = <?= json_encode($graf6) ?>;
        const chartData12 = <?= json_encode($graf12) ?>;
    </script>
</body>
</html>