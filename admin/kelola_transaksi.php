<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$alert     = '';
$alertType = '';

// ── VALIDASI TRANSAKSI ────────────────────────────────────────
if (isset($_GET['validasi'])) {
    $transaksiId = (int) $_GET['validasi'];

    $stmtTrx = $pdo->prepare("SELECT * FROM transaksi WHERE id = ? AND status = 'pending'");
    $stmtTrx->execute([$transaksiId]);
    $trx = $stmtTrx->fetch();

    if (!$trx) {
        $alert     = 'Transaksi tidak ditemukan atau sudah divalidasi sebelumnya.';
        $alertType = 'danger';
    } else {
        $stmtItems = $pdo->prepare("
            SELECT ti.produk_id, ti.jumlah, p.jumlah AS stok_tersedia, p.nama_barang
            FROM transaksi_item ti
            JOIN produk p ON p.id = ti.produk_id
            WHERE ti.transaksi_id = ?
        ");
        $stmtItems->execute([$transaksiId]);
        $items = $stmtItems->fetchAll();

        $kurangStok = [];
        foreach ($items as $item) {
            if ($item['stok_tersedia'] < $item['jumlah']) {
                $kurangStok[] = $item['nama_barang'] . ' (tersedia: ' . $item['stok_tersedia'] . ', dibutuhkan: ' . $item['jumlah'] . ')';
            }
        }

        if (!empty($kurangStok)) {
            $alert     = 'Stok tidak mencukupi untuk: ' . implode(', ', $kurangStok) . '.';
            $alertType = 'danger';
        } else {
            try {
                $pdo->beginTransaction();

                foreach ($items as $item) {
                    $stmtKurangi = $pdo->prepare("
                        UPDATE produk
                        SET jumlah = jumlah - ?,
                            status = CASE
                                WHEN (jumlah - ?) <= 0   THEN 'habis'
                                WHEN (jumlah - ?) <= 10  THEN 'terbatas'
                                ELSE 'tersedia'
                            END
                        WHERE id = ?
                    ");
                    $stmtKurangi->execute([$item['jumlah'], $item['jumlah'], $item['jumlah'], $item['produk_id']]);
                }

                $stmtUpdate = $pdo->prepare("UPDATE transaksi SET status = 'divalidasi', validated_at = NOW(), validated_by = ? WHERE id = ?");
                $stmtUpdate->execute([$_SESSION['user_id'] ?? null, $transaksiId]);

                $pdo->commit();
                $alert     = 'Transaksi #' . $transaksiId . ' berhasil divalidasi dan stok telah dikurangi.';
                $alertType = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $alert     = 'Terjadi kesalahan saat memvalidasi transaksi. Silakan coba lagi.';
                $alertType = 'danger';
            }
        }
    }
}

// ── TOLAK TRANSAKSI ───────────────────────────────────────────
if (isset($_GET['tolak'])) {
    $transaksiId = (int) $_GET['tolak'];
    $stmtCek = $pdo->prepare("SELECT id FROM transaksi WHERE id = ? AND status = 'pending'");
    $stmtCek->execute([$transaksiId]);
    if ($stmtCek->fetch()) {
        $stmtTolak = $pdo->prepare("UPDATE transaksi SET status = 'ditolak', validated_at = NOW(), validated_by = ? WHERE id = ?");
        $stmtTolak->execute([$_SESSION['user_id'] ?? null, $transaksiId]);
        $alert     = 'Transaksi #' . $transaksiId . ' telah ditolak.';
        $alertType = 'success';
    } else {
        $alert     = 'Transaksi tidak ditemukan atau sudah diproses sebelumnya.';
        $alertType = 'danger';
    }
}

// ── FILTER & PAGINATION ───────────────────────────────────────
$search        = trim($_GET['q'] ?? '');
$statusFilter  = $_GET['status'] ?? '';
$page          = max(1, (int) ($_GET['page'] ?? 1));
$limit         = 10;
$offset        = ($page - 1) * $limit;

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where   .= " AND (t.kode_transaksi LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$validStatuses = ['pending', 'divalidasi', 'ditolak'];
if (in_array($statusFilter, $validStatuses)) {
    $where   .= " AND t.status = ?";
    $params[] = $statusFilter;
}

$totalStmt = $pdo->prepare("
    SELECT COUNT(*) FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    $where
");
$totalStmt->execute($params);
$total    = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

$dataStmt = $pdo->prepare("
    SELECT t.id, t.kode_transaksi, t.status, t.total_harga, t.created_at, t.validated_at, t.bukti_bayar,
           u.name AS nama_user, u.email AS email_user
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    $where
    ORDER BY t.id DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$transaksi = $dataStmt->fetchAll();

// ── DETAIL TRANSAKSI (untuk modal) ───────────────────────────
$detailTrx   = null;
$detailItems = [];
if (isset($_GET['detail'])) {
    $detailId = (int) $_GET['detail'];
    $stmtD    = $pdo->prepare("
        SELECT t.*, u.name AS nama_user, u.email AS email_user
        FROM transaksi t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.id = ?
    ");
    $stmtD->execute([$detailId]);
    $detailTrx = $stmtD->fetch();

    if ($detailTrx) {
        $stmtDI = $pdo->prepare("
            SELECT ti.jumlah, ti.harga_satuan, p.nama_barang, p.satuan, p.gambar, p.status AS status_stok
            FROM transaksi_item ti
            JOIN produk p ON p.id = ti.produk_id
            WHERE ti.transaksi_id = ?
        ");
        $stmtDI->execute([$detailId]);
        $detailItems = $stmtDI->fetchAll();
    }
}

// ... sisa kode HTML tidak berubah sama sekali

$imgUrl    = '../img/';
$imgDir    = __DIR__ . '/../img/';
$bukaModal = $detailTrx !== null;

// Helper: inisial
function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

// Helper: warna badge status transaksi
function badgeStyle(string $status): string
{
    return match ($status) {
        'pending'    => 'background:rgba(245,158,11,.15);color:#f59e0b',
        'divalidasi' => 'background:rgba(16,185,129,.15);color:#10b981',
        'ditolak'    => 'background:rgba(239,68,68,.15);color:#ef4444',
        default      => 'background:rgba(100,116,139,.15);color:#64748b',
    };
}

// Helper: ikon status
function statusIcon(string $status): string
{
    return match ($status) {
        'pending'    => 'fa-clock',
        'divalidasi' => 'fa-circle-check',
        'ditolak'    => 'fa-circle-xmark',
        default      => 'fa-circle',
    };
}

// Helper: format rupiah
function formatRupiah(float $angka): string
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Kelola Transaksi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />

    <style>
        /* ── Modal Backdrop ── */
        #modalBackdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 900;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        #modalBackdrop.aktif {
            display: flex;
        }

        /* ── Modal Box ── */
        #modalBox {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            width: min(720px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.25);
            animation: modalMasuk .25s cubic-bezier(.34,1.2,.64,1) both;
        }

        @keyframes modalMasuk {
            from { opacity: 0; transform: translateY(-24px) scale(.97); }
            to   { opacity: 1; transform: translateY(0)      scale(1);   }
        }

        /* ── Modal Header ── */
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            border-radius: 16px 16px 0 0;
            background: rgba(0, 0, 0, 0.02);
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .modal-header h3 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: 1rem;
        }

        /* ── Modal Body ── */
        .modal-body {
            padding: 1.75rem 1.5rem;
        }

        /* ── Modal Footer ── */
        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
            margin-top: .5rem;
        }

        /* ── Info Grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .info-item {
            background: var(--input-bg, #f8fafc);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: .9rem 1rem;
        }
        .info-item label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .3rem;
        }
        .info-item span {
            font-size: .92rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* ── Item List ── */
        .item-list {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .item-list-header {
            background: rgba(0,0,0,.02);
            padding: .75rem 1rem;
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: .5rem;
        }
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: .5rem;
            padding: .85rem 1rem;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .item-row:hover {
            background: rgba(0,0,0,.01);
        }

        /* ── Gambar di tabel ── */
        .tbl-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        /* ── Tombol aksi kecil ── */
        .btn-validasi {
            padding: .35rem .7rem;
            font-size: .78rem;
            background: rgba(16,185,129,.12);
            color: #10b981;
            border: 1px solid rgba(16,185,129,.3);
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-validasi:hover {
            background: rgba(16,185,129,.22);
        }
        .btn-tolak {
            padding: .35rem .7rem;
            font-size: .78rem;
            background: rgba(239,68,68,.12);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,.3);
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-tolak:hover {
            background: rgba(239,68,68,.22);
        }
        .btn-detail {
            padding: .35rem .7rem;
            font-size: .78rem;
            background: rgba(99,102,241,.12);
            color: var(--primary-color);
            border: 1px solid rgba(99,102,241,.3);
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-detail:hover {
            background: rgba(99,102,241,.22);
        }

        /* ── Total row ── */
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .9rem 1rem;
            background: var(--primary-color);
            border-radius: 0 0 10px 10px;
            color: #fff;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <?php include '../layout/sidebar.php'; ?>

    <div class="overlay" id="overlay"></div>

    <!-- ══════════════ MODAL DETAIL TRANSAKSI ══════════════ -->
    <div id="modalBackdrop" class="<?= $bukaModal ? 'aktif' : '' ?>">
        <div id="modalBox" role="dialog" aria-modal="true" aria-labelledby="modalJudul">

            <!-- Header -->
            <div class="modal-header">
                <h3 id="modalJudul">
                    <i class="fa-solid fa-receipt" style="color:var(--primary-color);"></i>
                    <span id="modalJudulText">Detail Transaksi</span>
                </h3>
                <button type="button" class="icon-btn" id="btnTutupModal" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body" id="modalContent">
                <?php if ($detailTrx): ?>
                    <!-- Info Grid -->
                    <div class="info-grid">
                        <div class="info-item">
                            <label><i class="fa-solid fa-hashtag"></i> Kode Transaksi</label>
                            <span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($detailTrx['kode_transaksi'] ?? '#'.$detailTrx['id']) ?></span>
                        </div>
                        <div class="info-item">
                            <label><i class="fa-solid fa-user"></i> Pemesan</label>
                            <span><?= htmlspecialchars($detailTrx['nama_user'] ?? '—') ?></span>
                        </div>
                        <div class="info-item">
                            <label><i class="fa-solid fa-envelope"></i> Email</label>
                            <span style="font-size:.82rem"><?= htmlspecialchars($detailTrx['email_user'] ?? '—') ?></span>
                        </div>
                        <div class="info-item">
                            <label><i class="fa-solid fa-circle-info"></i> Status</label>
                            <span style="<?= badgeStyle($detailTrx['status']) ?>;padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:600;text-transform:capitalize">
                                <i class="fa-solid <?= statusIcon($detailTrx['status']) ?>"></i>
                                <?= htmlspecialchars($detailTrx['status']) ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label><i class="fa-solid fa-calendar"></i> Tanggal Pesan</label>
                            <span style="font-size:.85rem"><?= date('d M Y, H:i', strtotime($detailTrx['created_at'])) ?></span>
                        </div>
                        <?php if ($detailTrx['validated_at']): ?>
                        <div class="info-item">
                            <label><i class="fa-solid fa-calendar-check"></i> Diproses Pada</label>
                            <span style="font-size:.85rem"><?= date('d M Y, H:i', strtotime($detailTrx['validated_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Bukti Pembayaran Section -->
                    <?php if ($detailTrx['status'] !== 'pending'): ?>
                    <div style="background:var(--input-bg,#f8fafc);border:1px solid var(--border-color);border-radius:10px;padding:1rem;margin-bottom:1.5rem">
                        <label style="display:block;font-weight:600;margin-bottom:.5rem;font-size:.9rem">
                            <i class="fa-solid fa-file" style="color:var(--primary-color);margin-right:.4rem"></i>
                            Bukti Pembayaran
                        </label>
                        <?php if ($detailTrx['bukti_bayar']): ?>
                            <div style="display:flex;align-items:center;gap:.8rem">
                                <i class="fa-solid fa-check-circle" style="color:#10b981;font-size:1.2rem"></i>
                                <div style="flex:1">
                                    <p style="margin:0;font-size:.85rem;color:var(--text-muted)">File sudah diunggah</p>
                                    <p style="margin:0;font-weight:600;font-family:'JetBrains Mono',monospace;font-size:.8rem"><?= htmlspecialchars($detailTrx['bukti_bayar']) ?></p>
                                </div>
                                <a href="../bukti/<?= htmlspecialchars($detailTrx['bukti_bayar']) ?>" 
                                   target="_blank" 
                                   rel="noopener noreferrer"
                                   class="btn-detail" 
                                   style="display:inline-flex;align-items:center;gap:.3rem"
                                   title="Buka Bukti Pembayaran">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka
                                </a>
                            </div>
                        <?php else: ?>
                            <p style="margin:0;font-size:.85rem;color:var(--text-muted)">
                                <i class="fa-solid fa-circle-exclamation" style="margin-right:.4rem"></i>
                                Belum ada bukti pembayaran diunggah
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Daftar Item -->
                    <p style="font-weight:600;margin-bottom:.6rem;font-size:.9rem">
                        <i class="fa-solid fa-boxes-stacked" style="color:var(--primary-color);margin-right:.4rem"></i>
                        Daftar Barang
                    </p>
                    <div class="item-list">
                        <div class="item-list-header">
                            <span>Barang</span>
                            <span>Jumlah</span>
                            <span>Harga/Satuan</span>
                            <span>Subtotal</span>
                        </div>
                        <?php if (empty($detailItems)): ?>
                            <div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:.85rem">
                                <i class="fa-solid fa-box-open" style="display:block;font-size:1.5rem;opacity:.3;margin-bottom:.4rem"></i>
                                Tidak ada item dalam transaksi ini.
                            </div>
                        <?php else: ?>
                            <?php foreach ($detailItems as $item): ?>
                                <div class="item-row">
                                    <div style="display:flex;align-items:center;gap:.7rem">
                                        <?php if (!empty($item['gambar']) && file_exists($imgDir . $item['gambar'])): ?>
                                            <img src="<?= $imgUrl . htmlspecialchars($item['gambar']) ?>"
                                                 alt="<?= htmlspecialchars($item['nama_barang']) ?>"
                                                 class="tbl-img" />
                                        <?php else: ?>
                                            <div class="activity-avatar" style="background:#6366f1;flex-shrink:0">
                                                <?= initials($item['nama_barang'] ?? '?') ?>
                                            </div>
                                        <?php endif; ?>
                                        <span style="font-weight:500;font-size:.88rem"><?= htmlspecialchars($item['nama_barang']) ?></span>
                                    </div>
                                    <div style="font-family:'JetBrains Mono',monospace;font-size:.84rem;font-weight:600">
                                        <?= number_format($item['jumlah']) ?>
                                        <span style="color:var(--text-muted);font-weight:400;font-size:.76rem"><?= htmlspecialchars($item['satuan']) ?></span>
                                    </div>
                                    <div style="font-family:'JetBrains Mono',monospace;font-size:.82rem">
                                        <?= formatRupiah((float) $item['harga_satuan']) ?>
                                    </div>
                                    <div style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:600">
                                        <?= formatRupiah((float) $item['harga_satuan'] * $item['jumlah']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="total-row">
                                <span><i class="fa-solid fa-money-bill-wave"></i> Total Pembayaran</span>
                                <span style="font-family:'JetBrains Mono',monospace;font-size:1rem">
                                    <?= formatRupiah((float) $detailTrx['total_harga']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer Modal -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="btnTutupModal2"
                            style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;">
                            <i class="fa-solid fa-xmark"></i> Tutup
                        </button>
                        <?php if ($detailTrx['status'] === 'pending'): ?>
                            <a href="kelola_transaksi.php?tolak=<?= $detailTrx['id'] ?>"
                               class="btn"
                               style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3)"
                               onclick="return confirm('Yakin ingin menolak transaksi ini?')">
                                <i class="fa-solid fa-circle-xmark"></i> Tolak
                            </a>
                            <a href="kelola_transaksi.php?validasi=<?= $detailTrx['id'] ?>"
                               class="btn btn-primary"
                               style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;"
                               onclick="return confirm('Validasi transaksi ini? Stok akan dikurangi otomatis.')">
                                <i class="fa-solid fa-circle-check"></i> Validasi Transaksi
                            </a>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <p style="color:var(--text-muted);text-align:center;padding:2rem 0">Pilih transaksi dari tabel untuk melihat detailnya.</p>
                <?php endif; ?>
            </div><!-- /modal-body -->

        </div><!-- /modalBox -->
    </div><!-- /modalBackdrop -->
    <!-- ══════════════════════════════════════════════════ -->

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
                    <span class="breadcrumb-current">Kelola Transaksi</span>
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
                        <h1 class="page-title">Kelola Transaksi</h1>
                        <p class="page-subtitle">Validasi pemesanan dan pengelolaan pengurangan stok otomatis</p>
                    </div>
                </div>

                <!-- ALERT -->
                <?php if ($alert): ?>
                    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?>" id="alertBox">
                        <i class="fa-solid fa-<?= $alertType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($alert) ?>
                    </div>
                <?php endif; ?>

                <!-- RINGKASAN STATISTIK -->
                <?php
                $stmtStat = $pdo->query("
                    SELECT
                        COUNT(*) AS total,
                        SUM(status = 'pending')    AS pending,
                        SUM(status = 'divalidasi') AS divalidasi,
                        SUM(status = 'ditolak')    AS ditolak
                    FROM transaksi
                ");
                $stat = $stmtStat->fetch();
                ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem">
                    <div class="card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem">
                        <div style="width:42px;height:42px;border-radius:10px;background:rgba(99,102,241,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-receipt" style="color:var(--primary-color)"></i>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0">Total Transaksi</p>
                            <p style="font-weight:700;font-size:1.2rem;margin:0;font-family:'JetBrains Mono',monospace"><?= number_format($stat['total']) ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem">
                        <div style="width:42px;height:42px;border-radius:10px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-clock" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0">Menunggu</p>
                            <p style="font-weight:700;font-size:1.2rem;margin:0;font-family:'JetBrains Mono',monospace"><?= number_format($stat['pending']) ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem">
                        <div style="width:42px;height:42px;border-radius:10px;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-circle-check" style="color:#10b981"></i>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0">Divalidasi</p>
                            <p style="font-weight:700;font-size:1.2rem;margin:0;font-family:'JetBrains Mono',monospace"><?= number_format($stat['divalidasi']) ?></p>
                        </div>
                    </div>
                    <div class="card" style="padding:1rem 1.25rem;display:flex;align-items:center;gap:.9rem">
                        <div style="width:42px;height:42px;border-radius:10px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fa-solid fa-circle-xmark" style="color:#ef4444"></i>
                        </div>
                        <div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin:0">Ditolak</p>
                            <p style="font-weight:700;font-size:1.2rem;margin:0;font-family:'JetBrains Mono',monospace"><?= number_format($stat['ditolak']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- TABEL TRANSAKSI -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Daftar Transaksi <span style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?= $total ?> total)</span></h3>
                        <div class="table-controls">
                            <form method="GET" action="kelola_transaksi.php" style="display:contents">
                                <div class="search-mini">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="q" placeholder="Cari kode, nama, email…" value="<?= htmlspecialchars($search) ?>" />
                                </div>
                                <select class="select-sm" name="status" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="pending"    <?= $statusFilter === 'pending'    ? 'selected' : '' ?>>Menunggu</option>
                                    <option value="divalidasi" <?= $statusFilter === 'divalidasi' ? 'selected' : '' ?>>Divalidasi</option>
                                    <option value="ditolak"    <?= $statusFilter === 'ditolak'    ? 'selected' : '' ?>>Ditolak</option>
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
                                    <th>Pemesan</th>
                                    <th>Total</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Bukti Bayar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transaksi)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center;padding:2rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-receipt" style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
                                            Tidak ada data transaksi ditemukan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksi as $i => $t): ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:600">
                                                <?= htmlspecialchars($t['kode_transaksi'] ?? '#'.$t['id']) ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:.7rem">
                                                    <div class="activity-avatar" style="background:#6366f1;flex-shrink:0">
                                                        <?= initials($t['nama_user'] ?? '?') ?>
                                                    </div>
                                                    <div>
                                                        <span style="font-weight:500;display:block"><?= htmlspecialchars($t['nama_user'] ?? '—') ?></span>
                                                        <span style="font-size:.75rem;color:var(--text-muted)"><?= htmlspecialchars($t['email_user'] ?? '') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:600">
                                                <?= formatRupiah((float) $t['total_harga']) ?>
                                            </td>
                                            <td style="font-size:.82rem;color:var(--text-muted)">
                                                <?= date('d M Y', strtotime($t['created_at'])) ?>
                                                <span style="display:block;font-size:.74rem"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                                            </td>
                                            <td>
                                                <span style="<?= badgeStyle($t['status']) ?>;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600;text-transform:capitalize">
                                                    <i class="fa-solid <?= statusIcon($t['status']) ?>"></i>
                                                    <?= htmlspecialchars($t['status']) ?>
                                                </span>
                                            </td>
                                            <td style="text-align:center">
                                                <?php if ($t['bukti_bayar']): ?>
                                                    <a href="../bukti/<?= htmlspecialchars($t['bukti_bayar']) ?>" 
                                                       target="_blank" 
                                                       rel="noopener noreferrer"
                                                       class="btn-detail" 
                                                       title="Lihat Bukti Pembayaran">
                                                        <i class="fa-solid fa-file"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted);font-size:.75rem">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                                                    <!-- Tombol Detail -->
                                                    <a href="kelola_transaksi.php?detail=<?= $t['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>"
                                                       class="btn-detail" title="Lihat Detail">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>

                                                    <?php if ($t['status'] === 'pending'): ?>
                                                        <!-- Tombol Validasi -->
                                                        <a href="kelola_transaksi.php?validasi=<?= $t['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>"
                                                           class="btn-validasi" title="Validasi"
                                                           onclick="return confirm('Validasi transaksi ini? Stok akan dikurangi otomatis.')">
                                                            <i class="fa-solid fa-circle-check"></i>
                                                        </a>

                                                        <!-- Tombol Tolak -->
                                                        <a href="kelola_transaksi.php?tolak=<?= $t['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>"
                                                           class="btn-tolak" title="Tolak"
                                                           onclick="return confirm('Yakin ingin menolak transaksi ini?')">
                                                            <i class="fa-solid fa-circle-xmark"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
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
                                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> dari <?= $total ?> transaksi
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
                </div><!-- /card table-card -->

            </section>
        </main>
    </div><!-- /main-wrapper -->

    <script src="../js/script.js"></script>
    <script>
    (() => {
        const backdrop = document.getElementById('modalBackdrop');
        const modalBox = document.getElementById('modalBox');

        // ── Tutup modal ──────────────────────────────────────
        function tutupModal() {
            backdrop.classList.remove('aktif');
            document.body.style.overflow = '';
            // Bersihkan query param 'detail' dari URL tanpa reload
            const url = new URL(window.location.href);
            url.searchParams.delete('detail');
            history.replaceState(null, '', url.toString());
        }

        // ── Klik backdrop (di luar modalBox) → tutup ────────
        backdrop.addEventListener('click', e => {
            if (!modalBox.contains(e.target)) tutupModal();
        });

        // ── ESC → tutup ──────────────────────────────────────
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && backdrop.classList.contains('aktif')) tutupModal();
        });

        // ── Tombol tutup ─────────────────────────────────────
        document.getElementById('btnTutupModal').addEventListener('click', tutupModal);
        const btnTutupModal2 = document.getElementById('btnTutupModal2');
        if (btnTutupModal2) btnTutupModal2.addEventListener('click', tutupModal);

        // ── Auto-hide alert setelah 4 detik ─────────────────
        const alertBox = document.getElementById('alertBox');
        if (alertBox) {
            setTimeout(() => alertBox.style.transition = 'opacity .5s', 3500);
            setTimeout(() => alertBox.style.opacity    = '0',           3600);
            setTimeout(() => alertBox.remove(),                          4100);
        }
    })();
    </script>
</body>
</html>