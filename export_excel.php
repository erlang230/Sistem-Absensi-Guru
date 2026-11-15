<?php
require_once 'auth.php';
check_login();
check_roles(['admin', 'kepala_sekolah']);

require_once 'config/database.php';

date_default_timezone_set('Asia/Jakarta');

// Parameter Filter
$filter_bulan   = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';
$filter_tahun   = isset($_GET['tahun']) ? trim($_GET['tahun']) : '';
$filter_id_guru = isset($_GET['id_guru']) ? trim($_GET['id_guru']) : '';
$filter_status  = isset($_GET['status']) ? trim($_GET['status']) : '';

$nama_bulan = [
    '1' => 'Januari', '2' => 'Februari', '3' => 'Maret',
    '4' => 'April', '5' => 'Mei', '6' => 'Juni',
    '7' => 'Juli', '8' => 'Agustus', '9' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Nama Guru terpilih jika ada
$nama_guru_filter = 'Semua Guru';
if (!empty($filter_id_guru)) {
    $q_g = mysqli_query($conn, "SELECT nama_guru FROM guru WHERE id_guru = " . intval($filter_id_guru));
    if ($rg = mysqli_fetch_assoc($q_g)) {
        $nama_guru_filter = $rg['nama_guru'];
    }
}

// Build Query
$where_conditions = ["1=1"];
if (!empty($filter_bulan)) {
    $where_conditions[] = "MONTH(a.tanggal) = " . intval($filter_bulan);
}
if (!empty($filter_tahun)) {
    $where_conditions[] = "YEAR(a.tanggal) = " . intval($filter_tahun);
}
if (!empty($filter_id_guru)) {
    $where_conditions[] = "a.id_guru = " . intval($filter_id_guru);
}
if (!empty($filter_status)) {
    $where_conditions[] = "a.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
$where_sql = implode(' AND ', $where_conditions);

// Statistik
$sql_stat = "SELECT 
    SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir,
    SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) as total_terlambat,
    SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) as total_izin,
    SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) as total_sakit,
    SUM(CASE WHEN a.status = 'Alpha' THEN 1 ELSE 0 END) as total_alpha,
    COUNT(*) as total_semua
    FROM absensi a 
    WHERE $where_sql";
$stat = mysqli_fetch_assoc(mysqli_query($conn, $sql_stat));

// Data Absensi
$sql_data = "SELECT a.*, g.nama_guru, g.nip 
    FROM absensi a 
    JOIN guru g ON a.id_guru = g.id_guru 
    WHERE $where_sql 
    ORDER BY a.tanggal DESC, a.jam_masuk DESC";
$res_data = mysqli_query($conn, $sql_data);

// Header Excel Download
$filename = "Laporan_Absensi_Guru_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Calibri, Arial, sans-serif; }
        .title { font-size: 16pt; font-weight: bold; text-align: center; }
        .subtitle { font-size: 11pt; text-align: center; color: #555; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th { background-color: #0d6efd; color: #ffffff; border: 1px solid #999; padding: 8px; font-weight: bold; text-align: center; }
        td { border: 1px solid #ccc; padding: 6px; }
        .stat-box { background-color: #f8f9fa; border: 1px solid #ddd; padding: 10px; margin-top: 10px; }
        .stat-label { font-weight: bold; }
        .center { text-align: center; }
    </style>
</head>
<body>

    <!-- JUDUL LAPORAN -->
    <table>
        <tr>
            <td colspan="7" class="title">LAPORAN REKAPITULASI ABSENSI GURU</td>
        </tr>
        <tr>
            <td colspan="7" class="subtitle">SISTEM ABSENSI GURU (SAG) BERBASIS AI</td>
        </tr>
        <tr>
            <td colspan="7" class="center">
                Periode: <?= !empty($filter_bulan) ? $nama_bulan[$filter_bulan] : 'Semua Bulan' ?> <?= !empty($filter_tahun) ? $filter_tahun : 'Semua Tahun' ?>
                | Guru: <?= htmlspecialchars($nama_guru_filter) ?>
                | Status: <?= !empty($filter_status) ? htmlspecialchars($filter_status) : 'Semua Status' ?>
            </td>
        </tr>
        <tr>
            <td colspan="7" class="center" style="font-size: 9pt; color:#666;">
                Tanggal Export: <?= date('d/m/Y H:i:s') ?> WIB
            </td>
        </tr>
    </table>

    <br>

    <!-- RINGKASAN STATISTIK -->
    <table style="width: 70%;">
        <tr style="background-color: #f2f2f2;">
            <th colspan="6" style="background-color: #333; color: #fff;">RINGKASAN STATISTIK KEHADIRAN</th>
        </tr>
        <tr style="text-align: center; font-weight: bold;">
            <td style="background-color: #e8f5e9; color: #1b5e20;">Total Hadir</td>
            <td style="background-color: #fffde7; color: #f57f17;">Total Terlambat</td>
            <td style="background-color: #e1f5fe; color: #0277bd;">Total Izin</td>
            <td style="background-color: #f3e5f5; color: #6a1b9a;">Total Sakit</td>
            <td style="background-color: #ffebee; color: #b71c1c;">Total Alpha</td>
            <td style="background-color: #e0e0e0; color: #212121;">Total Data</td>
        </tr>
        <tr style="text-align: center; font-size: 13pt; font-weight: bold;">
            <td><?= (int)($stat['total_hadir'] ?? 0) ?></td>
            <td><?= (int)($stat['total_terlambat'] ?? 0) ?></td>
            <td><?= (int)($stat['total_izin'] ?? 0) ?></td>
            <td><?= (int)($stat['total_sakit'] ?? 0) ?></td>
            <td><?= (int)($stat['total_alpha'] ?? 0) ?></td>
            <td><?= (int)($stat['total_semua'] ?? 0) ?></td>
        </tr>
    </table>

    <br>

    <!-- TABEL DATA ABSENSI -->
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">No</th>
                <th>Nama Guru</th>
                <th>NIP</th>
                <th>Tanggal</th>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Status</th>
                <th>Metode Verifikasi</th>
            </tr>
        </thead>
        <tbody>
            <?php if(mysqli_num_rows($res_data) > 0): ?>
                <?php $no = 1; while($row = mysqli_fetch_assoc($res_data)): ?>
                <tr>
                    <td class="center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['nama_guru']) ?></td>
                    <td style="mso-number-format:'\@';"><?= htmlspecialchars($row['nip'] ?? '-') ?></td>
                    <td class="center"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                    <td class="center"><?= $row['jam_masuk'] ? substr($row['jam_masuk'], 0, 5) : '-' ?></td>
                    <td class="center"><?= $row['jam_pulang'] ? substr($row['jam_pulang'], 0, 5) : '-' ?></td>
                    <td class="center" style="font-weight: bold;"><?= htmlspecialchars($row['status']) ?></td>
                    <td class="center"><?= htmlspecialchars($row['metode_verifikasi']) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="center">Tidak ada data absensi untuk filter ini.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br><br>

    <!-- TANDA TANGAN -->
    <table style="width: 100%; border: none;">
        <tr style="border: none;">
            <td style="border: none; width: 60%;"></td>
            <td style="border: none; text-align: center;">
                Mengetahui,<br>
                Kepala Sekolah
                <br><br><br><br><br>
                <strong>( ................................................. )</strong><br>
                NIP. ........................................
            </td>
        </tr>
    </table>

</body>
</html>
