<?php
session_start();

// Jika sudah login, redirect berdasarkan role
if (!empty($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    if ($role === 'admin')          header('Location: admin/dashboard.php');
    elseif ($role === 'pelanggan')  header('Location: pelanggan/dashboard.php');
    elseif ($role === 'pemilik')    header('Location: pemilik/dashboard.php');
    else { session_unset(); session_destroy(); }
    exit;
}

require_once __DIR__ . '/config.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']     ?? '');
    $nik     = trim($_POST['nik']      ?? '');
    $email   = trim($_POST['email']    ?? '');
    $no_hp   = trim($_POST['no_hp']    ?? '');
    $alamat  = trim($_POST['alamat']   ?? '');
    $pass    = $_POST['password']      ?? '';
    $confirm = $_POST['confirm_pass']  ?? '';

    // ── Validasi server-side ────────────────────────────────
    if (empty($name) || empty($nik) || empty($email) || empty($no_hp) || empty($alamat) || empty($pass)) {
        $error = 'Semua field wajib diisi.';
    } elseif (!preg_match('/^\d{16}$/', $nik)) {
        $error = 'NIK harus terdiri dari 16 digit angka.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($pass !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Cek NIK duplikat
        $stmtNik = $pdo->prepare("SELECT id FROM users WHERE nik = ? LIMIT 1");
        $stmtNik->execute([$nik]);
        if ($stmtNik->fetch()) {
            $error = 'NIK <strong>' . htmlspecialchars($nik) . '</strong> sudah terdaftar. Gunakan NIK lain atau hubungi admin.';
        } else {
            // Cek email duplikat
            $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmtEmail->execute([$email]);
            if ($stmtEmail->fetch()) {
                $error = 'Email sudah terdaftar. Silakan gunakan email lain atau <a href="login.php">masuk</a>.';
            } else {
                // Simpan user baru
                $hashed = password_hash($pass, PASSWORD_BCRYPT);
                $stmtInsert = $pdo->prepare("
                    INSERT INTO users (name, nik, email, no_hp, alamat, password, role)
                    VALUES (?, ?, ?, ?, ?, ?, 'pelanggan')
                ");
                $stmtInsert->execute([$name, $nik, $email, $no_hp, $alamat, $hashed]);
                $success = 'Akun berhasil dibuat! Silakan <a href="login.php">masuk sekarang</a>.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>E-Mego — Daftar Akun</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="css/style.css" />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-primary:   #f1f5f9;
            --bg-surface:   #e8edf5;
            --bg-card:      #ffffff;
            --bg-input:     #f8fafc;
            --border:       rgba(0,0,0,.09);
            --border-focus: #10b981;
            --text-primary: #0f172a;
            --text-secondary:#475569;
            --text-muted:   #94a3b8;
            --accent:       #10b981;
            --accent-hover: #059669;
            --accent-glow:  rgba(16,185,129,.15);
            --danger:       #ef4444;
            --success:      #10b981;
            --radius-sm:    6px;
            --radius-md:    10px;
            --radius-lg:    16px;
        }
        [data-theme="dark"] {
            --bg-primary:   #0f1117;
            --bg-surface:   #16191f;
            --bg-card:      #1c2028;
            --bg-input:     #232830;
            --border:       rgba(255,255,255,.08);
            --border-focus: #10b981;
            --text-primary: #f1f5f9;
            --text-secondary:#94a3b8;
            --text-muted:   #64748b;
            --accent-glow:  rgba(16,185,129,.25);
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            transition: background .3s, color .3s;
            padding: 2rem 1.5rem;
        }

        /* Theme toggle */
        .theme-toggle {
            position: fixed;
            top: 1.25rem; right: 1.25rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            width: 40px; height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            transition: all .2s;
            z-index: 10;
        }
        .theme-toggle:hover { color: var(--accent); border-color: var(--accent); }

        /* Wrapper */
        .login-wrapper {
            position: relative; z-index: 1;
            width: 100%; max-width: 480px;
        }

        /* Brand */
        .brand { text-align: center; margin-bottom: 2rem; }
        .brand-name { font-size: 1.35rem; font-weight: 600; letter-spacing: -.5px; }
        .brand-name span { color: var(--accent); }
        .brand-tagline {
            font-size: .78rem; color: var(--text-muted);
            margin-top: .25rem; letter-spacing: .5px; text-transform: uppercase;
        }

        /* Card */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem 2rem 2.25rem;
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
            animation: slideUp .4s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes slideUp {
            from { opacity:0; transform:translateY(24px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .card-title    { font-size: 1.2rem; font-weight: 600; margin-bottom: .35rem; }
        .card-subtitle { font-size: .825rem; color: var(--text-muted); margin-bottom: 1.75rem; }

        /* Form */
        .form-group  { margin-bottom: 1.1rem; }
        .form-label  {
            display: block; font-size: .8rem; font-weight: 500;
            color: var(--text-secondary); margin-bottom: .45rem; letter-spacing: .2px;
        }
        .input-wrap  { position: relative; }
        .input-icon  {
            position: absolute; left: .9rem; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 14px; pointer-events: none; transition: color .2s;
        }
        /* textarea icon aligns to top */
        .input-wrap.textarea-wrap .input-icon { top: .85rem; transform: none; }

        .form-input {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: 'Sora', sans-serif;
            font-size: .875rem;
            padding: .7rem .9rem .7rem 2.5rem;
            outline: none;
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .form-input::placeholder { color: var(--text-muted); }
        .form-input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px var(--accent-glow);
            background: var(--bg-surface);
        }
        .input-wrap:focus-within .input-icon { color: var(--accent); }

        textarea.form-input {
            resize: vertical; min-height: 80px;
            padding-top: .7rem; line-height: 1.5;
        }

        /* Password toggle */
        .btn-eye {
            position: absolute; right: .85rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 14px; padding: 4px; transition: color .2s;
        }
        .btn-eye:hover { color: var(--text-secondary); }

        /* Two-column row */
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 480px) { .form-row-2 { grid-template-columns: 1fr; } }

        /* Section divider */
        .section-label {
            font-size: .72rem; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: .8px;
            margin: 1.5rem 0 1rem;
            display: flex; align-items: center; gap: .6rem;
        }
        .section-label::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }

        /* Alert */
        .alert {
            display: flex; align-items: flex-start; gap: .6rem;
            padding: .75rem 1rem; border-radius: var(--radius-sm);
            font-size: .82rem; margin-bottom: 1.25rem;
            animation: slideUp .3s ease both; line-height: 1.5;
        }
        .alert i { margin-top: 1px; flex-shrink: 0; }
        .alert-danger  { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3);  color: #fca5a5; }
        .alert-success { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.3); color: #6ee7b7; }
        .alert a { color: inherit; font-weight: 600; }

        /* Field error */
        .form-input.error { border-color: var(--danger); }
        .field-error { font-size: .73rem; color: #fca5a5; margin-top: .35rem; display: none; }
        .field-error.show { display: block; }

        /* Strength bar */
        .strength-bar { display: flex; gap: 4px; margin-top: .4rem; }
        .strength-bar span {
            flex: 1; height: 3px; border-radius: 2px;
            background: var(--border); transition: background .3s;
        }
        .strength-bar[data-level="1"] span:nth-child(1)    { background: var(--danger); }
        .strength-bar[data-level="2"] span:nth-child(-n+2) { background: #f59e0b; }
        .strength-bar[data-level="3"] span:nth-child(-n+3) { background: #10b981; }
        .strength-bar[data-level="4"] span                 { background: var(--success); }
        .strength-hint { font-size: .72rem; color: var(--text-muted); margin-top: .3rem; }

        /* Submit button */
        .btn-login {
            width: 100%; padding: .78rem;
            background: var(--accent); color: #fff;
            border: none; border-radius: var(--radius-sm);
            font-family: 'Sora', sans-serif; font-size: .9rem; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: .5rem;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 4px 20px rgba(16,185,129,.3);
            letter-spacing: .2px; margin-top: 1.5rem;
        }
        .btn-login:hover {
            background: var(--accent-hover);
            box-shadow: 0 6px 28px rgba(16,185,129,.45);
            transform: translateY(-1px);
        }
        .btn-login:active  { transform: scale(.98); }
        .btn-login:disabled { opacity: .6; cursor: not-allowed; transform: none; }

        .spinner {
            display: none; width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,.4); border-top-color: #fff;
            border-radius: 50%; animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Footer */
        .login-footer {
            text-align: center; margin-top: 1.5rem;
            font-size: .78rem; color: var(--text-muted);
        }
        .login-footer a { color: var(--accent); text-decoration: none; }
        .login-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body class="login-page">

    <!-- Theme toggle -->
    <button class="theme-toggle" id="themeToggle" title="Mode Terang/Gelap">
        <i class="fa-solid fa-moon" id="themeIcon"></i>
    </button>

    <div class="login-wrapper">

        <!-- Brand -->
        <div class="brand">
            <div class="brand-name">E-<span>MEGO</span></div>
            <div class="brand-tagline">Management System</div>
        </div>

        <!-- Card -->
        <div class="login-card">
            <h1 class="card-title">Buat Akun Baru</h1>
            <p class="card-subtitle">Daftarkan diri Anda sebagai pelanggan e-Mego</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>

            <?php if (!$success): ?>
            <form method="POST" action="" id="registerForm" novalidate>

                <!-- ── DATA PRIBADI ── -->
                <div class="section-label">Data Pribadi</div>

                <!-- Nama -->
                <div class="form-group">
                    <label class="form-label" for="name">Nama Lengkap</label>
                    <div class="input-wrap">
                        <input class="form-input" type="text" id="name" name="name"
                            placeholder="Masukkan nama lengkap"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" autocomplete="name" autofocus />
                        <i class="fa-solid fa-user input-icon"></i>
                    </div>
                    <span class="field-error" id="nameError">Nama lengkap wajib diisi.</span>
                </div>

                <!-- NIK -->
                <div class="form-group">
                    <label class="form-label" for="nik">Nomor Induk Kependudukan (NIK)</label>
                    <div class="input-wrap">
                        <input class="form-input" type="text" id="nik" name="nik"
                            placeholder="16 digit NIK sesuai KTP"
                            maxlength="16"
                            value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>" />
                        <i class="fa-solid fa-id-card input-icon"></i>
                    </div>
                    <span class="field-error" id="nikError">NIK harus tepat 16 digit angka.</span>
                </div>

                <!-- Email & No HP -->
                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-wrap">
                            <input class="form-input" type="email" id="email" name="email"
                                placeholder="nama@email.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" />
                            <i class="fa-solid fa-envelope input-icon"></i>
                        </div>
                        <span class="field-error" id="emailError">Format email tidak valid.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="no_hp">No. Handphone</label>
                        <div class="input-wrap">
                            <input class="form-input" type="tel" id="no_hp" name="no_hp"
                                placeholder="08xxxxxxxxxx"
                                value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" />
                            <i class="fa-solid fa-phone input-icon"></i>
                        </div>
                        <span class="field-error" id="hpError">Nomor HP wajib diisi.</span>
                    </div>
                </div>

                <!-- Alamat -->
                <div class="form-group">
                    <label class="form-label" for="alamat">Alamat Lengkap</label>
                    <div class="input-wrap textarea-wrap">
                        <textarea class="form-input" id="alamat" name="alamat"
                            placeholder="Jl. Contoh No. 1, Kelurahan, Kecamatan, Kota…"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                        <i class="fa-solid fa-location-dot input-icon"></i>
                    </div>
                    <span class="field-error" id="alamatError">Alamat wajib diisi.</span>
                </div>

                <!-- ── KEAMANAN ── -->
                <div class="section-label">Keamanan Akun</div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <input class="form-input" type="password" id="password" name="password"
                            placeholder="Min. 6 karakter" autocomplete="new-password" />
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="btn-eye" id="togglePass" title="Tampilkan Password">
                            <i class="fa-solid fa-eye" id="eyeIcon1"></i>
                        </button>
                    </div>
                    <div class="strength-bar" id="strengthBar">
                        <span></span><span></span><span></span><span></span>
                    </div>
                    <p class="strength-hint" id="strengthHint"></p>
                    <span class="field-error" id="passError">Password minimal 6 karakter.</span>
                </div>

                <!-- Konfirmasi Password -->
                <div class="form-group">
                    <label class="form-label" for="confirm_pass">Konfirmasi Password</label>
                    <div class="input-wrap">
                        <input class="form-input" type="password" id="confirm_pass" name="confirm_pass"
                            placeholder="Ulangi password" autocomplete="new-password" />
                        <i class="fa-solid fa-lock-open input-icon"></i>
                        <button type="button" class="btn-eye" id="togglePass2" title="Tampilkan Password">
                            <i class="fa-solid fa-eye" id="eyeIcon2"></i>
                        </button>
                    </div>
                    <span class="field-error" id="confirmError">Password tidak cocok.</span>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-login" id="btnRegister">
                    <span class="spinner" id="regSpinner"></span>
                    <i class="fa-solid fa-user-plus" id="btnIcon"></i>
                    <span id="btnText">Daftar Sekarang</span>
                </button>

            </form>
            <?php endif; ?>

        </div><!-- /login-card -->

        <div class="login-footer">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>

    </div><!-- /login-wrapper -->

    <script>
        // ── Theme toggle ────────────────────────────────────────────
        const root      = document.documentElement;
        const themeBtn  = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const saved     = localStorage.getItem('nexus-theme') || 'dark';

        function applyTheme(t) {
            root.setAttribute('data-theme', t);
            themeIcon.className = t === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
            localStorage.setItem('nexus-theme', t);
        }
        applyTheme(saved);
        themeBtn.addEventListener('click', () => {
            applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });

        // ── Password toggles ────────────────────────────────────────
        function makeEye(btnId, inputId, iconId) {
            const btn = document.getElementById(btnId);
            if (!btn) return;
            btn.addEventListener('click', () => {
                const inp  = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                const show = inp.type === 'password';
                inp.type   = show ? 'text' : 'password';
                icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            });
        }
        makeEye('togglePass',  'password',     'eyeIcon1');
        makeEye('togglePass2', 'confirm_pass', 'eyeIcon2');

        // ── NIK: hanya angka ─────────────────────────────────────────
        document.getElementById('nik').addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 16);
        });

        // ── Password strength ────────────────────────────────────────
        const passInp     = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthHint= document.getElementById('strengthHint');

        const hints = ['', 'Lemah', 'Cukup', 'Kuat', 'Sangat Kuat'];
        function calcStrength(p) {
            let s = 0;
            if (p.length >= 6)  s++;
            if (p.length >= 10) s++;
            if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++;
            if (/\d/.test(p) && /[^A-Za-z0-9]/.test(p)) s++;
            return s;
        }
        passInp.addEventListener('input', () => {
            const lvl = passInp.value ? calcStrength(passInp.value) : 0;
            strengthBar.setAttribute('data-level', lvl || '');
            strengthHint.textContent = lvl ? hints[lvl] : '';
        });

        // ── Validation helpers ───────────────────────────────────────
        function showErr(inp, el, msg) {
            inp.classList.add('error');
            el.textContent = msg;
            el.classList.add('show');
        }
        function clearErr(inp, el) {
            inp.classList.remove('error');
            el.classList.remove('show');
        }
        function validateEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

        // Live clear on input
        [
            ['name',         'nameError'],
            ['nik',          'nikError'],
            ['email',        'emailError'],
            ['no_hp',        'hpError'],
            ['alamat',       'alamatError'],
            ['password',     'passError'],
            ['confirm_pass', 'confirmError'],
        ].forEach(([id, errId]) => {
            const el  = document.getElementById(id);
            const err = document.getElementById(errId);
            if (el && err) el.addEventListener('input', () => clearErr(el, err));
        });

        // ── Form submit ──────────────────────────────────────────────
        const form = document.getElementById('registerForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                let valid = true;

                const name    = document.getElementById('name');
                const nik     = document.getElementById('nik');
                const email   = document.getElementById('email');
                const hp      = document.getElementById('no_hp');
                const alamat  = document.getElementById('alamat');
                const pass    = document.getElementById('password');
                const confirm = document.getElementById('confirm_pass');

                if (!name.value.trim())   { showErr(name, document.getElementById('nameError'), 'Nama lengkap wajib diisi.'); valid = false; }
                if (!/^\d{16}$/.test(nik.value.trim())) { showErr(nik, document.getElementById('nikError'), 'NIK harus tepat 16 digit angka.'); valid = false; }
                if (!validateEmail(email.value.trim()))  { showErr(email, document.getElementById('emailError'), 'Format email tidak valid.'); valid = false; }
                if (!hp.value.trim())     { showErr(hp, document.getElementById('hpError'), 'Nomor HP wajib diisi.'); valid = false; }
                if (!alamat.value.trim()) { showErr(alamat, document.getElementById('alamatError'), 'Alamat wajib diisi.'); valid = false; }
                if (pass.value.length < 6){ showErr(pass, document.getElementById('passError'), 'Password minimal 6 karakter.'); valid = false; }
                if (pass.value !== confirm.value) { showErr(confirm, document.getElementById('confirmError'), 'Password tidak cocok.'); valid = false; }

                if (!valid) { e.preventDefault(); return; }

                // Loading state
                const btn     = document.getElementById('btnRegister');
                btn.disabled  = true;
                document.getElementById('regSpinner').style.display = 'block';
                document.getElementById('btnIcon').style.display    = 'none';
                document.getElementById('btnText').textContent      = 'Mendaftarkan…';
            });
        }
    </script>
</body>
</html>