<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

header('Content-Type: application/json; charset=utf-8');

$id_kelas = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_kelas <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID Kelas tidak valid']);
    exit;
}

// Ambil info kelas
$stmt_k = mysqli_prepare($conn, "SELECT id_kelas, nama_kelas, tingkat, jurusan FROM kelas WHERE id_kelas = ?");
mysqli_stmt_bind_param($stmt_k, "i", $id_kelas);
mysqli_stmt_execute($stmt_k);
$res_k = mysqli_stmt_get_result($stmt_k);
$kelas = mysqli_fetch_assoc($res_k);
mysqli_stmt_close($stmt_k);

if (!$kelas) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Data kelas tidak ditemukan']);
    exit;
}

// Ambil jadwal kelas
$sql = "SELECT j.id_jadwal, j.hari, j.jam_mulai, j.jam_selesai, g.nama_guru, m.nama_mapel, m.kode_mapel 
        FROM jadwal j 
        JOIN guru g ON j.id_guru = g.id_guru 
        JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
        WHERE j.id_kelas = ? 
        ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_kelas);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$jadwal = [];
while ($row = mysqli_fetch_assoc($res)) {
    $jadwal[] = [
        'id_jadwal'   => $row['id_jadwal'],
        'hari'        => $row['hari'],
        'jam_mulai'   => substr($row['jam_mulai'], 0, 5),
        'jam_selesai' => substr($row['jam_selesai'], 0, 5),
        'nama_guru'   => $row['nama_guru'],
        'nama_mapel'  => $row['nama_mapel'],
        'kode_mapel'  => $row['kode_mapel']
    ];
}
mysqli_stmt_close($stmt);

echo json_encode([
    'status' => 'success',
    'kelas'  => $kelas,
    'jadwal' => $jadwal
]);
