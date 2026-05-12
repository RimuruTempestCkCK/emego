<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pelanggan') {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ── Flash message dari proses_transaksi.php ───────────────────
$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ── Filter & Pagination ───────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$limit        = 8;
$offset       = ($page - 1) * $limit;

$where  = "WHERE t.user_id = ?";
$params = [$userId];

$validStatuses = ['pending', 'divalidasi', 'ditolak'];
if (in_array($statusFilter, $validStatuses)) {
    $where   .= " AND t.status = ?";
    $params[] = $statusFilter;
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM transaksi t $where");
$totalStmt->execute($params);
$total     = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

$dataStmt = $pdo->prepare("
    SELECT t.id, t.kode_transaksi, t.total_harga, t.status, t.catatan, t.created_at, t.validated_at, t.bukti_bayar
    FROM transaksi t
    $where
    ORDER BY t.id DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$transaksi = $dataStmt->fetchAll();

// ── Statistik ringkas ─────────────────────────────────────────
$stmtStat = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status='pending')    AS pending,
        SUM(status='divalidasi') AS divalidasi,
        SUM(status='ditolak')    AS ditolak,
        SUM(CASE WHEN status='divalidasi' THEN total_harga ELSE 0 END) AS total_belanja
    FROM transaksi
    WHERE user_id = ?
");
$stmtStat->execute([$userId]);
$stat = $stmtStat->fetch();

// ── Detail satu transaksi (untuk modal) ──────────────────────
$detailTrx   = null;
$detailItems = [];
$imgDir      = __DIR__ . '/../img/';
$imgUrl      = '../img/';

if (isset($_GET['detail'])) {
    $detailId = (int) $_GET['detail'];
    $stmtD    = $pdo->prepare("SELECT * FROM transaksi WHERE id = ? AND user_id = ?");
    $stmtD->execute([$detailId, $userId]);
    $detailTrx = $stmtD->fetch();

    if ($detailTrx) {
        $stmtDI = $pdo->prepare("
            SELECT ti.jumlah, ti.harga_satuan, s.nama_barang, s.satuan, s.gambar, s.kategori
            FROM transaksi_item ti
            JOIN produk s ON s.id = ti.produk_id
            WHERE ti.transaksi_id = ?
        ");
        $stmtDI->execute([$detailId]);
        $detailItems = $stmtDI->fetchAll();
    }
}

$bukaModal = $detailTrx !== null;

// Helpers
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}
function formatRupiah(float $a): string {
    return 'Rp ' . number_format($a, 0, ',', '.');
}
function badgeStyle(string $s): string {
    return match($s) {
        'pending'    => 'background:rgba(245,158,11,.15);color:#f59e0b',
        'divalidasi' => 'background:rgba(16,185,129,.15);color:#10b981',
        'ditolak'    => 'background:rgba(239,68,68,.15);color:#ef4444',
        default      => 'background:rgba(100,116,139,.15);color:#64748b',
    };
}
function statusIcon(string $s): string {
    return match($s) {
        'pending'    => 'fa-clock',
        'divalidasi' => 'fa-circle-check',
        'ditolak'    => 'fa-circle-xmark',
        default      => 'fa-circle',
    };
}
function statusLabel(string $s): string {
    return match($s) {
        'pending'    => 'Menunggu Validasi',
        'divalidasi' => 'Divalidasi',
        'ditolak'    => 'Ditolak',
        default      => ucfirst($s),
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Pesanan Saya</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />

    <style>
        /* ── Modal ── */
        #modalBackdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 900;
            background: rgba(0,0,0,.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        #modalBackdrop.aktif { display: flex; }
        #modalBox {
            background: var(--card-bg,#fff);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            width: min(680px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(0,0,0,.25);
            animation: modalMasuk .25s cubic-bezier(.34,1.2,.64,1) both;
        }
        @keyframes modalMasuk {
            from { opacity:0; transform: translateY(-24px) scale(.97); }
            to   { opacity:1; transform: translateY(0)      scale(1);   }
        }
        .modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            border-radius: 16px 16px 0 0;
            background: rgba(0,0,0,.02);
            position: sticky; top:0; z-index:1;
        }
        .modal-header h3 { margin:0; font-weight:600; display:flex; align-items:center; gap:.5rem; font-size:1rem; }
        .modal-body  { padding: 1.75rem 1.5rem; }
        .modal-footer {
            display:flex; gap:1rem; justify-content:flex-end;
            border-top: 1px solid var(--border-color);
            padding-top:1.5rem; margin-top:.5rem;
        }

        /* ── Info grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: .85rem;
            margin-bottom: 1.5rem;
        }
        .info-item {
            background: var(--input-bg,#f8fafc);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: .8rem 1rem;
        }
        .info-item label {
            display: block;
            font-size: .72rem; font-weight:600;
            color: var(--text-muted);
            text-transform: uppercase; letter-spacing:.05em;
            margin-bottom:.3rem;
        }
        .info-item span { font-size:.9rem; font-weight:500; }

        /* ── Item list ── */
        .item-list {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .item-list-header {
            background: rgba(0,0,0,.02);
            padding: .7rem 1rem;
            font-size:.76rem; font-weight:600; color:var(--text-muted);
            text-transform:uppercase; letter-spacing:.05em;
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: .5rem;
        }
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: .5rem;
            padding: .8rem 1rem;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .item-row:last-child { border-bottom: none; }
        .item-row:hover { background: rgba(0,0,0,.01); }
        .tbl-img {
            width:38px; height:38px; object-fit:cover;
            border-radius:8px; border:1px solid var(--border-color);
            flex-shrink:0;
        }
        .total-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: .85rem 1rem;
            background: var(--primary-color);
            color: #fff; font-weight:600;
        }

        /* ── Status timeline ── */
        .status-timeline {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--input-bg,#f8fafc);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            flex-wrap: wrap;
        }
        .timeline-step {
            display: flex; align-items: center; gap: .4rem;
            font-size: .8rem; font-weight:500;
            color: var(--text-muted);
        }
        .timeline-step.done  { color: #10b981; }
        .timeline-step.aktif { color: #f59e0b; }
        .timeline-step.tolak { color: #ef4444; }
        .timeline-divider { color: var(--border-color); font-size:.8rem; }

        /* ── Empty state ── */
        .empty-state {
            text-align:center; padding:3rem 1rem; color:var(--text-muted);
        }
        .empty-state i { font-size:3rem; display:block; margin-bottom:.75rem; opacity:.25; }
    </style>
</head>
<body>

    <?php include '../layout/sidebar.php'; ?>
    <div class="overlay" id="overlay"></div>

    <!-- ══════════════ MODAL DETAIL ══════════════ -->
    <div id="modalBackdrop" class="<?= $bukaModal ? 'aktif' : '' ?>">
        <div id="modalBox" role="dialog" aria-modal="true">
            <div class="modal-header">
                <h3>
                    <i class="fa-solid fa-receipt" style="color:var(--primary-color)"></i>
                    Detail Pesanan
                    <?php if ($detailTrx): ?>
                        <span style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:400;color:var(--text-muted)">
                            — <?= htmlspecialchars($detailTrx['kode_transaksi']) ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <button type="button" class="icon-btn" id="btnTutupModal"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body">
                <?php if ($detailTrx): ?>

                    <!-- Timeline status -->
                    <div class="status-timeline">
                        <?php $st = $detailTrx['status']; ?>
                        <div class="timeline-step done"><i class="fa-solid fa-circle-check"></i> Dipesan</div>
                        <div class="timeline-divider"><i class="fa-solid fa-chevron-right"></i></div>
                        <?php if ($st === 'ditolak'): ?>
                            <div class="timeline-step tolak"><i class="fa-solid fa-circle-xmark"></i> Ditolak Admin</div>
                        <?php elseif ($st === 'divalidasi'): ?>
                            <div class="timeline-step done"><i class="fa-solid fa-circle-check"></i> Divalidasi Admin</div>
                            <div class="timeline-divider"><i class="fa-solid fa-chevron-right"></i></div>
                            <div class="timeline-step done"><i class="fa-solid fa-circle-check"></i> produk Dikurangi</div>
                        <?php else: ?>
                            <div class="timeline-step aktif"><i class="fa-solid fa-clock"></i> Menunggu Validasi Admin</div>
                        <?php endif; ?>
                    </div>

                    <!-- Info grid -->
                    <div class="info-grid">
                        <div class="info-item">
                            <label><i class="fa-solid fa-hashtag"></i> Kode</label>
                            <span style="font-family:'JetBrains Mono',monospace;font-size:.82rem"><?= htmlspecialchars($detailTrx['kode_transaksi']) ?></span>
                        </div>
                        <div class="info-item">
                            <label><i class="fa-solid fa-circle-info"></i> Status</label>
                            <span style="<?= badgeStyle($detailTrx['status']) ?>;padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:capitalize">
                                <i class="fa-solid <?= statusIcon($detailTrx['status']) ?>"></i>
                                <?= statusLabel($detailTrx['status']) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label><i class="fa-solid fa-calendar"></i> Tanggal Pesan</label>
                            <span style="font-size:.83rem"><?= date('d M Y, H:i', strtotime($detailTrx['created_at'])) ?></span>
                        </div>
                        <?php if ($detailTrx['validated_at']): ?>
                        <div class="info-item">
                            <label><i class="fa-solid fa-calendar-check"></i> Diproses</label>
                            <span style="font-size:.83rem"><?= date('d M Y, H:i', strtotime($detailTrx['validated_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($detailTrx['catatan']): ?>
                        <div class="info-item" style="grid-column:1/-1">
                            <label><i class="fa-solid fa-note-sticky"></i> Catatan Anda</label>
                            <span style="font-size:.85rem"><?= htmlspecialchars($detailTrx['catatan']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Daftar item -->
                    <p style="font-weight:600;margin-bottom:.6rem;font-size:.88rem">
                        <i class="fa-solid fa-boxes-stacked" style="color:var(--primary-color);margin-right:.35rem"></i>
                        Daftar Barang
                    </p>
                    <div class="item-list">
                        <div class="item-list-header">
                            <span>Barang</span><span>Jumlah</span><span>Harga</span><span>Subtotal</span>
                        </div>
                        <?php if (empty($detailItems)): ?>
                            <div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:.85rem">Tidak ada data.</div>
                        <?php else: ?>
                            <?php foreach ($detailItems as $item): ?>
                            <div class="item-row">
                                <div style="display:flex;align-items:center;gap:.65rem">
                                    <?php if (!empty($item['gambar']) && file_exists($imgDir . $item['gambar'])): ?>
                                        <img src="<?= $imgUrl . htmlspecialchars($item['gambar']) ?>" class="tbl-img" alt="">
                                    <?php else: ?>
                                        <div class="activity-avatar" style="background:#6366f1;flex-shrink:0"><?= initials($item['nama_barang']) ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <span style="font-weight:500;font-size:.87rem;display:block"><?= htmlspecialchars($item['nama_barang']) ?></span>
                                        <span style="font-size:.73rem;color:var(--text-muted)"><?= htmlspecialchars($item['kategori'] ?? '') ?></span>
                                    </div>
                                </div>
                                <div style="font-family:'JetBrains Mono',monospace;font-size:.83rem;font-weight:600">
                                    <?= number_format($item['jumlah']) ?>
                                    <span style="color:var(--text-muted);font-weight:400;font-size:.75rem"><?= htmlspecialchars($item['satuan']) ?></span>
                                </div>
                                <div style="font-family:'JetBrains Mono',monospace;font-size:.81rem"><?= formatRupiah((float)$item['harga_satuan']) ?></div>
                                <div style="font-family:'JetBrains Mono',monospace;font-size:.81rem;font-weight:600"><?= formatRupiah((float)$item['harga_satuan'] * $item['jumlah']) ?></div>
                            </div>
                            <?php endforeach; ?>
                            <div class="total-row">
                                <span><i class="fa-solid fa-money-bill-wave"></i> Total</span>
                                <span style="font-family:'JetBrains Mono',monospace;font-size:1rem"><?= formatRupiah((float)$detailTrx['total_harga']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="btnTutupModal2"
                            style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem">
                            <i class="fa-solid fa-xmark"></i> Tutup
                        </button>
                        <?php if ($detailTrx['status'] === 'pending'): ?>
                        <span style="font-size:.8rem;color:var(--text-muted);align-self:center">
                            <i class="fa-solid fa-clock"></i> Menunggu konfirmasi admin…
                        </span>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <p style="color:var(--text-muted);text-align:center;padding:2rem 0">Klik tombol detail pada pesanan untuk melihat isinya.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- ═════════════════════════════════════════ -->

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
                    <span class="breadcrumb-current">Pesanan Saya</span>
                </div>
            </div>
            <div class="navbar-center"></div>
            <div class="navbar-right">
                <button class="icon-btn" title="Mode Gelap" id="themeToggle">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <div class="profile-dropdown" id="profileDropdown">
                    <button class="profile-trigger" id="profileTrigger">
                        <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'PL') ?></div>
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Pelanggan') ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'PL') ?></div>
                            <div>
                                <p><?= htmlspecialchars($_SESSION['user_name'] ?? 'Pelanggan') ?></p>
                                <small><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <!-- <a href="produk.php" class="dropdown-item"><i class="fa-solid fa-store"></i> Produk</a>
                        <div class="dropdown-divider"></div> -->
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
                        <h1 class="page-title">Pesanan Saya</h1>
                        <p class="page-subtitle">Riwayat semua pemesanan yang pernah Anda buat</p>
                    </div>
                    <a href="produk.php" class="btn btn-primary">
                        <i class="fa-solid fa-store"></i> Pesan Lagi
                    </a>
                </div>

                <!-- FLASH SUCCESS -->
                <?php if ($flashSuccess): ?>
                    <div class="alert alert-success" id="alertBox">
                        <i class="fa-solid fa-circle-check"></i> <?= $flashSuccess ?>
                    </div>
                <?php endif; ?>
                <!-- FLASH ERROR -->
                <?php if ($flashError): ?>
                    <div class="alert alert-danger" id="alertBox">
                        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($flashError) ?>
                    </div>
                <?php endif; ?>

                <!-- STATISTIK RINGKAS -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:1.5rem">
                    <div class="card" style="padding:1rem 1.15rem;display:flex;align-items:center;gap:.85rem">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-receipt" style="color:var(--primary-color)"></i>
                        </div>
                        <div>
                            <p style="font-size:.73rem;color:var(--text-muted);margin:0">Total Pesanan</p>
                            <p style="font-weight:700;font-size:1.15rem;margin:0;font-family:'JetBrains Mono',monospace"><?= $stat['total'] ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.15rem;display:flex;align-items:center;gap:.85rem">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-clock" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <p style="font-size:.73rem;color:var(--text-muted);margin:0">Menunggu</p>
                            <p style="font-weight:700;font-size:1.15rem;margin:0;font-family:'JetBrains Mono',monospace"><?= $stat['pending'] ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.15rem;display:flex;align-items:center;gap:.85rem">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-circle-check" style="color:#10b981"></i>
                        </div>
                        <div>
                            <p style="font-size:.73rem;color:var(--text-muted);margin:0">Divalidasi</p>
                            <p style="font-weight:700;font-size:1.15rem;margin:0;font-family:'JetBrains Mono',monospace"><?= $stat['divalidasi'] ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.15rem;display:flex;align-items:center;gap:.85rem">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-circle-xmark" style="color:#ef4444"></i>
                        </div>
                        <div>
                            <p style="font-size:.73rem;color:var(--text-muted);margin:0">Ditolak</p>
                            <p style="font-weight:700;font-size:1.15rem;margin:0;font-family:'JetBrains Mono',monospace"><?= $stat['ditolak'] ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.15rem;display:flex;align-items:center;gap:.85rem">
                        <div style="width:40px;height:40px;border-radius:10px;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-money-bill-wave" style="color:#10b981"></i>
                        </div>
                        <div>
                            <p style="font-size:.73rem;color:var(--text-muted);margin:0">Total Belanja</p>
                            <p style="font-weight:700;font-size:.9rem;margin:0;font-family:'JetBrains Mono',monospace"><?= formatRupiah((float)$stat['total_belanja']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- TABEL PESANAN -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Riwayat Pesanan <span style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?= $total ?> total)</span></h3>
                        <div class="table-controls">
                            <form method="GET" action="pemesanan_saya.php" style="display:contents">
                                <select class="select-sm" name="status" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="pending"    <?= $statusFilter === 'pending'    ? 'selected':'' ?>>Menunggu</option>
                                    <option value="divalidasi" <?= $statusFilter === 'divalidasi' ? 'selected':'' ?>>Divalidasi</option>
                                    <option value="ditolak"    <?= $statusFilter === 'ditolak'    ? 'selected':'' ?>>Ditolak</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Kode Transaksi</th>
                                    <th>Total</th>
                                    <th>Catatan</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transaksi)): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <i class="fa-solid fa-receipt"></i>
                                                <p style="font-weight:600;font-size:1rem;margin:0 0 .35rem">Belum ada pesanan</p>
                                                <p style="font-size:.85rem;margin:0 0 1rem">Yuk mulai pesan produk hidroponik segar!</p>
                                                <a href="produk.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:.5rem">
                                                    <i class="fa-solid fa-store"></i> Lihat Produk
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksi as $i => $t): ?>
                                    <tr>
                                        <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                        <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:600">
                                            <?= htmlspecialchars($t['kode_transaksi']) ?>
                                        </td>
                                        <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:600">
                                            <?= formatRupiah((float)$t['total_harga']) ?>
                                        </td>
                                        <td style="font-size:.82rem;color:var(--text-muted);max-width:160px">
                                            <?php if ($t['catatan']): ?>
                                                <span title="<?= htmlspecialchars($t['catatan']) ?>">
                                                    <?= htmlspecialchars(mb_strimwidth($t['catatan'], 0, 30, '…')) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="opacity:.4">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:.82rem;color:var(--text-muted)">
                                            <?= date('d M Y', strtotime($t['created_at'])) ?>
                                            <span style="display:block;font-size:.73rem"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                                        </td>
                                        <td>
                                            <span style="<?= badgeStyle($t['status']) ?>;padding:.25rem .65rem;border-radius:20px;font-size:.73rem;font-weight:700;white-space:nowrap">
                                                <i class="fa-solid <?= statusIcon($t['status']) ?>"></i>
                                                <?= statusLabel($t['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="pemesanan_saya.php?detail=<?= $t['id'] ?><?= $statusFilter ? '&status='.$statusFilter : '' ?><?= $page > 1 ? '&page='.$page : '' ?>"
                                               style="padding:.35rem .7rem;font-size:.78rem;background:rgba(99,102,241,.12);color:var(--primary-color);border:1px solid rgba(99,102,241,.3);border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:.3rem">
                                                <i class="fa-solid fa-eye"></i> Detail
                                            </a>
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
                                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> dari <?= $total ?> pesanan
                            </span>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page-1 ?>&status=<?= $statusFilter ?>" class="page-btn">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPage; $p++): ?>
                                    <a href="?page=<?= $p ?>&status=<?= $statusFilter ?>"
                                       class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPage): ?>
                                    <a href="?page=<?= $page+1 ?>&status=<?= $statusFilter ?>" class="page-btn">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div><!-- /card -->

            </section>
        </main>
    </div><!-- /main-wrapper -->

    <script src="../js/script.js"></script>
    <script>
    (() => {
        const backdrop = document.getElementById('modalBackdrop');
        const modalBox = document.getElementById('modalBox');

        function tutupModal() {
            backdrop.classList.remove('aktif');
            document.body.style.overflow = '';
            const url = new URL(window.location.href);
            url.searchParams.delete('detail');
            history.replaceState(null, '', url.toString());
        }

        backdrop.addEventListener('click', e => { if (!modalBox.contains(e.target)) tutupModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupModal(); });
        document.getElementById('btnTutupModal').addEventListener('click', tutupModal);
        const btn2 = document.getElementById('btnTutupModal2');
        if (btn2) btn2.addEventListener('click', tutupModal);

        // Alert auto-hide
        const alertBox = document.getElementById('alertBox');
        if (alertBox) {
            setTimeout(() => alertBox.style.transition = 'opacity .5s', 3500);
            setTimeout(() => alertBox.style.opacity = '0', 3600);
            setTimeout(() => alertBox.remove(), 4100);
        }
    })();
    </script>
</body>
</html>