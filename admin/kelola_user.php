<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$alert = '';
$alertType = '';

// ── HAPUS USER ────────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $hapusId = (int) $_GET['hapus'];
    if ($hapusId === (int) $_SESSION['user_id']) {
        $alert = 'Tidak dapat menghapus akun Anda sendiri.';
        $alertType = 'danger';
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$hapusId]);
        $alert = 'User berhasil dihapus.';
        $alertType = 'success';
    }
}

// ── TAMBAH / EDIT USER ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi     = $_POST['aksi'] ?? '';
    $editId   = (int) ($_POST['edit_id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $validRoles = ['admin', 'pelanggan', 'pemilik'];

    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, $validRoles)) {
        $alert     = 'Data tidak valid. Periksa kembali isian form.';
        $alertType = 'danger';
    } else {
        if ($aksi === 'tambah') {
            if (empty($password)) {
                $alert     = 'Password wajib diisi untuk user baru.';
                $alertType = 'danger';
            } else {
                $cek = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $cek->execute([$email]);
                if ($cek->fetch()) {
                    $alert     = 'Email sudah digunakan oleh user lain.';
                    $alertType = 'danger';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hash, $role]);
                    $alert     = 'User baru berhasil ditambahkan.';
                    $alertType = 'success';
                }
            }
        } elseif ($aksi === 'edit') {
            $cek = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $cek->execute([$email, $editId]);
            if ($cek->fetch()) {
                $alert     = 'Email sudah digunakan oleh user lain.';
                $alertType = 'danger';
            } else {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role=? WHERE id=?");
                    $stmt->execute([$name, $email, $hash, $role, $editId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
                    $stmt->execute([$name, $email, $role, $editId]);
                }
                $alert     = 'Data user berhasil diperbarui.';
                $alertType = 'success';
            }
        }
    }
}

// ── AMBIL DATA USER ───────────────────────────────────────────
$search     = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$page       = max(1, (int) ($_GET['page'] ?? 1));
$limit      = 10;
$offset     = ($page - 1) * $limit;

$where  = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where    .= " AND (name LIKE ? OR email LIKE ?)";
    $params[]  = "%$search%";
    $params[]  = "%$search%";
}
if (in_array($roleFilter, ['admin', 'pelanggan', 'pemilik'])) {
    $where   .= " AND role = ?";
    $params[] = $roleFilter;
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$totalStmt->execute($params);
$total     = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

$dataStmt = $pdo->prepare("SELECT id, name, email, role, created_at FROM users $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$dataStmt->execute($params);
$users = $dataStmt->fetchAll();

// ── DATA EDIT — dipakai saat modal harus terbuka otomatis ─────
$editUser = null;
if (isset($_GET['edit'])) {
    $stmt     = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editUser = $stmt->fetch();
}

// Apakah modal harus terbuka otomatis (edit via URL atau POST gagal)
$bukaModal = $editUser !== null || ($alertType === 'danger' && $_SERVER['REQUEST_METHOD'] === 'POST');

// Nilai awal form (jika POST gagal, isi ulang dari $_POST)
$formName  = htmlspecialchars($editUser['name']  ?? $_POST['name']  ?? '');
$formEmail = htmlspecialchars($editUser['email'] ?? $_POST['email'] ?? '');
$formRole  = $editUser['role'] ?? $_POST['role'] ?? '';
$formAksi  = $editUser ? 'edit' : 'tambah';
$formId    = $editUser['id'] ?? '';

// Helper: inisial dari nama
function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

// Helper: warna avatar berdasarkan role
function avatarColor(string $role): string
{
    return match ($role) {
        'admin'    => '#10b981',
        'pemilik'  => '#8b5cf6',
        'pelanggan'=> '#f59e0b',
        default    => '#64748b',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Kelola User</title>
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
                    <i class="fa-solid <?= $editUser ? 'fa-user-pen' : 'fa-user-plus' ?>" id="modalIcon" style="color:var(--primary-color);"></i>
                    <span id="modalJudulText"><?= $editUser ? 'Edit User' : 'Tambah User Baru' ?></span>
                </h3>
                <button type="button" class="icon-btn" id="btnTutupModal" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Form -->
            <form method="POST" action="kelola_user.php" id="userForm" class="modal-body">
                <input type="hidden" name="aksi"    id="inputAksi"   value="<?= $formAksi ?>">
                <input type="hidden" name="edit_id" id="inputEditId" value="<?= $formId ?>">

                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(270px,1fr)); gap:1.25rem; margin-bottom:1.25rem;">

                    <!-- Nama -->
                    <div class="form-group">
                        <label class="form-label" for="inputName" style="display:block;margin-bottom:.5rem;font-weight:500;">Nama Lengkap</label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-user" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <input class="form-input" type="text" name="name" id="inputName"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;"
                                placeholder="Nama pengguna" value="<?= $formName ?>" required />
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label" for="inputEmail" style="display:block;margin-bottom:.5rem;font-weight:500;">Alamat Email</label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-envelope" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <input class="form-input" type="email" name="email" id="inputEmail"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;"
                                placeholder="email@domain.com" value="<?= $formEmail ?>" required />
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label class="form-label" for="inputPassword" id="labelPassword" style="display:block;margin-bottom:.5rem;font-weight:500;">
                            Password
                            <?php if ($editUser): ?>
                                <small style="color:var(--text-muted);font-weight:400;font-size:.7rem;">(Kosongkan jika tidak diubah)</small>
                            <?php endif; ?>
                        </label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-lock" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <input class="form-input" type="password" name="password" id="inputPassword"
                                style="width:100%;padding:.75rem 3rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;"
                                placeholder="••••••••" autocomplete="new-password" />
                            <button type="button" id="togglePassForm"
                                style="position:absolute;right:.5rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">
                                <i class="fa-solid fa-eye" id="eyeIconForm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label class="form-label" for="inputRole" style="display:block;margin-bottom:.5rem;font-weight:500;">Hak Akses (Role)</label>
                        <div class="input-wrap" style="position:relative;">
                            <i class="fa-solid fa-shield-halved" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none;"></i>
                            <select class="form-input" name="role" id="inputRole"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;appearance:none;cursor:pointer;">
                                <option value="admin"     <?= $formRole === 'admin'     ? 'selected' : '' ?>>Admin</option>
                                <option value="pemilik"   <?= $formRole === 'pemilik'   ? 'selected' : '' ?>>Pemilik</option>
                                <option value="pelanggan" <?= $formRole === 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
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
                        <span id="btnSimpanText"><?= $editUser ? 'Simpan Perubahan' : 'Daftarkan User' ?></span>
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
                    <span class="breadcrumb-current">Kelola User</span>
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
                        <a href="#" class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan</a>
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
                        <h1 class="page-title">Kelola User</h1>
                        <p class="page-subtitle">Tambah, edit, dan hapus pengguna sistem</p>
                    </div>
                    <button class="btn btn-primary" id="btnTambah">
                        <i class="fa-solid fa-user-plus"></i> Tambah User
                    </button>
                </div>

                <!-- ALERT -->
                <?php if ($alert): ?>
                    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?>" id="alertBox">
                        <i class="fa-solid fa-<?= $alertType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($alert) ?>
                    </div>
                <?php endif; ?>

                <!-- TABEL USER -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Daftar User <span style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?= $total ?> total)</span></h3>
                        <div class="table-controls">
                            <form method="GET" action="kelola_user.php" style="display:contents">
                                <div class="search-mini">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="q" placeholder="Cari nama / email…" value="<?= htmlspecialchars($search) ?>" />
                                </div>
                                <select class="select-sm" name="role" onchange="this.form.submit()">
                                    <option value="">Semua Role</option>
                                    <option value="admin"     <?= $roleFilter === 'admin'     ? 'selected' : '' ?>>Admin</option>
                                    <option value="pemilik"   <?= $roleFilter === 'pemilik'   ? 'selected' : '' ?>>Pemilik</option>
                                    <option value="pelanggan" <?= $roleFilter === 'pelanggan' ? 'selected' : '' ?>>Pelanggan</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Pengguna</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Bergabung</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-users-slash" style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
                                            Tidak ada user ditemukan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $i => $u): ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:.75rem">
                                                    <div class="activity-avatar" style="background:<?= avatarColor($u['role']) ?>;flex-shrink:0">
                                                        <?= initials($u['name'] ?? '?') ?>
                                                    </div>
                                                    <span style="font-weight:500"><?= htmlspecialchars($u['name'] ?? '—') ?></span>
                                                </div>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.8rem">
                                                <?= htmlspecialchars($u['email']) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeColor = match ($u['role']) {
                                                    'admin'     => 'background:rgba(16,185,129,.15);color:#10b981',
                                                    'pemilik'   => 'background:rgba(139,92,246,.15);color:#8b5cf6',
                                                    'pelanggan' => 'background:rgba(245,158,11,.15);color:#f59e0b',
                                                    default     => '',
                                                };
                                                ?>
                                                <span style="<?= $badgeColor ?>;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600;text-transform:capitalize">
                                                    <?= htmlspecialchars($u['role']) ?>
                                                </span>
                                            </td>
                                            <td style="color:var(--text-muted);font-size:.82rem">
                                                <?= date('d M Y', strtotime($u['created_at'])) ?>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:.4rem">
                                                    <!-- Tombol Edit — panggil modal via JS, bukan redirect -->
                                                    <button type="button"
                                                        class="btn btn-outline btn-edit"
                                                        style="padding:.35rem .7rem;font-size:.78rem"
                                                        title="Edit"
                                                        data-id="<?= $u['id'] ?>"
                                                        data-name="<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>"
                                                        data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
                                                        data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES) ?>">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>

                                                    <?php if ($u['id'] !== (int) $_SESSION['user_id']): ?>
                                                        <a href="kelola_user.php?hapus=<?= $u['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?><?= $roleFilter ? '&role='.$roleFilter : '' ?>"
                                                            class="btn"
                                                            style="padding:.35rem .7rem;font-size:.78rem;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3)"
                                                            title="Hapus"
                                                            onclick="return confirm('Yakin hapus user ini?')">
                                                            <i class="fa-solid fa-trash"></i>
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
                                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> dari <?= $total ?> user
                            </span>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&role=<?= $roleFilter ?>" class="page-btn">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPage; $p++): ?>
                                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&role=<?= $roleFilter ?>"
                                        class="page-btn <?= $p === $page ? 'active' : '' ?>">
                                        <?= $p ?>
                                    </a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPage): ?>
                                    <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&role=<?= $roleFilter ?>" class="page-btn">
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
        const inputName    = document.getElementById('inputName');
        const inputEmail   = document.getElementById('inputEmail');
        const inputPass    = document.getElementById('inputPassword');
        const inputRole    = document.getElementById('inputRole');
        const modalIcon    = document.getElementById('modalIcon');
        const modalJudul   = document.getElementById('modalJudulText');
        const btnSimpan    = document.getElementById('btnSimpanText');
        const labelPass    = document.getElementById('labelPassword');

        // ── Buka modal ──────────────────────────────────────
        function bukaModal() {
            backdrop.classList.add('aktif');
            document.body.style.overflow = 'hidden';
            setTimeout(() => inputName.focus(), 150);
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
            inputName.value   = '';
            inputEmail.value  = '';
            inputPass.value   = '';
            inputRole.value   = 'pelanggan';

            modalIcon.className  = 'fa-solid fa-user-plus';
            modalJudul.textContent = 'Tambah User Baru';
            btnSimpan.textContent  = 'Daftarkan User';

            // Label password — wajib, tanpa keterangan kosongkan
            labelPass.innerHTML = 'Password';
        }

        // ── Tombol Tambah User ───────────────────────────────
        document.getElementById('btnTambah').addEventListener('click', () => {
            setModeTambah();
            bukaModal();
        });

        // ── Tombol Edit (delegasi event ke semua .btn-edit) ──
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                inputAksi.value   = 'edit';
                inputEditId.value = btn.dataset.id;
                inputName.value   = btn.dataset.name;
                inputEmail.value  = btn.dataset.email;
                inputPass.value   = '';
                inputRole.value   = btn.dataset.role;

                modalIcon.className    = 'fa-solid fa-user-pen';
                modalJudul.textContent = 'Edit User';
                btnSimpan.textContent  = 'Simpan Perubahan';

                // Label password — opsional
                labelPass.innerHTML =
                    'Password <small style="color:var(--text-muted);font-weight:400;font-size:.7rem;">(Kosongkan jika tidak diubah)</small>';

                bukaModal();
            });
        });

        // ── Toggle show/hide password ────────────────────────
        document.getElementById('togglePassForm').addEventListener('click', () => {
            const show         = inputPass.type === 'password';
            inputPass.type     = show ? 'text' : 'password';
            document.getElementById('eyeIconForm').className =
                show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
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