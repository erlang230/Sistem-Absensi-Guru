<?php
// ============================================================
// chatbot.php  -  Endpoint Chatbot SAG (PHP + MySQL)
// Dibuat terpisah agar tidak mengganggu halaman lain.
// Menerima POST JSON, membaca data nyata dari database,
// dan mengembalikan JSON { reply: "..." }
// ============================================================

// ─── Header CORS & JSON ──────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['reply' => 'Metode tidak diizinkan.']);
    exit;
}

// ─── Baca input ──────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$message  = isset($body['message'])  ? trim($body['message'])  : '';
$role     = isset($body['role'])     ? trim($body['role'])      : '';
$namaUser = isset($body['namaUser']) ? trim($body['namaUser'])  : '';
$idGuru   = isset($body['idGuru'])   ? intval($body['idGuru'])  : 0;

if ($message === '') {
    echo json_encode(['reply' => 'Pesan tidak boleh kosong.']);
    exit;
}

// ─── Koneksi database ────────────────────────────────────────
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'db_absensi_guru_ai';

$conn = @mysqli_connect($host, $user, $pass, $db);
$dbOk = ($conn !== false);

// Periksa status aktif/nonaktif chatbot dari database
if ($dbOk) {
    $q_status = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'chatbot_enabled' LIMIT 1");
    if ($q_status && $row = mysqli_fetch_assoc($q_status)) {
        if ($row['nilai_pengaturan'] === '0') {
            http_response_code(403);
            echo json_encode(['reply' => 'Chatbot AI saat ini sedang dinonaktifkan oleh Administrator.']);
            exit;
        }
    }
}

// ─── Utilitas ────────────────────────────────────────────────
function hariIni(): string {
    // Format sesuai ENUM jadwal: Senin, Selasa, …
    $hariEn = date('l'); // English day name
    $map = [
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
        'Sunday'    => 'Minggu',
    ];
    return $map[$hariEn] ?? $hariEn;
}

function tanggalIndo(string $tgl): string {
    $ts = strtotime($tgl);
    $bulan = [
        1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
        5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
        9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
    ];
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function sanitize(string $s): string {
    return htmlspecialchars_decode(strip_tags($s));
}

// ─── Pembaca data dari DB ─────────────────────────────────────

/** Jadwal mengajar hari ini untuk guru tertentu */
function getJadwalHariIni(mysqli $conn, int $idGuru): array {
    $hari = hariIni();
    $stmt = $conn->prepare(
        'SELECT j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas
         FROM jadwal j
         JOIN mata_pelajaran m ON m.id_mapel = j.id_mapel
         JOIN kelas k ON k.id_kelas = j.id_kelas
         WHERE j.id_guru = ? AND j.hari = ?
         ORDER BY j.jam_mulai'
    );
    if (!$stmt) return [];
    $stmt->bind_param('is', $idGuru, $hari);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Semua jadwal hari ini (untuk admin/kepsek) */
function getJadwalHariIniSemua(mysqli $conn): array {
    $hari = hariIni();
    $stmt = $conn->prepare(
        'SELECT g.nama_guru, j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas
         FROM jadwal j
         JOIN guru g ON g.id_guru = j.id_guru
         JOIN mata_pelajaran m ON m.id_mapel = j.id_mapel
         JOIN kelas k ON k.id_kelas = j.id_kelas
         WHERE j.hari = ?
         ORDER BY j.jam_mulai, g.nama_guru'
    );
    if (!$stmt) return [];
    $stmt->bind_param('s', $hari);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Cari jadwal berdasarkan filter hari, nama mapel, nama guru, atau nama kelas */
function cariJadwal(mysqli $conn, ?string $hari = null, ?string $mapel = null, ?string $guru = null, ?string $kelas = null): array {
    $where = [];
    $params = [];
    $types = '';

    if ($hari) {
        $where[] = 'j.hari = ?';
        $params[] = $hari;
        $types .= 's';
    }
    if ($mapel) {
        $where[] = 'LOWER(m.nama_mapel) LIKE ?';
        $params[] = '%' . strtolower($mapel) . '%';
        $types .= 's';
    }
    if ($guru) {
        $where[] = 'LOWER(g.nama_guru) LIKE ?';
        $params[] = '%' . strtolower($guru) . '%';
        $types .= 's';
    }
    if ($kelas) {
        $where[] = 'LOWER(k.nama_kelas) LIKE ?';
        $params[] = '%' . strtolower($kelas) . '%';
        $types .= 's';
    }

    $sql = 'SELECT j.hari, j.jam_mulai, j.jam_selesai, g.nama_guru, m.nama_mapel, k.nama_kelas
            FROM jadwal j
            JOIN guru g ON g.id_guru = j.id_guru
            JOIN mata_pelajaran m ON m.id_mapel = j.id_mapel
            JOIN kelas k ON k.id_kelas = j.id_kelas';
    
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai, k.nama_kelas";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Absensi hari ini satu guru */
function getAbsensiGuru(mysqli $conn, int $idGuru): ?array {
    $stmt = $conn->prepare(
        'SELECT status, jam_masuk, jam_pulang, metode_verifikasi
         FROM absensi
         WHERE id_guru = ? AND tanggal = CURDATE()'
    );
    if (!$stmt) return null;
    $stmt->bind_param('i', $idGuru);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Rekap absensi hari ini (untuk admin/kepsek) */
function getRekapHariIni(mysqli $conn): array {
    $row = $conn->query(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "Hadir") AS hadir,
            SUM(status = "Terlambat") AS terlambat,
            SUM(status = "Izin") AS izin,
            SUM(status = "Sakit") AS sakit,
            SUM(status = "Alpha") AS alpha
         FROM absensi WHERE tanggal = CURDATE()'
    )->fetch_assoc();
    return $row ?: [];
}

/** Jumlah total guru aktif */
function getTotalGuru(mysqli $conn): int {
    $r = $conn->query(
        'SELECT COUNT(*) as total FROM users WHERE role = "guru" AND status = "aktif"'
    )->fetch_assoc();
    return (int)($r['total'] ?? 0);
}

/** Guru yang belum absen hari ini */
function getGureBelumAbsen(mysqli $conn): array {
    $rows = $conn->query(
        'SELECT u.nama FROM users u
         WHERE u.role = "guru" AND u.status = "aktif"
           AND u.id NOT IN (
               SELECT g2.id_user FROM absensi a
               JOIN guru g2 ON g2.id_guru = a.id_guru
               WHERE a.tanggal = CURDATE()
           )
         ORDER BY u.nama'
    )->fetch_all(MYSQLI_ASSOC);
    return $rows ?: [];
}

/** Status registrasi wajah seluruh guru (untuk admin/kepsek) */
function getStatusRegistrasiWajah(mysqli $conn): array {
    $res = $conn->query(
        "SELECT id_guru, nama_guru, nip, (face_data IS NOT NULL AND face_data != '') AS terdaftar
         FROM guru
         ORDER BY nama_guru ASC"
    );
    if (!$res) return ['terdaftar' => [], 'belum' => [], 'total' => 0];

    $terdaftar = [];
    $belum = [];
    while ($row = $res->fetch_assoc()) {
        if ($row['terdaftar']) {
            $terdaftar[] = $row;
        } else {
            $belum[] = $row;
        }
    }
    return [
        'terdaftar' => $terdaftar,
        'belum'     => $belum,
        'total'     => count($terdaftar) + count($belum)
    ];
}

/** Cek status registrasi wajah satu guru berdasarkan nama atau ID guru */
function cekStatusWajahGuru(mysqli $conn, string|int $identifier): ?array {
    if (is_numeric($identifier)) {
        $stmt = $conn->prepare("SELECT id_guru, nama_guru, nip, (face_data IS NOT NULL AND face_data != '') AS terdaftar FROM guru WHERE id_guru = ?");
        $id = (int)$identifier;
        $stmt->bind_param("i", $id);
    } else {
        $stmt = $conn->prepare("SELECT id_guru, nama_guru, nip, (face_data IS NOT NULL AND face_data != '') AS terdaftar FROM guru WHERE LOWER(nama_guru) LIKE ? LIMIT 1");
        $param = '%' . strtolower($identifier) . '%';
        $stmt->bind_param("s", $param);
    }
    if (!$stmt) return null;
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// ─── Pencocok kata kunci ──────────────────────────────────────
function mengandung(string $msg, array $kata): bool {
    $msg = mb_strtolower($msg);
    foreach ($kata as $k) {
        if (mb_strpos($msg, mb_strtolower($k)) !== false) return true;
    }
    return false;
}

// ─── Logika utama: bangun respons ─────────────────────────────
$msg   = mb_strtolower($msg_raw = $message);
$reply = null;
$hari  = hariIni();
$tgl   = tanggalIndo(date('Y-m-d'));

// --- Sapaan ---
if (mengandung($msg, ['halo','hai','hello','hi','selamat pagi','selamat siang','selamat malam','selamat sore','good morning'])) {
    $greet = ($namaUser !== '') ? ", $namaUser" : '';
    $roleLabel = match($role) {
        'admin'          => 'Admin',
        'guru'           => 'Guru',
        'kepala_sekolah' => 'Kepala Sekolah',
        default          => ''
    };
    $roleStr = $roleLabel ? " ($roleLabel)" : '';
    $reply = "Halo{$greet}{$roleStr}! 👋\nSaya Asisten SAG. Hari ini {$hari}, {$tgl}.\nSilakan tanyakan tentang jadwal, absensi, laporan, atau fitur sistem lainnya.";
}

// --- Pertanyaan spesifik guru / mapel / hari / jadwal ---
elseif (
    mengandung($msg, ['guru', 'siapa', 'mengajar', 'jadwal', 'pelajaran', 'mata pelajaran', 'kelas']) &&
    (
        mengandung($msg, ['senin','selasa','rabu','kamis','jumat','sabtu','minggu']) ||
        mengandung($msg, ['matematika','fisika','inggris','sejarah'])
    )
) {
    if (!$dbOk) {
        $reply = "Koneksi database gagal. Data jadwal tidak dapat diakses.";
    } else {
        // Deteksi hari yang ditanyakan
        $hariTanya = null;
        $daftarHari = ['senin'=>'Senin', 'selasa'=>'Selasa', 'rabu'=>'Rabu', 'kamis'=>'Kamis', 'jumat'=>'Jumat', 'sabtu'=>'Sabtu', 'minggu'=>'Minggu'];
        foreach ($daftarHari as $k => $v) {
            if (mb_strpos($msg, $k) !== false) {
                $hariTanya = $v;
                break;
            }
        }

        // Deteksi mapel yang ditanyakan
        $mapelTanya = null;
        $daftarMapel = ['matematika'=>'Matematika', 'fisika'=>'Fisika', 'inggris'=>'Bahasa Inggris', 'sejarah'=>'Sejarah'];
        foreach ($daftarMapel as $k => $v) {
            if (mb_strpos($msg, $k) !== false) {
                $mapelTanya = $v;
                break;
            }
        }

        // Deteksi guru yang ditanyakan jika ada
        $guruTanya = null;
        $daftarGuru = ['rafi'=>'Rafi', 'ali'=>'Ali', 'yur'=>'Yur', 'soleh'=>'Soleh'];
        foreach ($daftarGuru as $k => $v) {
            if (mb_strpos($msg, $k) !== false) {
                $guruTanya = $v;
                break;
            }
        }

        // Cari di DB
        $rows = cariJadwal($conn, $hariTanya, $mapelTanya, $guruTanya);

        if (empty($rows)) {
            $ket = [];
            if ($mapelTanya) $ket[] = "mata pelajaran $mapelTanya";
            if ($hariTanya) $ket[] = "hari $hariTanya";
            if ($guruTanya) $ket[] = "guru $guruTanya";
            $reply = "Tidak ditemukan jadwal untuk " . (!empty($ket) ? implode(' pada ', $ket) : 'pencarian tersebut') . " di database.";
        } else {
            $header = "Berikut informasi jadwal berdasarkan database SAG:\n";
            $lines = [];
            foreach ($rows as $r) {
                $jam = substr($r['jam_mulai'],0,5) . '–' . substr($r['jam_selesai'],0,5);
                $lines[] = "• {$r['hari']} ({$jam}): {$r['nama_guru']} mengajar {$r['nama_mapel']} di kelas {$r['nama_kelas']}";
            }
            $reply = $header . implode("\n", $lines);
        }
    }
}

// --- Jadwal hari ini ---
elseif (mengandung($msg, ['jadwal hari ini','jadwal mengajar hari ini','jadwal saya hari ini','mengajar hari ini','jadwal sekarang'])) {
    if (!$dbOk) {
        $reply = "Tidak dapat mengambil jadwal karena koneksi database gagal. Periksa konfigurasi XAMPP.";
    } elseif ($role === 'guru' && $idGuru > 0) {
        $rows = getJadwalHariIni($conn, $idGuru);
        if (empty($rows)) {
            $reply = "Anda tidak memiliki jadwal mengajar hari ini ({$hari}, {$tgl}).";
        } else {
            $lines = ["Jadwal mengajar Anda hari ini ({$hari}, {$tgl}):"];
            foreach ($rows as $r) {
                $jam  = substr($r['jam_mulai'],0,5) . '–' . substr($r['jam_selesai'],0,5);
                $lines[] = "• {$jam} · {$r['nama_mapel']} · Kelas {$r['nama_kelas']}";
            }
            $reply = implode("\n", $lines);
        }
    } else {
        // admin / kepsek: semua jadwal hari ini
        $rows = $dbOk ? getJadwalHariIniSemua($conn) : [];
        if (empty($rows)) {
            $reply = "Tidak ada jadwal mengajar yang tercatat untuk hari ini ({$hari}, {$tgl}).";
        } else {
            $lines = ["Jadwal mengajar hari ini ({$hari}, {$tgl}):"];
            foreach ($rows as $r) {
                $jam  = substr($r['jam_mulai'],0,5) . '–' . substr($r['jam_selesai'],0,5);
                $lines[] = "• {$r['nama_guru']} · {$r['nama_mapel']} · Kelas {$r['nama_kelas']} ({$jam})";
            }
            $reply = implode("\n", $lines);
        }
    }
}

// --- Jadwal umum (tanpa "hari ini") ---
elseif (mengandung($msg, ['jadwal','mengajar','pelajaran','mata pelajaran'])) {
    if (!$dbOk) {
        $reply = "Koneksi database gagal. Jadwal tidak dapat diambil saat ini.";
    } elseif ($role === 'guru' && $idGuru > 0) {
        $rows = getJadwalHariIni($conn, $idGuru);
        $hariStr = hariIni();
        if (empty($rows)) {
            $reply = "Tidak ada jadwal Anda untuk hari {$hariStr}.\nUntuk melihat jadwal lengkap seminggu, buka menu Jadwal Mengajar.";
        } else {
            $lines = ["Jadwal Anda hari {$hariStr}:"];
            foreach ($rows as $r) {
                $jam = substr($r['jam_mulai'],0,5) . '–' . substr($r['jam_selesai'],0,5);
                $lines[] = "• {$jam} · {$r['nama_mapel']} · Kelas {$r['nama_kelas']}";
            }
            $lines[] = "\nUntuk jadwal hari lain, buka menu Jadwal Mengajar.";
            $reply = implode("\n", $lines);
        }
    } else {
        $reply = "Jadwal lengkap tersedia di menu Jadwal Mengajar.\nAdmin dapat menambah atau mengubah jadwal melalui menu Tambah Jadwal.";
    }
}

// --- Status absensi diri sendiri ---
elseif (mengandung($msg, ['absensi saya','status absensi','sudah absen','belum absen','absen hari ini','kehadiran saya'])) {
    if (!$dbOk) {
        $reply = "Koneksi database gagal. Status absensi tidak dapat diambil.";
    } elseif ($role === 'guru' && $idGuru > 0) {
        $absen = getAbsensiGuru($conn, $idGuru);
        if ($absen === null) {
            $reply = "Anda belum tercatat absensi hari ini ({$tgl}).\nSilakan lakukan absensi wajah melalui menu Absensi.";
        } else {
            $masuk  = $absen['jam_masuk']  ? substr($absen['jam_masuk'],0,5)  : '-';
            $pulang = $absen['jam_pulang'] ? substr($absen['jam_pulang'],0,5) : 'belum tercatat';
            $reply  = "Status absensi Anda hari ini ({$tgl}):\n"
                    . "• Status   : {$absen['status']}\n"
                    . "• Jam masuk: {$masuk}\n"
                    . "• Jam pulang: {$pulang}\n"
                    . "• Metode   : {$absen['metode_verifikasi']}";
        }
    } else {
        $reply = "Untuk melihat status absensi pribadi, silakan login sebagai Guru.";
    }
}

// --- Rekap absensi hari ini (admin/kepsek) ---
elseif (mengandung($msg, ['rekap','rekap absensi','rekap hari ini','statistik absensi','total absensi','berapa guru hadir','siapa tidak hadir','guru belum absen'])) {
    if (!$dbOk) {
        $reply = "Koneksi database gagal. Rekap absensi tidak dapat diambil.";
    } elseif (in_array($role, ['admin','kepala_sekolah'], true)) {
        $rekap      = getRekapHariIni($conn);
        $totalGuru  = getTotalGuru($conn);
        $belumAbsen = getGureBelumAbsen($conn);
        $sudahAbsen = (int)($rekap['total'] ?? 0);

        $lines = [
            "📊 Rekap Absensi Guru – {$tgl}:",
            "• Total guru aktif : {$totalGuru}",
            "• Sudah absen      : {$sudahAbsen}",
            "• Hadir            : " . ($rekap['hadir']     ?? 0),
            "• Terlambat        : " . ($rekap['terlambat'] ?? 0),
            "• Izin             : " . ($rekap['izin']      ?? 0),
            "• Sakit            : " . ($rekap['sakit']     ?? 0),
            "• Alpha            : " . ($rekap['alpha']     ?? 0),
        ];
        if (!empty($belumAbsen)) {
            $lines[] = "\nBelum absen (" . count($belumAbsen) . "):";
            foreach ($belumAbsen as $g) {
                $lines[] = "• " . sanitize($g['nama']);
            }
        } else {
            $lines[] = "\n✅ Semua guru sudah absen hari ini.";
        }
        $reply = implode("\n", $lines);
    } else {
        // guru — tampilkan status diri sendiri
        if ($idGuru > 0 && $dbOk) {
            $absen = getAbsensiGuru($conn, $idGuru);
            if ($absen === null) {
                $reply = "Anda belum tercatat absensi hari ini ({$tgl}).\nSilakan lakukan absensi wajah melalui menu Absensi.";
            } else {
                $masuk  = $absen['jam_masuk']  ? substr($absen['jam_masuk'],0,5)  : '-';
                $pulang = $absen['jam_pulang'] ? substr($absen['jam_pulang'],0,5) : 'belum tercatat';
                $reply  = "Status absensi Anda hari ini ({$tgl}):\n"
                        . "• Status    : {$absen['status']}\n"
                        . "• Jam masuk : {$masuk}\n"
                        . "• Jam pulang: {$pulang}";
            }
        } else {
            $reply = "Rekap seluruh guru hanya bisa dilihat oleh Admin atau Kepala Sekolah.";
        }
    }
}

// --- Cara absensi / cara menggunakan ---
elseif (mengandung($msg, ['cara absensi','cara absen','bagaimana absen','langkah absensi','cara menggunakan','cara pakai','cara memakai'])) {
    $reply = "Cara melakukan absensi di SAG:\n"
           . "1. Login dengan akun Anda.\n"
           . "2. Buka menu Absensi di sidebar kiri.\n"
           . "3. Klik tombol Mulai Absensi.\n"
           . "4. Arahkan wajah Anda ke kamera — sistem akan mengenali otomatis.\n"
           . "5. Tunggu konfirmasi \"Absensi Berhasil\".\n\n"
           . "Pastikan wajah sudah terdaftar terlebih dahulu melalui menu Registrasi Wajah.";
}

// --- Status Registrasi Wajah Guru (Data Aktual DB) ---
elseif (
    mengandung($msg, ['wajah', 'face', 'terdaftar', 'registrasi']) &&
    (
        mengandung($msg, ['belum', 'sudah', 'status', 'siapa', 'berapa', 'apakah', 'daftar', 'total', 'semua', 'cek', 'rafi', 'ali', 'soleh', 'yur']) ||
        mengandung($msg, ['status wajah', 'wajahnya', 'registrasi wajah', 'data wajah'])
    ) &&
    !mengandung($msg, ['cara daftar', 'cara registrasi', 'panduan registrasi', 'bagaimana daftar'])
) {
    if (!$dbOk) {
        $reply = "Koneksi database gagal. Status registrasi wajah tidak dapat diambil.";
    } elseif ($role === 'guru') {
        // Hak akses Guru: fokus ke status diri sendiri atau panduan
        if ($idGuru > 0) {
            $dataSelf = cekStatusWajahGuru($conn, $idGuru);
            if ($dataSelf) {
                $statusStr = $dataSelf['terdaftar'] ? "Sudah Terdaftar ✅" : "Belum Terdaftar ⚠️";
                $ket = $dataSelf['terdaftar']
                    ? "Wajah Anda telah tersimpan di sistem dan siap digunakan untuk verifikasi absensi wajah (Face Recognition)."
                    : "Wajah Anda belum terdaftar. Silakan lakukan registrasi wajah melalui menu Registrasi Wajah di sidebar kiri.";
                $reply = "Status Registrasi Wajah Anda ({$dataSelf['nama_guru']}):\n• Status: {$statusStr}\n\n{$ket}";
            } else {
                $reply = "Data guru Anda tidak ditemukan di sistem.";
            }
        } else {
            $reply = "Untuk memeriksa status registrasi wajah pribadi Anda, pastikan akun guru Anda sudah terhubung.";
        }
    } else {
        // Hak akses Admin & Kepala Sekolah: akses penuh statistik dan daftar aktual guru
        $statusData = getStatusRegistrasiWajah($conn);
        $totalAll = $statusData['total'];
        $listTerdaftar = $statusData['terdaftar'];
        $listBelum = $statusData['belum'];
        $countTerdaftar = count($listTerdaftar);
        $countBelum = count($listBelum);

        // 1. Menanyakan yang BELUM terdaftar
        if (mengandung($msg, ['belum terdaftar', 'belum daftar', 'belum ada wajah', 'belum registrasi'])) {
            if ($countBelum === 0) {
                $reply = "Semua guru ({$totalAll} guru) sudah mendaftarkan wajahnya di sistem. Tidak ada guru yang belum terdaftar.";
            } else {
                $lines = [
                    "📋 Daftar Guru yang Wajahnya BELUM Terdaftar ({$countBelum} dari {$totalAll} guru):"
                ];
                foreach ($listBelum as $b) {
                    $lines[] = "• " . sanitize($b['nama_guru']) . " (NIP: " . ($b['nip'] ?? '-') . ")";
                }
                $lines[] = "\nUntuk mendaftarkan wajah guru, buka menu Registrasi Wajah di sidebar.";
                $reply = implode("\n", $lines);
            }
        }
        // 2. Menanyakan jumlah / berapa guru terdaftar/belum (harus sebelum cek nama atau sudah terdaftar)
        elseif (mengandung($msg, ['berapa', 'jumlah', 'total', 'statistik'])) {
            if (mengandung($msg, ['sudah', 'terdaftar']) && !mengandung($msg, ['belum'])) {
                $reply = "📊 Jumlah guru yang wajahnya SUDAH terdaftar: {$countTerdaftar} dari {$totalAll} guru.\n"
                       . "Daftar guru: " . implode(', ', array_map(fn($g) => $g['nama_guru'], $listTerdaftar));
            } elseif (mengandung($msg, ['belum'])) {
                $reply = "📊 Jumlah guru yang wajahnya BELUM terdaftar: {$countBelum} dari {$totalAll} guru.\n"
                       . "Daftar guru: " . implode(', ', array_map(fn($g) => $g['nama_guru'], $listBelum));
            } else {
                $lines = [
                    "📊 Status Registrasi Wajah Guru Saat Ini:",
                    "• Total Guru Terdaftar: {$totalAll} guru",
                    "• Sudah Terdaftar Wajah: {$countTerdaftar} guru (" . implode(', ', array_map(fn($g) => $g['nama_guru'], $listTerdaftar)) . ")",
                    "• Belum Terdaftar Wajah: {$countBelum} guru (" . implode(', ', array_map(fn($g) => $g['nama_guru'], $listBelum)) . ")"
                ];
                $reply = implode("\n", $lines);
            }
        }
        // 3. Menanyakan yang SUDAH terdaftar (semua / daftar yang sudah terdaftar)
        elseif (
            (mengandung($msg, ['sudah terdaftar', 'sudah daftar', 'sudah ada wajah', 'sudah registrasi']) ||
            (mengandung($msg, ['siapa']) && mengandung($msg, ['sudah']))) &&
            !mengandung($msg, ['apakah'])
        ) {
            if ($countTerdaftar === 0) {
                $reply = "Belum ada guru yang mendaftarkan wajahnya di sistem.";
            } else {
                $lines = [
                    "✅ Daftar Guru yang Wajahnya SUDAH Terdaftar ({$countTerdaftar} dari {$totalAll} guru):"
                ];
                foreach ($listTerdaftar as $t) {
                    $lines[] = "• " . sanitize($t['nama_guru']) . " (NIP: " . ($t['nip'] ?? '-') . ")";
                }
                $reply = implode("\n", $lines);
            }
        }
        // 4. Cek apakah menanyakan guru tertentu (misal: "Apakah Rafi Erlangga sudah terdaftar?")
        else {
            $namaDicari = null;
            // Cek nama guru yang ada di database terlebih dahulu
            foreach (array_merge($listTerdaftar, $listBelum) as $g) {
                $parts = explode(' ', strtolower($g['nama_guru']));
                $firstPart = $parts[0];
                if (mb_strpos($msg, strtolower($g['nama_guru'])) !== false || (strlen($firstPart) >= 3 && mb_strpos($msg, $firstPart) !== false)) {
                    $namaDicari = $g['nama_guru'];
                    break;
                }
            }

            if ($namaDicari) {
                $guruFound = cekStatusWajahGuru($conn, $namaDicari);
                if ($guruFound) {
                    $stat = $guruFound['terdaftar'] ? "SUDAH TERDAFTAR ✅" : "BELUM TERDAFTAR ⚠️";
                    $reply = "Status Registrasi Wajah untuk {$guruFound['nama_guru']} (NIP: {$guruFound['nip']}):\n"
                           . "• Status: {$stat}\n"
                           . ($guruFound['terdaftar'] ? "Wajah guru ini sudah siap digunakan untuk absensi wajah." : "Guru ini belum mendaftarkan sampel wajahnya di sistem.");
                } else {
                    $reply = "Guru dengan nama \"{$namaDicari}\" tidak ditemukan dalam database SAG.";
                }
            } else {
                // 5. Tampilkan seluruh status registrasi wajah guru
                $lines = [
                    "📋 Status Registrasi Wajah Seluruh Guru ({$totalAll} guru):"
                ];
                foreach (array_merge($listTerdaftar, $listBelum) as $g) {
                    $icon = $g['terdaftar'] ? "✅ Sudah Terdaftar" : "⚠️ Belum Terdaftar";
                    $lines[] = "• " . sanitize($g['nama_guru']) . " : " . $icon;
                }
                $lines[] = "\nRingkasan: {$countTerdaftar} sudah terdaftar, {$countBelum} belum terdaftar.";
                $reply = implode("\n", $lines);
            }
        }
    }
}

// --- Cara registrasi wajah / panduan ---
elseif (mengandung($msg, ['registrasi wajah','daftar wajah','register wajah','face recognition','face register','data wajah'])) {
    $reply = "Cara mendaftarkan wajah:\n"
           . "1. Login sebagai Guru atau Admin.\n"
           . "2. Buka menu Registrasi Wajah di sidebar kiri.\n"
           . "3. Posisikan wajah di tengah kamera dengan pencahayaan yang cukup.\n"
           . "4. Ikuti instruksi yang muncul di layar.\n"
           . "5. Setelah selesai, wajah Anda tersimpan dan siap digunakan untuk absensi.";
}

// --- Laporan / export ---
elseif (mengandung($msg, ['laporan','rekap bulanan','export','unduh','download','pdf','excel','cetak'])) {
    if (in_array($role, ['admin','kepala_sekolah'], true)) {
        $reply = "Laporan absensi tersedia di menu Laporan.\nAnda dapat mengunduh dalam format:\n• PDF — untuk cetak atau arsip resmi\n• Excel — untuk analisis data lebih lanjut\n\nFilter laporan tersedia berdasarkan bulan, guru, dan status kehadiran.";
    } else {
        $reply = "Fitur laporan dan ekspor tersedia untuk Admin dan Kepala Sekolah.\nJika perlu rekap absensi Anda sendiri, hubungi admin sekolah.";
    }
}

// --- Data guru ---
elseif (mengandung($msg, ['data guru','tambah guru','edit guru','hapus guru','daftar guru','jumlah guru'])) {
    if (!$dbOk) {
        $reply = "Koneksi database gagal.";
    } elseif ($role === 'admin') {
        $total = getTotalGuru($conn);
        $reply = "Saat ini terdapat {$total} guru aktif di sistem.\n"
               . "Pengelolaan data guru tersedia di menu Data Guru:\n"
               . "• Tambah guru baru\n"
               . "• Edit data (nama, NIP, email, kontak)\n"
               . "• Nonaktifkan atau hapus akun guru";
    } else {
        $reply = "Pengelolaan data guru hanya bisa dilakukan oleh Admin.\nUntuk informasi profil Anda, lihat menu Profil.";
    }
}

// --- Login / akun ---
elseif (mengandung($msg, ['login','masuk','password','kata sandi','lupa password','akun','username'])) {
    $reply = "Untuk login ke SAG:\n"
           . "• Akses halaman login di browser Anda\n"
           . "• Masukkan username dan password sesuai peran\n\n"
           . "Akun tersedia untuk tiga peran:\n"
           . "• Admin — kelola seluruh data sistem\n"
           . "• Guru — absensi mandiri & lihat jadwal\n"
           . "• Kepala Sekolah — monitoring & laporan\n\n"
           . "Lupa password? Hubungi Admin sekolah untuk reset.";
}

// --- Dashboard ---
elseif (mengandung($msg, ['dashboard','beranda','home','halaman utama'])) {
    $reply = match($role) {
        'admin'          => "Dashboard Admin menampilkan:\n• Total guru aktif\n• Absensi hari ini (hadir, terlambat, izin, dll.)\n• Grafik kehadiran mingguan\n• Notifikasi guru yang belum absen",
        'guru'           => "Dashboard Guru menampilkan:\n• Status absensi Anda hari ini\n• Jadwal mengajar hari ini\n• Riwayat absensi 7 hari terakhir",
        'kepala_sekolah' => "Dashboard Kepala Sekolah menampilkan:\n• Ringkasan kehadiran seluruh guru\n• Statistik absensi per bulan\n• Daftar guru dengan kehadiran rendah",
        default          => "Dashboard menampilkan ringkasan kehadiran dan jadwal sesuai peran login Anda."
    };
}

// --- Monitoring ---
elseif (mengandung($msg, ['monitoring','pantau','real-time','kehadiran guru'])) {
    if (in_array($role, ['admin','kepala_sekolah'], true)) {
        $reply = "Menu Monitoring Absensi memungkinkan Anda melihat kehadiran guru secara real-time.\n"
               . "Data diperbarui otomatis setiap kali guru melakukan absensi.\n"
               . "Buka menu Monitoring Absensi di sidebar.";
    } else {
        $reply = "Fitur monitoring kehadiran seluruh guru tersedia untuk Admin dan Kepala Sekolah.";
    }
}

// --- Terima kasih ---
elseif (mengandung($msg, ['terima kasih','makasih','thanks','thank you','thx'])) {
    $reply = "Sama-sama! 😊 Jika ada pertanyaan lain seputar sistem absensi, jangan ragu untuk bertanya.";
}

// --- Bantuan umum ---
elseif (mengandung($msg, ['help','bantuan','bisa apa','apa saja','fitur','menu'])) {
    $roleFeatures = match($role) {
        'guru'           => "• Cek status absensi Anda hari ini\n• Lihat jadwal mengajar hari ini\n• Panduan absensi wajah\n• Info registrasi wajah",
        'admin'          => "• Rekap absensi hari ini\n• Jadwal mengajar semua guru\n• Info pengelolaan data guru\n• Info laporan & export",
        'kepala_sekolah' => "• Rekap absensi hari ini\n• Statistik kehadiran guru\n• Jadwal mengajar hari ini\n• Info laporan & export",
        default          => "• Informasi fitur sistem\n• Panduan penggunaan\n• Info absensi dan jadwal"
    };
    $nama_str = $namaUser ? ", {$namaUser}" : '';
    $reply = "Halo{$nama_str}! Saya dapat membantu Anda dengan:\n{$roleFeatures}\n\nCoba tanyakan, misalnya:\n\"Jadwal saya hari ini\"\n\"Status absensi saya\"\n\"Rekap absensi hari ini\"";
}

// --- Fallback ---
if ($reply === null) {
    $reply = "Maaf, saya belum dapat menjawab pertanyaan itu secara spesifik.\n\n"
           . "Saya bisa membantu Anda dengan:\n"
           . "• Jadwal mengajar hari ini\n"
           . "• Status absensi\n"
           . "• Rekap kehadiran guru\n"
           . "• Panduan cara absensi\n"
           . "• Info fitur dan menu sistem\n\n"
           . "Coba tanyakan salah satunya!";
}

// ─── Tutup koneksi & kirim respons ───────────────────────────
if ($dbOk) mysqli_close($conn);

echo json_encode(['reply' => $reply], JSON_UNESCAPED_UNICODE);
