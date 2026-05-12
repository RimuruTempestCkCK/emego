<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$alert = '';
$alertType = '';

// ── Folder gambar ─────────────────────────────────────────────
$imgDir = __DIR__ . '/../img/';
$imgUrl = '../img/';

// ── HAPUS PRODUK ──────────────────────────────────────────────
if (isset($_GET['hapus'])) {
    $hapusId = (int) $_GET['hapus'];
    $stmtGbr = $pdo->prepare("SELECT gambar FROM produk WHERE id = ?");
    $stmtGbr->execute([$hapusId]);
    $hapusGambar = $stmtGbr->fetchColumn();

    $pdo->prepare("DELETE FROM produk WHERE id = ?")->execute([$hapusId]);

    if ($hapusGambar && file_exists($imgDir . $hapusGambar)) {
        unlink($imgDir . $hapusGambar);
    }
    $alert = 'Data produk berhasil dihapus.';
    $alertType = 'success';
}

// ── TAMBAH STOK MASUK ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_stok') {
    $produkId = (int) ($_POST['produk_id'] ?? 0);
    $jumlahMasuk = (int) ($_POST['jumlah_masuk'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');
    $adminId = (int) ($_SESSION['user_id'] ?? 0);

    if ($produkId <= 0 || $jumlahMasuk <= 0) {
        $alert = 'Jumlah stok harus lebih dari 0.';
        $alertType = 'danger';
    } else {
        // Catat riwayat
        $pdo->prepare("INSERT INTO stok_masuk (produk_id, jumlah, keterangan, admin_id) VALUES (?, ?, ?, ?)")
            ->execute([$produkId, $jumlahMasuk, $keterangan ?: null, $adminId ?: null]);

        // Update jumlah di tabel produk (cache)
        $pdo->prepare("UPDATE produk SET jumlah = jumlah + ?,
            status = CASE
                WHEN jumlah + ? > 10 THEN 'tersedia'
                WHEN jumlah + ? > 0  THEN 'terbatas'
                ELSE 'habis'
            END
            WHERE id = ?")
            ->execute([$jumlahMasuk, $jumlahMasuk, $jumlahMasuk, $produkId]);

        $alert = 'Stok berhasil ditambahkan.';
        $alertType = 'success';
    }
}

// ── TAMBAH / EDIT PRODUK ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['aksi'] ?? '', ['tambah', 'edit'])) {
    $aksi = $_POST['aksi'];
    $editId = (int) ($_POST['edit_id'] ?? 0);
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $satuan = trim($_POST['satuan'] ?? '');
    $harga_satuan = (float) str_replace(',', '.', $_POST['harga_satuan'] ?? '0');
    $status = trim($_POST['status'] ?? '');

    $validStatuses = ['tersedia', 'habis', 'terbatas'];

    if (empty($nama_barang) || empty($satuan) || !in_array($status, $validStatuses)) {
        $alert = 'Data tidak valid. Periksa kembali isian form.';
        $alertType = 'danger';
    } else {
        $namaGambarBaru = null;
        $uploadError = '';

        if (!empty($_FILES['gambar']['name'])) {
            $file = $_FILES['gambar'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $maxSize = 2 * 1024 * 1024;

            if (!in_array($ext, $allowed)) {
                $uploadError = 'Format gambar tidak didukung.';
            } elseif ($file['size'] > $maxSize) {
                $uploadError = 'Ukuran gambar melebihi 2 MB.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $uploadError = 'Gagal mengupload gambar.';
            } else {
                if (!is_dir($imgDir))
                    mkdir($imgDir, 0755, true);
                $namaGambarBaru = 'produk_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $imgDir . $namaGambarBaru)) {
                    $uploadError = 'Gagal menyimpan gambar.';
                    $namaGambarBaru = null;
                }
            }
        }

        if ($uploadError !== '') {
            $alert = $uploadError;
            $alertType = 'danger';
        } else {
            if ($aksi === 'tambah') {
                // Produk baru: jumlah = 0, status = habis (stok diisi via tambah stok)
                $pdo->prepare("INSERT INTO produk (nama_barang, kategori, jumlah, satuan, harga_satuan, status, gambar) VALUES (?, ?, 0, ?, ?, ?, ?)")
                    ->execute([$nama_barang, $kategori ?: null, $satuan, $harga_satuan, $status, $namaGambarBaru]);
                $alert = 'Produk baru berhasil ditambahkan. Silakan tambah stok via tombol Stok.';
                $alertType = 'success';
            } elseif ($aksi === 'edit') {
                $stmtLama = $pdo->prepare("SELECT gambar FROM produk WHERE id = ?");
                $stmtLama->execute([$editId]);
                $gambarLama = $stmtLama->fetchColumn();

                if ($namaGambarBaru !== null) {
                    if ($gambarLama && file_exists($imgDir . $gambarLama))
                        unlink($imgDir . $gambarLama);
                    $gambarFinal = $namaGambarBaru;
                } else {
                    $gambarFinal = $gambarLama;
                }

                // Edit tidak mengubah jumlah — jumlah dikelola via stok_masuk
                $pdo->prepare("UPDATE produk SET nama_barang=?, kategori=?, satuan=?, harga_satuan=?, status=?, gambar=? WHERE id=?")
                    ->execute([$nama_barang, $kategori ?: null, $satuan, $harga_satuan, $status, $gambarFinal, $editId]);
                $alert = 'Data produk berhasil diperbarui.';
                $alertType = 'success';
            }
        }
    }
}

// ── AMBIL DATA PRODUK ─────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (nama_barang LIKE ? OR kategori LIKE ? OR satuan LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (in_array($statusFilter, ['tersedia', 'habis', 'terbatas'])) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM produk $where");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();
$totalPage = (int) ceil($total / $limit);

$dataStmt = $pdo->prepare("SELECT id, nama_barang, kategori, jumlah, satuan, harga_satuan, status, gambar, created_at FROM produk $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$dataStmt->execute($params);
$produk = $dataStmt->fetchAll();

// ── DATA EDIT ─────────────────────────────────────────────────
$editProduk = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT id, nama_barang, kategori, jumlah, satuan, harga_satuan, status, gambar FROM produk WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editProduk = $stmt->fetch();
}

// ── DATA STOK MODAL (produk yang diklik tombol Stok) ──────────
$stokProdukId = (int) ($_GET['stok'] ?? 0);
$stokProdukData = null;
$riwayatStok = [];
if ($stokProdukId > 0) {
    $stmt = $pdo->prepare("SELECT id, nama_barang, jumlah, satuan FROM produk WHERE id = ?");
    $stmt->execute([$stokProdukId]);
    $stokProdukData = $stmt->fetch();

    if ($stokProdukData) {
        $riwayatStmt = $pdo->prepare("
            SELECT sm.jumlah, sm.keterangan, sm.created_at,
                   u.name AS admin_name
            FROM stok_masuk sm
            LEFT JOIN users u ON u.id = sm.admin_id
            WHERE sm.produk_id = ?
            ORDER BY sm.created_at DESC
            LIMIT 20
        ");
        $riwayatStmt->execute([$stokProdukId]);
        $riwayatStok = $riwayatStmt->fetchAll();
    }
}

$bukaModalProduk = $editProduk !== null || ($alertType === 'danger' && isset($_POST['aksi']) && in_array($_POST['aksi'], ['tambah', 'edit']));
$bukaModalStok = $stokProdukData !== null || ($alertType === 'danger' && ($_POST['aksi'] ?? '') === 'tambah_stok');

// Nilai form produk
$formNamaBarang = htmlspecialchars($editProduk['nama_barang'] ?? $_POST['nama_barang'] ?? '');
$formKategori = htmlspecialchars($editProduk['kategori'] ?? $_POST['kategori'] ?? '');
$formSatuan = htmlspecialchars($editProduk['satuan'] ?? $_POST['satuan'] ?? '');
$formHargaSatuan = $editProduk['harga_satuan'] ?? $_POST['harga_satuan'] ?? 0;
$formStatus = $editProduk['status'] ?? $_POST['status'] ?? '';
$formGambar = $editProduk['gambar'] ?? null;
$formAksi = $editProduk ? 'edit' : 'tambah';
$formId = $editProduk['id'] ?? '';

function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1]))
        $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}
function avatarColor(string $status): string
{
    return match ($status) {
        'tersedia' => '#10b981', 'terbatas' => '#f59e0b', 'habis' => '#ef4444', default => '#64748b',
    };
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
    <title>E-MEGO — Kelola Produk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />
    <style>
        /* ── Shared Modal Backdrop ── */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 900;
            background: rgba(0, 0, 0, .55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-backdrop.aktif {
            display: flex;
        }

        .modal-box {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            width: min(680px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .25);
            animation: modalMasuk .25s cubic-bezier(.34, 1.2, .64, 1) both;
        }

        .modal-box.modal-sm {
            width: min(480px, 100%);
        }

        @keyframes modalMasuk {
            from {
                opacity: 0;
                transform: translateY(-24px) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            border-radius: 16px 16px 0 0;
            background: rgba(0, 0, 0, .02);
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

        .modal-body {
            padding: 1.75rem 1.5rem;
        }

        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
            margin-top: .5rem;
        }

        .modal-body .form-input {
            background: var(--input-bg, #fff);
            color: var(--text-primary);
        }

        /* ── Upload area ── */
        .upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
            background: var(--input-bg, #fafafa);
        }

        .upload-area:hover,
        .upload-area.drag-over {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, .05);
        }

        .upload-area input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-preview {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            display: block;
            margin: 0 auto .75rem;
        }

        .upload-placeholder {
            color: var(--text-muted);
            font-size: .85rem;
        }

        .upload-placeholder i {
            font-size: 2rem;
            display: block;
            margin-bottom: .4rem;
            opacity: .5;
        }

        .tbl-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        /* ── Stok modal info bar ── */
        .stok-info-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: .85rem 1.1rem;
            background: rgba(99, 102, 241, .07);
            border-radius: 10px;
            margin-bottom: 1.25rem;
        }

        .stok-info-bar .stok-nama {
            font-weight: 600;
            font-size: .95rem;
        }

        .stok-info-bar .stok-jumlah {
            margin-left: auto;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary-color);
        }

        /* ── Riwayat stok tabel dalam modal ── */
        .riwayat-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: .83rem;
            margin-top: 1rem;
        }

        .riwayat-tbl th {
            background: var(--table-header-bg, #f1f5f9);
            padding: .55rem .9rem;
            text-align: left;
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
        }

        .riwayat-tbl td {
            padding: .55rem .9rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .riwayat-tbl tbody tr:last-child td {
            border-bottom: none;
        }

        /* ── Tombol stok ── */
        .btn-stok {
            padding: .35rem .7rem;
            font-size: .78rem;
            background: rgba(16, 185, 129, .12);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, .3);
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            text-decoration: none;
            transition: background .15s;
        }

        .btn-stok:hover {
            background: rgba(16, 185, 129, .22);
        }
    </style>
</head>

<body>

    <?php include '../layout/sidebar.php'; ?>
    <div class="overlay" id="overlay"></div>

    <!-- ══════════════ MODAL TAMBAH / EDIT PRODUK ══════════════ -->
    <div id="modalProdukBackdrop" class="modal-backdrop <?= $bukaModalProduk ? 'aktif' : '' ?>">
        <div id="modalProdukBox" class="modal-box" role="dialog" aria-modal="true">

            <div class="modal-header">
                <h3>
                    <i class="fa-solid <?= $editProduk ? 'fa-boxes-stacked' : 'fa-box-open' ?>" id="modalIcon"
                        style="color:var(--primary-color)"></i>
                    <span id="modalJudulText"><?= $editProduk ? 'Edit Produk' : 'Tambah Produk Baru' ?></span>
                </h3>
                <button type="button" class="icon-btn" id="btnTutupModalProduk"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>

            <form method="POST" action="kelola_produk.php" id="produkForm" class="modal-body"
                enctype="multipart/form-data">
                <input type="hidden" name="aksi" id="inputAksi" value="<?= $formAksi ?>">
                <input type="hidden" name="edit_id" id="inputEditId" value="<?= $formId ?>">

                <div
                    style="display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:1.25rem;margin-bottom:1.25rem">

                    <!-- Nama Barang -->
                    <div class="form-group">
                        <label class="form-label" style="display:block;margin-bottom:.5rem;font-weight:500">Nama
                            Barang</label>
                        <div style="position:relative">
                            <i class="fa-solid fa-box"
                                style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                            <input class="form-input" type="text" name="nama_barang" id="inputNamaBarang"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit"
                                placeholder="Nama barang" value="<?= $formNamaBarang ?>" required />
                        </div>
                    </div>

                    <!-- Kategori -->
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block;margin-bottom:.5rem;font-weight:500">Kategori</label>
                        <div style="position:relative">
                            <i class="fa-solid fa-tag"
                                style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                            <input class="form-input" type="text" name="kategori" id="inputKategori"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit"
                                placeholder="Kategori (opsional)" value="<?= $formKategori ?>" />
                        </div>
                    </div>

                    <!-- Satuan -->
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block;margin-bottom:.5rem;font-weight:500">Satuan</label>
                        <div style="position:relative">
                            <i class="fa-solid fa-ruler"
                                style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                            <input class="form-input" type="text" name="satuan" id="inputSatuan"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit"
                                placeholder="pcs, kg, ikat…" value="<?= $formSatuan ?>" required />
                        </div>
                    </div>

                    <!-- Harga Satuan -->
                    <div class="form-group">
                        <label class="form-label" style="display:block;margin-bottom:.5rem;font-weight:500">Harga Satuan
                            (Rp)</label>
                        <div style="position:relative">
                            <i class="fa-solid fa-money-bill"
                                style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                            <input class="form-input" type="number" name="harga_satuan" id="inputHargaSatuan" min="0"
                                step="100"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit"
                                placeholder="0" value="<?= $formHargaSatuan ?>" required />
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label class="form-label"
                            style="display:block;margin-bottom:.5rem;font-weight:500">Status</label>
                        <div style="position:relative">
                            <i class="fa-solid fa-info-circle"
                                style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                            <select class="form-input" name="status" id="inputStatus"
                                style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;appearance:none;cursor:pointer">
                                <option value="tersedia" <?= $formStatus === 'tersedia' ? 'selected' : '' ?>>Tersedia
                                </option>
                                <option value="terbatas" <?= $formStatus === 'terbatas' ? 'selected' : '' ?>>Terbatas
                                </option>
                                <option value="habis" <?= $formStatus === 'habis' ? 'selected' : '' ?>>Habis</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Upload Gambar -->
                <div class="form-group" style="margin-bottom:1.5rem">
                    <label class="form-label" style="display:block;margin-bottom:.5rem;font-weight:500">
                        <i class="fa-solid fa-image" style="color:var(--text-muted);margin-right:.35rem"></i>
                        Gambar <span style="color:var(--text-muted);font-weight:400;font-size:.82rem">(opsional · maks.
                            2 MB · JPG/PNG/WEBP/GIF)</span>
                    </label>
                    <div class="upload-area" id="uploadArea">
                        <input type="file" name="gambar" id="inputGambar"
                            accept="image/jpeg,image/png,image/webp,image/gif" />
                        <?php if ($formGambar && $formAksi === 'edit'): ?>
                            <img src="<?= $imgUrl . htmlspecialchars($formGambar) ?>" alt="" class="upload-preview"
                                id="previewImg" />
                            <div class="upload-placeholder" id="uploadPlaceholder" style="display:none">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Klik atau seret untuk mengganti
                            </div>
                        <?php else: ?>
                            <img src="" alt="" class="upload-preview" id="previewImg" style="display:none" />
                            <div class="upload-placeholder" id="uploadPlaceholder">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Klik atau seret gambar ke sini
                            </div>
                        <?php endif; ?>
                    </div>
                    <p id="namaFile" style="margin-top:.4rem;font-size:.8rem;color:var(--text-muted)"></p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="btnBatalModalProduk"
                        style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem">
                        <i class="fa-solid fa-xmark"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary"
                        style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span id="btnSimpanText"><?= $editProduk ? 'Simpan Perubahan' : 'Tambahkan Produk' ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ══════════════ MODAL TAMBAH STOK ══════════════ -->
    <div id="modalStokBackdrop" class="modal-backdrop <?= $bukaModalStok ? 'aktif' : '' ?>">
        <div id="modalStokBox" class="modal-box" role="dialog" aria-modal="true">

            <div class="modal-header">
                <h3>
                    <i class="fa-solid fa-boxes-stacked" style="color:#10b981"></i>
                    <span>Kelola Stok —
                        <span id="stokNamaProduk"><?= htmlspecialchars($stokProdukData['nama_barang'] ?? '') ?></span>
                    </span>
                </h3>
                <button type="button" class="icon-btn" id="btnTutupModalStok"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="modal-body">

                <!-- Info stok saat ini -->
                <div class="stok-info-bar">
                    <i class="fa-solid fa-layer-group" style="color:var(--primary-color);font-size:1.3rem"></i>
                    <div>
                        <p style="margin:0;font-size:.74rem;color:var(--text-muted)">Stok saat ini</p>
                        <p class="stok-nama" id="stokNamaDisplay">
                            <?= htmlspecialchars($stokProdukData['nama_barang'] ?? '') ?></p>
                    </div>
                    <div class="stok-jumlah" id="stokJumlahDisplay">
                        <?= number_format($stokProdukData['jumlah'] ?? 0) ?>
                        <span style="font-size:.75rem;font-weight:400;color:var(--text-muted);margin-left:.2rem"
                            id="stokSatuanDisplay">
                            <?= htmlspecialchars($stokProdukData['satuan'] ?? '') ?>
                        </span>
                    </div>
                </div>

                <!-- Form tambah stok -->
                <form method="POST" action="kelola_produk.php" id="stokForm">
                    <input type="hidden" name="aksi" value="tambah_stok">
                    <input type="hidden" name="produk_id" id="inputStokProdukId" value="<?= $stokProdukId ?>">

                    <?php
                    // Ambil kembali stok_produk_id dari POST jika ada error
                    $stokIdPost = (int) ($_POST['produk_id'] ?? $stokProdukId);
                    ?>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
                        <div class="form-group">
                            <label class="form-label" style="display:block;margin-bottom:.5rem;font-weight:500">
                                Jumlah Tambah <span style="color:#ef4444">*</span>
                            </label>
                            <div style="position:relative">
                                <i class="fa-solid fa-plus"
                                    style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#10b981;pointer-events:none"></i>
                                <input class="form-input" type="number" name="jumlah_masuk" id="inputJumlahMasuk"
                                    min="1" placeholder="0"
                                    style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit"
                                    value="<?= ($alertType === 'danger' && ($_POST['aksi'] ?? '') === 'tambah_stok') ? (int) ($_POST['jumlah_masuk'] ?? '') : '' ?>"
                                    required />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"
                                style="display:block;margin-bottom:.5rem;font-weight:500">Keterangan</label>
                            <div style="position:relative">
                                <i class="fa-solid fa-note-sticky"
                                    style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                                <input class="form-input" type="text" name="keterangan" id="inputKeterangan"
                                    placeholder="Mis: Panen, Restock…"
                                    style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit"
                                    value="<?= htmlspecialchars(($_POST['aksi'] ?? '') === 'tambah_stok' ? ($_POST['keterangan'] ?? '') : '') ?>" />
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer" style="margin-bottom:1.5rem">
                        <button type="button" class="btn btn-outline" id="btnBatalModalStok"
                            style="display:flex;align-items:center;gap:.5rem;padding:.65rem 1.25rem">
                            <i class="fa-solid fa-xmark"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-primary"
                            style="display:flex;align-items:center;gap:.5rem;padding:.65rem 1.25rem;background:#10b981;border-color:#10b981">
                            <i class="fa-solid fa-plus"></i> Tambah Stok
                        </button>
                    </div>
                </form>

                <!-- Riwayat penambahan stok -->
                <div>
                    <h4 style="margin:0 0 .75rem;font-size:.9rem;display:flex;align-items:center;gap:.45rem">
                        <i class="fa-solid fa-clock-rotate-left" style="color:var(--text-muted)"></i>
                        Riwayat Penambahan Stok
                    </h4>

                    <div id="riwayatStokWrap"
                        style="max-height:260px;overflow-y:auto;border:1px solid var(--border-color);border-radius:10px">
                        <?php if (empty($riwayatStok)): ?>
                            <p style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:.85rem;margin:0">
                                <i class="fa-solid fa-inbox"
                                    style="display:block;font-size:1.5rem;margin-bottom:.4rem;opacity:.3"></i>
                                Belum ada riwayat stok masuk.
                            </p>
                        <?php else: ?>
                            <table class="riwayat-tbl">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Jumlah</th>
                                        <th>Keterangan</th>
                                        <th>Oleh</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody id="riwayatStokBody">
                                    <?php foreach ($riwayatStok as $idx => $r): ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $idx + 1 ?></td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#10b981">
                                                +<?= number_format($r['jumlah']) ?>
                                            </td>
                                            <td style="color:var(--text-muted)">
                                                <?= $r['keterangan'] ? htmlspecialchars($r['keterangan']) : '<span style="opacity:.4">—</span>' ?>
                                            </td>
                                            <td style="font-size:.78rem"><?= htmlspecialchars($r['admin_name'] ?? '—') ?></td>
                                            <td style="font-size:.78rem;color:var(--text-muted)">
                                                <?= date('d M Y', strtotime($r['created_at'])) ?>
                                                <span
                                                    style="display:block;font-size:.72rem"><?= date('H:i', strtotime($r['created_at'])) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- /modal-body -->
        </div>
    </div>
    <!-- ══════════════════════════════════════════════════ -->

    <div class="main-wrapper" id="mainWrapper">

        <!-- NAVBAR -->
        <header class="navbar">
            <div class="navbar-left">
                <button class="btn-toggle" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
                <div class="breadcrumb">
                    <span class="breadcrumb-root">E-MEGO</span>
                    <i class="fa-solid fa-chevron-right"></i>
                    <span class="breadcrumb-current">Kelola Produk</span>
                </div>
            </div>
            <div class="navbar-center"></div>
            <div class="navbar-right">
                <button class="icon-btn" title="Mode Gelap" id="themeToggle"><i class="fa-solid fa-moon"></i></button>
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

        <main class="content" id="contentArea">
            <section class="page active">

                <div class="page-header">
                    <div>
                        <h1 class="page-title">Kelola Produk</h1>
                        <p class="page-subtitle">Tambah, edit, hapus produk · Stok dikelola via tombol
                            <strong>Stok</strong></p>
                    </div>
                    <button class="btn btn-primary" id="btnTambah">
                        <i class="fa-solid fa-box-open"></i> Tambah Produk
                    </button>
                </div>

                <?php if ($alert): ?>
                    <div class="alert alert-<?= $alertType === 'success' ? 'success' : 'danger' ?>" id="alertBox">
                        <i class="fa-solid fa-<?= $alertType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
                        <?= htmlspecialchars($alert) ?>
                    </div>
                <?php endif; ?>

                <!-- TABEL PRODUK -->
                <div class="card table-card">
                    <div class="card-header">
                        <h3>Daftar Produk <span
                                style="color:var(--text-muted);font-weight:400;font-size:.85rem">(<?= $total ?>
                                total)</span></h3>
                        <div class="table-controls">
                            <form method="GET" action="kelola_produk.php" style="display:contents">
                                <div class="search-mini">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                    <input type="text" name="q" placeholder="Cari nama, kategori, satuan…"
                                        value="<?= htmlspecialchars($search) ?>" />
                                </div>
                                <select class="select-sm" name="status" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="tersedia" <?= $statusFilter === 'tersedia' ? 'selected' : '' ?>>Tersedia
                                    </option>
                                    <option value="terbatas" <?= $statusFilter === 'terbatas' ? 'selected' : '' ?>>Terbatas
                                    </option>
                                    <option value="habis" <?= $statusFilter === 'habis' ? 'selected' : '' ?>>Habis</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nama Barang</th>
                                    <th>Kategori</th>
                                    <th>Jumlah Stok</th>
                                    <th>Harga Satuan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($produk)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted)">
                                            <i class="fa-solid fa-box-open"
                                                style="font-size:2rem;margin-bottom:.5rem;display:block;opacity:.3"></i>
                                            Tidak ada data produk ditemukan.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($produk as $i => $s): ?>
                                        <tr>
                                            <td style="color:var(--text-muted)"><?= $offset + $i + 1 ?></td>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:.75rem">
                                                    <?php if (!empty($s['gambar']) && file_exists($imgDir . $s['gambar'])): ?>
                                                        <img src="<?= $imgUrl . htmlspecialchars($s['gambar']) ?>"
                                                            alt="<?= htmlspecialchars($s['nama_barang']) ?>" class="tbl-img" />
                                                    <?php else: ?>
                                                        <div class="activity-avatar"
                                                            style="background:<?= avatarColor($s['status']) ?>;flex-shrink:0">
                                                            <?= initials($s['nama_barang'] ?? '?') ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span
                                                        style="font-weight:500"><?= htmlspecialchars($s['nama_barang'] ?? '—') ?></span>
                                                </div>
                                            </td>
                                            <td style="color:var(--text-muted)"><?= htmlspecialchars($s['kategori'] ?? '—') ?>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:600">
                                                <?= number_format($s['jumlah']) ?>
                                                <span
                                                    style="color:var(--text-muted);font-weight:400;font-size:.78rem"><?= htmlspecialchars($s['satuan']) ?></span>
                                            </td>
                                            <td style="font-family:'JetBrains Mono',monospace;font-size:.82rem">
                                                <?= formatRupiah((float) $s['harga_satuan']) ?></td>
                                            <td>
                                                <?php $badgeColor = match ($s['status']) {
                                                    'tersedia' => 'background:rgba(16,185,129,.15);color:#10b981',
                                                    'terbatas' => 'background:rgba(245,158,11,.15);color:#f59e0b',
                                                    'habis' => 'background:rgba(239,68,68,.15);color:#ef4444',
                                                    default => '',
                                                }; ?>
                                                <span
                                                    style="<?= $badgeColor ?>;padding:.25rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600;text-transform:capitalize">
                                                    <?= htmlspecialchars($s['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                                                    <!-- Tombol Stok -->
                                                    <button type="button" class="btn-stok btn-kelola-stok" title="Kelola Stok"
                                                        data-id="<?= $s['id'] ?>"
                                                        data-nama="<?= htmlspecialchars($s['nama_barang'], ENT_QUOTES) ?>"
                                                        data-jumlah="<?= $s['jumlah'] ?>"
                                                        data-satuan="<?= htmlspecialchars($s['satuan'], ENT_QUOTES) ?>">
                                                        <i class="fa-solid fa-cubes-stacked"></i> Stok
                                                    </button>

                                                    <!-- Tombol Edit -->
                                                    <button type="button" class="btn btn-outline btn-edit"
                                                        style="padding:.35rem .7rem;font-size:.78rem" title="Edit"
                                                        data-id="<?= $s['id'] ?>"
                                                        data-nama_barang="<?= htmlspecialchars($s['nama_barang'], ENT_QUOTES) ?>"
                                                        data-kategori="<?= htmlspecialchars($s['kategori'] ?? '', ENT_QUOTES) ?>"
                                                        data-satuan="<?= htmlspecialchars($s['satuan'], ENT_QUOTES) ?>"
                                                        data-harga_satuan="<?= $s['harga_satuan'] ?>"
                                                        data-status="<?= htmlspecialchars($s['status'], ENT_QUOTES) ?>"
                                                        data-gambar="<?= htmlspecialchars($s['gambar'] ?? '', ENT_QUOTES) ?>">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>

                                                    <!-- Tombol Hapus -->
                                                    <a href="kelola_produk.php?hapus=<?= $s['id'] ?><?= $search ? '&q=' . urlencode($search) : '' ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?>"
                                                        class="btn"
                                                        style="padding:.35rem .7rem;font-size:.78rem;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3)"
                                                        title="Hapus"
                                                        onclick="return confirm('Yakin hapus produk ini? Riwayat stok juga akan terhapus.')">
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

                    <?php if ($totalPage > 1): ?>
                        <div class="table-footer">
                            <span class="table-info">
                                Menampilkan <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> dari <?= $total ?>
                                produk
                            </span>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>"
                                        class="page-btn">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $totalPage; $p++): ?>
                                    <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>"
                                        class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                                <?php endfor; ?>
                                <?php if ($page < $totalPage): ?>
                                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= $statusFilter ?>"
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

    <script src="../js/script.js"></script>
    <script>
        (() => {
            // ── Elemen Modal Produk ───────────────────────────────────
            const backdropProduk = document.getElementById('modalProdukBackdrop');
            const boxProduk = document.getElementById('modalProdukBox');
            const inputAksi = document.getElementById('inputAksi');
            const inputEditId = document.getElementById('inputEditId');
            const inputNamaBarang = document.getElementById('inputNamaBarang');
            const inputKategori = document.getElementById('inputKategori');
            const inputSatuan = document.getElementById('inputSatuan');
            const inputHargaSatuan = document.getElementById('inputHargaSatuan');
            const inputStatus = document.getElementById('inputStatus');
            const inputGambar = document.getElementById('inputGambar');
            const previewImg = document.getElementById('previewImg');
            const uploadPlaceholder = document.getElementById('uploadPlaceholder');
            const namaFile = document.getElementById('namaFile');
            const uploadArea = document.getElementById('uploadArea');
            const modalIcon = document.getElementById('modalIcon');
            const modalJudul = document.getElementById('modalJudulText');
            const btnSimpan = document.getElementById('btnSimpanText');
            const imgUrl = '../img/';

            // ── Elemen Modal Stok ─────────────────────────────────────
            const backdropStok = document.getElementById('modalStokBackdrop');
            const boxStok = document.getElementById('modalStokBox');
            const inputStokId = document.getElementById('inputStokProdukId');
            const stokNamaProduk = document.getElementById('stokNamaProduk');
            const stokNamaDisplay = document.getElementById('stokNamaDisplay');
            const stokJumlahDisplay = document.getElementById('stokJumlahDisplay');
            const stokSatuanDisplay = document.getElementById('stokSatuanDisplay');
            const inputJumlahMasuk = document.getElementById('inputJumlahMasuk');
            const inputKeterangan = document.getElementById('inputKeterangan');

            // ── Helpers buka/tutup modal ──────────────────────────────
            function bukaModal(backdrop) {
                backdrop.classList.add('aktif');
                document.body.style.overflow = 'hidden';
            }
            function tutupModal(backdrop) {
                backdrop.classList.remove('aktif');
                document.body.style.overflow = '';
            }

            // Klik di luar box → tutup
            backdropProduk.addEventListener('click', e => { if (!boxProduk.contains(e.target)) tutupModal(backdropProduk); });
            backdropStok.addEventListener('click', e => { if (!boxStok.contains(e.target)) tutupModal(backdropStok); });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    tutupModal(backdropProduk);
                    tutupModal(backdropStok);
                }
            });

            document.getElementById('btnTutupModalProduk').addEventListener('click', () => tutupModal(backdropProduk));
            document.getElementById('btnBatalModalProduk').addEventListener('click', () => tutupModal(backdropProduk));
            document.getElementById('btnTutupModalStok').addEventListener('click', () => tutupModal(backdropStok));
            document.getElementById('btnBatalModalStok').addEventListener('click', () => tutupModal(backdropStok));

            // ── Preview gambar ────────────────────────────────────────
            inputGambar.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) return;
                const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!allowed.includes(file.type)) { alert('Format tidak didukung.'); this.value = ''; return; }
                if (file.size > 2 * 1024 * 1024) { alert('Ukuran melebihi 2 MB.'); this.value = ''; return; }
                const reader = new FileReader();
                reader.onload = e => {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                    uploadPlaceholder.style.display = 'none';
                    namaFile.textContent = '📎 ' + file.name;
                };
                reader.readAsDataURL(file);
            });
            uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
            uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
            uploadArea.addEventListener('drop', () => uploadArea.classList.remove('drag-over'));

            // ── Reset preview ─────────────────────────────────────────
            function resetPreview(gambarLama) {
                inputGambar.value = '';
                namaFile.textContent = '';
                if (gambarLama) {
                    previewImg.src = imgUrl + gambarLama;
                    previewImg.style.display = 'block';
                    uploadPlaceholder.style.display = 'none';
                } else {
                    previewImg.src = '';
                    previewImg.style.display = 'none';
                    uploadPlaceholder.style.display = 'block';
                }
            }

            // ── Tombol Tambah Produk ──────────────────────────────────
            document.getElementById('btnTambah').addEventListener('click', () => {
                inputAksi.value = 'tambah';
                inputEditId.value = '';
                inputNamaBarang.value = '';
                inputKategori.value = '';
                inputSatuan.value = '';
                inputHargaSatuan.value = 0;
                inputStatus.value = 'tersedia';
                resetPreview(null);
                modalIcon.className = 'fa-solid fa-box-open';
                modalJudul.textContent = 'Tambah Produk Baru';
                btnSimpan.textContent = 'Tambahkan Produk';
                bukaModal(backdropProduk);
                setTimeout(() => inputNamaBarang.focus(), 150);
            });

            // ── Tombol Edit ───────────────────────────────────────────
            document.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', () => {
                    inputAksi.value = 'edit';
                    inputEditId.value = btn.dataset.id;
                    inputNamaBarang.value = btn.dataset.nama_barang;
                    inputKategori.value = btn.dataset.kategori;
                    inputSatuan.value = btn.dataset.satuan;
                    inputHargaSatuan.value = btn.dataset.harga_satuan;
                    inputStatus.value = btn.dataset.status;
                    resetPreview(btn.dataset.gambar || null);
                    modalIcon.className = 'fa-solid fa-boxes-stacked';
                    modalJudul.textContent = 'Edit Produk';
                    btnSimpan.textContent = 'Simpan Perubahan';
                    bukaModal(backdropProduk);
                    setTimeout(() => inputNamaBarang.focus(), 150);
                });
            });

            // ── Tombol Kelola Stok ────────────────────────────────────
            document.querySelectorAll('.btn-kelola-stok').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const nama = btn.dataset.nama;
                    const jumlah = btn.dataset.jumlah;
                    const satuan = btn.dataset.satuan;

                    // Update tampilan di modal
                    inputStokId.value = id;
                    stokNamaProduk.textContent = nama;
                    stokNamaDisplay.textContent = nama;
                    stokJumlahDisplay.childNodes[0].textContent = Number(jumlah).toLocaleString('id-ID');
                    stokSatuanDisplay.textContent = satuan;

                    // Reset form tambah stok
                    inputJumlahMasuk.value = '';
                    inputKeterangan.value = '';

                    // Muat riwayat via AJAX
                    loadRiwayat(id);

                    bukaModal(backdropStok);
                    setTimeout(() => inputJumlahMasuk.focus(), 150);
                });
            });

            // ── AJAX load riwayat stok ────────────────────────────────
            function loadRiwayat(produkId) {
                const wrap = document.getElementById('riwayatStokWrap');
                wrap.innerHTML = '<p style="text-align:center;padding:1.25rem;color:var(--text-muted);font-size:.85rem">Memuat riwayat…</p>';

                fetch('ajax_riwayat_stok.php?produk_id=' + produkId)
                    .then(r => r.text())
                    .then(html => { wrap.innerHTML = html; })
                    .catch(() => {
                        wrap.innerHTML = '<p style="text-align:center;padding:1rem;color:#ef4444;font-size:.83rem">Gagal memuat riwayat.</p>';
                    });
            }

            // ── Auto-hide alert ───────────────────────────────────────
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