<?php
/**
 * test_api_chat_proxy.php
 * Menguji api_chat_proxy.php (yang memanggil node server.js dengan AI)
 */

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
session_write_close();

function testProxy($msg) {
    echo "============================================================\n";
    echo "PENGUJIAN VIA PROXY AI (api_chat_proxy.php -> Node.js Groq AI)\n";
    echo "Pertanyaan: \"$msg\"\n";
    echo "------------------------------------------------------------\n";
    
    $ch = curl_init('http://localhost/SAG/api_chat_proxy.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Cookie: PHPSESSID=' . session_id()]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'message' => $msg
    ]));
    
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    $data = json_decode($res, true);
    echo "Jawaban AI:\n" . ($data['reply'] ?? $res) . "\n\n";
}

testProxy("Siapa guru yang status wajahnya belum terdaftar?");
testProxy("Berapa guru yang sudah terdaftar?");
testProxy("Apakah Rafi Erlangga sudah terdaftar?");
