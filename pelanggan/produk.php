<?php
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pelanggan') {
    header('Location: ../login.php');
    exit;
}

$imgDir = __DIR__ . '/../img/';
$imgUrl = '../img/';

// ── Filter & Search ───────────────────────────────────────────
$search        = trim($_GET['q'] ?? '');
$kategoriFilter = $_GET['kategori'] ?? '';

$where  = "WHERE status != 'habis'";
$params = [];

if ($search !== '') {
    $where   .= " AND (nama_barang LIKE ? OR kategori LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($kategoriFilter !== '') {
    $where   .= " AND kategori = ?";
    $params[] = $kategoriFilter;
}

// Ambil semua kategori untuk filter
$kategoriList = $pdo->query("SELECT DISTINCT kategori FROM produk WHERE kategori IS NOT NULL ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

// Ambil produk
$stmtProduk = $pdo->prepare("SELECT * FROM produk $where ORDER BY id DESC");
$stmtProduk->execute($params);
$produk = $stmtProduk->fetchAll();

// Ambil SEMUA produk (termasuk habis) untuk ditampilkan dengan overlay
$stmtSemua = $pdo->prepare("SELECT * FROM produk WHERE 1=1" .
    ($search !== '' ? " AND (nama_barang LIKE ? OR kategori LIKE ?)" : "") .
    ($kategoriFilter !== '' ? " AND kategori = ?" : "") .
    " ORDER BY id DESC");
$paramsSemua = [];
if ($search !== '') { $paramsSemua[] = "%$search%"; $paramsSemua[] = "%$search%"; }
if ($kategoriFilter !== '') $paramsSemua[] = $kategoriFilter;
$stmtSemua->execute($paramsSemua);
$semuaProduk = $stmtSemua->fetchAll();

// Helper
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $init  = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}
function formatRupiah(float $a): string {
    return 'Rp ' . number_format($a, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Produk</title>
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
            background: rgba(0,0,0,.55);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        #modalBackdrop.aktif { display: flex; }

        #modalBox {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            width: min(520px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 24px 64px rgba(0,0,0,.25);
            animation: modalMasuk .25s cubic-bezier(.34,1.2,.64,1) both;
        }
        @keyframes modalMasuk {
            from { opacity:0; transform: translateY(-24px) scale(.97); }
            to   { opacity:1; transform: translateY(0)      scale(1);   }
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            border-radius: 16px 16px 0 0;
            background: rgba(0,0,0,.02);
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .modal-header h3 { margin:0; font-weight:600; display:flex; align-items:center; gap:.5rem; font-size:1rem; }
        .modal-body  { padding: 1.75rem 1.5rem; }
        .modal-footer {
            display: flex; gap: 1rem; justify-content: flex-end;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem; margin-top: .5rem;
        }
        .modal-body .form-input {
            background: var(--input-bg, #fff);
            color: var(--text-primary);
        }

        /* ── Product Grid ── */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.25rem;
        }

        /* ── Product Card ── */
        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: box-shadow .2s, transform .2s;
            position: relative;
        }
        .product-card:hover {
            box-shadow: 0 8px 32px rgba(0,0,0,.1);
            transform: translateY(-3px);
        }
        .product-card.habis {
            opacity: .65;
        }

        /* Gambar produk */
        .product-img-wrap {
            width: 100%;
            aspect-ratio: 4/3;
            overflow: hidden;
            background: var(--input-bg, #f1f5f9);
            position: relative;
        }
        .product-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .product-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
        }

        /* Badge status */
        .product-badge {
            position: absolute;
            top: .6rem;
            right: .6rem;
            padding: .22rem .6rem;
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .badge-tersedia { background: rgba(16,185,129,.85); color:#fff; }
        .badge-terbatas { background: rgba(245,158,11,.85);  color:#fff; }
        .badge-habis    { background: rgba(239,68,68,.85);   color:#fff; }

        /* Overlay habis */
        .habis-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,.35);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .habis-overlay span {
            background: #ef4444;
            color: #fff;
            font-weight: 700;
            font-size: .85rem;
            padding: .4rem 1rem;
            border-radius: 20px;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        /* Card body */
        .product-info {
            padding: .9rem 1rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: .35rem;
        }
        .product-nama {
            font-weight: 600;
            font-size: .95rem;
            color: var(--text-primary);
            line-height: 1.3;
        }
        .product-kategori {
            font-size: .75rem;
            color: var(--text-muted);
        }
        .product-sisa {
            font-size: .78rem;
            color: var(--text-muted);
            margin-top: .1rem;
        }
        .product-sisa span {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Card footer */
        .product-footer {
            padding: .75rem 1rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--border-color);
            gap: .5rem;
        }
        .product-harga {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: .92rem;
            color: var(--primary-color);
        }
        .product-satuan {
            font-size: .72rem;
            color: var(--text-muted);
            font-weight: 400;
            display: block;
        }

        /* Filter bar */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .filter-chip {
            padding: .35rem .9rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-muted);
            font-size: .8rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: border-color .15s, color .15s, background .15s;
        }
        .filter-chip:hover,
        .filter-chip.aktif {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: rgba(99,102,241,.07);
        }

        /* Empty state */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 3rem;
            display: block;
            margin-bottom: .75rem;
            opacity: .25;
        }
    </style>
</head>
<body>

    <?php include '../layout/sidebar.php'; ?>
    <div class="overlay" id="overlay"></div>

    <!-- ══════════════ MODAL PESAN ══════════════ -->
    <div id="modalBackdrop">
        <div id="modalBox" role="dialog" aria-modal="true" aria-labelledby="modalJudul">
            <div class="modal-header">
                <h3 id="modalJudul">
                    <i class="fa-solid fa-cart-plus" style="color:var(--primary-color)"></i>
                    <span id="modalJudulText">Pesan Produk</span>
                </h3>
                <button type="button" class="icon-btn" id="btnTutupModal" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form method="POST" action="proses_transaksi.php" class="modal-body" id="formPesan">
                <input type="hidden" name="produk_id"     id="inputprodukId">
                <input type="hidden" name="harga_satuan" id="inputHarga">

                <!-- Preview produk -->
                <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding:.9rem;background:var(--input-bg,#f8fafc);border:1px solid var(--border-color);border-radius:10px">
                    <div id="modalImgWrap" style="width:56px;height:56px;border-radius:8px;overflow:hidden;flex-shrink:0;background:var(--border-color)">
                        <img id="modalImg" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:none">
                        <div id="modalAvatar" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;color:#fff;background:#6366f1"></div>
                    </div>
                    <div>
                        <p id="modalNama" style="font-weight:600;margin:0;font-size:.95rem"></p>
                        <p id="modalKategori" style="color:var(--text-muted);font-size:.78rem;margin:.15rem 0 0"></p>
                        <p id="modalHargaDisplay" style="font-family:'JetBrains Mono',monospace;font-size:.82rem;font-weight:700;color:var(--primary-color);margin:.25rem 0 0"></p>
                    </div>
                </div>

                <!-- Jumlah -->
                <div class="form-group" style="margin-bottom:1.25rem">
                    <label class="form-label" for="inputJumlah" style="display:block;margin-bottom:.5rem;font-weight:500;">
                        Jumlah Pesanan
                        <span id="modalSisaInfo" style="color:var(--text-muted);font-weight:400;font-size:.8rem"></span>
                    </label>
                    <div class="input-wrap" style="position:relative">
                        <i class="fa-solid fa-hashtag" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--text-muted);pointer-events:none"></i>
                        <input class="form-input" type="number" name="jumlah" id="inputJumlah" min="1" value="1"
                            style="width:100%;padding:.75rem 1rem .75rem 2.8rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit"
                            required />
                    </div>
                </div>

                <!-- Catatan -->
                <div class="form-group" style="margin-bottom:1.5rem">
                    <label class="form-label" for="inputCatatan" style="display:block;margin-bottom:.5rem;font-weight:500;">
                        Catatan <span style="color:var(--text-muted);font-weight:400;font-size:.8rem">(opsional)</span>
                    </label>
                    <textarea class="form-input" name="catatan" id="inputCatatan" rows="2"
                        style="width:100%;padding:.75rem 1rem;border-radius:8px;border:1px solid var(--border-color);font-family:inherit;resize:vertical"
                        placeholder="Misal: kirim pagi hari, kondisi segar…"></textarea>
                </div>

                <!-- Total estimasi -->
                <div style="padding:.8rem 1rem;background:rgba(99,102,241,.07);border:1px solid rgba(99,102,241,.2);border-radius:8px;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.85rem;color:var(--text-muted)"><i class="fa-solid fa-calculator"></i> Estimasi Total</span>
                    <span id="estimasiTotal" style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:.95rem;color:var(--primary-color)">Rp 0</span>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="btnBatal"
                        style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem">
                        <i class="fa-solid fa-xmark"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-primary"
                        style="display:flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem">
                        <i class="fa-solid fa-cart-plus"></i> Kirim Pesanan
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- ══════════════════════════════════════════ -->

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
                    <span class="breadcrumb-current">Produk</span>
                </div>
            </div>
            <div class="navbar-center"></div>
            <div class="navbar-right">
                <button class="icon-btn" title="Mode Gelap" id="themeToggle">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <div class="profile-dropdown" id="profileDropdown">
                    <button class="profile-trigger" id="profileTrigger">
                        <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'PL') ?></div>
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Pelanggan') ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'PL') ?></div>
                            <div>
                                <p><?= htmlspecialchars($_SESSION['user_name'] ?? 'Pelanggan') ?></p>
                                <small><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <!-- <a href="pemesanan_saya.php" class="dropdown-item"><i class="fa-solid fa-receipt"></i> Pesanan Saya</a>
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
                        <h1 class="page-title">Produk Kami</h1>
                        <p class="page-subtitle">Pilih produk hidroponik segar langsung dari kebun</p>
                    </div>
                    <a href="pemesanan_saya.php" class="btn btn-outline">
                        <i class="fa-solid fa-receipt"></i> Pesanan Saya
                    </a>
                </div>

                <!-- SEARCH + FILTER -->
                <form method="GET" action="produk.php">
                    <div class="filter-bar">
                        <div class="search-mini" style="flex:1;min-width:200px">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" name="q" placeholder="Cari produk…" value="<?= htmlspecialchars($search) ?>" />
                        </div>
                        <a href="produk.php?q=<?= urlencode($search) ?>"
                           class="filter-chip <?= $kategoriFilter === '' ? 'aktif' : '' ?>">Semua</a>
                        <?php foreach ($kategoriList as $kat): ?>
                            <a href="produk.php?q=<?= urlencode($search) ?>&kategori=<?= urlencode($kat) ?>"
                               class="filter-chip <?= $kategoriFilter === $kat ? 'aktif' : '' ?>">
                                <?= htmlspecialchars($kat) ?>
                            </a>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-primary" style="padding:.45rem 1.1rem;font-size:.85rem">
                            <i class="fa-solid fa-magnifying-glass"></i> Cari
                        </button>
                    </div>
                </form>

                <!-- PRODUCT GRID -->
                <div class="product-grid">
                    <?php if (empty($semuaProduk)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-seedling"></i>
                            <p style="font-weight:600;font-size:1rem;margin:0 0 .35rem">Produk tidak ditemukan</p>
                            <p style="font-size:.85rem;margin:0">Coba ubah kata kunci atau filter kategori.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($semuaProduk as $p):
                            $isHabis  = $p['status'] === 'habis';
                            $avatarBg = match($p['status']) {
                                'tersedia' => '#10b981',
                                'terbatas' => '#f59e0b',
                                'habis'    => '#ef4444',
                                default    => '#6366f1',
                            };
                            $badgeClass = match($p['status']) {
                                'tersedia' => 'badge-tersedia',
                                'terbatas' => 'badge-terbatas',
                                default    => 'badge-habis',
                            };
                        ?>
                        <div class="product-card <?= $isHabis ? 'habis' : '' ?>">
                            <!-- Gambar -->
                            <div class="product-img-wrap">
                                <?php if (!empty($p['gambar']) && file_exists($imgDir . $p['gambar'])): ?>
                                    <img src="<?= $imgUrl . htmlspecialchars($p['gambar']) ?>"
                                         alt="<?= htmlspecialchars($p['nama_barang']) ?>" />
                                <?php else: ?>
                                    <div class="product-img-placeholder" style="background:<?= $avatarBg ?>">
                                        <?= initials($p['nama_barang']) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Badge status -->
                                <span class="product-badge <?= $badgeClass ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>

                                <!-- Overlay habis -->
                                <?php if ($isHabis): ?>
                                    <div class="habis-overlay"><span>produk Habis</span></div>
                                <?php endif; ?>
                            </div>

                            <!-- Info -->
                            <div class="product-info">
                                <p class="product-nama"><?= htmlspecialchars($p['nama_barang']) ?></p>
                                <p class="product-kategori">
                                    <i class="fa-solid fa-tag" style="margin-right:.3rem;opacity:.5"></i>
                                    <?= htmlspecialchars($p['kategori'] ?? 'Umum') ?>
                                </p>
                                <p class="product-sisa">
                                    Sisa produk: <span><?= number_format($p['jumlah']) ?> <?= htmlspecialchars($p['satuan']) ?></span>
                                </p>
                            </div>

                            <!-- Footer: harga + tombol -->
                            <div class="product-footer">
                                <div>
                                    <span class="product-harga"><?= formatRupiah((float)$p['harga_satuan']) ?></span>
                                    <span class="product-satuan">/ <?= htmlspecialchars($p['satuan']) ?></span>
                                </div>
                                <?php if (!$isHabis): ?>
                                    <button type="button" class="btn btn-primary btn-pesan"
                                        style="padding:.4rem .9rem;font-size:.8rem;display:flex;align-items:center;gap:.35rem"
                                        data-id="<?= $p['id'] ?>"
                                        data-nama="<?= htmlspecialchars($p['nama_barang'], ENT_QUOTES) ?>"
                                        data-kategori="<?= htmlspecialchars($p['kategori'] ?? 'Umum', ENT_QUOTES) ?>"
                                        data-harga="<?= $p['harga_satuan'] ?>"
                                        data-satuan="<?= htmlspecialchars($p['satuan'], ENT_QUOTES) ?>"
                                        data-sisa="<?= $p['jumlah'] ?>"
                                        data-gambar="<?= htmlspecialchars($p['gambar'] ?? '', ENT_QUOTES) ?>">
                                        <i class="fa-solid fa-cart-plus"></i> Pesan
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline"
                                        style="padding:.4rem .9rem;font-size:.8rem;opacity:.5;cursor:not-allowed" disabled>
                                        <i class="fa-solid fa-ban"></i> Habis
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </section>
        </main>
    </div><!-- /main-wrapper -->

    <script src="../js/script.js"></script>
    <script>
    (() => {
        const backdrop      = document.getElementById('modalBackdrop');
        const modalBox      = document.getElementById('modalBox');
        const inputprodukId   = document.getElementById('inputprodukId');
        const inputHarga    = document.getElementById('inputHarga');
        const inputJumlah   = document.getElementById('inputJumlah');
        const modalNama     = document.getElementById('modalNama');
        const modalKategori = document.getElementById('modalKategori');
        const modalHarga    = document.getElementById('modalHargaDisplay');
        const modalSisa     = document.getElementById('modalSisaInfo');
        const modalImg      = document.getElementById('modalImg');
        const modalAvatar   = document.getElementById('modalAvatar');
        const estimasi      = document.getElementById('estimasiTotal');
        const imgUrl        = '../img/';

        function formatRp(n) {
            return 'Rp ' + parseInt(n).toLocaleString('id-ID');
        }

        function bukaModal() {
            backdrop.classList.add('aktif');
            document.body.style.overflow = 'hidden';
            setTimeout(() => inputJumlah.focus(), 150);
        }
        function tutupModal() {
            backdrop.classList.remove('aktif');
            document.body.style.overflow = '';
        }

        // Hitung estimasi saat jumlah berubah
        inputJumlah.addEventListener('input', () => {
            const harga  = parseFloat(inputHarga.value) || 0;
            const jumlah = parseInt(inputJumlah.value)  || 0;
            const sisa   = parseInt(inputJumlah.max)    || 9999;
            if (jumlah > sisa) inputJumlah.value = sisa;
            estimasi.textContent = formatRp(harga * Math.min(jumlah, sisa));
        });

        // Buka modal saat klik tombol Pesan
        document.querySelectorAll('.btn-pesan').forEach(btn => {
            btn.addEventListener('click', () => {
                const id      = btn.dataset.id;
                const nama    = btn.dataset.nama;
                const kat     = btn.dataset.kategori;
                const harga   = parseFloat(btn.dataset.harga);
                const satuan  = btn.dataset.satuan;
                const sisa    = parseInt(btn.dataset.sisa);
                const gambar  = btn.dataset.gambar;

                inputprodukId.value = id;
                inputHarga.value  = harga;
                inputJumlah.value = 1;
                inputJumlah.max   = sisa;

                modalNama.textContent     = nama;
                modalKategori.textContent = kat;
                modalHarga.textContent    = formatRp(harga) + ' / ' + satuan;
                modalSisa.textContent     = '(sisa ' + sisa + ' ' + satuan + ')';
                estimasi.textContent      = formatRp(harga);

                // Avatar / gambar
                if (gambar) {
                    modalImg.src          = imgUrl + gambar;
                    modalImg.style.display    = 'block';
                    modalAvatar.style.display = 'none';
                } else {
                    modalImg.style.display    = 'none';
                    modalAvatar.style.display = 'flex';
                    modalAvatar.textContent   = nama.trim().split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase();
                }

                document.getElementById('modalJudulText').textContent = 'Pesan — ' + nama;
                document.getElementById('inputCatatan').value = '';

                bukaModal();
            });
        });

        backdrop.addEventListener('click', e => { if (!modalBox.contains(e.target)) tutupModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupModal(); });
        document.getElementById('btnTutupModal').addEventListener('click', tutupModal);
        document.getElementById('btnBatal').addEventListener('click', tutupModal);

        // Alert auto-hide
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