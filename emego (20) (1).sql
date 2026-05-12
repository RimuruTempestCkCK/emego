-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 07 Bulan Mei 2026 pada 01.59
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

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

DROP TABLE IF EXISTS transaksi_item;
DROP TABLE IF EXISTS transaksi;
DROP TABLE IF EXISTS stok_masuk;
DROP TABLE IF EXISTS produk;
DROP TABLE IF EXISTS kunjungan;
DROP TABLE IF EXISTS users;


--
-- Struktur dari tabel `kunjungan`
--

CREATE TABLE IF NOT EXISTS `kunjungan` ( 
   `id` int(10) UNSIGNED NOT NULL,
  `nama_pengunjung` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `tanggal_kunjungan` date NOT NULL,
  `shift` enum('pagi','siang') DEFAULT 'pagi',
  `jam` varchar(50) DEFAULT '09:00',
  `jumlah_orang` int(11) DEFAULT 1,
  `tujuan` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kunjungan`
--

INSERT INTO `kunjungan` (`id`, `nama_pengunjung`, `email`, `tanggal_kunjungan`, `shift`, `jam`, `jumlah_orang`, `tujuan`, `status`, `created_at`) VALUES
(1, 'Ahmad Rahman', 'ahmad@example.com', '2026-04-20', 'pagi', '09:00', 1, 'Meeting dengan tim IT', 'approved', '2026-04-16 12:05:20'),
(2, 'Siti Nurhaliza', 'siti@example.com', '2026-04-22', 'pagi', '09:00', 1, 'Kunjungan bisnis', 'approved', '2026-04-16 12:05:20'),
(3, 'Budi Santoso', 'budi@example.com', '2026-04-18', 'pagi', '09:00', 1, 'Audit keuangan', 'approved', '2026-04-16 12:05:20'),
(4, 'Maya Sari', 'maya@example.com', '2026-04-25', 'pagi', '09:00', 1, 'Presentasi produk', 'rejected', '2026-04-16 12:05:20'),
(5, 'Rizky Pratama', 'rizky@example.com', '2026-04-28', 'pagi', '09:00', 1, 'Training karyawan', 'approved', '2026-04-16 12:05:20'),
(6, 'asdasdsd', 'asdasd@asdss.com', '2026-06-05', 'pagi', '08:00', 12, 'Training/workshop', 'approved', '2026-05-06 19:48:36'),
(7, 'AAAAAAAAAA', 'aaa@aaa.com', '2026-06-04', 'siang', '15:00', 45, 'Diskusi kerjasama bisnis', 'approved', '2026-05-06 19:52:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
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
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id`, `nama_barang`, `kategori`, `jumlah`, `satuan`, `harga_satuan`, `status`, `created_at`, `gambar`) VALUES
(1, 'Melon Hijau', 'Produk Hidroponik', 50, 'kg', 25000.00, 'tersedia', '2026-05-06 18:09:23', 'stok_1778092073_b3e2428c.jpg'),
(2, 'Selada Keriting', 'Produk Hidroponik', 30, 'ikat', 5000.00, 'tersedia', '2026-05-06 18:09:23', 'stok_1778092066_11ce3879.jpg'),
(3, 'Nutrisi AB Mix', 'Pupuk & Nutrisi', 10, 'kg', 85000.00, 'terbatas', '2026-05-06 18:09:23', 'stok_1778092056_4619a6fa.jpg'),
(4, 'Rockwool', 'Media Tanam', 20, 'lembar', 3500.00, 'tersedia', '2026-05-06 18:09:23', 'stok_1778092046_8bcbf846.jpg'),
(5, 'NFT Pipe 2m', 'Peralatan', 20, 'pcs', 45000.00, 'tersedia', '2026-05-06 18:09:23', 'stok_1778092079_dbc8ea64.jpg'),
(6, 'adasdasdasd', 'asdsasdasd', 243, 'pcs', 1000000.00, 'tersedia', '2026-05-06 21:23:30', 'produk_1778102610_b170e538.jpeg');

-- --------------------------------------------------------

--
-- Struktur dari tabel `stok_masuk`
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
-- Dumping data untuk tabel `stok_masuk`
--

INSERT INTO `stok_masuk` (`id`, `produk_id`, `jumlah`, `keterangan`, `admin_id`, `created_at`) VALUES
(1, 1, 50, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(2, 2, 30, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(3, 3, 10, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(4, 5, 20, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(5, 6, 233, 'Stok awal (migrasi)', NULL, '2026-05-06 21:31:50'),
(8, 6, 10, 'Restok', 1, '2026-05-06 21:32:58'),
(9, 4, 20, 'Restok', 1, '2026-05-06 21:33:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
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
-- Dumping data untuk tabel `transaksi`
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
(9, 'TRX-20260506-F21E93', 2, 5000000.00, 'divalidasi', 'ini adalah catatan', 'bukti_9_1778104734.jpeg', NULL, NULL, '2026-05-06 21:58:45');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi_item`
--

CREATE TABLE `transaksi_item` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaksi_id` int(10) UNSIGNED NOT NULL,
  `produk_id` int(10) UNSIGNED NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `harga_satuan` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi_item`
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
(10, 9, 6, 5, 1000000.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
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
-- Dumping data untuk tabel `users`
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
-- Indeks untuk tabel `kunjungan`
--
ALTER TABLE `kunjungan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `stok_masuk`
--
ALTER TABLE `stok_masuk`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stok_masuk_produk` (`produk_id`),
  ADD KEY `fk_stok_masuk_admin` (`admin_id`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_transaksi` (`kode_transaksi`),
  ADD KEY `fk_transaksi_user` (`user_id`),
  ADD KEY `fk_transaksi_validator` (`validated_by`);

--
-- Indeks untuk tabel `transaksi_item`
--
ALTER TABLE `transaksi_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item_transaksi` (`transaksi_id`),
  ADD KEY `fk_item_stok` (`produk_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nik` (`nik`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `kunjungan`
--
ALTER TABLE `kunjungan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `stok_masuk`
--
ALTER TABLE `stok_masuk`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `transaksi_item`
--
ALTER TABLE `transaksi_item`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `stok_masuk`
--
ALTER TABLE `stok_masuk`
  ADD CONSTRAINT `fk_stok_masuk_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stok_masuk_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transaksi_validator` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `transaksi_item`
--
ALTER TABLE `transaksi_item`
  ADD CONSTRAINT `fk_item_produk` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  ADD CONSTRAINT `fk_item_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
