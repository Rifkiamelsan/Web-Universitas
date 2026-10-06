<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Proteksi akses role Program Studi
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'prodi') {
    echo json_encode(['success' => false, 'message' => 'Sesi login tidak sah atau bukan pengelola Program Studi.']);
    exit;
}

$pdo = getDBConnection();
$prodi = getLoggedInProdi();

if (!$prodi) {
    echo json_encode(['success' => false, 'message' => 'Akun Prodi Anda belum terhubung dengan Program Studi mana pun.']);
    exit;
}

$studyProgramId = (int)$prodi['study_program_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // 1. SETUJUI KRS MAHASISWA
    case 'approve_krs':
        $krsId = (int)($_POST['krs_id'] ?? 0);
        $note = trim($_POST['note'] ?? 'KRS telah diverifikasi dan disetujui oleh Ketua Program Studi.');

        if ($krsId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID KRS tidak valid.']);
            exit;
        }

        try {
            // Verifikasi bahwa mahasiswa KRS ini memang dari prodi ini
            $stmt = $pdo->prepare("
                SELECT k.*, s.name AS student_name, s.nim, s.id AS student_id, s.study_program_id 
                FROM krs k
                JOIN students s ON k.student_id = s.id
                WHERE k.id = ? AND s.study_program_id = ? LIMIT 1
            ");
            $stmt->execute([$krsId, $studyProgramId]);
            $krs = $stmt->fetch();

            if (!$krs) {
                echo json_encode(['success' => false, 'message' => 'KRS tidak ditemukan atau berada di luar program studi Anda.']);
                exit;
            }

            $update = $pdo->prepare("
                UPDATE krs 
                SET status = 'Disetujui', approved_at = NOW(), note = ? 
                WHERE id = ?
            ");
            $update->execute([$note, $krsId]);

            // Kirim notifikasi ke Mahasiswa
            $notif = $pdo->prepare("
                INSERT INTO notifications (student_id, title, message, type, is_read)
                VALUES (?, 'KRS Telah Disetujui Prodi', ?, 'krs', 0)
            ");
            $notif->execute([$krs['student_id'], "KRS Anda untuk semester berjalan telah diverifikasi dan disetujui oleh Program Studi {$prodi['study_program_name']}."]);

            // Catat ke Audit Log
            logAuditActivity($pdo, 'Persetujuan KRS', 'krs', $krsId, "Menyetujui KRS Mahasiswa {$krs['student_name']} (NIM {$krs['nim']})");

            echo json_encode([
                'success' => true,
                'message' => "KRS Mahasiswa {$krs['student_name']} ({$krs['nim']}) berhasil disetujui."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyetujui KRS: ' . $e->getMessage()]);
        }
        break;

    // 2. TOLAK KRS MAHASISWA DENGAN CATATAN PENOLAKAN
    case 'reject_krs':
        $krsId = (int)($_POST['krs_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if ($krsId <= 0 || empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Alasan penolakan KRS wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT k.*, s.name AS student_name, s.nim, s.id AS student_id 
                FROM krs k
                JOIN students s ON k.student_id = s.id
                WHERE k.id = ? AND s.study_program_id = ? LIMIT 1
            ");
            $stmt->execute([$krsId, $studyProgramId]);
            $krs = $stmt->fetch();

            if (!$krs) {
                echo json_encode(['success' => false, 'message' => 'KRS tidak ditemukan atau berada di luar program studi Anda.']);
                exit;
            }

            $update = $pdo->prepare("
                UPDATE krs 
                SET status = 'Ditolak', note = ? 
                WHERE id = ?
            ");
            $update->execute(["Ditolak Prodi: " . $reason, $krsId]);

            // Notifikasi ke Mahasiswa
            $notif = $pdo->prepare("
                INSERT INTO notifications (student_id, title, message, type, is_read)
                VALUES (?, 'KRS Ditolak Program Studi', ?, 'krs', 0)
            ");
            $notif->execute([$krs['student_id'], "KRS Anda ditolak oleh Program Studi. Catatan: " . $reason]);

            // Audit Log
            logAuditActivity($pdo, 'Penolakan KRS', 'krs', $krsId, "Menolak KRS Mahasiswa {$krs['student_name']} (NIM {$krs['nim']}). Alasan: {$reason}");

            echo json_encode([
                'success' => true,
                'message' => "KRS Mahasiswa {$krs['student_name']} telah ditolak dan mahasiswa telah dinotifikasi."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal memproses penolakan: ' . $e->getMessage()]);
        }
        break;

    // 3. TAMBAH MAHASISWA BARU KE PROGRAM STUDI INI
    case 'add_student':
        $nim = trim($_POST['nim'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Laki-laki');
        $placeOfBirth = trim($_POST['place_of_birth'] ?? 'Jakarta');
        $dateOfBirth = trim($_POST['date_of_birth'] ?? '2004-01-01');
        $address = trim($_POST['address'] ?? '-');
        $semester = (int)($_POST['semester'] ?? 1);
        $entryYear = (int)($_POST['entry_year'] ?? date('Y'));
        $advisorName = trim($_POST['advisor_name'] ?? $prodi['head_of_program']);

        if (empty($nim) || empty($name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'NIM, Nama, dan Email wajib diisi.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Cek apakah NIM sudah dipakai
            $checkNim = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $checkNim->execute([$nim]);
            if ($checkNim->fetch()) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => "NIM '{$nim}' sudah terdaftar dalam sistem."]);
                exit;
            }

            // Buat akun user
            $hash = password_hash('mahasiswa123', PASSWORD_BCRYPT);
            $userStmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'mahasiswa', 1)");
            $userStmt->execute([$nim, $hash]);
            $newUserId = $pdo->lastInsertId();

            // Buat record student
            $stdStmt = $pdo->prepare("
                INSERT INTO students (
                    user_id, nim, name, place_of_birth, date_of_birth, gender, address, 
                    phone, email, faculty_id, study_program_id, semester, entry_year, 
                    advisor_name, status, avatar, gpa_last_sem, gpa_cumulative, total_credits
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif', 'assets/img/avatar-default.svg', 0.00, 0.00, 0)
            ");
            $stdStmt->execute([
                $newUserId, $nim, $name, $placeOfBirth, $dateOfBirth, $gender, $address,
                $phone, $email, $prodi['faculty_id'], $studyProgramId, $semester, $entryYear, $advisorName
            ]);
            $newStudentId = $pdo->lastInsertId();

            // Audit log
            logAuditActivity($pdo, 'Tambah Mahasiswa', 'students', $newStudentId, "Menambahkan mahasiswa baru: {$name} ({$nim}) ke Prodi {$prodi['study_program_name']}");

            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => "Mahasiswa {$name} ({$nim}) berhasil ditambahkan ke Program Studi {$prodi['study_program_name']}."
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan mahasiswa: ' . $e->getMessage()]);
        }
        break;

    // 4. UBAH STATUS AKADEMIK MAHASISWA
    case 'update_student_status':
        $studentId = (int)($_POST['student_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Aktif');

        $allowedStatuses = ['Aktif', 'Cuti', 'Lulus', 'Non-Aktif'];
        if ($studentId <= 0 || !in_array($status, $allowedStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Data status mahasiswa tidak valid.']);
            exit;
        }

        try {
            // Verifikasi mahasiswa prodi ini
            $stmt = $pdo->prepare("SELECT id, name, nim, status FROM students WHERE id = ? AND study_program_id = ?");
            $stmt->execute([$studentId, $studyProgramId]);
            $std = $stmt->fetch();

            if (!$std) {
                echo json_encode(['success' => false, 'message' => 'Mahasiswa tidak ditemukan di program studi ini.']);
                exit;
            }

            $oldStatus = $std['status'];
            $upd = $pdo->prepare("UPDATE students SET status = ? WHERE id = ?");
            $upd->execute([$status, $studentId]);

            // Audit log
            logAuditActivity($pdo, 'Ubah Status Mahasiswa', 'students', $studentId, "Mengubah status mahasiswa {$std['name']} ({$std['nim']}) dari {$oldStatus} menjadi {$status}");

            echo json_encode([
                'success' => true,
                'message' => "Status akademik mahasiswa {$std['name']} ({$std['nim']}) berhasil diubah menjadi '{$status}'."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah status: ' . $e->getMessage()]);
        }
        break;

    // 5. TAMBAH DOSEN KE PROGRAM STUDI
    case 'add_lecturer':
        $nidn = trim($_POST['nidn'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $degree = trim($_POST['degree'] ?? 'M.Kom.');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $coursesTaught = trim($_POST['courses_taught'] ?? '');

        if (empty($nidn) || empty($name) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'NIDN, Nama, dan Email dosen wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO lecturers (faculty_id, study_program_id, nidn, name, academic_degree, email, phone, status, courses_taught)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Aktif', ?)
            ");
            $stmt->execute([$prodi['faculty_id'], $studyProgramId, $nidn, $name, $degree, $email, $phone, $coursesTaught]);

            logAuditActivity($pdo, 'Tambah Dosen', 'lecturers', $pdo->lastInsertId(), "Menambahkan dosen: {$name}, {$degree} (NIDN {$nidn})");

            echo json_encode([
                'success' => true,
                'message' => "Dosen {$name}, {$degree} berhasil ditambahkan ke Program Studi."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan dosen: ' . $e->getMessage()]);
        }
        break;

    // 6. TAMBAH MATA KULIAH PRODI
    case 'add_course':
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $credits = (int)($_POST['credits'] ?? 3);
        $semester = (int)($_POST['semester'] ?? 1);
        $courseType = trim($_POST['course_type'] ?? 'Wajib');
        $prereq = trim($_POST['prerequisites'] ?? '-');

        if (empty($code) || empty($name) || $credits <= 0) {
            echo json_encode(['success' => false, 'message' => 'Kode MK, Nama MK, dan SKS wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO courses (study_program_id, code, name, credits, semester, course_type, prerequisites, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$studyProgramId, $code, $name, $credits, $semester, $courseType, $prereq]);

            logAuditActivity($pdo, 'Tambah Mata Kuliah', 'courses', $pdo->lastInsertId(), "Menambahkan MK: {$code} - {$name} ({$credits} SKS)");

            echo json_encode([
                'success' => true,
                'message' => "Mata kuliah {$name} ({$code}) berhasil ditambahkan ke kurikulum."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menambahkan mata kuliah: ' . $e->getMessage()]);
        }
        break;

    // 7. TAMBAH JADWAL KULIAH DENGAN DETEKSI BENTROK
    case 'add_schedule':
        $courseId = (int)($_POST['course_id'] ?? 0);
        $className = trim($_POST['class_name'] ?? 'A');
        $lecturerName = trim($_POST['lecturer_name'] ?? '');
        $day = trim($_POST['day'] ?? 'Senin');
        $startTime = trim($_POST['start_time'] ?? '08:00');
        $endTime = trim($_POST['end_time'] ?? '10:30');
        $room = trim($_POST['room'] ?? 'Lab Software R.302');
        $quota = (int)($_POST['quota'] ?? 40);

        if ($courseId <= 0 || empty($lecturerName)) {
            echo json_encode(['success' => false, 'message' => 'Mata kuliah dan Dosen pengampu wajib dipilih.']);
            exit;
        }

        try {
            // DETEKSI BENTROK 1: Ruangan sama pada hari & jam beririsan
            $roomConflict = $pdo->prepare("
                SELECT cs.*, c.name AS course_name FROM course_schedules cs
                JOIN courses c ON cs.course_id = c.id
                WHERE cs.day = ? AND cs.room = ? AND cs.academic_year_id = 3
                AND (cs.start_time < ? AND cs.end_time > ?)
                LIMIT 1
            ");
            $roomConflict->execute([$day, $room, $endTime, $startTime]);
            $conflictRoom = $roomConflict->fetch();

            if ($conflictRoom) {
                echo json_encode([
                    'success' => false,
                    'message' => "Peringatan Bentrok Ruangan! Ruangan '{$room}' pada hari {$day} pukul {$startTime}-{$endTime} sudah dipakai oleh mata kuliah {$conflictRoom['course_name']} ({$conflictRoom['start_time']} - {$conflictRoom['end_time']})."
                ]);
                exit;
            }

            // DETEKSI BENTROK 2: Dosen sama mengajar di tempat lain pada jam yang sama
            $lecturerConflict = $pdo->prepare("
                SELECT cs.*, c.name AS course_name FROM course_schedules cs
                JOIN courses c ON cs.course_id = c.id
                WHERE cs.day = ? AND cs.lecturer_name = ? AND cs.academic_year_id = 3
                AND (cs.start_time < ? AND cs.end_time > ?)
                LIMIT 1
            ");
            $lecturerConflict->execute([$day, $lecturerName, $endTime, $startTime]);
            $conflictLec = $lecturerConflict->fetch();

            if ($conflictLec) {
                echo json_encode([
                    'success' => false,
                    'message' => "Peringatan Bentrok Dosen! Dosen '{$lecturerName}' sudah memiliki jadwal mengajar mata kuliah {$conflictLec['course_name']} di ruang {$conflictLec['room']} pada jam yang sama."
                ]);
                exit;
            }

            // Simpan Jadwal
            $ins = $pdo->prepare("
                INSERT INTO course_schedules (course_id, academic_year_id, class_name, lecturer_name, day, start_time, end_time, room, quota, enrolled)
                VALUES (?, 3, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $ins->execute([$courseId, $className, $lecturerName, $day, $startTime, $endTime, $room, $quota]);

            logAuditActivity($pdo, 'Buat Jadwal Kuliah', 'course_schedules', $pdo->lastInsertId(), "Membuat jadwal kelas {$className} di {$room} pada {$day} {$startTime}-{$endTime}");

            echo json_encode([
                'success' => true,
                'message' => "Jadwal kuliah berhasil dibuat tanpa bentrok!"
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal membuat jadwal: ' . $e->getMessage()]);
        }
        break;

    // 8. PROSES LAYANAN AKADEMIK MAHASISWA
    case 'process_service':
        $requestId = (int)($_POST['request_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'Selesai');
        $adminNote = trim($_POST['admin_note'] ?? '');

        if ($requestId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Layanan tidak valid.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT sr.*, s.name AS student_name, s.nim, s.id AS student_id
                FROM service_requests sr
                JOIN students s ON sr.student_id = s.id
                WHERE sr.id = ? AND s.study_program_id = ? LIMIT 1
            ");
            $stmt->execute([$requestId, $studyProgramId]);
            $req = $stmt->fetch();

            if (!$req) {
                echo json_encode(['success' => false, 'message' => 'Permohonan layanan tidak ditemukan dalam program studi Anda.']);
                exit;
            }

            $upd = $pdo->prepare("
                UPDATE service_requests 
                SET status = ?, admin_note = ?, updated_at = NOW(), processed_by_user_id = ?
                WHERE id = ?
            ");
            $upd->execute([$newStatus, $adminNote, $_SESSION['user_id'], $requestId]);

            // Kirim notifikasi ke Mahasiswa
            $notif = $pdo->prepare("
                INSERT INTO notifications (student_id, title, message, type, is_read)
                VALUES (?, 'Pembaruan Pengajuan Layanan', ?, 'service', 0)
            ");
            $notif->execute([$req['student_id'], "Pengajuan '{$req['service_type']}' Anda kini berstatus: {$newStatus}. Catatan: {$adminNote}"]);

            logAuditActivity($pdo, 'Proses Layanan Akademik', 'service_requests', $requestId, "Mengubah status permohonan #SRV-{$requestId} ({$req['student_name']}) menjadi {$newStatus}");

            echo json_encode([
                'success' => true,
                'message' => "Permohonan layanan mahasiswa berhasil diperbarui menjadi status '{$newStatus}'."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal memproses layanan: ' . $e->getMessage()]);
        }
        break;

    // 9. BUAT PENGUMUMAN KHUSUS MAHASISWA PRODI INI
    case 'create_announcement':
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? 'Program Studi');

        if (empty($title) || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Judul dan isi pengumuman wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, category, published_at, target_role, target_study_program_id, is_pinned)
                VALUES (?, ?, ?, CURDATE(), 'Prodi', ?, 1)
            ");
            $stmt->execute([$title, $content, $category, $studyProgramId]);

            logAuditActivity($pdo, 'Buat Pengumuman Prodi', 'announcements', $pdo->lastInsertId(), "Membuat pengumuman: {$title}");

            echo json_encode([
                'success' => true,
                'message' => "Pengumuman berhasil dipublikasikan khusus untuk mahasiswa {$prodi['study_program_name']}."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mempublikasikan pengumuman: ' . $e->getMessage()]);
        }
        break;

    // 10. DETAIL MAHASISWA (GET COMPLETE ACADEMIC PROFILE)
    case 'get_student_detail':
        $studentId = (int)($_GET['student_id'] ?? 0);

        try {
            $stmt = $pdo->prepare("
                SELECT s.*, f.name AS faculty_name, sp.name AS study_program_name
                FROM students s
                JOIN faculties f ON s.faculty_id = f.id
                JOIN study_programs sp ON s.study_program_id = sp.id
                WHERE s.id = ? AND s.study_program_id = ? LIMIT 1
            ");
            $stmt->execute([$studentId, $studyProgramId]);
            $studentData = $stmt->fetch();

            if (!$studentData) {
                echo json_encode(['success' => false, 'message' => 'Data mahasiswa tidak ditemukan di prodi ini.']);
                exit;
            }

            // Ambil KRS semester ini
            $krsStmt = $pdo->prepare("
                SELECT k.*, ay.name AS academic_year_name 
                FROM krs k
                JOIN academic_years ay ON k.academic_year_id = ay.id
                WHERE k.student_id = ? AND k.academic_year_id = 3 LIMIT 1
            ");
            $krsStmt->execute([$studentId]);
            $krsData = $krsStmt->fetch();

            // Ambil Detail Mata Kuliah KRS
            $courses = [];
            if ($krsData) {
                $cStmt = $pdo->prepare("
                    SELECT cs.*, c.code, c.name, c.credits
                    FROM krs_details kd
                    JOIN course_schedules cs ON kd.course_schedule_id = cs.id
                    JOIN courses c ON cs.course_id = c.id
                    WHERE kd.krs_id = ?
                ");
                $cStmt->execute([$krsData['id']]);
                $courses = $cStmt->fetchAll();
            }

            echo json_encode([
                'success' => true,
                'student' => $studentData,
                'krs' => $krsData,
                'courses' => $courses
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil detail: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi API Prodi tidak dikenali.']);
        break;
}
