# E-MEGO — Sistem Informasi Terpadu MEGO Hydrofarm

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=flat-square&logo=php)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479a1?style=flat-square&logo=mysql)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

**E-MEGO** adalah platform manajemen operasional dan sistem informasi terpadu yang dirancang khusus untuk **MEGO ID (MEGO Hydrofarm)**. Aplikasi ini mentransformasi proses bisnis konvensional yang manual menjadi sistem digital yang terintegrasi, mencakup manajemen stok, transaksi produk hidroponik, hingga sistem booking kunjungan edukasi.

---

## 📄 Latar Belakang

Perkembangan teknologi informasi saat ini menuntut efisiensi dalam manajemen operasional bisnis. **MEGO ID** selama ini menghadapi tantangan dalam pengelolaan data manual:
- Pencatatan kunjungan yang tersebar di WhatsApp dan DM Instagram.
- Manajemen stok barang (masuk/keluar) yang masih menggunakan buku atau file Excel terpisah.
- Pencatatan keuangan manual yang menyulitkan rekapitulasi bulanan.
- Risiko kesalahan jadwal pada sistem booking edukasi dan *open green house*.

**E-MEGO** hadir sebagai solusi terpusat untuk meningkatkan akurasi data, mempercepat proses kerja, dan memberikan kemudahan pantauan bagi pemilik dalam mengelola ekosistem hidroponik secara profesional.

---

## ✨ Fitur Utama

### 🛠️ Admin Panel (Management Center)
- **Dashboard Analitik**: Visualisasi statistik real-time (Total User, Pendapatan, Transaksi, & Stok Kritis) menggunakan Chart.js.
- **Manajemen Inventaris**: Pelacakan stok barang otomatis dengan fitur log riwayat "Stok Masuk".
- **Validasi Transaksi**: Sistem verifikasi pembayaran pelanggan yang terintegrasi dengan pengurangan stok otomatis.
- **Manajemen Pengguna**: Pengaturan hak akses (Admin, Pelanggan, Pemilik) secara aman.

### 🍃 Customer Portal (E-Commerce & Booking)
- **Katalog Produk Hidroponik**: Belanja sayuran dan melon segar langsung dari kebun dengan sistem keranjang digital.
- **Booking Kunjungan**: Sistem reservasi jadwal edukasi dan *open green house* yang terorganisir.
- **Riwayat Pesanan**: Pantau status pesanan dan kunjungan secara transparan.

### 📊 Owner Insights
- **Laporan Terpadu**: Akses cepat ke riwayat keuangan dan aktivitas operasional untuk pengambilan keputusan bisnis yang lebih akurat.

---

## 🚀 Teknologi yang Digunakan

| Komponen | Teknologi |
| :--- | :--- |
| **Backend** | PHP 8.1+ (PDO MySQL) |
| **Database** | MySQL / MariaDB |
| **Frontend** | HTML5, Vanilla CSS3, JavaScript (ES6) |
| **Visualisasi** | Chart.js 4.4.1 |
| **Ikon** | FontAwesome 6.5.0 |
| **UI/UX** | Modern Glassmorphism Design, Dark/Light Mode Support |

---

## ⚙️ Instalasi Lokal

Ikuti langkah-langkah berikut untuk menjalankan project di lingkungan pengembangan Anda:

1. **Clone Repository**
   ```bash
   git clone https://github.com/username/e-mego2.git
   ```

2. **Pindahkan ke Server Lokal**
   Pindahkan folder project ke direktori `htdocs` (XAMPP) atau `www` (WampServer).

3. **Konfigurasi Database**
   - Buka `phpMyAdmin` dan buat database baru bernama `emego`.
   - Import file SQL database (jika tersedia) atau buat tabel berdasarkan struktur `config.php`.
   - Sesuaikan konfigurasi koneksi di file `config.php`:
     ```php
     $host = '127.0.0.1';
     $db   = 'emego';
     $user = 'root';
     $pass = '';
     ```

4. **Jalankan Aplikasi**
   Buka browser dan akses `http://localhost/e-mego2/login.php`.

---

## 📂 Struktur Folder

```text
e-mego2/
├── admin/          # Logika & Tampilan khusus Admin
├── pelanggan/      # Portal khusus Pelanggan
├── pemilik/        # Laporan khusus Pemilik
├── layout/         # Komponen UI bersama (Sidebar, Navbar)
├── css/            # Style utama (Dark/Light mode)
├── js/             # Logika frontend & Charting
├── img/            # Upload gambar produk & profil
├── bukti/          # Penyimpanan bukti pembayaran
├── config.php      # Konfigurasi Database PDO
├── login.php       # Sistem Autentikasi
└── README.md       # Dokumentasi Project
```

---

## 📸 Tampilan Aplikasi

> [!NOTE]
> *Tambahkan screenshot dashboard admin dan halaman produk di sini untuk meningkatkan daya tarik repositori.*

---

## 🤝 Kontribusi

Kontribusi selalu terbuka! Silakan lakukan *fork* pada repositori ini dan kirimkan *pull request* untuk perbaikan atau penambahan fitur.

---

## ⚖️ Lisensi

Didistribusikan di bawah Lisensi MIT. Lihat `LICENSE` untuk informasi lebih lanjut.

---
*Dikembangkan dengan ❤️ untuk kemajuan MEGO Hydrofarm.*
