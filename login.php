<?php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/config/auth.php';

// Jika pengguna sudah login, langsung arahkan ke dashboard masing-masing sesuai role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'prodi') {
        header("Location: prodi_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'mahasiswa') {
        header("Location: dashboard.php");
        exit;
    }
}

$error = '';
$success = '';
$username = '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'auth_required') {
        $error = 'Silakan masuk terlebih dahulu untuk mengakses sistem.';
    } elseif ($_GET['msg'] === 'logged_out') {
        $success = 'Anda telah berhasil keluar dari sistem.';
    }
}

// Proses Login Satu Pintu (Unified Single Sign-On)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (empty($username)) {
        $error = 'NIM / Username / ID Pengguna wajib diisi.';
    } elseif (empty($password)) {
        $error = 'Kata sandi / Password wajib diisi.';
    } else {
        try {
            $pdo = getDBConnection();

            // Cari user berdasarkan username (NIM mahasiswa, id prodi, atau username admin)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Akun dengan NIM / Username tersebut tidak terdaftar.';
            } elseif ((int)$user['is_active'] !== 1) {
                $error = 'Akun Anda berstatus tidak aktif atau terblokir. Silakan hubungi Administrator.';
            } elseif (!verifyUserPassword($password, $user['password'])) {
                $error = 'Kata sandi yang Anda masukkan salah. Silakan coba kembali.';
            } else {
                // ==========================================
                // REDIRECT OTOMATIS BERDASARKAN ROLE PENGGUNA
                // ==========================================
                $role = $user['role'];

                // 1. ROLE: ADMINISTRATOR PUSAT
                if ($role === 'admin') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = 'admin';

                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                    logAuditActivity($pdo, 'Login Administrator', 'users', $user['id'], 'Administrator pusat berhasil masuk melalui Portal Terpadu.');

                    header("Location: admin_dashboard.php");
                    exit;

                // 2. ROLE: PENGELOLA PROGRAM STUDI (PRODI)
                } elseif ($role === 'prodi') {
                    $prodiStmt = $pdo->prepare("
                        SELECT psu.study_program_id, sp.name AS study_program_name, sp.code AS study_program_code
                        FROM program_study_users psu
                        JOIN study_programs sp ON psu.study_program_id = sp.id
                        WHERE psu.user_id = ? LIMIT 1
                    ");
                    $prodiStmt->execute([$user['id']]);
                    $prodiData = $prodiStmt->fetch();

                    if (!$prodiData) {
                        $error = 'Akun Pengelola Prodi ini belum ditautkan ke Program Studi mana pun. Hubungi Administrator.';
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = 'prodi';
                        $_SESSION['study_program_id'] = $prodiData['study_program_id'];
                        $_SESSION['study_program_name'] = $prodiData['study_program_name'];

                        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        logAuditActivity($pdo, 'Login Pengelola Prodi', 'users', $user['id'], "Pengelola Program Studi {$prodiData['study_program_name']} berhasil masuk.");

                        header("Location: prodi_dashboard.php");
                        exit;
                    }

                // 3. ROLE: MAHASISWA
                } elseif ($role === 'mahasiswa') {
                    $studentStmt = $pdo->prepare("SELECT id, name, status FROM students WHERE user_id = ? LIMIT 1");
                    $studentStmt->execute([$user['id']]);
                    $student = $studentStmt->fetch();

                    if (!$student) {
                        $error = 'Data profil mahasiswa belum terkonfigurasi. Hubungi BAAK.';
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = 'mahasiswa';
                        $_SESSION['student_id'] = $student['id'];
                        $_SESSION['student_name'] = $student['name'];
                        $_SESSION['nim'] = $user['username'];

                        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        logAuditActivity($pdo, 'Login Mahasiswa', 'users', $user['id'], "Mahasiswa {$student['name']} berhasil masuk.");

                        header("Location: dashboard.php");
                        exit;
                    }

                // 4. ROLE LAIN (DOSEN / UMUM)
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $role;

                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                    header("Location: dashboard.php");
                    exit;
                }
            }
        } catch (Exception $e) {
            $error = 'Terjadi kendala pada sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Terpadu — Universitas Nusantara Mandiri</title>
  <meta name="description" content="Pintu Masuk Tunggal Sistem Informasi Akademik Universitas Nusantara Mandiri.">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .role-pills {
      display: flex;
      gap: 6px;
      margin-bottom: 20px;
      background: #f1f5f9;
      padding: 4px;
      border-radius: var(--radius-md);
    }
    .role-pill {
      flex: 1;
      text-align: center;
      padding: 8px 6px;
      font-size: 0.78rem;
      font-weight: 600;
      color: #64748b;
      border-radius: var(--radius-sm);
      cursor: pointer;
      transition: var(--transition-fast);
      border: none;
      background: transparent;
    }
    .role-pill.active {
      background: #ffffff;
      color: var(--primary);
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      font-weight: 700;
    }
  </style>
</head>
<body class="login-page-body">

  <div class="login-card" style="max-width: 440px;">
    <div class="login-header">
      <a href="index.php">
        <img src="assets/img/logo-universitas.svg" alt="Logo Universitas" class="login-logo">
      </a>
      <h1 class="login-title">Portal SIAKAD Terpadu</h1>
      <p class="login-subtitle">Universitas Nusantara Mandiri</p>
    </div>

    <!-- Info Satu Pintu Akses -->
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:var(--radius-md); padding:10px 14px; margin-bottom:18px; font-size:0.82rem; color:#1e40af; display:flex; align-items:center; gap:8px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
      <span>Satu akses login untuk <strong>Mahasiswa</strong>, <strong>Prodi</strong>, dan <strong>Admin</strong>. Sistem otomatis mendeteksi role Anda.</span>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <span><?= htmlspecialchars($success) ?></span>
      </div>
    <?php endif; ?>

    <form action="login.php" method="POST" id="loginForm">
      <div class="form-group">
        <label for="username" class="form-label" id="usernameLabel">NIM / Username / ID Pengguna</label>
        <input 
          type="text" 
          id="username" 
          name="username" 
          class="form-control" 
          placeholder="Masukkan NIM, ID Prodi, atau Admin" 
          value="<?= htmlspecialchars($username) ?>" 
          required 
          autocomplete="username"
          autofocus
        >
      </div>

      <div class="form-group">
        <label for="password" class="form-label">Kata Sandi / Password</label>
        <div style="position: relative;">
          <input 
            type="password" 
            id="password" 
            name="password" 
            class="form-control" 
            placeholder="Masukkan kata sandi Anda" 
            required 
            autocomplete="current-password"
          >
          <button 
            type="button" 
            id="togglePassword" 
            style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer;"
            title="Tampilkan / Sembunyikan Sandi"
          >
            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
          </button>
        </div>
      </div>

      <div class="form-row">
        <label class="checkbox-label">
          <input type="checkbox" name="remember" id="remember" value="1">
          <span>Ingat Saya</span>
        </label>
        <a href="#modalHelpdesk" onclick="openHelpdeskModal(event)" class="link-help">Lupa Password?</a>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 0.95rem; font-weight:700;">
        Masuk ke Sistem
      </button>
    </form>

    <!-- Quick Demo Access Helper Tabs -->
    <div class="login-demo-helper" style="margin-top:20px;">
      <div style="font-weight:700; color:var(--primary); font-size:0.85rem; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
        <span>Pilihan Cepat Akun Demo:</span>
      </div>
      <div style="display:flex; gap:6px; flex-wrap:wrap;">
        <button type="button" class="btn btn-sm btn-outline" onclick="fillDemo('mahasiswa')" style="flex:1; padding:6px 8px; font-size:0.75rem;">
          Mahasiswa
        </button>
        <button type="button" class="btn btn-sm btn-outline" onclick="fillDemo('prodi')" style="flex:1; padding:6px 8px; font-size:0.75rem;">
          Pengelola Prodi
        </button>
        <button type="button" class="btn btn-sm btn-outline" onclick="fillDemo('admin')" style="flex:1; padding:6px 8px; font-size:0.75rem;">
          Administrator
        </button>
      </div>
      <div id="demoDetailText" style="font-size:0.78rem; color:var(--text-muted); margin-top:8px;">
        Klik salah satu tombol demo di atas untuk mengisi otomatis akun uji coba.
      </div>
    </div>

    <!-- Helpdesk Information -->
    <div style="margin-top: 22px; text-align: center; font-size: 0.82rem; color: var(--text-muted); border-top: 1px solid var(--border-subtle); padding-top: 16px;">
      <a href="index.php" style="display:inline-block; color:var(--brand-blue); font-weight:600;">
        &larr; Kembali ke Beranda Utama
      </a>
    </div>
  </div>

  <!-- Helpdesk Modal for Lupa Password -->
  <div class="modal-overlay" id="helpModal">
    <div class="modal-container" style="max-width: 480px;">
      <div class="modal-header">
        <h3 class="modal-title">Bantuan Reset Password</h3>
        <button type="button" class="modal-close" onclick="closeHelpdeskModal()">✕</button>
      </div>
      <div class="modal-body">
        <p style="margin-bottom: 12px; color: var(--text-main);">
          Demi menjaga keamanan data akademik dan operasional universitas, reset kata sandi dilakukan secara terpusat:
        </p>
        <div style="background: var(--bg-subtle); padding: 14px; border-radius: var(--radius-sm); margin-bottom: 14px;">
          <strong>Panduan Pemulihan:</strong>
          <ul style="margin-left: 18px; margin-top: 6px; font-size: 0.85rem; color: var(--text-muted);">
            <li><strong>Mahasiswa:</strong> Kirim email ke <code>baak@univ-nusantara.ac.id</code> atau ke loket BAAK dengan melampirkan foto KTM.</li>
            <li><strong>Program Studi:</strong> Hubungi Biro Administrasi Akademik Pusat.</li>
            <li><strong>Administrator:</strong> Hubungi Unit Pelaksana Teknis Teknologi Informasi (UPT-TI).</li>
          </ul>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="closeHelpdeskModal()">Tutup</button>
      </div>
    </div>
  </div>

  <script>
    function fillDemo(role) {
      const u = document.getElementById('username');
      const p = document.getElementById('password');
      const t = document.getElementById('demoDetailText');

      if (role === 'mahasiswa') {
        u.value = '202401001';
        p.value = 'mahasiswa123';
        t.innerHTML = '<strong>Akun Mahasiswa:</strong> NIM <code>202401001</code> / Pass <code>mahasiswa123</code> &rarr; Masuk ke <em>Dashboard Mahasiswa</em>.';
      } else if (role === 'prodi') {
        u.value = 'prodi_ti';
        p.value = 'prodi123';
        t.innerHTML = '<strong>Akun Prodi:</strong> User <code>prodi_ti</code> / Pass <code>prodi123</code> &rarr; Masuk ke <em>Dashboard Pengelola Prodi</em>.';
      } else if (role === 'admin') {
        u.value = 'admin';
        p.value = 'admin123';
        t.innerHTML = '<strong>Akun Admin:</strong> User <code>admin</code> / Pass <code>admin123</code> &rarr; Masuk ke <em>Pusat Kendali Admin</em>.';
      }
    }

    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    if (togglePassword) {
      togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
      });
    }

    function openHelpdeskModal(e) {
      if (e) e.preventDefault();
      document.getElementById('helpModal').classList.add('active');
    }

    function closeHelpdeskModal() {
      document.getElementById('helpModal').classList.remove('active');
    }
  </script>
</body>
</html>
