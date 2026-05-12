<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ── FILTER ────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$tglMulai = $_GET['tgl_mulai'] ?? '';
$tglAkhir = $_GET['tgl_akhir'] ?? '';
$bulanFilter = $_GET['bulan'] ?? '';   // format: YYYY-MM
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$where = "WHERE t.status = 'divalidasi'";
$params = [];

if ($search !== '') {
    $where .= " AND (t.kode_transaksi LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($bulanFilter !== '') {
    $where .= " AND DATE_FORMAT(t.validated_at, '%Y-%m') = ?";
    $params[] = $bulanFilter;
} else {
    if ($tglMulai !== '') {
        $where .= " AND DATE(t.validated_at) >= ?";
        $params[] = $tglMulai;
    }
    if ($tglAkhir !== '') {
        $where .= " AND DATE(t.validated_at) <= ?";
        $params[] = $tglAkhir;
    }
}

// ── TOTAL COUNT ───────────────────────────────────────────────
$totalStmt = $pdo->prepare("
    SELECT COUNT(*) FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    $where
");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

// ── DATA TABEL ────────────────────────────────────────────────
$dataStmt = $pdo->prepare("
    SELECT t.id, t.kode_transaksi, t.total_harga, t.validated_at, t.created_at, t.catatan,
           u.name AS nama_user, u.email AS email_user
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    $where
    ORDER BY t.validated_at DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$transaksi = $dataStmt->fetchAll();

// ── RINGKASAN (scope filter) ──────────────────────────────────
$sumStmt = $pdo->prepare("
    SELECT
        COUNT(*)            AS jumlah_transaksi,
        COALESCE(SUM(t.total_harga), 0) AS total_pendapatan,
        COALESCE(AVG(t.total_harga), 0) AS rata_rata,
        COALESCE(MAX(t.total_harga), 0) AS tertinggi
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    $where
");
$sumStmt->execute($params);
$ringkasan = $sumStmt->fetch();

// ── TOP PRODUK (scope filter) ─────────────────────────────────
$topProdukWhere = str_replace('WHERE t.status', 'WHERE t.status', $where);
$topStmt = $pdo->prepare("
    SELECT s.nama_barang, s.satuan, s.kategori,
           SUM(ti.jumlah)                         AS total_qty,
           SUM(ti.jumlah * ti.harga_satuan)        AS total_nilai
    FROM transaksi_item ti
    JOIN transaksi t ON t.id = ti.transaksi_id
    JOIN produk s      ON s.id = ti.produk_id
    LEFT JOIN users u ON u.id = t.user_id
    $topProdukWhere
    GROUP BY ti.produk_id, s.nama_barang, s.satuan, s.kategori
    ORDER BY total_nilai DESC
    LIMIT 5
");
$topStmt->execute($params);
$topProduk = $topStmt->fetchAll();

// ── GRAFIK BULANAN (12 bulan terakhir, hanya divalidasi) ──────
$grafStmt = $pdo->query("
    SELECT DATE_FORMAT(validated_at, '%Y-%m') AS bulan,
           DATE_FORMAT(validated_at, '%b %Y') AS label,
           COUNT(*)                            AS jumlah,
           SUM(total_harga)                    AS pendapatan
    FROM transaksi
    WHERE status = 'divalidasi'
      AND validated_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(validated_at, '%Y-%m')
    ORDER BY bulan ASC
");
$grafData = $grafStmt->fetchAll();

// ── DAFTAR BULAN UNTUK FILTER ─────────────────────────────────
$bulanStmt = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(validated_at, '%Y-%m') AS val,
                    DATE_FORMAT(validated_at, '%M %Y')  AS label
    FROM transaksi
    WHERE status = 'divalidasi'
    ORDER BY val DESC
    LIMIT 24
");
$daftarBulan = $bulanStmt->fetchAll();

// ── HELPERS ───────────────────────────────────────────────────
function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1]))
        $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}
function formatRupiah(float $n): string
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Laporan Penjualan</title>
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

        /* ── Top produk bar ── */
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
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            transition: width .6s ease;
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

        /* ── Print styles ──
        @media print {
            .navbar, .sidebar, .overlay, .btn-print, .filter-row,
            .no-print, .pagination, .table-footer { display: none !important; }
            .main-wrapper { margin: 0 !important; padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        } */

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

            /* Sembunyikan elemen UI */
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

            /* Reset layout */
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

            /* Tampilkan kop */
            .print-kop {
                display: block !important;
            }

            /* Statistik grid saat print */
            /* .print-stat-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 10pt !important;
                margin-bottom: 14pt !important;
            }

            .print-stat-grid .card {
                padding: 10pt !important;
            } */

            .stat-label {
                font-size: 8pt !important;
                color: #6b7280 !important;
            }

            .stat-value {
                font-size: 12pt !important;
                font-weight: 700 !important;
            }

            /* Tabel */
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

            .activity-avatar {
                display: none !important;
            }

            /* Sub-total */
            .print-subtotal {
                background: #f0fdf4 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Footer tanda tangan */
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
                    <span class="breadcrumb-current">Laporan Penjualan</span>
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
                                    <!-- Logo / Inisial -->
                                    <div
                                        style="width:52pt;height:52pt;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:10pt;display:flex;align-items:center;justify-content:center;flex-shrink:0;-webkit-print-color-adjust:exact;print-color-adjust:exact">
                                        <span
                                            style="color:#fff;font-size:18pt;font-weight:800;font-family:sans-serif">EM</span>
                                    </div>
                                    <div>
                                        <p
                                            style="margin:0;font-size:18pt;font-weight:800;letter-spacing:-0.5pt;color:#1e293b;font-family:sans-serif">
                                            E-MEGO</p>
                                        <p
                                            style="margin:0;font-size:9pt;color:#6366f1;font-weight:600;letter-spacing:1.5pt;text-transform:uppercase">
                                            Hydroponic Management System</p>
                                        <p style="margin:3pt 0 0;font-size:8pt;color:#6b7280">Jl. Contoh No. 1, Kota
                                            Contoh, Indonesia</p>
                                        <p style="margin:1pt 0 0;font-size:8pt;color:#6b7280">Telp: (021) 0000-0000
                                            &nbsp;·&nbsp; Email: info@emego.id</p>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:right;vertical-align:top">
                                <p style="margin:0;font-size:14pt;font-weight:700;color:#1e293b;font-family:sans-serif">
                                    LAPORAN PENJUALAN</p>
                                <p style="margin:3pt 0 0;font-size:8.5pt;color:#6b7280">
                                    Dicetak oleh:
                                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>
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
                                    <p style="margin:2pt 0 0;font-size:8.5pt;color:#6b7280">Periode: <strong>Semua
                                            Waktu</strong></p>
                                <?php endif; ?>
                                <div
                                    style="margin-top:6pt;display:inline-block;background:#1e293b;color:#fff;padding:3pt 10pt;border-radius:4pt;font-size:8pt;font-weight:600;-webkit-print-color-adjust:exact;print-color-adjust:exact">
                                    No. Dok: LAP-<?= date('Ymd') ?>-<?= str_pad($page, 3, '0', STR_PAD_LEFT) ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="page-header no-print">
                    <div>
                        <h1 class="page-title">Laporan Penjualan</h1>
                        <p class="page-subtitle">Ringkasan pendapatan dan riwayat transaksi yang telah divalidasi</p>
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
                            <i class="fa-solid fa-money-bill-trend-up" style="color:var(--primary-color)"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Pendapatan</p>
                            <p class="stat-value" style="font-size:.95rem">
                                <?= formatRupiah((float) $ringkasan['total_pendapatan']) ?>
                            </p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(16,185,129,.12)">
                            <i class="fa-solid fa-circle-check" style="color:#10b981"></i>
                        </div>
                        <div>
                            <p class="stat-label">Transaksi Berhasil</p>
                            <p class="stat-value"><?= number_format($ringkasan['jumlah_transaksi']) ?></p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(245,158,11,.12)">
                            <i class="fa-solid fa-calculator" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <p class="stat-label">Rata-rata / Transaksi</p>
                            <p class="stat-value" style="font-size:.95rem">
                                <?= formatRupiah((float) $ringkasan['rata_rata']) ?>
                            </p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(139,92,246,.12)">
                            <i class="fa-solid fa-trophy" style="color:#8b5cf6"></i>
                        </div>
                        <div>
                            <p class="stat-label">Transaksi Tertinggi</p>
                            <p class="stat-value" style="font-size:.95rem">
                                <?= formatRupiah((float) $ringkasan['tertinggi']) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── GRAFIK + TOP PRODUK ── -->
                <div style="display:grid;grid-template-columns:1fr 340px;gap:1.25rem;margin-bottom:1.5rem"
                    class="no-print">

                    <!-- Grafik pendapatan bulanan -->
                    <div class="card" style="padding:1.5rem">
                        <div
                            style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
                            <h3 style="margin:0;font-size:.95rem">
                                <i class="fa-solid fa-chart-line"
                                    style="color:var(--primary-color);margin-right:.4rem"></i>
                                Pendapatan 12 Bulan Terakhir
                            </h3>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="chartBulanan"></canvas>
                        </div>
                    </div>

                    <!-- Top 5 Produk -->
                    <div class="card" style="padding:1.5rem">
                        <h3 style="margin:0 0 1.25rem;font-size:.95rem">
                            <i class="fa-solid fa-ranking-star" style="color:#f59e0b;margin-right:.4rem"></i>
                            Top 5 Produk Terlaris
                        </h3>
                        <?php if (empty($topProduk)): ?>
                            <p style="color:var(--text-muted);font-size:.85rem;text-align:center;padding:1rem 0">Belum ada
                                data.</p>
                        <?php else:
                            $maxNilai = max(array_column($topProduk, 'total_nilai')) ?: 1;
                            foreach ($topProduk as $idx => $prod):
                                $pct = round(($prod['total_nilai'] / $maxNilai) * 100);
                                $colors = ['#6366f1', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'];
                                $c = $colors[$idx] ?? '#6366f1';
                                ?>
                                <div class="produk-bar-wrap">
                                    <div class="produk-bar-label">
                                        <span><?= htmlspecialchars($prod['nama_barang']) ?></span>
                                        <span style="color:var(--text-muted)"><?= number_format($prod['total_qty']) ?>
                                            <?= htmlspecialchars($prod['satuan']) ?></span>
                                    </div>
                                    <div class="produk-bar-track">
                                        <div class="produk-bar-fill" style="width:<?= $pct ?>%;background:<?= $c ?>"></div>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem;text-align:right">
                                        <?= formatRupiah((float) $prod['total_nilai']) ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- ── FILTER ── -->
                <div class="card no-print" style="padding:1.25rem 1.5rem;margin-bottom:1.25rem">
                    <form method="GET" action="laporan_penjualan.php">
                        <div class="filter-row">
                            <div class="filter-group" style="flex:1;min-width:160px">
                                <label>Cari Transaksi</label>
                                <div class="search-mini" style="width:100%">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="q" placeholder="Kode / nama pemesan…"
                                        value="<?= htmlspecialchars($search) ?>" style="width:100%" />
                                </div>
                            </div>
                            <div class="filter-group">
                                <label>Filter Bulan</label>
                                <select class="select-sm" name="bulan" onchange="this.form.submit()"
                                    style="min-width:160px">
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
                                <a href="laporan_penjualan.php" class="btn-print"
                                    style="background:rgba(239,68,68,.08);color:#ef4444;border-color:rgba(239,68,68,.3)">
                                    <i class="fa-solid fa-rotate-left"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ── TABEL RIWAYAT ── -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>
                            Riwayat Penjualan
                            <span
                                style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?= number_format($total) ?>
                                transaksi)</span>
                        </h3>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Kode Transaksi</th>
                                    <th>Pemesan</th>
                                    <th>Total</th>
                                    <th>Tgl. Pesan</th>
                                    <!-- <th>Tgl. Validasi</th> -->
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transaksi)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-chart-simple"
                                                style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
                                            Tidak ada data penjualan untuk filter ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksi as $i => $t): ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:600">
                                                <?= htmlspecialchars($t['kode_transaksi'] ?? '#' . $t['id']) ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:.7rem">
                                                    <div class="activity-avatar" style="background:#6366f1;flex-shrink:0">
                                                        <?= initials($t['nama_user'] ?? '?') ?>
                                                    </div>
                                                    <div>
                                                        <span
                                                            style="font-weight:500;display:block;font-size:.88rem"><?= htmlspecialchars($t['nama_user'] ?? '—') ?></span>
                                                        <span
                                                            style="font-size:.74rem;color:var(--text-muted)"><?= htmlspecialchars($t['email_user'] ?? '') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                style="font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:700;color:#10b981">
                                                <?= formatRupiah((float) $t['total_harga']) ?>
                                            </td>
                                            <td style="font-size:.82rem;color:var(--text-muted)">
                                                <?= date('d M Y', strtotime($t['created_at'])) ?>
                                                <span
                                                    style="display:block;font-size:.74rem"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                                            </td>
                                            <!-- <td style="font-size:.82rem">
                                                <?php if ($t['validated_at']): ?>
                                                    <?= date('d M Y', strtotime($t['validated_at'])) ?>
                                                    <span
                                                        style="display:block;font-size:.74rem;color:var(--text-muted)"><?= date('H:i', strtotime($t['validated_at'])) ?></span>
                                                <?php else: ?>
                                                    <span style="color:var(--text-muted)">—</span>
                                                <?php endif; ?>
                                            </td> -->
                                            <td style="font-size:.82rem;color:var(--text-muted);max-width:180px">
                                                <?= $t['catatan'] ? htmlspecialchars(mb_strimwidth($t['catatan'], 0, 50, '…')) : '<span style="opacity:.4">—</span>' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sub-total row -->
                    <?php if (!empty($transaksi)): ?>
                        <div
                            style="padding:.85rem 1.25rem;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;align-items:center;gap:1rem;background:rgba(16,185,129,.04)">
                            <span style="font-size:.85rem;color:var(--text-muted)">Total pendapatan (halaman ini):</span>
                            <span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#10b981">
                                <?= formatRupiah(array_sum(array_column($transaksi, 'total_harga'))) ?>
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
                                        <p style="margin:0;font-size:9pt;font-weight:700">
                                            (.............................)</p>
                                        <p style="margin:0;font-size:8pt;color:#6b7280">Manajer Operasional</p>
                                    </div>
                                </td>
                                <td style="width:33%;text-align:center;vertical-align:top;padding:0 10pt">
                                    <p style="margin:0;font-size:8.5pt;color:#6b7280">Disetujui oleh,</p>
                                    <div style="height:42pt"></div>
                                    <div style="border-top:1px solid #374151;padding-top:4pt">
                                        <p style="margin:0;font-size:9pt;font-weight:700">
                                            (.............................)</p>
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
                                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> dari <?= $total ?>
                                transaksi
                            </span>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>"
                                        class="page-btn">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($p = max(1, $page - 2); $p <= min($totalPage, $page + 2); $p++): ?>
                                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>"
                                        class="page-btn <?= $p === $page ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPage): ?>
                                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>"
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
            const grafLabels = <?= json_encode(array_column($grafData, 'label')) ?>;
            const grafNilai = <?= json_encode(array_map(fn($r) => (float) $r['pendapatan'], $grafData)) ?>;

            // ── Deteksi warna tema ───────────────────────────────
            const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
            const gridClr = () => isDark() ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
            const txtClr = () => isDark() ? '#94a3b8' : '#64748b';

            const ctx = document.getElementById('chartBulanan');
            if (!ctx) return;

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: grafLabels,
                    datasets: [{
                        label: 'Pendapatan',
                        data: grafNilai,
                        backgroundColor: 'rgba(99,102,241,.55)',
                        borderColor: '#6366f1',
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