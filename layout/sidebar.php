<?php
$role = $_SESSION['user_role'] ?? '';

// ambil halaman aktif
$current = basename($_SERVER['PHP_SELF']);
?>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon"><i class="fa-solid fa-bolt"></i></div>
        <span class="logo-text">E-MEGO</span>
    </div>

    <nav class="sidebar-nav">

        <!-- ================= ADMIN ================= -->
        <?php if ($role === 'admin'): ?>
            <div class="nav-group">
                <span class="nav-label">UTAMA</span>

                <a href="../admin/dashboard.php" class="nav-item <?= $current == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gauge"></i>
                    <span>Dashboard</span>
                    <div class="nav-indicator"></div>
                </a>

                <a href="../admin/kelola_user.php" class="nav-item <?= $current == 'kelola_user.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Data User</span>
                </a>

                <!-- <a href="../admin/kelola_kunjungan.php"
                    class="nav-item <?= $current == 'kelola_kunjungan.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Kunjungan</span>
                </a> -->

                <a href="../admin/kelola_kunjungan_pelanggan.php"
                    class="nav-item <?= $current == 'kelola_kunjungan_pelanggan.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <span>Booking Pelanggan</span>
                </a>

                <a href="../admin/kelola_produk.php"
                    class="nav-item <?= $current == 'kelola_produk.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-box-open"></i>
                    <span>Produk</span>
                </a>

                <a href="../admin/kelola_transaksi.php"
                    class="nav-item <?= $current == 'kelola_transaksi.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Transaksi</span>
                </a>

                <!-- ===== DIVIDER LAPORAN ===== -->
                <span class="nav-label">LAPORAN</span>

                <a href="../admin/laporan_penjualan.php"
                    class="nav-item <?= $current == 'laporan_penjualan.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Laporan Penjualan</span>
                </a>

                <a href="../admin/laporan_kunjungan.php"
                    class="nav-item <?= $current == 'laporan_kunjungan.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Laporan Kunjungan</span>
                </a>

                <a href="../admin/laporan_stok.php" class="nav-item <?= $current == 'laporan_stok.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Laporan Stok</span>
                </a>
            </div>
        <?php endif; ?>


        <!-- ================= PELANGGAN ================= -->
        <?php if ($role === 'pelanggan'): ?>
            <div class="nav-group">
                <span class="nav-label">UTAMA</span>

                <a href="../pelanggan/dashboard.php" class="nav-item <?= $current == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gauge"></i>
                    <span>Dashboard</span>
                </a>

                <a href="../pelanggan/produk.php" class="nav-item <?= $current == 'produk.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-box-open"></i>
                    <span>Produk</span>
                </a>

                <a href="../pelanggan/pemesanan_saya.php"
                    class="nav-item <?= $current == 'pemesanan_saya.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Pemesanan Saya</span>
                </a>

                <a href="../pelanggan/jadwal_kunjungan.php"
                    class="nav-item <?= $current == 'jadwal_kunjungan.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Jadwal Kunjungan</span>
                </a>

                <a href="../pelanggan/kunjungan_saya.php"
                    class="nav-item <?= $current == 'kunjungan_saya.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Kunjungan Saya</span>
                </a>
            </div>
        <?php endif; ?>


        <!-- ================= PEMILIK ================= -->
        <?php if ($role === 'pemilik'): ?>
            <div class="nav-group">
                <span class="nav-label">UTAMA</span>

                <a href="../pemilik/dashboard.php" class="nav-item <?= $current == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <!-- ===== DIVIDER LAPORAN ===== -->
            <span class="nav-label">LAPORAN</span>

            <a href="../pemilik/laporan_penjualan.php"
                class="nav-item <?= $current == 'laporan_penjualan.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Laporan Penjualan</span>
            </a>

            <a href="../pemilik/laporan_kunjungan.php"
                class="nav-item <?= $current == 'laporan_kunjungan.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Laporan Kunjungan</span>
            </a>

            <a href="../pemilik/laporan_stok.php" class="nav-item <?= $current == 'laporan_stok.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Laporan Stok</span>
            </a>
        <?php endif; ?>

    </nav>

    <!-- FOOTER -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar small">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="user-meta">
                <span class="user-name"><?= $_SESSION['user_name'] ?? 'User' ?></span>
                <span class="user-role"><?= ucfirst($role) ?></span>
            </div>
            <a href="../logout.php" class="logout-btn" title="Keluar">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>