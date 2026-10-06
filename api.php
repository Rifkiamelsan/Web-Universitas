<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Proteksi sesi mahasiswa
if (!isset($_SESSION['user_id']) || !isset($_SESSION['student_id']) || $_SESSION['role'] !== 'mahasiswa') {
    echo json_encode(['success' => false, 'message' => 'Sesi login tidak sah atau telah berakhir.']);
    exit;
}

$pdo = getDBConnection();
$studentId = (int)$_SESSION['student_id'];
$userId = (int)$_SESSION['user_id'];

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {

    // 1. UPDATE DATA PRIBADI MAHASISWA
    case 'update_profile':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if (empty($phone) || empty($email) || empty($address)) {
            echo json_encode(['success' => false, 'message' => 'Nomor HP, Email, dan Alamat wajib diisi.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE students SET phone = ?, email = ?, address = ? WHERE id = ?");
            $stmt->execute([$phone, $email, $address, $studentId]);

            echo json_encode([
                'success' => true,
                'message' => 'Data kontak profil Anda berhasil diperbarui.'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui profil: ' . $e->getMessage()]);
        }
        break;

    // 2. TOGGLE MATA KULIAH KRS (TAMBAH / BATAL PILIH)
    case 'toggle_krs_course':
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        if ($scheduleId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Jadwal mata kuliah tidak valid.']);
            exit;
        }

        try {
            // Ambil KRS aktif mahasiswa
            $krsStmt = $pdo->prepare("SELECT * FROM krs WHERE student_id = ? AND academic_year_id = 3 LIMIT 1");
            $krsStmt->execute([$studentId]);
            $krs = $krsStmt->fetch();

            if (!$krs) {
                // Buat draft KRS baru jika belum ada
                $initKrs = $pdo->prepare("INSERT INTO krs (student_id, academic_year_id, total_credits, max_credits, status) VALUES (?, 3, 0, 24, 'Draft')");
                $initKrs->execute([$studentId]);
                $krsId = $pdo->lastInsertId();
                $krsStatus = 'Draft';
                $krsTotalCredits = 0;
                $krsMaxCredits = 24;
            } else {
                $krsId = $krs['id'];
                $krsStatus = $krs['status'];
                $krsTotalCredits = (int)$krs['total_credits'];
                $krsMaxCredits = (int)$krs['max_credits'];
            }

            // Kunci jika sudah diajukan atau disetujui
            if ($krsStatus === 'Disetujui' || $krsStatus === 'Diajukan') {
                echo json_encode([
                    'success' => false, 
                    'message' => "KRS Anda berstatus '{$krsStatus}'. Perubahan tidak diperkenankan sebelum periode revisi dibuka oleh BAAK."
                ]);
                exit;
            }

            // Cek detail jadwal & bobot SKS
            $schedStmt = $pdo->prepare("
                SELECT cs.*, c.name AS course_name, c.credits, c.id AS course_id 
                FROM course_schedules cs 
                JOIN courses c ON cs.course_id = c.id 
                WHERE cs.id = ? LIMIT 1
            ");
            $schedStmt->execute([$scheduleId]);
            $schedule = $schedStmt->fetch();

            if (!$schedule) {
                echo json_encode(['success' => false, 'message' => 'Jadwal kuliah tidak ditemukan.']);
                exit;
            }

            // Cek apakah jadwal ini sudah dipilih di krs_details
            $checkStmt = $pdo->prepare("SELECT id FROM krs_details WHERE krs_id = ? AND course_schedule_id = ?");
            $checkStmt->execute([$krsId, $scheduleId]);
            $existingDetail = $checkStmt->fetch();

            if ($existingDetail) {
                // BATALKAN MATA KULIAH (REMOVE)
                $delStmt = $pdo->prepare("DELETE FROM krs_details WHERE id = ?");
                $delStmt->execute([$existingDetail['id']]);

                // Kurangi kuota enrolled di jadwal
                $pdo->prepare("UPDATE course_schedules SET enrolled = GREATEST(0, enrolled - 1) WHERE id = ?")->execute([$scheduleId]);

                $newCredits = max(0, $krsTotalCredits - (int)$schedule['credits']);
                $pdo->prepare("UPDATE krs SET total_credits = ? WHERE id = ?")->execute([$newCredits, $krsId]);

                echo json_encode([
                    'success' => true,
                    'action' => 'removed',
                    'total_credits' => $newCredits,
                    'message' => "Mata kuliah {$schedule['course_name']} berhasil dibatalkan dari KRS."
                ]);
            } else {
                // TAMBAH MATA KULIAH (ADD)
                // 1. Cek apakah mata kuliah yang sama sudah diambil di kelas lain
                $duplicateCourseStmt = $pdo->prepare("
                    SELECT kd.id FROM krs_details kd
                    JOIN course_schedules cs ON kd.course_schedule_id = cs.id
                    WHERE kd.krs_id = ? AND cs.course_id = ?
                ");
                $duplicateCourseStmt->execute([$krsId, $schedule['course_id']]);
                if ($duplicateCourseStmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => "Mata kuliah {$schedule['course_name']} sudah ada dalam pilihan KRS Anda (tidak boleh mengambil ganda)."]);
                    exit;
                }

                // 2. Cek batas maksimal SKS
                if ($krsTotalCredits + (int)$schedule['credits'] > $krsMaxCredits) {
                    echo json_encode([
                        'success' => false, 
                        'message' => "Batas maksimal SKS Anda adalah {$krsMaxCredits} SKS. Total SKS yang dipilih melebihi kuota."
                    ]);
                    exit;
                }

                // Simpan ke krs_details
                $insertStmt = $pdo->prepare("INSERT INTO krs_details (krs_id, course_schedule_id) VALUES (?, ?)");
                $insertStmt->execute([$krsId, $scheduleId]);

                // Tambahkan kuota enrolled
                $pdo->prepare("UPDATE course_schedules SET enrolled = enrolled + 1 WHERE id = ?")->execute([$scheduleId]);

                $newCredits = $krsTotalCredits + (int)$schedule['credits'];
                $pdo->prepare("UPDATE krs SET total_credits = ? WHERE id = ?")->execute([$newCredits, $krsId]);

                echo json_encode([
                    'success' => true,
                    'action' => 'added',
                    'total_credits' => $newCredits,
                    'message' => "Mata kuliah {$schedule['course_name']} ({$schedule['credits']} SKS) berhasil ditambahkan ke KRS."
                ]);
            }

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem KRS: ' . $e->getMessage()]);
        }
        break;

    // 3. PENGAJUAN KRS
    case 'submit_krs':
        try {
            $krsStmt = $pdo->prepare("SELECT * FROM krs WHERE student_id = ? AND academic_year_id = 3 LIMIT 1");
            $krsStmt->execute([$studentId]);
            $krs = $krsStmt->fetch();

            if (!$krs || (int)$krs['total_credits'] === 0) {
                echo json_encode(['success' => false, 'message' => 'Anda belum memilih mata kuliah untuk diajukan.']);
                exit;
            }

            if ($krs['status'] === 'Disetujui') {
                echo json_encode(['success' => false, 'message' => 'KRS Anda sudah disetujui sebelumnya.']);
                exit;
            }

            $updateStmt = $pdo->prepare("
                UPDATE krs 
                SET status = 'Diajukan', submitted_at = NOW(), note = 'KRS telah diajukan dan sedang menunggu review Dosen PA.' 
                WHERE id = ?
            ");
            $updateStmt->execute([$krs['id']]);

            // Tambahkan notifikasi
            $notif = $pdo->prepare("
                INSERT INTO notifications (student_id, title, message, type, is_read) 
                VALUES (?, 'KRS Berhasil Diajukan', 'KRS Semester Ganjil 2024/2025 telah diajukan ke Dosen Pembimbing Akademik.', 'krs', 0)
            ");
            $notif->execute([$studentId]);

            echo json_encode([
                'success' => true,
                'status' => 'Diajukan',
                'message' => 'KRS Anda berhasil diajukan ke Dosen Pembimbing Akademik untuk diverifikasi.'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengajukan KRS: ' . $e->getMessage()]);
        }
        break;

    // 4. PENGAJUAN LAYANAN AKADEMIK BARU
    case 'request_service':
        $serviceType = trim($_POST['service_type'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if (empty($serviceType) || empty($purpose)) {
            echo json_encode(['success' => false, 'message' => 'Jenis layanan dan keperluan wajib diisi.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO service_requests (student_id, service_type, purpose, note, status, submitted_at) 
                VALUES (?, ?, ?, ?, 'Diajukan', NOW())
            ");
            $stmt->execute([$studentId, $serviceType, $purpose, $note]);

            // Buat notifikasi
            $pdo->prepare("
                INSERT INTO notifications (student_id, title, message, type, is_read) 
                VALUES (?, 'Pengajuan Layanan Baru', ?, 'service', 0)
            ")->execute([$studentId, "Permohonan {$serviceType} telah tercatat dan sedang dalam antrean BAAK."]);

            echo json_encode([
                'success' => true,
                'message' => 'Permohonan layanan akademik berhasil dikirimkan ke Biro BAAK.'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal membuat permohonan: ' . $e->getMessage()]);
        }
        break;

    // 5. PEMBAYARAN TAGIHAN (SIMULASI PEMBAYARAN INSTAN)
    case 'pay_invoice':
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $method = trim($_POST['payment_method'] ?? 'Bank Mandiri Virtual Account');

        if ($paymentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Tagihan tidak valid.']);
            exit;
        }

        try {
            // Verifikasi tagihan milik mahasiswa ini
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND student_id = ? LIMIT 1");
            $stmt->execute([$paymentId, $studentId]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                echo json_encode(['success' => false, 'message' => 'Tagihan tidak ditemukan atau bukan milik Anda.']);
                exit;
            }

            if ($invoice['status'] === 'Lunas') {
                echo json_encode(['success' => false, 'message' => 'Tagihan ini sudah lunas sebelumnya.']);
                exit;
            }

            $receipt = 'RCP/' . date('Ym') . '/' . sprintf('%05d', rand(1000, 99999));
            $update = $pdo->prepare("
                UPDATE payments 
                SET status = 'Lunas', paid_at = NOW(), payment_method = ?, receipt_number = ? 
                WHERE id = ?
            ");
            $update->execute([$method, $receipt, $paymentId]);

            // Buat notifikasi
            $pdo->prepare("
                INSERT INTO notifications (student_id, title, message, type, is_read) 
                VALUES (?, 'Pembayaran Tagihan Berhasil', ?, 'payment', 0)
            ")->execute([$studentId, "Pembayaran untuk {$invoice['invoice_type']} sebesar Rp " . number_format($invoice['amount'], 0, ',', '.') . " telah lunas."]);

            echo json_encode([
                'success' => true,
                'receipt_number' => $receipt,
                'message' => "Pembayaran berhasil diverifikasi. Nomor Kuitansi: {$receipt}"
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()]);
        }
        break;

    // 6. GANTI KATA SANDI MAHASISWA
    case 'change_password':
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($oldPass) || empty($newPass) || empty($confirmPass)) {
            echo json_encode(['success' => false, 'message' => 'Semua kolom kata sandi wajib diisi.']);
            exit;
        }

        if ($newPass !== $confirmPass) {
            echo json_encode(['success' => false, 'message' => 'Kata sandi baru dan konfirmasi kata sandi tidak cocok.']);
            exit;
        }

        if (strlen($newPass) < 6) {
            echo json_encode(['success' => false, 'message' => 'Kata sandi minimal 6 karakter.']);
            exit;
        }

        try {
            $userStmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $userStmt->execute([$userId]);
            $userData = $userStmt->fetch();

            if (!verifyUserPassword($oldPass, $userData['password'])) {
                echo json_encode(['success' => false, 'message' => 'Kata sandi lama yang Anda masukkan tidak sesuai.']);
                exit;
            }

            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->execute([$newHash, $userId]);

            echo json_encode([
                'success' => true,
                'message' => 'Kata sandi akun Anda telah berhasil diperbarui.'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengubah kata sandi: ' . $e->getMessage()]);
        }
        break;

    // 7. TANDAI NOTIFIKASI SUDAH DIBACA
    case 'mark_notifications_read':
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = ?");
            $stmt->execute([$studentId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
        break;
}
