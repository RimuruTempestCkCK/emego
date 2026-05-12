<?php
session_start();

// Jika sudah login, redirect berdasarkan role
if (!empty($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'pelanggan') {
        header('Location: pelanggan/dashboard.php');
    } elseif ($role === 'pemilik') {
        header('Location: pemilik/dashboard.php');
    } else {
        // Role tidak valid, logout
        session_unset();
        session_destroy();
    }
    exit;
}

require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        // Ambil user berdasarkan email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $loginOk = false;

        if ($user) {
            // Cek apakah password di DB adalah bcrypt hash atau plain text
            $isHashed = (strlen($user['password']) >= 60 && str_starts_with($user['password'], '$2'));

            if ($isHashed) {
                $loginOk = password_verify($password, $user['password']);
            } else {
                // Plain text — untuk data lama (sebaiknya segera di-hash ulang)
                $loginOk = ($password === $user['password']);
            }
        }

        if ($loginOk) {
            // Regenerate session ID untuk keamanan
            session_regenerate_id(true);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            // Redirect berdasarkan role
            switch ($user['role']) {
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
                case 'pelanggan':
                    header('Location: pelanggan/dashboard.php');
                    break;
                case 'pemilik':
                    header('Location: pemilik/dashboard.php');
                    break;
                default:
                    header('Location: login.php');
            }
            exit;
        } else {
            // Pesan error generik — jangan bocorkan "email tidak ada" vs "password salah"
            $error = 'Email atau password salah. Silakan coba lagi.';
        }
    }
}
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>AdminNexus — Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link rel="stylesheet" href="css/style.css" />
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg-primary: #f1f5f9;
            --bg-surface: #e8edf5;
            --bg-card: #ffffff;
            --bg-input: #f8fafc;
            --border: rgba(0, 0, 0, 0.09);
            --border-focus: #10b981;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --accent: #10b981;
            --accent-hover: #059669;
            --accent-glow: rgba(16, 185, 129, 0.15);
            --danger: #ef4444;
            --success: #10b981;
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        [data-theme="dark"] {
            --bg-primary: #0f1117;
            --bg-surface: #16191f;
            --bg-card: #1c2028;
            --bg-input: #232830;
            --border: rgba(255, 255, 255, 0.08);
            --border-focus: #10b981;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent-glow: rgba(16, 185, 129, 0.25);
        }

        [data-theme="dark"] body::before {
            background-image:
                linear-gradient(rgba(16, 185, 129, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(16, 185, 129, 0.04) 1px, transparent 1px);
        }

        [data-theme="dark"] body::after {
            background: radial-gradient(circle, rgba(16, 185, 129, 0.12) 0%, transparent 70%);
        }

        body {
            font-family: 'Sora', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .3s, color .3s;
            padding: 1.5rem;
        }

        /* Animated background grid */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: none;
            background-size: 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        /* Glowing orb */
        body::after {
            content: '';
            position: fixed;
            top: -200px;
            right: -200px;
            width: 600px;
            height: 600px;
            background: none;
            pointer-events: none;
            z-index: 0;
        }

        /* ── THEME TOGGLE ── */
        .theme-toggle {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            transition: all .2s;
            z-index: 10;
        }

        .theme-toggle:hover {
            color: var(--accent);
            border-color: var(--accent);
        }

        /* ── CARD ── */
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }

        /* Brand header above card */
        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #10b981, #8b5cf6);
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -1px;
            margin-bottom: .75rem;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.35);
        }

        .brand-name {
            font-size: 1.35rem;
            font-weight: 600;
            letter-spacing: -.5px;
        }

        .brand-name span {
            color: var(--accent);
        }

        .brand-tagline {
            font-size: .78rem;
            color: var(--text-muted);
            margin-top: .25rem;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        /* Card itself */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem 2rem 2.25rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
            animation: slideUp .4s cubic-bezier(.22, 1, .36, 1) both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: .35rem;
        }

        .card-subtitle {
            font-size: .825rem;
            color: var(--text-muted);
            margin-bottom: 1.75rem;
        }

        /* ── FORM ── */
        .form-group {
            margin-bottom: 1.15rem;
        }

        .form-label {
            display: block;
            font-size: .8rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: .45rem;
            letter-spacing: .2px;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: .9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
            transition: color .2s;
        }

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

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .form-input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px var(--accent-glow);
            background: var(--bg-surface);
        }

        .form-input:focus+.input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--accent);
        }

        /* Password toggle */
        .btn-eye {
            position: absolute;
            right: .85rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 14px;
            padding: 4px;
            transition: color .2s;
        }

        .btn-eye:hover {
            color: var(--text-secondary);
        }

        /* Remember + Forgot */
        .form-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: .5rem;
            cursor: pointer;
        }

        .checkbox-wrap input[type="checkbox"] {
            width: 15px;
            height: 15px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .checkbox-label {
            font-size: .8rem;
            color: var(--text-secondary);
        }

        .link-forgot {
            font-size: .8rem;
            color: var(--accent);
            text-decoration: none;
            transition: opacity .2s;
        }

        .link-forgot:hover {
            opacity: .75;
        }

        /* ── ALERT (PHP feedback) ── */
        .alert {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .7rem 1rem;
            border-radius: var(--radius-sm);
            font-size: .8rem;
            margin-bottom: 1.25rem;
            animation: slideUp .3s ease both;
        }

        .alert-danger {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, .1);
            border: 1px solid rgba(16, 185, 129, .3);
            color: #6ee7b7;
        }

        /* ── BUTTON ── */
        .btn-login {
            width: 100%;
            padding: .78rem;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'Sora', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
            letter-spacing: .2px;
        }

        .btn-login:hover {
            background: var(--accent-hover);
            box-shadow: 0 6px 28px rgba(16, 185, 129, 0.45);
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: scale(.98);
        }

        .btn-login:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading spinner */
        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ── DIVIDER ── */
        .divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin: 1.5rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            font-size: .75rem;
            color: var(--text-muted);
            white-space: nowrap;
        }

        /* SSO / alternative login */
        .sso-btn {
            width: 100%;
            padding: .65rem;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-family: 'Sora', sans-serif;
            font-size: .82rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            transition: border-color .2s, color .2s, background .2s;
        }

        .sso-btn:hover {
            border-color: var(--border-focus);
            color: var(--text-primary);
            background: var(--bg-surface);
        }

        .sso-icon {
            width: 16px;
            height: 16px;
        }

        /* ── FOOTER ── */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .78rem;
            color: var(--text-muted);
        }

        .login-footer a {
            color: var(--accent);
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* ── INPUT ERROR STATE ── */
        .form-input.error {
            border-color: var(--danger);
        }

        .field-error {
            font-size: .73rem;
            color: #fca5a5;
            margin-top: .35rem;
            display: none;
        }

        .field-error.show {
            display: block;
        }

        /* ── PASSWORD STRENGTH ── */
        .strength-bar {
            display: flex;
            gap: 4px;
            margin-top: .4rem;
        }

        .strength-bar span {
            flex: 1;
            height: 3px;
            border-radius: 2px;
            background: var(--border);
            transition: background .3s;
        }

        .strength-bar[data-level="1"] span:nth-child(1) {
            background: var(--danger);
        }

        .strength-bar[data-level="2"] span:nth-child(-n+2) {
            background: #f59e0b;
        }

        .strength-bar[data-level="3"] span:nth-child(-n+3) {
            background: #10b981;
        }

        .strength-bar[data-level="4"] span {
            background: var(--success);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem 1.25rem 1.75rem;
            }
        }
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
            <h1 class="card-title">Masuk ke Dashboard</h1>
            <p class="card-subtitle">Gunakan kredensial akun Anda untuk melanjutkan</p>


            <form method="POST" action="" id="loginForm" novalidate>

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Alamat Email</label>
                    <div class="input-wrap">
                        <input class="form-input" type="email" id="email" name="email" placeholder="arya@nexus.id"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" autofocus />
                        <i class="fa-solid fa-envelope input-icon"></i>
                    </div>
                    <span class="field-error" id="emailError">Masukkan email yang valid.</span>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <input class="form-input" type="password" id="password" name="password" placeholder="••••••••"
                            autocomplete="current-password" />
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="btn-eye" id="togglePass" title="Tampilkan Password">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <span class="field-error" id="passError">Password wajib diisi.</span>
                </div>


                <!-- Submit -->
                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="spinner" id="loginSpinner"></span>
                    <i class="fa-solid fa-right-to-bracket" id="btnIcon"></i>
                    <span id="btnText">Masuk</span>
                </button>

            </form>


        </div><!-- /login-card -->

        <!-- Footer note -->
        <div class="login-footer">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
            &nbsp;·&nbsp;
        </div>

    </div><!-- /login-wrapper -->

    <script>
        // ── Theme toggle ───────────────────────────────────────────
        const root = document.documentElement;
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const savedTheme = localStorage.getItem('nexus-theme') || 'dark';

        function applyTheme(t) {
            root.setAttribute('data-theme', t);
            themeIcon.className = t === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
            localStorage.setItem('nexus-theme', t);
        }
        applyTheme(savedTheme);
        themeBtn.addEventListener('click', () => {
            applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });

        // ── Password visibility ────────────────────────────────────
        const passInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        document.getElementById('togglePass').addEventListener('click', () => {
            const isPass = passInput.type === 'password';
            passInput.type = isPass ? 'text' : 'password';
            eyeIcon.className = isPass ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        });

        // ── Client-side validation ─────────────────────────────────
        const form = document.getElementById('loginForm');
        const emailInp = document.getElementById('email');
        const emailErr = document.getElementById('emailError');
        const passErr = document.getElementById('passError');
        const btnLogin = document.getElementById('btnLogin');
        const btnText = document.getElementById('btnText');
        const btnIcon = document.getElementById('btnIcon');
        const spinner = document.getElementById('loginSpinner');

        function validateEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

        function showError(input, errEl, msg) {
            input.classList.add('error');
            errEl.textContent = msg;
            errEl.classList.add('show');
        }
        function clearError(input, errEl) {
            input.classList.remove('error');
            errEl.classList.remove('show');
        }

        emailInp.addEventListener('input', () => {
            if (validateEmail(emailInp.value.trim())) clearError(emailInp, emailErr);
        });
        passInput.addEventListener('input', () => {
            if (passInput.value.length > 0) clearError(passInput, passErr);
        });

        form.addEventListener('submit', function (e) {
            let valid = true;

            if (!validateEmail(emailInp.value.trim())) {
                showError(emailInp, emailErr, 'Masukkan alamat email yang valid.');
                valid = false;
            }
            if (passInput.value.trim() === '') {
                showError(passInput, passErr, 'Password tidak boleh kosong.');
                valid = false;
            }

            if (!valid) { e.preventDefault(); return; }

            // Show loading state
            btnLogin.disabled = true;
            spinner.style.display = 'block';
            btnIcon.style.display = 'none';
            btnText.textContent = 'Memverifikasi…';
        });
    </script>
</body>

</html>