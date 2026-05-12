<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ── FILTER ────────────────────────────────────────────────────
$search      = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$kategoriFilter = $_GET['kategori'] ?? '';
$tglMulai    = $_GET['tgl_mulai'] ?? '';
$tglAkhir    = $_GET['tgl_akhir'] ?? '';
$bulanFilter = $_GET['bulan'] ?? '';   // format: YYYY-MM
$page        = max(1, (int) ($_GET['page'] ?? 1));
$limit       = 15;
$offset      = ($page - 1) * $limit;

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (p.nama_barang LIKE ? OR p.kategori LIKE ? OR p.satuan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (in_array($statusFilter, ['tersedia', 'habis', 'terbatas'])) {
    $where .= " AND p.status = ?";
    $params[] = $statusFilter;
}

if ($kategoriFilter !== '') {
    $where .= " AND p.kategori = ?";
    $params[] = $kategoriFilter;
}

// Filter tanggal berdasarkan created_at produk
if ($bulanFilter !== '') {
    $where .= " AND DATE_FORMAT(p.created_at, '%Y-%m') = ?";
    $params[] = $bulanFilter;
} else {
    if ($tglMulai !== '') {
        $where .= " AND DATE(p.created_at) >= ?";
        $params[] = $tglMulai;
    }
    if ($tglAkhir !== '') {
        $where .= " AND DATE(p.created_at) <= ?";
        $params[] = $tglAkhir;
    }
}

// ── TOTAL COUNT ───────────────────────────────────────────────
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM produk p $where");
$totalStmt->execute($params);
$total     = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

// ── DATA TABEL ────────────────────────────────────────────────
$dataStmt = $pdo->prepare("
    SELECT p.id, p.nama_barang, p.kategori, p.jumlah, p.satuan,
           p.harga_satuan, p.status, p.created_at, p.gambar,
           COALESCE(sm.total_masuk, 0) AS total_masuk,
           COALESCE(sm.jumlah_restok, 0) AS jumlah_restok
    FROM produk p
    LEFT JOIN (
        SELECT produk_id,
               SUM(jumlah)   AS total_masuk,
               COUNT(*)      AS jumlah_restok
        FROM stok_masuk
        GROUP BY produk_id
    ) sm ON sm.produk_id = p.id
    $where
    ORDER BY p.id DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$produk = $dataStmt->fetchAll();

// ── RINGKASAN ─────────────────────────────────────────────────
$sumStmt = $pdo->prepare("
    SELECT
        COUNT(*)                                        AS jumlah_produk,
        COALESCE(SUM(p.jumlah), 0)                     AS total_stok,
        COALESCE(SUM(p.jumlah * p.harga_satuan), 0)    AS nilai_stok,
        COALESCE(SUM(CASE WHEN p.status = 'habis'    THEN 1 ELSE 0 END), 0) AS stok_habis,
        COALESCE(SUM(CASE WHEN p.status = 'terbatas' THEN 1 ELSE 0 END), 0) AS stok_terbatas
    FROM produk p
    $where
");
$sumStmt->execute($params);
$ringkasan = $sumStmt->fetch();

// ── STOK PER KATEGORI (untuk grafik) ─────────────────────────
$grafStmt = $pdo->query("
    SELECT
        COALESCE(kategori, 'Tidak Berkategori') AS label,
        COUNT(*)                                 AS jumlah_produk,
        SUM(jumlah)                              AS total_stok,
        SUM(jumlah * harga_satuan)               AS nilai_stok
    FROM produk
    GROUP BY kategori
    ORDER BY nilai_stok DESC
    LIMIT 8
");
$grafData = $grafStmt->fetchAll();

// ── RIWAYAT STOK MASUK TERBARU ────────────────────────────────
$riwayatStmt = $pdo->query("
    SELECT sm.jumlah, sm.keterangan, sm.created_at,
           p.nama_barang, p.satuan,
           u.name AS admin_name
    FROM stok_masuk sm
    JOIN produk p ON p.id = sm.produk_id
    LEFT JOIN users u ON u.id = sm.admin_id
    ORDER BY sm.created_at DESC
    LIMIT 5
");
$riwayatTerbaru = $riwayatStmt->fetchAll();

// ── DAFTAR KATEGORI UNTUK FILTER ──────────────────────────────
$katStmt = $pdo->query("
    SELECT DISTINCT kategori FROM produk
    WHERE kategori IS NOT NULL AND kategori != ''
    ORDER BY kategori ASC
");
$daftarKategori = $katStmt->fetchAll(PDO::FETCH_COLUMN);

// ── DAFTAR BULAN ──────────────────────────────────────────────
$bulanStmt = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS val,
                    DATE_FORMAT(created_at, '%M %Y')  AS label
    FROM produk
    ORDER BY val DESC
    LIMIT 24
");
$daftarBulan = $bulanStmt->fetchAll();

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
function statusColor(string $status): string
{
    return match ($status) {
        'tersedia' => 'background:rgba(16,185,129,.15);color:#10b981',
        'terbatas' => 'background:rgba(245,158,11,.15);color:#f59e0b',
        'habis'    => 'background:rgba(239,68,68,.15);color:#ef4444',
        default    => '',
    };
}
function statusIcon(string $status): string
{
    return match ($status) {
        'tersedia' => 'fa-circle-check',
        'terbatas' => 'fa-circle-exclamation',
        'habis'    => 'fa-circle-xmark',
        default    => 'fa-circle',
    };
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Laporan Stok</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />
    <style>
        /* ── Chart wrapper ── */
        .chart-wrap {
            position: relative;
            height: 220px;
            width: 100%;
        }

        /* ── Kartu ringkasan ── */
        .stat-card {
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: .9rem;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-label {
            font-size: .75rem;
            color: var(--text-muted);
            margin: 0;
        }

        .stat-value {
            font-weight: 700;
            font-size: 1.15rem;
            margin: 0;
            font-family: 'JetBrains Mono', monospace;
        }

        /* ── Print button ── */
        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .55rem 1.1rem;
            background: rgba(99, 102, 241, .1);
            color: var(--primary-color);
            border: 1px solid rgba(99, 102, 241, .3);
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            text-decoration: none;
            transition: background .15s;
        }

        .btn-print:hover {
            background: rgba(99, 102, 241, .2);
        }

        /* ── Filter bar ── */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: flex-end;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }

        .filter-group label {
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* ── Kategori bar ── */
        .produk-bar-wrap {
            margin-bottom: .85rem;
        }

        .produk-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: .8rem;
            margin-bottom: .3rem;
            font-weight: 500;
        }

        .produk-bar-track {
            background: var(--border-color, rgba(0, 0, 0, .08));
            border-radius: 6px;
            height: 8px;
            overflow: hidden;
        }

        .produk-bar-fill {
            height: 100%;
            border-radius: 6px;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width .6s ease;
        }

        /* ── Thumbnail gambar tabel ── */
        .tbl-img {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 7px;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        /* ── Elemen yang hanya muncul saat print ── */
        .print-kop,
        .print-footer {
            display: none;
        }

        /* ── Print styles ── */
        @media print {
            @page {
                size: A4;
                margin: 1.5cm 1.8cm;
            }

            .navbar,
            .sidebar,
            .overlay,
            .btn-print,
            .no-print,
            .pagination,
            .table-footer,
            #sidebarToggle {
                display: none !important;
            }

            body {
                background: #fff !important;
                color: #000 !important;
                font-size: 11pt;
            }

            .main-wrapper {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .content {
                padding: 0 !important;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #d1d5db !important;
                break-inside: avoid;
                margin-bottom: 14pt !important;
            }

            .print-kop {
                display: block !important;
            }

            .stat-label {
                font-size: 8pt !important;
                color: #6b7280 !important;
            }

            .stat-value {
                font-size: 12pt !important;
                font-weight: 700 !important;
            }

            .data-table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9.5pt;
            }

            .data-table th {
                background: #1e293b !important;
                color: #fff !important;
                padding: 7pt 9pt !important;
                font-size: 8.5pt !important;
                text-align: left;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .data-table td {
                padding: 6pt 9pt !important;
                border-bottom: 1px solid #e5e7eb !important;
                vertical-align: middle;
            }

            .data-table tbody tr:nth-child(even) td {
                background: #f9fafb !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .activity-avatar,
            .tbl-img {
                display: none !important;
            }

            .print-footer {
                display: block !important;
            }
        }
    </style>
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
                    <span class="breadcrumb-current">Laporan Stok</span>
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
                        <a href="../logout.php" class="dropdown-item danger">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT -->
        <main class="content" id="contentArea">
            <section class="page active">

                <!-- ══ KOP SURAT (hanya tampil saat print) ══ -->
                <div class="print-kop" style="border-bottom:3px solid #1e293b;padding-bottom:14pt;margin-bottom:18pt">
                    <table style="width:100%;border-collapse:collapse">
                        <tr>
                            <td style="width:70%;vertical-align:middle">
                                <div style="display:flex;align-items:center;gap:14pt">
                                    <div
                                        style="width:52pt;height:52pt;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:10pt;display:flex;align-items:center;justify-content:center;flex-shrink:0;-webkit-print-color-adjust:exact;print-color-adjust:exact">
                                        <span style="color:#fff;font-size:18pt;font-weight:800;font-family:sans-serif">EM</span>
                                    </div>
                                    <div>
                                        <p style="margin:0;font-size:18pt;font-weight:800;letter-spacing:-0.5pt;color:#1e293b;font-family:sans-serif">E-MEGO</p>
                                        <p style="margin:0;font-size:9pt;color:#6366f1;font-weight:600;letter-spacing:1.5pt;text-transform:uppercase">Hydroponic Management System</p>
                                        <p style="margin:3pt 0 0;font-size:8pt;color:#6b7280">Jl. Contoh No. 1, Kota Contoh, Indonesia</p>
                                        <p style="margin:1pt 0 0;font-size:8pt;color:#6b7280">Telp: (021) 0000-0000 &nbsp;·&nbsp; Email: info@emego.id</p>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:right;vertical-align:top">
                                <p style="margin:0;font-size:14pt;font-weight:700;color:#1e293b;font-family:sans-serif">LAPORAN STOK BARANG</p>
                                <p style="margin:3pt 0 0;font-size:8.5pt;color:#6b7280">
                                    Dicetak oleh: <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>
                                </p>
                                <p style="margin:2pt 0 0;font-size:8.5pt;color:#6b7280">
                                    Tanggal cetak: <strong><?= date('d F Y, H:i') ?> WIB</strong>
                                </p>
                                <?php if ($bulanFilter): ?>
                                    <p style="margin:2pt 0 0;font-size:8.5pt;color:#6b7280">
                                        Periode: <strong><?= date('F Y', strtotime($bulanFilter . '-01')) ?></strong>
                                    </p>
                                <?php elseif ($tglMulai || $tglAkhir): ?>
                                    <p style="margin:2pt 0 0;font-size:8.5pt;color:#6b7280">
                                        Periode: <strong>
                                            <?= $tglMulai ? date('d M Y', strtotime($tglMulai)) : '—' ?>
                                            s/d
                                            <?= $tglAkhir ? date('d M Y', strtotime($tglAkhir)) : 'sekarang' ?>
                                        </strong>
                                    </p>
                                <?php else: ?>
                                    <p style="margin:2pt 0 0;font-size:8.5pt;color:#6b7280">Periode: <strong>Semua Waktu</strong></p>
                                <?php endif; ?>
                                <?php if ($statusFilter): ?>
                                    <p style="margin:2pt 0 0;font-size:8.5pt;color:#6b7280">Status: <strong><?= ucfirst($statusFilter) ?></strong></p>
                                <?php endif; ?>
                                <div style="margin-top:6pt;display:inline-block;background:#1e293b;color:#fff;padding:3pt 10pt;border-radius:4pt;font-size:8pt;font-weight:600;-webkit-print-color-adjust:exact;print-color-adjust:exact">
                                    No. Dok: STK-<?= date('Ymd') ?>-<?= str_pad($page, 3, '0', STR_PAD_LEFT) ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- PAGE HEADER -->
                <div class="page-header no-print">
                    <div>
                        <h1 class="page-title">Laporan Stok</h1>
                        <p class="page-subtitle">Ringkasan stok barang, nilai inventaris, dan riwayat stok masuk</p>
                    </div>
                    <div style="display:flex;gap:.75rem;align-items:center" class="no-print">
                        <button class="btn-print" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Cetak Laporan
                        </button>
                    </div>
                </div>

                <!-- ── KARTU STATISTIK ── -->
                <div class="no-print"
                    style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem">

                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(99,102,241,.12)">
                            <i class="fa-solid fa-boxes-stacked" style="color:var(--primary-color)"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Produk</p>
                            <p class="stat-value"><?= number_format($ringkasan['jumlah_produk']) ?></p>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(16,185,129,.12)">
                            <i class="fa-solid fa-cubes" style="color:#10b981"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Unit Stok</p>
                            <p class="stat-value"><?= number_format($ringkasan['total_stok']) ?></p>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(245,158,11,.12)">
                            <i class="fa-solid fa-money-bill-wave" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <p class="stat-label">Nilai Inventaris</p>
                            <p class="stat-value" style="font-size:.9rem">
                                <?= formatRupiah((float) $ringkasan['nilai_stok']) ?>
                            </p>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(239,68,68,.12)">
                            <i class="fa-solid fa-circle-xmark" style="color:#ef4444"></i>
                        </div>
                        <div>
                            <p class="stat-label">Stok Habis</p>
                            <p class="stat-value" style="color:#ef4444"><?= number_format($ringkasan['stok_habis']) ?></p>
                        </div>
                    </div>

                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(245,158,11,.12)">
                            <i class="fa-solid fa-circle-exclamation" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <p class="stat-label">Stok Terbatas</p>
                            <p class="stat-value" style="color:#f59e0b"><?= number_format($ringkasan['stok_terbatas']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- ── GRAFIK KATEGORI + RIWAYAT MASUK ── -->
                <div style="display:grid;grid-template-columns:1fr 340px;gap:1.25rem;margin-bottom:1.5rem" class="no-print">

                    <!-- Grafik nilai per kategori -->
                    <div class="card" style="padding:1.5rem">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
                            <h3 style="margin:0;font-size:.95rem">
                                <i class="fa-solid fa-chart-pie" style="color:var(--primary-color);margin-right:.4rem"></i>
                                Nilai Stok per Kategori
                            </h3>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="chartKategori"></canvas>
                        </div>
                    </div>

                    <!-- Riwayat stok masuk terbaru -->
                    <div class="card" style="padding:1.5rem">
                        <h3 style="margin:0 0 1.25rem;font-size:.95rem">
                            <i class="fa-solid fa-clock-rotate-left" style="color:#10b981;margin-right:.4rem"></i>
                            Stok Masuk Terbaru
                        </h3>
                        <?php if (empty($riwayatTerbaru)): ?>
                            <p style="color:var(--text-muted);font-size:.85rem;text-align:center;padding:1rem 0">Belum ada data.</p>
                        <?php else: ?>
                            <?php foreach ($riwayatTerbaru as $r): ?>
                                <div style="display:flex;align-items:center;gap:.8rem;margin-bottom:.85rem;padding-bottom:.85rem;border-bottom:1px solid var(--border-color)">
                                    <div class="activity-avatar" style="background:#10b981;flex-shrink:0;width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;font-weight:700">
                                        <?= initials($r['nama_barang']) ?>
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <p style="margin:0;font-size:.84rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                            <?= htmlspecialchars($r['nama_barang']) ?>
                                        </p>
                                        <p style="margin:0;font-size:.74rem;color:var(--text-muted)">
                                            <?= $r['keterangan'] ? htmlspecialchars(mb_strimwidth($r['keterangan'], 0, 28, '…')) : 'Tanpa keterangan' ?>
                                            · <?= date('d M Y', strtotime($r['created_at'])) ?>
                                        </p>
                                    </div>
                                    <div style="text-align:right;flex-shrink:0">
                                        <span style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:700;color:#10b981">
                                            +<?= number_format($r['jumlah']) ?>
                                        </span>
                                        <span style="font-size:.72rem;color:var(--text-muted);display:block">
                                            <?= htmlspecialchars($r['satuan']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── STOK PER KATEGORI (bar sederhana) ── -->
                <?php if (!empty($grafData)): ?>
                <div class="card no-print" style="padding:1.5rem;margin-bottom:1.5rem">
                    <h3 style="margin:0 0 1.25rem;font-size:.95rem">
                        <i class="fa-solid fa-ranking-star" style="color:#f59e0b;margin-right:.4rem"></i>
                        Nilai Stok per Kategori
                    </h3>
                    <?php
                    $maxNilai = max(array_column($grafData, 'nilai_stok')) ?: 1;
                    $colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];
                    foreach ($grafData as $idx => $g):
                        $pct = round(($g['nilai_stok'] / $maxNilai) * 100);
                        $c = $colors[$idx % count($colors)];
                        ?>
                        <div class="produk-bar-wrap">
                            <div class="produk-bar-label">
                                <span><?= htmlspecialchars($g['label']) ?></span>
                                <span style="color:var(--text-muted)"><?= number_format($g['total_stok']) ?> unit · <?= number_format($g['jumlah_produk']) ?> produk</span>
                            </div>
                            <div class="produk-bar-track">
                                <div class="produk-bar-fill" style="width:<?= $pct ?>%;background:<?= $c ?>"></div>
                            </div>
                            <div style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem;text-align:right">
                                <?= formatRupiah((float) $g['nilai_stok']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- ── FILTER ── -->
                <div class="card no-print" style="padding:1.25rem 1.5rem;margin-bottom:1.25rem">
                    <form method="GET" action="laporan_stok.php">
                        <div class="filter-row">
                            <div class="filter-group" style="flex:1;min-width:160px">
                                <label>Cari Produk</label>
                                <div class="search-mini" style="width:100%">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="q" placeholder="Nama, kategori, satuan…"
                                        value="<?= htmlspecialchars($search) ?>" style="width:100%" />
                                </div>
                            </div>
                            <div class="filter-group">
                                <label>Status Stok</label>
                                <select class="select-sm" name="status" onchange="this.form.submit()" style="min-width:140px">
                                    <option value="">Semua Status</option>
                                    <option value="tersedia" <?= $statusFilter === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                                    <option value="terbatas" <?= $statusFilter === 'terbatas' ? 'selected' : '' ?>>Terbatas</option>
                                    <option value="habis" <?= $statusFilter === 'habis' ? 'selected' : '' ?>>Habis</option>
                                </select>
                            </div>
                            <?php if (!empty($daftarKategori)): ?>
                            <div class="filter-group">
                                <label>Kategori</label>
                                <select class="select-sm" name="kategori" onchange="this.form.submit()" style="min-width:160px">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($daftarKategori as $k): ?>
                                        <option value="<?= htmlspecialchars($k) ?>" <?= $kategoriFilter === $k ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($k) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="filter-group">
                                <label>Filter Bulan (Dibuat)</label>
                                <select class="select-sm" name="bulan" onchange="this.form.submit()" style="min-width:160px">
                                    <option value="">Semua Bulan</option>
                                    <?php foreach ($daftarBulan as $b): ?>
                                        <option value="<?= $b['val'] ?>" <?= $bulanFilter === $b['val'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Tanggal Mulai</label>
                                <input type="date" name="tgl_mulai" class="select-sm"
                                    value="<?= htmlspecialchars($tglMulai) ?>" style="padding:.45rem .75rem" />
                            </div>
                            <div class="filter-group">
                                <label>Tanggal Akhir</label>
                                <input type="date" name="tgl_akhir" class="select-sm"
                                    value="<?= htmlspecialchars($tglAkhir) ?>" style="padding:.45rem .75rem" />
                            </div>
                            <div class="filter-group" style="flex-direction:row;gap:.5rem;align-items:flex-end">
                                <button type="submit" class="btn-print">
                                    <i class="fa-solid fa-filter"></i> Terapkan
                                </button>
                                <a href="laporan_stok.php" class="btn-print"
                                    style="background:rgba(239,68,68,.08);color:#ef4444;border-color:rgba(239,68,68,.3)">
                                    <i class="fa-solid fa-rotate-left"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ── TABEL STOK ── -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>
                            Daftar Stok Barang
                            <span style="color:var(--text-muted);font-weight:400;font-size:.85rem">
                                (<?= number_format($total) ?> produk)
                            </span>
                        </h3>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Produk</th>
                                    <th>Kategori</th>
                                    <th>Jumlah Stok</th>
                                    <th>Harga Satuan</th>
                                    <th>Nilai Stok</th>
                                    <th>Total Masuk</th>
                                    <th>Status</th>
                                    <th>Tgl. Ditambahkan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($produk)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align:center;padding:2.5rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-boxes-stacked"
                                                style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
                                            Tidak ada data stok untuk filter ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                    $imgDir = __DIR__ . '/../img/';
                                    $imgUrl = '../img/';
                                    foreach ($produk as $i => $p):
                                    ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:.75rem">
                                                    <?php if (!empty($p['gambar']) && file_exists($imgDir . $p['gambar'])): ?>
                                                        <img src="<?= $imgUrl . htmlspecialchars($p['gambar']) ?>"
                                                            alt="<?= htmlspecialchars($p['nama_barang']) ?>"
                                                            class="tbl-img" />
                                                    <?php else: ?>
                                                        <div class="activity-avatar"
                                                            style="background:#6366f1;flex-shrink:0;border-radius:8px">
                                                            <?= initials($p['nama_barang'] ?? '?') ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span style="font-weight:500;font-size:.88rem">
                                                        <?= htmlspecialchars($p['nama_barang'] ?? '—') ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td style="color:var(--text-muted);font-size:.84rem">
                                                <?= $p['kategori'] ? htmlspecialchars($p['kategori']) : '<span style="opacity:.4">—</span>' ?>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:700">
                                                <?= number_format($p['jumlah']) ?>
                                                <span style="color:var(--text-muted);font-weight:400;font-size:.76rem">
                                                    <?= htmlspecialchars($p['satuan']) ?>
                                                </span>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem">
                                                <?= formatRupiah((float) $p['harga_satuan']) ?>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:700;color:#6366f1">
                                                <?= formatRupiah((float) $p['jumlah'] * (float) $p['harga_satuan']) ?>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem;color:#10b981;font-weight:600">
                                                <?php if ($p['jumlah_restok'] > 0): ?>
                                                    +<?= number_format($p['total_masuk']) ?>
                                                    <span style="font-size:.72rem;color:var(--text-muted);font-weight:400;display:block">
                                                        <?= $p['jumlah_restok'] ?> kali restok
                                                    </span>
                                                <?php else: ?>
                                                    <span style="opacity:.4">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span style="<?= statusColor($p['status']) ?>;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600;text-transform:capitalize;display:inline-flex;align-items:center;gap:.3rem">
                                                    <i class="fa-solid <?= statusIcon($p['status']) ?>" style="font-size:.65rem"></i>
                                                    <?= htmlspecialchars($p['status']) ?>
                                                </span>
                                            </td>
                                            <td style="font-size:.82rem;color:var(--text-muted)">
                                                <?= date('d M Y', strtotime($p['created_at'])) ?>
                                                <span style="display:block;font-size:.74rem">
                                                    <?= date('H:i', strtotime($p['created_at'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sub-total row -->
                    <?php if (!empty($produk)): ?>
                        <div style="padding:.85rem 1.25rem;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;align-items:center;gap:1.5rem;background:rgba(99,102,241,.04)">
                            <span style="font-size:.85rem;color:var(--text-muted)">Nilai inventaris (halaman ini):</span>
                            <span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#6366f1">
                                <?php
                                $nilaiHalaman = array_sum(array_map(
                                    fn($p) => (float) $p['jumlah'] * (float) $p['harga_satuan'],
                                    $produk
                                ));
                                echo formatRupiah($nilaiHalaman);
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- ══ FOOTER TANDA TANGAN (hanya print) ══ -->
                    <div class="print-footer" style="margin-top:28pt;padding-top:14pt;border-top:1px solid #d1d5db">
                        <table style="width:100%;border-collapse:collapse">
                            <tr>
                                <td style="width:33%;text-align:center;vertical-align:top;padding:0 10pt">
                                    <p style="margin:0;font-size:8.5pt;color:#6b7280">Dibuat oleh,</p>
                                    <div style="height:42pt"></div>
                                    <div style="border-top:1px solid #374151;padding-top:4pt">
                                        <p style="margin:0;font-size:9pt;font-weight:700">
                                            <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?>
                                        </p>
                                        <p style="margin:0;font-size:8pt;color:#6b7280">Admin E-Mego</p>
                                    </div>
                                </td>
                                <td style="width:33%;text-align:center;vertical-align:top;padding:0 10pt">
                                    <p style="margin:0;font-size:8.5pt;color:#6b7280">Diketahui oleh,</p>
                                    <div style="height:42pt"></div>
                                    <div style="border-top:1px solid #374151;padding-top:4pt">
                                        <p style="margin:0;font-size:9pt;font-weight:700">(.............................)</p>
                                        <p style="margin:0;font-size:8pt;color:#6b7280">Manajer Operasional</p>
                                    </div>
                                </td>
                                <td style="width:33%;text-align:center;vertical-align:top;padding:0 10pt">
                                    <p style="margin:0;font-size:8.5pt;color:#6b7280">Disetujui oleh,</p>
                                    <div style="height:42pt"></div>
                                    <div style="border-top:1px solid #374151;padding-top:4pt">
                                        <p style="margin:0;font-size:9pt;font-weight:700">(.............................)</p>
                                        <p style="margin:0;font-size:8pt;color:#6b7280">Pemilik / Direktur</p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:16pt 0 0;font-size:7.5pt;color:#9ca3af;text-align:center">
                            Dokumen ini dicetak secara otomatis oleh sistem E-MEGO Hydroponic Management System
                            &nbsp;·&nbsp; <?= date('d F Y H:i') ?> WIB
                        </p>
                    </div>

                    <!-- PAGINATION -->
                    <?php if ($totalPage > 1): ?>
                        <div class="table-footer no-print">
                            <span class="table-info">
                                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> dari <?= $total ?> produk
                            </span>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&kategori=<?= urlencode($kategoriFilter) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>"
                                        class="page-btn">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($p = max(1, $page - 2); $p <= min($totalPage, $page + 2); $p++): ?>
                                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&kategori=<?= urlencode($kategoriFilter) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>"
                                        class="page-btn <?= $p === $page ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPage): ?>
                                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&kategori=<?= urlencode($kategoriFilter) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>"
                                        class="page-btn">
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

    <!-- Chart.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="../js/script.js"></script>
    <script>
        (() => {
            // ── Data grafik dari PHP ─────────────────────────────
            const grafLabels  = <?= json_encode(array_column($grafData, 'label')) ?>;
            const grafNilai   = <?= json_encode(array_map(fn($r) => (float) $r['nilai_stok'], $grafData)) ?>;
            const grafJumlah  = <?= json_encode(array_map(fn($r) => (int) $r['total_stok'], $grafData)) ?>;

            const palette = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#84cc16'];

            // ── Deteksi warna tema ───────────────────────────────
            const isDark  = () => document.documentElement.getAttribute('data-theme') === 'dark';
            const gridClr = () => isDark() ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
            const txtClr  = () => isDark() ? '#94a3b8' : '#64748b';

            const ctx = document.getElementById('chartKategori');
            if (!ctx) return;

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: grafLabels,
                    datasets: [{
                        label: 'Nilai Stok',
                        data: grafNilai,
                        backgroundColor: palette.map(c => c + '99'),
                        borderColor: palette,
                        borderWidth: 2,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => 'Rp ' + Number(ctx.parsed.y).toLocaleString('id-ID')
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
                                callback: v => 'Rp ' + (v / 1000).toLocaleString('id-ID') + 'K'
                            }
                        }
                    }
                }
            });

            // Perbarui warna chart saat tema berubah
            const themeBtn = document.getElementById('themeToggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    setTimeout(() => {
                        chart.options.scales.x.grid.color = gridClr();
                        chart.options.scales.x.ticks.color = txtClr();
                        chart.options.scales.y.grid.color = gridClr();
                        chart.options.scales.y.ticks.color = txtClr();
                        chart.update();
                    }, 100);
                });
            }
        })();
    </script>
</body>

</html>