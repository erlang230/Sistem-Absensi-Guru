<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_guru = intval($_GET['id']);
    $stmt = mysqli_prepare($conn, "UPDATE guru SET face_data = NULL WHERE id_guru = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_guru);
    if(mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Data wajah guru berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Gagal menghapus data wajah.";
    }
}
header("Location: registrasi_wajah.php");
exit;
?>
