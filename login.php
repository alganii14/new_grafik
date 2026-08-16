<?php

require_once __DIR__ . '/auth.php';

start_secure_session();

$scriptNonce = base64_encode(random_bytes(18));
send_security_headers(
    "default-src 'self'; "
    . "script-src 'self' 'nonce-{$scriptNonce}'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data:; "
    . "font-src 'self'; connect-src 'self'; object-src 'none'; "
    . "base-uri 'none'; frame-ancestors 'none'; form-action 'self'"
);

if (is_authenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!csrf_is_valid($token)) {
        audit_security_event('login_csrf_rejected', $username);
        $error = 'Sesi formulir telah berakhir. Silakan muat ulang halaman.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username dan kata sandi wajib diisi.';
    } elseif (strlen($username) > 64 || strlen($password) > 256) {
        record_failed_login($username);
        $error = 'Username atau kata sandi tidak sesuai.';
    } else {
        $retryAfter = login_retry_after($username);

        if ($retryAfter > 0) {
            $minutes = max(1, (int) ceil($retryAfter / 60));
            audit_security_event('login_rate_limited', $username);
            $error = "Terlalu banyak percobaan login. Coba lagi dalam {$minutes} menit.";
        } elseif (attempt_login($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $retryAfter = record_failed_login($username);
            if ($retryAfter > 0) {
                $minutes = max(1, (int) ceil($retryAfter / 60));
                $error = "Terlalu banyak percobaan login. Coba lagi dalam {$minutes} menit.";
            } else {
                $error = 'Username atau kata sandi tidak sesuai.';
            }
        }
    }
}

$loggedOut = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';
$loginCsrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#07122e">
    <title>Login · Dashboard DPK &amp; BriLink</title>
    <style>
        :root {
            --navy-950: #03091a;
            --navy-900: #07122e;
            --navy-800: #0b1d49;
            --blue-500: #2f6df6;
            --cyan-400: #47d7e8;
            --amber-400: #fbbf4a;
            --white: #ffffff;
            --muted: #8494ba;
            --surface: rgba(12, 27, 65, 0.76);
            --stroke: rgba(255, 255, 255, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            min-height: 100%;
            background: var(--navy-950);
        }

        body {
            min-height: 100vh;
            min-height: 100svh;
            overflow-x: hidden;
            color: var(--white);
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                radial-gradient(circle at 16% 14%, rgba(47, 109, 246, 0.28), transparent 30rem),
                radial-gradient(circle at 88% 82%, rgba(71, 215, 232, 0.16), transparent 25rem),
                linear-gradient(145deg, #03091a 0%, #07122e 48%, #061536 100%);
        }

        button,
        input {
            font: inherit;
        }

        .scene {
            position: relative;
            display: grid;
            min-height: 100vh;
            min-height: 100svh;
            place-items: center;
            isolation: isolate;
            padding: 32px;
            perspective: 1400px;
        }

        body::before {
            position: fixed;
            inset: 0;
            z-index: -3;
            content: "";
            opacity: 0.18;
            background-image:
                linear-gradient(rgba(117, 156, 255, 0.18) 1px, transparent 1px),
                linear-gradient(90deg, rgba(117, 156, 255, 0.18) 1px, transparent 1px);
            background-size: 54px 54px;
            mask-image: linear-gradient(to bottom, black, transparent 85%);
            transform: perspective(700px) rotateX(58deg) scale(1.65) translateY(20%);
            transform-origin: center bottom;
            pointer-events: none;
        }

        .glow {
            position: fixed;
            z-index: -2;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            filter: blur(14px);
            opacity: 0.34;
            pointer-events: none;
            animation: drift 9s ease-in-out infinite alternate;
        }

        .glow-one {
            top: -90px;
            right: 12%;
            background: rgba(47, 109, 246, 0.38);
        }

        .glow-two {
            bottom: -130px;
            left: 8%;
            background: rgba(71, 215, 232, 0.2);
            animation-delay: -4s;
        }

        @keyframes drift {
            to { transform: translate3d(28px, 22px, 0) scale(1.08); }
        }

        .login-shell {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(390px, 0.92fr);
            width: min(1080px, 100%);
            min-height: 650px;
            overflow: hidden;
            border: 1px solid var(--stroke);
            border-radius: 32px;
            background: rgba(5, 14, 38, 0.68);
            box-shadow:
                0 42px 90px rgba(0, 0, 0, 0.48),
                0 1px 0 rgba(255, 255, 255, 0.12) inset,
                0 -1px 0 rgba(0, 0, 0, 0.45) inset;
            backdrop-filter: blur(24px);
            transform-style: preserve-3d;
            transition: transform 250ms ease-out;
        }

        .login-shell::after {
            position: absolute;
            inset: 1px;
            z-index: 4;
            border-radius: 31px;
            box-shadow: 0 0 80px rgba(47, 109, 246, 0.06) inset;
            content: "";
            pointer-events: none;
        }

        .showcase {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            padding: 48px;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            background:
                linear-gradient(150deg, rgba(47, 109, 246, 0.18), transparent 55%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.04), transparent);
            transform: translateZ(14px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            z-index: 2;
        }

        .brand-mark {
            display: grid;
            width: 50px;
            height: 50px;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 16px;
            color: white;
            background: linear-gradient(145deg, rgba(56, 119, 255, 0.95), rgba(23, 61, 156, 0.88));
            box-shadow:
                0 12px 28px rgba(23, 91, 232, 0.35),
                0 1px 0 rgba(255, 255, 255, 0.3) inset;
        }

        .brand-mark svg {
            width: 26px;
            height: 26px;
        }

        .brand-name {
            font-size: 1rem;
            font-weight: 750;
            letter-spacing: 0.01em;
        }

        .brand-meta {
            margin-top: 3px;
            color: var(--muted);
            font-size: 0.72rem;
            letter-spacing: 0.11em;
            text-transform: uppercase;
        }

        .showcase-copy {
            position: relative;
            z-index: 2;
            max-width: 480px;
            margin-top: 32px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            padding: 7px 11px;
            border: 1px solid rgba(71, 215, 232, 0.18);
            border-radius: 999px;
            color: #b9f7ff;
            background: rgba(71, 215, 232, 0.08);
            font-size: 0.74rem;
            font-weight: 650;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--cyan-400);
            box-shadow: 0 0 12px var(--cyan-400);
            animation: pulse 2s ease-out infinite;
        }

        @keyframes pulse {
            70% { box-shadow: 0 0 0 8px rgba(71, 215, 232, 0); }
        }

        .showcase h2 {
            max-width: 450px;
            font-size: clamp(2.15rem, 4.2vw, 3.65rem);
            font-weight: 760;
            letter-spacing: -0.055em;
            line-height: 1.04;
        }

        .showcase h2 span {
            color: transparent;
            background: linear-gradient(90deg, #75a2ff, #64e7f5);
            background-clip: text;
            -webkit-background-clip: text;
        }

        .showcase-copy > p {
            max-width: 420px;
            margin-top: 18px;
            color: #9cadd1;
            font-size: 0.94rem;
            line-height: 1.75;
        }

        .mini-dashboard {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 34px;
            transform: rotateX(3deg) rotateY(-4deg);
            transform-style: preserve-3d;
        }

        .metric-card {
            min-height: 94px;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.095), rgba(255, 255, 255, 0.025));
            box-shadow: 0 15px 28px rgba(0, 0, 0, 0.22);
            transform: translateZ(12px);
        }

        .metric-card:nth-child(2) { transform: translateZ(26px) translateY(-7px); }
        .metric-card:nth-child(3) { transform: translateZ(8px); }
        .metric-label { color: #8494ba; font-size: 0.68rem; }
        .metric-value { margin-top: 8px; font-size: 1.05rem; font-weight: 750; }
        .metric-line { width: 100%; height: 22px; margin-top: 8px; }

        .showcase-footer {
            position: relative;
            z-index: 2;
            color: #65769f;
            font-size: 0.72rem;
        }

        .showcase-ring {
            position: absolute;
            top: 28%;
            right: -170px;
            width: 360px;
            height: 360px;
            border: 1px solid rgba(86, 143, 255, 0.15);
            border-radius: 50%;
            box-shadow:
                0 0 0 38px rgba(86, 143, 255, 0.035),
                0 0 0 78px rgba(86, 143, 255, 0.025);
            transform: rotateX(66deg) rotateZ(-14deg);
        }

        .login-panel {
            position: relative;
            z-index: 5;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: clamp(36px, 5vw, 64px);
            background: var(--surface);
            transform: translateZ(28px);
        }

        .mobile-brand {
            display: none;
        }

        .eyebrow {
            margin-bottom: 12px;
            color: var(--cyan-400);
            font-size: 0.72rem;
            font-weight: 750;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .login-panel h1 {
            font-size: clamp(1.8rem, 3vw, 2.35rem);
            font-weight: 750;
            letter-spacing: -0.035em;
        }

        .login-subtitle {
            margin-top: 10px;
            color: #91a1c5;
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 22px;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 0.78rem;
            line-height: 1.45;
        }

        .notice-error {
            border: 1px solid rgba(255, 107, 122, 0.25);
            color: #ffc3ca;
            background: rgba(199, 49, 70, 0.12);
        }

        .notice-success {
            border: 1px solid rgba(71, 215, 232, 0.2);
            color: #bff9ff;
            background: rgba(30, 172, 150, 0.1);
        }

        .notice svg {
            flex: 0 0 auto;
            width: 17px;
            height: 17px;
            margin-top: 1px;
        }

        .login-form {
            margin-top: 28px;
        }

        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            margin-bottom: 8px;
            color: #c9d4ed;
            font-size: 0.78rem;
            font-weight: 650;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 15px;
            width: 18px;
            height: 18px;
            color: #6f82ac;
            pointer-events: none;
            transform: translateY(-50%);
            transition: color 180ms ease;
        }

        .field input {
            width: 100%;
            height: 52px;
            padding: 0 46px;
            border: 1px solid rgba(143, 164, 211, 0.2);
            outline: none;
            border-radius: 14px;
            color: white;
            caret-color: var(--cyan-400);
            background: rgba(3, 10, 29, 0.58);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.035) inset;
            transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
        }

        .field input::placeholder { color: #56698f; }

        .field input:focus {
            border-color: rgba(71, 215, 232, 0.58);
            box-shadow: 0 0 0 4px rgba(71, 215, 232, 0.08), 0 14px 30px rgba(0, 0, 0, 0.12);
            transform: translateY(-1px);
        }

        .field input:focus + .input-icon,
        .input-wrap:focus-within .input-icon {
            color: var(--cyan-400);
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 8px;
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border: 0;
            border-radius: 10px;
            color: #7487af;
            background: transparent;
            cursor: pointer;
            transform: translateY(-50%);
            transition: color 180ms ease, background 180ms ease;
        }

        .toggle-password:hover,
        .toggle-password:focus-visible {
            color: white;
            outline: none;
            background: rgba(255, 255, 255, 0.07);
        }

        .toggle-password svg { width: 18px; height: 18px; }

        .submit-button {
            position: relative;
            display: flex;
            width: 100%;
            height: 54px;
            align-items: center;
            justify-content: center;
            gap: 10px;
            overflow: hidden;
            margin-top: 8px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 15px;
            color: white;
            background: linear-gradient(110deg, #2457d6, #357cf6 52%, #2b9fc2);
            box-shadow:
                0 16px 28px rgba(32, 93, 225, 0.28),
                0 1px 0 rgba(255, 255, 255, 0.28) inset;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 750;
            letter-spacing: 0.01em;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        .submit-button::before {
            position: absolute;
            top: 0;
            left: -70%;
            width: 55%;
            height: 100%;
            content: "";
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transform: skewX(-20deg);
            transition: left 500ms ease;
        }

        .submit-button:hover {
            box-shadow: 0 20px 36px rgba(32, 93, 225, 0.38), 0 1px 0 rgba(255, 255, 255, 0.3) inset;
            transform: translateY(-2px);
        }

        .submit-button:hover::before { left: 125%; }
        .submit-button:active { transform: translateY(0); }
        .submit-button svg { width: 18px; height: 18px; }

        .secure-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            margin-top: 24px;
            color: #687ba5;
            font-size: 0.7rem;
        }

        .secure-note svg { width: 14px; height: 14px; }

        @media (max-width: 860px) {
            .scene { padding: 20px; }
            .login-shell { grid-template-columns: 1fr; width: min(520px, 100%); min-height: auto; }
            .showcase { display: none; }
            .login-panel { min-height: 620px; }
            .mobile-brand { display: flex; margin-bottom: 42px; }
        }

        @media (max-width: 520px) {
            .scene { align-items: stretch; padding: 0; }
            .login-shell { width: 100%; min-height: 100vh; min-height: 100svh; border: 0; border-radius: 0; }
            .login-shell::after { display: none; }
            .login-panel { min-height: 100vh; min-height: 100svh; padding: 30px 24px; transform: none; }
            .mobile-brand { margin-bottom: auto; padding-bottom: 44px; }
            .secure-note { margin-top: auto; padding-top: 32px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="glow glow-one"></div>
    <div class="glow glow-two"></div>
    <main class="scene">

        <section class="login-shell" id="loginShell" aria-label="Login dashboard">
            <div class="showcase" aria-hidden="true">
                <div class="brand">
                    <div class="brand-mark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19V9m5 10V5m5 14v-7m5 7V3" stroke-linecap="round"/>
                            <path d="m3 7 5-4 5 6 7-7" opacity=".65"/>
                        </svg>
                    </div>
                    <div>
                        <div class="brand-name">Dashboard Kinerja</div>
                        <div class="brand-meta">DPK &amp; BriLink Analytics</div>
                    </div>
                </div>

                <div class="showcase-copy">
                    <div class="status-pill"><span class="status-dot"></span> Sistem monitoring aktif</div>
                    <h2>Data yang jelas.<br><span>Keputusan lebih cepat.</span></h2>
                    <p>Pantau tren harian Tabungan, Giro, Deposito, CASA, DPK, dan performa BriLink dalam satu ruang kerja.</p>

                    <div class="mini-dashboard">
                        <div class="metric-card">
                            <div class="metric-label">Tabungan</div>
                            <div class="metric-value">Stabil</div>
                            <svg class="metric-line" viewBox="0 0 100 24" preserveAspectRatio="none">
                                <path d="M1 19 C18 17, 23 8, 37 12 S58 20, 69 8 S84 5, 99 2" fill="none" stroke="#5f91ff" stroke-width="2"/>
                            </svg>
                        </div>
                        <div class="metric-card">
                            <div class="metric-label">DPK</div>
                            <div class="metric-value">Terpantau</div>
                            <svg class="metric-line" viewBox="0 0 100 24" preserveAspectRatio="none">
                                <path d="M1 20 C12 14, 22 18, 33 11 S51 3, 63 9 S83 12, 99 4" fill="none" stroke="#47d7e8" stroke-width="2"/>
                            </svg>
                        </div>
                        <div class="metric-card">
                            <div class="metric-label">BriLink</div>
                            <div class="metric-value">Realtime</div>
                            <svg class="metric-line" viewBox="0 0 100 24" preserveAspectRatio="none">
                                <path d="M1 18 C11 10, 22 16, 34 15 S51 4, 64 8 S83 5, 99 3" fill="none" stroke="#fbbf4a" stroke-width="2"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <p class="showcase-footer">Internal performance monitoring dashboard</p>
                <div class="showcase-ring"></div>
            </div>

            <div class="login-panel">
                <div class="brand mobile-brand">
                    <div class="brand-mark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19V9m5 10V5m5 14v-7m5 7V3" stroke-linecap="round"/>
                            <path d="m3 7 5-4 5 6 7-7" opacity=".65"/>
                        </svg>
                    </div>
                    <div>
                        <div class="brand-name">Dashboard Kinerja</div>
                        <div class="brand-meta">DPK &amp; BriLink</div>
                    </div>
                </div>

                <div class="eyebrow">Secure Access</div>
                <h1>Selamat datang</h1>
                <p class="login-subtitle">Masuk untuk melanjutkan ke dashboard monitoring harian Anda.</p>

                <?php if ($error !== ''): ?>
                    <div class="notice notice-error" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></svg>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php elseif ($loggedOut): ?>
                    <div class="notice notice-success" role="status">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>
                        <span>Anda berhasil keluar dengan aman.</span>
                    </div>
                <?php endif; ?>

                <form class="login-form" method="post" action="login.php" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($loginCsrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="field">
                        <label for="username">Username</label>
                        <div class="input-wrap">
                            <input id="username" name="username" type="text" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>" placeholder="Masukkan username" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Kata sandi</label>
                        <div class="input-wrap">
                            <input id="password" name="password" type="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <button class="toggle-password" id="togglePassword" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                            </button>
                        </div>
                    </div>

                    <button class="submit-button" type="submit">
                        <span>Masuk ke Dashboard</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-5-5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </form>

                <div class="secure-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                    <span>Akses dilindungi oleh secure session</span>
                </div>
            </div>
        </section>
    </main>

    <script nonce="<?= htmlspecialchars($scriptNonce, ENT_QUOTES, 'UTF-8') ?>">
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');
        const loginShell = document.getElementById('loginShell');

        togglePassword.addEventListener('click', () => {
            const showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            togglePassword.setAttribute('aria-pressed', String(showPassword));
            togglePassword.setAttribute('aria-label', showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            passwordInput.focus();
        });

        if (window.matchMedia('(pointer: fine)').matches && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.addEventListener('pointermove', (event) => {
                const rotateY = ((event.clientX / window.innerWidth) - 0.5) * 2.4;
                const rotateX = ((event.clientY / window.innerHeight) - 0.5) * -1.8;
                loginShell.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });

            document.addEventListener('pointerleave', () => {
                loginShell.style.transform = 'rotateX(0) rotateY(0)';
            });
        }
    </script>
</body>
</html>
