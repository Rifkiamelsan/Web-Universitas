-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: db_universitas
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_documents`
--

DROP TABLE IF EXISTS `academic_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `document_type` enum('KRS','KHS','Transkrip','Surat Keterangan','Kartu Ujian','Lainnya') NOT NULL,
  `issue_date` date NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('Tersedia','Siap Cetak','Diarsipkan') DEFAULT 'Tersedia',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `academic_documents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_documents`
--

LOCK TABLES `academic_documents` WRITE;
/*!40000 ALTER TABLE `academic_documents` DISABLE KEYS */;
INSERT INTO `academic_documents` VALUES (1,1,'Kartu Rencana Studi (KRS) Semester Ganjil 2024/2025','KRS','2024-09-03','docs/krs-ganjil-2024.pdf','Tersedia','2026-10-06 04:08:09'),(2,1,'Kartu Hasil Studi (KHS) Semester Genap 2023/2024','KHS','2024-07-15','docs/khs-genap-2023.pdf','Tersedia','2026-10-06 04:08:09'),(3,1,'Kartu Hasil Studi (KHS) Semester Ganjil 2023/2024','KHS','2024-02-10','docs/khs-ganjil-2023.pdf','Tersedia','2026-10-06 04:08:09'),(4,1,'Transkrip Nilai Akademik Sementara (Semester 1 - 4)','Transkrip','2024-08-20','docs/transkrip-sementara.pdf','Tersedia','2026-10-06 04:08:09'),(5,1,'Surat Keterangan Mahasiswa Aktif Kuliah (No. 412/BAAK/IX/2024)','Surat Keterangan','2024-09-08','docs/surat-aktif-kuliah.pdf','Tersedia','2026-10-06 04:08:09');
/*!40000 ALTER TABLE `academic_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `semester_type` enum('Ganjil','Genap') NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_years`
--

LOCK TABLES `academic_years` WRITE;
/*!40000 ALTER TABLE `academic_years` DISABLE KEYS */;
INSERT INTO `academic_years` VALUES (1,'2023-1','Tahun Akademik 2023/2024 Ganjil','Ganjil',0,'2023-09-01','2024-01-31','2026-10-06 04:08:09'),(2,'2023-2','Tahun Akademik 2023/2024 Genap','Genap',0,'2024-02-01','2024-06-30','2026-10-06 04:08:09'),(3,'2024-1','Tahun Akademik 2024/2025 Ganjil','Ganjil',1,'2024-09-01','2025-01-31','2026-10-06 04:08:09');
/*!40000 ALTER TABLE `academic_years` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` enum('Akademik','Keuangan','Kemahasiswaan','Fakultas','Program Studi','Beasiswa') NOT NULL,
  `published_at` date NOT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_role` enum('Semua','Mahasiswa','Dosen','Prodi') NOT NULL DEFAULT 'Semua',
  `target_study_program_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
INSERT INTO `announcements` VALUES (1,'Jadwal Ujian Tengah Semester (UTS) Semester Ganjil 2024/2025','Diberitahukan kepada seluruh mahasiswa bahwa pelaksanaan UTS Ganjil 2024/2025 akan dimulai pada 21 Oktober hingga 1 November 2024. Mahasiswa diwajibkan mencetak Kartu Ujian dan melunasi tagihan biaya kuliah semester berjalan sebelum tanggal 18 Oktober 2024.','Akademik','2024-10-01','Panduan-Pelaksanaan-UTS-2024.pdf',NULL,1,'2026-10-06 04:08:09','Semua',NULL),(2,'Pembukaan Pendaftaran Beasiswa Prestasi dan Unggulan Riset','Biro Kemahasiswaan membuka pendaftaran Beasiswa Prestasi Akademik Semester Genap. Syarat minimal IPK 3.50, aktif dalam kegiatan organisasi atau kompetisi ilmiah nasional.','Beasiswa','2024-09-25','Syarat-Ketentuan-Beasiswa.pdf',NULL,1,'2026-10-06 04:08:09','Semua',NULL),(3,'Pemeliharaan Server Portal SIAKAD Akhir Pekan','Akan dilakukan optimalisasi performa basis data pada hari Sabtu, 12 Oktober 2024 pukul 23:00 WIB hingga Minggu, 13 Oktober 2024 pukul 04:00 WIB. Layanan portal mungkin akan mengalami interupsi singkat.','Akademik','2024-09-20',NULL,NULL,0,'2026-10-06 04:08:09','Semua',NULL),(4,'Batas Akhir Validasi Pembayaran SPP/BPP Tahap I','Batas akhir pembayaran tagihan akademik Tahap I adalah 15 Oktober 2024. Mahasiswa yang belum menyelesaikan pembayaran tepat waktu tidak dapat mencetak Kartu Ujian.','Keuangan','2024-09-18','Surat-Edaran-Keuangan-2024.pdf',NULL,0,'2026-10-06 04:08:09','Semua',NULL);
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `role` varchar(30) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `target_entity` varchar(100) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,2,'admin','admin','Inisialisasi Sistem','system',1,'Konfigurasi awal pangkalan data dan hak akses role berhasil diaktifkan.','127.0.0.1','2026-10-06 04:41:10'),(2,3,'prodi_ti','prodi','Verifikasi KRS','krs',1,'Menyetujui KRS Semester Ganjil 2024/2025 Mahasiswa Muhammad Arya Pratama (NIM 202401001)','127.0.0.1','2026-10-06 04:41:10'),(3,3,'prodi_ti','prodi','Login Pengelola Prodi','users',3,'Pengelola Program Studi Teknik Informatika berhasil login.','::1','2026-10-06 04:47:07'),(4,2,'admin','admin','Login Administrator','users',2,'Administrator pusat berhasil masuk ke kendali sistem.','::1','2026-10-06 04:47:38'),(5,2,'admin','admin','Login Administrator','users',2,'Administrator pusat berhasil masuk ke kendali sistem.','::1','2026-10-06 04:49:24'),(6,3,'prodi_ti','prodi','Login Pengelola Prodi','users',3,'Pengelola Program Studi Teknik Informatika berhasil login.','::1','2026-10-06 04:50:05'),(7,3,'prodi_ti','prodi','Tambah Mahasiswa','students',6,'Menambahkan mahasiswa baru: reno (25233) ke Prodi Teknik Informatika','::1','2026-10-06 04:50:59'),(8,2,'admin','admin','Login Administrator','users',2,'Administrator pusat berhasil masuk ke kendali sistem.','::1','2026-10-06 04:52:57'),(9,3,'prodi_ti','prodi','Login Pengelola Prodi','users',3,'Pengelola Program Studi Teknik Informatika berhasil login.','::1','2026-10-06 04:57:16'),(10,2,'admin','admin','Login Administrator','users',2,'Administrator pusat berhasil masuk ke kendali sistem.','::1','2026-10-06 04:57:29'),(11,2,'admin','admin','Login Administrator','users',2,'Administrator pusat berhasil masuk ke kendali sistem.','::1','2026-10-06 05:06:52'),(12,2,'admin','admin','Login Administrator','users',2,'Administrator pusat berhasil masuk melalui Portal Terpadu.','::1','2026-10-06 05:14:29'),(13,1,'202401001','mahasiswa','Login Mahasiswa','users',1,'Mahasiswa Muhammad Arya Pratama berhasil masuk.','::1','2026-10-06 05:16:47'),(14,1,'202401001','mahasiswa','Login Mahasiswa','users',1,'Mahasiswa Muhammad Arya Pratama berhasil masuk.','::1','2026-10-06 05:17:53'),(15,3,'prodi_ti','prodi','Login Pengelola Prodi','users',3,'Pengelola Program Studi Teknik Informatika berhasil masuk.','::1','2026-10-06 05:17:59'),(16,2,'admin','admin','Login Administrator','users',2,'Administrator pusat berhasil masuk melalui Portal Terpadu.','::1','2026-10-06 05:18:04');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_schedules`
--

DROP TABLE IF EXISTS `course_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `class_name` varchar(20) NOT NULL DEFAULT 'A',
  `lecturer_name` varchar(150) NOT NULL,
  `day` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) NOT NULL,
  `quota` int(11) NOT NULL DEFAULT 40,
  `enrolled` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `course_schedules_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_schedules_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_schedules`
--

LOCK TABLES `course_schedules` WRITE;
/*!40000 ALTER TABLE `course_schedules` DISABLE KEYS */;
INSERT INTO `course_schedules` VALUES (1,3,3,'TI-5A','Dr. Ir. Hendra Setiawan, M.T.','Senin','08:00:00','11:20:00','Lab Software R.302',35,31,'2026-10-06 04:08:09'),(2,4,3,'TI-5A','Bambang Trianto, S.Kom., M.Cs.','Senin','13:00:00','15:30:00','Lab Komputasi R.204',35,29,'2026-10-06 04:08:09'),(3,5,3,'TI-5A','Dr. Maya Kartika, S.Si., M.Sc.','Selasa','09:00:00','11:30:00','Gedung B R.401',40,37,'2026-10-06 04:08:09'),(4,6,3,'TI-5B','Rizky Firmansyah, M.Kom.','Rabu','10:00:00','12:30:00','Gedung C R.105',35,29,'2026-10-06 04:08:09'),(5,7,3,'TI-5A','Anindya Putri, M.Sn.','Kamis','08:00:00','10:30:00','Lab Multimedia R.101',30,27,'2026-10-06 04:08:09'),(6,8,3,'TI-5A','Prof. Dr. Sri Wahyuni, M.Si.','Jumat','08:30:00','10:10:00','Auditorium Lt. 2',60,52,'2026-10-06 04:08:09'),(7,9,3,'TI-5B','Fajar Nugroho, M.T.','Jumat','13:30:00','16:00:00','Lab Jaringan R.303',30,24,'2026-10-06 04:08:09');
/*!40000 ALTER TABLE `course_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `study_program_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `credits` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `prerequisites` varchar(100) DEFAULT '-',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `course_type` enum('Wajib','Pilihan') NOT NULL DEFAULT 'Wajib',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `study_program_id` (`study_program_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,1,'TI2101','Algoritma & Struktur Data',3,3,'-','2026-10-06 04:08:09','Wajib',1),(2,1,'TI2102','Basis Data Lanjut',3,3,'TI1102','2026-10-06 04:08:09','Wajib',1),(3,1,'TI3101','Rekayasa Perangkat Lunak',4,5,'TI2101','2026-10-06 04:08:09','Wajib',1),(4,1,'TI3102','Pemrograman Web Modern',3,5,'-','2026-10-06 04:08:09','Wajib',1),(5,1,'TI3103','Kecerdasan Buatan & ML',3,5,'TI2101','2026-10-06 04:08:09','Wajib',1),(6,1,'TI3104','Keamanan Informasi & Jaringan',3,5,'-','2026-10-06 04:08:09','Wajib',1),(7,1,'TI3105','Interaksi Manusia & Komputer',3,5,'-','2026-10-06 04:08:09','Wajib',1),(8,1,'TI3106','Etika Profesi & Hukum Siber',2,5,'-','2026-10-06 04:08:09','Wajib',1),(9,1,'TI3107','Cloud Computing & DevOps',3,5,'TI2102','2026-10-06 04:08:09','Wajib',1);
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `curricula`
--

DROP TABLE IF EXISTS `curricula`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `curricula` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `study_program_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `year` int(11) NOT NULL,
  `total_credits` int(11) NOT NULL DEFAULT 144,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `study_program_id` (`study_program_id`),
  CONSTRAINT `curricula_ibfk_1` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `curricula`
--

LOCK TABLES `curricula` WRITE;
/*!40000 ALTER TABLE `curricula` DISABLE KEYS */;
INSERT INTO `curricula` VALUES (1,1,'Kurikulum MBKM Berbasis Industri AI & Software Engineering',2024,144,1,'2026-10-06 04:41:10'),(2,2,'Kurikulum Enterprise Information Systems & Analytics',2024,144,1,'2026-10-06 04:41:10');
/*!40000 ALTER TABLE `curricula` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `faculties`
--

DROP TABLE IF EXISTS `faculties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faculties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `dean` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `faculties`
--

LOCK TABLES `faculties` WRITE;
/*!40000 ALTER TABLE `faculties` DISABLE KEYS */;
INSERT INTO `faculties` VALUES (1,'FTI','Fakultas Teknologi Informasi','Dr. Ir. Hendra Setiawan, M.T.','2026-10-06 04:08:09'),(2,'FEB','Fakultas Ekonomi dan Bisnis','Prof. Dr. Sri Wahyuni, M.Si.','2026-10-06 04:08:09'),(3,'FTSP','Fakultas Teknik dan Desain','Dr. Raden Baskoro, M.Eng.','2026-10-06 04:08:09');
/*!40000 ALTER TABLE `faculties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grades`
--

DROP TABLE IF EXISTS `grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `semester` int(11) NOT NULL,
  `grade_letter` varchar(5) NOT NULL,
  `grade_point` decimal(3,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grades`
--

LOCK TABLES `grades` WRITE;
/*!40000 ALTER TABLE `grades` DISABLE KEYS */;
INSERT INTO `grades` VALUES (1,1,1,1,1,'A',4.00,'2026-10-06 04:08:09'),(2,1,2,2,2,'A-',3.75,'2026-10-06 04:08:09'),(3,1,3,3,3,'A',4.00,'2026-10-06 04:08:09'),(4,1,4,3,4,'B+',3.50,'2026-10-06 04:08:09'),(5,1,5,2,4,'A',4.00,'2026-10-06 04:08:09'),(6,1,6,2,4,'A-',3.75,'2026-10-06 04:08:09');
/*!40000 ALTER TABLE `grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `krs`
--

DROP TABLE IF EXISTS `krs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `krs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `total_credits` int(11) NOT NULL DEFAULT 0,
  `max_credits` int(11) NOT NULL DEFAULT 24,
  `status` enum('Draft','Diajukan','Disetujui','Ditolak') DEFAULT 'Draft',
  `submitted_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `krs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `krs_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `krs`
--

LOCK TABLES `krs` WRITE;
/*!40000 ALTER TABLE `krs` DISABLE KEYS */;
INSERT INTO `krs` VALUES (1,1,3,18,24,'Disetujui','2024-09-02 10:15:00','2024-09-03 14:00:00','KRS Semester Ganjil 2024/2025 telah diverifikasi & disetujui Dosen Pembimbing Akademik.','2026-10-06 04:08:09','2026-10-06 04:08:09');
/*!40000 ALTER TABLE `krs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `krs_details`
--

DROP TABLE IF EXISTS `krs_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `krs_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `krs_id` int(11) NOT NULL,
  `course_schedule_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `krs_id` (`krs_id`),
  KEY `course_schedule_id` (`course_schedule_id`),
  CONSTRAINT `krs_details_ibfk_1` FOREIGN KEY (`krs_id`) REFERENCES `krs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `krs_details_ibfk_2` FOREIGN KEY (`course_schedule_id`) REFERENCES `course_schedules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `krs_details`
--

LOCK TABLES `krs_details` WRITE;
/*!40000 ALTER TABLE `krs_details` DISABLE KEYS */;
INSERT INTO `krs_details` VALUES (1,1,1,'2026-10-06 04:08:09'),(2,1,2,'2026-10-06 04:08:09'),(3,1,3,'2026-10-06 04:08:09'),(4,1,4,'2026-10-06 04:08:09'),(5,1,5,'2026-10-06 04:08:09'),(6,1,6,'2026-10-06 04:08:09');
/*!40000 ALTER TABLE `krs_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lecturers`
--

DROP TABLE IF EXISTS `lecturers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lecturers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `faculty_id` int(11) NOT NULL,
  `study_program_id` int(11) NOT NULL,
  `nidn` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `academic_degree` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `status` enum('Aktif','Tidak Aktif','Cuti') NOT NULL DEFAULT 'Aktif',
  `courses_taught` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nidn` (`nidn`),
  UNIQUE KEY `email` (`email`),
  KEY `faculty_id` (`faculty_id`),
  KEY `study_program_id` (`study_program_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `lecturers_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`),
  CONSTRAINT `lecturers_ibfk_2` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`),
  CONSTRAINT `lecturers_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lecturers`
--

LOCK TABLES `lecturers` WRITE;
/*!40000 ALTER TABLE `lecturers` DISABLE KEYS */;
INSERT INTO `lecturers` VALUES (1,5,1,1,'0412057801','Dr. Ir. Hendra Setiawan','M.T.','hendra.setiawan@univ-nusantara.ac.id','081234567801','Aktif','Rekayasa Perangkat Lunak, Arsitektur Komputer','2026-10-06 04:41:10'),(2,NULL,1,1,'0415088202','Bambang Trianto','S.Kom., M.Cs.','bambang.trianto@univ-nusantara.ac.id','081234567802','Aktif','Pemrograman Web Modern, Algoritma & Pemrograman','2026-10-06 04:41:10'),(3,NULL,1,1,'0420118503','Dr. Maya Kartika','S.Si., M.Sc.','maya.kartika@univ-nusantara.ac.id','081234567803','Aktif','Kecerdasan Buatan & ML, Deep Learning','2026-10-06 04:41:10'),(4,NULL,1,1,'0425028904','Rizky Firmansyah','M.Kom.','rizky.f@univ-nusantara.ac.id','081234567804','Aktif','Keamanan Informasi & Jaringan, Cyber Ops','2026-10-06 04:41:10'),(5,NULL,1,1,'0410098705','Fajar Nugroho','M.T.','fajar.nugroho@univ-nusantara.ac.id','081234567805','Aktif','Cloud Computing & DevOps, Sistem Terdistribusi','2026-10-06 04:41:10'),(6,NULL,1,2,'0418048606','Dewi Anggraini','M.Kom.','dewi.a@univ-nusantara.ac.id','081234567806','Aktif','Manajemen Basis Data Enterprise, Analisis Proses Bisnis','2026-10-06 04:41:10'),(7,NULL,2,3,'0405067907','Hj. Ratna Sari','S.E., M.M.','ratna.sari@univ-nusantara.ac.id','081234567807','Aktif','Manajemen Strategis, Kewirausahaan','2026-10-06 04:41:10');
/*!40000 ALTER TABLE `lecturers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('krs','grade','payment','announcement','service','academic') DEFAULT 'academic',
  `is_read` tinyint(1) DEFAULT 0,
  `action_link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'KRS Semester Ganjil Disetujui','Pengajuan KRS Anda untuk Semester Ganjil 2024/2025 telah disetujui oleh Dosen Pembimbing Akademik.','krs',0,NULL,'2024-09-03 21:05:00'),(2,1,'Pembayaran Biaya Kuliah Berhasil','Pembayaran BPP/UKT Semester 5 sebesar Rp 5.500.000 telah terverifikasi secara otomatis oleh sistem.','payment',0,NULL,'2024-09-02 16:32:00'),(3,1,'Pengumuman Pelaksanaan UTS 2024','Jadwal dan tata tertib Ujian Tengah Semester Ganjil 2024/2025 telah dipublikasikan. Silakan periksa dokumen pengumuman.','announcement',1,NULL,'2024-10-01 17:00:00'),(4,1,'Pengajuan Layanan Diproses','Permohonan Legalisir Transkrip Nilai Akademik Anda saat ini sedang diproses oleh staf BAAK.','service',1,NULL,'2024-10-02 17:15:00');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `invoice_type` varchar(150) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('Belum Dibayar','Menunggu Pembayaran','Lunas','Terlambat') DEFAULT 'Belum Dibayar',
  `due_date` date NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `va_number` varchar(50) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,1,'Biaya Kuliah Pokok (BPP/UKT) Semester 5',3,5500000.00,'Lunas','2024-09-10','2024-09-02 09:30:15','Bank Mandiri Virtual Account','889210202401001','RCP/202409/00912','2026-10-06 04:08:09'),(2,1,'Praktikum & Laboratorium Terpadu',3,750000.00,'Lunas','2024-09-15','2024-09-02 09:35:22','Bank Mandiri Virtual Account','889210202401002','RCP/202409/00913','2026-10-06 04:08:09'),(3,1,'Iuran Kemahasiswaan & Asuransi Kesehatan',3,250000.00,'Lunas','2024-09-15','2024-09-02 09:37:05','Bank Mandiri Virtual Account','889210202401003','RCP/202409/00914','2026-10-06 04:08:09'),(4,1,'Biaya Ujian Sertifikasi Internasional (Opsional)',3,1200000.00,'Menunggu Pembayaran','2024-11-20',NULL,NULL,'889210202401004',NULL,'2026-10-06 04:08:09');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `program_study_users`
--

DROP TABLE IF EXISTS `program_study_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `program_study_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `study_program_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `study_program_id` (`study_program_id`),
  CONSTRAINT `program_study_users_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `program_study_users_ibfk_2` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `program_study_users`
--

LOCK TABLES `program_study_users` WRITE;
/*!40000 ALTER TABLE `program_study_users` DISABLE KEYS */;
INSERT INTO `program_study_users` VALUES (1,3,1,'2026-10-06 04:41:10'),(2,4,2,'2026-10-06 04:41:10');
/*!40000 ALTER TABLE `program_study_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_requests`
--

DROP TABLE IF EXISTS `service_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `service_type` varchar(150) NOT NULL,
  `purpose` text NOT NULL,
  `note` text DEFAULT NULL,
  `status` enum('Draft','Diajukan','Diproses','Disetujui','Ditolak','Selesai') DEFAULT 'Diajukan',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_note` text DEFAULT NULL,
  `study_program_id` int(11) DEFAULT NULL,
  `processed_by_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_requests`
--

LOCK TABLES `service_requests` WRITE;
/*!40000 ALTER TABLE `service_requests` DISABLE KEYS */;
INSERT INTO `service_requests` VALUES (1,1,'Surat Keterangan Aktif Kuliah','Keperluan pengurusan beasiswa instansi pemerintah dan kelengkapan BPJS Ketenagakerjaan orang tua.','Mohon dicetak dengan tanda tangan basah dan cap stempel BAAK.','Selesai','2024-09-05 11:20:00','2026-10-05 21:41:10','Dokumen telah selesai diverifikasi dan dapat diambil di loket BAAK Gedung Rektorat Lt. 1.',1,NULL),(2,1,'Legalisir Transkrip Nilai Akademik','Persyaratan pendaftaran magang bersertifikat MSIB Kemendikbudristek.','Dibutuhkan rangkap 3 lembar bertanda tangan Wakil Dekan I.','Diproses','2024-10-02 09:45:00','2026-10-05 21:41:10','Berkas sedang dalam tahap penandatanganan pimpinan fakultas.',1,NULL);
/*!40000 ALTER TABLE `service_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nim` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `place_of_birth` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Laki-laki','Perempuan') NOT NULL,
  `address` text NOT NULL,
  `phone` varchar(25) NOT NULL,
  `email` varchar(120) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `study_program_id` int(11) NOT NULL,
  `semester` int(11) NOT NULL DEFAULT 1,
  `entry_year` int(11) NOT NULL,
  `advisor_name` varchar(150) NOT NULL,
  `status` enum('Aktif','Cuti','Lulus','Non-Aktif') DEFAULT 'Aktif',
  `avatar` varchar(255) DEFAULT 'assets/img/avatar-default.svg',
  `gpa_last_sem` decimal(3,2) DEFAULT 0.00,
  `gpa_cumulative` decimal(3,2) DEFAULT 0.00,
  `total_credits` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `nim` (`nim`),
  UNIQUE KEY `email` (`email`),
  KEY `faculty_id` (`faculty_id`),
  KEY `study_program_id` (`study_program_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`),
  CONSTRAINT `students_ibfk_3` FOREIGN KEY (`study_program_id`) REFERENCES `study_programs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,1,'202401001','Muhammad Arya Pratama','Jakarta','2004-05-14','Laki-laki','Jl. Cendrawasih Raya No. 45, Kebayoran Baru, Jakarta Selatan','081298765432','arya.pratama@univ-nusantara.ac.id',1,1,5,2022,'Bambang Trianto, S.Kom., M.Cs.','Aktif','assets/img/student-avatar.svg',3.82,3.78,86,'2026-10-06 04:08:09','2026-10-06 04:08:09'),(2,6,'202401002','Siti Nurhaliza','Bandung','2004-08-19','Perempuan','Jl. Merdeka No. 12, Bandung','081298765402','siti.nurhaliza@univ-nusantara.ac.id',1,1,5,2022,'Dr. Maya Kartika, S.Si., M.Sc.','Aktif','assets/img/avatar-default.svg',3.90,3.85,88,'2026-10-06 04:41:10','2026-10-06 04:41:10'),(3,7,'202401003','Dimas Anggara','Surabaya','2003-12-04','Laki-laki','Jl. Pemuda No. 88, Jakarta Pusat','081298765403','dimas.anggara@univ-nusantara.ac.id',1,1,5,2022,'Bambang Trianto, S.Kom., M.Cs.','Cuti','assets/img/avatar-default.svg',3.40,3.45,68,'2026-10-06 04:41:10','2026-10-06 04:41:10'),(4,8,'202401004','Nabila Maharani','Semarang','2004-03-22','Perempuan','Jl. Pahlawan No. 25, Jakarta Selatan','081298765404','nabila.m@univ-nusantara.ac.id',1,1,3,2023,'Dr. Ir. Hendra Setiawan, M.T.','Aktif','assets/img/avatar-default.svg',3.65,3.70,48,'2026-10-06 04:41:10','2026-10-06 04:41:10'),(5,9,'202401005','Kevin Sanjaya','Yogyakarta','2004-07-11','Laki-laki','Jl. Kaliurang Km 5, Sleman','081298765405','kevin.s@univ-nusantara.ac.id',1,2,5,2022,'Dewi Anggraini, M.Kom.','Aktif','assets/img/avatar-default.svg',3.75,3.72,84,'2026-10-06 04:41:10','2026-10-06 04:41:10');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `study_programs`
--

DROP TABLE IF EXISTS `study_programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `study_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `degree` varchar(10) NOT NULL DEFAULT 'S1',
  `head_of_program` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `faculty_id` (`faculty_id`),
  CONSTRAINT `study_programs_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `study_programs`
--

LOCK TABLES `study_programs` WRITE;
/*!40000 ALTER TABLE `study_programs` DISABLE KEYS */;
INSERT INTO `study_programs` VALUES (1,1,'TI','Teknik Informatika','S1','Bambang Trianto, S.Kom., M.Cs.','2026-10-06 04:08:09'),(2,1,'SI','Sistem Informasi','S1','Dewi Anggraini, M.Kom.','2026-10-06 04:08:09'),(3,2,'MN','Manajemen Bisnis','S1','Hj. Ratna Sari, S.E., M.M.','2026-10-06 04:08:09'),(4,3,'DKV','Desain Komunikasi Visual','S1','Anindya Putri, M.Sn.','2026-10-06 04:08:09');
/*!40000 ALTER TABLE `study_programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'university_name','Universitas Nusantara Mandiri','2026-10-06 04:41:10'),(2,'university_code','UNM-041001','2026-10-06 04:41:10'),(3,'university_email','info@univ-nusantara.ac.id','2026-10-06 04:41:10'),(4,'university_phone','(021) 7890-1234','2026-10-06 04:41:10'),(5,'university_address','Jl. Danau Sentani Raya No. 99, Kawasan Pendidikan Terpadu, Jakarta Selatan 12340','2026-10-06 04:41:10'),(6,'academic_year_active','3','2026-10-06 04:41:10'),(7,'theme_primary_color','#0f172a','2026-10-06 04:41:10'),(8,'theme_accent_color','#1d4ed8','2026-10-06 04:41:10');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','prodi','dosen','mahasiswa') NOT NULL DEFAULT 'mahasiswa',
  `is_active` tinyint(1) DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'202401001','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','mahasiswa',1,NULL,'2026-10-05 22:17:53','2026-10-06 04:08:09'),(2,'admin','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','admin',1,NULL,'2026-10-05 22:18:04','2026-10-06 04:41:10'),(3,'prodi_ti','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','prodi',1,NULL,'2026-10-05 22:17:59','2026-10-06 04:41:10'),(4,'prodi_si','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','prodi',1,NULL,NULL,'2026-10-06 04:41:10'),(5,'dosen_hendra','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','dosen',1,NULL,NULL,'2026-10-06 04:41:10'),(6,'202401002','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','mahasiswa',1,NULL,NULL,'2026-10-06 04:41:10'),(7,'202401003','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','mahasiswa',1,NULL,NULL,'2026-10-06 04:41:10'),(8,'202401004','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','mahasiswa',1,NULL,NULL,'2026-10-06 04:41:10'),(9,'202401005','$2y$10$wT0lJkK5zZzXg/2JtJt90.6ZlWkP3OqjN6vO3ZzY1Y7F6e6h7oNGe','mahasiswa',1,NULL,NULL,'2026-10-06 04:41:10');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-05 22:18:31
