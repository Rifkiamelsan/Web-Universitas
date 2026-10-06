<?php
/**
 * ============================================================================
 * FILE KONFIGURASI KONEKSI DATABASE (koneksi.php)
 * ============================================================================
 * File ini dirancang siap pakai baik untuk Localhost (XAMPP/Laragon)
 * maupun Hosting CPanel, Plesk, VPS, Hostinger, Niagahoster, DomaiNesia, dll.
 * 
 * PANDUAN UPLOAD KE HOSTING:
 * 1. Buat database baru di cPanel (MySQL Database Wizard).
 * 2. Buat user database dan berikan Hak Akses Penuh (All Privileges).
 * 3. Import file database (.sql) melalui menu phpMyAdmin di hosting.
 * 4. Buka file koneksi.php ini di File Manager cPanel, lalu ubah 4 baris di bawah:
 *    - $db_host = 'localhost';  (Tetap 'localhost' untuk mayoritas hosting)
 *    - $db_user = 'nama_user_hosting';
 *    - $db_pass = 'password_user_hosting';
 *    - $db_name = 'nama_database_hosting';
 * ============================================================================
 */

// ----------------------------------------------------------------------------
// 1. PENGATURAN KONEKSI DATABASE
// ----------------------------------------------------------------------------
$db_host = '127.0.0.1';       // Server database (gunakan 'localhost' atau '127.0.0.1')
$db_user = 'root';            // Username MySQL (di hosting contoh: u123456_admin)
$db_pass = '';                // Password MySQL (di localhost XAMPP default kosong '')
$db_name = 'db_universitas';  // Nama database (di hosting contoh: u123456_siakad)
$db_port = '3306';            // Port standar MySQL

// Definisikan konstanta jika belum didefinisikan
if (!defined('DB_HOST')) define('DB_HOST', $db_host);
if (!defined('DB_USER')) define('DB_USER', $db_user);
if (!defined('DB_PASS')) define('DB_PASS', $db_pass);
if (!defined('DB_NAME')) define('DB_NAME', $db_name);
if (!defined('DB_PORT')) define('DB_PORT', $db_port);

// ----------------------------------------------------------------------------
// 2. KONEKSI PDO (Digunakan oleh seluruh sistem backend SIAKAD)
// ----------------------------------------------------------------------------
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Jika koneksi gagal, tampilkan pesan ramah pengguna
            die("<div style='font-family:sans-serif; max-width:600px; margin:50px auto; padding:24px; border:1px solid #fecaca; background:#fef2f2; border-radius:8px; color:#991b1b;'>"
                . "<h3 style='margin-top:0;'>Gagal Terhubung ke Database MySQL!</h3>"
                . "<p>Pastikan nama database, user, dan password pada file <code>koneksi.php</code> sudah benar.</p>"
                . "<p style='font-size:0.85rem; color:#b91c1c;'><strong>Detail Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>"
                . "</div>");
        }
    }
    return $pdo;
}

// Inisialisasi variabel $pdo agar langsung bisa dipakai
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    // handled inside getDBConnection
}

// ----------------------------------------------------------------------------
// 3. KONEKSI MYSQLI ($koneksi dan $conn untuk kompatibilitas script standar PHP)
// ----------------------------------------------------------------------------
$koneksi = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
if (!$koneksi) {
    // Jangan die jika PDO berhasil, tapi simpan error
    $mysqli_error_message = mysqli_connect_error();
} else {
    mysqli_set_charset($koneksi, "utf8mb4");
}
$conn = $koneksi; // Alias $conn
