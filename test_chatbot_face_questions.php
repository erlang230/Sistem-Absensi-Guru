<?php
/**
 * test_chatbot_face_questions.php
 * Menguji chatbot SAG untuk pertanyaan registrasi wajah via PHP endpoint dan proxy
 */

function testPhpChatbot($msg, $role = 'admin') {
    echo "============================================================\n";
    echo "PENGUJIAN PHP ENDPOINT (chatbot.php)\n";
    echo "Pertanyaan: \"$msg\" (Role: $role)\n";
    echo "------------------------------------------------------------\n";
    
    $ch = curl_init('http://localhost/SAG/chatbot.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'message' => $msg,
        'role' => $role,
        'namaUser' => 'Administrator',
        'idGuru' => 0
    ]));
    
    $res = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($res, true);
    echo ($data['reply'] ?? $res) . "\n\n";
}

// 1. Uji pertanyaan "Siapa guru yang status wajahnya belum terdaftar?"
testPhpChatbot("Siapa guru yang status wajahnya belum terdaftar?");

// 2. Uji pertanyaan "Berapa guru yang sudah terdaftar?"
testPhpChatbot("Berapa guru yang sudah terdaftar?");

// 3. Uji pertanyaan "Apakah Rafi Erlangga sudah terdaftar?"
testPhpChatbot("Apakah Rafi Erlangga sudah terdaftar?");

// 4. Uji pertanyaan "Siapa saja guru yang wajahnya sudah terdaftar?"
testPhpChatbot("Siapa saja guru yang wajahnya sudah terdaftar?");

// 5. Uji pertanyaan "Apakah Ali Sadikin NPD sudah terdaftar?"
testPhpChatbot("Apakah Ali Sadikin NPD sudah terdaftar?");
