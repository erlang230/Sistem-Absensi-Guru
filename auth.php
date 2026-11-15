<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function check_role($allowed_role) {
    if (!isset($_SESSION['role'])) {
        header("Location: login.php");
        exit;
    }
    
    if ($_SESSION['role'] !== $allowed_role) {
        // Redirect jika role tidak sesuai
        if ($_SESSION['role'] == 'admin') {
            header("Location: dashboard_admin.php");
        } elseif ($_SESSION['role'] == 'guru') {
            header("Location: dashboard_guru.php");
        } elseif ($_SESSION['role'] == 'kepala_sekolah') {
            header("Location: dashboard_kepala_sekolah.php");
        }
        exit;
    }
}

function check_roles($allowed_roles) {
    if (!isset($_SESSION['role'])) {
        header("Location: login.php");
        exit;
    }
    $allowed = (array) $allowed_roles;
    if (!in_array($_SESSION['role'], $allowed)) {
        if ($_SESSION['role'] == 'admin') {
            header("Location: dashboard_admin.php");
        } elseif ($_SESSION['role'] == 'guru') {
            header("Location: dashboard_guru.php");
        } elseif ($_SESSION['role'] == 'kepala_sekolah') {
            header("Location: dashboard_kepala_sekolah.php");
        }
        exit;
    }
}
?>
