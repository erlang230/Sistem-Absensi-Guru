<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once 'config/database.php';

// Periksa status aktif/nonaktif chatbot dari database
$q_status = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'chatbot_enabled' LIMIT 1");
if ($q_status && $row = mysqli_fetch_assoc($q_status)) {
    if ($row['nilai_pengaturan'] === '0') {
        http_response_code(403);
        echo json_encode([
            'status' => 'disabled',
            'reply'  => 'Chatbot AI saat ini sedang dinonaktifkan oleh Administrator.'
        ]);
        exit;
    }
}

require_once 'proses_alpa.php';

// Jalankan engine alpa setiap chatbot diakses (hari ini)
$today_chat = date('Y-m-d');
proses_alpa_otomatis($conn, $today_chat);

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$role = $_SESSION['role'] ?? 'pengguna';
$user_id = $_SESSION['user_id'];
$nama_user = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Pengguna';

// Kumpulkan konteks berdasarkan DB
$context = [];
$context['role_saya'] = ($role === 'admin') ? 'Admin' : (($role === 'kepala_sekolah') ? 'Kepala Sekolah' : 'Guru');
$context['nama_saya'] = $nama_user;
$context['hari_dan_tanggal_hari_ini'] = date('d-m-Y');

$hari_indo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$hari_sekarang = $hari_indo[date('l')];
$besok_indo = $hari_indo[date('l', strtotime('+1 day'))];
$context['nama_hari_ini'] = $hari_sekarang;

if ($role === 'guru') {
    $stmt = mysqli_prepare($conn, "SELECT id_guru, nama_guru, (face_data IS NOT NULL AND face_data != '') AS status_wajah FROM guru WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $guru = mysqli_fetch_assoc($res);
    
    if ($guru) {
        $id_guru = $guru['id_guru'];
        $context['nama_guru_login'] = $guru['nama_guru'];
        $context['status_registrasi_wajah_saya'] = $guru['status_wajah'] ? 'Sudah Terdaftar (siap absensi wajah)' : 'Belum Terdaftar (perlu registrasi wajah di menu Registrasi Wajah)';
        
        // Jadwal mengajar guru login hari ini
        $stmt_jadwal = mysqli_prepare($conn, "SELECT j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas 
            FROM jadwal j 
            JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
            JOIN kelas k ON j.id_kelas = k.id_kelas 
            WHERE j.id_guru = ? AND j.hari = ? ORDER BY j.jam_mulai");
        mysqli_stmt_bind_param($stmt_jadwal, "is", $id_guru, $hari_sekarang);
        mysqli_stmt_execute($stmt_jadwal);
        $res_jadwal = mysqli_stmt_get_result($stmt_jadwal);
        $jadwal_hari_ini = [];
        while($row = mysqli_fetch_assoc($res_jadwal)) {
            $jadwal_hari_ini[] = substr($row['jam_mulai'], 0, 5) . ' - ' . substr($row['jam_selesai'], 0, 5) . ': ' . $row['nama_mapel'] . ' (Kelas ' . $row['nama_kelas'] . ')';
        }
        $context['jadwal_saya_hari_ini'] = empty($jadwal_hari_ini) ? 'Tidak ada jadwal mengajar untuk hari ini.' : implode('; ', $jadwal_hari_ini);

        // Jadwal mengajar guru login besok
        $stmt_jadwal_besok = mysqli_prepare($conn, "SELECT j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas 
            FROM jadwal j 
            JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
            JOIN kelas k ON j.id_kelas = k.id_kelas 
            WHERE j.id_guru = ? AND j.hari = ? ORDER BY j.jam_mulai");
        mysqli_stmt_bind_param($stmt_jadwal_besok, "is", $id_guru, $besok_indo);
        mysqli_stmt_execute($stmt_jadwal_besok);
        $res_jadwal_besok = mysqli_stmt_get_result($stmt_jadwal_besok);
        $jadwal_besok = [];
        while($row = mysqli_fetch_assoc($res_jadwal_besok)) {
            $jadwal_besok[] = substr($row['jam_mulai'], 0, 5) . ' - ' . substr($row['jam_selesai'], 0, 5) . ': ' . $row['nama_mapel'] . ' (Kelas ' . $row['nama_kelas'] . ')';
        }
        $context['jadwal_saya_besok'] = empty($jadwal_besok) ? 'Tidak ada jadwal mengajar untuk besok.' : implode('; ', $jadwal_besok);

        // Seluruh jadwal mengajar guru login sepanjang minggu
        $stmt_all_jadwal = mysqli_prepare($conn, "SELECT j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas 
            FROM jadwal j 
            JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
            JOIN kelas k ON j.id_kelas = k.id_kelas 
            WHERE j.id_guru = ? 
            ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai");
        mysqli_stmt_bind_param($stmt_all_jadwal, "i", $id_guru);
        mysqli_stmt_execute($stmt_all_jadwal);
        $res_all_jadwal = mysqli_stmt_get_result($stmt_all_jadwal);
        $semua_jadwal_saya = [];
        while($row = mysqli_fetch_assoc($res_all_jadwal)) {
            $semua_jadwal_saya[] = 'Hari ' . $row['hari'] . ' (' . substr($row['jam_mulai'], 0, 5) . ' - ' . substr($row['jam_selesai'], 0, 5) . '): ' . $row['nama_mapel'] . ' di ' . $row['nama_kelas'];
        }
        $context['seluruh_jadwal_saya_mingguan'] = empty($semua_jadwal_saya) ? 'Belum ada jadwal mengajar yang terdaftar.' : implode('; ', $semua_jadwal_saya);

        // Semua mata pelajaran yang diajar oleh guru login
        $stmt_mapel = mysqli_prepare($conn, "SELECT DISTINCT m.nama_mapel 
            FROM jadwal j 
            JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
            WHERE j.id_guru = ?");
        mysqli_stmt_bind_param($stmt_mapel, "i", $id_guru);
        mysqli_stmt_execute($stmt_mapel);
        $res_mapel = mysqli_stmt_get_result($stmt_mapel);
        $mapel_diajar = [];
        while($row = mysqli_fetch_assoc($res_mapel)) {
            $mapel_diajar[] = $row['nama_mapel'];
        }
        $context['mata_pelajaran_yang_saya_ajar'] = empty($mapel_diajar) ? 'Belum ada mata pelajaran.' : implode(', ', $mapel_diajar);

        // Absensi hari ini
        $hari_ini_date = date('Y-m-d');
        $stmt_absen_hari_ini = mysqli_prepare($conn, "SELECT jam_masuk, jam_pulang, status FROM absensi WHERE id_guru = ? AND tanggal = ?");
        mysqli_stmt_bind_param($stmt_absen_hari_ini, "is", $id_guru, $hari_ini_date);
        mysqli_stmt_execute($stmt_absen_hari_ini);
        $res_absen_hari_ini = mysqli_stmt_get_result($stmt_absen_hari_ini);
        $absen_hari_ini = mysqli_fetch_assoc($res_absen_hari_ini);
        if ($absen_hari_ini) {
            $context['absensi_saya_hari_ini'] = "Status: " . $absen_hari_ini['status'] . ", Jam Masuk: " . ($absen_hari_ini['jam_masuk'] ?? '-') . ", Jam Pulang: " . ($absen_hari_ini['jam_pulang'] ?? '-');
        } else {
            $context['absensi_saya_hari_ini'] = "Belum absen hari ini.";
        }

        // Rekap absensi bulan ini (hadir, terlambat, dll)
        $bulan_ini = date('Y-m');
        $stmt_rekap = mysqli_prepare($conn, "SELECT status, COUNT(*) as jumlah FROM absensi WHERE id_guru = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ? GROUP BY status");
        mysqli_stmt_bind_param($stmt_rekap, "is", $id_guru, $bulan_ini);
        mysqli_stmt_execute($stmt_rekap);
        $res_rekap = mysqli_stmt_get_result($stmt_rekap);
        $rekap_bulan_ini = [];
        $total_absen = 0;
        $total_terlambat = 0;
        while($row = mysqli_fetch_assoc($res_rekap)) {
            $rekap_bulan_ini[] = $row['status'] . ": " . $row['jumlah'];
            $total_absen += $row['jumlah'];
            if ($row['status'] === 'Terlambat') {
                $total_terlambat += $row['jumlah'];
            }
        }
        $context['rekap_absensi_saya_bulan_ini'] = empty($rekap_bulan_ini) ? 'Belum ada absensi bulan ini.' : implode(', ', $rekap_bulan_ini);
        $context['jumlah_absensi_bulan_ini'] = $total_absen;
        $context['jumlah_terlambat_bulan_ini'] = $total_terlambat;

        // Riwayat absensi bulan ini
        $stmt_riwayat_bulan = mysqli_prepare($conn, "SELECT tanggal, jam_masuk, jam_pulang, status FROM absensi WHERE id_guru = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ? ORDER BY tanggal DESC");
        mysqli_stmt_bind_param($stmt_riwayat_bulan, "is", $id_guru, $bulan_ini);
        mysqli_stmt_execute($stmt_riwayat_bulan);
        $res_riwayat_bulan = mysqli_stmt_get_result($stmt_riwayat_bulan);
        $riwayat_bulan_ini = [];
        while($row = mysqli_fetch_assoc($res_riwayat_bulan)) {
            $riwayat_bulan_ini[] = "Tgl " . $row['tanggal'] . " (" . $row['status'] . ", masuk " . ($row['jam_masuk'] ?? '-') . ")";
        }
        $context['riwayat_absensi_bulan_ini'] = empty($riwayat_bulan_ini) ? 'Tidak ada riwayat bulan ini.' : implode('; ', $riwayat_bulan_ini);
    }
} else if ($role === 'admin' || $role === 'kepala_sekolah') {
    // Seluruh Jadwal Mengajar Hari Ini (Semua Guru)
    $stmt_jadwal_all = mysqli_prepare($conn, "SELECT j.hari, j.jam_mulai, j.jam_selesai, g.nama_guru, m.nama_mapel, k.nama_kelas 
        FROM jadwal j 
        JOIN guru g ON j.id_guru = g.id_guru 
        JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
        JOIN kelas k ON j.id_kelas = k.id_kelas 
        WHERE j.hari = ? 
        ORDER BY j.jam_mulai ASC, g.nama_guru ASC");
    mysqli_stmt_bind_param($stmt_jadwal_all, "s", $hari_sekarang);
    mysqli_stmt_execute($stmt_jadwal_all);
    $res_jadwal_all = mysqli_stmt_get_result($stmt_jadwal_all);
    $jadwal_hari_ini_semua = [];
    while($row = mysqli_fetch_assoc($res_jadwal_all)) {
        $jadwal_hari_ini_semua[] = "Guru: " . $row['nama_guru'] . " | Mapel: " . $row['nama_mapel'] . " | Kelas: " . $row['nama_kelas'] . " | Waktu: " . substr($row['jam_mulai'], 0, 5) . " - " . substr($row['jam_selesai'], 0, 5);
    }
    $context['jadwal_mengajar_seluruh_guru_hari_ini'] = empty($jadwal_hari_ini_semua) ? 'Tidak ada jadwal mengajar yang tercatat untuk hari ini (' . $hari_sekarang . ').' : implode('; ', $jadwal_hari_ini_semua);

    // Seluruh Master Jadwal Mengajar Sekolah (Hanya dimuat jika pengguna bertanya jadwal hari lain / umum)
    $lowerMsg = strtolower($message);
    if (preg_match('/(jadwal|senin|selasa|rabu|kamis|jumat|sabtu|minggu|besok|lusa|mingguan)/i', $lowerMsg)) {
        $res_master_jadwal = mysqli_query($conn, "SELECT j.hari, j.jam_mulai, j.jam_selesai, g.nama_guru, m.nama_mapel, k.nama_kelas 
            FROM jadwal j 
            JOIN guru g ON j.id_guru = g.id_guru 
            JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
            JOIN kelas k ON j.id_kelas = k.id_kelas 
            ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC");
        $master_jadwal = [];
        while($row = mysqli_fetch_assoc($res_master_jadwal)) {
            $master_jadwal[] = "Hari: " . $row['hari'] . " | Guru: " . $row['nama_guru'] . " | Mapel: " . $row['nama_mapel'] . " | Kelas: " . $row['nama_kelas'] . " | Waktu: " . substr($row['jam_mulai'], 0, 5) . " - " . substr($row['jam_selesai'], 0, 5);
        }
        $context['seluruh_jadwal_mengajar_sekolah'] = empty($master_jadwal) ? 'Belum ada data jadwal pelajaran yang terdaftar.' : implode('; ', $master_jadwal);
    }

    // Siapa saja guru yang hadir hari ini?
    $hari_ini_date = date('Y-m-d');
    $stmt_hadir = mysqli_prepare($conn, "SELECT g.nama_guru, a.jam_masuk, a.status FROM absensi a JOIN guru g ON a.id_guru = g.id_guru WHERE a.tanggal = ? ORDER BY a.status, a.jam_masuk");
    mysqli_stmt_bind_param($stmt_hadir, "s", $hari_ini_date);
    mysqli_stmt_execute($stmt_hadir);
    $res_hadir = mysqli_stmt_get_result($stmt_hadir);
    $guru_hadir = [];
    $guru_terlambat = [];
    $guru_alpha = [];
    $jumlah_hadir_tepat = 0;
    $jumlah_terlambat = 0;
    $jumlah_alpha = 0;
    while($row = mysqli_fetch_assoc($res_hadir)) {
        if ($row['status'] === 'Hadir') {
            $guru_hadir[] = $row['nama_guru'] . " (masuk: " . ($row['jam_masuk'] ?? '-') . ")";
            $jumlah_hadir_tepat++;
        } elseif ($row['status'] === 'Terlambat') {
            $guru_terlambat[] = $row['nama_guru'] . " (masuk: " . ($row['jam_masuk'] ?? '-') . ")";
            $jumlah_terlambat++;
        } elseif ($row['status'] === 'Alpha') {
            $guru_alpha[] = $row['nama_guru'];
            $jumlah_alpha++;
        }
    }
    $context['guru_hadir_tepat_waktu_hari_ini'] = empty($guru_hadir) ? 'Tidak ada.' : implode(', ', $guru_hadir);
    $context['jumlah_guru_hadir_tepat_waktu_hari_ini'] = $jumlah_hadir_tepat;
    $context['guru_terlambat_hari_ini'] = empty($guru_terlambat) ? 'Tidak ada.' : implode(', ', $guru_terlambat);
    $context['jumlah_guru_terlambat_hari_ini'] = $jumlah_terlambat;
    $context['guru_alpha_tidak_hadir_hari_ini'] = empty($guru_alpha) ? 'Tidak ada.' : implode(', ', $guru_alpha);
    $context['jumlah_guru_alpha_tidak_hadir_hari_ini'] = $jumlah_alpha;
    // Untuk backward compatibility:
    $semua_tercatat = array_merge(
        array_map(fn($g) => str_replace(' (masuk:', ' (Hadir, masuk:', $g), $guru_hadir),
        array_map(fn($g) => str_replace(' (masuk:', ' (Terlambat, masuk:', $g), $guru_terlambat),
        array_map(fn($g) => "$g (Alpha/Tidak Hadir)", $guru_alpha)
    );
    $context['guru_yang_hadir_hari_ini'] = empty($semua_tercatat) ? 'Belum ada guru yang hadir.' : implode(', ', $semua_tercatat);

    // Jumlah guru terdaftar
    $res_guru = mysqli_query($conn, "SELECT COUNT(*) as total_guru FROM guru g JOIN users u ON g.id_user = u.id WHERE u.status = 'aktif'");
    $data_guru = mysqli_fetch_assoc($res_guru);
    $total_guru = (int)($data_guru['total_guru'] ?? 0);
    $context['jumlah_guru_terdaftar'] = $total_guru;

    // Guru yang belum absen (belum ada catatan sama sekali hari ini)
    $stmt_belum = mysqli_prepare($conn, 
        "SELECT g.nama_guru FROM guru g 
         JOIN users u ON g.id_user = u.id
         WHERE u.status = 'aktif'
         AND g.id_guru NOT IN (SELECT id_guru FROM absensi WHERE tanggal = ?)
         ORDER BY g.nama_guru");
    mysqli_stmt_bind_param($stmt_belum, "s", $hari_ini_date);
    mysqli_stmt_execute($stmt_belum);
    $res_belum = mysqli_stmt_get_result($stmt_belum);
    $guru_belum_list = [];
    while ($rb = mysqli_fetch_assoc($res_belum)) {
        $guru_belum_list[] = $rb['nama_guru'];
    }
    $context['jumlah_guru_yang_belum_absen_hari_ini'] = count($guru_belum_list);
    $context['daftar_guru_belum_absen_hari_ini'] = empty($guru_belum_list) ? 'Semua guru sudah absen.' : implode(', ', $guru_belum_list);

    // Ambil batas waktu alpa
    $q_batas_chat = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'batas_waktu_alpa'");
    $batas_waktu_alpa_chat = mysqli_fetch_assoc($q_batas_chat)['nilai_pengaturan'] ?? '09:00:00';
    $context['batas_waktu_alpa'] = substr($batas_waktu_alpa_chat, 0, 5) . ' WIB';
    $context['batas_waktu_alpa_sudah_terlewati'] = (strtotime(date('H:i:s')) > strtotime($batas_waktu_alpa_chat)) ? 'Ya' : 'Belum';
    $context['jam_masuk_standar'] = '';
    $q_jam_masuk = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'jam_masuk_sekolah'");
    if ($q_jam_masuk) {
        $jm = mysqli_fetch_assoc($q_jam_masuk);
        $context['jam_masuk_standar'] = substr($jm['nilai_pengaturan'] ?? '07:00:00', 0, 5) . ' WIB';
    }

    // Rekap absensi semua guru bulan ini
    $bulan_ini = date('Y-m');
    $stmt_rekap_all = mysqli_prepare($conn, "SELECT status, COUNT(*) as jumlah FROM absensi WHERE DATE_FORMAT(tanggal, '%Y-%m') = ? GROUP BY status");
    mysqli_stmt_bind_param($stmt_rekap_all, "s", $bulan_ini);
    mysqli_stmt_execute($stmt_rekap_all);
    $res_rekap_all = mysqli_stmt_get_result($stmt_rekap_all);
    $rekap_semua = [];
    while($row = mysqli_fetch_assoc($res_rekap_all)) {
        $rekap_semua[] = $row['status'] . ": " . $row['jumlah'];
    }
    $context['rekap_absensi_semua_guru_bulan_ini'] = empty($rekap_semua) ? 'Belum ada data bulan ini.' : implode(', ', $rekap_semua);

    // Data mata pelajaran terdaftar
    $res_mapel_all = mysqli_query($conn, "SELECT nama_mapel, kode_mapel FROM mata_pelajaran");
    $data_mapel_all = [];
    while($row = mysqli_fetch_assoc($res_mapel_all)) {
        $data_mapel_all[] = $row['nama_mapel'] . " (" . $row['kode_mapel'] . ")";
    }
    $context['daftar_mata_pelajaran'] = empty($data_mapel_all) ? 'Tidak ada data mapel.' : implode(', ', $data_mapel_all);

    // Data kelas terdaftar
    $res_kelas = mysqli_query($conn, "SELECT nama_kelas, tingkat, jurusan FROM kelas");
    $data_kelas = [];
    while($row = mysqli_fetch_assoc($res_kelas)) {
        $data_kelas[] = $row['nama_kelas'] . " (" . $row['tingkat'] . " - " . $row['jurusan'] . ")";
    }
    $context['data_kelas'] = empty($data_kelas) ? 'Tidak ada data kelas.' : implode(', ', $data_kelas);

    // Status Registrasi Wajah Semua Guru (Sesuai Halaman Registrasi Wajah)
    $res_face = mysqli_query($conn, "SELECT id_guru, nama_guru, nip, (face_data IS NOT NULL AND face_data != '') AS terdaftar FROM guru ORDER BY nama_guru ASC");
    $guru_face_terdaftar = [];
    $guru_face_belum = [];
    $status_wajah_semua = [];
    while($row = mysqli_fetch_assoc($res_face)) {
        $status_teks = $row['terdaftar'] ? 'Sudah Terdaftar' : 'Belum Terdaftar';
        $status_wajah_semua[] = $row['nama_guru'] . " (NIP: " . ($row['nip'] ?? '-') . "): " . $status_teks;
        if ($row['terdaftar']) {
            $guru_face_terdaftar[] = $row['nama_guru'];
        } else {
            $guru_face_belum[] = $row['nama_guru'];
        }
    }
    $context['status_registrasi_wajah_seluruh_guru'] = implode('; ', $status_wajah_semua);
    $context['jumlah_guru_wajah_sudah_terdaftar'] = count($guru_face_terdaftar);
    $context['daftar_guru_wajah_sudah_terdaftar'] = empty($guru_face_terdaftar) ? 'Tidak ada' : implode(', ', $guru_face_terdaftar);
    $context['jumlah_guru_wajah_belum_terdaftar'] = count($guru_face_belum);
    $context['daftar_guru_wajah_belum_terdaftar'] = empty($guru_face_belum) ? 'Tidak ada (semua guru sudah terdaftar)' : implode(', ', $guru_face_belum);
}

// Kirim request ke node
$node_url = 'http://localhost:3000/api/chat';
$ch = curl_init($node_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 35);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'message' => $message,
    'role' => $role,
    'context' => $context
]));

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpcode ?: 500);
if ($response === false) {
    echo json_encode(['reply' => 'Gagal terhubung ke backend AI (Node.js). Pastikan server.js berjalan di port 3000.']);
} else {
    echo $response;
}
