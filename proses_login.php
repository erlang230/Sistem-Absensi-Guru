<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['error_msg'] = "Username dan password wajib diisi!";
        header("Location: login.php");
        exit;
    }

    // Gunakan Prepared Statement untuk mencegah SQL Injection
    $stmt = mysqli_prepare($conn, "SELECT id, nama, username, password, role, status FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        // Verifikasi password yang di-hash dengan bcrypt
        if (password_verify($password, $user['password'])) {
            
            // Cek apakah akun aktif atau nonaktif
            if (isset($user['status']) && $user['status'] === 'nonaktif') {
                $_SESSION['error_msg'] = "Akun Anda berstatus nonaktif. Silakan hubungi Administrator.";
                header("Location: login.php");
                exit;
            }

            // Regenerasi Session ID untuk mencegah Session Fixation / Hijacking
            session_regenerate_id(true);

            // Set session data
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['nama']     = $user['nama'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // Redirect ke dashboard sesuai role yang sah
            if ($user['role'] === 'admin') {
                header("Location: dashboard_admin.php");
            } elseif ($user['role'] === 'guru') {
                header("Location: dashboard_guru.php");
            } elseif ($user['role'] === 'kepala_sekolah') {
                header("Location: dashboard_kepala_sekolah.php");
            } else {
                header("Location: login.php");
            }
            exit;
            
        } else {
            // Password salah (pesan aman)
            $_SESSION['error_msg'] = "Username atau password tidak valid!";
            header("Location: login.php");
            exit;
        }
    } else {
        // Username tidak ditemukan (pesan aman yang konsisten)
        $_SESSION['error_msg'] = "Username atau password tidak valid!";
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
?>
