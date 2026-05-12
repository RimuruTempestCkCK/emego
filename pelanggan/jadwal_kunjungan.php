<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pelanggan') {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ── Ambil 30 hari ke depan ─────
$stmtJadwal = $pdo->query("
    SELECT DISTINCT DATE(NOW() + INTERVAL d DAY) as tanggal_tersedia
    FROM (
        SELECT 0 AS d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
        UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
        UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
        UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
        UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24
        UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29
        UNION SELECT 30
    ) AS dates
    WHERE DATE(NOW() + INTERVAL d DAY) >= DATE(NOW())
    ORDER BY tanggal_tersedia ASC
    LIMIT 30
");
$jadwalTersedia = $stmtJadwal->fetchAll();

// ── Ambil data booking yang ada (untuk menampilkan dengan warna merah) ─────
$stmtBookings = $pdo->query("
    SELECT tanggal_kunjungan, shift, jam, status
    FROM kunjungan
    WHERE tanggal_kunjungan >= DATE(NOW())
    ORDER BY tanggal_kunjungan ASC
");
$bookings = $stmtBookings->fetchAll();

// Buat array untuk memudahkan lookup
$bookingsByDate = [];
foreach ($bookings as $booking) {
    $date = $booking['tanggal_kunjungan'];
    if (!isset($bookingsByDate[$date])) {
        $bookingsByDate[$date] = [];
    }
    $bookingsByDate[$date][] = $booking;
}

// Helper functions
function initials(string $name): string
{
    $parts = explode(' ', trim($name));
    $init = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1]))
        $init .= strtoupper(substr($parts[1], 0, 1));
    return $init;
}

function formatTanggal($tanggal): string
{
    $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $date = new DateTime($tanggal);
    return $date->format('d') . ' ' . $months[(int) $date->format('m')] . ' ' . $date->format('Y');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-MEGO — Jadwal Kunjungan</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />
    <style>
        .jadwal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .jadwal-card {
            background: var(--card-bg, #fff);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .jadwal-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
            transform: translateY(-2px);
        }

        .jadwal-card.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .jadwal-card.has-booking {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
        }

        .jadwal-card.has-booking:hover {
            border-color: #ef4444;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
        }

        .jadwal-card.has-booking .tanggal {
            color: #ef4444;
        }

        .booking-indicator {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
        }

        .jadwal-card .hari {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.7;
            margin-bottom: 0.5rem;
            display: block;
        }

        .jadwal-card .tanggal {
            font-weight: 700;
            font-size: 1.5rem;
            display: block;
            margin-bottom: 0.25rem;
        }

        .jadwal-card .bulan {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .form-section {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
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

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background: var(--primary-hover, #4f46e5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        /* .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        } */
        .selected-info {
            background: rgba(99, 102, 241, 0.05);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: none;
        }

        .selected-info.aktif {
            display: block;
        }

        .info-badge {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .jadwal-grid {
                grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
                gap: 0.75rem;
            }
        }
    </style>
</head>

<body>

    <?php include '../layout/sidebar.php'; ?>

    <div class="overlay" id="overlay"></div>

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
                    <span class="breadcrumb-current">Jadwal Kunjungan</span>
                </div>
            </div>
            <div class="navbar-center"></div>
            <div class="navbar-right">
                <button class="icon-btn" title="Mode Gelap" id="themeToggle">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <div class="profile-dropdown" id="profileDropdown">
                    <button class="profile-trigger" id="profileTrigger">
                        <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'U') ?></div>
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <div class="dropdown-header">
                            <div class="user-avatar"><?= initials($_SESSION['user_name'] ?? 'U') ?></div>
                            <div>
                                <p><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></p>
                                <small><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></small>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <!-- <a href="#" class="dropdown-item"><i class="fa-solid fa-user"></i> Profil Saya</a>
                        <a href="#" class="dropdown-item"><i class="fa-solid fa-gear"></i> Pengaturan</a>
                        <div class="dropdown-divider"></div> -->
                        <a href="../logout.php" class="dropdown-item danger"><i
                                class="fa-solid fa-arrow-right-from-bracket"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- CONTENT -->
        <main class="content" id="contentArea">
            <section class="page active">

                <div class="page-header">
                    <div>
                        <h1 class="page-title">Jadwal Kunjungan</h1>
                        <p class="page-subtitle">Pilih tanggal yang tersedia untuk melakukan booking kunjungan</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>Pilih Tanggal Kunjungan</h3>
                    </div>

                    <div style="padding: 1.5rem">
                        <div class="selected-info" id="selectedInfo">
                            <i class="fa-solid fa-calendar-check"
                                style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                            <span>Tanggal dipilih: </span>
                            <span class="info-badge" id="selectedDate">—</span>
                        </div>

                        <div class="jadwal-grid" id="jadwalGrid">
                            <?php foreach ($jadwalTersedia as $jadwal): ?>
                                <?php
                                $date = new DateTime($jadwal['tanggal_tersedia']);
                                $hari = $date->format('l');
                                $hariIndo = match ($hari) {
                                    'Monday' => 'Sen',
                                    'Tuesday' => 'Sel',
                                    'Wednesday' => 'Rab',
                                    'Thursday' => 'Kam',
                                    'Friday' => 'Jum',
                                    'Saturday' => 'Sab',
                                    'Sunday' => 'Min',
                                };
                                $tanggal = $date->format('d');
                                $bulan = $date->format('M');
                                $hasBooking = isset($bookingsByDate[$jadwal['tanggal_tersedia']]);
                                ?>
                                <div class="jadwal-card <?= $hasBooking ? 'has-booking' : '' ?>"
                                    data-tanggal="<?= $jadwal['tanggal_tersedia'] ?>" onclick="selectDate(this)">
                                    <?php if ($hasBooking): ?>
                                        <div class="booking-indicator"></div>
                                    <?php endif; ?>
                                    <span class="hari"><?= $hariIndo ?></span>
                                    <span class="tanggal"><?= $tanggal ?></span>
                                    <span class="bulan"><?= $bulan ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <form method="POST" action="proses_booking.php" id="bookingForm">
                            <input type="hidden" name="tanggal_kunjungan" id="inputTanggal" value="">

                            <div class="form-section">
                                <div class="form-group">
                                    <label for="nama"><i class="fa-solid fa-user"></i> Nama Lengkap</label>
                                    <input type="text" id="nama" name="nama_pengunjung" required
                                        placeholder="Masukkan nama lengkap Anda">
                                </div>

                                <div class="form-group">
                                    <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                                    <input type="email" id="email" name="email" required
                                        placeholder="Masukkan email Anda">
                                </div>

                                <div class="form-group">
                                    <label for="tujuan"><i class="fa-solid fa-target"></i> Tujuan Kunjungan</label>
                                    <select id="tujuan" name="tujuan" required>
                                        <option value="">-- Pilih Tujuan --</option>
                                        <option value="Melihat fasilitas hydroponik">Melihat fasilitas hydroponik
                                        </option>
                                        <option value="Diskusi kerjasama bisnis">Diskusi kerjasama bisnis</option>
                                        <option value="Training/workshop">Training/workshop</option>
                                        <option value="Riset/edukasi">Riset/edukasi</option>
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                    <div class="form-group">
                                        <label for="shift"><i class="fa-solid fa-clock"></i> Shift Kunjungan</label>
                                        <select id="shift" name="shift" required onchange="updateJamOptions()">
                                            <option value="">-- Pilih Shift --</option>
                                            <option value="pagi">Pagi (08:00 - 12:00)</option>
                                            <option value="siang">Siang (13:00 - 17:00)</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="jam"><i class="fa-solid fa-hourglass-start"></i> Jam
                                            Kunjungan</label>
                                        <select id="jam" name="jam" required>
                                            <option value="">-- Pilih Jam --</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="jumlah_orang"><i class="fa-solid fa-people-group"></i> Jumlah
                                        Orang</label>
                                    <input type="number" id="jumlah_orang" name="jumlah_orang" required min="1"
                                        max="100" value="1" placeholder="Masukkan jumlah orang">
                                </div>

                                <div class="form-group">
                                    <label for="catatan"><i class="fa-solid fa-note-sticky"></i> Catatan Tambahan
                                        (Opsional)</label>
                                    <textarea id="catatan" name="catatan"
                                        placeholder="Berikan catatan atau pertanyaan tambahan..."></textarea>
                                </div>

                                <button type="submit" class="btn-submit" id="submitBtn"
                                    style="width:100%;justify-content:center;font-size:1rem;padding:1rem 1.5rem;background:#6366f1;color:#fff;border:none;">
                                    <i class="fa-solid fa-calendar-check"></i> Konfirmasi Booking
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </section>
        </main>
    </div>

    <script src="../js/script.js"></script>
    <script>
        // Jam options berdasarkan shift
        const jamOptions = {
            pagi: ['08:00', '09:00', '10:00', '11:00', '12:00'],
            siang: ['13:00', '14:00', '15:00', '16:00', '17:00']
        };

        function updateJamOptions() {
            const shift = document.getElementById('shift').value;
            const jamSelect = document.getElementById('jam');

            jamSelect.innerHTML = '<option value="">-- Pilih Jam --</option>';

            if (shift && jamOptions[shift]) {
                jamOptions[shift].forEach(jam => {
                    const option = document.createElement('option');
                    option.value = jam;
                    option.textContent = jam + ' WIB';
                    jamSelect.appendChild(option);
                });
            }
            jamSelect.value = '';
        }

        // function selectDate(element) {
        //     // Hapus class selected dari semua kartu
        //     document.querySelectorAll('.jadwal-card').forEach(card => card.classList.remove('selected'));

        //     // Tambah class selected ke kartu yang diklik
        //     element.classList.add('selected');

        //     // Ambil tanggal
        //     const tanggal = element.getAttribute('data-tanggal');
        //     const date = new Date(tanggal);
        //     const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        //     const formatTanggal = date.toLocaleDateString('id-ID', options);

        //     // Update input tersembunyi dan display
        //     document.getElementById('inputTanggal').value = tanggal;
        //     document.getElementById('selectedDate').textContent = formatTanggal;
        //     document.getElementById('selectedInfo').classList.add('aktif');
        // }

        function selectDate(element) {
            document.querySelectorAll('.jadwal-card').forEach(card => card.classList.remove('selected'));
            element.classList.add('selected');

            const tanggal = element.getAttribute('data-tanggal');
            const date = new Date(tanggal);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formatTanggal = date.toLocaleDateString('id-ID', options);

            document.getElementById('inputTanggal').value = tanggal;
            document.getElementById('selectedDate').textContent = formatTanggal;
            document.getElementById('selectedInfo').classList.add('aktif');
        }

        document.getElementById('bookingForm').addEventListener('submit', function (e) {
            if (!document.getElementById('inputTanggal').value) {
                e.preventDefault();
                alert('Silakan pilih tanggal kunjungan terlebih dahulu!');
                return false;
            }
            if (!document.getElementById('shift').value) {
                e.preventDefault();
                alert('Silakan pilih shift kunjungan!');
                return false;
            }
            if (!document.getElementById('jam').value) {
                e.preventDefault();
                alert('Silakan pilih jam kunjungan!');
                return false;
            }
            if (!document.getElementById('jumlah_orang').value) {
                e.preventDefault();
                alert('Silakan masukkan jumlah orang!');
                return false;
            }
        });
    </script>
</body>

</html>