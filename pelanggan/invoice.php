<?php
session_start();
require_once __DIR__ . '/../config.php';

// Hanya pelanggan yang bisa akses
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pelanggan') {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Ambil ID transaksi dari parameter
$transaksiId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($transaksiId <= 0) {
    header('Location: pelanggan/pemesanan_saya.php');
    exit;
}

// Cek apakah transaksi milik user dan sudah divalidasi
$stmtTrx = $pdo->prepare("SELECT * FROM transaksi WHERE id = ? AND user_id = ? AND status = 'divalidasi'");
$stmtTrx->execute([$transaksiId, $userId]);
$transaksi = $stmtTrx->fetch();

if (!$transaksi) {
    header('Location: pelanggan/pemesanan_saya.php');
    exit;
}

// Ambil data user
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

// Ambil item transaksi
$stmtItems = $pdo->prepare("
    SELECT ti.jumlah, ti.harga_satuan, p.nama_barang, p.satuan, p.kategori
    FROM transaksi_item ti
    JOIN produk p ON p.id = ti.produk_id
    WHERE ti.transaksi_id = ?
");
$stmtItems->execute([$transaksiId]);
$items = $stmtItems->fetchAll();

// Helper format rupiah
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Helper format tanggal
function formatDate($date) {
    return date('d M Y', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invoice - <?= htmlspecialchars($transaksi['kode_transaksi']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: rgba(99, 102, 241, 0.1);
            --success: #10b981;
            --success-light: rgba(16, 185, 129, 0.1);
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 40px 20px;
            line-height: 1.5;
        }

        .invoice-container {
            background: white;
            max-width: 850px;
            margin: 0 auto;
            padding: 60px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-radius: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative Element */
        .invoice-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 8px;
            background: linear-gradient(90deg, var(--primary), #8b5cf6);
        }

        /* Toolbar */
        .toolbar {
            max-width: 850px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
        }

        .btn-primary:hover {
            background: #4f46e5;
            transform: translateY(-1px);
        }

        .btn-outline {
            background: white;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background: var(--bg);
        }

        /* Header */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .brand-icon {
            width: 45px; height: 45px;
            background: var(--primary);
            border-radius: 12px;
            display: grid;
            place-items: center;
            color: white;
            font-size: 20px;
        }

        .brand-name {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .company-details {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .invoice-info {
            text-align: right;
        }

        .invoice-info h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .badge-id {
            display: inline-block;
            background: var(--bg);
            padding: 8px 16px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            border: 1px dashed var(--primary);
        }

        /* Addresses */
        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
            padding: 30px;
            background: var(--bg);
            border-radius: 16px;
        }

        .address-box h4 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        .address-box p {
            font-size: 14px;
            line-height: 1.6;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: var(--success-light);
            color: var(--success);
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            margin-top: 10px;
        }

        /* Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            padding: 15px 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border);
        }

        .items-table td {
            padding: 20px 10px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .item-name {
            font-weight: 600;
            display: block;
            margin-bottom: 4px;
        }

        .item-meta {
            font-size: 12px;
            color: var(--text-muted);
        }

        .mono {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
        }

        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }

        /* Summary */
        .summary-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 50px;
        }

        .summary-box {
            width: 100%;
            max-width: 300px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 14px;
        }

        .summary-row.total {
            margin-top: 15px;
            padding-top: 20px;
            border-top: 2px solid var(--text);
            font-size: 18px;
            font-weight: 800;
        }

        .summary-row.total .val {
            color: var(--primary);
        }

        /* Notes */
        .notes-box {
            padding: 20px;
            background: #fffbeb;
            border-radius: 12px;
            border: 1px solid #fef3c7;
            margin-bottom: 40px;
        }

        .notes-box h4 {
            font-size: 13px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .notes-box p {
            font-size: 13px;
            color: #b45309;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 40px;
            border-top: 1px solid var(--border);
        }

        .footer p {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .signature {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .sig-box {
            text-align: center;
            width: 200px;
        }

        .sig-line {
            margin-top: 60px;
            border-top: 1px solid var(--text);
            padding-top: 8px;
            font-weight: 600;
            font-size: 13px;
        }

        @media print {
            body { background: white; padding: 0; }
            .invoice-container { box-shadow: none; border-radius: 0; padding: 0; max-width: 100%; }
            .toolbar { display: none; }
            .invoice-container::before { display: none; }
        }

        @media (max-width: 600px) {
            .address-grid { grid-template-columns: 1fr; gap: 20px; }
            .invoice-header { flex-direction: column; gap: 30px; }
            .invoice-info { text-align: left; }
            .invoice-container { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    
    <div class="toolbar">
        <a href="pemesanan_saya.php" class="btn btn-outline">
            <i class="fa-solid fa-arrow-left"></i> Kembali
        </a>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fa-solid fa-print"></i> Cetak Invoice
        </button>
    </div>

    <div class="invoice-container">
        <!-- Header -->
        <header class="invoice-header">
            <div class="brand-side">
                <div class="brand">
                    <!-- <div class="brand-icon"><i class="fa-solid fa-leaf"></i></div> -->
                    <span class="brand-name">E-MEGO</span>
                </div>
                <div class="company-details">
                    <p><strong>Hidroponik Premium Indonesia</strong></p>
                    <p>Jl. Pertanian Modern No. 88, Jakarta Selatan</p>
                    <p>Email: billing@emego.id | Telp: (021) 8888-9999</p>
                </div>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <div class="badge-id">#<?= htmlspecialchars($transaksi['kode_transaksi']) ?></div>
                <div style="margin-top: 15px; font-size: 13px;">
                    <p style="color: var(--text-muted)">Tanggal Terbit</p>
                    <p><strong><?= formatDate($transaksi['created_at']) ?></strong></p>
                </div>
            </div>
        </header>

        <!-- Addresses -->
        <section class="address-grid">
            <div class="address-box">
                <h4>Ditagihkan Kepada</h4>
                <p><strong><?= htmlspecialchars($user['name'] ?? 'Pelanggan') ?></strong></p>
                <p style="color: var(--text-muted)"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                <p style="color: var(--text-muted)"><?= htmlspecialchars($user['no_hp'] ?? '-') ?></p>
                <p style="margin-top: 8px;"><?= htmlspecialchars($user['alamat'] ?? 'Alamat belum diatur') ?></p>
            </div>
            <div class="address-box">
                <h4>Informasi Pembayaran</h4>
                <p>Metode: <strong>Transfer Bank</strong></p>
                <p>Status: </p>
                <div class="status-badge">
                    <i class="fa-solid fa-circle-check"></i> TERVALIDASI
                </div>
                <p style="margin-top: 10px; font-size: 12px; color: var(--text-muted)">
                    Tervalidasi pada: <?= date('d M Y, H:i', strtotime($transaksi['validated_at'])) ?>
                </p>
            </div>
        </section>

        <!-- Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 45%">Deskripsi Produk</th>
                    <th class="text-center">Jumlah</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $sub = $item['harga_satuan'] * $item['jumlah'];
                ?>
                <tr>
                    <td>
                        <span class="item-name"><?= htmlspecialchars($item['nama_barang']) ?></span>
                        <span class="item-meta"><?= htmlspecialchars($item['kategori'] ?? 'Kategori') ?></span>
                    </td>
                    <td class="text-center mono">
                        <?= number_format($item['jumlah']) ?>
                        <span style="font-size: 11px; color: var(--text-muted)"><?= htmlspecialchars($item['satuan']) ?></span>
                    </td>
                    <td class="text-right mono"><?= formatRupiah($item['harga_satuan']) ?></td>
                    <td class="text-right mono" style="font-weight: 600;"><?= formatRupiah($sub) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary-container">
            <div class="summary-box">
                <div class="summary-row">
                    <span style="color: var(--text-muted)">Subtotal</span>
                    <span class="mono"><?= formatRupiah($transaksi['total_harga']) ?></span>
                </div>
                <div class="summary-row">
                    <span style="color: var(--text-muted)">Pajak (0%)</span>
                    <span class="mono">Rp 0</span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span class="val mono"><?= formatRupiah($transaksi['total_harga']) ?></span>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($transaksi['catatan'])): ?>
        <div class="notes-box">
            <h4><i class="fa-solid fa-circle-info"></i> Catatan Pelanggan</h4>
            <p><?= nl2br(htmlspecialchars($transaksi['catatan'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Signatures -->
        <div class="signature">
            <div class="sig-box">
                <p style="font-size: 11px; color: var(--text-muted); margin-bottom: 10px;">Pelanggan,</p>
                <div class="sig-line"><?= htmlspecialchars($user['name']) ?></div>
            </div>
            <div class="sig-box">
                <p style="font-size: 11px; color: var(--text-muted); margin-bottom: 10px;">Administrasi E-MEGO,</p>
                <div class="sig-line">Sistem E-MEGO</div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p>Terima kasih atas kepercayaan Anda berbelanja di E-MEGO.</p>
            <p>Invoice ini sah dan diproses secara komputerisasi.</p>
            <p style="margin-top: 20px; font-family: 'JetBrains Mono', monospace; opacity: 0.5;">Dicetak pada <?= date('d/m/Y H:i') ?></p>
        </footer>
    </div>

</body>
</html>