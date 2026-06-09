<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$alert     = '';
$alertType = '';

// ── UPDATE KEHADIRAN ──────────────────────────────────────
if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int) $_GET['id'];
    $status = $_GET['status']; // hadir | tidak_hadir

    if (in_array($status, ['hadir', 'tidak_hadir'])) {
        $stmt = $pdo->prepare("UPDATE kunjungan SET kehadiran = ? WHERE id = ? AND status = 'approved'");
        $stmt->execute([$status, $id]);
        $alert     = 'Kehadiran berhasil diperbarui.';
        $alertType = 'success';
    }
}

// ── FILTER TANGGAL ────────────────────────────────────────
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

// ── AMBIL DATA KUNJUNGAN DISIAPKAN UNTUK TANGGAL TERSEBUT ──
$stmt = $pdo->prepare("
    SELECT id, nama_pengunjung, email, shift, jam, jumlah_orang, tujuan, kehadiran
    FROM kunjungan
    WHERE tanggal_kunjungan = ? AND status = 'approved'
    ORDER BY jam ASC
");
$stmt->execute([$tanggal]);
$kunjungan = $stmt->fetchAll();

// Helper functions
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
    <title>E-MEGO — Absensi Kunjungan</title>
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
                    <span class="breadcrumb-current">Absensi Kunjungan</span>
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
                        <h1 class="page-title">Absensi Kunjungan</h1>
                        <p class="page-subtitle">Catat kehadiran pengunjung yang telah disetujui untuk hari ini</p>
                    </div>
                </div>

                <!-- ALERT -->
                <?php if ($alert): ?>
                    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?>" id="alertBox">
                        <i class="fa-solid fa-<?= $alertType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($alert) ?>
                    </div>
                <?php endif; ?>

                <!-- FILTER TANGGAL -->
                <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
                    <form method="GET" action="kehadiran_kunjungan.php" style="display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                            <label class="form-label" style="display:block;margin-bottom:.5rem;font-weight:500;">Pilih Tanggal Kunjungan</label>
                            <div class="input-wrap" style="position:relative;">
                                <i class="fa-solid fa-calendar-day" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                                <input class="form-input" type="date" name="tanggal" value="<?= $tanggal ?>" 
                                    style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;background:var(--input-bg);color:var(--text-primary);" 
                                    onchange="this.form.submit()">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 1.5rem;">
                            <i class="fa-solid fa-magnifying-glass"></i> Tampilkan
                        </button>
                    </form>
                </div>

                <!-- TABEL KEHADIRAN -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Daftar Pengunjung - <?= date('d M Y', strtotime($tanggal)) ?></h3>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Pengunjung</th>
                                    <th>Shift & Jam</th>
                                    <th>Tujuan</th>
                                    <th>Status Kehadiran</th>
                                    <th style="text-align:center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kunjungan)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:3rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-user-slash" style="font-size:2.5rem;margin-bottom:1rem;display:block;opacity:.3"></i>
                                            Tidak ada pengunjung yang disetujui untuk tanggal ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($kunjungan as $i => $k): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td>
                                                <div style="font-weight:600"><?= htmlspecialchars($k['nama_pengunjung']) ?></div>
                                                <div style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($k['email'] ?: '-') ?></div>
                                            </td>
                                            <td>
                                                <span style="font-size:.82rem;background:rgba(99,102,241,.1);padding:.2rem .5rem;border-radius:4px;color:var(--primary-color)">
                                                    <?= ucfirst($k['shift']) ?> - <?= $k['jam'] ?>
                                                </span>
                                            </td>
                                            <td style="font-size:.85rem"><?= htmlspecialchars($k['tujuan']) ?></td>
                                            <td>
                                                <?php if ($k['kehadiran'] === 'hadir'): ?>
                                                    <span style="background:rgba(16,185,129,.15);color:#10b981;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600">
                                                        <i class="fa-solid fa-check-circle"></i> Hadir
                                                    </span>
                                                <?php elseif ($k['kehadiran'] === 'tidak_hadir'): ?>
                                                    <span style="background:rgba(239,68,68,.15);color:#ef4444;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600">
                                                        <i class="fa-solid fa-times-circle"></i> Tidak Hadir
                                                    </span>
                                                <?php else: ?>
                                                    <span style="background:rgba(100,116,139,.15);color:#64748b;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600">
                                                        Belum Absen
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:.5rem;justify-content:center">
                                                    <a href="?id=<?= $k['id'] ?>&status=hadir&tanggal=<?= $tanggal ?>" 
                                                       class="btn" style="background:#10b981;color:white;font-size:.75rem;padding:.4rem .8rem;border-radius:6px;"
                                                       title="Tandai Hadir">
                                                        <i class="fa-solid fa-user-check"></i> Hadir
                                                    </a>
                                                    <a href="?id=<?= $k['id'] ?>&status=tidak_hadir&tanggal=<?= $tanggal ?>" 
                                                       class="btn" style="background:#ef4444;color:white;font-size:.75rem;padding:.4rem .8rem;border-radius:6px;"
                                                       title="Tandai Tidak Hadir">
                                                        <i class="fa-solid fa-user-xmark"></i> Tidak Hadir
                                                    </a>
                                                </div>
                                            </td>
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

    <script src="../js/script.js"></script>
    <script>
        const alertBox = document.getElementById('alertBox');
        if (alertBox) {
            setTimeout(() => {
                alertBox.style.transition = 'opacity 0.5s ease';
                alertBox.style.opacity = '0';
                setTimeout(() => alertBox.remove(), 500);
            }, 3000);
        }
    </script>
</body>
</html>
