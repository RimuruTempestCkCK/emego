<!DOCTYPE html>
<html lang="id" class="login-page">

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
</head>

<body>

    <!-- Theme toggle -->
    <button class="theme-toggle" id="themeToggle" title="Mode Terang/Gelap">
        <i class="fa-solid fa-moon" id="themeIcon"></i>
    </button>

    <div class="login-wrapper">

        <!-- Brand -->
        <div class="brand">
            <div class="brand-logo">AN</div>
            <div class="brand-name">Admin<span>Nexus</span></div>
            <div class="brand-tagline">Management System</div>
        </div>

        <!-- Card -->
        <div class="login-card">
            <h1 class="card-title">Masuk ke Dashboard</h1>
            <p class="card-subtitle">Gunakan kredensial akun Anda untuk melanjutkan</p>

            <?php
            // ── PHP: handle login form submission ──────────────────────
            $error   = '';
            $success = '';

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $email    = trim($_POST['email']    ?? '');
                $password = trim($_POST['password'] ?? '');

                if (empty($email) || empty($password)) {
                    $error = 'Email dan password wajib diisi.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Format email tidak valid.';
                } else {
                    /*
                     * TODO: Ganti dengan query database Anda.
                     *
                     * Contoh PDO:
                     *   $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                     *   $stmt->execute([$email]);
                     *   $user = $stmt->fetch();
                     *   if ($user && password_verify($password, $user['password'])) { ... }
                     *
                     * Contoh hardcoded (hanya untuk testing):
                     */
                    if ($email === 'arya@nexus.id' && $password === 'password123') {
                        session_start();
                        $_SESSION['user_id']    = 1;
                        $_SESSION['user_name']  = 'Arya Wijaya';
                        $_SESSION['user_email'] = $email;
                        header('Location: admin/dashboard.php');
                        exit;
                    } else {
                        $error = 'Email atau password salah. Silakan coba lagi.';
                    }
                }
            }
            ?>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Alamat Email</label>
                    <div class="input-wrap">
                        <input
                            class="form-input"
                            type="email"
                            id="email"
                            name="email"
                            placeholder="arya@nexus.id"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            autocomplete="email"
                            autofocus
                        />
                        <i class="fa-solid fa-envelope input-icon"></i>
                    </div>
                    <span class="field-error" id="emailError">Masukkan email yang valid.</span>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <input
                            class="form-input"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                        />
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="btn-eye" id="togglePass" title="Tampilkan Password">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <span class="field-error" id="passError">Password wajib diisi.</span>
                </div>

                <!-- Remember + Forgot -->
                <div class="form-row">
                    <label class="checkbox-wrap">
                        <input type="checkbox" name="remember" id="remember" />
                        <span class="checkbox-label">Ingat saya</span>
                    </label>
                    <a href="forgot-password.php" class="link-forgot">Lupa password?</a>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="spinner" id="loginSpinner"></span>
                    <i class="fa-solid fa-right-to-bracket" id="btnIcon"></i>
                    <span id="btnText">Masuk</span>
                </button>

            </form>

            <!-- Divider -->
            <div class="divider"><span>atau masuk dengan</span></div>

            <!-- SSO Google (placeholder) -->
            <button class="sso-btn" type="button" onclick="alert('SSO belum dikonfigurasi.')">
                <svg class="sso-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.1c-.22-.66-.35-1.36-.35-2.1s.13-1.44.35-2.1V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.83z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.83c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                Masuk dengan Google
            </button>

        </div><!-- /login-card -->

        <!-- Footer -->
        <div class="login-footer">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
            &nbsp;·&nbsp;
            <a href="#">Bantuan</a>
        </div>

    </div><!-- /login-wrapper -->

    <script>
    // ── Theme toggle ───────────────────────────────────────────
    const root       = document.documentElement;
    const themeBtn   = document.getElementById('themeToggle');
    const themeIcon  = document.getElementById('themeIcon');
    const savedTheme = localStorage.getItem('nexus-theme') || 'light';

    function applyTheme(t) {
        root.setAttribute('data-theme', t);
        themeIcon.className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        localStorage.setItem('nexus-theme', t);
    }
    applyTheme(savedTheme);
    themeBtn.addEventListener('click', () => {
        applyTheme(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    });

    // ── Password visibility ────────────────────────────────────
    const passInput = document.getElementById('password');
    const eyeIcon   = document.getElementById('eyeIcon');
    document.getElementById('togglePass').addEventListener('click', () => {
        const isPass      = passInput.type === 'password';
        passInput.type    = isPass ? 'text' : 'password';
        eyeIcon.className = isPass ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    });

    // ── Client-side validation ─────────────────────────────────
    const form     = document.getElementById('loginForm');
    const emailInp = document.getElementById('email');
    const emailErr = document.getElementById('emailError');
    const passErr  = document.getElementById('passError');
    const btnLogin = document.getElementById('btnLogin');
    const btnText  = document.getElementById('btnText');
    const btnIcon  = document.getElementById('btnIcon');
    const spinner  = document.getElementById('loginSpinner');

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

    form.addEventListener('submit', function(e) {
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

        // Loading state
        btnLogin.disabled     = true;
        spinner.style.display = 'block';
        btnIcon.style.display = 'none';
        btnText.textContent   = 'Memverifikasi…';
    });
    </script>

</body>
</html>