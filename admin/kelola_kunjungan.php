<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$alert = '';
$alertType = '';

// ── HAPUS KUNJUNGAN ────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $hapusId = (int) $_GET['hapus'];
    $stmt = $pdo->prepare("DELETE FROM kunjungan WHERE id = ?");
    $stmt->execute([$hapusId]);
    $alert = 'Kunjungan berhasil dihapus.';
    $alertType = 'success';
}

// ── TAMBAH / EDIT KUNJUNGAN ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi              = $_POST['aksi'] ?? '';
    $editId            = (int) ($_POST['edit_id'] ?? 0);
    $nama_pengunjung   = trim($_POST['nama_pengunjung'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $tanggal_kunjungan = trim($_POST['tanggal_kunjungan'] ?? '');
    $tujuan            = trim($_POST['tujuan'] ?? '');
    $status            = trim($_POST['status'] ?? '');

    $validStatuses = ['pending', 'approved', 'rejected'];

    if (empty($nama_pengunjung) || empty($tanggal_kunjungan) || empty($tujuan) || !in_array($status, $validStatuses)) {
        $alert     = 'Data tidak valid. Periksa kembali isian form.';
        $alertType = 'danger';
    } else {
        if ($aksi === 'tambah') {
            $stmt = $pdo->prepare("INSERT INTO kunjungan (nama_pengunjung, email, tanggal_kunjungan, tujuan, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nama_pengunjung, $email ?: null, $tanggal_kunjungan, $tujuan, $status]);
            $alert     = 'Kunjungan baru berhasil ditambahkan.';
            $alertType = 'success';
        } elseif ($aksi === 'edit') {
            $stmt = $pdo->prepare("UPDATE kunjungan SET nama_pengunjung=?, email=?, tanggal_kunjungan=?, tujuan=?, status=? WHERE id=?");
            $stmt->execute([$nama_pengunjung, $email ?: null, $tanggal_kunjungan, $tujuan, $status, $editId]);
            $alert     = 'Data kunjungan berhasil diperbarui.';
            $alertType = 'success';
        }
    }
}

// ── AMBIL DATA KUNJUNGAN ───────────────────────────────────────────
$search     = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page       = max(1, (int) ($_GET['page'] ?? 1));
$limit      = 10;
$offset     = ($page - 1) * $limit;

$where  = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where    .= " AND (nama_pengunjung LIKE ? OR email LIKE ? OR tujuan LIKE ?)";
    $params[]  = "%$search%";
    $params[]  = "%$search%";
    $params[]  = "%$search%";
}
if (in_array($statusFilter, ['pending', 'approved', 'rejected'])) {
    $where   .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM kunjungan $where");
$totalStmt->execute($params);
$total     = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

$dataStmt = $pdo->prepare("SELECT id, nama_pengunjung, email, tanggal_kunjungan, tujuan, status, created_at FROM kunjungan $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$dataStmt->execute($params);
$kunjungan = $dataStmt->fetchAll();

// ── DATA EDIT — dipakai saat modal harus terbuka otomatis ─────
$editKunjungan = null;
if (isset($_GET['edit'])) {
    $stmt     = $pdo->prepare("SELECT id, nama_pengunjung, email, tanggal_kunjungan, tujuan, status FROM kunjungan WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editKunjungan = $stmt->fetch();
}

// Apakah modal harus terbuka otomatis (edit via URL atau POST gagal)
$bukaModal = $editKunjungan !== null || ($alertType === 'danger' && $_SERVER['REQUEST_METHOD'] === 'POST');

// Nilai awal form (jika POST gagal, isi ulang dari $_POST)
$formNamaPengunjung   = htmlspecialchars($editKunjungan['nama_pengunjung']  ?? $_POST['nama_pengunjung']  ?? '');
$formEmail            = htmlspecialchars($editKunjungan['email']            ?? $_POST['email']            ?? '');
$formTanggalKunjungan = $editKunjungan['tanggal_kunjungan'] ?? $_POST['tanggal_kunjungan'] ?? '';
$formTujuan           = htmlspecialchars($editKunjungan['tujuan']           ?? $_POST['tujuan']           ?? '');
$formStatus           = $editKunjungan['status'] ?? $_POST['status'] ?? '';
$formAksi             = $editKunjungan ? 'edit' : 'tambah';
$formId               = $editKunjungan['id'] ?? '';

// Helper: inisial dari nama
function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

// Helper: warna avatar berdasarkan status
function avatarColor(string $status): string
{
    return match ($status) {
        'approved' => '#10b981',
        'pending'  => '#f59e0b',
        'rejected' => '#ef4444',
        default    => '#64748b',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Kelola Kunjungan</title>
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
            width: min(680px, 100%);
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

        /* ── Input background ikut tema ── */
        .modal-body .form-input {
            background: var(--input-bg, #fff);
            color: var(--text-primary);
        }
    </style>
</head>

<body>

    <?php include '../layout/sidebar.php'; ?>

    <div class="overlay" id="overlay"></div>

    <!-- ══════════════ MODAL TAMBAH / EDIT ══════════════ -->
    <div id="modalBackdrop" class="<?= $bukaModal ? 'aktif' : '' ?>">
        <div id="modalBox" role="dialog" aria-modal="true" aria-labelledby="modalJudul">

            <!-- Header -->
            <div class="modal-header">
                <h3 id="modalJudul">
                    <i class="fa-solid <?= $editKunjungan ? 'fa-calendar-check' : 'fa-calendar-plus' ?>" id="modalIcon" style="color:var(--primary-color);"></i>
                    <span id="modalJudulText"><?= $editKunjungan ? 'Edit Kunjungan' : 'Tambah Kunjungan Baru' ?></span>
                </h3>
                <button type="button" class="icon-btn" id="btnTutupModal" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Form -->
            <form method="POST" action="kelola_kunjungan.php" id="kunjunganForm" class="modal-body">
                <input type="hidden" name="aksi"    id="inputAksi"   value="<?= $formAksi ?>">
                <input type="hidden" name="edit_id" id="inputEditId" value="<?= $formId ?>">

                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(270px,1fr)); gap:1.25rem; margin-bottom:1.25rem;">

                    <!-- Nama Pengunjung -->
                    <div class="form-group">
                        <label class="form-label" for="inputNamaPengunjung" style="display:block;margin-bottom:.5rem;font-weight:500;">Nama Pengunjung</label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-user" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <input class="form-input" type="text" name="nama_pengunjung" id="inputNamaPengunjung"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;"
                                placeholder="Nama pengunjung" value="<?= $formNamaPengunjung ?>" required />
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label" for="inputEmail" style="display:block;margin-bottom:.5rem;font-weight:500;">Alamat Email</label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-envelope" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <input class="form-input" type="email" name="email" id="inputEmail"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;"
                                placeholder="email@domain.com" value="<?= $formEmail ?>" />
                        </div>
                    </div>

                    <!-- Tanggal Kunjungan -->
                    <div class="form-group">
                        <label class="form-label" for="inputTanggalKunjungan" style="display:block;margin-bottom:.5rem;font-weight:500;">Tanggal Kunjungan</label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-calendar" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <input class="form-input" type="date" name="tanggal_kunjungan" id="inputTanggalKunjungan"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;"
                                value="<?= $formTanggalKunjungan ?>" required />
                        </div>
                    </div>

                    <!-- Tujuan -->
                    <div class="form-group">
                        <label class="form-label" for="inputTujuan" style="display:block;margin-bottom:.5rem;font-weight:500;">Tujuan Kunjungan</label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-map-marker-alt" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <input class="form-input" type="text" name="tujuan" id="inputTujuan"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;"
                                placeholder="Tujuan kunjungan" value="<?= $formTujuan ?>" required />
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label" for="inputStatus" style="display:block;margin-bottom:.5rem;font-weight:500;">Status</label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-info-circle" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <select class="form-input" name="status" id="inputStatus"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;appearance:none;cursor:pointer;">
                                <option value="pending"  <?= $formStatus === 'pending'  ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $formStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $formStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                    </div>

                </div><!-- /grid -->

                <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="btnBatalModal"
                        style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;">
                        <i class="fa-solid fa-xmark"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary"
                        style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span id="btnSimpanText"><?= $editKunjungan ? 'Simpan Perubahan' : 'Tambahkan Kunjungan' ?></span>
                    </button>
                </div>

            </form><!-- /form -->
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
                    <span class="breadcrumb-current">Kelola Kunjungan</span>
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
                        <h1 class="page-title">Kelola Kunjungan</h1>
                        <p class="page-subtitle">Tambah, edit, dan hapus data kunjungan</p>
                    </div>
                    <button class="btn btn-primary" id="btnTambah">
                        <i class="fa-solid fa-calendar-plus"></i> Tambah Kunjungan
                    </button>
                </div>

                <!-- ALERT -->
                <?php if ($alert): ?>
                    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?>" id="alertBox">
                        <i class="fa-solid fa-<?= $alertType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($alert) ?>
                    </div>
                <?php endif; ?>

                <!-- TABEL KUNJUNGAN -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Daftar Kunjungan <span style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?= $total ?> total)</span></h3>
                        <div class="table-controls">
                            <form method="GET" action="kelola_kunjungan.php" style="display:contents">
                                <div class="search-mini">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="q" placeholder="Cari nama, email, tujuan…" value="<?= htmlspecialchars($search) ?>" />
                                </div>
                                <select class="select-sm" name="status" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="pending"  <?= $statusFilter === 'pending'  ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Pengunjung</th>
                                    <th>Email</th>
                                    <th>Tanggal Kunjungan</th>
                                    <th>Tujuan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kunjungan)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-calendar-xmark" style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
                                            Tidak ada kunjungan ditemukan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($kunjungan as $i => $k): ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:.75rem">
                                                    <div class="activity-avatar" style="background:<?= avatarColor($k['status']) ?>;flex-shrink:0">
                                                        <?= initials($k['nama_pengunjung'] ?? '?') ?>
                                                    </div>
                                                    <span style="font-weight:500"><?= htmlspecialchars($k['nama_pengunjung'] ?? '—') ?></span>
                                                </div>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.8rem">
                                                <?= htmlspecialchars($k['email'] ?? '—') ?>
                                            </td>
                                            <td style="color:var(--text-muted);font-size:.82rem">
                                                <?= date('d M Y', strtotime($k['tanggal_kunjungan'])) ?>
                                            </td>
                                            <td style="font-weight:500">
                                                <?= htmlspecialchars($k['tujuan']) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeColor = match ($k['status']) {
                                                    'approved' => 'background:rgba(16,185,129,.15);color:#10b981',
                                                    'pending'  => 'background:rgba(245,158,11,.15);color:#f59e0b',
                                                    'rejected' => 'background:rgba(239,68,68,.15);color:#ef4444',
                                                    default    => '',
                                                };
                                                ?>
                                                <span style="<?= $badgeColor ?>;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600;text-transform:capitalize">
                                                    <?= htmlspecialchars($k['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:.4rem">
                                                    <!-- Tombol Edit — panggil modal via JS, bukan redirect -->
                                                    <button type="button"
                                                        class="btn btn-outline btn-edit"
                                                        style="padding:.35rem .7rem;font-size:.78rem"
                                                        title="Edit"
                                                        data-id="<?= $k['id'] ?>"
                                                        data-nama_pengunjung="<?= htmlspecialchars($k['nama_pengunjung'], ENT_QUOTES) ?>"
                                                        data-email="<?= htmlspecialchars($k['email'] ?? '', ENT_QUOTES) ?>"
                                                        data-tanggal_kunjungan="<?= htmlspecialchars($k['tanggal_kunjungan'], ENT_QUOTES) ?>"
                                                        data-tujuan="<?= htmlspecialchars($k['tujuan'], ENT_QUOTES) ?>"
                                                        data-status="<?= htmlspecialchars($k['status'], ENT_QUOTES) ?>">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>

                                                    <a href="kelola_kunjungan.php?hapus=<?= $k['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $statusFilter ? '&status='.$statusFilter : '' ?>"
                                                        class="btn"
                                                        style="padding:.35rem .7rem;font-size:.78rem;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3)"
                                                        title="Hapus"
                                                        onclick="return confirm('Yakin hapus kunjungan ini?')">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </a>
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
                </div><!-- /card table-card -->

            </section>
        </main>
    </div><!-- /main-wrapper -->

    <script src="../js/script.js"></script>
    <script>
    (() => {
        const backdrop     = document.getElementById('modalBackdrop');
        const modalBox     = document.getElementById('modalBox');
        const inputAksi    = document.getElementById('inputAksi');
        const inputEditId  = document.getElementById('inputEditId');
        const inputNamaPengunjung = document.getElementById('inputNamaPengunjung');
        const inputEmail   = document.getElementById('inputEmail');
        const inputTanggalKunjungan = document.getElementById('inputTanggalKunjungan');
        const inputTujuan  = document.getElementById('inputTujuan');
        const inputStatus  = document.getElementById('inputStatus');
        const modalIcon    = document.getElementById('modalIcon');
        const modalJudul   = document.getElementById('modalJudulText');
        const btnSimpan    = document.getElementById('btnSimpanText');

        // ── Buka modal ──────────────────────────────────────
        function bukaModal() {
            backdrop.classList.add('aktif');
            document.body.style.overflow = 'hidden';
            setTimeout(() => inputNamaPengunjung.focus(), 150);
        }

        // ── Tutup modal ─────────────────────────────────────
        function tutupModal() {
            backdrop.classList.remove('aktif');
            document.body.style.overflow = '';
        }

        // ── Klik backdrop (di luar modalBox) → tutup ────────
        backdrop.addEventListener('click', e => {
            if (!modalBox.contains(e.target)) tutupModal();
        });

        // ── ESC → tutup ──────────────────────────────────────
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && backdrop.classList.contains('aktif')) tutupModal();
        });

        // ── Tombol tutup & batal ─────────────────────────────
        document.getElementById('btnTutupModal').addEventListener('click', tutupModal);
        document.getElementById('btnBatalModal').addEventListener('click', tutupModal);

        // ── Reset form ke mode TAMBAH ────────────────────────
        function setModeTambah() {
            inputAksi.value   = 'tambah';
            inputEditId.value = '';
            inputNamaPengunjung.value = '';
            inputEmail.value  = '';
            inputTanggalKunjungan.value = '';
            inputTujuan.value = '';
            inputStatus.value = 'pending';

            modalIcon.className  = 'fa-solid fa-calendar-plus';
            modalJudul.textContent = 'Tambah Kunjungan Baru';
            btnSimpan.textContent  = 'Tambahkan Kunjungan';
        }

        // ── Tombol Tambah Kunjungan ───────────────────────────────
        document.getElementById('btnTambah').addEventListener('click', () => {
            setModeTambah();
            bukaModal();
        });

        // ── Tombol Edit (delegasi event ke semua .btn-edit) ──
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                inputAksi.value   = 'edit';
                inputEditId.value = btn.dataset.id;
                inputNamaPengunjung.value = btn.dataset.nama_pengunjung;
                inputEmail.value  = btn.dataset.email;
                inputTanggalKunjungan.value = btn.dataset.tanggal_kunjungan;
                inputTujuan.value = btn.dataset.tujuan;
                inputStatus.value = btn.dataset.status;

                modalIcon.className    = 'fa-solid fa-calendar-check';
                modalJudul.textContent = 'Edit Kunjungan';
                btnSimpan.textContent  = 'Simpan Perubahan';

                bukaModal();
            });
        });

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