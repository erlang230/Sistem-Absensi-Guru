<?php
/**
 * api_chatbot_status.php
 * Endpoint publik ringan terautentikasi sesi untuk membaca status aktif/nonaktif chatbot secara real-time
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['enabled' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config/database.php';

$chatbot_enabled = true;
$res = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'chatbot_enabled' LIMIT 1");
if ($res && $row = mysqli_fetch_assoc($res)) {
    if ($row['nilai_pengaturan'] === '0') {
        $chatbot_enabled = false;
    }
}

echo json_encode([
    'success' => true,
    'enabled' => $chatbot_enabled
]);

