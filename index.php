<?php
require_once __DIR__ . '/config/database.php';

// Ambil pengumuman publik terbaru untuk ditampilkan di landing page
$announcements = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY is_pinned DESC, published_at DESC LIMIT 3");
    $announcements = $stmt->fetchAll();
} catch (Exception $e) {
    // Graceful fallback
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Universitas Nusantara Mandiri — Pusat Keunggulan Akademik & Inovasi</title>
  <meta name="description" content="Sistem Informasi Akademik dan Portal Resmi Universitas Nusantara Mandiri. Mewujudkan pendidikan tinggi bertaraf dunia yang berkarakter dan inovatif.">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="landing-full-bg">

  <!-- ========================================================
       IMMERSIVE FULL-PAGE FOTO BERSAMA CONTINUOUS BACKGROUND
       ======================================================== -->
  <div class="site-full-photo-container" aria-hidden="true">
    <img src="foto bersama.jpeg" alt="Sivitas Akademika Universitas Nusantara Mandiri" class="site-full-photo-img" id="siteFullPhotoImg">
    <div class="site-full-photo-overlay"></div>
  </div>

  <!-- Landing Header -->
  <header class="landing-header">
    <div class="landing-nav-container">
      <a href="index.php" class="brand-badge">
        <img src="assets/img/logo-universitas.svg" alt="Logo Universitas Nusantara Mandiri">
        <div class="brand-text">
          <h1>UNIVERSITAS NUSANTARA MANDIRI</h1>
          <span>Knowledge • Integrity • Innovation</span>
        </div>
      </a>

      <ul class="landing-nav-links">
        <li><a href="#beranda">Beranda</a></li>
        <li><a href="#tentang">Tentang Kampus</a></li>
        <li><a href="#fakultas">Fakultas</a></li>
        <li><a href="#berita">Pengumuman</a></li>
        <li><a href="#agenda">Kalender</a></li>
        <li><a href="#kontak">Kontak</a></li>
      </ul>

      <div>
        <a href="login.php" class="btn btn-primary" title="Masuk ke Sistem Informasi Akademik" style="padding: 9px 22px; font-weight: 700; font-size: 0.92rem; display: inline-flex; align-items: center; gap: 8px;">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
            <polyline points="10 17 15 12 10 7"></polyline>
            <line x1="15" y1="12" x2="3" y2="12"></line>
          </svg>
          Login
        </a>
      </div>
    </div>
  </header>

  <!-- HERO SECTION WITH FOTO BERSAMA -->
  <section id="beranda" class="hero-showcase" style="background: transparent;">
    <div class="hero-content" id="heroContent">
      <div class="hero-glass-card">
        <div class="hero-tag">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="7"></circle>
            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
          </svg>
          Akreditasi Unggul (A) BAN-PT • Kampus Berkelanjutan
        </div>

        <h1 class="hero-title">
          Membangun Generasi Unggul Berkarakter & Berdaya Saing Global
        </h1>

        <p class="hero-subtitle">
          Selamat datang di Universitas Nusantara Mandiri. Institusi pendidikan tinggi terdepan dengan kurikulum berbasis teknologi modern, riset berdampak nyata, dan ekosistem akademik yang kolaboratif.
        </p>

        <div class="hero-actions">
          <a href="login.php" class="btn btn-primary btn-lg">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
              <polyline points="10 17 15 12 10 7"></polyline>
              <line x1="15" y1="12" x2="3" y2="12"></line>
            </svg>
            Masuk Portal SIAKAD
          </a>
          <a href="#fakultas" class="btn btn-outline btn-lg" style="color: #ffffff; border-color: rgba(255,255,255,0.4); background: rgba(255,255,255,0.12);">
            Eksplorasi Program Studi
          </a>
        </div>
      </div>
    </div>

    <!-- Animated Scroll Down Indicator -->
    <div class="hero-scroll-indicator" onclick="document.getElementById('tentang').scrollIntoView({behavior:'smooth'})" id="heroScrollIndicator">
      <span>Scroll untuk Menjelajah</span>
      <div class="scroll-mouse">
        <div class="scroll-wheel"></div>
      </div>
    </div>
  </section>

  <!-- STATS RIBBON -->
  <div class="hero-stats-bar">
    <div class="stats-grid">
      <div class="stat-item reveal-item">
        <div class="stat-num">15.400+</div>
        <div class="stat-label">Mahasiswa Aktif Terdaftar</div>
      </div>
      <div class="stat-item reveal-item">
        <div class="stat-num">48</div>
        <div class="stat-label">Program Studi Sarjana & Magister</div>
      </div>
      <div class="stat-item reveal-item">
        <div class="stat-num">96.4%</div>
        <div class="stat-label">Tingkat Serapan Kerja Lulusan</div>
      </div>
      <div class="stat-item reveal-item">
        <div class="stat-num">140+</div>
        <div class="stat-label">Kemitraan Industri & Riset Global</div>
      </div>
    </div>
  </div>

  <!-- TENTANG KAMPUS -->
  <section id="tentang" class="section" style="position: relative; z-index: 5;">
    <div class="section-header reveal-item">
      <span class="section-badge dark-section-badge">Profil & Identitas</span>
      <h2 class="section-title dark-section-title">Dedikasi untuk Mutu Pendidikan Tertinggi</h2>
      <p class="section-desc dark-section-desc">
        Menyelenggarakan tridharma perguruan tinggi berlandaskan integritas akademik, kebebasan berpikir, dan penguasaan sains teknologi masa depan.
      </p>
    </div>

    <div class="grid-3">
      <div class="glass-section-card reveal-item">
        <div class="card-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
            <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
          </svg>
        </div>
        <h3 class="card-title">Kurikulum Berbasis Kompetensi</h3>
        <p class="card-text">
          Dirancang bersama praktisi industri teknologi dan riset untuk memastikan setiap lulusan memiliki keterampilan siap terap di era transformasi digital.
        </p>
      </div>

      <div class="glass-section-card reveal-item">
        <div class="card-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
        </div>
        <h3 class="card-title">Sistem Akademik Terintegrasi</h3>
        <p class="card-text">
          Layanan SIAKAD modern mempermudah mahasiswa mengelola rencana studi, melihat nilai KHS, presensi, hingga pengajuan dokumen resmi secara digital.
        </p>
      </div>

      <div class="glass-section-card reveal-item">
        <div class="card-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
          </svg>
        </div>
        <h3 class="card-title">Jejaring Global & Karir</h3>
        <p class="card-text">
          Program pertukaran mahasiswa internasional, program magang bersertifikat, dan career development center yang terhubung dengan ratusan korporasi mitra.
        </p>
      </div>
    </div>
  </section>

  <!-- FAKULTAS -->
  <section id="fakultas" style="background: transparent; padding: 80px 24px; position: relative; z-index: 5;">
    <div style="max-width: 1280px; margin: 0 auto;">
      <div class="section-header reveal-item">
        <span class="section-badge dark-section-badge">Fakultas & Keilmuan</span>
        <h2 class="section-title dark-section-title">Program Studi Unggulan</h2>
        <p class="section-desc dark-section-desc">Pilihan program studi sarjana yang memadukan teori fundamental dengan implementasi praktis industri.</p>
      </div>

      <div class="grid-3">
        <div class="glass-section-card reveal-item">
          <div style="font-size:0.75rem; font-weight:700; color:#60a5fa; text-transform:uppercase; margin-bottom:8px;">Fakultas Teknologi Informasi</div>
          <h3 class="card-title">Teknik Informatika (S1)</h3>
          <p class="card-text" style="margin-bottom:16px;">
            Fokus pada Rekayasa Perangkat Lunak, Kecerdasan Buatan (AI), Machine Learning, serta Keamanan Siber & Cloud Architecture.
          </p>
          <div style="font-size:0.82rem; color:#94a3b8; font-weight:600;">Akreditasi: Unggul (A) • Gelar: S.Kom.</div>
        </div>

        <div class="glass-section-card reveal-item">
          <div style="font-size:0.75rem; font-weight:700; color:#60a5fa; text-transform:uppercase; margin-bottom:8px;">Fakultas Teknologi Informasi</div>
          <h3 class="card-title">Sistem Informasi (S1)</h3>
          <p class="card-text" style="margin-bottom:16px;">
            Integrasi proses bisnis perusahaan dengan teknologi enterprise, manajemen basis data analitik, dan arsitektur sistem informasi.
          </p>
          <div style="font-size:0.82rem; color:#94a3b8; font-weight:600;">Akreditasi: Unggul (A) • Gelar: S.Kom.</div>
        </div>

        <div class="glass-section-card reveal-item">
          <div style="font-size:0.75rem; font-weight:700; color:#60a5fa; text-transform:uppercase; margin-bottom:8px;">Fakultas Ekonomi & Bisnis</div>
          <h3 class="card-title">Manajemen Bisnis (S1)</h3>
          <p class="card-text" style="margin-bottom:16px;">
            Manajemen strategi, kewirausahaan digital, ekonomi manajerial, keuangan terapan, dan operasional bisnis multinasional.
          </p>
          <div style="font-size:0.82rem; color:#94a3b8; font-weight:600;">Akreditasi: Unggul (A) • Gelar: S.M.</div>
        </div>
      </div>
    </div>
  </section>

  <!-- PENGUMUMAN TERBARU -->
  <section id="berita" class="section" style="position: relative; z-index: 5;">
    <div class="section-header reveal-item">
      <span class="section-badge dark-section-badge">Pusat Informasi</span>
      <h2 class="section-title dark-section-title">Pengumuman & Agenda Kampus</h2>
      <p class="section-desc dark-section-desc">Kabar terbaru seputar kebijakan akademik, beasiswa, dan agenda kegiatan universitas.</p>
    </div>

    <div class="grid-3">
      <?php if (!empty($announcements)): ?>
        <?php foreach ($announcements as $ann): ?>
          <div class="glass-section-card reveal-item">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
              <span class="badge badge-info"><?= htmlspecialchars($ann['category']) ?></span>
              <span style="font-size:0.78rem; color:#94a3b8;"><?= date('d M Y', strtotime($ann['published_at'])) ?></span>
            </div>
            <h3 class="card-title" style="font-size:1.05rem; line-height:1.4; margin-bottom:10px;">
              <?= htmlspecialchars($ann['title']) ?>
            </h3>
            <p class="card-text" style="margin-bottom:16px;">
              <?= htmlspecialchars(substr($ann['content'], 0, 130)) ?>...
            </p>
            <a href="login.php" style="color:#60a5fa; font-size:0.85rem; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
              Baca Selengkapnya
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" style="grid-column: span 3; background: rgba(15, 23, 42, 0.6); color: #fff; border: 1px solid rgba(255, 255, 255, 0.1);">
          <h3 style="color:#fff;">Belum ada pengumuman</h3>
          <p style="color:#cbd5e1;">Informasi terbaru dari universitas akan ditampilkan di bagian ini.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- CALL TO ACTION PORTAL -->
  <div style="background: rgba(11, 17, 32, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); color:#fff; padding: 70px 24px; text-align: center; border-top: 1px solid rgba(255,255,255,0.12); border-bottom: 1px solid rgba(255,255,255,0.12); position: relative; z-index: 5;">
    <div style="max-width: 800px; margin: 0 auto;">
      <h2 style="font-size: 2.1rem; font-weight: 800; margin-bottom: 14px; text-shadow: 0 4px 16px rgba(0,0,0,0.5);">Akses Layanan Akademik Anda Secara Mudah</h2>
      <p style="color: #cbd5e1; font-size: 1rem; margin-bottom: 28px; line-height: 1.6;">
        Masuk ke Portal SIAKAD untuk mengakses layanan akademik mahasiswa, pengelolaan program studi, maupun pusat kendali administrator universitas.
      </p>
      <a href="login.php" class="btn btn-primary btn-lg" style="padding: 14px 32px; font-weight:700; box-shadow: 0 10px 25px rgba(29, 78, 216, 0.4);">
        Login ke Portal SIAKAD
      </a>
    </div>
  </div>

  <!-- FOOTER -->
  <footer id="kontak" class="landing-footer" style="position: relative; z-index: 5; background: #020617; border-top: 1px solid rgba(255,255,255,0.1);">
    <div class="footer-container">
      <div class="footer-brand">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
          <img src="assets/img/logo-universitas.svg" alt="Logo" style="width:40px; height:40px;">
          <h2 style="margin:0; font-size:1.15rem; color:#fff;">Universitas Nusantara Mandiri</h2>
        </div>
        <p style="font-size:0.88rem; line-height:1.6; color:#94a3b8; max-width:380px;">
          Kampus Utama: Jl. Danau Sentani Raya No. 99, Kawasan Pendidikan Terpadu, Jakarta Selatan 12340.<br>
          Telp: (021) 7890-1234 | Fax: (021) 7890-5678<br>
          Email: info@univ-nusantara.ac.id
        </p>
      </div>

      <div class="footer-links">
        <h3>Layanan Portal</h3>
        <ul>
          <li><a href="login.php">Login SIAKAD (Semua Role)</a></li>
          <li><a href="login.php">Pengisian KRS Online</a></li>
          <li><a href="login.php">Transkrip Nilai</a></li>
          <li><a href="login.php">Biro Administrasi BAAK</a></li>
        </ul>
      </div>

      <div class="footer-links">
        <h3>Fakultas</h3>
        <ul>
          <li><a href="#fakultas">Teknologi Informasi</a></li>
          <li><a href="#fakultas">Ekonomi dan Bisnis</a></li>
          <li><a href="#fakultas">Teknik dan Desain</a></li>
          <li><a href="#fakultas">Pascasarjana</a></li>
        </ul>
      </div>

      <div class="footer-links">
        <h3>Bantuan & Kontak</h3>
        <ul>
          <li><a href="login.php">Helpdesk SIAKAD</a></li>
          <li><a href="#">Panduan Mahasiswa</a></li>
          <li><a href="#">Kalender Akademik</a></li>
          <li><a href="#">Lapor Kendala Teknis</a></li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom">
      <div>&copy; <?= date('Y') ?> Universitas Nusantara Mandiri. All rights reserved.</div>
      <div style="display:flex; gap:20px;">
        <a href="#">Kebijakan Privasi</a>
        <a href="#">Ketentuan Layanan</a>
        <a href="#">Standar Keamanan Informasi</a>
      </div>
    </div>
  </footer>

  <!-- ========================================================
       PARALLAX SCROLL & ON-SCROLL DYNAMIC MOTION SCRIPT
       ======================================================== -->
  <script>
    (function() {
      const siteFullPhotoImg = document.getElementById('siteFullPhotoImg');
      const heroContent = document.getElementById('heroContent');
      const scrollIndicator = document.getElementById('heroScrollIndicator');

      let ticking = false;

      function updateParallax() {
        const scrollY = window.pageYOffset || document.documentElement.scrollTop;

        // 1. Full-page continuous parallax for the background photo
        // As user scrolls down the page, the photo glides smoothly with a slower translation rate
        // giving continuous motion from the navbar all the way through the bottom!
        const translateY = scrollY * 0.22;
        const scale = 1.05 + (scrollY * 0.00015);
        if (siteFullPhotoImg) {
          siteFullPhotoImg.style.transform = `translate3d(0, ${translateY}px, 0) scale(${scale})`;
        }

        // 2. Gentle upward drift and smooth fade for the hero text card
        if (heroContent) {
          const contentTranslateY = -scrollY * 0.16;
          const opacity = Math.max(0, 1 - (scrollY / 600));
          heroContent.style.transform = `translate3d(0, ${contentTranslateY}px, 0)`;
          heroContent.style.opacity = opacity;
        }

        // 3. Fade out the scroll mouse indicator quickly
        if (scrollIndicator) {
          scrollIndicator.style.opacity = Math.max(0, 1 - (scrollY / 140));
        }

        ticking = false;
      }

      window.addEventListener('scroll', function() {
        if (!ticking) {
          window.requestAnimationFrame(updateParallax);
          ticking = true;
        }
      }, { passive: true });

      // Run once on load to set initial state
      updateParallax();

      // On-scroll reveal animations for content sections
      const revealItems = document.querySelectorAll('.reveal-item');
      if ('IntersectionObserver' in window && revealItems.length > 0) {
        const observer = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              entry.target.classList.add('revealed');
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        revealItems.forEach((el, idx) => {
          el.style.transitionDelay = `${(idx % 4) * 0.1}s`;
          observer.observe(el);
        });
      } else {
        revealItems.forEach(el => el.classList.add('revealed'));
      }
    })();
  </script>

</body>
</html>
