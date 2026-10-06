<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Proteksi hak akses Administrator
checkAuthAdmin();

$pdo = getDBConnection();
$admin = getLoggedInAdmin();

if (!$admin) {
    session_destroy();
    header("Location: login_admin.php?msg=auth_required");
    exit;
}

// ==========================================
// PENGAMBILAN DATA GLOBAL UNIVERSITAS
// ==========================================

// 1. Hitung Metrik Utama Universitas
$totalFaculties = (int)$pdo->query("SELECT COUNT(*) FROM faculties")->fetchColumn();
$totalPrograms = (int)$pdo->query("SELECT COUNT(*) FROM study_programs")->fetchColumn();
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Aktif'")->fetchColumn();
$leaveStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Cuti'")->fetchColumn();
$gradStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Lulus'")->fetchColumn();
$totalLecturers = (int)$pdo->query("SELECT COUNT(*) FROM lecturers")->fetchColumn();
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$totalClasses = (int)$pdo->query("SELECT COUNT(*) FROM course_schedules WHERE academic_year_id = 3")->fetchColumn();
$krsPending = (int)$pdo->query("SELECT COUNT(*) FROM krs WHERE status = 'Diajukan'")->fetchColumn();

// 2. Data Pengguna (Users)
$usersList = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

// 3. Data Fakultas & Prodi
$facultiesList = $pdo->query("SELECT * FROM faculties ORDER BY id ASC")->fetchAll();
$programsList = $pdo->query("
    SELECT sp.*, f.name AS faculty_name, f.code AS faculty_code 
    FROM study_programs sp 
    JOIN faculties f ON sp.faculty_id = f.id 
    ORDER BY sp.id ASC
")->fetchAll();

// 4. Data Mahasiswa Seluruh Universitas
$allStudents = $pdo->query("
    SELECT s.*, sp.name AS study_program_name, f.code AS faculty_code 
    FROM students s
    JOIN study_programs sp ON s.study_program_id = sp.id
    JOIN faculties f ON s.faculty_id = f.id
    ORDER BY s.nim ASC
")->fetchAll();

// 5. Data Dosen Seluruh Universitas
$allLecturers = $pdo->query("
    SELECT l.*, sp.name AS study_program_name, f.code AS faculty_code 
    FROM lecturers l
    JOIN study_programs sp ON l.study_program_id = sp.id
    JOIN faculties f ON l.faculty_id = f.id
    ORDER BY l.name ASC
")->fetchAll();

// 6. Data Tahun Akademik
$academicYears = $pdo->query("SELECT * FROM academic_years ORDER BY id DESC")->fetchAll();

// 7. Audit Log Terbaru (50 item)
$auditLogs = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50")->fetchAll();

// 8. Pengaturan Sistem
$settingsRows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$univName = $settingsRows['university_name'] ?? 'Universitas Nusantara Mandiri';
$univEmail = $settingsRows['university_email'] ?? 'info@univ-nusantara.ac.id';
$univPhone = $settingsRows['university_phone'] ?? '(021) 7890-1234';
$univAddress = $settingsRows['university_address'] ?? 'Jl. Danau Sentani Raya No. 99, Jakarta';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pusat Kendali Administrator — <?= htmlspecialchars($univName) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .admin-sidebar {
      background: #0b1120;
    }
    .admin-badge {
      background: #dc2626;
      color: #fff;
      font-size: 0.7rem;
      font-weight: 800;
      padding: 3px 8px;
      border-radius: var(--radius-full);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .stat-card-admin {
      background: #ffffff;
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      padding: 18px;
      box-shadow: var(--shadow-sm);
    }
    .stat-card-admin .label {
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
    }
    .stat-card-admin .val {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--primary);
      margin-top: 4px;
    }
  </style>
</head>
<body>

<div class="portal-layout">

  <!-- ========================================================
       SIDEBAR ADMINISTRATOR UNIVERSITAS
       ======================================================== -->
  <aside class="sidebar admin-sidebar" id="sidebar">
    <div class="sidebar-brand" style="border-color: rgba(255, 255, 255, 0.08);">
      <img src="assets/img/logo-universitas.svg" alt="Logo">
      <div class="sidebar-brand-text">
        <h2>CENTRAL ADMIN</h2>
        <p>Master Control Unit</p>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Pusat Kendali</div>

      <div class="nav-item active" onclick="switchAdminTab('dashboard', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        <span>Dashboard Admin</span>
      </div>

      <div class="nav-item" onclick="switchAdminTab('users', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span>Manajemen Pengguna</span>
      </div>

      <div class="nav-section-title">Struktur Akademik</div>

      <div class="nav-item" onclick="switchAdminTab('fakultas', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        <span>Fakultas</span>
      </div>

      <div class="nav-item" onclick="switchAdminTab('prodi', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>
        <span>Program Studi</span>
      </div>

      <div class="nav-item" onclick="switchAdminTab('mahasiswa', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        <span>Semua Mahasiswa</span>
      </div>

      <div class="nav-item" onclick="switchAdminTab('dosen', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
        <span>Semua Dosen</span>
      </div>

      <div class="nav-section-title">Tata Kelola & Periode</div>

      <div class="nav-item" onclick="switchAdminTab('tahunakademik', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line></svg>
        <span>Tahun Akademik</span>
      </div>

      <div class="nav-item" onclick="switchAdminTab('pengumuman', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <span>Pengumuman Global</span>
      </div>

      <div class="nav-item" onclick="switchAdminTab('auditlog', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
        <span>Audit Log Aktivitas</span>
      </div>

      <div class="nav-item" onclick="switchAdminTab('pengaturan', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        <span>Pengaturan Sistem</span>
      </div>
    </nav>

    <div class="sidebar-footer">
      <a href="logout_admin.php" class="logout-btn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span>Keluar Sistem</span>
      </a>
    </div>
  </aside>

  <!-- ========================================================
       MAIN WRAPPER ADMIN
       ======================================================== -->
  <main class="main-wrapper">

    <!-- Top Navbar -->
    <header class="top-navbar">
      <div class="nav-left">
        <button class="mobile-toggle" onclick="toggleSidebar()">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <div style="display:flex; align-items:center; gap:12px;">
          <span class="admin-badge">Super Admin</span>
          <span style="font-weight:700; font-size:0.95rem; color:var(--primary);"><?= htmlspecialchars($univName) ?></span>
        </div>
      </div>

      <div class="nav-right">
        <span style="font-size:0.82rem; color:var(--text-muted);">
          Login as: <strong><?= htmlspecialchars($admin['username']) ?></strong>
        </span>
        <div style="width:38px; height:38px; border-radius:50%; background:#dc2626; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.85rem;">
          ADM
        </div>
      </div>
    </header>

    <!-- Page Content Container -->
    <div class="page-container">

      <!-- ========================================================
           TAB 1: DASHBOARD MONITORING PUSAT
           ======================================================== -->
      <div id="tab-dashboard" class="tab-content active">

        <!-- Top Banner -->
        <div style="background: linear-gradient(135deg, #020617 0%, #1e293b 100%); border-radius:var(--radius-lg); padding:28px 32px; color:#fff; margin-bottom:28px;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
              <span style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em; color:#f87171; font-weight:700;">
                Executive Command Center
              </span>
              <h2 style="font-size:1.6rem; font-weight:800; margin-top:2px;">Monitoring Sistem Akademik Terpadu</h2>
              <p style="font-size:0.88rem; color:#94a3b8; margin:0;">
                Kontrol keseluruhan fakultas, jurusan, pengguna, dan integritas data pangkalan perguruan tinggi.
              </p>
            </div>
            <div style="display:flex; gap:10px;">
              <button class="btn btn-primary" onclick="openAddUserModal()" style="background:#dc2626; border-color:#ef4444;">
                + Tambah Pengguna Baru
              </button>
            </div>
          </div>
        </div>

        <!-- 8 Statistik Cards Grid -->
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:28px;">
          <div class="stat-card-admin">
            <div class="label">Total Fakultas</div>
            <div class="val"><?= $totalFaculties ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Struktur Fakultas Aktif</div>
          </div>

          <div class="stat-card-admin">
            <div class="label">Total Program Studi</div>
            <div class="val" style="color:var(--brand-blue);"><?= $totalPrograms ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Sarjana & Vokasi</div>
          </div>

          <div class="stat-card-admin">
            <div class="label">Total Mahasiswa</div>
            <div class="val" style="color:var(--success);"><?= $totalStudents ?></div>
            <div style="font-size:0.75rem; color:var(--success); margin-top:4px;"><?= $activeStudents ?> Mahasiswa Aktif</div>
          </div>

          <div class="stat-card-admin">
            <div class="label">Total Dosen Tetap</div>
            <div class="val"><?= $totalLecturers ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Tenaga Pendidik Terdaftar</div>
          </div>

          <div class="stat-card-admin">
            <div class="label">Total Mata Kuliah</div>
            <div class="val"><?= $totalCourses ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Katalog Kurikulum Pusat</div>
          </div>

          <div class="stat-card-admin">
            <div class="label">Kelas Perkuliahan</div>
            <div class="val" style="color:var(--info);"><?= $totalClasses ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Semester Ganjil 2024/2025</div>
          </div>

          <div class="stat-card-admin">
            <div class="label">KRS Diajukan</div>
            <div class="val" style="color:var(--warning);"><?= $krsPending ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Menunggu Review Prodi</div>
          </div>

          <div class="stat-card-admin">
            <div class="label">Mahasiswa Cuti</div>
            <div class="val" style="color:var(--danger);"><?= $leaveStudents ?></div>
            <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Status Izin Resmi</div>
          </div>
        </div>

        <!-- Section: Aktivitas Sistem Terbaru (Audit Log Live Feed) -->
        <div class="card" style="margin-bottom:28px;">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <div>
              <h3 class="card-title" style="margin:0;">Aktivitas Sistem Terbaru (Audit Log)</h3>
              <p style="font-size:0.8rem; color:var(--text-muted); margin:0;">Pencatatan real-time aksi pengguna untuk keamanan data</p>
            </div>
            <button class="btn btn-outline btn-sm" onclick="switchAdminTab('auditlog')">Buka Log Lengkap</button>
          </div>

          <div class="table-responsive">
            <table class="academic-table" style="font-size:0.82rem;">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Pengguna</th>
                  <th>Role</th>
                  <th>Aktivitas</th>
                  <th>Rincian Catatan</th>
                  <th>IP Address</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $topLogs = array_slice($auditLogs, 0, 6);
                foreach ($topLogs as $log): 
                ?>
                  <tr>
                    <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($log['username']) ?></strong></td>
                    <td><span class="badge badge-outline-white" style="color:var(--primary); font-size:0.72rem;"><?= strtoupper(htmlspecialchars($log['role'])) ?></span></td>
                    <td><strong><?= htmlspecialchars($log['activity']) ?></strong></td>
                    <td style="max-width:320px;"><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                    <td><code><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></code></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- ========================================================
           TAB 2: MANAJEMEN PENGGUNA (USERS & RBAC)
           ======================================================== -->
      <div id="tab-users" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Manajemen Pengguna & Hak Akses</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Kontrol akun Administrator, Program Studi, Dosen, dan Mahasiswa</p>
          </div>
          <button class="btn btn-primary" onclick="openAddUserModal()">
            + Tambah Pengguna Baru
          </button>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status Akun</th>
                <th>Terakhir Masuk</th>
                <th>Dibuat Pada</th>
                <th style="text-align:center;">Tindakan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($usersList as $u): 
                $rBadge = 'badge-info';
                if ($u['role'] === 'admin') $rBadge = 'badge-danger';
                elseif ($u['role'] === 'prodi') $rBadge = 'badge-warning';
                elseif ($u['role'] === 'dosen') $rBadge = 'badge-success';
              ?>
                <tr>
                  <td>#<?= $u['id'] ?></td>
                  <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                  <td><span class="badge <?= $rBadge ?>"><?= strtoupper(htmlspecialchars($u['role'])) ?></span></td>
                  <td>
                    <span class="badge <?= (int)$u['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                      <?= (int)$u['is_active'] === 1 ? 'Aktif' : 'Terblokir' ?>
                    </span>
                  </td>
                  <td><?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : 'Belum pernah' ?></td>
                  <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                  <td style="text-align:center;">
                    <div style="display:inline-flex; gap:6px;">
                      <button class="btn btn-sm btn-outline" onclick="openResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                        Reset Sandi
                      </button>
                      <?php if ($u['id'] !== $admin['id']): ?>
                        <button class="btn btn-sm btn-outline" style="color:<?= (int)$u['is_active'] === 1 ? 'var(--danger)' : 'var(--success)' ?>;" onclick="toggleUserStatus(<?= $u['id'] ?>, <?= (int)$u['is_active'] === 1 ? 0 : 1 ?>)">
                          <?= (int)$u['is_active'] === 1 ? 'Blokir' : 'Aktifkan' ?>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 3: MANAJEMEN FAKULTAS
           ======================================================== -->
      <div id="tab-fakultas" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Manajemen Fakultas</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Daftar seluruh fakultas pada universitas</p>
          </div>
          <button class="btn btn-primary" onclick="openAddFacultyModal()">
            + Tambah Fakultas
          </button>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Fakultas</th>
                <th>Dekan Fakultas</th>
                <th>Tanggal Pembentukan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($facultiesList as $f): ?>
                <tr>
                  <td><code><?= htmlspecialchars($f['code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                  <td><?= htmlspecialchars($f['dean']) ?></td>
                  <td><?= date('d M Y', strtotime($f['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 4: MANAJEMEN PROGRAM STUDI
           ======================================================== -->
      <div id="tab-prodi" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Manajemen Program Studi</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Daftar seluruh program studi di universitas</p>
          </div>
          <button class="btn btn-primary" onclick="openAddProdiModal()">
            + Tambah Program Studi
          </button>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Program Studi</th>
                <th>Fakultas</th>
                <th>Jenjang</th>
                <th>Ketua Program Studi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($programsList as $p): ?>
                <tr>
                  <td><code><?= htmlspecialchars($p['code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                  <td><?= htmlspecialchars($p['faculty_name']) ?></td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($p['degree']) ?></span></td>
                  <td><?= htmlspecialchars($p['head_of_program']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 5: SEMUA MAHASISWA & DOSEN
           ======================================================== -->
      <div id="tab-mahasiswa" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Data Seluruh Mahasiswa Universitas</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Pangkalan data mahasiswa lintas fakultas dan jurusan</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <input type="text" id="searchMahasiswa" placeholder="Cari NIM / Nama / Prodi..." class="form-control" style="width:230px; font-size:0.85rem;" onkeyup="filterMahasiswaTable()">
            <button class="btn btn-primary" onclick="openAddStudentModal()">
              + Tambah Mahasiswa
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="academic-table" id="tableMahasiswa">
            <thead>
              <tr>
                <th>NIM</th>
                <th>Nama Mahasiswa</th>
                <th>Program Studi</th>
                <th>Fakultas</th>
                <th>Semester</th>
                <th>Angkatan</th>
                <th>Status</th>
                <th>IPK</th>
                <th style="text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allStudents as $st): ?>
                <tr>
                  <td><code><?= htmlspecialchars($st['nim']) ?></code></td>
                  <td><strong><?= htmlspecialchars($st['name']) ?></strong></td>
                  <td><?= htmlspecialchars($st['study_program_name']) ?></td>
                  <td><?= htmlspecialchars($st['faculty_code']) ?></td>
                  <td>Semester <?= htmlspecialchars($st['semester']) ?></td>
                  <td><?= htmlspecialchars($st['entry_year']) ?></td>
                  <td>
                    <?php
                      $stBadge = 'badge-success';
                      if ($st['status'] === 'Cuti') $stBadge = 'badge-warning';
                      elseif ($st['status'] === 'Lulus') $stBadge = 'badge-info';
                      elseif ($st['status'] === 'Drop Out' || $st['status'] === 'Non-Aktif') $stBadge = 'badge-danger';
                    ?>
                    <span class="badge <?= $stBadge ?>"><?= htmlspecialchars($st['status']) ?></span>
                  </td>
                  <td><strong><?= number_format($st['gpa_cumulative'], 2) ?></strong></td>
                  <td style="text-align:center;">
                    <div style="display:inline-flex; gap:6px;">
                      <button class="btn btn-sm btn-outline" style="color:var(--brand-blue); border-color:var(--brand-blue);" onclick="openEditStudentModal(<?= $st['id'] ?>)" title="Edit Data Mahasiswa">
                        Edit
                      </button>
                      <button class="btn btn-sm btn-outline" style="color:var(--danger); border-color:var(--danger);" onclick="deleteStudent(<?= $st['id'] ?>, '<?= addslashes(htmlspecialchars($st['name'])) ?>')" title="Hapus Mahasiswa">
                        Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="tab-dosen" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Data Seluruh Dosen Universitas</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Tenaga pengajar terdaftar di seluruh fakultas</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <input type="text" id="searchDosen" placeholder="Cari NIDN / Nama Dosen..." class="form-control" style="width:230px; font-size:0.85rem;" onkeyup="filterDosenTable()">
            <button class="btn btn-primary" onclick="openAddLecturerModal()">
              + Tambah Dosen
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="academic-table" id="tableDosen">
            <thead>
              <tr>
                <th>NIDN</th>
                <th>Nama Dosen</th>
                <th>Gelar</th>
                <th>Program Studi</th>
                <th>Fakultas</th>
                <th>Status</th>
                <th style="text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allLecturers as $lc): ?>
                <tr>
                  <td><code><?= htmlspecialchars($lc['nidn']) ?></code></td>
                  <td><strong><?= htmlspecialchars($lc['name']) ?></strong></td>
                  <td><?= htmlspecialchars($lc['academic_degree']) ?></td>
                  <td><?= htmlspecialchars($lc['study_program_name']) ?></td>
                  <td><?= htmlspecialchars($lc['faculty_code']) ?></td>
                  <td>
                    <?php
                      $lcBadge = 'badge-success';
                      if ($lc['status'] === 'Cuti' || $lc['status'] === 'Studi Lanjut') $lcBadge = 'badge-warning';
                      elseif ($lc['status'] === 'Non-Aktif') $lcBadge = 'badge-danger';
                    ?>
                    <span class="badge <?= $lcBadge ?>"><?= htmlspecialchars($lc['status']) ?></span>
                  </td>
                  <td style="text-align:center;">
                    <div style="display:inline-flex; gap:6px;">
                      <button class="btn btn-sm btn-outline" style="color:var(--brand-blue); border-color:var(--brand-blue);" onclick="openEditLecturerModal(<?= $lc['id'] ?>)" title="Edit Data Dosen">
                        Edit
                      </button>
                      <button class="btn btn-sm btn-outline" style="color:var(--danger); border-color:var(--danger);" onclick="deleteLecturer(<?= $lc['id'] ?>, '<?= addslashes(htmlspecialchars($lc['name'])) ?>')" title="Hapus Dosen">
                        Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 6: TAHUN AKADEMIK & KALENDER
           ======================================================== -->
      <div id="tab-tahunakademik" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Manajemen Periode Akademik</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Pengaturan semester aktif universitas (Hanya 1 periode yang dapat aktif)</p>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Tahun Akademik</th>
                <th>Tipe Semester</th>
                <th>Status</th>
                <th style="text-align:center;">Tindakan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($academicYears as $ay): ?>
                <tr>
                  <td><code><?= htmlspecialchars($ay['code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($ay['name']) ?></strong></td>
                  <td><?= htmlspecialchars($ay['semester_type']) ?></td>
                  <td>
                    <span class="badge <?= (int)$ay['is_active'] === 1 ? 'badge-success' : 'badge-outline-white' ?>" style="<?= (int)$ay['is_active'] !== 1 ? 'color:var(--text-muted);' : '' ?>">
                      <?= (int)$ay['is_active'] === 1 ? 'Sedang Aktif' : 'Tidak Aktif' ?>
                    </span>
                  </td>
                  <td style="text-align:center;">
                    <?php if ((int)$ay['is_active'] !== 1): ?>
                      <button class="btn btn-sm btn-primary" onclick="setActiveYear(<?= $ay['id'] ?>)">
                        Aktifkan Periode Ini
                      </button>
                    <?php else: ?>
                      <span style="font-size:0.8rem; color:var(--success); font-weight:700;">Periode Berjalan</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 7: PENGUMUMAN GLOBAL
           ======================================================== -->
      <div id="tab-pengumuman" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Pusat Siaran Pengumuman Universitas</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Publikasikan surat edaran atau kebijakan rektorat kepada sivitas akademika</p>
          </div>
          <button class="btn btn-primary" onclick="openAddUnivAnnouncementModal()">
            + Buat Pengumuman Universitas
          </button>
        </div>

        <div class="card">
          <p style="color:var(--text-muted); font-size:0.9rem;">
            Pengumuman yang dibuat oleh Administrator dapat ditargetkan ke seluruh mahasiswa, seluruh dosen, pengelola prodi, atau seluruh pengunjung landing page.
          </p>
        </div>
      </div>

      <!-- ========================================================
           TAB 8: AUDIT LOG SISTEM LENGKAP
           ======================================================== -->
      <div id="tab-auditlog" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Audit Log & Jejak Keamanan Sistem</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Riwayat pencatatan aktivitas seluruh pengguna sistem (Single Source of Truth)</p>
        </div>

        <div class="table-responsive">
          <table class="academic-table" style="font-size:0.84rem;">
            <thead>
              <tr>
                <th>Waktu Kejadian</th>
                <th>Pengguna</th>
                <th>Role</th>
                <th>Aktivitas</th>
                <th>Entitas Terkait</th>
                <th>Keterangan Aksi</th>
                <th>IP Address</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($auditLogs as $al): ?>
                <tr>
                  <td><?= date('d/m/Y H:i:s', strtotime($al['created_at'])) ?></td>
                  <td><strong><?= htmlspecialchars($al['username']) ?></strong></td>
                  <td><span class="badge badge-info" style="font-size:0.7rem;"><?= strtoupper(htmlspecialchars($al['role'])) ?></span></td>
                  <td><strong><?= htmlspecialchars($al['activity']) ?></strong></td>
                  <td><code><?= htmlspecialchars($al['target_entity'] ?? '-') ?> #<?= $al['target_id'] ?? '' ?></code></td>
                  <td style="max-width:300px;"><?= htmlspecialchars($al['details'] ?? '-') ?></td>
                  <td><code><?= htmlspecialchars($al['ip_address'] ?? '127.0.0.1') ?></code></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 9: PENGATURAN SISTEM
           ======================================================== -->
      <div id="tab-pengaturan" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Pengaturan Konfigurasi Sistem</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Konfigurasi nama universitas, kontak institusi, dan parameter sistem</p>
        </div>

        <div class="card" style="max-width:700px;">
          <form onsubmit="handleSaveSettings(event)">
            <div class="form-group">
              <label class="form-label">Nama Universitas</label>
              <input type="text" id="cfgUnivName" class="form-control" value="<?= htmlspecialchars($univName) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email Resmi Institusi</label>
              <input type="email" id="cfgUnivEmail" class="form-control" value="<?= htmlspecialchars($univEmail) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Nomor Telepon / Call Center</label>
              <input type="text" id="cfgUnivPhone" class="form-control" value="<?= htmlspecialchars($univPhone) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Alamat Kampus Utama</label>
              <textarea id="cfgUnivAddress" class="form-control" rows="3" required><?= htmlspecialchars($univAddress) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">
              Simpan Pengaturan
            </button>
          </form>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- ========================================================
     MODALS FOR ADMIN ACTIONS
     ======================================================== -->

<!-- 1. Modal Tambah User -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal-container" style="max-width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Pengguna Baru</h3>
      <button class="modal-close" onclick="closeModal('addUserModal')">✕</button>
    </div>
    <form onsubmit="handleAddUser(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" id="newUsername" class="form-control" placeholder="Contoh: prodi_fe" required>
        </div>
        <div class="form-group">
          <label class="form-label">Kata Sandi Awal</label>
          <input type="password" id="newUserPass" class="form-control" required placeholder="Minimal 6 karakter">
        </div>
        <div class="form-group">
          <label class="form-label">Role Akses</label>
          <select id="newUserRole" class="form-control" onchange="toggleProdiSelect(this.value)">
            <option value="mahasiswa">Mahasiswa</option>
            <option value="prodi">Pengelola Program Studi</option>
            <option value="dosen">Dosen</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <div class="form-group" id="prodiSelectContainer" style="display:none;">
          <label class="form-label">Tautkan ke Program Studi</label>
          <select id="newUserProdiId" class="form-control">
            <?php foreach ($programsList as $pr): ?>
              <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['name']) ?> (<?= htmlspecialchars($pr['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. Modal Reset Password -->
<div class="modal-overlay" id="resetPassModal">
  <div class="modal-container" style="max-width:440px;">
    <div class="modal-header">
      <h3 class="modal-title">Reset Kata Sandi Pengguna</h3>
      <button class="modal-close" onclick="closeModal('resetPassModal')">✕</button>
    </div>
    <form onsubmit="handleResetPassword(event)">
      <div class="modal-body">
        <input type="hidden" id="resetUserId">
        <p style="margin-bottom:12px;" id="resetUserText">Reset kata sandi untuk akun:</p>
        <div class="form-group">
          <label class="form-label">Kata Sandi Baru</label>
          <input type="text" id="resetNewPass" class="form-control" value="password123" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('resetPassModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Eksekusi Reset</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. Modal Tambah Fakultas -->
<div class="modal-overlay" id="addFacultyModal">
  <div class="modal-container" style="max-width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Fakultas Baru</h3>
      <button class="modal-close" onclick="closeModal('addFacultyModal')">✕</button>
    </div>
    <form onsubmit="handleAddFaculty(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Kode Fakultas</label>
          <input type="text" id="newFacCode" class="form-control" placeholder="FKIP" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Fakultas</label>
          <input type="text" id="newFacName" class="form-control" placeholder="Fakultas Keguruan dan Ilmu Pendidikan" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Dekan</label>
          <input type="text" id="newFacDean" class="form-control" placeholder="Dr. ..." required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addFacultyModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Fakultas</button>
      </div>
    </form>
  </div>
</div>

<!-- 4. Modal Tambah Program Studi -->
<div class="modal-overlay" id="addProdiModal">
  <div class="modal-container" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Program Studi Baru</h3>
      <button class="modal-close" onclick="closeModal('addProdiModal')">✕</button>
    </div>
    <form onsubmit="handleAddProdi(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Fakultas Naungan</label>
          <select id="newProdiFacId" class="form-control" required>
            <?php foreach ($facultiesList as $fac): ?>
              <option value="<?= $fac['id'] ?>"><?= htmlspecialchars($fac['name']) ?> (<?= htmlspecialchars($fac['code']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Kode Program Studi</label>
          <input type="text" id="newProdiCode" class="form-control" placeholder="AK" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Program Studi</label>
          <input type="text" id="newProdiName" class="form-control" placeholder="Akuntansi S1" required>
        </div>
        <div class="form-group">
          <label class="form-label">Ketua Program Studi</label>
          <input type="text" id="newProdiHead" class="form-control" placeholder="Dosen Pengampu..." required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addProdiModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Program Studi</button>
      </div>
    </form>
  </div>
</div>

<!-- 5. Modal Tambah Pengumuman Universitas -->
<div class="modal-overlay" id="addUnivAnnouncementModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="modal-title">Terbitkan Pengumuman Universitas</h3>
      <button class="modal-close" onclick="closeModal('addUnivAnnouncementModal')">✕</button>
    </div>
    <form onsubmit="handleAddUnivAnnouncement(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Judul Pengumuman</label>
          <input type="text" id="univAnnTitle" class="form-control" required placeholder="Judul informasi...">
        </div>
        <div class="form-group">
          <label class="form-label">Target Penerima</label>
          <select id="univAnnTarget" class="form-control">
            <option value="Semua">Semua Pengguna (Mahasiswa, Dosen, Publik)</option>
            <option value="Mahasiswa">Khusus Seluruh Mahasiswa</option>
            <option value="Dosen">Khusus Seluruh Dosen</option>
            <option value="Prodi">Khusus Pengelola Program Studi</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Kategori</label>
          <select id="univAnnCategory" class="form-control">
            <option value="Akademik">Akademik</option>
            <option value="Keuangan">Keuangan</option>
            <option value="Beasiswa">Beasiswa</option>
            <option value="Kemahasiswaan">Kemahasiswaan</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Isi Pengumuman</label>
          <textarea id="univAnnContent" class="form-control" rows="4" required placeholder="Tuliskan isi pengumuman lengkap..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addUnivAnnouncementModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Siarkan Pengumuman</button>
      </div>
    </form>
  </div>
</div>

<!-- 6. Modal Tambah Mahasiswa -->
<div class="modal-overlay" id="addStudentModal">
  <div class="modal-container" style="max-width:680px;">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Mahasiswa Baru</h3>
      <button class="modal-close" onclick="closeModal('addStudentModal')">✕</button>
    </div>
    <form onsubmit="handleAddStudent(event)">
      <div class="modal-body" style="max-height:75vh; overflow-y:auto;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Nomor Induk Mahasiswa (NIM) *</label>
            <input type="text" id="newStudentNim" class="form-control" placeholder="Contoh: 202401009" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" id="newStudentName" class="form-control" placeholder="Nama mahasiswa lengkap" required>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Jenis Kelamin</label>
            <select id="newStudentGender" class="form-control">
              <option value="Laki-laki">Laki-laki</option>
              <option value="Perempuan">Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Lahir</label>
            <input type="date" id="newStudentDob" class="form-control" value="2003-01-01">
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Tempat Lahir</label>
            <input type="text" id="newStudentPob" class="form-control" placeholder="Kota lahir">
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon / WhatsApp</label>
            <input type="text" id="newStudentPhone" class="form-control" placeholder="081234567890">
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Fakultas *</label>
            <select id="newStudentFaculty" class="form-control" required onchange="filterStudentProdi(this.value, 'newStudentProdi')">
              <option value="">-- Pilih Fakultas --</option>
              <?php foreach ($facultiesList as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Program Studi *</label>
            <select id="newStudentProdi" class="form-control" required>
              <option value="">-- Pilih Fakultas Dahulu --</option>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Semester</label>
            <input type="number" id="newStudentSemester" class="form-control" value="1" min="1" max="14" required>
          </div>
          <div class="form-group">
            <label class="form-label">Angkatan / Thn Masuk</label>
            <input type="number" id="newStudentEntryYear" class="form-control" value="<?= date('Y') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status Akademik</label>
            <select id="newStudentStatus" class="form-control">
              <option value="Aktif">Aktif</option>
              <option value="Cuti">Cuti</option>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Email Mahasiswa</label>
            <input type="email" id="newStudentEmail" class="form-control" placeholder="mhs@univ.ac.id">
          </div>
          <div class="form-group">
            <label class="form-label">Dosen Pembimbing Akademik</label>
            <input type="text" id="newStudentAdvisor" class="form-control" placeholder="Nama Dosen Wali">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Alamat Lengkap</label>
          <textarea id="newStudentAddress" class="form-control" rows="2" placeholder="Alamat domisili atau tempat tinggal mahasiswa"></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Kata Sandi Akun Login (Default: sama dengan NIM jika dikosongkan)</label>
          <input type="password" id="newStudentPassword" class="form-control" placeholder="Kosongkan untuk otomatis gunakan NIM">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addStudentModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Daftarkan Mahasiswa</button>
      </div>
    </form>
  </div>
</div>

<!-- 7. Modal Edit Mahasiswa -->
<div class="modal-overlay" id="editStudentModal">
  <div class="modal-container" style="max-width:680px;">
    <div class="modal-header">
      <h3 class="modal-title">Edit Data Mahasiswa</h3>
      <button class="modal-close" onclick="closeModal('editStudentModal')">✕</button>
    </div>
    <form onsubmit="handleEditStudent(event)">
      <input type="hidden" id="editStudentId">
      <div class="modal-body" style="max-height:75vh; overflow-y:auto;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">NIM (Tidak dapat diubah)</label>
            <input type="text" id="editStudentNim" class="form-control" readonly style="background:#f1f5f9; cursor:not-allowed;">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" id="editStudentName" class="form-control" required>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Jenis Kelamin</label>
            <select id="editStudentGender" class="form-control">
              <option value="Laki-laki">Laki-laki</option>
              <option value="Perempuan">Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Lahir</label>
            <input type="date" id="editStudentDob" class="form-control">
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Tempat Lahir</label>
            <input type="text" id="editStudentPob" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon / WhatsApp</label>
            <input type="text" id="editStudentPhone" class="form-control">
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Fakultas *</label>
            <select id="editStudentFaculty" class="form-control" required onchange="filterStudentProdi(this.value, 'editStudentProdi')">
              <?php foreach ($facultiesList as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Program Studi *</label>
            <select id="editStudentProdi" class="form-control" required>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Semester</label>
            <input type="number" id="editStudentSemester" class="form-control" min="1" max="14" required>
          </div>
          <div class="form-group">
            <label class="form-label">IPK Kumulatif</label>
            <input type="number" id="editStudentGpa" class="form-control" step="0.01" min="0" max="4.00">
          </div>
          <div class="form-group">
            <label class="form-label">Total SKS Lulus</label>
            <input type="number" id="editStudentCredits" class="form-control" min="0" max="200">
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Status Akademik</label>
            <select id="editStudentStatus" class="form-control">
              <option value="Aktif">Aktif</option>
              <option value="Cuti">Cuti</option>
              <option value="Lulus">Lulus</option>
              <option value="Drop Out">Drop Out</option>
              <option value="Non-Aktif">Non-Aktif</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Dosen Pembimbing Akademik</label>
            <input type="text" id="editStudentAdvisor" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Mahasiswa</label>
          <input type="email" id="editStudentEmail" class="form-control">
        </div>

        <div class="form-group">
          <label class="form-label">Alamat Lengkap</label>
          <textarea id="editStudentAddress" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editStudentModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- 8. Modal Tambah Dosen -->
<div class="modal-overlay" id="addLecturerModal">
  <div class="modal-container" style="max-width:620px;">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Dosen Baru</h3>
      <button class="modal-close" onclick="closeModal('addLecturerModal')">✕</button>
    </div>
    <form onsubmit="handleAddLecturer(event)">
      <div class="modal-body" style="max-height:75vh; overflow-y:auto;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Nomor Induk Dosen Nasional (NIDN) *</label>
            <input type="text" id="newLecturerNidn" class="form-control" placeholder="Contoh: 0315088201" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Dosen (Lengkap tanpa gelar) *</label>
            <input type="text" id="newLecturerName" class="form-control" placeholder="Contoh: Budi Santoso" required>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Gelar Akademik</label>
            <input type="text" id="newLecturerDegree" class="form-control" placeholder="Contoh: S.Kom., M.Kom., Ph.D.">
          </div>
          <div class="form-group">
            <label class="form-label">Status Kepegawaian</label>
            <select id="newLecturerStatus" class="form-control">
              <option value="Aktif">Aktif</option>
              <option value="Cuti">Cuti</option>
              <option value="Studi Lanjut">Studi Lanjut</option>
              <option value="Non-Aktif">Non-Aktif</option>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Fakultas *</label>
            <select id="newLecturerFaculty" class="form-control" required onchange="filterStudentProdi(this.value, 'newLecturerProdi')">
              <option value="">-- Pilih Fakultas --</option>
              <?php foreach ($facultiesList as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Program Studi Homebase *</label>
            <select id="newLecturerProdi" class="form-control" required>
              <option value="">-- Pilih Fakultas Dahulu --</option>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Email Dosen</label>
            <input type="email" id="newLecturerEmail" class="form-control" placeholder="dosen@univ-nusantara.ac.id">
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon / WhatsApp</label>
            <input type="text" id="newLecturerPhone" class="form-control" placeholder="08123456789">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Mata Kuliah Diampu / Keahlian</label>
          <input type="text" id="newLecturerCourses" class="form-control" placeholder="Contoh: Rekayasa Perangkat Lunak, Struktur Data, AI">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addLecturerModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Data Dosen</button>
      </div>
    </form>
  </div>
</div>

<!-- 9. Modal Edit Dosen -->
<div class="modal-overlay" id="editLecturerModal">
  <div class="modal-container" style="max-width:620px;">
    <div class="modal-header">
      <h3 class="modal-title">Edit Data Dosen</h3>
      <button class="modal-close" onclick="closeModal('editLecturerModal')">✕</button>
    </div>
    <form onsubmit="handleEditLecturer(event)">
      <input type="hidden" id="editLecturerId">
      <div class="modal-body" style="max-height:75vh; overflow-y:auto;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">NIDN (Tidak dapat diubah)</label>
            <input type="text" id="editLecturerNidn" class="form-control" readonly style="background:#f1f5f9; cursor:not-allowed;">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Dosen *</label>
            <input type="text" id="editLecturerName" class="form-control" required>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Gelar Akademik</label>
            <input type="text" id="editLecturerDegree" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Status Kepegawaian</label>
            <select id="editLecturerStatus" class="form-control">
              <option value="Aktif">Aktif</option>
              <option value="Cuti">Cuti</option>
              <option value="Studi Lanjut">Studi Lanjut</option>
              <option value="Non-Aktif">Non-Aktif</option>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Fakultas *</label>
            <select id="editLecturerFaculty" class="form-control" required onchange="filterStudentProdi(this.value, 'editLecturerProdi')">
              <?php foreach ($facultiesList as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Program Studi Homebase *</label>
            <select id="editLecturerProdi" class="form-control" required>
            </select>
          </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label">Email Dosen</label>
            <input type="email" id="editLecturerEmail" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">No. Telepon / WhatsApp</label>
            <input type="text" id="editLecturerPhone" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Mata Kuliah Diampu / Keahlian</label>
          <input type="text" id="editLecturerCourses" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editLecturerModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
function switchAdminTab(tabId, el) {
  const tabs = document.querySelectorAll('.tab-content');
  tabs.forEach(t => t.classList.remove('active'));

  const target = document.getElementById('tab-' + tabId);
  if (target) target.classList.add('active');

  const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
  navItems.forEach(n => n.classList.remove('active'));
  if (el) el.classList.add('active');

  const sidebar = document.getElementById('sidebar');
  if (sidebar) sidebar.classList.remove('mobile-open');
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('mobile-open');
}

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function toggleProdiSelect(role) {
  const cont = document.getElementById('prodiSelectContainer');
  cont.style.display = (role === 'prodi') ? 'block' : 'none';
}

// 1. Tambah User
function openAddUserModal() { openModal('addUserModal'); }
function handleAddUser(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_user');
  fd.append('username', document.getElementById('newUsername').value);
  fd.append('password', document.getElementById('newUserPass').value);
  fd.append('role', document.getElementById('newUserRole').value);
  fd.append('study_program_id', document.getElementById('newUserProdiId').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addUserModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 2. Toggle Status User
function toggleUserStatus(id, st) {
  if (!confirm('Apakah Anda yakin ingin memperbarui status akun ini?')) return;
  const fd = new FormData();
  fd.append('action', 'toggle_user_status');
  fd.append('user_id', id);
  fd.append('status', st);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 3. Reset Password
function openResetPasswordModal(id, uname) {
  document.getElementById('resetUserId').value = id;
  document.getElementById('resetUserText').innerText = 'Reset kata sandi untuk akun: ' + uname;
  openModal('resetPassModal');
}

function handleResetPassword(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'reset_user_password');
  fd.append('user_id', document.getElementById('resetUserId').value);
  fd.append('new_password', document.getElementById('resetNewPass').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('resetPassModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 4. Tambah Fakultas
function openAddFacultyModal() { openModal('addFacultyModal'); }
function handleAddFaculty(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_faculty');
  fd.append('code', document.getElementById('newFacCode').value);
  fd.append('name', document.getElementById('newFacName').value);
  fd.append('dean', document.getElementById('newFacDean').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addFacultyModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 5. Tambah Program Studi
function openAddProdiModal() { openModal('addProdiModal'); }
function handleAddProdi(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_program_study');
  fd.append('faculty_id', document.getElementById('newProdiFacId').value);
  fd.append('code', document.getElementById('newProdiCode').value);
  fd.append('name', document.getElementById('newProdiName').value);
  fd.append('head_of_program', document.getElementById('newProdiHead').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addProdiModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 6. Set Active Year
function setActiveYear(id) {
  if (!confirm('Apakah Anda yakin ingin mengaktifkan tahun akademik ini?')) return;
  const fd = new FormData();
  fd.append('action', 'set_active_academic_year');
  fd.append('year_id', id);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 7. Pengumuman Univ
function openAddUnivAnnouncementModal() { openModal('addUnivAnnouncementModal'); }
function handleAddUnivAnnouncement(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'create_university_announcement');
  fd.append('title', document.getElementById('univAnnTitle').value);
  fd.append('content', document.getElementById('univAnnContent').value);
  fd.append('target_role', document.getElementById('univAnnTarget').value);
  fd.append('category', document.getElementById('univAnnCategory').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addUnivAnnouncementModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 8. Simpan Pengaturan
function handleSaveSettings(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'save_system_settings');
  fd.append('university_name', document.getElementById('cfgUnivName').value);
  fd.append('university_email', document.getElementById('cfgUnivEmail').value);
  fd.append('university_phone', document.getElementById('cfgUnivPhone').value);
  fd.append('university_address', document.getElementById('cfgUnivAddress').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// ==========================================
// DYNAMIC PRODI FILTER & CLIENT SEARCH
// ==========================================
const allPrograms = <?= json_encode($programsList) ?>;

function filterStudentProdi(facultyId, targetSelectId, selectedProdiId = null) {
  const select = document.getElementById(targetSelectId);
  select.innerHTML = '';
  
  const defaultOpt = document.createElement('option');
  defaultOpt.value = '';
  defaultOpt.textContent = '-- Pilih Program Studi --';
  select.appendChild(defaultOpt);

  if (!facultyId) return;

  const filtered = allPrograms.filter(p => String(p.faculty_id) === String(facultyId));
  filtered.forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.id;
    opt.textContent = `${p.name} (${p.degree})`;
    if (selectedProdiId && String(p.id) === String(selectedProdiId)) {
      opt.selected = true;
    }
    select.appendChild(opt);
  });
}

function filterMahasiswaTable() {
  const q = document.getElementById('searchMahasiswa').value.toLowerCase();
  const rows = document.querySelectorAll('#tableMahasiswa tbody tr');
  rows.forEach(tr => {
    const text = tr.innerText.toLowerCase();
    tr.style.display = text.includes(q) ? '' : 'none';
  });
}

function filterDosenTable() {
  const q = document.getElementById('searchDosen').value.toLowerCase();
  const rows = document.querySelectorAll('#tableDosen tbody tr');
  rows.forEach(tr => {
    const text = tr.innerText.toLowerCase();
    tr.style.display = text.includes(q) ? '' : 'none';
  });
}

// ==========================================
// 9. MAHASISWA CRUD
// ==========================================
function openAddStudentModal() {
  document.getElementById('newStudentNim').value = '';
  document.getElementById('newStudentName').value = '';
  document.getElementById('newStudentFaculty').value = '';
  document.getElementById('newStudentProdi').innerHTML = '<option value="">-- Pilih Fakultas Dahulu --</option>';
  document.getElementById('newStudentPhone').value = '';
  document.getElementById('newStudentEmail').value = '';
  document.getElementById('newStudentAddress').value = '';
  document.getElementById('newStudentAdvisor').value = '';
  document.getElementById('newStudentPassword').value = '';
  openModal('addStudentModal');
}

function handleAddStudent(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_student');
  fd.append('nim', document.getElementById('newStudentNim').value);
  fd.append('name', document.getElementById('newStudentName').value);
  fd.append('gender', document.getElementById('newStudentGender').value);
  fd.append('date_of_birth', document.getElementById('newStudentDob').value);
  fd.append('place_of_birth', document.getElementById('newStudentPob').value);
  fd.append('phone', document.getElementById('newStudentPhone').value);
  fd.append('email', document.getElementById('newStudentEmail').value);
  fd.append('faculty_id', document.getElementById('newStudentFaculty').value);
  fd.append('study_program_id', document.getElementById('newStudentProdi').value);
  fd.append('semester', document.getElementById('newStudentSemester').value);
  fd.append('entry_year', document.getElementById('newStudentEntryYear').value);
  fd.append('status', document.getElementById('newStudentStatus').value);
  fd.append('advisor_name', document.getElementById('newStudentAdvisor').value);
  fd.append('address', document.getElementById('newStudentAddress').value);
  fd.append('password', document.getElementById('newStudentPassword').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addStudentModal');
      alert(data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => {
      alert('Terjadi kesalahan saat memproses data: ' + err);
    });
}

function openEditStudentModal(studentId) {
  fetch(`admin_api.php?action=get_student&student_id=${studentId}`)
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        alert(res.message || 'Gagal mengambil data mahasiswa');
        return;
      }
      const st = res.data;
      document.getElementById('editStudentId').value = st.id;
      document.getElementById('editStudentNim').value = st.nim;
      document.getElementById('editStudentName').value = st.name;
      document.getElementById('editStudentGender').value = st.gender || 'Laki-laki';
      document.getElementById('editStudentDob').value = st.date_of_birth || '';
      document.getElementById('editStudentPob').value = st.place_of_birth || '';
      document.getElementById('editStudentPhone').value = st.phone || '';
      document.getElementById('editStudentEmail').value = st.email || '';
      document.getElementById('editStudentFaculty').value = st.faculty_id;
      filterStudentProdi(st.faculty_id, 'editStudentProdi', st.study_program_id);
      document.getElementById('editStudentSemester').value = st.semester;
      document.getElementById('editStudentGpa').value = st.gpa_cumulative;
      document.getElementById('editStudentCredits').value = st.total_credits;
      document.getElementById('editStudentStatus').value = st.status;
      document.getElementById('editStudentAdvisor').value = st.advisor_name || '';
      document.getElementById('editStudentAddress').value = st.address || '';
      openModal('editStudentModal');
    })
    .catch(err => alert('Gagal memuat data: ' + err));
}

function handleEditStudent(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'edit_student');
  fd.append('student_id', document.getElementById('editStudentId').value);
  fd.append('name', document.getElementById('editStudentName').value);
  fd.append('gender', document.getElementById('editStudentGender').value);
  fd.append('date_of_birth', document.getElementById('editStudentDob').value);
  fd.append('place_of_birth', document.getElementById('editStudentPob').value);
  fd.append('phone', document.getElementById('editStudentPhone').value);
  fd.append('email', document.getElementById('editStudentEmail').value);
  fd.append('faculty_id', document.getElementById('editStudentFaculty').value);
  fd.append('study_program_id', document.getElementById('editStudentProdi').value);
  fd.append('semester', document.getElementById('editStudentSemester').value);
  fd.append('gpa_cumulative', document.getElementById('editStudentGpa').value);
  fd.append('total_credits', document.getElementById('editStudentCredits').value);
  fd.append('status', document.getElementById('editStudentStatus').value);
  fd.append('advisor_name', document.getElementById('editStudentAdvisor').value);
  fd.append('address', document.getElementById('editStudentAddress').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('editStudentModal');
      alert(data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => {
      alert('Terjadi kesalahan saat menyimpan perubahan: ' + err);
    });
}

function deleteStudent(studentId, name) {
  if (!confirm(`Apakah Anda yakin ingin menghapus data mahasiswa "${name}" beserta akun login dan seluruh relasi datanya? Tindakan ini tidak dapat dibatalkan.`)) {
    return;
  }
  const fd = new FormData();
  fd.append('action', 'delete_student');
  fd.append('student_id', studentId);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => alert('Gagal menghapus mahasiswa: ' + err));
}

// ==========================================
// 10. DOSEN CRUD
// ==========================================
function openAddLecturerModal() {
  document.getElementById('newLecturerNidn').value = '';
  document.getElementById('newLecturerName').value = '';
  document.getElementById('newLecturerDegree').value = '';
  document.getElementById('newLecturerEmail').value = '';
  document.getElementById('newLecturerPhone').value = '';
  document.getElementById('newLecturerCourses').value = '';
  document.getElementById('newLecturerFaculty').value = '';
  document.getElementById('newLecturerProdi').innerHTML = '<option value="">-- Pilih Fakultas Dahulu --</option>';
  openModal('addLecturerModal');
}

function handleAddLecturer(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_lecturer');
  fd.append('nidn', document.getElementById('newLecturerNidn').value);
  fd.append('name', document.getElementById('newLecturerName').value);
  fd.append('academic_degree', document.getElementById('newLecturerDegree').value);
  fd.append('status', document.getElementById('newLecturerStatus').value);
  fd.append('faculty_id', document.getElementById('newLecturerFaculty').value);
  fd.append('study_program_id', document.getElementById('newLecturerProdi').value);
  fd.append('email', document.getElementById('newLecturerEmail').value);
  fd.append('phone', document.getElementById('newLecturerPhone').value);
  fd.append('courses_taught', document.getElementById('newLecturerCourses').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addLecturerModal');
      alert(data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => alert('Terjadi kesalahan: ' + err));
}

function openEditLecturerModal(lecturerId) {
  fetch(`admin_api.php?action=get_lecturer&lecturer_id=${lecturerId}`)
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        alert(res.message || 'Gagal memuat data dosen');
        return;
      }
      const lc = res.data;
      document.getElementById('editLecturerId').value = lc.id;
      document.getElementById('editLecturerNidn').value = lc.nidn;
      document.getElementById('editLecturerName').value = lc.name;
      document.getElementById('editLecturerDegree').value = lc.academic_degree || '';
      document.getElementById('editLecturerStatus').value = lc.status || 'Aktif';
      document.getElementById('editLecturerFaculty').value = lc.faculty_id;
      filterStudentProdi(lc.faculty_id, 'editLecturerProdi', lc.study_program_id);
      document.getElementById('editLecturerEmail').value = lc.email || '';
      document.getElementById('editLecturerPhone').value = lc.phone || '';
      document.getElementById('editLecturerCourses').value = lc.courses_taught || '';
      openModal('editLecturerModal');
    })
    .catch(err => alert('Gagal memuat data: ' + err));
}

function handleEditLecturer(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'edit_lecturer');
  fd.append('lecturer_id', document.getElementById('editLecturerId').value);
  fd.append('name', document.getElementById('editLecturerName').value);
  fd.append('academic_degree', document.getElementById('editLecturerDegree').value);
  fd.append('status', document.getElementById('editLecturerStatus').value);
  fd.append('faculty_id', document.getElementById('editLecturerFaculty').value);
  fd.append('study_program_id', document.getElementById('editLecturerProdi').value);
  fd.append('email', document.getElementById('editLecturerEmail').value);
  fd.append('phone', document.getElementById('editLecturerPhone').value);
  fd.append('courses_taught', document.getElementById('editLecturerCourses').value);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('editLecturerModal');
      alert(data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => alert('Terjadi kesalahan saat menyimpan perubahan: ' + err));
}

function deleteLecturer(lecturerId, name) {
  if (!confirm(`Apakah Anda yakin ingin menghapus data dosen "${name}"? Tindakan ini tidak dapat dibatalkan.`)) {
    return;
  }
  const fd = new FormData();
  fd.append('action', 'delete_lecturer');
  fd.append('lecturer_id', lecturerId);

  fetch('admin_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => alert('Gagal menghapus dosen: ' + err));
}
</script>

</body>
</html>
