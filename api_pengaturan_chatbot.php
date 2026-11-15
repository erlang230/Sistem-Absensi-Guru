<?php
/**
 * api_pengaturan_chatbot.php
 * Endpoint API internal untuk Manajemen Status & Sistem Multi API Key Chatbot AI SAG
 * Khusus Administrator dengan otentikasi sesi dan proteksi CSRF token
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php'; // Menggunakan enkripsi AES-256 yang sudah teruji aman di SAG

// 1. Otorisasi Akses: Wajib Login & Role Administrator
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Fitur ini hanya dapat dikontrol oleh Administrator.'
    ]);
    exit;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

// 2. Proteksi CSRF untuk operasi mutasi state (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Sesi keamanan (CSRF Token) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.'
        ]);
        exit;
    }
}

/**
 * Helper untuk memeriksa kesehatan backend Node.js
 */
if (!function_exists('check_node_health')) {
function check_node_health() {
    $node_health_url = 'http://localhost:3000/health';
    $ch = curl_init($node_health_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    
    $res = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $is_online = ($res !== false && $httpcode === 200);
    $parsed_info = null;
    if ($is_online) {
        $parsed_info = json_decode($res, true);
    }

    return [
        'online'      => $is_online,
        'http_code'   => $httpcode,
        'error'       => $curl_error,
        'backend_info'=> $parsed_info
    ];
}
}

/**
 * Helper untuk mengambil nilai pengaturan
 */
if (!function_exists('get_setting')) {
function get_setting($conn, $key, $default = '') {
    $stmt = mysqli_prepare($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $key);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $val);
        if (mysqli_stmt_fetch($stmt)) {
            mysqli_stmt_close($stmt);
            return $val;
        }
        mysqli_stmt_close($stmt);
    }
    return $default;
}
}

if (!function_exists('set_setting')) {
function set_setting($conn, $key, $value) {
    $stmt = mysqli_prepare($conn, 
        "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) 
         VALUES (?, ?) 
         ON DUPLICATE KEY UPDATE nilai_pengaturan = VALUES(nilai_pengaturan)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $key, $value);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
    return false;
}
}

/**
 * Helper untuk menyamarkan (mask) API Key: ••••••••••••Ab12
 */
if (!function_exists('mask_api_key')) {
function mask_api_key($key) {
    $key = trim((string)$key);
    if (empty($key)) return '';
    $len = strlen($key);
    if ($len <= 4) {
        return str_repeat('•', 8);
    }
    $last4 = substr($key, -4);
    return str_repeat('•', 12) . $last4;
}
}

/**
 * Sinkronisasi seluruh daftar ai_providers ke file backend runtime (ai_config.json dan .env)
 */
if (!function_exists('sync_all_ai_configs')) {
function sync_all_ai_configs($conn) {
    $res = mysqli_query($conn, "SELECT id, provider, api_key_encrypted, model, priority, is_primary, is_active FROM ai_providers ORDER BY is_primary DESC, id ASC");
    $list = [];
    $primaryGroq = null;
    $primaryGemini = null;
    $hasPrimary = false;

    while ($r = mysqli_fetch_assoc($res)) {
        $plainKey = SAGMailer::decrypt_credential($r['api_key_encrypted']);
        $isPri = (int)$r['is_primary'] === 1;
        $isAct = (int)$r['is_active'] === 1;
        if ($isPri && $isAct) $hasPrimary = true;

        $list[] = [
            'id' => (int)$r['id'],
            'provider' => strtolower($r['provider']),
            'model' => $r['model'],
            'priority' => $r['priority'],
            'is_primary' => $isPri,
            'is_active' => $isAct,
            'apiKey' => $plainKey
        ];

        if (strtolower($r['provider']) === 'groq' && $primaryGroq === null) {
            $primaryGroq = [
                'key' => $plainKey,
                'model' => $r['model'],
                'status' => $isAct ? 'aktif' : 'nonaktif'
            ];
        }
        if (strtolower($r['provider']) === 'gemini' && $primaryGemini === null) {
            $primaryGemini = [
                'key' => $plainKey,
                'model' => $r['model'],
                'status' => $isAct ? 'aktif' : 'nonaktif'
            ];
        }
    }

    // Tulis ke ai_config.json
    $jsonPath = __DIR__ . '/chatbot-backend/ai_config.json';
    @file_put_contents($jsonPath, json_encode($list, JSON_PRETTY_PRINT));

    // Sinkronkan ke .env untuk backward compatibility
    $envPath = __DIR__ . '/chatbot-backend/.env';
    $groqKey = $primaryGroq ? $primaryGroq['key'] : '';
    $groqModel = $primaryGroq ? $primaryGroq['model'] : 'openai/gpt-oss-20b';
    $groqStatus = $primaryGroq ? $primaryGroq['status'] : 'nonaktif';

    $geminiKey = $primaryGemini ? $primaryGemini['key'] : '';
    $geminiModel = $primaryGemini ? $primaryGemini['model'] : 'gemini-1.5-flash';
    $geminiStatus = $primaryGemini ? $primaryGemini['status'] : 'nonaktif';

    $lines = [
        "# SAG Chatbot Backend Configuration",
        "# Disinkronkan secara otomatis dari Dashboard Admin SAG",
        "PORT=3000",
        "",
        "# Provider 1: Groq",
        "GROQ_API_KEY=" . (!empty($groqKey) ? $groqKey : 'ISI_API_KEY_GROQ_DI_LOKAL'),
        "GROQ_BASE_URL=https://api.groq.com/openai/v1",
        "GROQ_MODEL=" . $groqModel,
        "GROQ_STATUS=" . $groqStatus,
        "",
        "# Provider 2: Google Gemini",
        "GEMINI_API_KEY=" . (!empty($geminiKey) ? $geminiKey : 'ISI_API_KEY_GEMINI_BARU_DI_LOKAL'),
        "GEMINI_MODEL=" . $geminiModel,
        "GEMINI_STATUS=" . $geminiStatus,
        "",
        "# Pengaturan Prioritas Fallback",
        "AI_PRIMARY_PROVIDER=groq",
        "AI_BACKUP_PROVIDER=gemini",
        ""
    ];
    @file_put_contents($envPath, implode("\n", $lines));
}
}

switch ($action) {
    // -------------------------------------------------------------
    // GET STATUS CHATBOT & KONEKSI BACKEND
    // -------------------------------------------------------------
    case 'status':
        $chatbot_enabled = get_setting($conn, 'chatbot_enabled', '1') === '1';
        $health = check_node_health();

        // Tentukan status akhir: 'aktif', 'nonaktif', atau 'gangguan'
        if (!$chatbot_enabled) {
            $status_label = 'nonaktif';
            $status_badge = 'Nonaktif';
            $status_color = 'secondary';
        } else {
            if ($health['online']) {
                $status_label = 'aktif';
                $status_badge = 'Aktif';
                $status_color = 'success';
            } else {
                $status_label = 'gangguan';
                $status_badge = 'Gangguan';
                $status_color = 'danger';
            }
        }

        // Catat waktu pemeriksaan terakhir
        $last_checked = date('d-m-Y H:i:s') . ' WIB';
        set_setting($conn, 'chatbot_last_checked', $last_checked);

        $last_changed_at = get_setting($conn, 'chatbot_last_changed_at', '-');
        $last_changed_by = get_setting($conn, 'chatbot_last_changed_by', '-');

        // Ambil provider utama dari database ai_providers
        $q_pri = mysqli_query($conn, "SELECT provider, model FROM ai_providers WHERE is_primary = 1 AND is_active = 1 LIMIT 1");
        $pri_row = mysqli_fetch_assoc($q_pri);
        if (!$pri_row) {
            $q_fallback = mysqli_query($conn, "SELECT provider, model FROM ai_providers WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
            $pri_row = mysqli_fetch_assoc($q_fallback);
        }

        $provider_info = '-';
        $ai_ready = false;
        if ($pri_row) {
            $provName = (strtolower($pri_row['provider']) === 'gemini') ? 'Google Gemini' : 'Groq';
            $provider_info = $provName . ' (' . $pri_row['model'] . ')';
            $ai_ready = true;
        } else {
            $provider_info = 'Belum Ada Provider Aktif';
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'enabled'          => $chatbot_enabled,
                'backend_online'   => $health['online'],
                'status_label'     => $status_label,
                'status_badge'     => $status_badge,
                'status_color'     => $status_color,
                'provider_info'    => $provider_info,
                'ai_ready'         => $ai_ready,
                'last_checked'     => $last_checked,
                'last_changed_at'  => $last_changed_at,
                'last_changed_by'  => $last_changed_by,
                'error_detail'     => $health['online'] ? null : 'Backend Node.js di localhost:3000 tidak merespon.'
            ]
        ]);
        break;

    // -------------------------------------------------------------
    // LIST SELURUH KONFIGURASI AI (MULTI API KEY)
    // -------------------------------------------------------------
    case 'list_providers':
        $res = mysqli_query($conn, "SELECT id, provider, api_key_encrypted, model, priority, is_primary, is_active, last_test_at, last_test_status, created_at, updated_at FROM ai_providers ORDER BY is_primary DESC, id ASC");
        $items = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $plain = SAGMailer::decrypt_credential($r['api_key_encrypted']);
            $items[] = [
                'id' => (int)$r['id'],
                'provider' => strtolower($r['provider']),
                'provider_label' => (strtolower($r['provider']) === 'gemini') ? 'Google Gemini' : 'Groq',
                'model' => $r['model'],
                'priority' => $r['priority'],
                'is_primary' => (int)$r['is_primary'] === 1,
                'is_active' => (int)$r['is_active'] === 1,
                'masked_key' => mask_api_key($plain),
                'last_test_at' => $r['last_test_at'] ? date('d-m-Y H:i', strtotime($r['last_test_at'])) : '-',
                'last_test_status' => $r['last_test_status'] ?? '-'
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $items
        ]);
        break;

    // -------------------------------------------------------------
    // GET DETAIL SATU KONFIGURASI UNTUK MODAL EDIT
    // -------------------------------------------------------------
    case 'get_provider':
        $id = (int)($_GET['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "SELECT id, provider, api_key_encrypted, model, priority, is_primary, is_active FROM ai_providers WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Konfigurasi tidak ditemukan.']);
            exit;
        }

        $plain = SAGMailer::decrypt_credential($row['api_key_encrypted']);
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => (int)$row['id'],
                'provider' => strtolower($row['provider']),
                'model' => $row['model'],
                'priority' => $row['priority'],
                'is_primary' => (int)$row['is_primary'] === 1,
                'is_active' => (int)$row['is_active'] === 1,
                'masked_key' => mask_api_key($plain)
            ]
        ]);
        break;

    // -------------------------------------------------------------
    // SIMPAN KONFIGURASI (TAMBAH BARU / EDIT LAMA)
    // -------------------------------------------------------------
    case 'save_provider':
        $id = (int)($_POST['id'] ?? 0);
        $provider = strtolower(trim($_POST['provider'] ?? ''));
        $apiKeyInput = trim($_POST['api_key'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $priority = strtolower(trim($_POST['priority'] ?? 'cadangan'));
        $status = strtolower(trim($_POST['status'] ?? 'aktif'));
        $isActive = ($status === 'aktif') ? 1 : 0;
        $isPrimary = ($priority === 'utama') ? 1 : 0;

        // Allow any non‑empty provider name; only known adapters are handled later.
        if (empty($provider)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Provider AI wajib diisi.']);
            exit;
        }

        if (empty($model)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nama model AI wajib diisi.']);
            exit;
        }

        // Jika dipilih sebagai Utama, otomatis ubah konfigurasi lain menjadi Cadangan (tidak ambigu)
        if ($isPrimary === 1) {
            mysqli_query($conn, "UPDATE ai_providers SET is_primary = 0, priority = 'cadangan'");
        }

        if ($id > 0) {
            // EDIT KONFIGURASI LAMA
            $q_old = mysqli_query($conn, "SELECT api_key_encrypted FROM ai_providers WHERE id = $id LIMIT 1");
            $old_data = mysqli_fetch_assoc($q_old);
            if (!$old_data) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Konfigurasi tidak ditemukan.']);
                exit;
            }

            $encKey = $old_data['api_key_encrypted'];
            if (!empty($apiKeyInput)) {
                $encKey = SAGMailer::encrypt_credential($apiKeyInput);
                if (empty($encKey)) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Gagal mengenkripsi API Key.']);
                    exit;
                }
            }

            $stmt = mysqli_prepare($conn, "UPDATE ai_providers SET provider = ?, api_key_encrypted = ?, model = ?, priority = ?, is_primary = ?, is_active = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssssiii", $provider, $encKey, $model, $priority, $isPrimary, $isActive, $id);
            mysqli_stmt_execute($stmt);
            $msg = "Konfigurasi provider berhasil diperbarui.";
        } else {
            // TAMBAH KONFIGURASI BARU
            if (empty($apiKeyInput)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'API Key wajib diisi untuk konfigurasi baru.']);
                exit;
            }

            $encKey = SAGMailer::encrypt_credential($apiKeyInput);
            if (empty($encKey)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal mengenkripsi API Key.']);
                exit;
            }

            $stmt = mysqli_prepare($conn, "INSERT INTO ai_providers (provider, api_key_encrypted, model, priority, is_primary, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssssii", $provider, $encKey, $model, $priority, $isPrimary, $isActive);
            mysqli_stmt_execute($stmt);
            $msg = "Konfigurasi provider AI baru berhasil ditambahkan.";
        }

        // Audit log
        $now = date('d-m-Y H:i:s') . ' WIB';
        $admin_name = $_SESSION['nama'] ?? $_SESSION['username'] ?? ('Admin ID ' . $_SESSION['user_id']);
        set_setting($conn, 'chatbot_last_changed_at', $now);
        set_setting($conn, 'chatbot_last_changed_by', $admin_name);

        // Sinkronisasi data ke runtime backend
        sync_all_ai_configs($conn);

        echo json_encode([
            'success' => true,
            'message' => $msg
        ]);
        break;

    // -------------------------------------------------------------
    // TOGGLE AKTIF / NONAKTIF PROVIDER
    // -------------------------------------------------------------
    case 'toggle_provider_status':
        $id = (int)($_POST['id'] ?? 0);
        $q = mysqli_query($conn, "SELECT is_active, provider FROM ai_providers WHERE id = $id LIMIT 1");
        $row = mysqli_fetch_assoc($q);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Konfigurasi tidak ditemukan.']);
            exit;
        }

        $newActive = ((int)$row['is_active'] === 1) ? 0 : 1;
        $stmt = mysqli_prepare($conn, "UPDATE ai_providers SET is_active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $newActive, $id);
        mysqli_stmt_execute($stmt);

        sync_all_ai_configs($conn);

        $statusText = $newActive ? 'diaktifkan' : 'dinonaktifkan';
        echo json_encode([
            'success' => true,
            'message' => "Konfigurasi provider {$row['provider']} berhasil $statusText.",
            'is_active' => $newActive
        ]);
        break;

    // -------------------------------------------------------------
    // SET SEBAGAI PROVIDER UTAMA
    // -------------------------------------------------------------
    case 'set_primary_provider':
        $id = (int)($_POST['id'] ?? 0);
        // Reset semua provider lain ke cadangan
        mysqli_query($conn, "UPDATE ai_providers SET is_primary = 0, priority = 'cadangan'");
        // Set id ini ke utama dan aktifkan
        $stmt = mysqli_prepare($conn, "UPDATE ai_providers SET is_primary = 1, priority = 'utama', is_active = 1 WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        sync_all_ai_configs($conn);

        echo json_encode([
            'success' => true,
            'message' => 'Provider berhasil dijadikan sebagai AI Utama.'
        ]);
        break;

    // -------------------------------------------------------------
    // HAPUS KONFIGURASI PROVIDER
    // -------------------------------------------------------------
    case 'delete_provider':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM ai_providers WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        $ok = mysqli_stmt_execute($stmt);

        if ($ok) {
            sync_all_ai_configs($conn);
            echo json_encode([
                'success' => true,
                'message' => 'Konfigurasi provider berhasil dihapus.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus konfigurasi.']);
        }
        break;

    // -------------------------------------------------------------
    // UJI KONEKSI API KEY (DARI DAFTAR ATAU DARI MODAL)
    // -------------------------------------------------------------
    case 'test_connection':
        $id = (int)($_POST['id'] ?? 0);
        $provider = strtolower(trim($_POST['provider'] ?? ''));
        $apiKeyInput = trim($_POST['api_key'] ?? '');
        $model = trim($_POST['model'] ?? '');

        $keyToTest = $apiKeyInput;

        // Jika id diberikan dan api_key kosong di input, ambil dari DB
        if ($id > 0 && empty($keyToTest)) {
            $stmt = mysqli_prepare($conn, "SELECT provider, api_key_encrypted, model FROM ai_providers WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $item = mysqli_fetch_assoc($res);
            if ($item) {
                $provider = strtolower($item['provider']);
                $model = !empty($model) ? $model : $item['model'];
                $keyToTest = SAGMailer::decrypt_credential($item['api_key_encrypted']);
            }
        }

        if (empty($keyToTest)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'API Key belum diisi. Masukkan API Key terlebih dahulu untuk melakukan pengujian.'
            ]);
            exit;
        }

        if (empty($model)) {
            $model = ($provider === 'groq') ? 'openai/gpt-oss-20b' : 'gemini-1.5-flash';
        }

        $testSuccess = false;
        $testMsg = '';

        if ($provider === 'groq') {
            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $keyToTest,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => 'Test connection ping. Reply with pong.']
                ],
                'max_tokens' => 10
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($code === 200) {
                $testSuccess = true;
                $testMsg = "Koneksi berhasil. Model: $model (HTTP 200 OK).";
            } else {
                $errDetail = '';
                if ($res) {
                    $json = json_decode($res, true);
                    $errDetail = $json['error']['message'] ?? "HTTP Status $code";
                } else {
                    $errDetail = $err ?: "HTTP Status $code";
                }
                if ($code === 401 || $code === 403) {
                    $errDetail = 'API Key tidak valid.';
                } elseif ($code === 404) {
                    $errDetail = 'Model tidak tersedia.';
                } elseif ($code === 429) {
                    $errDetail = 'Rate limit tercapai.';
                }
                $testMsg = "Koneksi gagal: $errDetail";
            }
        } else {
            // Google Gemini
            $cleanModel = ltrim($model, '/');
            $cleanModel = preg_replace('/^models\//i', '', $cleanModel);
            // Gunakan query param ?key= untuk kompatibilitas penuh dengan Google API
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($cleanModel) . ":generateContent?key=" . urlencode($keyToTest);

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => 'Ping']]]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 5
                ]
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            // Log teknis aman (tanpa API key)
            $safeLog = sprintf(
                "[%s] TEST_CONN Gemini | Model: %s | HTTP: %d | CurlErr: %s\n",
                date('Y-m-d H:i:s'),
                $cleanModel,
                $code,
                $err ?: 'none'
            );
            @file_put_contents(__DIR__ . '/chatbot-backend/ai_test.log', $safeLog, FILE_APPEND);

            if ($code === 200) {
                $testSuccess = true;
                $testMsg = "Koneksi Gemini berhasil. Model: $cleanModel (HTTP 200 OK).";
            } else {
                $errDetail = '';
                $rawMsg = '';
                if ($res) {
                    $json = json_decode($res, true);
                    $rawMsg = $json['error']['message'] ?? '';
                }

                if ($code === 400) {
                    if (stripos($rawMsg, 'API key not valid') !== false || stripos($rawMsg, 'API_KEY_INVALID') !== false) {
                        $errDetail = 'API Key Gemini tidak valid.';
                    } else {
                        $errDetail = 'Format request Gemini tidak valid' . ($rawMsg ? ': ' . substr($rawMsg, 0, 100) : '.');
                    }
                } elseif ($code === 401 || $code === 403) {
                    $errDetail = 'API Key Gemini tidak valid atau tidak memiliki akses.';
                } elseif ($code === 404) {
                    $errDetail = "Model Gemini '$cleanModel' tidak tersedia untuk API Key ini.";
                } elseif ($code === 429) {
                    $errDetail = 'Batas penggunaan (Rate Limit / Quota) Gemini tercapai.';
                } elseif ($code === 0) {
                    if (stripos($err, 'timed out') !== false) {
                        $errDetail = 'Koneksi ke endpoint Gemini timeout. Model mungkin sedang mengalami latency tinggi atau tidak merespon.';
                    } else {
                        $errDetail = 'Endpoint Gemini tidak dapat dihubungi: ' . ($err ?: 'Network error.');
                    }
                } else {
                    $errDetail = "Google Gemini mengembalikan HTTP $code" . ($rawMsg ? ": " . substr($rawMsg, 0, 120) : '.');
                }

                $testMsg = "Koneksi gagal: $errDetail";
            }
        }

        // Update status uji coba ke database jika id ada
        if ($id > 0) {
            $testStatusStr = $testSuccess ? 'berhasil' : 'gagal';
            $stmt = mysqli_prepare($conn, "UPDATE ai_providers SET last_test_at = NOW(), last_test_status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $testStatusStr, $id);
            mysqli_stmt_execute($stmt);
        }

        echo json_encode([
            'success' => $testSuccess,
            'message' => $testMsg
        ]);
        break;

    // -------------------------------------------------------------
    // AKTIFKAN CHATBOT
    // -------------------------------------------------------------
    case 'enable':
        // 1. Periksa kesehatan backend sebelum mengaktifkan
        $health = check_node_health();

        if (!$health['online']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Backend chatbot belum tersedia. Periksa layanan Node.js.',
                'backend_online' => false
            ]);
            exit;
        }

        // 2. Pastikan minimal ada 1 provider AI yang aktif
        $q_act = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM ai_providers WHERE is_active = 1");
        $row_act = mysqli_fetch_assoc($q_act);
        if ((int)($row_act['cnt'] ?? 0) === 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengaktifkan chatbot: Belum ada provider AI yang aktif. Tambahkan atau aktifkan minimal satu provider AI di bawah terlebih dahulu.',
                'backend_online' => true
            ]);
            exit;
        }

        // 3. Simpan status aktif ke database secara persisten
        set_setting($conn, 'chatbot_enabled', '1');
        $now = date('d-m-Y H:i:s') . ' WIB';
        $admin_name = $_SESSION['nama'] ?? $_SESSION['username'] ?? ('Admin ID ' . $_SESSION['user_id']);
        set_setting($conn, 'chatbot_last_changed_at', $now);
        set_setting($conn, 'chatbot_last_changed_by', $admin_name);

        echo json_encode([
            'success' => true,
            'message' => 'Chatbot AI berhasil diaktifkan. Pengguna kini dapat menggunakan asisten AI.',
            'status'  => 'aktif'
        ]);
        break;

    // -------------------------------------------------------------
    // NONAKTIFKAN CHATBOT
    // -------------------------------------------------------------
    case 'disable':
        set_setting($conn, 'chatbot_enabled', '0');
        $now = date('d-m-Y H:i:s') . ' WIB';
        $admin_name = $_SESSION['nama'] ?? $_SESSION['username'] ?? ('Admin ID ' . $_SESSION['user_id']);
        set_setting($conn, 'chatbot_last_changed_at', $now);
        set_setting($conn, 'chatbot_last_changed_by', $admin_name);

        echo json_encode([
            'success' => true,
            'message' => 'Chatbot AI berhasil dinonaktifkan. Seluruh permintaan percakapan baru dihentikan.',
            'status'  => 'nonaktif'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action tidak valid.'
        ]);
        break;
}
