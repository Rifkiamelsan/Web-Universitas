<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Proteksi akses Administrator
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: Hanya Administrator yang berwenang.']);
    exit;
}

$pdo = getDBConnection();
$adminId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // 1. MANAJEMEN PENGGUNA: TAMBAH USER
    case 'add_user':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'mahasiswa');
        $prodiId = (int)($_POST['study_program_id'] ?? 0);

        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username dan kata sandi wajib diisi.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => "Username '{$username}' sudah terdaftar."]);
                exit;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$username, $hash, $role]);
            $newUserId = $pdo->lastInsertId();

            if ($role === 'prodi' && $prodiId > 0) {
                $psu = $pdo->prepare("INSERT INTO program_study_users (user_id, study_program_id) VALUES (?, ?)");
                $psu->execute([$newUserId, $prodiId]);
            }

            logAuditActivity($pdo, 'Tambah Pengguna', 'users', $newUserId, "Admin membuat akun baru: {$username} dengan role {$role}");

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Pengguna '{$username}' ({$role}) berhasil didaftarkan."]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Gagal menambah pengguna: ' . $e->getMessage()]);
        }
        break;

    // 2. TOGGLE STATUS USER
    case 'toggle_user_status':
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = (int)($_POST['status'] ?? 1);

        if ($userId <= 0 || $userId === $adminId) {
            echo json_encode(['success' => false, 'message' => 'Tidak dapat mengubah status akun administrator yang sedang aktif.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $userId]);

            logAuditActivity($pdo, 'Ubah Status Akun', 'users', $userId, "Mengubah status aktif user ID {$userId} menjadi {$newStatus}");

            echo json_encode(['success' => true, 'message' => "Status akun berhasil diperbarui."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status: ' . $e->getMessage()]);
        }
        break;

    // 3. RESET PASSWORD PENGGUNA
    case 'reset_user_password':
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = trim($_POST['new_password'] ?? 'reset12345');

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Pengguna tidak valid.']);
            exit;
        }

        try {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);

            logAuditActivity($pdo, 'Reset Password Pengguna', 'users', $userId, "Admin melakukan reset kata sandi untuk user ID {$userId}");

            echo json_encode(['success' => true, 'message' => "Kata sandi pengguna berhasil direset menjadi '{$newPass}'."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mereset kata sandi: ' . $e->getMessage()]);
        }
        break;

    // 4. TAMBAH FAKULTAS
    case 'add_faculty':
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $dean = trim($_POST['dean'] ?? '');

        if (empty($code) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Kode dan nama fakultas wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO faculties (code, name, dean) VALUES (?, ?, ?)");
            $stmt->execute([$code, $name, $dean]);

            logAuditActivity($pdo, 'Tambah Fakultas', 'faculties', $pdo->lastInsertId(), "Menambahkan fakultas baru: {$name} ({$code})");

            echo json_encode(['success' => true, 'message' => "Fakultas '{$name}' berhasil ditambahkan."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menambah fakultas: ' . $e->getMessage()]);
        }
        break;

    // 5. TAMBAH PROGRAM STUDI
    case 'add_program_study':
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $degree = trim($_POST['degree'] ?? 'S1');
        $head = trim($_POST['head_of_program'] ?? '');

        if ($facultyId <= 0 || empty($code) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Fakultas, kode, dan nama program studi wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO study_programs (faculty_id, code, name, degree, head_of_program) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$facultyId, $code, $name, $degree, $head]);

            logAuditActivity($pdo, 'Tambah Program Studi', 'study_programs', $pdo->lastInsertId(), "Menambahkan prodi: {$name} ({$code})");

            echo json_encode(['success' => true, 'message' => "Program Studi '{$name}' berhasil didaftarkan."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menambah program studi: ' . $e->getMessage()]);
        }
        break;

    // 6. AKTIFKAN TAHUN AKADEMIK
    case 'set_active_academic_year':
        $yearId = (int)($_POST['year_id'] ?? 0);
        if ($yearId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Tahun Akademik tidak valid.']);
            exit;
        }

        try {
            $pdo->beginTransaction();
            $pdo->exec("UPDATE academic_years SET is_active = 0");
            $stmt = $pdo->prepare("UPDATE academic_years SET is_active = 1 WHERE id = ?");
            $stmt->execute([$yearId]);
            $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'academic_year_active'")->execute([$yearId]);

            logAuditActivity($pdo, 'Aktivasi Tahun Akademik', 'academic_years', $yearId, "Mengubah tahun akademik aktif ke ID {$yearId}");

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Tahun akademik aktif berhasil diperbarui."]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah tahun akademik: ' . $e->getMessage()]);
        }
        break;

    // 7. BUAT PENGUMUMAN TINGKAT UNIVERSITAS
    case 'create_university_announcement':
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? 'Akademik');
        $targetRole = trim($_POST['target_role'] ?? 'Semua');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;

        if (empty($title) || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Judul dan isi pengumuman universitas wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, category, published_at, target_role, is_pinned)
                VALUES (?, ?, ?, CURDATE(), ?, ?)
            ");
            $stmt->execute([$title, $content, $category, $targetRole, $isPinned]);

            logAuditActivity($pdo, 'Buat Pengumuman Universitas', 'announcements', $pdo->lastInsertId(), "Pengumuman: {$title} (Target: {$targetRole})");

            echo json_encode(['success' => true, 'message' => "Pengumuman universitas berhasil diterbitkan."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menerbitkan pengumuman: ' . $e->getMessage()]);
        }
        break;

    // 8. SIMPAN PENGATURAN SISTEM
    case 'save_system_settings':
        $univName    = trim($_POST['university_name'] ?? '');
        $univEmail   = trim($_POST['university_email'] ?? '');
        $univPhone   = trim($_POST['university_phone'] ?? '');
        $univAddress = trim($_POST['university_address'] ?? '');

        try {
            $upd = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
            if (!empty($univName))    $upd->execute([$univName,    'university_name']);
            if (!empty($univEmail))   $upd->execute([$univEmail,   'university_email']);
            if (!empty($univPhone))   $upd->execute([$univPhone,   'university_phone']);
            if (!empty($univAddress)) $upd->execute([$univAddress, 'university_address']);

            logAuditActivity($pdo, 'Ubah Pengaturan Sistem', 'system_settings', 1, "Memperbarui data identitas universitas.");

            echo json_encode(['success' => true, 'message' => 'Pengaturan konfigurasi sistem berhasil disimpan.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pengaturan: ' . $e->getMessage()]);
        }
        break;

    // ================================================================
    // CRUD MAHASISWA
    // ================================================================

    // 9. TAMBAH MAHASISWA BARU
    case 'add_student':
        $nim       = trim($_POST['nim'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $gender    = trim($_POST['gender'] ?? 'Laki-laki');
        $dob       = trim($_POST['date_of_birth'] ?? '');
        $pob       = trim($_POST['place_of_birth'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $prodiId   = (int)($_POST['study_program_id'] ?? 0);
        $semester  = (int)($_POST['semester'] ?? 1);
        $entryYear = (int)($_POST['entry_year'] ?? date('Y'));
        $advisor   = trim($_POST['advisor_name'] ?? '');
        $status    = trim($_POST['status'] ?? 'Aktif');
        $password  = !empty($_POST['password']) ? trim($_POST['password']) : $nim;

        if (empty($nim) || empty($name) || $facultyId <= 0 || $prodiId <= 0) {
            echo json_encode(['success' => false, 'message' => 'NIM, Nama, Fakultas, dan Prodi wajib diisi.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $chk = $pdo->prepare("SELECT id FROM students WHERE nim = ?");
            $chk->execute([$nim]);
            if ($chk->fetch()) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => "NIM '{$nim}' sudah terdaftar."]);
                exit;
            }

            $chkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chkUser->execute([$nim]);
            if ($chkUser->fetch()) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => "Username/NIM '{$nim}' sudah digunakan."]);
                exit;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $uStmt = $pdo->prepare("INSERT INTO users (username, password, role, is_active) VALUES (?, ?, 'mahasiswa', 1)");
            $uStmt->execute([$nim, $hash]);
            $newUserId = $pdo->lastInsertId();

            $sStmt = $pdo->prepare("
                INSERT INTO students
                  (user_id, nim, name, place_of_birth, date_of_birth, gender,
                   address, phone, email, faculty_id, study_program_id,
                   semester, entry_year, advisor_name, status, avatar,
                   gpa_last_sem, gpa_cumulative, total_credits)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'assets/img/avatar-default.svg',0.00,0.00,0)
            ");
            $sStmt->execute([
                $newUserId, $nim, $name, $pob, $dob, $gender,
                $address, $phone, $email, $facultyId, $prodiId,
                $semester, $entryYear, $advisor, $status
            ]);
            $newStudentId = $pdo->lastInsertId();

            logAuditActivity($pdo, 'Tambah Mahasiswa', 'students', $newStudentId,
                "Admin mendaftarkan mahasiswa: {$name} (NIM: {$nim})");

            $pdo->commit();
            echo json_encode(['success' => true,
                'message' => "Mahasiswa '{$name}' (NIM: {$nim}) berhasil didaftarkan. Password login: {$password}"]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Gagal mendaftarkan mahasiswa: ' . $e->getMessage()]);
        }
        break;

    // 10. EDIT DATA MAHASISWA
    case 'edit_student':
        $studentId = (int)($_POST['student_id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $gender    = trim($_POST['gender'] ?? '');
        $dob       = trim($_POST['date_of_birth'] ?? '');
        $pob       = trim($_POST['place_of_birth'] ?? '');
        $address   = trim($_POST['address'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $prodiId   = (int)($_POST['study_program_id'] ?? 0);
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $semester  = (int)($_POST['semester'] ?? 1);
        $advisor   = trim($_POST['advisor_name'] ?? '');
        $status    = trim($_POST['status'] ?? 'Aktif');
        $gpa       = (float)($_POST['gpa_cumulative'] ?? 0);
        $credits   = (int)($_POST['total_credits'] ?? 0);

        if ($studentId <= 0 || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'ID mahasiswa dan nama wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE students SET
                    name=?, place_of_birth=?, date_of_birth=?, gender=?,
                    address=?, phone=?, email=?,
                    study_program_id=?, faculty_id=?,
                    semester=?, advisor_name=?, status=?,
                    gpa_cumulative=?, total_credits=?
                WHERE id=?
            ");
            $stmt->execute([
                $name, $pob, $dob, $gender,
                $address, $phone, $email,
                $prodiId, $facultyId,
                $semester, $advisor, $status,
                $gpa, $credits,
                $studentId
            ]);

            logAuditActivity($pdo, 'Edit Mahasiswa', 'students', $studentId,
                "Admin update mahasiswa ID {$studentId}: {$name}, status={$status}");

            echo json_encode(['success' => true, 'message' => "Data mahasiswa '{$name}' berhasil diperbarui."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui: ' . $e->getMessage()]);
        }
        break;

    // 11. HAPUS MAHASISWA
    case 'delete_student':
        $studentId = (int)($_POST['student_id'] ?? 0);

        if ($studentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID mahasiswa tidak valid.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $row = $pdo->prepare("SELECT user_id, name, nim FROM students WHERE id=?");
            $row->execute([$studentId]);
            $student = $row->fetch();

            if (!$student) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Mahasiswa tidak ditemukan.']);
                exit;
            }

            $pdo->prepare("DELETE FROM krs_details WHERE krs_id IN (SELECT id FROM krs WHERE student_id=?)")->execute([$studentId]);
            $pdo->prepare("DELETE FROM krs WHERE student_id=?")->execute([$studentId]);
            $pdo->prepare("DELETE FROM grades WHERE student_id=?")->execute([$studentId]);
            $pdo->prepare("DELETE FROM payments WHERE student_id=?")->execute([$studentId]);
            $pdo->prepare("DELETE FROM notifications WHERE student_id=?")->execute([$studentId]);
            $pdo->prepare("DELETE FROM service_requests WHERE student_id=?")->execute([$studentId]);
            $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$studentId]);

            if ($student['user_id']) {
                $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$student['user_id']]);
            }

            logAuditActivity($pdo, 'Hapus Mahasiswa', 'students', $studentId,
                "Admin hapus mahasiswa: {$student['name']} (NIM: {$student['nim']})");

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Mahasiswa '{$student['name']}' berhasil dihapus dari sistem."]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
        }
        break;

    // 12. GET DATA MAHASISWA (untuk modal edit)
    case 'get_student':
        $studentId = (int)($_GET['student_id'] ?? 0);
        if ($studentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
            exit;
        }
        $row = $pdo->prepare("
            SELECT s.*, sp.name AS study_program_name, f.name AS faculty_name
            FROM students s
            JOIN study_programs sp ON s.study_program_id = sp.id
            JOIN faculties f ON s.faculty_id = f.id
            WHERE s.id = ?
        ");
        $row->execute([$studentId]);
        $s = $row->fetch();
        echo $s
            ? json_encode(['success' => true, 'data' => $s])
            : json_encode(['success' => false, 'message' => 'Tidak ditemukan.']);
        break;

    // ================================================================
    // CRUD DOSEN
    // ================================================================

    // 13. TAMBAH DOSEN BARU
    case 'add_lecturer':
        $nidn      = trim($_POST['nidn'] ?? '');
        $name      = trim($_POST['name'] ?? '');
        $degree    = trim($_POST['academic_degree'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $facultyId = (int)($_POST['faculty_id'] ?? 0);
        $prodiId   = (int)($_POST['study_program_id'] ?? 0);
        $status    = trim($_POST['status'] ?? 'Aktif');
        $courses   = trim($_POST['courses_taught'] ?? '');

        if (empty($nidn) || empty($name) || $facultyId <= 0 || $prodiId <= 0) {
            echo json_encode(['success' => false, 'message' => 'NIDN, Nama, Fakultas, dan Prodi wajib diisi.']);
            exit;
        }

        try {
            $chk = $pdo->prepare("SELECT id FROM lecturers WHERE nidn=?");
            $chk->execute([$nidn]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false, 'message' => "NIDN '{$nidn}' sudah terdaftar."]);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO lecturers (faculty_id, study_program_id, nidn, name, academic_degree, email, phone, status, courses_taught)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([$facultyId, $prodiId, $nidn, $name, $degree, $email, $phone, $status, $courses]);
            $newId = $pdo->lastInsertId();

            logAuditActivity($pdo, 'Tambah Dosen', 'lecturers', $newId,
                "Admin mendaftarkan dosen: {$name} (NIDN: {$nidn})");

            echo json_encode(['success' => true, 'message' => "Dosen '{$name}' berhasil didaftarkan."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mendaftarkan dosen: ' . $e->getMessage()]);
        }
        break;

    // 14. EDIT DATA DOSEN
    case 'edit_lecturer':
        $lecturerId = (int)($_POST['lecturer_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $degree     = trim($_POST['academic_degree'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $facultyId  = (int)($_POST['faculty_id'] ?? 0);
        $prodiId    = (int)($_POST['study_program_id'] ?? 0);
        $status     = trim($_POST['status'] ?? 'Aktif');
        $courses    = trim($_POST['courses_taught'] ?? '');

        if ($lecturerId <= 0 || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'ID dan nama wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE lecturers SET
                    name=?, academic_degree=?, email=?, phone=?,
                    faculty_id=?, study_program_id=?,
                    status=?, courses_taught=?
                WHERE id=?
            ");
            $stmt->execute([$name, $degree, $email, $phone, $facultyId, $prodiId, $status, $courses, $lecturerId]);

            logAuditActivity($pdo, 'Edit Dosen', 'lecturers', $lecturerId,
                "Admin update dosen ID {$lecturerId}: {$name}");

            echo json_encode(['success' => true, 'message' => "Data dosen '{$name}' berhasil diperbarui."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui: ' . $e->getMessage()]);
        }
        break;

    // 15. HAPUS DOSEN
    case 'delete_lecturer':
        $lecturerId = (int)($_POST['lecturer_id'] ?? 0);

        if ($lecturerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
            exit;
        }

        try {
            $row = $pdo->prepare("SELECT name, nidn FROM lecturers WHERE id=?");
            $row->execute([$lecturerId]);
            $lc = $row->fetch();

            if (!$lc) {
                echo json_encode(['success' => false, 'message' => 'Dosen tidak ditemukan.']);
                exit;
            }

            $pdo->prepare("DELETE FROM lecturers WHERE id=?")->execute([$lecturerId]);

            logAuditActivity($pdo, 'Hapus Dosen', 'lecturers', $lecturerId,
                "Admin hapus dosen: {$lc['name']} (NIDN: {$lc['nidn']})");

            echo json_encode(['success' => true, 'message' => "Dosen '{$lc['name']}' berhasil dihapus."]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
        }
        break;

    // 16. GET DATA DOSEN (untuk modal edit)
    case 'get_lecturer':
        $lecturerId = (int)($_GET['lecturer_id'] ?? 0);
        if ($lecturerId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
            exit;
        }
        $row = $pdo->prepare("SELECT * FROM lecturers WHERE id=?");
        $row->execute([$lecturerId]);
        $lc = $row->fetch();
        echo $lc
            ? json_encode(['success' => true, 'data' => $lc])
            : json_encode(['success' => false, 'message' => 'Tidak ditemukan.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi API Admin tidak valid.']);
        break;
}
