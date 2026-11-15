<?php
/**
 * Cron Script Pengingat Otomatis Absensi Guru
 * Sistem Absensi Guru (SAG)
 * 
 * Penggunaan via CLI / Windows Task Scheduler:
 * C:\xampp\php\php.exe C:\xampp\htdocs\SAG\cron_pengingat_absensi.php
 */

// Hanya izinkan eksekusi via CLI atau proteksi parameter kunci jika diakses via web
if (php_sapi_name() !== 'cli') {
    $cron_key = $_GET['key'] ?? '';
    $secret = 'SAG_CRON_SECRET_2026';
    if ($cron_key !== $secret) {
        http_response_code(403);
        die("Akses ditolak. Script ini dirancang untuk dijalankan melalui Windows Task Scheduler / CLI.\n");
    }
}

date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d');
$now_time = date('H:i:s');
$day_of_week = (int)date('w'); // 0 = Minggu, 1 = Senin, dst.

echo "====================================================\n";
echo "SISTEM ABSENSI GURU (SAG) - PENGINGAT OTOMATIS EMAIL\n";
echo "Tanggal : " . date('d-m-Y') . " | Pukul : {$now_time} WIB\n";
echo "====================================================\n";

// Aturan 1: Jangan kirim pengingat pada hari Minggu (Libur Sekolah)
if ($day_of_week === 0) {
    echo "[INFO] Hari ini adalah hari Minggu (Libur). Pengingat otomatis dilewati.\n";
    exit(0);
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/proses_alpa.php';

// Pastikan proses alpa otomatis hari ini juga tersinkronisasi
proses_alpa_otomatis($conn, $today);

// Cek konfigurasi mail
$smtp_check = SAGMailer::check_status();
if (!$smtp_check['status']) {
    echo "[ERROR] SMTP belum siap: " . $smtp_check['message'] . "\n";
    exit(1);
}

// Ambil aturan jam masuk dan batas waktu alpa
$stmt_p = mysqli_query($conn, "SELECT nama_pengaturan, nilai_pengaturan FROM pengaturan");
$config = [];
while ($row = mysqli_fetch_assoc($stmt_p)) {
    $config[$row['nama_pengaturan']] = $row['nilai_pengaturan'];
}
$jam_masuk = $config['jam_masuk_sekolah'] ?? '07:00:00';
$batas_alpa = $config['batas_waktu_alpa'] ?? '09:00:00';

echo "[INFO] Jam Masuk: {$jam_masuk} | Batas Waktu Alpa: {$batas_alpa}\n";

// Query guru yang:
// 1. Memiliki akun Google terverifikasi
// 2. Belum melakukan absensi Hadir / Terlambat / Izin / Sakit hari ini
// 3. Belum dikirimi pengingat sukses hari ini dalam 2 jam terakhir
$query = "
    SELECT g.id_guru, g.nama_guru, g.nip, gg.email_google
    FROM guru g
    JOIN guru_google gg ON g.id_guru = gg.id_guru AND gg.status_tautan = 'terhubung'
    JOIN users u ON g.id_user = u.id AND u.status = 'aktif'
    WHERE g.id_guru NOT IN (
        SELECT id_guru FROM absensi 
        WHERE tanggal = ? AND status IN ('Hadir', 'Terlambat', 'Izin', 'Sakit')
    )
    AND g.id_guru NOT IN (
        SELECT id_guru FROM riwayat_pengingat 
        WHERE tanggal_absensi = ? AND status_pengiriman = 'berhasil' 
          AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    )
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $today, $today);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$target_guru = [];
while ($row = mysqli_fetch_assoc($res)) {
    $target_guru[] = $row;
}
mysqli_stmt_close($stmt);

$total_targets = count($target_guru);
echo "[INFO] Ditemukan {$total_targets} guru yang belum absen dan memenuhi kriteria pengingat.\n";

if ($total_targets === 0) {
    echo "[SUKSES] Semua guru telah absen atau telah menerima pengingat. Tidak ada aksi lanjutan.\n";
    exit(0);
}

$hari_indo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulan_indo = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$ts = strtotime($today);
$tgl_formatted = $hari_indo[date('l', $ts)] . ', ' . date('d', $ts) . ' ' . $bulan_indo[date('m', $ts)] . ' ' . date('Y', $ts);

$berhasil = 0;
$gagal = 0;

foreach ($target_guru as $g) {
    $id_guru = (int)$g['id_guru'];
    $nama = $g['nama_guru'];
    $email = $g['email_google'];

    echo " -> Mengirim pengingat ke: {$nama} ({$email})... ";

    $send_res = SAGMailer::send_reminder($email, $nama, $tgl_formatted);
    $status_kirim = $send_res['success'] ? 'berhasil' : 'gagal';
    $ket = $send_res['success'] 
        ? 'Pengingat otomatis berhasil terkirim' 
        : 'Gagal kirim: ' . ($send_res['error'] ?? 'Kesalahan SMTP');

    // Catat ke riwayat_pengingat dengan jenis_pengingat = 'otomatis' dan dikirim_oleh = NULL
    $stmt_log = mysqli_prepare($conn, "
        INSERT INTO riwayat_pengingat (id_guru, dikirim_oleh, jenis_pengingat, email_tujuan, tanggal_absensi, status_pengiriman, keterangan)
        VALUES (?, NULL, 'otomatis', ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt_log, "issss", $id_guru, $email, $today, $status_kirim, $ket);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);

    if ($send_res['success']) {
        echo "[BERHASIL]\n";
        $berhasil++;
    } else {
        echo "[GAGAL] {$ket}\n";
        $gagal++;
    }

    // Sleep kecil agar tidak membebani server SMTP
    usleep(500000); // 0.5 detik
}

echo "====================================================\n";
echo "RINGKASAN EKSEKUSI:\n";
echo "Total Target   : {$total_targets}\n";
echo "Berhasil Kirim : {$berhasil}\n";
echo "Gagal Kirim    : {$gagal}\n";
echo "====================================================\n";
