<?php
session_start();

// Jika sudah login, redirect sesuai role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') header("Location: dashboard_admin.php");
    elseif ($_SESSION['role'] == 'guru') header("Location: dashboard_guru.php");
    elseif ($_SESSION['role'] == 'kepala_sekolah') header("Location: dashboard_kepala_sekolah.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Absensi Guru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-login {
            width: 100%;
            max-width: 400px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="card card-login p-4 bg-white">
    <div class="text-center mb-4">
        <h4>Sistem Absensi Guru</h4>
        <p class="text-muted">Silakan login ke akun Anda</p>
    </div>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger text-center" role="alert">
            <?= htmlspecialchars($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?>
        </div>
    <?php endif; ?>

    <form action="proses_login.php" method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required autocomplete="off">
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
    </form>
    
    <div class="mt-4 text-center text-muted small">
        &copy; <?= date("Y") ?> SMA Negeri
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
