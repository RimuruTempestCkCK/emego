<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pelanggan') {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Validasi input
$tanggalKunjungan = $_POST['tanggal_kunjungan'] ?? '';
$namaKunjungan = trim($_POST['nama_pengunjung'] ?? '');
$emailKunjungan = trim($_POST['email'] ?? '');
$tujuan = trim($_POST['tujuan'] ?? '');
$shift = trim($_POST['shift'] ?? '');
$jam = trim($_POST['jam'] ?? '');
$jumlahOrang = (int) ($_POST['jumlah_orang'] ?? 1);
$catatan = trim($_POST['catatan'] ?? '');

// Validasi dasar
if (empty($tanggalKunjungan) || empty($namaKunjungan) || empty($emailKunjungan) || empty($tujuan) || empty($shift) || empty($jam)) {
    $_SESSION['flash_error'] = 'Semua field yang diperlukan harus diisi.';
    header('Location: jadwal_kunjungan.php');
    exit;
}

// Validasi shift
if (!in_array($shift, ['pagi', 'siang'])) {
    $_SESSION['flash_error'] = 'Shift tidak valid.';
    header('Location: jadwal_kunjungan.php');
    exit;
}

// Validasi jumlah orang
if ($jumlahOrang < 1 || $jumlahOrang > 100) {
    $_SESSION['flash_error'] = 'Jumlah orang harus antara 1-100.';
    header('Location: jadwal_kunjungan.php');
    exit;
}

// Validasi format tanggal
$dateCheck = DateTime::createFromFormat('Y-m-d', $tanggalKunjungan);
if (!$dateCheck || $dateCheck->format('Y-m-d') !== $tanggalKunjungan) {
    $_SESSION['flash_error'] = 'Format tanggal tidak valid.';
    header('Location: jadwal_kunjungan.php');
    exit;
}

// Validasi tanggal tidak di masa lalu
if (strtotime($tanggalKunjungan) < strtotime('today')) {
    $_SESSION['flash_error'] = 'Tanggal kunjungan tidak boleh di masa lalu.';
    header('Location: jadwal_kunjungan.php');
    exit;
}

// Cek apakah tanggal sudah ada booking yang disetujui
$stmtCekTanggal = $pdo->prepare("
    SELECT COUNT(*) FROM kunjungan 
    WHERE tanggal_kunjungan = ? AND status = 'approved'
");
$stmtCekTanggal->execute([$tanggalKunjungan]);
if ($stmtCekTanggal->fetchColumn() > 0) {
    $_SESSION['flash_error'] = 'Tanggal ini sudah penuh. Silakan pilih tanggal lain.';
    header('Location: jadwal_kunjungan.php');
    exit;
}

// Simpan booking
try {
    $stmtInsert = $pdo->prepare("
        INSERT INTO kunjungan (nama_pengunjung, email, tanggal_kunjungan, shift, jam, jumlah_orang, tujuan, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmtInsert->execute([$namaKunjungan, $emailKunjungan, $tanggalKunjungan, $shift, $jam, $jumlahOrang, $tujuan]);
    
    $_SESSION['flash_success'] = 'Booking kunjungan berhasil dibuat! Admin akan memvalidasi dalam waktu 1x24 jam.';
    header('Location: kunjungan_saya.php');
    exit;
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Terjadi kesalahan saat membuat booking. Silakan coba lagi.';
    header('Location: jadwal_kunjungan.php');
    exit;
}
?>
