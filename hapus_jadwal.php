<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = mysqli_prepare($conn, "DELETE FROM jadwal WHERE id_jadwal=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Jadwal berhasil dihapus.";
    } else {
        $_SESSION['error'] = "Gagal menghapus jadwal.";
    }
}
header("Location: jadwal_mengajar.php");
exit;
?>
