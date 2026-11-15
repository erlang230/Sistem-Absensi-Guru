<?php
/**
 * API Pengingat Absensi Guru untuk Administrator
 * Sistem Absensi Guru (SAG)
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Mailer.php';
require_once __DIR__ . '/proses_alpa.php';

// Pastikan user sudah login dan berstatus admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Fitur ini hanya dapat diakses oleh Administrator.'
    ]);
    exit;
}

date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d');
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {
    // -------------------------------------------------------------
    // 1. KIRIM PENGINGAT MANUAL KE GURU
    // -------------------------------------------------------------
    case 'kirim':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan.']);
            exit;
        }

        $id_guru = (int)($_POST['id_guru'] ?? 0);
        $tanggal = trim($_POST['tanggal'] ?? $today);

        if ($id_guru <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID Guru tidak valid.']);
            exit;
        }

        // 1. Periksa data guru dan akun email (Google / Profil Guru)
        $stmt_g = mysqli_prepare($conn, "
            SELECT g.id_guru, g.nama_guru, g.nip, g.email AS email_guru, gg.email_google, gg.status_tautan
            FROM guru g
            LEFT JOIN guru_google gg ON g.id_guru = gg.id_guru
            WHERE g.id_guru = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt_g, "i", $id_guru);
        mysqli_stmt_execute($stmt_g);
        $guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_g));
        mysqli_stmt_close($stmt_g);

        if (!$guru) {
            echo json_encode(['success' => false, 'message' => 'Data guru tidak ditemukan.']);
            exit;
        }

        // 2. Tentukan alamat email tujuan guru
        $target_email = '';
        if (!empty($guru['email_google']) && $guru['status_tautan'] === 'terhubung') {
            $target_email = trim($guru['email_google']);
        } elseif (!empty($guru['email_guru']) && filter_var(trim($guru['email_guru']), FILTER_VALIDATE_EMAIL)) {
            $target_email = trim($guru['email_guru']);
        }

        if (empty($target_email) || !filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Alamat email guru belum tersedia. Silakan lengkapi alamat email guru terlebih dahulu pada menu Data Guru.'
            ]);
            exit;
        }

        // 2b. Periksa kesiapan akun pengirim email sistem
        $chk_mail = SAGMailer::check_status();
        if (!$chk_mail['status']) {
            echo json_encode([
                'success' => false,
                'message' => 'Email pengirim sistem belum siap: ' . $chk_mail['message']
            ]);
            exit;
        }

        // 3. Periksa status absensi aktual pada tanggal tersebut (real-time check)
        $stmt_ab = mysqli_prepare($conn, "SELECT status, jam_masuk FROM absensi WHERE id_guru = ? AND tanggal = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_ab, "is", $id_guru, $tanggal);
        mysqli_stmt_execute($stmt_ab);
        $absensi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ab));
        mysqli_stmt_close($stmt_ab);

        // Ambil konfigurasi aturan waktu dari database
        $q_set = mysqli_query($conn, "SELECT nama_pengaturan, nilai_pengaturan FROM pengaturan WHERE nama_pengaturan IN ('jam_masuk_sekolah', 'toleransi_keterlambatan', 'batas_waktu_alpa')");
        $pengaturan = [];
        while ($r_set = mysqli_fetch_assoc($q_set)) {
            $pengaturan[$r_set['nama_pengaturan']] = $r_set['nilai_pengaturan'];
        }
        $jam_masuk_sekolah = $pengaturan['jam_masuk_sekolah'] ?? '07:00:00';
        $toleransi_menit   = intval($pengaturan['toleransi_keterlambatan'] ?? 15);
        $batas_waktu_alpa  = $pengaturan['batas_waktu_alpa'] ?? '09:00:00';

        // Hitung batas keterlambatan = jam masuk + toleransi
        $batas_terlambat = date('H:i:s', strtotime("+{$toleransi_menit} minutes", strtotime($jam_masuk_sekolah)));

        // Waktu saat ini (Asia/Jakarta)
        $waktu_sekarang = date('H:i:s');
        $is_today = ($tanggal === $today);

        // Kategori status absensi: 'sudah_absen', 'belum_absen', 'terlambat', 'tidak_absen'
        $status_kategori = 'belum_absen';

        if ($absensi && !empty($absensi['status'])) {
            $st = $absensi['status'];
            if (in_array($st, ['Hadir', 'Terlambat', 'Izin', 'Sakit'])) {
                $status_teks = ($st === 'Hadir') ? 'Sudah Absen' : $st;
                echo json_encode([
                    'success' => false,
                    'message' => 'Guru sudah melakukan absensi (Status: ' . $status_teks . '). Pengingat tidak dapat dikirimkan.'
                ]);
                exit;
            } elseif ($st === 'Alpha') {
                $status_kategori = 'tidak_absen';
            }
        } else {
            // Belum ada record absensi di tabel absensi
            if ($is_today) {
                if (strtotime($waktu_sekarang) > strtotime($batas_waktu_alpa)) {
                    $status_kategori = 'tidak_absen';
                } elseif (strtotime($waktu_sekarang) > strtotime($batas_terlambat)) {
                    $status_kategori = 'terlambat';
                } else {
                    $status_kategori = 'belum_absen';
                }
            } else {
                // Untuk hari lampau yang belum absen, otomatis tidak absen
                $status_kategori = 'tidak_absen';
            }
        }

        // Aturan D: TIDAK ABSEN / ALPA
        // Batas waktu absensi sudah ditutup. Guru belum melakukan absensi.
        // Jangan lagi mengirimkan pengingat absensi karena periode absensi sudah berakhir.
        if ($status_kategori === 'tidak_absen') {
            echo json_encode([
                'success' => false,
                'message' => 'Batas waktu absensi hari ini telah ditutup (' . substr($batas_waktu_alpa, 0, 5) . ' WIB). Status guru tercatat sebagai TIDAK ABSEN / ALPA sehingga pengingat tidak dapat dikirimkan.'
            ]);
            exit;
        }

        // 4. Rate Limiting: Hindari spam pengingat dalam 5 menit terakhir untuk guru & tanggal yang sama
        $stmt_spam = mysqli_prepare($conn, "
            SELECT id, created_at FROM riwayat_pengingat 
            WHERE id_guru = ? AND tanggal_absensi = ? AND status_pengiriman = 'berhasil' 
              AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt_spam, "is", $id_guru, $tanggal);
        mysqli_stmt_execute($stmt_spam);
        $res_spam = mysqli_stmt_get_result($stmt_spam);
        $recent_reminder = mysqli_fetch_assoc($res_spam);
        mysqli_stmt_close($stmt_spam);

        if ($recent_reminder) {
            echo json_encode([
                'success' => false,
                'message' => 'Pengingat baru saja dikirim ke guru ini dalam 5 menit terakhir. Mohon berikan jeda sebelum mengirim pengingat berikutnya.'
            ]);
            exit;
        }

        // Format tanggal bahasa Indonesia
        $hari_indo = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
        ];
        $bulan_indo = [
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        ];
        $ts = strtotime($tanggal);
        $tgl_formatted = $hari_indo[date('l', $ts)] . ', ' . date('d', $ts) . ' ' . $bulan_indo[date('m', $ts)] . ' ' . date('Y', $ts);

        // 5. Kirim Email Pengingat sesuai status kategori
        $send_res = SAGMailer::send_reminder($target_email, $guru['nama_guru'], $tgl_formatted, $conn, $status_kategori);

        $admin_user_id = (int)$_SESSION['user_id'];
        $status_pengiriman = $send_res['success'] ? 'berhasil' : 'gagal';
        $keterangan = $send_res['success'] 
            ? 'Pengingat absensi (' . $status_kategori . ') berhasil dikirim ke ' . $target_email
            : 'Email gagal dikirim: ' . ($send_res['error'] ?? 'Kesalahan SMTP');

        // 6. Catat riwayat pengiriman ke database (tanpa kredensial)
        $stmt_log = mysqli_prepare($conn, "
            INSERT INTO riwayat_pengingat (id_guru, dikirim_oleh, jenis_pengingat, email_tujuan, tanggal_absensi, status_pengiriman, keterangan)
            VALUES (?, ?, 'manual', ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt_log, "iissss", $id_guru, $admin_user_id, $target_email, $tanggal, $status_pengiriman, $keterangan);
        mysqli_stmt_execute($stmt_log);
        mysqli_stmt_close($stmt_log);

        if ($send_res['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Email pengingat berhasil dikirim ke ' . htmlspecialchars($guru['nama_guru']) . ' (' . htmlspecialchars($target_email) . ').',
                'waktu' => date('H:i:s d/m/Y')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $keterangan
            ]);
        }
        break;

    // -------------------------------------------------------------
    // 2. LIHAT RIWAYAT PENGIRIMAN PENGINGAT
    // -------------------------------------------------------------
    case 'riwayat':
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $stmt_hist = mysqli_prepare($conn, "
            SELECT rp.id, rp.id_guru, g.nama_guru, g.nip, rp.jenis_pengingat, rp.email_tujuan, 
                   rp.tanggal_absensi, rp.status_pengiriman, rp.keterangan, rp.created_at,
                   u.nama as pengirim_nama
            FROM riwayat_pengingat rp
            JOIN guru g ON rp.id_guru = g.id_guru
            LEFT JOIN users u ON rp.dikirim_oleh = u.id
            ORDER BY rp.id DESC
            LIMIT ?
        ");
        mysqli_stmt_bind_param($stmt_hist, "i", $limit);
        mysqli_stmt_execute($stmt_hist);
        $res_hist = mysqli_stmt_get_result($stmt_hist);
        $rows = [];
        while ($r = mysqli_fetch_assoc($res_hist)) {
            $rows[] = [
                'id' => (int)$r['id'],
                'nama_guru' => $r['nama_guru'],
                'nip' => $r['nip'],
                'jenis_pengingat' => $r['jenis_pengingat'],
                'email_tujuan' => $r['email_tujuan'],
                'tanggal_absensi' => $r['tanggal_absensi'],
                'status_pengiriman' => $r['status_pengiriman'],
                'keterangan' => $r['keterangan'],
                'pengirim' => $r['pengirim_nama'] ?? 'Sistem Otomatis',
                'created_at' => date('d/m/Y H:i', strtotime($r['created_at']))
            ];
        }
        mysqli_stmt_close($stmt_hist);

        echo json_encode([
            'success' => true,
            'data' => $rows
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
        break;
}
