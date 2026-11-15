<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: registrasi_wajah.php"); exit;
}

$id_guru        = intval($_POST['id_guru'] ?? 0);
$face_data_json = trim($_POST['face_data_json'] ?? '');

// Validasi ID
if ($id_guru <= 0) {
    $_SESSION['error'] = "ID Guru tidak valid.";
    header("Location: registrasi_wajah.php"); exit;
}

// Validasi JSON kosong
if (empty($face_data_json)) {
    $_SESSION['error'] = "Gagal: Data wajah tidak diterima dari browser. Silakan ulangi proses registrasi.";
    header("Location: kamera_wajah.php?id=$id_guru"); exit;
}

// Validasi format JSON
$parsed = json_decode($face_data_json, true);
if (!$parsed || json_last_error() !== JSON_ERROR_NONE) {
    $_SESSION['error'] = "Gagal: Format data wajah tidak valid.";
    header("Location: kamera_wajah.php?id=$id_guru"); exit;
}

// Validasi jumlah sampel minimal 5
if (!isset($parsed['descriptors']) || !is_array($parsed['descriptors'])) {
    $_SESSION['error'] = "Gagal: Data descriptor wajah tidak ditemukan.";
    header("Location: kamera_wajah.php?id=$id_guru"); exit;
}

$jumlah_sampel = count($parsed['descriptors']);
if ($jumlah_sampel < 5) {
    $_SESSION['error'] = "Gagal: Hanya $jumlah_sampel sampel diterima. Minimal 5 sampel diperlukan.";
    header("Location: kamera_wajah.php?id=$id_guru"); exit;
}

// Validasi setiap descriptor adalah array dengan 128 elemen (face-api.js standard)
foreach ($parsed['descriptors'] as $idx => $desc) {
    if (!is_array($desc) || count($desc) !== 128) {
        $_SESSION['error'] = "Gagal: Sampel ke-" . ($idx + 1) . " tidak valid. Silakan ulangi registrasi.";
        header("Location: kamera_wajah.php?id=$id_guru"); exit;
    }
}

// Ambil nama guru
$stmt_nama = mysqli_prepare($conn, "SELECT nama_guru FROM guru WHERE id_guru = ?");
mysqli_stmt_bind_param($stmt_nama, "i", $id_guru);
mysqli_stmt_execute($stmt_nama);
$nama_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_nama));

if (!$nama_result) {
    $_SESSION['error'] = "Data guru tidak ditemukan.";
    header("Location: registrasi_wajah.php"); exit;
}
$nama_guru = $nama_result['nama_guru'];

// Tambahkan metadata tambahan
$parsed['registered_at'] = date('Y-m-d H:i:s');
$parsed['registered_by'] = $_SESSION['user_id'] ?? 0;
$face_data_final = json_encode($parsed);

// Simpan ke database
$stmt = mysqli_prepare($conn, "UPDATE guru SET face_data = ? WHERE id_guru = ?");
mysqli_stmt_bind_param($stmt, "si", $face_data_final, $id_guru);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['success'] = "✓ Data wajah <strong>$nama_guru</strong> berhasil disimpan dengan <strong>$jumlah_sampel sampel</strong>. Guru dapat menggunakan absensi AI.";
} else {
    $_SESSION['error'] = "Gagal menyimpan data wajah ke database. Silakan coba lagi.";
}

header("Location: registrasi_wajah.php");
exit;
?>
