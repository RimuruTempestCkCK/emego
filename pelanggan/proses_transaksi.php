<?php
session_start();
require_once __DIR__ . '/../config.php';

// Hanya pelanggan yang bisa mengakses
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pelanggan') {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$transaksiId = null;
$transaksi = null;
$flashError = '';
$flashSuccess = '';

// ── Bank Account Info ──────────────────────────────────────────
$BANK_ACCOUNT = [
    'bank_name' => 'BCA',
    'account_number' => '1234567890',
    'account_holder' => 'PT E-Mego Indonesia'
];

// ── Handle File Upload ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_bukti') {
    $transaksiId = (int) ($_POST['transaksi_id'] ?? 0);

    // Validasi transaksi ID
    $stmtCek = $pdo->prepare("SELECT id, kode_transaksi, total_harga FROM transaksi WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmtCek->execute([$transaksiId, $userId]);
    $transaksi = $stmtCek->fetch();

    if (!$transaksi) {
        $flashError = 'Transaksi tidak ditemukan atau sudah diproses.';
    } elseif (!isset($_FILES['bukti_bayar']) || $_FILES['bukti_bayar']['error'] !== UPLOAD_ERR_OK) {
        $flashError = 'Silakan pilih file untuk diunggah.';
    } else {
        $file = $_FILES['bukti_bayar'];

        // Validasi tipe file
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($fileMime, $allowedMimes)) {
            $flashError = 'Format file tidak didukung. Gunakan JPG, PNG, atau PDF.';
        } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB max
            $flashError = 'Ukuran file terlalu besar. Maksimal 5MB.';
        } else {
            // Buat folder bukti jika belum ada
            $buktiDir = __DIR__ . '/../bukti/';
            if (!is_dir($buktiDir)) {
                mkdir($buktiDir, 0755, true);
            }

            // Generate nama file unik
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'bukti_' . $transaksiId . '_' . time() . '.' . $ext;
            $filePath = $buktiDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Update status transaksi dengan kolom bukti_bayar
                try {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE transaksi 
                        SET bukti_bayar = ? 
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmtUpdate->execute([$fileName, $transaksiId, $userId]);

                    $flashSuccess = 'Bukti pembayaran berhasil diunggah! Admin akan memvalidasi segera.';
                    $_SESSION['flash_success'] = $flashSuccess;
                    header('Location: pemesanan_saya.php');
                    exit;
                } catch (Exception $e) {
                    $flashError = 'Terjadi kesalahan saat menyimpan data.';
                    @unlink($filePath);
                }
            } else {
                $flashError = 'Gagal mengunggah file. Silakan coba lagi.';
            }
        }
    }
}

// ── Handle Pesanan Baru ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $produkId = (int) ($_POST['produk_id'] ?? 0);
    $jumlah = (int) ($_POST['jumlah'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');

    // Validasi input dasar
    if ($produkId <= 0 || $jumlah <= 0) {
        $flashError = 'Data pesanan tidak valid. Silakan coba lagi.';
    } else {
        // Cek produk
        $stmtproduk = $pdo->prepare("SELECT id, nama_barang, jumlah, harga_satuan, status FROM produk WHERE id = ?");
        $stmtproduk->execute([$produkId]);
        $produk = $stmtproduk->fetch();

        if (!$produk) {
            $flashError = 'Produk tidak ditemukan.';
        } elseif ($produk['status'] === 'habis' || $produk['jumlah'] <= 0) {
            $flashError = 'Produk "' . $produk['nama_barang'] . '" sedang habis.';
        } elseif ($jumlah > $produk['jumlah']) {
            $flashError = 'Jumlah pesanan melebihi produk tersedia (' . $produk['jumlah'] . ').';
        } else {
            // Buat kode transaksi unik
            $kode = 'TRX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

            do {
                $stmtCek = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE kode_transaksi = ?");
                $stmtCek->execute([$kode]);
                if ($stmtCek->fetchColumn() > 0) {
                    $kode = 'TRX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
                } else {
                    break;
                }
            } while (true);

            $totalHarga = (float) $produk['harga_satuan'] * $jumlah;

            // Simpan ke database
            try {
                $pdo->beginTransaction();

                $stmtTrx = $pdo->prepare("
                    INSERT INTO transaksi (kode_transaksi, user_id, total_harga, status, catatan, created_at)
                    VALUES (?, ?, ?, 'pending', ?, NOW())
                ");
                $stmtTrx->execute([$kode, $userId, $totalHarga, $catatan ?: null]);
                $transaksiId = $pdo->lastInsertId();

                $stmtItem = $pdo->prepare("
                    INSERT INTO transaksi_item (transaksi_id, produk_id, jumlah, harga_satuan)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtItem->execute([$transaksiId, $produkId, $jumlah, $produk['harga_satuan']]);

                $pdo->commit();

                // Ambil data transaksi untuk ditampilkan
                $stmtTrx2 = $pdo->prepare("SELECT * FROM transaksi WHERE id = ?");
                $stmtTrx2->execute([$transaksiId]);
                $transaksi = $stmtTrx2->fetch();

            } catch (Exception $e) {
                $pdo->rollBack();
                $flashError = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }

    // Jika ada error, arahkan kembali
    if ($flashError) {
        $_SESSION['flash_error'] = $flashError;
        header('Location: produk.php');
        exit;
    }
}

// Jika tidak ada POST request, arahkan ke produk
if (!$transaksiId && !$transaksi) {
    header('Location: produk.php');
    exit;
}

// Helper function
function formatRupiah($amount)
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>e-Mego — Konfirmasi Pembayaran</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />
    <style>
        :root {
            --primary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --border-color: #e5e7eb;
            --card-bg: #ffffff;
            --text-primary: #1f2937;
            --text-muted: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .content {
            padding: 2rem 1.5rem;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert i {
            margin-top: 0.2rem;
            flex-shrink: 0;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .bank-info {
            background: #f9fafb;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .bank-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .bank-info-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .bank-info-item label {
            font-weight: 500;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .bank-info-item value {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .copy-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .copy-btn:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .transaction-summary {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary);
        }

        .summary-label {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .summary-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .form-group input[type="file"],
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-group input[type="file"]::file-selector-button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-right: 0.5rem;
        }

        .form-group input[type="file"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .file-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-info i {
            color: var(--primary);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        @media (max-width: 640px) {
            .content {
                padding: 1.5rem 1rem;
            }

            .header {
                padding: 1.5rem 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-credit-card"></i> Konfirmasi Pembayaran</h1>
            <p>Silakan transfer sesuai jumlah dan upload bukti pembayaran</p>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Alert Messages -->
            <?php if ($flashError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($flashError) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($flashSuccess) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($transaksi): ?>
                <!-- Section 1: Informasi Transaksi -->
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-shopping-bag"></i> Informasi Transaksi
                    </div>
                    <div class="transaction-summary">
                        <div class="summary-row">
                            <span class="summary-label">Kode Transaksi</span>
                            <span class="summary-value"><?= htmlspecialchars($transaksi['kode_transaksi']) ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Tanggal</span>
                            <span class="summary-value"><?= date('d M Y H:i', strtotime($transaksi['created_at'])) ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Jumlah Pembayaran</span>
                            <span class="summary-value"><?= formatRupiah($transaksi['total_harga']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Informasi Rekening -->
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-university"></i> Data Rekening E-Mego
                    </div>
                    <div class="bank-info">
                        <div class="bank-info-item">
                            <label>Bank</label>
                            <value><?= htmlspecialchars($BANK_ACCOUNT['bank_name']) ?></value>
                        </div>
                        <div class="bank-info-item">
                            <label>Nomor Rekening</label>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <value id="account-number"><?= htmlspecialchars($BANK_ACCOUNT['account_number']) ?></value>
                                <button type="button" class="copy-btn" onclick="copyToClipboard()">
                                    <i class="fas fa-copy"></i> Salin
                                </button>
                            </div>
                        </div>
                        <div class="bank-info-item">
                            <label>Atas Nama</label>
                            <value><?= htmlspecialchars($BANK_ACCOUNT['account_holder']) ?></value>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Upload Bukti -->
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-file-upload"></i> Upload Bukti Pembayaran
                    </div>
                    <form method="POST" enctype="multipart/form-data" id="payment-form">
                        <input type="hidden" name="action" value="upload_bukti" />
                        <input type="hidden" name="transaksi_id" value="<?= $transaksi['id'] ?>" />

                        <div class="form-group">
                            <label for="bukti-file">Pilih File Bukti Pembayaran <span
                                    style="color: var(--danger);">*</span></label>
                            <input type="file" id="bukti-file" name="bukti_bayar"
                                accept="image/jpeg,image/png,application/pdf" required />
                            <div class="file-info">
                                <i class="fas fa-info-circle"></i>
                                Format: JPG, PNG, atau PDF (Max. 5MB)
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='produk.php'">
                                <i class="fas fa-arrow-left"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-primary" id="submit-btn">
                                <i class="fas fa-check"></i> Upload & Konfirmasi
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Info Footer -->
                <div
                    style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 10px; padding: 1rem; margin-top: 1.5rem; font-size: 0.9rem; color: #059669; line-height: 1.6;">
                    <i class="fas fa-lightbulb" style="margin-right: 0.5rem;"></i>
                    <strong>Tips:</strong> Pastikan bukti pembayaran jelas dan mencantumkan jumlah transfer, nomor rekening
                    tujuan, dan tanggal transfer. Admin akan memverifikasi dalam waktu 1x24 jam.
                </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const accountNumber = document.getElementById('account-number').textContent;
            navigator.clipboard.writeText(accountNumber).then(() => {
                alert('Nomor rekening berhasil disalin!');
            });
        }

        document.getElementById('payment-form').addEventListener('submit', function (e) {
            const fileInput = document.getElementById('bukti-file');
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Silakan pilih file bukti pembayaran terlebih dahulu!');
                return false;
            }

            const file = fileInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (file.size > maxSize) {
                e.preventDefault();
                alert('Ukuran file terlalu besar. Maksimal 5MB.');
                return false;
            }

            // Disable submit button during submission
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sedang mengunggah...';
        });
    </script>
</body>

</html>