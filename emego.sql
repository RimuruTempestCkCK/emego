-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 09, 2026 at 11:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `emego`
--

-- --------------------------------------------------------

--
-- Table structure for table `kunjungan`
--

CREATE TABLE `kunjungan` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama_pengunjung` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tanggal_kunjungan` date NOT NULL,
  `shift` enum('pagi','siang') DEFAULT 'pagi',
  `jam` varchar(50) DEFAULT '09:00',
  `jumlah_orang` int(11) DEFAULT 1,
  `tujuan` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `kehadiran` enum('hadir','tidak_hadir') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kunjungan`
--

INSERT INTO `kunjungan` (`id`, `nama_pengunjung`, `email`, `tanggal_kunjungan`, `shift`, `jam`, `jumlah_orang`, `tujuan`, `status`, `kehadiran`, `created_at`) VALUES
(1, 'Ahmad Rahman', 'ahmad@example.com', '2026-04-20', 'pagi', '09:00', 1, 'Meeting dengan tim IT', 'approved', 'tidak_hadir', '2026-04-16 12:05:20'),
(2, 'Siti Nurhaliza', 'siti@example.com', '2026-04-22', 'pagi', '09:00', 1, 'Kunjungan bisnis', 'approved', 'tidak_hadir', '2026-04-16 12:05:20'),
(3, 'Budi Santoso', 'budi@example.com', '2026-04-18', 'pagi', '09:00', 1, 'Audit keuangan', 'approved', 'hadir', '2026-04-16 12:05:20'),
(4, 'Maya Sari', 'maya@example.com', '2026-04-25', 'pagi', '09:00', 1, 'Presentasi produk', 'rejected', 'hadir', '2026-04-16 12:05:20'),
(5, 'Rizky Pratama', 'rizky@example.com', '2026-04-28', 'pagi', '09:00', 1, 'Training karyawan', 'approved', 'tidak_hadir', '2026-04-16 12:05:20'),
(6, 'asdasdsd', 'asdasd@asdss.com', '2026-06-05', 'pagi', '08:00', 12, 'Training/workshop', 'approved', 'hadir', '2026-05-06 19:48:36'),
(7, 'AAAAAAAAAA', 'aaa@aaa.com', '2026-06-04', 'siang', '15:00', 45, 'Diskusi kerjasama bisnis', 'approved', 'tidak_hadir', '2026-05-06 19:52:56'),
(8, 'Andi Saputra', 'andi@gmail.com', '2026-05-01', 'pagi', '08:00', 2, 'Survey lokasi', 'approved', 'hadir', '2026-06-09 04:19:01'),
(9, 'Rina Marlina', 'rina@gmail.com', '2026-05-02', 'siang', '13:00', 5, 'Pelatihan hidroponik', 'approved', 'hadir', '2026-06-09 04:19:01'),
(10, 'Dedi Setiawan', 'dedi@gmail.com', '2026-05-03', 'pagi', '09:00', 1, 'Konsultasi bisnis', 'pending', 'tidak_hadir', '2026-06-09 04:19:01'),
(11, 'Siska Wulandari', 'siska@gmail.com', '2026-05-04', 'siang', '14:00', 4, 'Kunjungan edukasi', 'approved', 'tidak_hadir', '2026-06-09 04:19:01'),
(12, 'Fajar Nugraha', 'fajar@gmail.com', '2026-05-05', 'pagi', '10:00', 2, 'Pembelian produk', 'approved', 'tidak_hadir', '2026-06-09 04:19:01'),
(13, 'Rizal Hidayat', 'rizal@gmail.com', '2026-05-06', 'siang', '15:00', 8, 'Workshop hidroponik', 'approved', 'tidak_hadir', '2026-06-09 04:19:01'),
(14, 'Teguh Prakoso', 'teguh@gmail.com', '2026-05-07', 'pagi', '08:30', 3, 'Kerjasama bisnis', 'pending', 'hadir', '2026-06-09 04:19:01'),
(15, 'Fitri Handayani', 'fitri@gmail.com', '2026-05-08', 'siang', '14:30', 7, 'Kunjungan umum', 'approved', 'hadir', '2026-06-09 04:19:01'),
(16, 'Yuni Kartika', 'yuni@gmail.com', '2026-05-09', 'pagi', '09:30', 2, 'Belajar hidroponik', 'rejected', 'hadir', '2026-06-09 04:19:01'),
(17, 'Bayu Firmansyah', 'bayu@gmail.com', '2026-05-10', 'siang', '13:30', 5, 'Observasi usaha', 'approved', 'hadir', '2026-06-09 04:19:01'),
(18, 'asdasd', 'firdinaljuliandre9@gmail.com', '2026-06-09', 'siang', '16:00', 14, 'Melihat fasilitas hydroponik', 'rejected', 'hadir', '2026-06-09 08:43:35');

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama_barang` varchar(150) NOT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 0,
  `satuan` varchar(50) NOT NULL DEFAULT 'pcs',
  `harga_satuan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('tersedia','habis','terbatas') DEFAULT 'tersedia',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gambar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `nama_barang`, `kategori`, `jumlah`, `satuan`, `harga_satuan`, `status`, `created_at`, `gambar`) VALUES
(1, 'Melon Hijau', 'Produk Hidroponik', 50, 'kg', 25000.00, 'tersedia', '2026-05-06 18:09:23', 'stok_1778092073_b3e2428c.jpg'),
(2, 'Selada Keriting', 'Produk Hidroponik', 30, 'ikat', 5000.00, 'tersedia', '2026-05-06 18:09:23', 'stok_1778092066_11ce3879.jpg'),
(3, 'Nutrisi AB Mix', 'Pupuk & Nutrisi', 10, 'kg', 85000.00, 'terbatas', '2026-05-06 18:09:23', 'stok_1778092056_4619a6fa.jpg'),
(4, 'Rockwool', 'Media Tanam', 20, 'lembar', 3500.00, 'tersedia', '2026-05-06 18:09:23', 'stok_1778092046_8bcbf846.jpg'),
(5, 'NFT Pipe 2m', 'Peralatan', 20, 'pcs', 45000.00, 'tersedia', '2026-05-06 18:09:23', 'stok_1778092079_dbc8ea64.jpg'),
(6, 'adasdasdasd', 'asdsasdasd', 243, 'pcs', 1000000.00, 'tersedia', '2026-05-06 21:23:30', 'produk_1778102610_b170e538.jpeg'),
(7, 'Bayam Hijau', 'Produk Hidroponik', 120, 'ikat', 4000.00, 'tersedia', '2026-06-09 04:19:01', 'produk1.jpg'),
(8, 'Pakcoy Premium', 'Produk Hidroponik', 80, 'kg', 18000.00, 'tersedia', '2026-06-09 04:19:01', 'produk2.jpg'),
(9, 'Kangkung Hidroponik', 'Produk Hidroponik', 150, 'ikat', 3500.00, 'tersedia', '2026-06-09 04:19:01', 'produk3.jpg'),
(10, 'Bibit Selada', 'Benih', 90, 'pack', 25000.00, 'tersedia', '2026-06-09 04:19:01', 'produk4.jpg'),
(11, 'Netpot Hidroponik', 'Peralatan', 200, 'pcs', 1200.00, 'tersedia', '2026-06-09 04:19:01', 'produk5.jpg'),
(12, 'Pompa Air Mini', 'Peralatan', 35, 'pcs', 85000.00, 'terbatas', '2026-06-09 04:19:01', 'produk6.jpg'),
(13, 'pH Meter Digital', 'Peralatan', 20, 'pcs', 65000.00, 'terbatas', '2026-06-09 04:19:01', 'produk7.jpg'),
(14, 'TDS Meter', 'Peralatan', 15, 'pcs', 90000.00, 'terbatas', '2026-06-09 04:19:01', 'produk8.jpg'),
(15, 'Nutrisi Sayur A', 'Pupuk & Nutrisi', 50, 'botol', 45000.00, 'tersedia', '2026-06-09 04:19:01', 'produk9.jpg'),
(16, 'Nutrisi Sayur B', 'Pupuk & Nutrisi', 55, 'botol', 45000.00, 'tersedia', '2026-06-09 04:19:01', 'produk10.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `stok_masuk`
--

CREATE TABLE `stok_masuk` (
  `id` int(10) UNSIGNED NOT NULL,
  `produk_id` int(10) UNSIGNED NOT NULL,
  `jumlah` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stok_masuk`
--

INSERT INTO `stok_masuk` (`id`, `produk_id`, `jumlah`, `keterangan`, `admin_id`, `created_at`) VALUES
(1, 1, 50, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(2, 2, 30, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(3, 3, 10, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(4, 5, 20, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(5, 6, 233, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(8, 6, 10, 'Restok', 1, '2026-05-06 21:32:58'),
(9, 4, 20, 'Restok', 1, '2026-05-06 21:33:12'),
(10, 1, 30, 'Restok mingguan', 1, '2026-06-09 04:19:01'),
(11, 2, 25, 'Restok supplier', 1, '2026-06-09 04:19:01'),
(12, 3, 40, 'Tambah stok', 1, '2026-06-09 04:19:01'),
(13, 4, 15, 'Pembelian baru', 1, '2026-06-09 04:19:01'),
(14, 5, 50, 'Restok gudang', 1, '2026-06-09 04:19:01'),
(15, 6, 10, 'Stok tambahan', 1, '2026-06-09 04:19:01'),
(16, 7, 5, 'Barang baru', 1, '2026-06-09 04:19:01'),
(17, 8, 5, 'Restok', 1, '2026-06-09 04:19:01'),
(18, 9, 20, 'Stok bulanan', 1, '2026-06-09 04:19:01'),
(19, 10, 20, 'Stok bulanan', 1, '2026-06-09 04:19:01');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode_transaksi` varchar(30) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `total_harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','divalidasi','ditolak') NOT NULL DEFAULT 'pending',
  `catatan` text DEFAULT NULL,
  `bukti_bayar` varchar(255) DEFAULT NULL COMMENT 'Nama file bukti pembayaran (disimpan di folder /bukti/)',
  `validated_by` int(10) UNSIGNED DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id`, `kode_transaksi`, `user_id`, `total_harga`, `status`, `catatan`, `bukti_bayar`, `validated_by`, `validated_at`, `created_at`) VALUES
(1, 'TRX-20260506-001', 2, 175000.00, 'ditolak', 'Mohon dikirim pagi hari', NULL, 1, '2026-05-06 19:17:01', '2026-05-06 18:42:46'),
(2, 'TRX-20260506-002', 2, 85000.00, 'ditolak', NULL, NULL, 1, '2026-05-06 19:16:58', '2026-05-06 18:42:46'),
(3, 'TRX-20260506-003', 3, 50000.00, 'divalidasi', NULL, NULL, NULL, NULL, '2026-05-06 18:42:46'),
(4, 'TRX-20260506-004', 2, 90000.00, 'ditolak', 'Stok tidak mencukupi', NULL, NULL, NULL, '2026-05-06 18:42:46'),
(5, 'TRX-20260506-BD5E4A', 2, 45000.00, 'ditolak', 'adsadsadasd', NULL, 1, '2026-05-06 19:16:56', '2026-05-06 18:54:12'),
(6, 'TRX-20260506-F293D5', 2, 45000.00, 'ditolak', 'adsadsadasd', NULL, 1, '2026-05-06 19:16:50', '2026-05-06 18:54:36'),
(7, 'TRX-20260506-D51DB0', 2, 45000.00, 'ditolak', NULL, NULL, 1, '2026-05-06 19:16:43', '2026-05-06 19:00:06'),
(8, 'TRX-20260506-6E6F08', 2, 45000.00, 'divalidasi', NULL, 'bukti_8_1778094661.png', NULL, NULL, '2026-05-06 19:10:40'),
(9, 'TRX-20260506-F21E93', 2, 5000000.00, 'divalidasi', 'ini adalah catatan', 'bukti_9_1778104734.jpeg', NULL, NULL, '2026-05-06 21:58:45'),
(10, 'TRX-20260609-8AC4E3', 2, 45000.00, 'pending', 'asdasdasd', 'bukti_10_1780974025.jpg', NULL, NULL, '2026-06-09 03:00:14'),
(11, 'TRX-20260601-001', 2, 45000.00, 'divalidasi', 'Pesanan cepat', NULL, 1, '2026-06-09 04:19:01', '2026-06-09 04:19:01'),
(12, 'TRX-20260601-002', 2, 120000.00, 'pending', NULL, NULL, NULL, NULL, '2026-06-09 04:19:01'),
(13, 'TRX-20260601-003', 2, 90000.00, 'ditolak', 'Alamat tidak jelas', NULL, 1, '2026-06-09 04:19:01', '2026-06-09 04:19:01'),
(14, 'TRX-20260601-004', 2, 75000.00, 'divalidasi', NULL, NULL, 1, '2026-06-09 04:19:01', '2026-06-09 04:19:01'),
(15, 'TRX-20260601-005', 2, 180000.00, 'pending', 'Kirim sore', NULL, NULL, NULL, '2026-06-09 04:19:01'),
(16, 'TRX-20260601-006', 2, 60000.00, 'divalidasi', NULL, NULL, 1, '2026-06-09 04:19:01', '2026-06-09 04:19:01'),
(17, 'TRX-20260601-007', 2, 150000.00, 'divalidasi', 'COD', NULL, 1, '2026-06-09 04:19:01', '2026-06-09 04:19:01'),
(18, 'TRX-20260601-008', 2, 95000.00, 'pending', NULL, NULL, NULL, NULL, '2026-06-09 04:19:01'),
(19, 'TRX-20260601-009', 2, 70000.00, 'ditolak', 'Stok habis', NULL, 1, '2026-06-09 04:19:01', '2026-06-09 04:19:01'),
(20, 'TRX-20260601-010', 2, 210000.00, 'divalidasi', NULL, NULL, 1, '2026-06-09 04:19:01', '2026-06-09 04:19:01');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_item`
--

CREATE TABLE `transaksi_item` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaksi_id` int(10) UNSIGNED NOT NULL,
  `produk_id` int(10) UNSIGNED NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `harga_satuan` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaksi_item`
--

INSERT INTO `transaksi_item` (`id`, `transaksi_id`, `produk_id`, `jumlah`, `harga_satuan`) VALUES
(1, 1, 1, 5, 25000.00),
(2, 1, 2, 2, 5000.00),
(3, 2, 3, 1, 85000.00),
(4, 3, 2, 10, 5000.00),
(5, 4, 4, 30, 3500.00),
(6, 5, 5, 1, 45000.00),
(7, 6, 5, 1, 45000.00),
(8, 7, 5, 1, 45000.00),
(9, 8, 5, 1, 45000.00),
(10, 9, 6, 5, 1000000.00),
(11, 10, 5, 1, 45000.00),
(12, 1, 1, 5, 4000.00),
(13, 1, 6, 1, 85000.00),
(14, 2, 2, 2, 18000.00),
(15, 2, 9, 2, 45000.00),
(16, 3, 5, 10, 1200.00),
(17, 3, 7, 1, 65000.00),
(18, 4, 3, 10, 3500.00),
(19, 4, 4, 1, 25000.00),
(20, 5, 8, 1, 90000.00),
(21, 5, 10, 2, 45000.00),
(22, 6, 1, 5, 4000.00),
(23, 6, 2, 2, 18000.00),
(24, 7, 6, 1, 85000.00),
(25, 7, 9, 1, 45000.00),
(26, 8, 3, 8, 3500.00),
(27, 8, 4, 2, 25000.00),
(28, 9, 7, 1, 65000.00),
(29, 9, 5, 5, 1200.00),
(30, 10, 8, 1, 90000.00),
(31, 10, 10, 2, 45000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pelanggan','pemilik') NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `name`, `nik`, `no_hp`, `alamat`, `created_at`) VALUES
(1, 'admin@emego.local', '$2y$10$HHntzkjPoXS6uXOQ38sREu2sSZAVWbhaMuwKwV5M81SrLz.1M4SeG', 'admin', 'Admin Ganteng', NULL, NULL, NULL, '2026-04-14 11:48:34'),
(2, 'pelanggan@emego.local', 'pelanggan123', 'pelanggan', 'Pelanggan Kacaw', NULL, NULL, NULL, '2026-04-14 11:48:34'),
(3, 'pemilik@emego.local', 'pemilik123', 'pemilik', 'Pemilik', NULL, NULL, NULL, '2026-04-14 11:48:34'),
(4, 'asdasd@mail.com', '$2y$10$30x7S/aJ7yTnQdvRkQnff.RFa3jxFKnHhWvSTwQO1OjLWBY8vN5ha', 'admin', 'adasd', NULL, NULL, NULL, '2026-04-14 12:17:02'),
(5, 'zikri@mail.com', '$2y$10$hYxmi3hdKV3k2XxOd0NvSurdmOFecQD0J2XSutyHq8YgAxJcHUREO', 'pelanggan', 'Muhammad ZIkri', '1111234567835461', '08234234234234', 'adsasdas', '2026-05-06 20:26:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kunjungan`
--
ALTER TABLE `kunjungan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stok_masuk`
--
ALTER TABLE `stok_masuk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stok_masuk_produk` (`produk_id`),
  ADD KEY `fk_stok_masuk_admin` (`admin_id`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `fk_transaksi_user` (`user_id`),
  ADD KEY `fk_transaksi_validator` (`validated_by`);

--
-- Indexes for table `transaksi_item`
--
ALTER TABLE `transaksi_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item_transaksi` (`transaksi_id`),
  ADD KEY `fk_item_stok` (`produk_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nik` (`nik`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kunjungan`
--
ALTER TABLE `kunjungan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `stok_masuk`
--
ALTER TABLE `stok_masuk`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `transaksi_item`
--
ALTER TABLE `transaksi_item`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `stok_masuk`
--
ALTER TABLE `stok_masuk`
  ADD CONSTRAINT `fk_stok_masuk_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stok_masuk_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transaksi_validator` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transaksi_item`
--
ALTER TABLE `transaksi_item`
  ADD CONSTRAINT `fk_item_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  ADD CONSTRAINT `fk_item_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
