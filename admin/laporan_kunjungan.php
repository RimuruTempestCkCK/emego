<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// ── FILTER ────────────────────────────────────────────────────
$search      = trim($_GET['q'] ?? '');
$tglMulai    = $_GET['tgl_mulai'] ?? '';
$tglAkhir    = $_GET['tgl_akhir'] ?? '';
$bulanFilter = $_GET['bulan'] ?? '';      // format: YYYY-MM
$statusFilter = $_GET['status'] ?? '';    // pending | approved | rejected
$kehadiranFilter = $_GET['kehadiran'] ?? ''; // hadir | tidak_hadir | belum
$page        = max(1, (int) ($_GET['page'] ?? 1));
$limit       = 15;
$offset      = ($page - 1) * $limit;

$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (k.nama_pengunjung LIKE ? OR k.email LIKE ? OR k.tujuan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $where .= " AND k.status = ?";
    $params[] = $statusFilter;
}

if ($kehadiranFilter !== '') {
    if ($kehadiranFilter === 'belum') {
        $where .= " AND k.kehadiran IS NULL AND k.status = 'approved'";
    } else {
        $where .= " AND k.kehadiran = ?";
        $params[] = $kehadiranFilter;
    }
}

if ($bulanFilter !== '') {
    $where .= " AND DATE_FORMAT(k.tanggal_kunjungan, '%Y-%m') = ?";
    $params[] = $bulanFilter;
} else {
    if ($tglMulai !== '') {
        $where .= " AND k.tanggal_kunjungan >= ?";
        $params[] = $tglMulai;
    }
    if ($tglAkhir !== '') {
        $where .= " AND k.tanggal_kunjungan <= ?";
        $params[] = $tglAkhir;
    }
}

// ── TOTAL COUNT ───────────────────────────────────────────────
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM kunjungan k $where");
$totalStmt->execute($params);
$total     = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

// ── DATA TABEL ────────────────────────────────────────────────
$dataStmt = $pdo->prepare("
    SELECT k.id, k.nama_pengunjung, k.email, k.tanggal_kunjungan,
           k.shift, k.jam, k.jumlah_orang, k.tujuan, k.status, k.kehadiran, k.created_at
    FROM kunjungan k
    $where
    ORDER BY k.tanggal_kunjungan DESC, k.created_at DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$kunjungan = $dataStmt->fetchAll();

// ── RINGKASAN (scope filter) ──────────────────────────────────
$sumStmt = $pdo->prepare("
    SELECT
        COUNT(*)                                        AS total_kunjungan,
        COALESCE(SUM(k.jumlah_orang), 0)               AS total_pengunjung,
        COALESCE(AVG(k.jumlah_orang), 0)               AS rata_rata_orang,
        SUM(k.status = 'approved')                     AS jumlah_approved,
        SUM(k.status = 'pending')                      AS jumlah_pending,
        SUM(k.status = 'rejected')                     AS jumlah_rejected,
        SUM(k.kehadiran = 'hadir')                     AS jumlah_hadir,
        SUM(k.kehadiran = 'tidak_hadir')               AS jumlah_tidak_hadir
    FROM kunjungan k
    $where
");
$sumStmt->execute($params);
$ringkasan = $sumStmt->fetch();

// ── TOP TUJUAN ────────────────────────────────────────────────
$topTujuanStmt = $pdo->prepare("
    SELECT k.tujuan,
           COUNT(*)              AS jumlah_kunjungan,
           SUM(k.jumlah_orang)   AS total_orang
    FROM kunjungan k
    $where
    GROUP BY k.tujuan
    ORDER BY jumlah_kunjungan DESC
    LIMIT 5
");
$topTujuanStmt->execute($params);
$topTujuan = $topTujuanStmt->fetchAll();

// ── GRAFIK BULANAN (12 bulan terakhir) ────────────────────────
$grafStmt = $pdo->query("
    SELECT DATE_FORMAT(tanggal_kunjungan, '%Y-%m') AS bulan,
           DATE_FORMAT(tanggal_kunjungan, '%b %Y') AS label,
           COUNT(*)                                 AS jumlah,
           SUM(jumlah_orang)                        AS total_orang
    FROM kunjungan
    WHERE tanggal_kunjungan >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal_kunjungan, '%Y-%m')
    ORDER BY bulan ASC
");
$grafData = $grafStmt->fetchAll();

// ── DAFTAR BULAN UNTUK FILTER ─────────────────────────────────
$bulanStmt = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(tanggal_kunjungan, '%Y-%m') AS val,
                    DATE_FORMAT(tanggal_kunjungan, '%M %Y')  AS label
    FROM kunjungan
    ORDER BY val DESC
    LIMIT 24
");
$daftarBulan = $bulanStmt->fetchAll();

// ── HELPERS ───────────────────────────────────────────────────
function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1]))
        $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

function statusBadge(string $status): string
{
    return match ($status) {
        'approved' => '<span style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;border-radius:20px;font-size:.74rem;font-weight:600;background:rgba(16,185,129,.12);color:#10b981"><i class="fa-solid fa-circle-check" style="font-size:.65rem"></i>Approved</span>',
        'rejected' => '<span style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;border-radius:20px;font-size:.74rem;font-weight:600;background:rgba(239,68,68,.12);color:#ef4444"><i class="fa-solid fa-circle-xmark" style="font-size:.65rem"></i>Rejected</span>',
        default    => '<span style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;border-radius:20px;font-size:.74rem;font-weight:600;background:rgba(245,158,11,.12);color:#f59e0b"><i class="fa-solid fa-clock" style="font-size:.65rem"></i>Pending</span>',
    };
}

function shiftBadge(string $shift): string
{
    return match ($shift) {
        'pagi'  => '<span style="padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:600;background:rgba(99,102,241,.12);color:#6366f1">🌅 Pagi</span>',
        'siang' => '<span style="padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:600;background:rgba(245,158,11,.12);color:#f59e0b">☀️ Siang</span>',
        default => htmlspecialchars($shift),
    };
}

function kehadiranBadge(?string $kehadiran, string $status): string
{
    if ($status !== 'approved') return '<span style="color:var(--text-muted);font-size:.7rem;font-style:italic">N/A</span>';
    
    if ($kehadiran === 'hadir') {
        return '<span style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;border-radius:20px;font-size:.74rem;font-weight:600;background:rgba(16,185,129,.12);color:#10b981"><i class="fa-solid fa-user-check" style="font-size:.65rem"></i>Hadir</span>';
    } elseif ($kehadiran === 'tidak_hadir') {
        return '<span style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;border-radius:20px;font-size:.74rem;font-weight:600;background:rgba(239,68,68,.12);color:#ef4444"><i class="fa-solid fa-user-xmark" style="font-size:.65rem"></i>Tidak Hadir</span>';
    }
    return '<span style="display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .65rem;border-radius:20px;font-size:.74rem;font-weight:600;background:rgba(100,116,139,.12);color:#64748b"><i class="fa-solid fa-clock-rotate-left" style="font-size:.65rem"></i>Belum Absen</span>';
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Laporan Kunjungan</title>
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

        /* ── Top tujuan bar ── */
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

            .activity-avatar {
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
                    <span class="breadcrumb-current">Laporan Kunjungan</span>
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
                                    LAPORAN KUNJUNGAN</p>
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
                                <?php if ($statusFilter): ?>
                                    <p style="margin:2pt 0 0;font-size:8.5pt;color:#6b7280">
                                        Status: <strong><?= ucfirst($statusFilter) ?></strong>
                                    </p>
                                <?php endif; ?>
                                <div
                                    style="margin-top:6pt;display:inline-block;background:#1e293b;color:#fff;padding:3pt 10pt;border-radius:4pt;font-size:8pt;font-weight:600;-webkit-print-color-adjust:exact;print-color-adjust:exact">
                                    No. Dok: KUN-<?= date('Ymd') ?>-<?= str_pad($page, 3, '0', STR_PAD_LEFT) ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="page-header no-print">
                    <div>
                        <h1 class="page-title">Laporan Kunjungan</h1>
                        <p class="page-subtitle">Ringkasan dan riwayat kunjungan tamu ke fasilitas E-MEGO</p>
                    </div>
                    <div style="display:flex;gap:.75rem;align-items:center" class="no-print">
                        <button class="btn-print" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Cetak Laporan
                        </button>
                    </div>
                </div>

                <!-- ── KARTU STATISTIK ── -->
                <div class="no-print"
                    style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem">
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(99,102,241,.12)">
                            <i class="fa-solid fa-calendar-days" style="color:var(--primary-color)"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Kunjungan</p>
                            <p class="stat-value"><?= number_format((int) $ringkasan['total_kunjungan']) ?></p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(16,185,129,.12)">
                            <i class="fa-solid fa-users" style="color:#10b981"></i>
                        </div>
                        <div>
                            <p class="stat-label">Total Pengunjung</p>
                            <p class="stat-value"><?= number_format((int) $ringkasan['total_pengunjung']) ?></p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(16,185,129,.12)">
                            <i class="fa-solid fa-user-check" style="color:#10b981"></i>
                        </div>
                        <div>
                            <p class="stat-label">Hadir</p>
                            <p class="stat-value" style="color:#10b981"><?= number_format((int) $ringkasan['jumlah_hadir']) ?></p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(239,68,68,.12)">
                            <i class="fa-solid fa-user-xmark" style="color:#ef4444"></i>
                        </div>
                        <div>
                            <p class="stat-label">Tidak Hadir</p>
                            <p class="stat-value" style="color:#ef4444"><?= number_format((int) $ringkasan['jumlah_tidak_hadir']) ?></p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(245,158,11,.12)">
                            <i class="fa-solid fa-circle-check" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <p class="stat-label">Disetujui</p>
                            <p class="stat-value" style="color:#10b981"><?= number_format((int) $ringkasan['jumlah_approved']) ?></p>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="stat-icon" style="background:rgba(245,158,11,.12)">
                            <i class="fa-solid fa-clock" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <p class="stat-label">Pending</p>
                            <p class="stat-value" style="color:#f59e0b"><?= number_format((int) $ringkasan['jumlah_pending']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- ── GRAFIK + TOP TUJUAN ── -->
                <div style="display:grid;grid-template-columns:1fr 340px;gap:1.25rem;margin-bottom:1.5rem"
                    class="no-print">

                    <!-- Grafik kunjungan bulanan -->
                    <div class="card" style="padding:1.5rem">
                        <div
                            style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
                            <h3 style="margin:0;font-size:.95rem">
                                <i class="fa-solid fa-chart-bar"
                                    style="color:var(--primary-color);margin-right:.4rem"></i>
                                Kunjungan 12 Bulan Terakhir
                            </h3>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="chartBulanan"></canvas>
                        </div>
                    </div>

                    <!-- Top 5 Tujuan Kunjungan -->
                    <div class="card" style="padding:1.5rem">
                        <h3 style="margin:0 0 1.25rem;font-size:.95rem">
                            <i class="fa-solid fa-bullseye" style="color:#f59e0b;margin-right:.4rem"></i>
                            Top 5 Tujuan Kunjungan
                        </h3>
                        <?php if (empty($topTujuan)): ?>
                            <p style="color:var(--text-muted);font-size:.85rem;text-align:center;padding:1rem 0">Belum ada
                                data.</p>
                        <?php else:
                            $maxJml = max(array_column($topTujuan, 'jumlah_kunjungan')) ?: 1;
                            foreach ($topTujuan as $idx => $tj):
                                $pct = round(($tj['jumlah_kunjungan'] / $maxJml) * 100);
                                $colors = ['#6366f1', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'];
                                $c = $colors[$idx] ?? '#6366f1';
                                ?>
                                <div class="produk-bar-wrap">
                                    <div class="produk-bar-label">
                                        <span
                                            style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px"
                                            title="<?= htmlspecialchars($tj['tujuan']) ?>">
                                            <?= htmlspecialchars(mb_strimwidth($tj['tujuan'], 0, 28, '…')) ?>
                                        </span>
                                        <span style="color:var(--text-muted);white-space:nowrap">
                                            <?= number_format($tj['jumlah_kunjungan']) ?> kunjungan
                                        </span>
                                    </div>
                                    <div class="produk-bar-track">
                                        <div class="produk-bar-fill" style="width:<?= $pct ?>%;background:<?= $c ?>"></div>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--text-muted);margin-top:.2rem;text-align:right">
                                        <?= number_format($tj['total_orang']) ?> orang total
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- ── FILTER ── -->
                <div class="card no-print" style="padding:1.25rem 1.5rem;margin-bottom:1.25rem">
                    <form method="GET" action="laporan_kunjungan.php">
                        <div class="filter-row">
                            <div class="filter-group" style="flex:1;min-width:160px">
                                <label>Cari Kunjungan</label>
                                <div class="search-mini" style="width:100%">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="q" placeholder="Nama / email / tujuan…"
                                        value="<?= htmlspecialchars($search) ?>" style="width:100%" />
                                </div>
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select class="select-sm" name="status" onchange="this.form.submit()"
                                    style="min-width:140px">
                                    <option value="">Semua Status</option>
                                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Kehadiran</label>
                                <select class="select-sm" name="kehadiran" onchange="this.form.submit()"
                                    style="min-width:140px">
                                    <option value="">Semua Kehadiran</option>
                                    <option value="hadir" <?= $kehadiranFilter === 'hadir' ? 'selected' : '' ?>>Hadir</option>
                                    <option value="tidak_hadir" <?= $kehadiranFilter === 'tidak_hadir' ? 'selected' : '' ?>>Tidak Hadir</option>
                                    <option value="belum" <?= $kehadiranFilter === 'belum' ? 'selected' : '' ?>>Belum Absen</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Filter Bulan</label>
                                <select class="select-sm" name="bulan" onchange="this.form.submit()"
                                    style="min-width:160px">
                                    <option value="">Semua Bulan</option>
                                    <?php foreach ($daftarBulan as $b): ?>
                                        <option value="<?= $b['val'] ?>"
                                            <?= $bulanFilter === $b['val'] ? 'selected' : '' ?>>
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
                                <a href="laporan_kunjungan.php" class="btn-print"
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
                            Riwayat Kunjungan
                            <span
                                style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?= number_format($total) ?>
                                kunjungan)</span>
                        </h3>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Pengunjung</th>
                                    <th>Tujuan</th>
                                    <th>Tgl. Kunjungan</th>
                                    <th>Shift / Jam</th>
                                    <th>Jml. Orang</th>
                                    <th>Status</th>
                                    <th>Kehadiran</th>
                                    <th>Tgl. Daftar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kunjungan)): ?>
                                    <tr>
                                        <td colspan="9"
                                            style="text-align:center;padding:2.5rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-calendar-xmark"
                                                style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
                                            Tidak ada data kunjungan untuk filter ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($kunjungan as $i => $k): ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:.7rem">
                                                    <div class="activity-avatar"
                                                        style="background:#6366f1;flex-shrink:0">
                                                        <?= initials($k['nama_pengunjung']) ?>
                                                    </div>
                                                    <div>
                                                        <span
                                                            style="font-weight:500;display:block;font-size:.88rem"><?= htmlspecialchars($k['nama_pengunjung']) ?></span>
                                                        <span
                                                            style="font-size:.74rem;color:var(--text-muted)"><?= htmlspecialchars($k['email'] ?? '') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="font-size:.82rem;max-width:200px">
                                                <?= htmlspecialchars(mb_strimwidth($k['tujuan'], 0, 55, '…')) ?>
                                            </td>
                                            <td style="font-size:.82rem;font-weight:600">
                                                <?= date('d M Y', strtotime($k['tanggal_kunjungan'])) ?>
                                            </td>
                                            <td>
                                                <?= shiftBadge($k['shift']) ?>
                                                <span
                                                    style="display:block;font-size:.74rem;color:var(--text-muted);margin-top:.2rem"><?= htmlspecialchars($k['jam']) ?></span>
                                            </td>
                                            <td style="text-align:center">
                                                <span
                                                    style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:.9rem"><?= number_format($k['jumlah_orang']) ?></span>
                                                <span style="font-size:.72rem;color:var(--text-muted);display:block">orang</span>
                                            </td>
                                            <td><?= statusBadge($k['status']) ?></td>
                                            <td><?= kehadiranBadge($k['kehadiran'], $k['status']) ?></td>
                                            <td style="font-size:.82rem;color:var(--text-muted)">
                                                <?= date('d M Y', strtotime($k['created_at'])) ?>
                                                <span
                                                    style="display:block;font-size:.74rem"><?= date('H:i', strtotime($k['created_at'])) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sub-total row -->
                    <?php if (!empty($kunjungan)): ?>
                        <div
                            style="padding:.85rem 1.25rem;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;align-items:center;gap:1rem;background:rgba(99,102,241,.04)">
                            <span style="font-size:.85rem;color:var(--text-muted)">Total pengunjung (halaman ini):</span>
                            <span
                                style="font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--primary-color)">
                                <?= number_format(array_sum(array_column($kunjungan, 'jumlah_orang'))) ?> orang
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
                                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> dari <?= $total ?>
                                kunjungan
                            </span>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>&kehadiran=<?= urlencode($kehadiranFilter) ?>"
                                        class="page-btn">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($p = max(1, $page - 2); $p <= min($totalPage, $page + 2); $p++): ?>
                                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>&kehadiran=<?= urlencode($kehadiranFilter) ?>"
                                        class="page-btn <?= $p === $page ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPage): ?>
                                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&bulan=<?= urlencode($bulanFilter) ?>&tgl_mulai=<?= urlencode($tglMulai) ?>&tgl_akhir=<?= urlencode($tglAkhir) ?>&kehadiran=<?= urlencode($kehadiranFilter) ?>"
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
            const grafJumlah  = <?= json_encode(array_map(fn($r) => (int) $r['jumlah'], $grafData)) ?>;
            const grafOrang   = <?= json_encode(array_map(fn($r) => (int) $r['total_orang'], $grafData)) ?>;

            // ── Deteksi warna tema ───────────────────────────────
            const isDark  = () => document.documentElement.getAttribute('data-theme') === 'dark';
            const gridClr = () => isDark() ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
            const txtClr  = () => isDark() ? '#94a3b8' : '#64748b';

            const ctx = document.getElementById('chartBulanan');
            if (!ctx) return;

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: grafLabels,
                    datasets: [
                        {
                            label: 'Jumlah Kunjungan',
                            data: grafJumlah,
                            backgroundColor: 'rgba(99,102,241,.55)',
                            borderColor: '#6366f1',
                            borderWidth: 2,
                            borderRadius: 6,
                            borderSkipped: false,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Total Pengunjung',
                            data: grafOrang,
                            type: 'line',
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,.15)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#10b981',
                            tension: 0.35,
                            fill: true,
                            yAxisID: 'y2',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                color: txtClr(),
                                font: { family: 'Sora', size: 11 },
                                boxWidth: 12,
                                padding: 14,
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.dataset.label + ': ' + Number(ctx.parsed.y).toLocaleString('id-ID')
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: gridClr() },
                            ticks: { color: txtClr(), font: { family: 'Sora', size: 11 } }
                        },
                        y: {
                            position: 'left',
                            grid: { color: gridClr() },
                            ticks: {
                                color: txtClr(),
                                font: { family: 'JetBrains Mono', size: 10 },
                                stepSize: 1,
                            },
                            title: { display: true, text: 'Kunjungan', color: txtClr(), font: { size: 10 } }
                        },
                        y2: {
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                color: '#10b981',
                                font: { family: 'JetBrains Mono', size: 10 },
                                stepSize: 1,
                            },
                            title: { display: true, text: 'Orang', color: '#10b981', font: { size: 10 } }
                        }
                    }
                }
            });

            // Perbarui warna chart saat tema berubah
            const themeBtn = document.getElementById('themeToggle');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    setTimeout(() => {
                        chart.options.scales.x.grid.color  = gridClr();
                        chart.options.scales.x.ticks.color = txtClr();
                        chart.options.scales.y.grid.color  = gridClr();
                        chart.options.scales.y.ticks.color = txtClr();
                        chart.options.plugins.legend.labels.color = txtClr();
                        chart.update();
                    }, 100);
                });
            }
        })();
    </script>
</body>

</html>