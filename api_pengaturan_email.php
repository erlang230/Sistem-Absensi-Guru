<?php
/**
 * API Pengaturan Email Sistem SAG
 * Mengelola penyimpanan konfigurasi SMTP Gmail dan pengujian kirim email
 * Terproteksi otentikasi sesi Admin, CSRF token, dan enkripsi OpenSSL AES-256
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';

// 1. Pemeriksaan Otorisasi: Khusus Administrator
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Fitur ini hanya dapat diakses oleh Administrator.'
    ]);
    exit;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// 2. Pemeriksaan CSRF Token untuk operasi POST (simpan & uji)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Sesi keamanan (CSRF Token) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.'
        ]);
        exit;
    }
}

switch ($action) {
    // -----------------------------------------------------------------
    // 1. GET STATUS PENGATURAN EMAIL (HANYA DATA AMAN NON-SENSITIF)
    // -----------------------------------------------------------------
    case 'get_status':
        $settings = SAGMailer::get_public_settings();
        echo json_encode([
            'success' => true,
            'data'    => $settings
        ]);
        break;

    // -----------------------------------------------------------------
    // 2. SIMPAN PENGATURAN EMAIL (ENKRIPSI AES-256-CBC)
    // -----------------------------------------------------------------
    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak diizinkan.']);
            exit;
        }

        $smtp_username = trim($_POST['smtp_username'] ?? '');
        $app_password  = trim($_POST['app_password'] ?? '');
        $from_name     = trim($_POST['from_name'] ?? 'Sistem Absensi Guru (SAG)');
        $from_email    = trim($_POST['from_email'] ?? $smtp_username);

        $result = SAGMailer::save_settings([
            'smtp_username' => $smtp_username,
            'app_password'  => $app_password,
            'from_name'     => $from_name,
            'from_email'    => $from_email
        ]);

        if ($result['success']) {
            // Catat log non-sensitif ke database
            SAGMailer::log_system_email(
                $conn,
                'notifikasi_lain',
                $smtp_username,
                true,
                'Konfigurasi email pengirim sistem diperbarui oleh Admin (ID: ' . (int)$_SESSION['user_id'] . ')',
                (int)$_SESSION['user_id']
            );
        }

        echo json_encode($result);
        break;

    // -----------------------------------------------------------------
    // 3. UJI KIRIM EMAIL (TEST SMTP CONNECTION & DELIVERY)
    // -----------------------------------------------------------------
    case 'test':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode HTTP tidak diizinkan.']);
            exit;
        }

        // Rate Limiting: Minimum 10 detik antar pengujian email untuk mencegah spam
        $lastTestTime = $_SESSION['last_email_test_timestamp'] ?? 0;
        $currentTime  = time();
        $cooldown     = 10;

        if (($currentTime - $lastTestTime) < $cooldown) {
            $remaining = $cooldown - ($currentTime - $lastTestTime);
            echo json_encode([
                'success' => false,
                'message' => "Mohon tunggu {$remaining} detik sebelum melakukan pengujian email kembali."
            ]);
            exit;
        }

        $testRecipient = trim($_POST['test_email'] ?? '');
        if (empty($testRecipient) || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Alamat email tujuan pengujian tidak valid. Harap periksa kembali format email.'
            ]);
            exit;
        }

        // Catat waktu pengujian untuk rate limiter
        $_SESSION['last_email_test_timestamp'] = $currentTime;

        // Eksekusi pengujian koneksi SMTP & pengiriman email
        $testResult = SAGMailer::test_connection($testRecipient, $conn, (int)$_SESSION['user_id']);

        echo json_encode($testResult);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aksi pengujian tidak dikenali.'
        ]);
        break;
}
