<?php
/**
 * Endpoint API: Mengembalikan data wajah semua guru yang sudah terdaftar.
 * Hanya dapat diakses oleh user yang sudah login.
 */
require_once 'auth.php';
check_login(); // Wajib login, semua role boleh akses endpoint ini
require_once 'config/database.php';

header('Content-Type: application/json');

$sql = "SELECT id_guru, nama_guru, face_data FROM guru WHERE face_data IS NOT NULL AND face_data != ''";
$result = mysqli_query($conn, $sql);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $parsed = json_decode($row['face_data'], true);
    if ($parsed && isset($parsed['descriptors']) && count($parsed['descriptors']) >= 5) {
        $data[] = [
            'id_guru'    => (int) $row['id_guru'],
            'nama_guru'  => $row['nama_guru'],
            'descriptors'=> $parsed['descriptors']
        ];
    }
}

echo json_encode(['status' => 'ok', 'total' => count($data), 'data' => $data]);
exit;
?>
