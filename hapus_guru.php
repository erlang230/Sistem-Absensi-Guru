<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_guru = intval($_GET['id']);
    
    // Cari data guru terlebih dahulu untuk mendapatkan id_user dan nama file foto
    $stmt = mysqli_prepare($conn, "SELECT id_user, foto FROM guru WHERE id_guru = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_guru);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $guru = mysqli_fetch_assoc($result);
        
        // Hapus foto jika ada
        if ($guru['foto'] && file_exists('uploads/profil/' . $guru['foto'])) {
            unlink('uploads/profil/' . $guru['foto']);
        }
        
        // Karena relasi database ON DELETE CASCADE, menghapus user akan otomatis menghapus guru (dan jadwal/absensinya)
        $id_user = $guru['id_user'];
        $stmt_del = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $id_user);
        
        if (mysqli_stmt_execute($stmt_del)) {
            $_SESSION['success'] = "Data guru dan akunnya berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus data: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Data tidak ditemukan!";
    }
} else {
    $_SESSION['error'] = "ID tidak valid!";
}

header("Location: data_guru.php");
exit;
?>
