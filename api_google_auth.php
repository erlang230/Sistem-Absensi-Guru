<?php
/**
 * API Penautan Akun Google & Verifikasi OTP Guru
 * Sistem Absensi Guru (SAG)
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';

// Pastikan user sudah login dan berstatus guru
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Anda harus login sebagai Guru untuk mengelola akun Google.'
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Ambil data guru yang sah dari session
$stmt_g = mysqli_prepare($conn, "SELECT id_guru, nama_guru, email FROM guru WHERE id_user = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_g, "i", $user_id);
mysqli_stmt_execute($stmt_g);
$res_g = mysqli_stmt_get_result($stmt_g);
$guru = mysqli_fetch_assoc($res_g);
mysqli_stmt_close($stmt_g);

if (!$guru) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Data guru tidak ditemukan.'
    ]);
    exit;
}

$id_guru = (int)$guru['id_guru'];
$nama_guru = $guru['nama_guru'];

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    // -------------------------------------------------------------
    // 1. GET STATUS AKUN GOOGLE GURU SAAT INI
    // -------------------------------------------------------------
    case 'get_status':
        $stmt_status = mysqli_prepare($conn, "SELECT email_google, status_tautan, verified_at FROM guru_google WHERE id_guru = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_status, "i", $id_guru);
        mysqli_stmt_execute($stmt_status);
        $res_status = mysqli_stmt_get_result($stmt_status);
        $data_google = mysqli_fetch_assoc($res_status);
        mysqli_stmt_close($stmt_status);

        // Cek juga apakah ada OTP aktif yang masih dalam masa berlaku
        $stmt_otp = mysqli_prepare($conn, "SELECT email_tujuan, expires_at, created_at FROM otp_verifikasi WHERE id_guru = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt_otp, "i", $id_guru);
        mysqli_stmt_execute($stmt_otp);
        $res_otp = mysqli_stmt_get_result($stmt_otp);
        $active_otp = mysqli_fetch_assoc($res_otp);
        mysqli_stmt_close($stmt_otp);

        $status = 'belum_terhubung';
        $email_terhubung = '';
        $verified_at = null;
        $pending_email = '';
        $remaining_seconds = 0;

        if ($data_google && $data_google['status_tautan'] === 'terhubung') {
            $status = 'terhubung';
            $email_terhubung = $data_google['email_google'];
            $verified_at = $data_google['verified_at'];
        } elseif ($active_otp) {
            $status = 'menunggu_verifikasi';
            $pending_email = $active_otp['email_tujuan'];
            $remaining_seconds = max(0, strtotime($active_otp['expires_at']) - time());
        }

        // Cek kesiapan konfigurasi SMTP
        $mail_check = SAGMailer::check_status();

        echo json_encode([
            'success' => true,
            'status' => $status,
            'email_google' => $email_terhubung,
            'verified_at' => $verified_at,
            'pending_email' => $pending_email,
            'remaining_seconds' => $remaining_seconds,
            'smtp_ready' => $mail_check['status'],
            'smtp_message' => $mail_check['message'] ?? ''
        ]);
        break;

    // -------------------------------------------------------------
    // 2. KIRIM KODE OTP
    // -------------------------------------------------------------
    case 'send_otp':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $email = strtolower(trim($_POST['email'] ?? ''));

        // Validasi format email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Alamat email tidak valid.']);
            exit;
        }

        // Pastikan domain adalah gmail.com atau domain google workspace
        $domain = substr(strrchr($email, "@"), 1);
        if (empty($domain)) {
            echo json_encode(['success' => false, 'message' => 'Format email Google tidak valid.']);
            exit;
        }

        // Validasi: Cek apakah email ini sudah ditautkan ke akun guru LAIN yang terhubung
        $stmt_check = mysqli_prepare($conn, "SELECT id_guru FROM guru_google WHERE email_google = ? AND status_tautan = 'terhubung' AND id_guru != ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_check, "si", $email, $id_guru);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            mysqli_stmt_close($stmt_check);
            echo json_encode([
                'success' => false,
                'message' => 'Alamat email Google ini sudah ditautkan ke akun guru lain. Harap gunakan alamat Gmail Anda sendiri.'
            ]);
            exit;
        }
        mysqli_stmt_close($stmt_check);

        // Rate Limiting 1: Jeda pengiriman ulang (cooldown 60 detik)
        $stmt_last = mysqli_prepare($conn, "SELECT created_at FROM otp_verifikasi WHERE id_guru = ? ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt_last, "i", $id_guru);
        mysqli_stmt_execute($stmt_last);
        $res_last = mysqli_stmt_get_result($stmt_last);
        if ($last = mysqli_fetch_assoc($res_last)) {
            $last_time = strtotime($last['created_at']);
            $diff = time() - $last_time;
            if ($diff < 60) {
                $wait = 60 - $diff;
                echo json_encode([
                    'success' => false,
                    'message' => "Harap tunggu {$wait} detik sebelum meminta kode OTP kembali."
                ]);
                exit;
            }
        }
        mysqli_stmt_close($stmt_last);

        // Rate Limiting 2: Batas maksimal 5 permintaan OTP per jam
        $stmt_limit = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM otp_verifikasi WHERE id_guru = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        mysqli_stmt_bind_param($stmt_limit, "i", $id_guru);
        mysqli_stmt_execute($stmt_limit);
        $res_limit = mysqli_stmt_get_result($stmt_limit);
        $limit_row = mysqli_fetch_assoc($res_limit);
        mysqli_stmt_close($stmt_limit);

        if (($limit_row['total'] ?? 0) >= 5) {
            echo json_encode([
                'success' => false,
                'message' => 'Anda telah mencapai batas maksimal permintaan OTP per jam (5 kali). Silakan coba lagi nanti.'
            ]);
            exit;
        }

        // Cek kesiapan konfigurasi SMTP sebelum membuat OTP
        $smtp_status = SAGMailer::check_status();
        if (!$smtp_status['status']) {
            echo json_encode([
                'success' => false,
                'message' => $smtp_status['message']
            ]);
            exit;
        }

        // Generate kode OTP 6-digit secara aman (Cryptographically Secure)
        $otp_code = (string)random_int(100000, 999999);
        $otp_hash = password_hash($otp_code, PASSWORD_BCRYPT);

        // Nonaktifkan kode OTP lama yang belum terpakai untuk guru ini
        $stmt_cancel = mysqli_prepare($conn, "UPDATE otp_verifikasi SET is_used = 1 WHERE id_guru = ? AND is_used = 0");
        mysqli_stmt_bind_param($stmt_cancel, "i", $id_guru);
        mysqli_stmt_execute($stmt_cancel);
        mysqli_stmt_close($stmt_cancel);

        // Simpan OTP ke database dengan masa berlaku 5 menit
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO otp_verifikasi (id_guru, email_tujuan, otp_hash, attempts, max_attempts, is_used, expires_at) VALUES (?, ?, ?, 0, 5, 0, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
        mysqli_stmt_bind_param($stmt_insert, "iss", $id_guru, $email, $otp_hash);
        
        if (!mysqli_stmt_execute($stmt_insert)) {
            mysqli_stmt_close($stmt_insert);
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem saat memproses token OTP.']);
            exit;
        }
        $otp_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_insert);

        // Kirim email via backend PHPMailer
        $send_result = SAGMailer::send_otp($email, $nama_guru, $otp_code);

        if (!$send_result['success']) {
            // Jika pengiriman email gagal, hapus record OTP agar tidak menggantung
            $stmt_del = mysqli_prepare($conn, "DELETE FROM otp_verifikasi WHERE id = ?");
            mysqli_stmt_bind_param($stmt_del, "i", $otp_id);
            mysqli_stmt_execute($stmt_del);
            mysqli_stmt_close($stmt_del);

            echo json_encode([
                'success' => false,
                'message' => $send_result['error']
            ]);
            exit;
        }

        // Berhasil dikirim — OTP TIDAK PERNAH dikembalikan ke frontend
        echo json_encode([
            'success' => true,
            'message' => 'Kode OTP 6-digit telah berhasil dikirim ke alamat email Anda. Silakan cek Kotak Masuk atau folder Spam.',
            'cooldown' => 60,
            'email' => $email
        ]);
        break;

    // -------------------------------------------------------------
    // 3. VERIFIKASI KODE OTP DAN TAUTKAN AKUN
    // -------------------------------------------------------------
    case 'verify_otp':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $otp_input = trim($_POST['otp'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (empty($otp_input) || strlen($otp_input) !== 6 || !ctype_digit($otp_input)) {
            echo json_encode(['success' => false, 'message' => 'Format kode OTP harus 6 digit angka.']);
            exit;
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Alamat email tidak valid.']);
            exit;
        }

        // Cari OTP aktif terbaru untuk guru dan email tersebut
        $stmt_find = mysqli_prepare($conn, "SELECT id, otp_hash, attempts, max_attempts, expires_at, is_used FROM otp_verifikasi WHERE id_guru = ? AND email_tujuan = ? AND is_used = 0 ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt_find, "is", $id_guru, $email);
        mysqli_stmt_execute($stmt_find);
        $res_find = mysqli_stmt_get_result($stmt_find);
        $otp_row = mysqli_fetch_assoc($res_find);
        mysqli_stmt_close($stmt_find);

        if (!$otp_row) {
            echo json_encode([
                'success' => false,
                'message' => 'Tidak ada sesi OTP aktif untuk email ini. Silakan minta kode OTP baru.'
            ]);
            exit;
        }

        $otp_id = (int)$otp_row['id'];
        $attempts = (int)$otp_row['attempts'];
        $max_attempts = (int)$otp_row['max_attempts'];
        $expires_at = strtotime($otp_row['expires_at']);

        // Cek kedaluwarsa waktu (5 menit)
        if (time() > $expires_at) {
            $stmt_exp = mysqli_prepare($conn, "UPDATE otp_verifikasi SET is_used = 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt_exp, "i", $otp_id);
            mysqli_stmt_execute($stmt_exp);
            mysqli_stmt_close($stmt_exp);

            echo json_encode([
                'success' => false,
                'message' => 'Kode OTP telah kedaluwarsa (berlaku 5 menit). Silakan minta kode OTP baru.'
            ]);
            exit;
        }

        // Cek batas percobaan verifikasi
        if ($attempts >= $max_attempts) {
            $stmt_lock = mysqli_prepare($conn, "UPDATE otp_verifikasi SET is_used = 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt_lock, "i", $otp_id);
            mysqli_stmt_execute($stmt_lock);
            mysqli_stmt_close($stmt_lock);

            echo json_encode([
                'success' => false,
                'message' => 'Batas percobaan verifikasi telah tercapai (5 kali). Demi keamanan, silakan minta kode OTP baru.'
            ]);
            exit;
        }

        // Verifikasi hash OTP
        if (!password_verify($otp_input, $otp_row['otp_hash'])) {
            $new_attempts = $attempts + 1;
            $remaining = $max_attempts - $new_attempts;

            $stmt_upd = mysqli_prepare($conn, "UPDATE otp_verifikasi SET attempts = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_upd, "ii", $new_attempts, $otp_id);
            mysqli_stmt_execute($stmt_upd);
            mysqli_stmt_close($stmt_upd);

            if ($remaining <= 0) {
                $stmt_lock = mysqli_prepare($conn, "UPDATE otp_verifikasi SET is_used = 1 WHERE id = ?");
                mysqli_stmt_bind_param($stmt_lock, "i", $otp_id);
                mysqli_stmt_execute($stmt_lock);
                mysqli_stmt_close($stmt_lock);

                echo json_encode([
                    'success' => false,
                    'message' => 'Kode OTP salah. Batas percobaan habis. Silakan kirim ulang OTP.'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => "Kode OTP salah. Sisa kesempatan verifikasi: {$remaining} kali."
                ]);
            }
            exit;
        }

        // --- VERIFIKASI BERHASIL ---
        mysqli_begin_transaction($conn);
        try {
            // Tandai OTP sudah terpakai
            $stmt_use = mysqli_prepare($conn, "UPDATE otp_verifikasi SET is_used = 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt_use, "i", $otp_id);
            mysqli_stmt_execute($stmt_use);
            mysqli_stmt_close($stmt_use);

            // Tautkan ke tabel guru_google (1 guru = 1 akun google)
            $stmt_link = mysqli_prepare($conn, "INSERT INTO guru_google (id_guru, email_google, status_tautan, verified_at) 
                VALUES (?, ?, 'terhubung', NOW()) 
                ON DUPLICATE KEY UPDATE email_google = VALUES(email_google), status_tautan = 'terhubung', verified_at = NOW()");
            mysqli_stmt_bind_param($stmt_link, "is", $id_guru, $email);
            mysqli_stmt_execute($stmt_link);
            mysqli_stmt_close($stmt_link);

            // Update juga kolom email di tabel guru agar sinkron jika diperlukan
            $stmt_sync = mysqli_prepare($conn, "UPDATE guru SET email = ? WHERE id_guru = ?");
            mysqli_stmt_bind_param($stmt_sync, "si", $email, $id_guru);
            mysqli_stmt_execute($stmt_sync);
            mysqli_stmt_close($stmt_sync);

            mysqli_commit($conn);

            echo json_encode([
                'success' => true,
                'message' => 'Akun Google berhasil diverifikasi dan ditautkan!',
                'email' => $email
            ]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data tautan Google: ' . $e->getMessage()
            ]);
        }
        break;

    // -------------------------------------------------------------
    // 4. PUTUSKAN TAUTAN AKUN GOOGLE
    // -------------------------------------------------------------
    case 'unlink_google':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $stmt_del = mysqli_prepare($conn, "DELETE FROM guru_google WHERE id_guru = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $id_guru);
        $ok = mysqli_stmt_execute($stmt_del);
        mysqli_stmt_close($stmt_del);

        // Batalkan juga OTP aktif jika ada
        $stmt_cnl = mysqli_prepare($conn, "UPDATE otp_verifikasi SET is_used = 1 WHERE id_guru = ? AND is_used = 0");
        mysqli_stmt_bind_param($stmt_cnl, "i", $id_guru);
        mysqli_stmt_execute($stmt_cnl);
        mysqli_stmt_close($stmt_cnl);

        if ($ok) {
            echo json_encode([
                'success' => true,
                'message' => 'Tautan akun Google berhasil diputuskan.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal memutuskan tautan akun Google.'
            ]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action tidak dikenali.'
        ]);
        break;
}
