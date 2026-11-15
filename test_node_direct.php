<?php
$data = [
    'message' => 'Siapa guru yang status wajahnya belum terdaftar?',
    'role' => 'admin',
    'context' => [
        'daftar_guru_wajah_belum_terdaftar' => 'Ali Sadikin NPD, YurCukurukuk',
        'daftar_guru_wajah_sudah_terdaftar' => 'Rafi Erlangga, Soleh sn'
    ]
];

$ch = curl_init('http://localhost:3000/api/chat');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Error: $err\n";
echo "Response: $res\n";
