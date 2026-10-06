<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Cek autentikasi role Mahasiswa
checkAuthStudent();

$pdo = getDBConnection();
$student = getLoggedInStudent();

if (!$student) {
    session_destroy();
    header("Location: login.php?msg=auth_required");
    exit;
}

$studentId = (int)$student['id'];

// 1. Ambil Notifikasi
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 10");
$notifStmt->execute([$studentId]);
$notifications = $notifStmt->fetchAll();
$unreadNotifCount = 0;
foreach ($notifications as $n) {
    if ((int)$n['is_read'] === 0) $unreadNotifCount++;
}

// 2. Ambil Data KRS Aktif (Tahun Akademik 3 = 2024/2025 Ganjil)
$krsStmt = $pdo->prepare("SELECT * FROM krs WHERE student_id = ? AND academic_year_id = 3 LIMIT 1");
$krsStmt->execute([$studentId]);
$activeKrs = $krsStmt->fetch();

// Ambil list ID course_schedule yang sudah dipilih dalam KRS
$selectedScheduleIds = [];
if ($activeKrs) {
    $detailStmt = $pdo->prepare("SELECT course_schedule_id FROM krs_details WHERE krs_id = ?");
    $detailStmt->execute([$activeKrs['id']]);
    $selectedScheduleIds = $detailStmt->fetchAll(PDO::FETCH_COLUMN);
}

// 3. Ambil Semua Jadwal Kuliah Semester Ini
$schedulesStmt = $pdo->prepare("
    SELECT cs.*, c.code AS course_code, c.name AS course_name, c.credits, c.semester AS course_sem
    FROM course_schedules cs
    JOIN courses c ON cs.course_id = c.id
    WHERE cs.academic_year_id = 3
    ORDER BY FIELD(cs.day, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), cs.start_time
");
$schedulesStmt->execute();
$allSchedules = $schedulesStmt->fetchAll();

// Filter jadwal hari ini
$indonesianDays = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$todayIndo = $indonesianDays[date('l')] ?? 'Senin';
// Untuk demo showcase yang kaya, jika hari ini weekend, tampilkan jadwal hari Senin
$displayDay = in_array($todayIndo, ['Minggu', 'Sabtu']) ? 'Senin' : $todayIndo;

$todaySchedules = [];
foreach ($allSchedules as $sch) {
    if (in_array($sch['id'], $selectedScheduleIds) && $sch['day'] === $displayDay) {
        $todaySchedules[] = $sch;
    }
}
// Fallback jika belum ada yang terpilih di hari ini, tampilkan jadwal hari ini dari jadwal umum
if (empty($todaySchedules)) {
    foreach ($allSchedules as $sch) {
        if ($sch['day'] === 'Senin') {
            $todaySchedules[] = $sch;
        }
    }
}

// 4. Ambil Pengumuman
$announcementsStmt = $pdo->query("SELECT * FROM announcements ORDER BY is_pinned DESC, published_at DESC");
$allAnnouncements = $announcementsStmt->fetchAll();

// 5. Ambil Riwayat Nilai (KHS & Transkrip)
$gradesStmt = $pdo->prepare("
    SELECT g.*, c.code AS course_code, c.name AS course_name, c.credits, ay.name AS academic_year_name
    FROM grades g
    JOIN courses c ON g.course_id = c.id
    JOIN academic_years ay ON g.academic_year_id = ay.id
    WHERE g.student_id = ?
    ORDER BY g.semester ASC, c.code ASC
");
$gradesStmt->execute([$studentId]);
$allGrades = $gradesStmt->fetchAll();

// 6. Ambil Data Tagihan Pembayaran
$paymentsStmt = $pdo->prepare("
    SELECT p.*, ay.name AS academic_year_name 
    FROM payments p
    JOIN academic_years ay ON p.academic_year_id = ay.id
    WHERE p.student_id = ?
    ORDER BY p.due_date DESC
");
$paymentsStmt->execute([$studentId]);
$allPayments = $paymentsStmt->fetchAll();

$totalTagihan = 0;
$totalTerbayar = 0;
foreach ($allPayments as $p) {
    $totalTagihan += (float)$p['amount'];
    if ($p['status'] === 'Lunas') {
        $totalTerbayar += (float)$p['amount'];
    }
}
$sisaTagihan = $totalTagihan - $totalTerbayar;

// 7. Ambil Dokumen Akademik
$docStmt = $pdo->prepare("SELECT * FROM academic_documents WHERE student_id = ? ORDER BY issue_date DESC");
$docStmt->execute([$studentId]);
$allDocuments = $docStmt->fetchAll();

// 8. Ambil Riwayat Pengajuan Layanan
$serviceStmt = $pdo->prepare("SELECT * FROM service_requests WHERE student_id = ? ORDER BY submitted_at DESC");
$serviceStmt->execute([$studentId]);
$allServices = $serviceStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Mahasiswa — SIAKAD Universitas Nusantara Mandiri</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="portal-layout">

  <!-- ========================================================
       SIDEBAR NAVIGASI
       ======================================================== -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="assets/img/logo-universitas.svg" alt="Logo">
      <div class="sidebar-brand-text">
        <h2>SIAKAD UNM</h2>
        <p>Portal Mahasiswa</p>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">Menu Utama</div>

      <div class="nav-item active" onclick="switchTab('dashboard', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"></rect>
          <rect x="14" y="3" width="7" height="7"></rect>
          <rect x="14" y="14" width="7" height="7"></rect>
          <rect x="3" y="14" width="7" height="7"></rect>
        </svg>
        <span>Dashboard</span>
      </div>

      <div class="nav-item" onclick="switchTab('profil', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span>Profil Saya</span>
      </div>

      <div class="nav-section-title">Akademik & Perkuliahan</div>

      <div class="nav-item" onclick="switchTab('krs', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        <span>Kartu Rencana Studi (KRS)</span>
      </div>

      <div class="nav-item" onclick="switchTab('khs', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
          <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
          <path d="M9 14l2 2 4-4"></path>
        </svg>
        <span>Kartu Hasil Studi (KHS)</span>
      </div>

      <div class="nav-item" onclick="switchTab('jadwal', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
        <span>Jadwal Kuliah</span>
      </div>

      <div class="nav-item" onclick="switchTab('transkrip', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
        </svg>
        <span>Transkrip Nilai</span>
      </div>

      <div class="nav-item" onclick="switchTab('kalender', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
        <span>Kalender Akademik</span>
      </div>

      <div class="nav-section-title">Administrasi & Layanan</div>

      <div class="nav-item" onclick="switchTab('pembayaran', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
          <line x1="1" y1="10" x2="23" y2="10"></line>
        </svg>
        <span>Pembayaran</span>
      </div>

      <div class="nav-item" onclick="switchTab('pengumuman', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span>Pengumuman</span>
      </div>

      <div class="nav-item" onclick="switchTab('dokumen', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
          <polyline points="13 2 13 9 20 9"></polyline>
        </svg>
        <span>Dokumen Akademik</span>
      </div>

      <div class="nav-item" onclick="switchTab('layanan', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
        </svg>
        <span>Layanan Akademik</span>
      </div>

      <div class="nav-item" onclick="switchTab('pengaturan', this)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
        </svg>
        <span>Pengaturan</span>
      </div>
    </nav>

    <div class="sidebar-footer">
      <a href="logout.php" class="logout-btn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        <span>Keluar (Logout)</span>
      </a>
    </div>
  </aside>

  <!-- ========================================================
       MAIN WRAPPER
       ======================================================== -->
  <main class="main-wrapper">

    <!-- Top Navbar -->
    <header class="top-navbar">
      <div class="nav-left">
        <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Menu">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>

        <div class="search-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" placeholder="Cari mata kuliah, jadwal, atau berkas...">
        </div>
      </div>

      <div class="nav-right">
        <!-- Notifikasi Bell -->
        <div class="notif-wrapper">
          <button class="notif-bell-btn" onclick="toggleNotifDropdown()" title="Notifikasi Akademik">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if ($unreadNotifCount > 0): ?>
              <span class="notif-badge" id="notifBadgeCount"><?= $unreadNotifCount ?></span>
            <?php endif; ?>
          </button>

          <!-- Dropdown Notifikasi -->
          <div class="notif-dropdown" id="notifDropdown">
            <div class="notif-header">
              <span>Pemberitahuan Sistem</span>
              <button onclick="markAllNotifRead()" style="background:none; border:none; color:var(--brand-blue); font-size:0.75rem; cursor:pointer; font-weight:600;">
                Tandai Sudah Dibaca
              </button>
            </div>
            <div class="notif-list">
              <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                  <div class="notif-item <?= (int)$notif['is_read'] === 0 ? 'unread' : '' ?>">
                    <div class="notif-item-title"><?= htmlspecialchars($notif['title']) ?></div>
                    <div><?= htmlspecialchars($notif['message']) ?></div>
                    <div class="notif-item-time"><?= date('d M Y, H:i', strtotime($notif['created_at'])) ?> WIB</div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:0.85rem;">Tidak ada pemberitahuan baru.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- User Profile Pill -->
        <div class="user-profile-pill" onclick="switchTab('profil')">
          <img src="<?= htmlspecialchars($student['avatar']) ?>" alt="Avatar" class="user-avatar-sm">
          <div class="user-info-text">
            <div class="user-name"><?= htmlspecialchars($student['name']) ?></div>
            <div class="user-nim"><?= htmlspecialchars($student['nim']) ?></div>
          </div>
        </div>
      </div>
    </header>

    <!-- Page Content Container -->
    <div class="page-container">

      <!-- ========================================================
           TAB 1: DASHBOARD UTAMA
           ======================================================== -->
      <div id="tab-dashboard" class="tab-content active">

        <!-- Student Banner -->
        <div class="student-banner">
          <div class="student-banner-left">
            <img src="<?= htmlspecialchars($student['avatar']) ?>" alt="Foto Mahasiswa" class="banner-avatar">
            <div class="banner-details">
              <h2><?= htmlspecialchars($student['name']) ?></h2>
              <div style="font-size:0.92rem; color:#cbd5e1; display:flex; gap:16px; flex-wrap:wrap; margin-bottom:6px;">
                <span><strong>NIM:</strong> <?= htmlspecialchars($student['nim']) ?></span>
                <span><strong>Prodi:</strong> <?= htmlspecialchars($student['study_program_name']) ?> (<?= htmlspecialchars($student['degree']) ?>)</span>
                <span><strong>Fakultas:</strong> <?= htmlspecialchars($student['faculty_name']) ?></span>
              </div>
              <div class="banner-badges">
                <span class="badge badge-outline-white">Semester <?= htmlspecialchars($student['semester']) ?></span>
                <span class="badge badge-outline-white">TA 2024/2025 Ganjil</span>
                <span class="badge badge-outline-white">Angkatan <?= htmlspecialchars($student['entry_year']) ?></span>
                <span class="banner-status-tag"><?= htmlspecialchars($student['status']) ?></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Academic KPI Cards -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <div>
              <div class="kpi-label">IP Semester Lalu</div>
              <div class="kpi-value"><?= number_format($student['gpa_last_sem'], 2) ?></div>
              <div style="font-size:0.75rem; color:var(--success); font-weight:600; margin-top:4px;">Predikat: Memuaskan</div>
            </div>
            <div class="kpi-icon" style="background:#ecfdf5; color:#059669;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6M6 20V10M18 20V4"></path></svg>
            </div>
          </div>

          <div class="kpi-card">
            <div>
              <div class="kpi-label">IPK Kumulatif</div>
              <div class="kpi-value"><?= number_format($student['gpa_cumulative'], 2) ?></div>
              <div style="font-size:0.75rem; color:var(--brand-blue); font-weight:600; margin-top:4px;">Skala 4.00</div>
            </div>
            <div class="kpi-icon" style="background:#eff6ff; color:#2563eb;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
            </div>
          </div>

          <div class="kpi-card">
            <div>
              <div class="kpi-label">Total SKS Ditempuh</div>
              <div class="kpi-value"><?= (int)$student['total_credits'] ?> <span style="font-size:1rem; font-weight:500; color:var(--text-muted);">SKS</span></div>
              <div style="font-size:0.75rem; color:var(--text-muted); font-weight:500; margin-top:4px;">Beban Lulus: 144 SKS</div>
            </div>
            <div class="kpi-icon" style="background:#fef3c7; color:#d97706;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            </div>
          </div>

          <div class="kpi-card">
            <div>
              <div class="kpi-label">Batas Beban SKS</div>
              <div class="kpi-value">24 <span style="font-size:1rem; font-weight:500; color:var(--text-muted);">SKS</span></div>
              <div style="font-size:0.75rem; color:var(--info); font-weight:600; margin-top:4px;">Maksimal SKS Reguler</div>
            </div>
            <div class="kpi-icon" style="background:#f0f9ff; color:#0284c7;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
          </div>
        </div>

        <!-- 8 Quick Action Menu Cards -->
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 14px;">Akses Cepat Layanan</h3>
        <div class="quick-menu-grid">
          <div class="quick-menu-item" onclick="switchTab('krs')">
            <div class="quick-menu-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            </div>
            <div class="quick-menu-label">1. KRS</div>
          </div>

          <div class="quick-menu-item" onclick="switchTab('khs')">
            <div class="quick-menu-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
            </div>
            <div class="quick-menu-label">2. KHS / Nilai</div>
          </div>

          <div class="quick-menu-item" onclick="switchTab('jadwal')">
            <div class="quick-menu-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <div class="quick-menu-label">3. Jadwal Kuliah</div>
          </div>

          <div class="quick-menu-item" onclick="switchTab('transkrip')">
            <div class="quick-menu-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
            </div>
            <div class="quick-menu-label">4. Transkrip Nilai</div>
          </div>

          <div class="quick-menu-item" onclick="switchTab('pembayaran')">
            <div class="quick-menu-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
            </div>
            <div class="quick-menu-label">5. Pembayaran</div>
          </div>

          <div class="quick-menu-item" onclick="switchTab('pengumuman')">
            <div class="quick-menu-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path></svg>
            </div>
            <div class="quick-menu-label">6. Pengumuman</div>
          </div>

          <div class="quick-menu-item" onclick="switchTab('profil')">
            <div class="quick-menu-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <div class="quick-menu-label">7. Profil</div>
          </div>

          <div class="quick-menu-item" onclick="switchTab('kalender')">
            <div class="quick-menu-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line></svg>
            </div>
            <div class="quick-menu-label">8. Kalender</div>
          </div>
        </div>

        <!-- 2 Column Section: Jadwal Hari Ini & Pengumuman Terbaru -->
        <div class="dash-two-col">

          <!-- Kolom Kiri: Jadwal Hari Ini -->
          <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
              <div>
                <h3 class="card-title" style="margin:0;">Jadwal Hari Ini (<?= htmlspecialchars($displayDay) ?>)</h3>
                <p style="font-size:0.8rem; color:var(--text-muted); margin:0;">Mata kuliah aktif semester berjalan</p>
              </div>
              <button class="btn btn-outline btn-sm" onclick="switchTab('jadwal')">Lihat Seluruhnya</button>
            </div>

            <?php if (!empty($todaySchedules)): ?>
              <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($todaySchedules as $sch): ?>
                  <div style="padding:14px; border:1px solid var(--border-subtle); border-radius:var(--radius-sm); border-left:4px solid var(--brand-blue); background:#ffffff;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                      <div>
                        <div style="font-weight:700; font-size:0.95rem; color:var(--primary);"><?= htmlspecialchars($sch['course_name']) ?></div>
                        <div style="font-size:0.78rem; color:var(--text-muted);">Kode: <code><?= htmlspecialchars($sch['course_code']) ?></code> • Kelas <?= htmlspecialchars($sch['class_name']) ?> • <?= htmlspecialchars($sch['credits']) ?> SKS</div>
                      </div>
                      <span class="badge badge-info" style="font-size:0.75rem;">
                        <?= date('H:i', strtotime($sch['start_time'])) ?> - <?= date('H:i', strtotime($sch['end_time'])) ?> WIB
                      </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.82rem; color:var(--text-main); margin-top:8px;">
                      <span>Ruangan: <strong><?= htmlspecialchars($sch['room']) ?></strong></span>
                      <span style="color:var(--text-muted);">Dosen: <?= htmlspecialchars($sch['lecturer_name']) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <h3>Tidak ada jadwal perkuliahan hari ini</h3>
                <p>Silakan periksa agenda studi mandiri atau kegiatan laboratorium Anda.</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Kolom Kanan: Pengumuman Terbaru -->
          <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
              <div>
                <h3 class="card-title" style="margin:0;">Pengumuman Terbaru</h3>
                <p style="font-size:0.8rem; color:var(--text-muted); margin:0;">Informasi resmi universitas</p>
              </div>
              <button class="btn btn-outline btn-sm" onclick="switchTab('pengumuman')">Semua</button>
            </div>

            <?php if (!empty($allAnnouncements)): ?>
              <div style="display:flex; flex-direction:column; gap:14px;">
                <?php 
                $topAnnouncements = array_slice($allAnnouncements, 0, 3);
                foreach ($topAnnouncements as $ann): 
                ?>
                  <div style="padding-bottom:12px; border-bottom:1px solid var(--border-subtle);">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                      <span class="badge badge-info" style="font-size:0.7rem;"><?= htmlspecialchars($ann['category']) ?></span>
                      <span style="font-size:0.74rem; color:var(--text-light);"><?= date('d M Y', strtotime($ann['published_at'])) ?></span>
                    </div>
                    <div style="font-weight:700; font-size:0.88rem; color:var(--primary); line-height:1.3; margin-bottom:4px;">
                      <?= htmlspecialchars($ann['title']) ?>
                    </div>
                    <div style="font-size:0.8rem; color:var(--text-muted); line-height:1.4;">
                      <?= htmlspecialchars(substr($ann['content'], 0, 90)) ?>...
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <h3>Belum ada pengumuman</h3>
                <p>Pengumuman akademik terbaru akan dirilis di sini.</p>
              </div>
            <?php endif; ?>
          </div>

        </div>

      </div>

      <!-- ========================================================
           TAB 2: PROFIL MAHASISWA
           ======================================================== -->
      <div id="tab-profil" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Data Pribadi & Akademik Mahasiswa</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Informasi terdaftar pada Pangkalan Data Pendidikan Tinggi</p>
          </div>
          <button class="btn btn-primary" onclick="openEditProfileModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
            Edit Profil Kontak
          </button>
        </div>

        <div style="background:#fff; border:1px solid var(--border-subtle); border-radius:var(--radius-lg); padding:32px; box-shadow:var(--shadow-sm); margin-bottom:24px;">
          <div style="display:flex; gap:32px; align-items:center; margin-bottom:32px; padding-bottom:24px; border-bottom:1px solid var(--border-subtle); flex-wrap:wrap;">
            <img src="<?= htmlspecialchars($student['avatar']) ?>" alt="Avatar" style="width:110px; height:110px; border-radius:var(--radius-full); border:4px solid var(--border-subtle);">
            <div>
              <h3 style="font-size:1.35rem; font-weight:800; color:var(--primary); margin-bottom:4px;"><?= htmlspecialchars($student['name']) ?></h3>
              <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:10px;">
                NIM: <strong><?= htmlspecialchars($student['nim']) ?></strong> • Program Studi: <strong><?= htmlspecialchars($student['study_program_name']) ?></strong>
              </div>
              <div style="display:flex; gap:8px;">
                <span class="badge badge-success"><?= htmlspecialchars($student['status']) ?></span>
                <span class="badge badge-info">Semester <?= htmlspecialchars($student['semester']) ?></span>
                <span class="badge badge-warning">Tahun Masuk <?= htmlspecialchars($student['entry_year']) ?></span>
              </div>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:32px;">
            <!-- Data Pribadi -->
            <div>
              <h4 style="font-size:1.05rem; font-weight:700; color:var(--primary); margin-bottom:16px; border-bottom:2px solid var(--brand-blue); padding-bottom:6px; display:inline-block;">
                Informasi Pribadi
              </h4>
              <table style="width:100%; font-size:0.88rem; line-height:2.2;">
                <tr>
                  <td style="width:140px; color:var(--text-muted);">Nama Lengkap</td>
                  <td style="font-weight:600;"><?= htmlspecialchars($student['name']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Tempat Lahir</td>
                  <td style="font-weight:600;"><?= htmlspecialchars($student['place_of_birth']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Tanggal Lahir</td>
                  <td style="font-weight:600;"><?= date('d F Y', strtotime($student['date_of_birth'])) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Jenis Kelamin</td>
                  <td style="font-weight:600;"><?= htmlspecialchars($student['gender']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Nomor HP / WhatsApp</td>
                  <td style="font-weight:600;" id="profPhone"><?= htmlspecialchars($student['phone']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Email Institusi</td>
                  <td style="font-weight:600;" id="profEmail"><?= htmlspecialchars($student['email']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted); vertical-align:top;">Alamat Domisili</td>
                  <td style="font-weight:600;" id="profAddress"><?= nl2br(htmlspecialchars($student['address'])) ?></td>
                </tr>
              </table>
            </div>

            <!-- Data Akademik Resmi -->
            <div>
              <h4 style="font-size:1.05rem; font-weight:700; color:var(--primary); margin-bottom:16px; border-bottom:2px solid var(--brand-gold); padding-bottom:6px; display:inline-block;">
                Informasi Akademik
              </h4>
              <table style="width:100%; font-size:0.88rem; line-height:2.2;">
                <tr>
                  <td style="width:150px; color:var(--text-muted);">NIM</td>
                  <td style="font-weight:700; color:var(--brand-blue);"><?= htmlspecialchars($student['nim']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Fakultas</td>
                  <td style="font-weight:600;"><?= htmlspecialchars($student['faculty_name']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Program Studi</td>
                  <td style="font-weight:600;"><?= htmlspecialchars($student['study_program_name']) ?> (<?= htmlspecialchars($student['degree']) ?>)</td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Semester Tempuh</td>
                  <td style="font-weight:600;">Semester <?= htmlspecialchars($student['semester']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Tahun Angkatan</td>
                  <td style="font-weight:600;"><?= htmlspecialchars($student['entry_year']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Dosen Wali (PA)</td>
                  <td style="font-weight:600;"><?= htmlspecialchars($student['advisor_name']) ?></td>
                </tr>
                <tr>
                  <td style="color:var(--text-muted);">Status Mahasiswa</td>
                  <td><span class="badge badge-success"><?= htmlspecialchars($student['status']) ?></span></td>
                </tr>
              </table>
            </div>
          </div>

          <!-- Academic Lock Notice -->
          <div style="margin-top:28px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:var(--radius-sm); padding:16px; display:flex; align-items:center; gap:12px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" style="flex-shrink:0;">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <div style="font-size:0.85rem; color:#1e3a8a;">
              <strong>Kebijakan Integritas Data Akademik:</strong> Data akademik penting seperti NIM, Program Studi, Fakultas, dan Status Mahasiswa dikunci oleh sistem. Perubahan data akademik harus melalui administrator/BAAK.
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================================
           TAB 3: KARTU RENCANA STUDI (KRS)
           ======================================================== -->
      <div id="tab-krs" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:14px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Kartu Rencana Studi (KRS) Online</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Tahun Akademik 2024/2025 Ganjil • Semester 5</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <?php 
              $krsStatus = $activeKrs['status'] ?? 'Draft';
              $badgeClass = 'badge-info';
              if ($krsStatus === 'Disetujui') $badgeClass = 'badge-success';
              elseif ($krsStatus === 'Diajukan') $badgeClass = 'badge-warning';
              elseif ($krsStatus === 'Ditolak') $badgeClass = 'badge-danger';
            ?>
            <span class="badge <?= $badgeClass ?>" style="font-size:0.85rem; padding:6px 14px;" id="krsStatusBadge">
              Status: <?= htmlspecialchars($krsStatus) ?>
            </span>
            <button class="btn btn-primary" onclick="confirmSubmitKrs()" id="btnSubmitKrs" <?= in_array($krsStatus, ['Disetujui', 'Diajukan']) ? 'disabled style="opacity:0.6; cursor:not-allowed;"' : '' ?>>
              Ajukan KRS
            </button>
          </div>
        </div>

        <!-- SKS Tracker Banner -->
        <div style="background:#ffffff; border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:20px; margin-bottom:24px; box-shadow:var(--shadow-sm);">
          <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <div>
              <span style="font-size:0.85rem; font-weight:600; color:var(--text-muted);">Beban SKS Terpilih:</span>
              <strong style="font-size:1.2rem; color:var(--brand-blue); margin-left:6px;" id="krsSelectedCredits">
                <?= (int)($activeKrs['total_credits'] ?? 0) ?>
              </strong>
              <span style="font-size:0.85rem; color:var(--text-muted);">/ <?= (int)($activeKrs['max_credits'] ?? 24) ?> SKS Maksimal</span>
            </div>
            <div style="font-size:0.82rem; color:var(--text-muted);">
              Dosen Pembimbing Akademik: <strong><?= htmlspecialchars($student['advisor_name']) ?></strong>
            </div>
          </div>
          <div style="width:100%; height:8px; background:var(--bg-subtle); border-radius:var(--radius-full); overflow:hidden;">
            <div id="krsProgressBar" style="height:100%; width: <?= min(100, (((int)($activeKrs['total_credits'] ?? 0)) / 24) * 100) ?>%; background:var(--brand-blue); transition:width 0.3s ease;"></div>
          </div>
          <?php if ($krsStatus === 'Disetujui'): ?>
            <div style="margin-top:12px; font-size:0.82rem; color:var(--success); font-weight:600; display:flex; align-items:center; gap:6px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <?= htmlspecialchars($activeKrs['note'] ?? 'KRS telah disetujui DPA.') ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Tabel Daftar Mata Kuliah Tersedia -->
        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Mata Kuliah</th>
                <th>SKS</th>
                <th>Kelas</th>
                <th>Jadwal & Ruang</th>
                <th>Dosen Pengampu</th>
                <th>Status Kuota</th>
                <th style="text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allSchedules as $sch): 
                $isChosen = in_array($sch['id'], $selectedScheduleIds);
              ?>
                <tr>
                  <td><code><?= htmlspecialchars($sch['course_code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($sch['course_name']) ?></strong></td>
                  <td><?= htmlspecialchars($sch['credits']) ?> SKS</td>
                  <td>Kelas <?= htmlspecialchars($sch['class_name']) ?></td>
                  <td><?= htmlspecialchars($sch['day']) ?>, <?= date('H:i', strtotime($sch['start_time'])) ?> - <?= date('H:i', strtotime($sch['end_time'])) ?><br><span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($sch['room']) ?></span></td>
                  <td><?= htmlspecialchars($sch['lecturer_name']) ?></td>
                  <td><?= htmlspecialchars($sch['enrolled']) ?> / <?= htmlspecialchars($sch['quota']) ?></td>
                  <td style="text-align:center;">
                    <?php if (in_array($krsStatus, ['Disetujui', 'Diajukan'])): ?>
                      <?php if ($isChosen): ?>
                        <span class="badge badge-success">Terpilih</span>
                      <?php else: ?>
                        <span style="font-size:0.8rem; color:var(--text-light);">-</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <?php if ($isChosen): ?>
                        <button class="btn btn-sm btn-outline" style="color:var(--danger); border-color:var(--danger-border);" onclick="toggleKrsCourse(<?= $sch['id'] ?>)">
                          Batal Pilih
                        </button>
                      <?php else: ?>
                        <button class="btn btn-sm btn-primary" onclick="toggleKrsCourse(<?= $sch['id'] ?>)">
                          Pilih MK
                        </button>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 4: KARTU HASIL STUDI (KHS)
           ======================================================== -->
      <div id="tab-khs" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Kartu Hasil Studi (KHS)</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Laporan Capaian Pembelajaran Mahasiswa Tiap Semester</p>
          </div>
          <div style="display:flex; gap:10px;">
            <button class="btn btn-outline" onclick="window.print()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
              Cetak KHS
            </button>
            <button class="btn btn-primary" onclick="downloadKhsPdf()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              Download PDF
            </button>
          </div>
        </div>

        <!-- Filter Semester & TA -->
        <div style="background:#fff; border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:16px; margin-bottom:20px; display:flex; gap:16px; align-items:center;">
          <div>
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); display:block; margin-bottom:4px;">Pilih Semester:</label>
            <select class="form-control" style="width:200px; padding:8px 12px;" onchange="filterKhsSemester(this.value)">
              <option value="4" selected>Semester 4 (2023/2024 Genap)</option>
              <option value="3">Semester 3 (2023/2024 Ganjil)</option>
              <option value="2">Semester 2 (2022/2023 Genap)</option>
              <option value="1">Semester 1 (2022/2023 Ganjil)</option>
            </select>
          </div>
        </div>

        <!-- Kop Surat untuk Cetak -->
        <div class="print-header">
          <img src="assets/img/logo-universitas.svg" alt="Logo" style="width:60px; height:60px; margin-bottom:8px;">
          <h2 style="font-size:1.2rem; font-weight:800; margin:0;">UNIVERSITAS NUSANTARA MANDIRI</h2>
          <p style="font-size:0.85rem; margin:0;">KARTU HASIL STUDI (KHS) MAHASISWA</p>
          <div style="margin-top:12px; font-size:0.82rem; text-align:left; display:flex; justify-content:space-between;">
            <div>NIM: <?= htmlspecialchars($student['nim']) ?><br>Nama: <?= htmlspecialchars($student['name']) ?></div>
            <div>Program Studi: <?= htmlspecialchars($student['study_program_name']) ?><br>Semester: 4</div>
          </div>
        </div>

        <!-- Tabel Nilai KHS -->
        <div class="table-responsive">
          <table class="academic-table" id="khsTable">
            <thead>
              <tr>
                <th style="width:50px;">No</th>
                <th>Kode</th>
                <th>Mata Kuliah</th>
                <th>SKS</th>
                <th>Nilai</th>
                <th>Bobot</th>
                <th>SKS x Bobot (Mutu)</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $no = 1;
              $totalSksSem = 0;
              $totalMutu = 0;
              foreach ($allGrades as $g): 
                if ((int)$g['semester'] === 4):
                  $mutu = (int)$g['credits'] * (float)$g['grade_point'];
                  $totalSksSem += (int)$g['credits'];
                  $totalMutu += $mutu;
              ?>
                <tr>
                  <td><?= $no++ ?></td>
                  <td><code><?= htmlspecialchars($g['course_code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($g['course_name']) ?></strong></td>
                  <td><?= htmlspecialchars($g['credits']) ?></td>
                  <td><span class="badge badge-success"><?= htmlspecialchars($g['grade_letter']) ?></span></td>
                  <td><?= number_format($g['grade_point'], 2) ?></td>
                  <td><?= number_format($mutu, 2) ?></td>
                </tr>
              <?php 
                endif;
              endforeach; 
              $ips = $totalSksSem > 0 ? ($totalMutu / $totalSksSem) : 0;
              ?>
            </tbody>
            <tfoot>
              <tr style="background:var(--bg-subtle); font-weight:700;">
                <td colspan="3" style="text-align:right;">Total Beban SKS Semester Ini:</td>
                <td><?= $totalSksSem ?> SKS</td>
                <td colspan="2" style="text-align:right;">Indeks Prestasi Semester (IPS):</td>
                <td style="color:var(--brand-blue); font-size:1rem;"><?= number_format($ips, 2) ?></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Ringkasan KHS -->
        <div style="background:#fff; border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:20px; margin-top:20px; display:grid; grid-template-columns:repeat(3, 1fr); gap:20px; text-align:center;">
          <div>
            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Total SKS Semester</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--primary);"><?= $totalSksSem ?></div>
          </div>
          <div>
            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Indeks Prestasi Semester (IPS)</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--brand-blue);"><?= number_format($ips, 2) ?></div>
          </div>
          <div>
            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Indeks Prestasi Kumulatif (IPK)</div>
            <div style="font-size:1.6rem; font-weight:800; color:var(--success);"><?= number_format($student['gpa_cumulative'], 2) ?></div>
          </div>
        </div>
      </div>

      <!-- ========================================================
           TAB 5: JADWAL KULIAH
           ======================================================== -->
      <div id="tab-jadwal" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Jadwal Perkuliahan Mahasiswa</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Semester Ganjil 2024/2025 • Timetable & Daftar Ruangan</p>
          </div>
          <div style="display:flex; gap:8px;">
            <button class="btn btn-outline btn-sm active" id="btnViewTimetable" onclick="toggleJadwalView('grid')">Tampilan Timetable</button>
            <button class="btn btn-outline btn-sm" id="btnViewList" onclick="toggleJadwalView('list')">Tampilan Daftar</button>
          </div>
        </div>

        <!-- View 1: Timetable Grid -->
        <div id="jadwalGridView" class="timetable-grid">
          <?php 
          $daysOfWeek = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
          foreach ($daysOfWeek as $d): 
          ?>
            <div class="timetable-day-col">
              <div class="timetable-day-header"><?= $d ?></div>
              <div class="timetable-slots">
                <?php 
                $foundSlot = false;
                foreach ($allSchedules as $sch):
                  if ($sch['day'] === $d):
                    $foundSlot = true;
                ?>
                  <div class="schedule-card-mini">
                    <div class="mk-title"><?= htmlspecialchars($sch['course_name']) ?></div>
                    <div class="mk-meta">
                      <strong><?= date('H:i', strtotime($sch['start_time'])) ?> - <?= date('H:i', strtotime($sch['end_time'])) ?></strong><br>
                      Ruang: <?= htmlspecialchars($sch['room']) ?><br>
                      Dosen: <?= htmlspecialchars($sch['lecturer_name']) ?>
                    </div>
                  </div>
                <?php 
                  endif;
                endforeach; 
                if (!$foundSlot):
                ?>
                  <div style="text-align:center; padding:40px 10px; color:var(--text-light); font-size:0.75rem;">
                    Tidak ada jadwal
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- View 2: Table List -->
        <div id="jadwalListView" class="table-responsive" style="display:none; margin-top:16px;">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Hari</th>
                <th>Waktu</th>
                <th>Kode</th>
                <th>Mata Kuliah</th>
                <th>SKS</th>
                <th>Ruangan</th>
                <th>Dosen Pengampu</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allSchedules as $sch): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($sch['day']) ?></strong></td>
                  <td><?= date('H:i', strtotime($sch['start_time'])) ?> - <?= date('H:i', strtotime($sch['end_time'])) ?> WIB</td>
                  <td><code><?= htmlspecialchars($sch['course_code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($sch['course_name']) ?></strong></td>
                  <td><?= htmlspecialchars($sch['credits']) ?> SKS</td>
                  <td><?= htmlspecialchars($sch['room']) ?></td>
                  <td><?= htmlspecialchars($sch['lecturer_name']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 6: TRANSKRIP NILAI
           ======================================================== -->
      <div id="tab-transkrip" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Transkrip Nilai Akademik</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Seluruh Riwayat Prestasi Belajar Mahasiswa</p>
          </div>
          <button class="btn btn-primary" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Download Transkrip PDF
          </button>
        </div>

        <div class="print-header">
          <img src="assets/img/logo-universitas.svg" alt="Logo" style="width:60px; height:60px; margin-bottom:8px;">
          <h2 style="font-size:1.2rem; font-weight:800; margin:0;">UNIVERSITAS NUSANTARA MANDIRI</h2>
          <p style="font-size:0.85rem; margin:0;">TRANSKRIP NILAI AKADEMIK SEMENTARA</p>
          <div style="margin-top:12px; font-size:0.82rem; text-align:left; display:flex; justify-content:space-between;">
            <div>NIM: <?= htmlspecialchars($student['nim']) ?><br>Nama: <?= htmlspecialchars($student['name']) ?></div>
            <div>Program Studi: <?= htmlspecialchars($student['study_program_name']) ?><br>Fakultas: <?= htmlspecialchars($student['faculty_name']) ?></div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th style="width:50px;">No</th>
                <th>Kode</th>
                <th>Nama Mata Kuliah</th>
                <th>SKS</th>
                <th>Nilai Huruf</th>
                <th>Bobot</th>
                <th>Semester</th>
              </tr>
            </thead>
            <tbody>
              <?php 
              $no = 1;
              $totalTranskripSks = 0;
              $totalTranskripMutu = 0;
              foreach ($allGrades as $g): 
                $mutu = (int)$g['credits'] * (float)$g['grade_point'];
                $totalTranskripSks += (int)$g['credits'];
                $totalTranskripMutu += $mutu;
              ?>
                <tr>
                  <td><?= $no++ ?></td>
                  <td><code><?= htmlspecialchars($g['course_code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($g['course_name']) ?></strong></td>
                  <td><?= htmlspecialchars($g['credits']) ?></td>
                  <td><span class="badge badge-success"><?= htmlspecialchars($g['grade_letter']) ?></span></td>
                  <td><?= number_format($g['grade_point'], 2) ?></td>
                  <td>Semester <?= htmlspecialchars($g['semester']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Ringkasan Transkrip -->
        <div style="background:#fff; border:1px solid var(--border-subtle); border-radius:var(--radius-md); padding:24px; margin-top:24px; display:grid; grid-template-columns:repeat(3, 1fr); gap:24px; text-align:center;">
          <div>
            <div style="font-size:0.82rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Total SKS Lulus</div>
            <div style="font-size:1.8rem; font-weight:800; color:var(--primary);"><?= $student['total_credits'] ?> SKS</div>
          </div>
          <div>
            <div style="font-size:0.82rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Indeks Prestasi Kumulatif (IPK)</div>
            <div style="font-size:1.8rem; font-weight:800; color:var(--brand-blue);"><?= number_format($student['gpa_cumulative'], 2) ?></div>
          </div>
          <div>
            <div style="font-size:0.82rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Predikat Kelulusan Sementara</div>
            <div style="font-size:1.35rem; font-weight:800; color:var(--success);">Dengan Pujian (Cum Laude)</div>
          </div>
        </div>
      </div>

      <!-- ========================================================
           TAB 7: KALENDER AKADEMIK
           ======================================================== -->
      <div id="tab-kalender" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Kalender Akademik</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Agenda & Milestone Universitas Nusantara Mandiri TA 2024/2025</p>
        </div>

        <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:20px;">
          <!-- Timeline Ganjil -->
          <div class="card">
            <h3 class="card-title" style="border-bottom:1px solid var(--border-subtle); padding-bottom:12px; margin-bottom:16px;">
              Semester Ganjil 2024/2025
            </h3>
            <div style="display:flex; flex-direction:column; gap:14px;">
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-success" style="width:110px; justify-content:center;">01 - 10 Sep 2024</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Masa Pengisian KRS Online</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Konsultasi Dosen Wali & Pengajuan Mata Kuliah</div>
                </div>
              </div>
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-info" style="width:110px; justify-content:center;">11 - 18 Sep 2024</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Masa Perubahan / Revisi KRS</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Batas akhir penyesuaian kelas & pembatalan MK</div>
                </div>
              </div>
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-warning" style="width:110px; justify-content:center;">21 Okt - 01 Nov 2024</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Ujian Tengah Semester (UTS)</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Evaluasi pembelajaran paruh semester</div>
                </div>
              </div>
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-warning" style="width:110px; justify-content:center;">06 - 17 Jan 2025</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Ujian Akhir Semester (UAS)</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Penilaian akhir semester berjalan</div>
                </div>
              </div>
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-outline-white" style="color:var(--primary); background:var(--bg-subtle); border-color:var(--border-strong); width:110px; justify-content:center;">20 - 31 Jan 2025</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Libur Akademik Semester Ganjil</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Penginputan nilai KHS oleh dosen pengampu</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Timeline Genap & Wisuda -->
          <div class="card">
            <h3 class="card-title" style="border-bottom:1px solid var(--border-subtle); padding-bottom:12px; margin-bottom:16px;">
              Semester Genap & Agenda Wisuda
            </h3>
            <div style="display:flex; flex-direction:column; gap:14px;">
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-info" style="width:110px; justify-content:center;">03 - 14 Feb 2025</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Registrasi & Pengisian KRS Genap</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Awal masa perkuliahan semester genap</div>
                </div>
              </div>
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-warning" style="width:110px; justify-content:center;">07 - 18 Apr 2025</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Ujian Tengah Semester (UTS) Genap</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Pelaksanaan evaluasi materi perkuliahan</div>
                </div>
              </div>
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-warning" style="width:110px; justify-content:center;">16 - 27 Jun 2025</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Ujian Akhir Semester (UAS) Genap</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Evaluasi akhir tahun akademik</div>
                </div>
              </div>
              <div style="display:flex; gap:14px; align-items:flex-start;">
                <span class="badge badge-success" style="width:110px; justify-content:center;">23 Agustus 2025</span>
                <div>
                  <div style="font-weight:700; font-size:0.9rem;">Upacara Wisuda Sarjana & Magister Ke-32</div>
                  <div style="font-size:0.8rem; color:var(--text-muted);">Pelepasan lulusan di Auditorium Utama Rektorat</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================================
           TAB 8: PEMBAYARAN
           ======================================================== -->
      <div id="tab-pembayaran" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Informasi Pembayaran Biaya Kuliah</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Status Tagihan, Kuitansi Digital, dan Pembayaran Virtual Account</p>
          </div>
        </div>

        <!-- Ringkasan Keuangan -->
        <div class="kpi-grid">
          <div class="kpi-card">
            <div>
              <div class="kpi-label">Total Tagihan TA 2024/2025</div>
              <div class="kpi-value" style="font-size:1.45rem;">Rp <?= number_format($totalTagihan, 0, ',', '.') ?></div>
            </div>
            <div class="kpi-icon" style="background:#eff6ff; color:#2563eb;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
            </div>
          </div>

          <div class="kpi-card">
            <div>
              <div class="kpi-label">Total Sudah Dibayar</div>
              <div class="kpi-value" style="font-size:1.45rem; color:var(--success);">Rp <?= number_format($totalTerbayar, 0, ',', '.') ?></div>
            </div>
            <div class="kpi-icon" style="background:#ecfdf5; color:#059669;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>
          </div>

          <div class="kpi-card">
            <div>
              <div class="kpi-label">Sisa Tagihan Tertunggak</div>
              <div class="kpi-value" style="font-size:1.45rem; color:<?= $sisaTagihan > 0 ? 'var(--warning)' : 'var(--success)' ?>;">
                Rp <?= number_format($sisaTagihan, 0, ',', '.') ?>
              </div>
            </div>
            <div class="kpi-icon" style="background:#fffbeb; color:#d97706;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
          </div>

          <div class="kpi-card">
            <div>
              <div class="kpi-label">Status Registrasi Keuangan</div>
              <div class="kpi-value" style="font-size:1.2rem; color:var(--success);">Bebas Masalah</div>
            </div>
            <div class="kpi-icon" style="background:#f0fdf4; color:#15803d;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
          </div>
        </div>

        <!-- Tabel Rincian Tagihan -->
        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Jenis Tagihan</th>
                <th>Tahun Akademik</th>
                <th>Nominal</th>
                <th>Batas Pembayaran</th>
                <th>Status</th>
                <th>Tanggal Bayar</th>
                <th>Nomor Kuitansi</th>
                <th style="text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allPayments as $pay): 
                $st = $pay['status'];
                $bClass = 'badge-info';
                if ($st === 'Lunas') $bClass = 'badge-success';
                elseif ($st === 'Menunggu Pembayaran') $bClass = 'badge-warning';
                elseif ($st === 'Terlambat' || $st === 'Belum Dibayar') $bClass = 'badge-danger';
              ?>
                <tr>
                  <td><strong><?= htmlspecialchars($pay['invoice_type']) ?></strong></td>
                  <td><?= htmlspecialchars($pay['academic_year_name']) ?></td>
                  <td><strong>Rp <?= number_format($pay['amount'], 0, ',', '.') ?></strong></td>
                  <td><?= date('d M Y', strtotime($pay['due_date'])) ?></td>
                  <td><span class="badge <?= $bClass ?>"><?= htmlspecialchars($st) ?></span></td>
                  <td><?= $pay['paid_at'] ? date('d M Y, H:i', strtotime($pay['paid_at'])) : '-' ?></td>
                  <td><?= $pay['receipt_number'] ? '<code>' . htmlspecialchars($pay['receipt_number']) . '</code>' : '-' ?></td>
                  <td style="text-align:center;">
                    <?php if ($st === 'Lunas'): ?>
                      <button class="btn btn-outline btn-sm" onclick="showReceiptModal('<?= htmlspecialchars($pay['invoice_type']) ?>', '<?= number_format($pay['amount'], 0, ',', '.') ?>', '<?= htmlspecialchars($pay['receipt_number']) ?>', '<?= date('d F Y', strtotime($pay['paid_at'])) ?>')">
                        Kuitansi
                      </button>
                    <?php else: ?>
                      <button class="btn btn-primary btn-sm" onclick="openPaymentModal(<?= $pay['id'] ?>, '<?= htmlspecialchars($pay['invoice_type']) ?>', <?= $pay['amount'] ?>, '<?= htmlspecialchars($pay['va_number']) ?>')">
                        Bayar Sekarang
                      </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 9: PENGUMUMAN
           ======================================================== -->
      <div id="tab-pengumuman" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Pusat Pengumuman & Berita Kampus</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Informasi Akademik, Kemahasiswaan, Keuangan, dan Beasiswa</p>
          </div>
          <div style="display:flex; gap:8px;">
            <button class="btn btn-outline btn-sm active" onclick="filterAnnouncements('Semua')">Semua Kategori</button>
            <button class="btn btn-outline btn-sm" onclick="filterAnnouncements('Akademik')">Akademik</button>
            <button class="btn btn-outline btn-sm" onclick="filterAnnouncements('Beasiswa')">Beasiswa</button>
            <button class="btn btn-outline btn-sm" onclick="filterAnnouncements('Keuangan')">Keuangan</button>
          </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;" id="announcementCardsContainer">
          <?php foreach ($allAnnouncements as $ann): ?>
            <div class="card ann-card" data-category="<?= htmlspecialchars($ann['category']) ?>">
              <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                <div style="display:flex; gap:10px; align-items:center;">
                  <span class="badge badge-info"><?= htmlspecialchars($ann['category']) ?></span>
                  <?php if ((int)$ann['is_pinned'] === 1): ?>
                    <span class="badge badge-warning">Pengumuman Penting</span>
                  <?php endif; ?>
                </div>
                <span style="font-size:0.8rem; color:var(--text-light);"><?= date('d F Y', strtotime($ann['published_at'])) ?></span>
              </div>
              <h3 style="font-size:1.15rem; font-weight:700; color:var(--primary); margin-bottom:8px;">
                <?= htmlspecialchars($ann['title']) ?>
              </h3>
              <p style="font-size:0.9rem; color:var(--text-muted); line-height:1.6; margin-bottom:14px;">
                <?= nl2br(htmlspecialchars($ann['content'])) ?>
              </p>
              <?php if (!empty($ann['attachment_name'])): ?>
                <div style="display:inline-flex; align-items:center; gap:8px; padding:6px 12px; background:var(--bg-subtle); border:1px solid var(--border-subtle); border-radius:var(--radius-sm); font-size:0.8rem; color:var(--brand-blue); cursor:pointer;" onclick="downloadDemoDoc('<?= htmlspecialchars($ann['attachment_name']) ?>')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                  <span>Lampiran: <?= htmlspecialchars($ann['attachment_name']) ?></span>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ========================================================
           TAB 10: DOKUMEN AKADEMIK
           ======================================================== -->
      <div id="tab-dokumen" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Dokumen Akademik Resmi</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Unduh dan cetak berkas legalitas studi terverifikasi secara mandiri</p>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>Nama Dokumen</th>
                <th>Tipe Berkas</th>
                <th>Tanggal Terbit</th>
                <th>Status Validasi</th>
                <th style="text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allDocuments as $doc): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($doc['title']) ?></strong></td>
                  <td><span class="badge badge-outline-white" style="color:var(--primary); background:var(--bg-subtle); border-color:var(--border-strong);"><?= htmlspecialchars($doc['document_type']) ?></span></td>
                  <td><?= date('d M Y', strtotime($doc['issue_date'])) ?></td>
                  <td><span class="badge badge-success"><?= htmlspecialchars($doc['status']) ?></span></td>
                  <td style="text-align:center;">
                    <div style="display:inline-flex; gap:8px;">
                      <button class="btn btn-outline btn-sm" onclick="previewDocModal('<?= htmlspecialchars($doc['title']) ?>', '<?= htmlspecialchars($doc['document_type']) ?>', '<?= date('d F Y', strtotime($doc['issue_date'])) ?>')">
                        Lihat
                      </button>
                      <button class="btn btn-primary btn-sm" onclick="downloadDemoDoc('<?= htmlspecialchars($doc['title']) ?>.pdf')">
                        Download
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
           TAB 11: LAYANAN AKADEMIK
           ======================================================== -->
      <div id="tab-layanan" class="tab-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Layanan Akademik Mahasiswa</h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Pengajuan Surat Mahasiswa Aktif, Cuti, Legalisir, dan Dokumen Resmi</p>
          </div>
          <button class="btn btn-primary" onclick="openRequestServiceModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Buat Pengajuan Baru
          </button>
        </div>

        <div class="table-responsive">
          <table class="academic-table">
            <thead>
              <tr>
                <th>ID Permohonan</th>
                <th>Jenis Layanan</th>
                <th>Keperluan Pengajuan</th>
                <th>Tanggal Pengajuan</th>
                <th>Status</th>
                <th>Catatan BAAK</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($allServices)): ?>
                <?php foreach ($allServices as $srv): 
                  $sSt = $srv['status'];
                  $sBadge = 'badge-info';
                  if ($sSt === 'Selesai' || $sSt === 'Disetujui') $sBadge = 'badge-success';
                  elseif ($sSt === 'Diproses') $sBadge = 'badge-warning';
                  elseif ($sSt === 'Ditolak') $sBadge = 'badge-danger';
                ?>
                  <tr>
                    <td><code>#SRV-<?= sprintf('%04d', $srv['id']) ?></code></td>
                    <td><strong><?= htmlspecialchars($srv['service_type']) ?></strong></td>
                    <td style="max-width:280px;"><?= htmlspecialchars($srv['purpose']) ?></td>
                    <td><?= date('d M Y, H:i', strtotime($srv['submitted_at'])) ?></td>
                    <td><span class="badge <?= $sBadge ?>"><?= htmlspecialchars($sSt) ?></span></td>
                    <td style="font-size:0.82rem; color:var(--text-muted);"><?= htmlspecialchars($srv['admin_note'] ?? 'Sedang ditinjau oleh staf BAAK.') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="empty-state">
                    <h3>Belum ada pengajuan layanan</h3>
                    <p>Klik tombol 'Buat Pengajuan Baru' di atas untuk mengajukan surat atau layanan administrasi.</p>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ========================================================
           TAB 12: PENGATURAN
           ======================================================== -->
      <div id="tab-pengaturan" class="tab-content">
        <div style="margin-bottom:20px;">
          <h2 style="font-size:1.4rem; font-weight:800; color:var(--primary);">Pengaturan Akun & Keamanan</h2>
          <p style="font-size:0.85rem; color:var(--text-muted);">Kelola kata sandi portal dan preferensi notifikasi Anda</p>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
          <!-- Form Ganti Password -->
          <div class="card">
            <h3 class="card-title" style="margin-bottom:16px;">Ubah Kata Sandi (Password)</h3>
            <form id="changePasswordForm" onsubmit="handleChangePassword(event)">
              <div class="form-group">
                <label class="form-label">Kata Sandi Lama</label>
                <input type="password" id="oldPassword" class="form-control" required placeholder="Masukkan kata sandi lama">
              </div>

              <div class="form-group">
                <label class="form-label">Kata Sandi Baru</label>
                <input type="password" id="newPassword" class="form-control" required minlength="6" placeholder="Minimal 6 karakter">
              </div>

              <div class="form-group">
                <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                <input type="password" id="confirmPassword" class="form-control" required placeholder="Ketik ulang kata sandi baru">
              </div>

              <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                Simpan Kata Sandi Baru
              </button>
            </form>
          </div>

          <!-- Preferensi Notifikasi & Sesi -->
          <div class="card">
            <h3 class="card-title" style="margin-bottom:16px;">Preferensi Pemberitahuan</h3>
            <div style="display:flex; flex-direction:column; gap:16px;">
              <label class="checkbox-label" style="justify-content:space-between; width:100%;">
                <div>
                  <strong style="color:var(--primary); font-size:0.9rem;">Notifikasi Email</strong>
                  <div style="font-size:0.78rem; color:var(--text-muted);">Kirim pemberitahuan KRS dan nilai ke email institusi</div>
                </div>
                <input type="checkbox" checked style="width:18px; height:18px;">
              </label>

              <label class="checkbox-label" style="justify-content:space-between; width:100%;">
                <div>
                  <strong style="color:var(--primary); font-size:0.9rem;">Pengingat Jadwal Kuliah</strong>
                  <div style="font-size:0.78rem; color:var(--text-muted);">Pemberitahuan sebelum jam perkuliahan dimulai</div>
                </div>
                <input type="checkbox" checked style="width:18px; height:18px;">
              </label>

              <label class="checkbox-label" style="justify-content:space-between; width:100%;">
                <div>
                  <strong style="color:var(--primary); font-size:0.9rem;">Peringatan Jatuh Tempo SPP</strong>
                  <div style="font-size:0.78rem; color:var(--text-muted);">Kirim notifikasi 3 hari sebelum batas pembayaran</div>
                </div>
                <input type="checkbox" checked style="width:18px; height:18px;">
              </label>
            </div>

            <div style="margin-top:32px; padding-top:20px; border-top:1px solid var(--border-subtle);">
              <h4 style="font-size:0.95rem; font-weight:700; color:var(--primary); margin-bottom:8px;">Informasi Keamanan Sesi</h4>
              <p style="font-size:0.8rem; color:var(--text-muted); line-height:1.5;">
                Akun terdaftar: <strong><?= htmlspecialchars($student['nim']) ?></strong><br>
                Status Enkripsi: AES-256 SSL TLS 1.3 Aktif<br>
                Role: <strong>Mahasiswa Aktif</strong> (Hak Akses Pribadi Terproteksi)
              </p>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- ========================================================
     MODALS
     ======================================================== -->

<!-- 1. Modal Edit Profil Kontak -->
<div class="modal-overlay" id="editProfileModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="modal-title">Perbarui Kontak Mahasiswa</h3>
      <button class="modal-close" onclick="closeModal('editProfileModal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="6"></line></svg>
      </button>
    </div>
    <form id="editProfileForm" onsubmit="handleUpdateProfile(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nomor Handphone / WhatsApp</label>
          <input type="tel" id="inputPhone" class="form-control" value="<?= htmlspecialchars($student['phone']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Kontak</label>
          <input type="email" id="inputEmail" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Alamat Domisili</label>
          <textarea id="inputAddress" class="form-control" rows="3" required><?= htmlspecialchars($student['address']) ?></textarea>
        </div>
        <div style="font-size:0.78rem; color:var(--text-muted); background:var(--bg-subtle); padding:10px; border-radius:var(--radius-sm);">
          <strong>Catatan:</strong> Data akademik penting seperti NIM, Program Studi, Fakultas, dan Nama tidak dapat diubah di sini.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editProfileModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. Modal Konfirmasi Pengajuan KRS -->
<div class="modal-overlay" id="confirmKrsModal">
  <div class="modal-container" style="max-width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">Konfirmasi Pengajuan KRS</h3>
      <button class="modal-close" onclick="closeModal('confirmKrsModal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="6"></line></svg>
      </button>
    </div>
    <div class="modal-body">
      <p style="font-size:0.95rem; color:var(--primary); font-weight:600; margin-bottom:10px;">
        Apakah Anda yakin ingin mengajukan KRS?
      </p>
      <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.5;">
        Setelah diajukan, KRS akan diteruskan kepada Dosen Pembimbing Akademik (DPA) untuk dievaluasi. Anda tidak dapat menambah atau membatalkan mata kuliah sampai periode revisi dibuka.
      </p>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('confirmKrsModal')">Batal</button>
      <button type="button" class="btn btn-primary" onclick="executeSubmitKrs()">Ya, Ajukan KRS</button>
    </div>
  </div>
</div>

<!-- 3. Modal Buat Pengajuan Layanan Akademik -->
<div class="modal-overlay" id="requestServiceModal">
  <div class="modal-container">
    <div class="modal-header">
      <h3 class="modal-title">Formulir Layanan Akademik</h3>
      <button class="modal-close" onclick="closeModal('requestServiceModal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="6"></line></svg>
      </button>
    </div>
    <form id="requestServiceForm" onsubmit="handleRequestService(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Jenis Layanan</label>
          <select id="serviceTypeSelect" class="form-control" required>
            <option value="">-- Pilih Jenis Layanan --</option>
            <option value="Surat Keterangan Aktif Kuliah">Surat Keterangan Aktif Kuliah</option>
            <option value="Surat Keterangan Mahasiswa">Surat Keterangan Mahasiswa</option>
            <option value="Pengajuan Cuti Akademik">Pengajuan Cuti Akademik</option>
            <option value="Pengajuan Perubahan Data Kontak">Pengajuan Perubahan Data Kontak</option>
            <option value="Legalisir Dokumen Akademik">Legalisir Dokumen Akademik</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tujuan / Keperluan Permohonan</label>
          <textarea id="servicePurpose" class="form-control" rows="3" placeholder="Contoh: Pengurusan beasiswa instansi / tunjangan orang tua..." required></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Catatan Tambahan (Opsional)</label>
          <input type="text" id="serviceNote" class="form-control" placeholder="Contoh: Membutuhkan 2 rangkap dengan cap basah">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('requestServiceModal')">Batal</button>
        <button type="submit" class="btn btn-primary">Kirim Permohonan</button>
      </div>
    </form>
  </div>
</div>

<!-- 4. Modal Pembayaran Virtual Account -->
<div class="modal-overlay" id="paymentModal">
  <div class="modal-container" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">Pembayaran Biaya Kuliah Online</h3>
      <button class="modal-close" onclick="closeModal('paymentModal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="6"></line></svg>
      </button>
    </div>
    <div class="modal-body">
      <div style="background:var(--bg-subtle); padding:16px; border-radius:var(--radius-sm); margin-bottom:16px;">
        <div style="font-size:0.8rem; color:var(--text-muted);">Nama Tagihan:</div>
        <div style="font-weight:700; font-size:1.05rem; color:var(--primary);" id="payModalTitle">-</div>
        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:8px;">Total yang harus dibayar:</div>
        <div style="font-weight:800; font-size:1.5rem; color:var(--brand-blue);" id="payModalAmount">-</div>
      </div>

      <div class="form-group">
        <label class="form-label">Pilih Metode Pembayaran</label>
        <select id="paymentMethodSelect" class="form-control">
          <option value="Bank Mandiri Virtual Account">Bank Mandiri Virtual Account</option>
          <option value="Bank BCA Virtual Account">Bank BCA Virtual Account</option>
          <option value="Bank BRI Virtual Account">Bank BRI Virtual Account</option>
          <option value="Bank BNI Virtual Account">Bank BNI Virtual Account</option>
        </select>
      </div>

      <div style="background:#f0f9ff; border:1px solid #bae6fd; padding:14px; border-radius:var(--radius-sm); font-size:0.85rem;">
        <div style="color:#0369a1; font-weight:600; margin-bottom:4px;">Nomor Virtual Account:</div>
        <div style="font-size:1.25rem; font-weight:800; font-family:monospace; color:var(--primary);" id="payModalVa">889210202401001</div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Simulasi: Klik "Konfirmasi Pembayaran Selesai" untuk memproses status Lunas.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('paymentModal')">Tutup</button>
      <button type="button" class="btn btn-primary" onclick="executePayment()">Konfirmasi Pembayaran Selesai</button>
    </div>
  </div>
</div>

<!-- 5. Modal Kuitansi Resmi -->
<div class="modal-overlay" id="receiptModal">
  <div class="modal-container" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title">Bukti Pembayaran Sah</h3>
      <button class="modal-close" onclick="closeModal('receiptModal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="6"></line></svg>
      </button>
    </div>
    <div class="modal-body" style="font-size:0.9rem;">
      <div style="text-align:center; margin-bottom:16px;">
        <img src="assets/img/logo-universitas.svg" alt="Logo" style="width:48px; height:48px; margin-bottom:6px;">
        <h4 style="margin:0; font-size:1.05rem;">UNIVERSITAS NUSANTARA MANDIRI</h4>
        <p style="font-size:0.78rem; color:var(--text-muted); margin:0;">BIRO ADMINISTRASI KEUANGAN</p>
      </div>
      <div style="border-top:1px dashed var(--border-strong); border-bottom:1px dashed var(--border-strong); padding:14px 0; margin-bottom:14px;">
        <table style="width:100%; font-size:0.85rem; line-height:2;">
          <tr><td style="color:var(--text-muted);">No. Kuitansi:</td><td style="font-weight:700;" id="recNumber">-</td></tr>
          <tr><td style="color:var(--text-muted);">NIM / Nama:</td><td><?= htmlspecialchars($student['nim']) ?> / <?= htmlspecialchars($student['name']) ?></td></tr>
          <tr><td style="color:var(--text-muted);">Deskripsi:</td><td style="font-weight:600;" id="recDesc">-</td></tr>
          <tr><td style="color:var(--text-muted);">Tanggal Lunas:</td><td id="recDate">-</td></tr>
          <tr><td style="color:var(--text-muted);">Jumlah Terbayar:</td><td style="font-weight:800; color:var(--brand-blue); font-size:1.05rem;" id="recAmount">-</td></tr>
          <tr><td style="color:var(--text-muted);">Status:</td><td><span class="badge badge-success">Lunas Terverifikasi</span></td></tr>
        </table>
      </div>
      <div style="font-size:0.75rem; color:var(--text-muted); text-align:center;">
        Dokumen ini diterbitkan secara elektronik oleh Sistem Informasi Akademik dan sah tanpa tanda tangan basah.
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="window.print()">Cetak Kuitansi</button>
      <button type="button" class="btn btn-primary" onclick="closeModal('receiptModal')">Tutup</button>
    </div>
  </div>
</div>

<!-- 6. Modal Pratinjau Dokumen -->
<div class="modal-overlay" id="previewDocModal">
  <div class="modal-container" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title" id="prevDocTitle">Pratinjau Dokumen</h3>
      <button class="modal-close" onclick="closeModal('previewDocModal')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="6"></line></svg>
      </button>
    </div>
    <div class="modal-body" style="text-align:center; padding:32px 24px;">
      <div style="width:64px; height:64px; background:var(--info-bg); color:var(--brand-blue); border-radius:var(--radius-full); display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto;">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
      </div>
      <h4 style="font-size:1.1rem; font-weight:700; margin-bottom:6px;" id="prevDocName">-</h4>
      <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;" id="prevDocMeta">-</p>
      <div style="background:var(--bg-subtle); padding:12px; border-radius:var(--radius-sm); font-size:0.8rem; color:var(--text-main); text-align:left;">
        <strong>Metadata Dokumen:</strong><br>
        Tanda Tangan Digital: Dilengkapi QR-Code Verifikasi PDDIKTI<br>
        Integritas Berkas: Terverifikasi oleh Biro Administrasi Akademik (BAAK)
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('previewDocModal')">Tutup</button>
      <button type="button" class="btn btn-primary" onclick="downloadDemoDoc('Dokumen-Akademik.pdf')">Unduh Dokumen</button>
    </div>
  </div>
</div>

<script src="assets/js/portal.js"></script>
</body>
</html>
