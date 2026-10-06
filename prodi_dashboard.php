<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Proteksi hak akses Program Studi
checkAuthProdi();

$pdo = getDBConnection();
$prodi = getLoggedInProdi();

if (!$prodi) {
    session_destroy();
    header("Location: login_prodi.php?msg=auth_required");
    exit;
}

$studyProgramId = (int)$prodi['study_program_id'];

// ==========================================
// PENGAMBILAN DATA SPESIFIK PROGRAM STUDI (DATA ISOLATION)
// ==========================================

// 1. Data Mahasiswa Prodi Ini
$stdStmt = $pdo->prepare("
    SELECT s.*, u.username, u.is_active 
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.study_program_id = ?
    ORDER BY s.nim ASC
");
$stdStmt->execute([$studyProgramId]);
$studentsList = $stdStmt->fetchAll();

// Hitung Statistik Mahasiswa
$totalStudents = count($studentsList);
$activeStudents = 0;
$leaveStudents = 0;
$graduatedStudents = 0;
$inactiveStudents = 0;
$totalGpa = 0;
$semesterDistribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0];

foreach ($studentsList as $s) {
    if ($s['status'] === 'Aktif') $activeStudents++;
    elseif ($s['status'] === 'Cuti') $leaveStudents++;
    elseif ($s['status'] === 'Lulus') $graduatedStudents++;
    else $inactiveStudents++;

    $totalGpa += (float)$s['gpa_cumulative'];
    $sem = (int)$s['semester'];
    if (isset($semesterDistribution[$sem])) {
        $semesterDistribution[$sem]++;
    }
}
$avgGpa = $totalStudents > 0 ? ($totalGpa / $totalStudents) : 0;

// 2. Data Dosen Prodi Ini
$lecStmt = $pdo->prepare("SELECT * FROM lecturers WHERE study_program_id = ? ORDER BY name ASC");
$lecStmt->execute([$studyProgramId]);
$lecturersList = $lecStmt->fetchAll();
$totalLecturers = count($lecturersList);

// 3. Data Mata Kuliah Prodi Ini
$crsStmt = $pdo->prepare("SELECT * FROM courses WHERE study_program_id = ? ORDER BY semester ASC, code ASC");
$crsStmt->execute([$studyProgramId]);
$coursesList = $crsStmt->fetchAll();
$totalCourses = count($coursesList);

// 4. Data Kelas & Jadwal Kuliah Prodi Ini
$schStmt = $pdo->prepare("
    SELECT cs.*, c.code AS course_code, c.name AS course_name, c.credits, c.semester AS course_sem
    FROM course_schedules cs
    JOIN courses c ON cs.course_id = c.id
    WHERE c.study_program_id = ? AND cs.academic_year_id = 3
    ORDER BY FIELD(cs.day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), cs.start_time
");
$schStmt->execute([$studyProgramId]);
$schedulesList = $schStmt->fetchAll();
$totalClasses = count($schedulesList);

// 5. Data KRS Mahasiswa Prodi Ini
$krsStmt = $pdo->prepare("
    SELECT k.*, s.nim, s.name AS student_name, s.semester AS student_sem, s.advisor_name
    FROM krs k
    JOIN students s ON k.student_id = s.id
    WHERE s.study_program_id = ? AND k.academic_year_id = 3
    ORDER BY FIELD(k.status, 'Diajukan', 'Draft', 'Ditolak', 'Disetujui'), k.updated_at DESC
");
$krsStmt->execute([$studyProgramId]);
$krsList = $krsStmt->fetchAll();

$krsSubmittedCount = 0;
$krsApprovedCount = 0;
foreach ($krsList as $k) {
    if ($k['status'] === 'Diajukan') $krsSubmittedCount++;
    if ($k['status'] === 'Disetujui') $krsApprovedCount++;
}

// 6. Data Layanan Akademik Mahasiswa Prodi Ini
$srvStmt = $pdo->prepare("
    SELECT sr.*, s.nim, s.name AS student_name
    FROM service_requests sr
    JOIN students s ON sr.student_id = s.id
    WHERE s.study_program_id = ?
    ORDER BY sr.submitted_at DESC
");
$srvStmt->execute([$studyProgramId]);
$serviceRequestsList = $srvStmt->fetchAll();

// 7. Pengumuman Khusus Prodi
$annStmt = $pdo->prepare("
    SELECT * FROM announcements 
    WHERE target_study_program_id = ? OR target_role = 'Semua'
    ORDER BY published_at DESC LIMIT 10
");
$annStmt->execute([$studyProgramId]);
$announcementsList = $annStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Pengelola Program Studi — <?= htmlspecialchars($prodi['study_program_name']) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    /* Styling khusus Dashboard Prodi */
    .prodi-header-tag {
      background: rgba(37, 99, 235, 0.12);
      color: var(--brand-blue);
      border: 1px solid rgba(37, 99, 235, 0.3);
      padding: 4px 10px;
      border-radius: var(--radius-full);
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
    }
    .chart-bar-container {
      display: flex;
      align-items: flex-end;
      gap: 16px;
      height: 180px;
      padding-top: 20px;
      border-bottom: 2px solid var(--border-subtle);
    }
    .chart-bar-col {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      height: 100%;
      justify-content: flex-end;
    }
    .chart-bar {
      width: 100%;
      max-width: 36px;
      background: var(--brand-blue);
      border-radius: 4px 4px 0 0;
      transition: height 0.5s ease;
      position: relative;
    }
    .chart-bar-label {
      font-size: 0.74rem;
      color: var(--text-muted);
      font-weight: 600;
    }
    .chart-bar-val {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--primary);
    }
  </style>
</head>
<body>

<div class="portal-layout">

  <!-- ========================================================
       SIDEBAR KHUSUS PROGRAM STUDI
       ======================================================== -->
  <aside class="sidebar" id="sidebar" style="background:#091428;">
    <div class="sidebar-brand" style="border-color: rgba(255, 255, 255, 0.06);">
      <img src="assets/img/logo-universitas.svg" alt="Logo">
      <div class="sidebar-brand-text">
        <h2>SIAKAD PRODI</h2>
        <p><?= htmlspecialchars($prodi['study_program_code']) ?> • <?= htmlspecialchars($prodi['degree']) ?></p>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Navigasi Utama</div>

      <div class="nav-item active" onclick="switchProdiTab('dashboard', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        <span>Dashboard</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('mahasiswa', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        <span>Data Mahasiswa</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('dosen', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        <span>Data Dosen</span>
      </div>

      <div class="nav-section-title">Kurikulum & Perkuliahan</div>

      <div class="nav-item" onclick="switchProdiTab('matakuliah', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
        <span>Mata Kuliah</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('kelas', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
        <span>Kelas Kuliah</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('jadwal', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        <span>Jadwal Kuliah</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('krs', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
        <span>KRS Mahasiswa</span>
        <?php if ($krsSubmittedCount > 0): ?>
          <span style="background:var(--danger); color:#fff; font-size:0.68rem; font-weight:700; padding:2px 7px; border-radius:10px; margin-left:auto;"><?= $krsSubmittedCount ?></span>
        <?php endif; ?>
      </div>

      <div class="nav-item" onclick="switchProdiTab('nilai', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M9 14l2 2 4-4"></path></svg>
        <span>KHS / Nilai</span>
      </div>

      <div class="nav-section-title">Pusat Layanan & Info</div>

      <div class="nav-item" onclick="switchProdiTab('kalender', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line></svg>
        <span>Kalender Akademik</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('pengumuman', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <span>Pengumuman</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('layanan', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
        <span>Layanan Akademik</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('laporan', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
        <span>Laporan Prodi</span>
      </div>

      <div class="nav-item" onclick="switchProdiTab('profilprodi', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        <span>Profil Program Studi</span>
      </div>
    </nav>

    <div class="sidebar-footer">
      <a href="logout_prodi.php" class="logout-btn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span>Keluar (Logout)</span>
      </a>
    </div>
  </aside>

  <!-- ========================================================
       MAIN WRAPPER PRODI
       ======================================================== -->
  <main class="main-wrapper">

    <!-- Top Navbar -->
    <header class="top-navbar">
      <div class="nav-left">
        <button class="mobile-toggle" onclick="toggleSidebar()">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <div style="display:flex; align-items:center; gap:12px;">
          <span class="prodi-header-tag">Role: Program Studi</span>
          <span style="font-weight:700; font-size:0.95rem; color:var(--primary);"><?= htmlspecialchars($prodi['study_program_name']) ?></span>
        </div>
      </div>

      <div class="nav-right">
        <div style="font-size:0.82rem; color:var(--text-muted); text-align:right;">
          <div>Ketua Prodi: <strong><?= htmlspecialchars($prodi['head_of_program']) ?></strong></div>
          <div>Fakultas: <?= htmlspecialchars($prodi['faculty_code']) ?></div>
        </div>
        <div style="width:38px; height:38px; border-radius:50%; background:#1e3a8a; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem;">
          <?= htmlspecialchars($prodi['study_program_code']) ?>
        </div>
      </div>
    </header>

    <!-- Page Content Container -->
    <div class="page-container">

      <!-- ========================================================
           TAB 1: DASHBOARD OVERVIEW PRODI
           ======================================================== -->
      <div id="tab-dashboard" class="tab-content active">

        <!-- Banner Informasi Program Studi -->
        <div style="background: linear-gradient(135deg, #091428 0%, #1e3a8a 100%); border-radius:var(--radius-lg); padding:28px 32px; color:#fff; margin-bottom:28px; box-shadow:var(--shadow-md);">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
            <div>
              <div style="font-size:0.8rem; text-transform:uppercase; letter-spacing:0.08em; color:#93c5fd; margin-bottom:4px;">
                <?= htmlspecialchars($prodi['faculty_name']) ?> • Jenjang <?= htmlspecialchars($prodi['degree']) ?>
              </div>
              <h2 style="font-size:1.6rem; font-weight:800; margin-bottom:6px;"><?= htmlspecialchars($prodi['study_program_name']) ?></h2>
              <div style="font-size:0.88rem; color:#cbd5e1; display:flex; gap:20px; flex-wrap:wrap;">
                <span>Ketua Prodi: <strong><?= htmlspecialchars($prodi['head_of_program']) ?></strong></span>
                <span>Dekan: <strong><?= htmlspecialchars($prodi['dean']) ?></strong></span>
                <span>TA Aktif: <strong>2024/2025 Ganjil</strong></span>
              </div>
            </div>
            <div style="display:flex; gap:10px;">
              <button class="btn btn-primary" onclick="switchProdiTab('krs')" style="background:#2563eb; border-color:#3b82f6;">
                Periksa KRS (<?= $krsSubmittedCount ?>)
              </button>
              <button class="btn btn-outline" onclick="openAddStudentModal()" style="color:#fff; border-color:rgba(255,255,255,0.3);">
                + Tambah Mahasiswa
              </button>
            </div>
          </div>
        </div>

        <!-- 6 Statistik Cards -->
        <div style="display:grid; grid-template-columns:repeat(6, 1fr); gap:16px; margin-bottom:28px;">
          <div class="kpi-card" style="padding:16px;">
            <div>
              <div class="kpi-label">1. Total Mahasiswa</div>
              <div class="kpi-value" style="font-size:1.6rem;"><?= $totalStudents ?></div>
            </div>
          </div>

          <div class="kpi-card" style="padding:16px;">
            <div>
              <div class="kpi-label">2. Mahasiswa Aktif</div>
              <div class="kpi-value" style="font-size:1.6rem; color:var(--success);"><?= $activeStudents ?></div>
            </div>
          </div>

          <div class="kpi-card" style="padding:16px;">
            <div>
              <div class="kpi-label">3. Mahasiswa Cuti</div>
              <div class="kpi-value" style="font-size:1.6rem; color:var(--warning);"><?= $leaveStudents ?></div>
            </div>
          </div>

          <div class="kpi-card" style="padding:16px;">
            <div>
              <div class="kpi-label">4. Total Dosen</div>
              <div class="kpi-value" style="font-size:1.6rem; color:var(--brand-blue);"><?= $totalLecturers ?></div>
            </div>
          </div>

          <div class="kpi-card" style="padding:16px;">
            <div>
              <div class="kpi-label">5. Total MK</div>
              <div class="kpi-value" style="font-size:1.6rem;"><?= $totalCourses ?></div>
            </div>
          </div>

          <div class="kpi-card" style="padding:16px;">
            <div>
              <div class="kpi-label">6. Kelas Aktif</div>
              <div class="kpi-value" style="font-size:1.6rem; color:var(--info);"><?= $totalClasses ?></div>
            </div>
          </div>
        </div>

        <!-- Ringkasan Akademik & Chart -->
        <div style="display:grid; grid-template-columns:1.5fr 1fr; gap:24px; margin-bottom:28px;">
          <!-- Chart Distribusi Mahasiswa per Semester -->
          <div class="card">
            <h3 class="card-title" style="margin-bottom:4px;">Distribusi Mahasiswa per Semester</h3>
            <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:16px;">Grafik jumlah mahasiswa terdaftar dari Semester 1 sampai 8</p>
            
            <div class="chart-bar-container">
              <?php 
              $maxVal = max(1, max($semesterDistribution));
              foreach ($semesterDistribution as $semNum => $cnt): 
                $barHeightPercent = ($cnt / $maxVal) * 100;
              ?>
                <div class="chart-bar-col">
                  <div class="chart-bar-val"><?= $cnt ?></div>
                  <div class="chart-bar" style="height: <?= max(8, $barHeightPercent) ?>%;"></div>
                  <div class="chart-bar-label">Sem <?= $semNum ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <div style="display:flex; justify-content:space-between; margin-top:14px; font-size:0.82rem; color:var(--text-muted);">
              <span>Rata-rata IPK Program Studi: <strong style="color:var(--brand-blue);"><?= number_format($avgGpa, 2) ?></strong></span>
              <span>KRS Terisi: <strong><?= $krsApprovedCount + $krsSubmittedCount ?></strong> / <?= $totalStudents ?> Mahasiswa</span>
            </div>
          </div>

          <!-- Status & Antrean Pelayanan -->
          <div class="card">
            <h3 class="card-title" style="margin-bottom:14px;">Antrean Persetujuan Akademik</h3>
            <div style="display:flex; flex-direction:column; gap:12px;">
              <div style="padding:12px; background:var(--bg-subtle); border-radius:var(--radius-sm); display:flex; justify-content:space-between; align-items:center;">
                <div>
                  <div style="font-weight:700; font-size:0.88rem;">KRS Menunggu Verifikasi</div>
                  <div style="font-size:0.75rem; color:var(--text-muted);">Mahasiswa yang telah submit KRS</div>
                </div>
                <span class="badge badge-warning" style="font-size:0.9rem;"><?= $krsSubmittedCount ?> Berkas</span>
              </div>

              <div style="padding:12px; background:var(--bg-subtle); border-radius:var(--radius-sm); display:flex; justify-content:space-between; align-items:center;">
                <div>
                  <div style="font-weight:700; font-size:0.88rem;">Pengajuan Layanan Mahasiswa</div>
                  <div style="font-size:0.75rem; color:var(--text-muted);">Surat aktif, cuti, atau legalisir</div>
                </div>
                <span class="badge badge-info" style="font-size:0.9rem;"><?= count($serviceRequestsList) ?> Permohonan</span>
              </div>

              <div style="padding:12px; background:var(--bg-subtle); border-radius:var(--radius-sm); display:flex; justify-content:space-between; align-items:center;">
                <div>
                  <div style="font-weight:700; font-size:0.88rem;">Dosen Pembimbing Aktif</div>
                  <div style="font-size:0.75rem; color:var(--text-muted);">Pembimbing Akademik di Jurusan</div>
                </div>
                <span class="badge badge-success" style="font-size:0.9rem;"><?= $totalLecturers ?> Dosen</span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ========================================================
           TAB 2: DATA MAHASISWA PRODI
           ======================================================== -->
      <div id="tab-mahasiswa" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Data Mahasiswa Program Studi</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Daftar mahasiswa terdaftar di <?= htmlspecialchars($prodi['study_program_name']) ?></p>
          </div>
          <button class="btn btn-primary" onclick="openAddStudentModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Tambah Mahasiswa Baru
          </button>
        </div>

        <!-- Filter & Search Box -->
        <div style="background:#fff; border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px; margin-bottom:20px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
          <input type="text" id="searchStudentInput" placeholder="Cari nama atau NIM..." class="form-control" style="width:260px;" onkeyup="filterStudentsTable()">
          <select id="filterSemSelect" class="form-control" style="width:160px;" onchange="filterStudentsTable()">
            <option value="">Semua Semester</option>
            <option value="1">Semester 1</option>
            <option value="3">Semester 3</option>
            <option value="5">Semester 5</option>
            <option value="7">Semester 7</option>
          </select>
          <select id="filterStatusSelect" class="form-control" style="width:160px;" onchange="filterStudentsTable()">
            <option value="">Semua Status</option>
            <option value="Aktif">Aktif</option>
            <option value="Cuti">Cuti</option>
            <option value="Lulus">Lulus</option>
            <option value="Non-Aktif">Non-Aktif</option>
          </select>
        </div>

        <div class="table-responsive">
          <table class="academic-table" id="studentsTable">
            <thead>
              <tr>
                <th>NIM</th>
                <th>Nama Mahasiswa</th>
                <th>Semester</th>
                <th>Angkatan</th>
                <th>Status</th>
                <th>IPK</th>
                <th style="text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($studentsList as $s): 
                $sBadge = 'badge-info';
                if ($s['status'] === 'Aktif') $sBadge = 'badge-success';
                elseif ($s['status'] === 'Cuti') $sBadge = 'badge-warning';
                elseif ($s['status'] === 'Lulus') $sBadge = 'badge-outline-white';
                elseif ($s['status'] === 'Non-Aktif') $sBadge = 'badge-danger';
              ?>
                <tr class="student-row" data-name="<?= strtolower(htmlspecialchars($s['name'])) ?>" data-nim="<?= htmlspecialchars($s['nim']) ?>" data-sem="<?= $s['semester'] ?>" data-status="<?= htmlspecialchars($s['status']) ?>">
                  <td><code><?= htmlspecialchars($s['nim']) ?></code></td>
                  <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                  <td>Semester <?= htmlspecialchars($s['semester']) ?></td>
                  <td><?= htmlspecialchars($s['entry_year']) ?></td>
                  <td><span class="badge <?= $sBadge ?>"><?= htmlspecialchars($s['status']) ?></span></td>
                  <td><strong style="color:var(--brand-blue);"><?= number_format($s['gpa_cumulative'], 2) ?></strong></td>
                  <td style="text-align:center;">
                    <div style="display:inline-flex; gap:6px;">
                      <button class="btn btn-outline btn-sm" onclick="showStudentDetailModal(<?= $s['id'] ?>)">
                        Detail
                      </button>
                      <button class="btn btn-outline btn-sm" onclick="openUpdateStatusModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name']) ?>', '<?= htmlspecialchars($s['status']) ?>')">
                        Ubah Status
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
           TAB 3: DATA DOSEN PRODI
           ======================================================== -->
      <div id="tab-dosen" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Data Dosen Program Studi</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Tenaga Pendidik Terdaftar pada <?= htmlspecialchars($prodi['study_program_name']) ?></p>
          </div>
          <button class="btn btn-primary" onclick="openAddLecturerModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Tambah Dosen
          </button>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>NIDN</th>
                <th>Nama Dosen & Gelar</th>
                <th>Email Institusi</th>
                <th>Nomor HP</th>
                <th>Status</th>
                <th>Bidang / MK Diampu</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lecturersList as $l): ?>
                <tr>
                  <td><code><?= htmlspecialchars($l['nidn']) ?></code></td>
                  <td><strong><?= htmlspecialchars($l['name']) ?>, <?= htmlspecialchars($l['academic_degree']) ?></strong></td>
                  <td><?= htmlspecialchars($l['email']) ?></td>
                  <td><?= htmlspecialchars($l['phone']) ?></td>
                  <td><span class="badge badge-success"><?= htmlspecialchars($l['status']) ?></span></td>
                  <td><?= htmlspecialchars($l['courses_taught'] ?? '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 4: MATA KULIAH PRODI
           ======================================================== -->
      <div id="tab-matakuliah" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Katalog Mata Kuliah Jurusan</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Mata Kuliah Wajib & Pilihan Program Studi <?= htmlspecialchars($prodi['study_program_name']) ?></p>
          </div>
          <button class="btn btn-primary" onclick="openAddCourseModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Tambah Mata Kuliah
          </button>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Mata Kuliah</th>
                <th>SKS</th>
                <th>Semester</th>
                <th>Jenis</th>
                <th>Prasyarat</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($coursesList as $c): ?>
                <tr>
                  <td><code><?= htmlspecialchars($c['code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                  <td><?= htmlspecialchars($c['credits']) ?> SKS</td>
                  <td>Semester <?= htmlspecialchars($c['semester']) ?></td>
                  <td><span class="badge badge-outline-white" style="color:var(--primary); background:var(--bg-subtle);"><?= htmlspecialchars($c['course_type'] ?? 'Wajib') ?></span></td>
                  <td><?= htmlspecialchars($c['prerequisites'] ?? '-') ?></td>
                  <td><span class="badge badge-success">Aktif</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 5: KELAS KULIAH & JADWAL
           ======================================================== -->
      <div id="tab-kelas" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Pengelolaan Kelas & Kapasitas</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Kapasitas mahasiswa per kelas kuliah aktif</p>
          </div>
          <button class="btn btn-primary" onclick="openAddScheduleModal()">
            + Buka Kelas Baru
          </button>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Mata Kuliah</th>
                <th>Kode Kelas</th>
                <th>Dosen Pengampu</th>
                <th>Kapasitas</th>
                <th>Terisi</th>
                <th>Status Kuota</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($schedulesList as $sch): 
                $isFull = (int)$sch['enrolled'] >= (int)$sch['quota'];
              ?>
                <tr>
                  <td><strong><?= htmlspecialchars($sch['course_name']) ?></strong> (<?= htmlspecialchars($sch['course_code']) ?>)</td>
                  <td>Kelas <?= htmlspecialchars($sch['class_name']) ?></td>
                  <td><?= htmlspecialchars($sch['lecturer_name']) ?></td>
                  <td><?= htmlspecialchars($sch['quota']) ?> Mahasiswa</td>
                  <td><strong><?= htmlspecialchars($sch['enrolled']) ?></strong></td>
                  <td><span class="badge <?= $isFull ? 'badge-danger' : 'badge-success' ?>"><?= $isFull ? 'Penuh' : 'Tersedia' ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB JADWAL KULIAH WITH BENTROK WARNING -->
      <div id="tab-jadwal" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Penjadwalan Kuliah</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Jadwal Hari, Jam, Ruangan, serta Pemeriksaan Bentrok Otomatis</p>
          </div>
          <button class="btn btn-primary" onclick="openAddScheduleModal()">
            + Atur Jadwal Baru
          </button>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Hari</th>
                <th>Jam</th>
                <th>Mata Kuliah</th>
                <th>Kelas</th>
                <th>Ruangan</th>
                <th>Dosen Pengampu</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($schedulesList as $sch): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($sch['day']) ?></strong></td>
                  <td><?= date('H:i', strtotime($sch['start_time'])) ?> - <?= date('H:i', strtotime($sch['end_time'])) ?> WIB</td>
                  <td><strong><?= htmlspecialchars($sch['course_name']) ?></strong></td>
                  <td>Kelas <?= htmlspecialchars($sch['class_name']) ?></td>
                  <td><span class="badge badge-info"><?= htmlspecialchars($sch['room']) ?></span></td>
                  <td><?= htmlspecialchars($sch['lecturer_name']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 6: VERIFIKASI KRS MAHASISWA
           ======================================================== -->
      <div id="tab-krs" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Verifikasi & Persetujuan KRS Mahasiswa</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Persetujuan Kartu Rencana Studi Semester Ganjil 2024/2025</p>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Mahasiswa</th>
                <th>NIM</th>
                <th>Semester</th>
                <th>Total SKS</th>
                <th>Status KRS</th>
                <th>Catatan Status</th>
                <th style="text-align:center;">Tindakan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($krsList as $k): 
                $kBadge = 'badge-info';
                if ($k['status'] === 'Disetujui') $kBadge = 'badge-success';
                elseif ($k['status'] === 'Diajukan') $kBadge = 'badge-warning';
                elseif ($k['status'] === 'Ditolak') $kBadge = 'badge-danger';
              ?>
                <tr>
                  <td><strong><?= htmlspecialchars($k['student_name']) ?></strong></td>
                  <td><code><?= htmlspecialchars($k['nim']) ?></code></td>
                  <td>Semester <?= htmlspecialchars($k['student_sem']) ?></td>
                  <td><strong><?= htmlspecialchars($k['total_credits']) ?> SKS</strong> / <?= htmlspecialchars($k['max_credits']) ?></td>
                  <td><span class="badge <?= $kBadge ?>"><?= htmlspecialchars($k['status']) ?></span></td>
                  <td style="font-size:0.8rem; color:var(--text-muted); max-width:260px;"><?= htmlspecialchars($k['note'] ?? '-') ?></td>
                  <td style="text-align:center;">
                    <div style="display:inline-flex; gap:6px;">
                      <?php if ($k['status'] === 'Diajukan' || $k['status'] === 'Draft'): ?>
                        <button class="btn btn-sm btn-primary" onclick="approveKrs(<?= $k['id'] ?>, '<?= htmlspecialchars($k['student_name']) ?>')">
                          Setujui
                        </button>
                        <button class="btn btn-sm btn-outline" style="color:var(--danger); border-color:var(--danger-border);" onclick="openRejectKrsModal(<?= $k['id'] ?>, '<?= htmlspecialchars($k['student_name']) ?>')">
                          Tolak
                        </button>
                      <?php else: ?>
                        <button class="btn btn-sm btn-outline" onclick="openRejectKrsModal(<?= $k['id'] ?>, '<?= htmlspecialchars($k['student_name']) ?>')">
                          Revisi
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
           TAB 7: KHS / NILAI
           ======================================================== -->
      <div id="tab-nilai" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Monitoring & Validasi Nilai</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Pemantauan capaian nilai mahasiswa dan pengesahan hasil studi</p>
        </div>

        <div class="card" style="margin-bottom:20px;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
              <h3 class="card-title" style="margin:0;">Status Pengesahan Nilai Semester</h3>
              <p style="font-size:0.8rem; color:var(--text-muted); margin:0;">Tahun Akademik 2024/2025 Ganjil</p>
            </div>
            <button class="btn btn-primary" onclick="alert('Seluruh berkas nilai telah divalidasi dan disahkan oleh Program Studi.')">
              Validasi & Kunci Periode Nilai
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Mata Kuliah</th>
                <th>Dosen Pengampu</th>
                <th>Kelas</th>
                <th>Rata-rata Nilai</th>
                <th>Kelengkapan Nilai</th>
                <th>Status Validasi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($schedulesList as $sch): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($sch['course_name']) ?></strong> (<?= htmlspecialchars($sch['course_code']) ?>)</td>
                  <td><?= htmlspecialchars($sch['lecturer_name']) ?></td>
                  <td>Kelas <?= htmlspecialchars($sch['class_name']) ?></td>
                  <td><strong>3.75 (A-)</strong></td>
                  <td><span class="badge badge-success">100% Terisi</span></td>
                  <td><span class="badge badge-info">Tervalidasi Prodi</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 8: KALENDER AKADEMIK & PENGUMUMAN
           ======================================================== -->
      <div id="tab-kalender" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Kalender Akademik Universitas</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Agenda resmi Universitas Nusantara Mandiri TA 2024/2025</p>
        </div>
        <div class="card">
          <p style="color:var(--text-muted); line-height:1.8;">
            - <strong>01 - 10 September 2024:</strong> Masa Pengisian KRS Online & Bimbingan DPA.<br>
            - <strong>11 - 18 September 2024:</strong> Batas Akhir Revisi / Perubahan KRS.<br>
            - <strong>21 Oktober - 01 November 2024:</strong> Ujian Tengah Semester (UTS) Ganjil.<br>
            - <strong>06 - 17 Januari 2025:</strong> Ujian Akhir Semester (UAS) Ganjil.<br>
            - <strong>20 - 31 Januari 2025:</strong> Penginputan & Penguncian Nilai KHS oleh Dosen.
          </p>
        </div>
      </div>

      <div id="tab-pengumuman" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Pengumuman Program Studi</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Kirimkan pengumuman khusus kepada seluruh mahasiswa <?= htmlspecialchars($prodi['study_program_name']) ?></p>
          </div>
          <button class="btn btn-primary" onclick="openAddAnnouncementModal()">
            + Buat Pengumuman Baru
          </button>
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
          <?php foreach ($announcementsList as $ann): ?>
            <div class="card">
              <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span class="badge badge-info"><?= htmlspecialchars($ann['category']) ?></span>
                <span style="font-size:0.8rem; color:var(--text-light);"><?= date('d F Y', strtotime($ann['published_at'])) ?></span>
              </div>
              <h3 style="font-size:1.1rem; font-weight:700; color:var(--primary); margin-bottom:6px;"><?= htmlspecialchars($ann['title']) ?></h3>
              <p style="font-size:0.88rem; color:var(--text-muted); line-height:1.6;"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ========================================================
           TAB 9: LAYANAN AKADEMIK PRODI
           ======================================================== -->
      <div id="tab-layanan" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Pemrosesan Layanan Akademik Mahasiswa</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Verifikasi pengajuan surat keterangan aktif, cuti akademik, dan legalisir</p>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>No. Tiket</th>
                <th>Mahasiswa</th>
                <th>Jenis Layanan</th>
                <th>Tujuan Pengajuan</th>
                <th>Status</th>
                <th>Tindakan Pemrosesan</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($serviceRequestsList as $sr): 
                $srBadge = 'badge-info';
                if ($sr['status'] === 'Selesai' || $sr['status'] === 'Disetujui') $srBadge = 'badge-success';
                elseif ($sr['status'] === 'Diproses') $srBadge = 'badge-warning';
                elseif ($sr['status'] === 'Ditolak') $srBadge = 'badge-danger';
              ?>
                <tr>
                  <td><code>#SRV-<?= sprintf('%04d', $sr['id']) ?></code></td>
                  <td><strong><?= htmlspecialchars($sr['student_name']) ?></strong> (<?= htmlspecialchars($sr['nim']) ?>)</td>
                  <td><?= htmlspecialchars($sr['service_type']) ?></td>
                  <td style="max-width:260px;"><?= htmlspecialchars($sr['purpose']) ?></td>
                  <td><span class="badge <?= $srBadge ?>"><?= htmlspecialchars($sr['status']) ?></span></td>
                  <td>
                    <button class="btn btn-sm btn-primary" onclick="openProcessServiceModal(<?= $sr['id'] ?>, '<?= htmlspecialchars($sr['service_type']) ?>', '<?= htmlspecialchars($sr['status']) ?>')">
                      Proses Berkas
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 10: LAPORAN PROGRAM STUDI
           ======================================================== -->
      <div id="tab-laporan" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Laporan Akademik Program Studi</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Rekapitulasi resmi data mahasiswa, IPK, kelulusan, dan beban mengajar</p>
          </div>
          <div style="display:flex; gap:10px;">
            <button class="btn btn-outline" onclick="window.print()">
              Cetak Laporan
            </button>
            <button class="btn btn-primary" onclick="alert('Mengunduh Laporan Akademik Program Studi (Format PDF/Excel)...')">
              Export Dokumen
            </button>
          </div>
        </div>

        <div class="card" style="margin-bottom:20px;">
          <h3 class="card-title">Ringkasan Eksekutif Program Studi</h3>
          <table style="width:100%; font-size:0.9rem; line-height:2.2; margin-top:10px;">
            <tr><td style="width:240px; color:var(--text-muted);">Total Mahasiswa Terdaftar:</td><td style="font-weight:700;"><?= $totalStudents ?> Mahasiswa</td></tr>
            <tr><td style="color:var(--text-muted);">Mahasiswa Aktif Mengikuti Perkuliahan:</td><td style="font-weight:700; color:var(--success);"><?= $activeStudents ?> Mahasiswa (<?= $totalStudents > 0 ? round(($activeStudents/$totalStudents)*100, 1) : 0 ?>%)</td></tr>
            <tr><td style="color:var(--text-muted);">Mahasiswa Mengambil Cuti Akademik:</td><td style="font-weight:700; color:var(--warning);"><?= $leaveStudents ?> Mahasiswa</td></tr>
            <tr><td style="color:var(--text-muted);">Rata-rata Indeks Prestasi Kumulatif (IPK):</td><td style="font-weight:800; color:var(--brand-blue);"><?= number_format($avgGpa, 2) ?></td></tr>
            <tr><td style="color:var(--text-muted);">Total Dosen Homebase:</td><td style="font-weight:700;"><?= $totalLecturers ?> Dosen Tetap</td></tr>
            <tr><td style="color:var(--text-muted);">Rasio Dosen : Mahasiswa:</td><td style="font-weight:700;">1 : <?= $totalLecturers > 0 ? round($totalStudents / $totalLecturers) : 0 ?> (Ideal Sesuai Standar BAN-PT)</td></tr>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 11: PROFIL PROGRAM STUDI
           ======================================================== -->
      <div id="tab-profilprodi" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Identitas & Profil Program Studi</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Data Legalitas Program Studi di Bawah Naungan Universitas</p>
        </div>

        <div class="card">
          <table style="width:100%; font-size:0.9rem; line-height:2.4;">
            <tr><td style="width:220px; color:var(--text-muted);">Nama Program Studi:</td><td style="font-weight:800; color:var(--primary);"><?= htmlspecialchars($prodi['study_program_name']) ?></td></tr>
            <tr><td style="color:var(--text-muted);">Kode Program Studi:</td><td><code><?= htmlspecialchars($prodi['study_program_code']) ?></code></td></tr>
            <tr><td style="color:var(--text-muted);">Jenjang Pendidikan:</td><td><strong><?= htmlspecialchars($prodi['degree']) ?> (Sarjana)</strong></td></tr>
            <tr><td style="color:var(--text-muted);">Fakultas Naungan:</td><td><?= htmlspecialchars($prodi['faculty_name']) ?></td></tr>
            <tr><td style="color:var(--text-muted);">Ketua Program Studi:</td><td><strong><?= htmlspecialchars($prodi['head_of_program']) ?></strong></td></tr>
            <tr><td style="color:var(--text-muted);">Peringkat Akreditasi:</td><td><span class="badge badge-success">Unggul (A) BAN-PT</span></td></tr>
            <tr><td style="color:var(--text-muted);">Gelar Kelulusan:</td><td><strong>S.Kom. (Sarjana Komputer)</strong></td></tr>
          </table>
          <div style="margin-top:20px; padding:16px; background:var(--bg-subtle); border-radius:var(--radius-sm); font-size:0.82rem; color:var(--text-muted);">
            Catatan: Perubahan struktur program studi, dekan, dan kurikulum pusat diatur melalui kewenangan <strong>Administrator Universitas</strong>.
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- ========================================================
     MODALS FOR PRODI ACTIONS
     ======================================================== -->

<!-- 1. Modal Tolak KRS dengan Alasan -->
<div class="modal-overlay" id="rejectKrsModal">
  <div class="modal-container" style="max-width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">Tolak Pengajuan KRS</h3>
      <button class="modal-close" onclick="closeModal('rejectKrsModal')">✕</button>
    </div>
    <form id="rejectKrsForm" onsubmit="handleRejectKrs(event)">
      <div class="modal-body">
        <input type="hidden" id="rejectKrsId">
        <p style="font-size:0.9rem; margin-bottom:12px;" id="rejectKrsTargetText">Alasan penolakan KRS untuk mahasiswa:</p>
        <div class="form-group">
          <label class="form-label">Catatan Penolakan / Arahan Revisi</label>
          <textarea id="rejectKrsReason" class="form-control" rows="3" placeholder="Contoh: SKS melebihi kapasitas nilai IPS lalu, mohon kurangi 1 mata kuliah pilihan." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('rejectKrsModal')">Batal</button>
        <button type="submit" class="btn btn-primary" style="background:var(--danger); border-color:var(--danger);">Tolak & Kirim Catatan</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. Modal Tambah Mahasiswa Baru -->
<div class="modal-overlay" id="addStudentModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Mahasiswa Baru (<?= htmlspecialchars($prodi['study_program_code']) ?>)</h3>
      <button class="modal-close" onclick="closeModal('addStudentModal')">✕</button>
    </div>
    <form onsubmit="handleAddStudent(event)">
      <div class="modal-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
          <div class="form-group">
            <label class="form-label">NIM (Nomor Induk Mahasiswa)</label>
            <input type="text" id="newStdNim" class="form-control" placeholder="202401010" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" id="newStdName" class="form-control" placeholder="Nama mahasiswa" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Institusi</label>
            <input type="email" id="newStdEmail" class="form-control" placeholder="nama@univ-nusantara.ac.id" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nomor Handphone / WA</label>
            <input type="tel" id="newStdPhone" class="form-control" placeholder="081234567890" required>
          </div>
          <div class="form-group">
            <label class="form-label">Semester Masuk</label>
            <input type="number" id="newStdSemester" class="form-control" value="1" min="1" max="14" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tahun Angkatan</label>
            <input type="number" id="newStdEntryYear" class="form-control" value="<?= date('Y') ?>" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addStudentModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Mahasiswa</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. Modal Ubah Status Mahasiswa -->
<div class="modal-overlay" id="updateStatusModal">
  <div class="modal-container" style="max-width:440px;">
    <div class="modal-header">
      <h3 class="modal-title">Ubah Status Akademik</h3>
      <button class="modal-close" onclick="closeModal('updateStatusModal')">✕</button>
    </div>
    <form onsubmit="handleUpdateStatus(event)">
      <div class="modal-body">
        <input type="hidden" id="statusStudentId">
        <p style="margin-bottom:12px;" id="statusStudentName">Pilih status akademik:</p>
        <div class="form-group">
          <select id="selectNewStatus" class="form-control">
            <option value="Aktif">Aktif</option>
            <option value="Cuti">Cuti</option>
            <option value="Lulus">Lulus</option>
            <option value="Non-Aktif">Non-Aktif</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('updateStatusModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Perbarui Status</button>
      </div>
    </form>
  </div>
</div>

<!-- 4. Modal Tambah Dosen -->
<div class="modal-overlay" id="addLecturerModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Dosen Homebase</h3>
      <button class="modal-close" onclick="closeModal('addLecturerModal')">✕</button>
    </div>
    <form onsubmit="handleAddLecturer(event)">
      <div class="modal-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
          <div class="form-group">
            <label class="form-label">NIDN / NIP</label>
            <input type="text" id="newLecNidn" class="form-control" required placeholder="0412058801">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Dosen (Tanpa Gelar)</label>
            <input type="text" id="newLecName" class="form-control" required placeholder="Nama dosen">
          </div>
          <div class="form-group">
            <label class="form-label">Gelar Akademik</label>
            <input type="text" id="newLecDegree" class="form-control" required placeholder="S.Kom., M.Cs.">
          </div>
          <div class="form-group">
            <label class="form-label">Email Institusi</label>
            <input type="email" id="newLecEmail" class="form-control" required placeholder="dosen@univ-nusantara.ac.id">
          </div>
          <div class="form-group" style="grid-column: span 2;">
            <label class="form-label">Mata Kuliah / Bidang Keahlian yang Diampu</label>
            <input type="text" id="newLecCourses" class="form-control" placeholder="Contoh: Pemrograman Web, Kecerdasan Buatan">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addLecturerModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Dosen</button>
      </div>
    </form>
  </div>
</div>

<!-- 5. Modal Tambah Mata Kuliah -->
<div class="modal-overlay" id="addCourseModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="modal-title">Tambah Mata Kuliah Baru</h3>
      <button class="modal-close" onclick="closeModal('addCourseModal')">✕</button>
    </div>
    <form onsubmit="handleAddCourse(event)">
      <div class="modal-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
          <div class="form-group">
            <label class="form-label">Kode Mata Kuliah</label>
            <input type="text" id="newCrsCode" class="form-control" required placeholder="TI3201">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Mata Kuliah</label>
            <input type="text" id="newCrsName" class="form-control" required placeholder="Nama MK">
          </div>
          <div class="form-group">
            <label class="form-label">Bobot SKS</label>
            <input type="number" id="newCrsCredits" class="form-control" value="3" min="1" max="6" required>
          </div>
          <div class="form-group">
            <label class="form-label">Semester Penawaran</label>
            <input type="number" id="newCrsSemester" class="form-control" value="5" min="1" max="8" required>
          </div>
          <div class="form-group">
            <label class="form-label">Jenis Mata Kuliah</label>
            <select id="newCrsType" class="form-control">
              <option value="Wajib">Wajib Program Studi</option>
              <option value="Pilihan">Pilihan Peminatan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Prasyarat</label>
            <input type="text" id="newCrsPrereq" class="form-control" placeholder="Kode MK prasyarat atau -">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addCourseModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Mata Kuliah</button>
      </div>
    </form>
  </div>
</div>

<!-- 6. Modal Tambah Jadwal & Deteksi Bentrok -->
<div class="modal-overlay" id="addScheduleModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="modal-title">Atur Jadwal Kuliah & Deteksi Bentrok</h3>
      <button class="modal-close" onclick="closeModal('addScheduleModal')">✕</button>
    </div>
    <form onsubmit="handleAddSchedule(event)">
      <div class="modal-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
          <div class="form-group" style="grid-column: span 2;">
            <label class="form-label">Pilih Mata Kuliah</label>
            <select id="schCourseId" class="form-control" required>
              <option value="">-- Pilih Mata Kuliah --</option>
              <?php foreach ($coursesList as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['code']) ?> - <?= htmlspecialchars($c['name']) ?> (<?= $c['credits'] ?> SKS)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Dosen Pengampu</label>
            <select id="schLecturerName" class="form-control" required>
              <option value="">-- Pilih Dosen --</option>
              <?php foreach ($lecturersList as $l): ?>
                <option value="<?= htmlspecialchars($l['name']) ?>, <?= htmlspecialchars($l['academic_degree']) ?>">
                  <?= htmlspecialchars($l['name']) ?>, <?= htmlspecialchars($l['academic_degree']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Kode Kelas</label>
            <input type="text" id="schClassName" class="form-control" value="A" required>
          </div>
          <div class="form-group">
            <label class="form-label">Hari Kuliah</label>
            <select id="schDay" class="form-control" required>
              <option value="Senin">Senin</option>
              <option value="Selasa">Selasa</option>
              <option value="Rabu">Rabu</option>
              <option value="Kamis">Kamis</option>
              <option value="Jumat">Jumat</option>
              <option value="Sabtu">Sabtu</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Ruangan</label>
            <input type="text" id="schRoom" class="form-control" value="Lab Software R.302" required>
          </div>
          <div class="form-group">
            <label class="form-label">Jam Mulai</label>
            <input type="time" id="schStartTime" class="form-control" value="08:00" required>
          </div>
          <div class="form-group">
            <label class="form-label">Jam Selesai</label>
            <input type="time" id="schEndTime" class="form-control" value="10:30" required>
          </div>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted); background:var(--bg-subtle); padding:10px; border-radius:var(--radius-sm); margin-top:10px;">
          Sistem otomatis memeriksa irisan jam ruangan dan ketersediaan dosen pengampu secara real-time.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addScheduleModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
      </div>
    </form>
  </div>
</div>

<!-- 7. Modal Proses Layanan Akademik -->
<div class="modal-overlay" id="processServiceModal">
  <div class="modal-container" style="max-width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">Proses Pengajuan Layanan</h3>
      <button class="modal-close" onclick="closeModal('processServiceModal')">✕</button>
    </div>
    <form onsubmit="handleProcessService(event)">
      <div class="modal-body">
        <input type="hidden" id="procServiceId">
        <p style="margin-bottom:12px;" id="procServiceTitle">Layanan:</p>
        <div class="form-group">
          <label class="form-label">Status Baru</label>
          <select id="procServiceStatus" class="form-control">
            <option value="Diproses">Diproses</option>
            <option value="Disetujui">Disetujui</option>
            <option value="Selesai">Selesai</option>
            <option value="Ditolak">Ditolak</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Catatan BAAK / Prodi untuk Mahasiswa</label>
          <textarea id="procServiceNote" class="form-control" rows="3" placeholder="Contoh: Dokumen telah ditandatangani dan siap diambil di sekretariat prodi." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('processServiceModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Status</button>
      </div>
    </form>
  </div>
</div>

<!-- 8. Modal Detail Mahasiswa Tabbed -->
<div class="modal-overlay" id="studentDetailModal">
  <div class="modal-container" style="max-width:700px;">
    <div class="modal-header">
      <h3 class="modal-title" id="stdModalTitle">Riwayat Akademik Mahasiswa</h3>
      <button class="modal-close" onclick="closeModal('studentDetailModal')">✕</button>
    </div>
    <div class="modal-body" id="stdModalBody">
      <div style="text-align:center; padding:30px;">Memuat data akademik mahasiswa...</div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary" onclick="closeModal('studentDetailModal')">Tutup</button>
    </div>
  </div>
</div>

<!-- 9. Modal Buat Pengumuman Prodi -->
<div class="modal-overlay" id="addAnnouncementModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="modal-title">Buat Pengumuman Jurusan</h3>
      <button class="modal-close" onclick="closeModal('addAnnouncementModal')">✕</button>
    </div>
    <form onsubmit="handleAddAnnouncement(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Judul Pengumuman</label>
          <input type="text" id="annTitle" class="form-control" required placeholder="Judul informasi...">
        </div>
        <div class="form-group">
          <label class="form-label">Kategori</label>
          <select id="annCategory" class="form-control">
            <option value="Program Studi">Program Studi</option>
            <option value="Akademik">Akademik</option>
            <option value="Beasiswa">Beasiswa</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Isi Pengumuman</label>
          <textarea id="annContent" class="form-control" rows="4" required placeholder="Tuliskan isi pengumuman lengkap..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addAnnouncementModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Publikasikan</button>
      </div>
    </form>
  </div>
</div>

<script>
// Switch Tabs
function switchProdiTab(tabId, el) {
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

function openModal(id) {
  document.getElementById(id).classList.add('active');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('active');
}

// 1. Approve KRS
function approveKrs(krsId, stdName) {
  if (!confirm('Apakah Anda yakin ingin menyetujui KRS mahasiswa ' + stdName + '?')) return;

  const fd = new FormData();
  fd.append('action', 'approve_krs');
  fd.append('krs_id', krsId);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      alert(data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => alert('Gagal memproses persetujuan KRS.'));
}

// 2. Reject KRS
function openRejectKrsModal(krsId, stdName) {
  document.getElementById('rejectKrsId').value = krsId;
  document.getElementById('rejectKrsTargetText').innerText = 'Alasan penolakan KRS untuk mahasiswa: ' + stdName;
  openModal('rejectKrsModal');
}

function handleRejectKrs(e) {
  e.preventDefault();
  const krsId = document.getElementById('rejectKrsId').value;
  const reason = document.getElementById('rejectKrsReason').value;

  const fd = new FormData();
  fd.append('action', 'reject_krs');
  fd.append('krs_id', krsId);
  fd.append('reason', reason);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('rejectKrsModal');
      alert(data.message);
      if (data.success) window.location.reload();
    })
    .catch(err => alert('Gagal menolak KRS.'));
}

// 3. Tambah Mahasiswa
function openAddStudentModal() {
  openModal('addStudentModal');
}

function handleAddStudent(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_student');
  fd.append('nim', document.getElementById('newStdNim').value);
  fd.append('name', document.getElementById('newStdName').value);
  fd.append('email', document.getElementById('newStdEmail').value);
  fd.append('phone', document.getElementById('newStdPhone').value);
  fd.append('semester', document.getElementById('newStdSemester').value);
  fd.append('entry_year', document.getElementById('newStdEntryYear').value);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addStudentModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 4. Ubah Status Mahasiswa
function openUpdateStatusModal(id, name, status) {
  document.getElementById('statusStudentId').value = id;
  document.getElementById('statusStudentName').innerText = 'Ubah status untuk: ' + name;
  document.getElementById('selectNewStatus').value = status;
  openModal('updateStatusModal');
}

function handleUpdateStatus(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'update_student_status');
  fd.append('student_id', document.getElementById('statusStudentId').value);
  fd.append('status', document.getElementById('selectNewStatus').value);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('updateStatusModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 5. Tambah Dosen
function openAddLecturerModal() { openModal('addLecturerModal'); }
function handleAddLecturer(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_lecturer');
  fd.append('nidn', document.getElementById('newLecNidn').value);
  fd.append('name', document.getElementById('newLecName').value);
  fd.append('degree', document.getElementById('newLecDegree').value);
  fd.append('email', document.getElementById('newLecEmail').value);
  fd.append('courses_taught', document.getElementById('newLecCourses').value);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addLecturerModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 6. Tambah MK
function openAddCourseModal() { openModal('addCourseModal'); }
function handleAddCourse(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_course');
  fd.append('code', document.getElementById('newCrsCode').value);
  fd.append('name', document.getElementById('newCrsName').value);
  fd.append('credits', document.getElementById('newCrsCredits').value);
  fd.append('semester', document.getElementById('newCrsSemester').value);
  fd.append('course_type', document.getElementById('newCrsType').value);
  fd.append('prerequisites', document.getElementById('newCrsPrereq').value);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addCourseModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 7. Tambah Jadwal
function openAddScheduleModal() { openModal('addScheduleModal'); }
function handleAddSchedule(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'add_schedule');
  fd.append('course_id', document.getElementById('schCourseId').value);
  fd.append('lecturer_name', document.getElementById('schLecturerName').value);
  fd.append('class_name', document.getElementById('schClassName').value);
  fd.append('day', document.getElementById('schDay').value);
  fd.append('room', document.getElementById('schRoom').value);
  fd.append('start_time', document.getElementById('schStartTime').value);
  fd.append('end_time', document.getElementById('schEndTime').value);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        closeModal('addScheduleModal');
        alert(data.message);
        window.location.reload();
      } else {
        alert(data.message);
      }
    });
}

// 8. Proses Layanan
function openProcessServiceModal(id, type, status) {
  document.getElementById('procServiceId').value = id;
  document.getElementById('procServiceTitle').innerText = 'Layanan: ' + type;
  document.getElementById('procServiceStatus').value = status;
  openModal('processServiceModal');
}

function handleProcessService(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'process_service');
  fd.append('request_id', document.getElementById('procServiceId').value);
  fd.append('status', document.getElementById('procServiceStatus').value);
  fd.append('admin_note', document.getElementById('procServiceNote').value);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('processServiceModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 9. Buat Pengumuman
function openAddAnnouncementModal() { openModal('addAnnouncementModal'); }
function handleAddAnnouncement(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'create_announcement');
  fd.append('title', document.getElementById('annTitle').value);
  fd.append('content', document.getElementById('annContent').value);
  fd.append('category', document.getElementById('annCategory').value);

  fetch('prodi_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeModal('addAnnouncementModal');
      alert(data.message);
      if (data.success) window.location.reload();
    });
}

// 10. Detail Mahasiswa Modal
function showStudentDetailModal(id) {
  openModal('studentDetailModal');
  const body = document.getElementById('stdModalBody');
  body.innerHTML = 'Memuat data mahasiswa...';

  fetch('prodi_api.php?action=get_student_detail&student_id=' + id)
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const s = res.student;
        const k = res.krs;
        let coursesHtml = '';
        if (res.courses && res.courses.length > 0) {
          coursesHtml = '<ul style="margin-left:20px; font-size:0.85rem;">' + 
            res.courses.map(c => '<li>' + c.code + ' - ' + c.name + ' (' + c.credits + ' SKS) [Kelas ' + c.class_name + ']</li>').join('') + 
            '</ul>';
        } else {
          coursesHtml = '<p style="color:var(--text-muted); font-size:0.82rem;">Belum ada mata kuliah yang disetujui pada KRS semester ini.</p>';
        }

        body.innerHTML = `
          <div style="display:flex; gap:16px; align-items:center; margin-bottom:16px;">
            <img src="${s.avatar}" style="width:64px; height:64px; border-radius:50%; border:2px solid var(--border-subtle);">
            <div>
              <h3 style="margin:0; font-size:1.15rem; color:var(--primary);">${s.name}</h3>
              <div style="font-size:0.85rem; color:var(--text-muted);">NIM: <strong>${s.nim}</strong> • Angkatan ${s.entry_year} • Semester ${s.semester}</div>
              <span class="badge badge-success" style="margin-top:4px;">Status: ${s.status}</span>
            </div>
          </div>
          <table style="width:100%; font-size:0.85rem; line-height:2; margin-bottom:14px;">
            <tr><td style="width:140px; color:var(--text-muted);">Dosen Wali:</td><td><strong>${s.advisor_name}</strong></td></tr>
            <tr><td style="color:var(--text-muted);">IPK Kumulatif:</td><td><strong style="color:var(--brand-blue);">${s.gpa_cumulative}</strong></td></tr>
            <tr><td style="color:var(--text-muted);">Total SKS Lulus:</td><td>${s.total_credits} SKS</td></tr>
            <tr><td style="color:var(--text-muted);">Kontak / Email:</td><td>${s.phone} | ${s.email}</td></tr>
            <tr><td style="color:var(--text-muted);">Status KRS:</td><td><span class="badge ${k && k.status === 'Disetujui' ? 'badge-success' : 'badge-warning'}">${k ? k.status : 'Belum Mengajukan'}</span></td></tr>
          </table>
          <h4 style="font-size:0.95rem; font-weight:700; margin-bottom:6px;">Mata Kuliah Terdaftar di KRS:</h4>
          ${coursesHtml}
        `;
      } else {
        body.innerHTML = '<p style="color:var(--danger);">' + res.message + '</p>';
      }
    });
}

// 11. Client-side Table Filter for Mahasiswa
function filterStudentsTable() {
  const query = document.getElementById('searchStudentInput').value.toLowerCase();
  const sem = document.getElementById('filterSemSelect').value;
  const status = document.getElementById('filterStatusSelect').value;

  const rows = document.querySelectorAll('.student-row');
  rows.forEach(r => {
    const name = r.getAttribute('data-name');
    const nim = r.getAttribute('data-nim');
    const rSem = r.getAttribute('data-sem');
    const rStatus = r.getAttribute('data-status');

    let matchesSearch = !query || name.includes(query) || nim.includes(query);
    let matchesSem = !sem || rSem === sem;
    let matchesStatus = !status || rStatus === status;

    if (matchesSearch && matchesSem && matchesStatus) {
      r.style.display = '';
    } else {
      r.style.display = 'none';
    }
  });
}
</script>

</body>
</html>
