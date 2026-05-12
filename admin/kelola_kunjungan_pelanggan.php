<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$alert     = '';
$alertType = '';

// ── SETUJUI KUNJUNGAN ──────────────────────────────────────
if (isset($_GET['setujui'])) {
    $kunjunganId = (int) $_GET['setujui'];
    $stmtCek = $pdo->prepare("SELECT id FROM kunjungan WHERE id = ? AND status = 'pending'");
    $stmtCek->execute([$kunjunganId]);
    
    if ($stmtCek->fetch()) {
        $stmtSetujui = $pdo->prepare("UPDATE kunjungan SET status = 'approved' WHERE id = ?");
        $stmtSetujui->execute([$kunjunganId]);
        $alert     = 'Kunjungan #' . $kunjunganId . ' telah disetujui.';
        $alertType = 'success';
    } else {
        $alert     = 'Kunjungan tidak ditemukan atau sudah diproses.';
        $alertType = 'danger';
    }
}

// ── TOLAK KUNJUNGAN ────────────────────────────────────────
if (isset($_GET['tolak'])) {
    $kunjunganId = (int) $_GET['tolak'];
    $stmtCek = $pdo->prepare("SELECT id FROM kunjungan WHERE id = ? AND status = 'pending'");
    $stmtCek->execute([$kunjunganId]);
    
    if ($stmtCek->fetch()) {
        $stmtTolak = $pdo->prepare("UPDATE kunjungan SET status = 'rejected' WHERE id = ?");
        $stmtTolak->execute([$kunjunganId]);
        $alert     = 'Kunjungan #' . $kunjunganId . ' telah ditolak.';
        $alertType = 'success';
    } else {
        $alert     = 'Kunjungan tidak ditemukan atau sudah diproses.';
        $alertType = 'danger';
    }
}

// ── FILTER & PAGINATION ────────────────────────────────────
$search       = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$limit        = 10;
$offset       = ($page - 1) * $limit;

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where   .= " AND (nama_pengunjung LIKE ? OR email LIKE ? OR tujuan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$validStatuses = ['pending', 'approved', 'rejected'];
if (in_array($statusFilter, $validStatuses)) {
    $where   .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM kunjungan $where");
$totalStmt->execute($params);
$total     = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

$dataStmt = $pdo->prepare("
    SELECT id, nama_pengunjung, email, tanggal_kunjungan, shift, jam, jumlah_orang, tujuan, status, created_at
    FROM kunjungan
    $where
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$kunjungan = $dataStmt->fetchAll();

// ── Statistik ──────────────────────────────────────────────
$stmtStat = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'pending')  AS pending,
        SUM(status = 'approved') AS approved,
        SUM(status = 'rejected') AS rejected
    FROM kunjungan
");
$stat = $stmtStat->fetch();

// Helper functions
function badgeStyle(string $status): string {
    return match($status) {
        'pending'  => 'background:rgba(245,158,11,.15);color:#f59e0b',
        'approved' => 'background:rgba(16,185,129,.15);color:#10b981',
        'rejected' => 'background:rgba(239,68,68,.15);color:#ef4444',
        default    => 'background:rgba(100,116,139,.15);color:#64748b',
    };
}

function statusIcon(string $status): string {
    return match($status) {
        'pending'  => 'fa-clock',
        'approved' => 'fa-circle-check',
        'rejected' => 'fa-circle-xmark',
        default    => 'fa-circle',
    };
}

function statusLabel(string $status): string {
    return match($status) {
        'pending'  => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        default    => ucfirst($status),
    };
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Kelola Kunjungan Pelanggan</title>
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
                    <span class="breadcrumb-current">Kelola Kunjungan Pelanggan</span>
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
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'AD') ?></div>
                            <div>
                                <p><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></p>
                                <small><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <!-- <a href="#" class="dropdown-item"><i class="fa-solid fa-user"></i> Profil Saya</a>
                        <a href="#" class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan</a> -->
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php" class="dropdown-item danger"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT -->
        <main class="content" id="contentArea">
            <section class="page active">

                <div class="page-header">
                    <div>
                        <h1 class="page-title">Kelola Kunjungan Pelanggan</h1>
                        <p class="page-subtitle">Validasi dan kelola permintaan booking kunjungan dari pelanggan</p>
                    </div>
                </div>

                <!-- ALERT -->
                <?php if ($alert): ?>
                    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?>" id="alertBox">
                        <i class="fa-solid fa-<?= $alertType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($alert) ?>
                    </div>
                <?php endif; ?>

                <!-- STATISTIK -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem">
                    <div class="card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem">
                        <div style="width:42px;height:42px;border-radius:10px;background:rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-calendar-days" style="color:var(--primary-color)"></i>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0">Total Kunjungan</p>
                            <p style="font-weight:700;font-size:1.2rem;margin:0;font-family:'JetBrains Mono',monospace"><?= $stat['total'] ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem">
                        <div style="width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-clock" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0">Menunggu</p>
                            <p style="font-weight:700;font-size:1.2rem;margin:0;font-family:'JetBrains Mono',monospace"><?= $stat['pending'] ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem">
                        <div style="width:42px;height:42px;border-radius:10px;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-circle-check" style="color:#10b981"></i>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0">Disetujui</p>
                            <p style="font-weight:700;font-size:1.2rem;margin:0;font-family:'JetBrains Mono',monospace"><?= $stat['approved'] ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem">
                        <div style="width:42px;height:42px;border-radius:10px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-circle-xmark" style="color:#ef4444"></i>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0">Ditolak</p>
                            <p style="font-weight:700;font-size:1.2rem;margin:0;font-family:'JetBrains Mono',monospace"><?= $stat['rejected'] ?></p>
                        </div>
                    </div>
                </div>

                <!-- TABEL -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Daftar Kunjungan <span style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?= $total ?> total)</span></h3>
                        <div class="table-controls">
                            <form method="GET" action="kelola_kunjungan_pelanggan.php" style="display:contents">
                                <div class="search-mini">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="q" placeholder="Cari nama, email, tujuan…" value="<?= htmlspecialchars($search) ?>" />
                                </div>
                                <select class="select-sm" name="status" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="pending"  <?= $statusFilter === 'pending'  ? 'selected' : '' ?>>Menunggu</option>
                                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Tanggal</th>
                                    <th>Shift & Jam</th>
                                    <th>Orang</th>
                                    <th>Tujuan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kunjungan)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-calendar-days" style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
                                            Tidak ada data kunjungan ditemukan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($kunjungan as $i => $k): ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                            <td style="font-weight:500"><?= htmlspecialchars($k['nama_pengunjung']) ?></td>
                                            <td style="font-size:.82rem;color:var(--text-muted)"><?= htmlspecialchars($k['email']) ?></td>
                                            <td style="font-size:.82rem"><?= date('d M Y', strtotime($k['tanggal_kunjungan'])) ?></td>
                                            <td style="font-size:.82rem">
                                                <span style="background:rgba(99,102,241,.1);padding:.2rem .5rem;border-radius:4px">
                                                    <?= $k['shift'] === 'pagi' ? '🌅' : '☀️' ?>
                                                    <?= ucfirst($k['shift']) ?> - <?= $k['jam'] ?>
                                                </span>
                                            </td>
                                            <td style="font-size:.82rem;font-weight:500;text-align:center"><?= $k['jumlah_orang'] ?> <i class="fa-solid fa-person" style="opacity:.5"></i></td>
                                            <td style="font-size:.85rem"><?= htmlspecialchars($k['tujuan']) ?></td>
                                            <td>
                                                <span style="<?= badgeStyle($k['status']) ?>;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600">
                                                    <i class="fa-solid <?= statusIcon($k['status']) ?>"></i>
                                                    <?= statusLabel($k['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($k['status'] === 'pending'): ?>
                                                    <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:nowrap">
                                                        <a href="kelola_kunjungan_pelanggan.php?setujui=<?= $k['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>"
                                                           style="display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .65rem;font-size:.78rem;font-weight:600;background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.35);border-radius:6px;text-decoration:none;white-space:nowrap;transition:background .15s,transform .1s"
                                                           title="Setujui kunjungan ini"
                                                           onclick="return confirm('Setujui kunjungan ini?')"
                                                           onmouseover="this.style.background='rgba(16,185,129,.25)'"
                                                           onmouseout="this.style.background='rgba(16,185,129,.12)'">
                                                            <i class="fa-solid fa-circle-check"></i> Setujui
                                                        </a>
                                                        <a href="kelola_kunjungan_pelanggan.php?tolak=<?= $k['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>"
                                                           style="display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .65rem;font-size:.78rem;font-weight:600;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.35);border-radius:6px;text-decoration:none;white-space:nowrap;transition:background .15s,transform .1s"
                                                           title="Tolak kunjungan ini"
                                                           onclick="return confirm('Tolak kunjungan ini?')"
                                                           onmouseover="this.style.background='rgba(239,68,68,.25)'"
                                                           onmouseout="this.style.background='rgba(239,68,68,.12)'">
                                                            <i class="fa-solid fa-circle-xmark"></i> Tolak
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="font-size:.78rem;color:var(--text-muted);font-style:italic">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION -->
                    <?php if ($totalPage > 1): ?>
                        <div class="table-footer">
                            <span class="table-info">
                                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> dari <?= $total ?> kunjungan
                            </span>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>" class="page-btn">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPage; $p++): ?>
                                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>"
                                        class="page-btn <?= $p === $page ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPage): ?>
                                    <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>" class="page-btn">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </section>
        </main>
    </div>

    <script src="../js/script.js"></script>
</body>
</html>
