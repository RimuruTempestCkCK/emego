<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'pelanggan') {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

// ── Kalender Logic ─────
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$firstDayOfMonth = new DateTime("$year-$month-01");
$daysInMonth = (int)$firstDayOfMonth->format('t');
$startDayOfWeek = (int)$firstDayOfMonth->format('w'); // 0 (Sun) to 6 (Sat)
// Adjust to Monday start (0=Mon, 6=Sun)
$startDayOfWeek = ($startDayOfWeek + 6) % 7;

$prevMonth = (clone $firstDayOfMonth)->modify('-1 month');
$nextMonth = (clone $firstDayOfMonth)->modify('+1 month');

// ── Ambil data booking yang ada ─────
$stmtBookings = $pdo->prepare("
    SELECT tanggal_kunjungan, shift, jam, status
    FROM kunjungan
    WHERE MONTH(tanggal_kunjungan) = ? AND YEAR(tanggal_kunjungan) = ?
    AND status != 'rejected'
    ORDER BY tanggal_kunjungan ASC
");
$stmtBookings->execute([$month, $year]);
$bookings = $stmtBookings->fetchAll();

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

$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
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
        .calendar-container {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 1.5rem;
        }

        .calendar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .calendar-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .btn-nav {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-primary);
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: all 0.2s;
        }

        .btn-nav:hover {
            background: var(--gray-100);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border-bottom: 1px solid var(--border-color);
        }

        .calendar-day-label {
            padding: 0.75rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color);
            background: var(--gray-50);
        }

        .calendar-day {
            min-height: 100px;
            padding: 0.75rem;
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .calendar-day:nth-child(7n) {
            border-right: none;
        }

        .calendar-day.empty {
            background: var(--gray-50);
            cursor: default;
        }

        .calendar-day:not(.empty):hover {
            background: rgba(99, 102, 241, 0.05);
        }

        .calendar-day.today {
            background: rgba(99, 102, 241, 0.03);
        }

        .day-number {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .calendar-day.today .day-number {
            color: var(--primary-color);
            width: 24px;
            height: 24px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 50%;
            display: grid;
            place-items: center;
        }

        .booking-tag {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: var(--danger);
            color: white;
            display: block;
            margin-top: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .past-date {
            opacity: 0.5;
            cursor: default !important;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            position: relative;
            background: var(--card-bg, #fff);
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalFadeUp 0.3s ease;
        }

        @keyframes modalFadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .btn-close {
            background: transparent;
            border: none;
            font-size: 1.25rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.2s;
        }

        .btn-close:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: var(--input-bg);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn-submit {
            background-color: #6366f1 !important;
            color: #ffffff !important;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            background-color: #4f46e5 !important;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .calendar-day {
                min-height: 70px;
                padding: 0.5rem;
            }
            .day-number { font-size: 0.85rem; }
            .booking-tag { font-size: 0.6rem; display: none; }
            .calendar-day.has-booking::after {
                content: '';
                position: absolute;
                bottom: 5px;
                right: 5px;
                width: 6px;
                height: 6px;
                background: var(--danger);
                border-radius: 50%;
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
                        <p class="page-subtitle">Pilih tanggal pada kalender untuk melakukan booking kunjungan</p>
                    </div>
                </div>

                <?php if (isset($_SESSION['flash_success'])): ?>
                    <div class="alert alert-success" style="background: rgba(16,185,129,0.1); border: 1px solid var(--success); color: var(--success); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fa-solid fa-circle-check"></i>
                        <span><?= $_SESSION['flash_success'] ?></span>
                        <?php unset($_SESSION['flash_success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['flash_error'])): ?>
                    <div class="alert alert-error" style="background: rgba(239,68,68,0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span><?= $_SESSION['flash_error'] ?></span>
                        <?php unset($_SESSION['flash_error']); ?>
                    </div>
                <?php endif; ?>

                <div class="calendar-container">
                    <div class="calendar-header">
                        <h2><?= $monthNames[$month] ?> <?= $year ?></h2>
                        <div class="calendar-nav">
                            <a href="?month=<?= $prevMonth->format('m') ?>&year=<?= $prevMonth->format('Y') ?>" class="btn-nav">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                            <a href="?month=<?= date('m') ?>&year=<?= date('Y') ?>" class="btn-nav" title="Bulan Ini">
                                <i class="fa-solid fa-calendar-day"></i>
                            </a>
                            <a href="?month=<?= $nextMonth->format('m') ?>&year=<?= $nextMonth->format('Y') ?>" class="btn-nav">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="calendar-grid">
                        <div class="calendar-day-label">Sen</div>
                        <div class="calendar-day-label">Sel</div>
                        <div class="calendar-day-label">Rab</div>
                        <div class="calendar-day-label">Kam</div>
                        <div class="calendar-day-label">Jum</div>
                        <div class="calendar-day-label">Sab</div>
                        <div class="calendar-day-label">Min</div>

                        <?php
                        // Fill empty slots for previous month
                        for ($i = 0; $i < $startDayOfWeek; $i++) {
                            echo '<div class="calendar-day empty"></div>';
                        }

                        $today = date('Y-m-d');
                        for ($day = 1; $day <= $daysInMonth; $day++) {
                            $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $isToday = ($currentDate === $today);
                            $hasBooking = isset($bookingsByDate[$currentDate]);
                            $isPast = ($currentDate < $today);
                            
                            $class = 'calendar-day';
                            if ($isToday) $class .= ' today';
                            if ($hasBooking) $class .= ' has-booking';
                            if ($isPast) $class .= ' past-date';

                            echo '<div class="' . $class . '" data-date="' . $currentDate . '" onclick="' . ($isPast ? '' : 'openBookingModal(\'' . $currentDate . '\')') . '">';
                            echo '<span class="day-number">' . $day . '</span>';
                            
                            if ($hasBooking) {
                                foreach ($bookingsByDate[$currentDate] as $b) {
                                    echo '<span class="booking-tag"><i class="fa-solid fa-clock"></i> ' . $b['jam'] . '</span>';
                                }
                            }
                            
                            echo '</div>';
                        }

                        // Fill empty slots for next month to complete the grid (optional)
                        $totalCells = $startDayOfWeek + $daysInMonth;
                        $remaining = 7 - ($totalCells % 7);
                        if ($remaining < 7) {
                            for ($i = 0; $i < $remaining; $i++) {
                                echo '<div class="calendar-day empty"></div>';
                            }
                        }
                        ?>
                    </div>
                </div>

            </section>
        </main>
    </div>

    <!-- Booking Modal -->
    <div class="modal" id="bookingModal">
        <div class="modal-overlay" onclick="closeBookingModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Formulir Booking</h3>
                <button class="btn-close" onclick="closeBookingModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="proses_booking.php" id="bookingForm">
                    <input type="hidden" name="tanggal_kunjungan" id="modalInputTanggal">
                    
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar-day"></i> Tanggal Terpilih</label>
                        <input type="text" id="displayTanggal" readonly style="background: var(--gray-50); border-color: var(--border-color);">
                    </div>

                    <div class="form-group">
                        <label for="nama"><i class="fa-solid fa-user"></i> Nama Lengkap</label>
                        <input type="text" id="nama" name="nama_pengunjung" required placeholder="Masukkan nama lengkap Anda">
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" required placeholder="Masukkan email Anda">
                    </div>

                    <div class="form-group">
                        <label for="tujuan"><i class="fa-solid fa-bullseye"></i> Tujuan Kunjungan</label>
                        <select id="tujuan" name="tujuan" required>
                            <option value="">-- Pilih Tujuan --</option>
                            <option value="Melihat fasilitas hydroponik">Melihat fasilitas hydroponik</option>
                            <option value="Diskusi kerjasama bisnis">Diskusi kerjasama bisnis</option>
                            <option value="Training/workshop">Training/workshop</option>
                            <option value="Riset/edukasi">Riset/edukasi</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="shift"><i class="fa-solid fa-clock"></i> Shift</label>
                            <select id="shift" name="shift" required onchange="updateJamOptions()">
                                <option value="">-- Shift --</option>
                                <option value="pagi">Pagi (08:00 - 12:00)</option>
                                <option value="siang">Siang (13:00 - 17:00)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="jam"><i class="fa-solid fa-hourglass-start"></i> Jam</label>
                            <select id="jam" name="jam" required>
                                <option value="">-- Jam --</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="jumlah_orang"><i class="fa-solid fa-users"></i> Jumlah Orang</label>
                        <input type="number" id="jumlah_orang" name="jumlah_orang" required min="1" max="100" value="1">
                    </div>

                    <div class="form-group">
                        <label for="catatan"><i class="fa-solid fa-note-sticky"></i> Catatan (Opsional)</label>
                        <textarea id="catatan" name="catatan" rows="3" placeholder="Tambahkan catatan jika ada..."></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-calendar-check"></i> Konfirmasi Booking
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/script.js"></script>
    <script>
        const jamOptions = {
            pagi: ['08:00', '09:00', '10:00', '11:00', '12:00'],
            siang: ['13:00', '14:00', '15:00', '16:00', '17:00']
        };

        function updateJamOptions() {
            const shift = document.getElementById('shift').value;
            const jamSelect = document.getElementById('jam');
            jamSelect.innerHTML = '<option value="">-- Jam --</option>';
            if (shift && jamOptions[shift]) {
                jamOptions[shift].forEach(jam => {
                    const option = document.createElement('option');
                    option.value = jam;
                    option.textContent = jam + ' WIB';
                    jamSelect.appendChild(option);
                });
            }
        }

        function openBookingModal(dateStr) {
            const date = new Date(dateStr);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = date.toLocaleDateString('id-ID', options);

            document.getElementById('modalInputTanggal').value = dateStr;
            document.getElementById('displayTanggal').value = formattedDate;
            document.getElementById('bookingModal').classList.add('active');
            document.body.style.overflow = 'hidden'; // Prevent scroll
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeBookingModal();
        });
    </script>
</body>

</html>