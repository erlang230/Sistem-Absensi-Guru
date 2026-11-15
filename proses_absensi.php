<?php
require_once 'auth.php';
check_login();
check_role('guru');
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: absensi.php"); exit;
}

$jenis = $_POST['jenis'] ?? '';
$foto_base64 = $_POST['foto_base64'] ?? '';
$metode = $_POST['metode_verifikasi'] ?? 'Manual';
$verified_guru_id = isset($_POST['verified_guru_id']) ? intval($_POST['verified_guru_id']) : 0;

// ============================================================
// Validasi Input
// ============================================================
if (empty($jenis) || empty($foto_base64)) {
    $_SESSION['error'] = "Data absensi tidak lengkap!";
    header("Location: absensi.php"); exit;
}

// ============================================================
// Ambil ID Guru dari Session (Server-side, tidak bisa dimanipulasi)
// ============================================================
$id_user = $_SESSION['user_id'];
$stmt_g = mysqli_prepare($conn, "SELECT id_guru FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_g, "i", $id_user);
mysqli_stmt_execute($stmt_g);
$id_guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_g))['id_guru'];

// ============================================================
// Validasi Keamanan: Jika metode AI, pastikan wajah yang diverifikasi
// sesuai dengan guru yang sedang login.
// ============================================================
if ($metode === 'AI Face Recognition') {
    if ($verified_guru_id !== $id_guru) {
        $_SESSION['error'] = "Keamanan: Wajah yang terverifikasi tidak sesuai dengan akun yang login. Absensi ditolak.";
        header("Location: absensi.php"); exit;
    }
}

// ============================================================
// Simpan Foto Bukti ke Server
// ============================================================
$foto_data = str_replace('data:image/png;base64,', '', $foto_base64);
$foto_data = str_replace(' ', '+', $foto_data);
$data_file = base64_decode($foto_data);
$upload_dir = 'uploads/absensi/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
$file_name = 'absensi_' . $jenis . '_guru' . $id_guru . '_' . date('Ymd_His') . '.png';
file_put_contents($upload_dir . $file_name, $data_file);

// ============================================================
// Proses Absensi
// ============================================================
date_default_timezone_set('Asia/Jakarta');
$tanggal = date('Y-m-d');
$waktu_sekarang = date('H:i:s');

$stmt_cek = mysqli_prepare($conn, "SELECT * FROM absensi WHERE id_guru = ? AND tanggal = ?");
mysqli_stmt_bind_param($stmt_cek, "is", $id_guru, $tanggal);
mysqli_stmt_execute($stmt_cek);
$absen = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));

if ($jenis === 'masuk') {
    if ($absen) {
        $_SESSION['error'] = "Anda sudah melakukan absensi masuk hari ini!";
    } else {
        // Ambil pengaturan jam masuk
        $q = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'jam_masuk_sekolah'");
        $jam_masuk_standar = mysqli_fetch_assoc($q)['nilai_pengaturan'];

        $q2 = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'toleransi_keterlambatan'");
        $toleransi = mysqli_fetch_assoc($q2)['nilai_pengaturan'];

        $batas = date('H:i:s', strtotime("+{$toleransi} minutes", strtotime($jam_masuk_standar)));
        $status = (strtotime($waktu_sekarang) <= strtotime($batas)) ? 'Hadir' : 'Terlambat';

        $stmt_in = mysqli_prepare($conn, "INSERT INTO absensi (id_guru, tanggal, jam_masuk, status, metode_verifikasi) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_in, "issss", $id_guru, $tanggal, $waktu_sekarang, $status, $metode);
        if (mysqli_stmt_execute($stmt_in)) {
            $_SESSION['success'] = "✓ Absensi Masuk berhasil pada $waktu_sekarang. Status: <strong>$status</strong>. Metode: $metode.";
        } else {
            $_SESSION['error'] = "Gagal menyimpan absensi masuk.";
        }
    }
} elseif ($jenis === 'pulang') {
    if (!$absen) {
        $_SESSION['error'] = "Anda belum melakukan absensi masuk!";
    } elseif ($absen['jam_pulang'] != null) {
        $_SESSION['error'] = "Anda sudah melakukan absensi pulang hari ini!";
    } else {
        $stmt_up = mysqli_prepare($conn, "UPDATE absensi SET jam_pulang = ? WHERE id_absensi = ?");
        mysqli_stmt_bind_param($stmt_up, "si", $waktu_sekarang, $absen['id_absensi']);
        if (mysqli_stmt_execute($stmt_up)) {
            $_SESSION['success'] = "✓ Absensi Pulang berhasil pada $waktu_sekarang. Metode: $metode.";
        } else {
            $_SESSION['error'] = "Gagal menyimpan absensi pulang.";
        }
    }
}

header("Location: absensi.php");
exit;
?>
