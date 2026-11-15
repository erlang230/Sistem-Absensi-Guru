<?php
/**
 * proses_alpa.php
 * ============================================================
 * Engine Otomasi Status Alpa / Tidak Hadir
 * ============================================================
 * Fungsi utama:
 *   proses_alpa_otomatis($conn, $tanggal)
 *
 * Aturan:
 * 1. Hanya berjalan jika waktu sekarang sudah melewati batas_waktu_alpa
 * 2. Hanya menandai guru yang SAMA SEKALI belum ada catatan absensi hari ini
 * 3. Guru yang sudah Hadir / Terlambat / Izin / Sakit TIDAK diubah
 * 4. Tidak membuat duplikat (INSERT IGNORE atau cek dulu)
 * 5. Tidak berjalan pada hari Minggu (libur umum)
 * 6. Aman dijalankan berulang kali
 * ============================================================
 */

/**
 * Jalankan proses otomatis Alpa untuk tanggal tertentu.
 *
 * @param mysqli $conn   Koneksi database aktif
 * @param string $tanggal  Format 'Y-m-d', default hari ini
 * @return array ['inserted' => int, 'skipped' => int, 'reason' => string]
 */
function proses_alpa_otomatis($conn, $tanggal = null) {
    date_default_timezone_set('Asia/Jakarta');

    if ($tanggal === null) {
        $tanggal = date('Y-m-d');
    }

    $result = ['inserted' => 0, 'skipped' => 0, 'reason' => ''];

    // ── 1. Jangan jalankan pada hari Minggu ─────────────────────────
    $hari_dalam_seminggu = date('N', strtotime($tanggal)); // 1=Mon...7=Sun
    if ($hari_dalam_seminggu == 7) {
        $result['reason'] = 'Hari Minggu - tidak ada kewajiban hadir';
        return $result;
    }

    // ── 2. Ambil konfigurasi batas waktu Alpa ────────────────────────
    $q_batas = mysqli_prepare($conn,
        "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'batas_waktu_alpa'");
    mysqli_stmt_execute($q_batas);
    $row_batas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_batas));
    $batas_waktu_alpa = $row_batas['nilai_pengaturan'] ?? '09:00:00';

    // ── 3. Cek apakah saat ini sudah melewati batas waktu ─────────────
    // Untuk tanggal hari ini: bandingkan jam sekarang vs batas
    // Untuk tanggal lampau: selalu proses (sudah pasti lewat)
    $today = date('Y-m-d');
    if ($tanggal === $today) {
        $waktu_sekarang = date('H:i:s');
        if (strtotime($waktu_sekarang) <= strtotime($batas_waktu_alpa)) {
            $result['reason'] = "Batas waktu Alpa ($batas_waktu_alpa) belum terlewati";
            return $result;
        }
    }
    // Jika tanggal yang diproses adalah tanggal lampau, skip cek waktu

    // ── 4. Ambil semua guru aktif ─────────────────────────────────────
    $q_guru = mysqli_query($conn,
        "SELECT g.id_guru FROM guru g 
         JOIN users u ON g.id_user = u.id 
         WHERE u.status = 'aktif'");

    if (!$q_guru) {
        $result['reason'] = 'Gagal mengambil data guru';
        return $result;
    }

    while ($guru = mysqli_fetch_assoc($q_guru)) {
        $id_guru = (int)$guru['id_guru'];

        // ── 5. Cek apakah guru sudah punya catatan absensi hari ini ──────
        $q_cek = mysqli_prepare($conn,
            "SELECT id_absensi FROM absensi WHERE id_guru = ? AND tanggal = ? LIMIT 1");
        mysqli_stmt_bind_param($q_cek, "is", $id_guru, $tanggal);
        mysqli_stmt_execute($q_cek);
        $cek_result = mysqli_stmt_get_result($q_cek);

        if (mysqli_num_rows($cek_result) > 0) {
            // Guru sudah punya catatan (Hadir/Terlambat/Izin/Sakit/Alpha)
            // JANGAN diubah
            $result['skipped']++;
            continue;
        }

        // ── 6. Insert status Alpha untuk guru yang belum absen ───────────
        // metode_verifikasi = 'Manual' karena ini proses sistem
        $q_insert = mysqli_prepare($conn,
            "INSERT IGNORE INTO absensi 
             (id_guru, tanggal, jam_masuk, jam_pulang, status, metode_verifikasi)
             VALUES (?, ?, NULL, NULL, 'Alpha', 'Sistem')");
        
        mysqli_stmt_bind_param($q_insert, "is", $id_guru, $tanggal);

        if (mysqli_stmt_execute($q_insert)) {
            if (mysqli_stmt_affected_rows($q_insert) > 0) {
                $result['inserted']++;
            } else {
                $result['skipped']++; // UNIQUE constraint mencegah duplikat
            }
        }
    }

    $result['reason'] = $result['inserted'] > 0
        ? "Berhasil mencatat {$result['inserted']} guru sebagai Alpha"
        : "Semua guru sudah punya catatan absensi";

    return $result;
}

/**
 * Hitung statistik kehadiran lengkap untuk tanggal tertentu,
 * termasuk jumlah "Belum Absen" dan "Alpha" yang sudah tercatat.
 *
 * @param mysqli $conn
 * @param string $tanggal Format 'Y-m-d'
 * @return array Statistik kehadiran
 */
function get_statistik_kehadiran($conn, $tanggal) {
    date_default_timezone_set('Asia/Jakarta');

    // Total guru aktif
    $q_total = mysqli_query($conn,
        "SELECT COUNT(*) as total FROM guru g 
         JOIN users u ON g.id_user = u.id 
         WHERE u.status = 'aktif'");
    $total_guru = (int)mysqli_fetch_assoc($q_total)['total'];

    // Statistik dari tabel absensi
    $q_stat = mysqli_prepare($conn,
        "SELECT 
            SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
            SUM(CASE WHEN status = 'Alpha' THEN 1 ELSE 0 END) as alpha,
            SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) as izin,
            SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
            COUNT(*) as total_tercatat
         FROM absensi WHERE tanggal = ?");
    mysqli_stmt_bind_param($q_stat, "s", $tanggal);
    mysqli_stmt_execute($q_stat);
    $stat = mysqli_fetch_assoc(mysqli_stmt_get_result($q_stat));

    $hadir      = (int)($stat['hadir'] ?? 0);
    $terlambat  = (int)($stat['terlambat'] ?? 0);
    $alpha      = (int)($stat['alpha'] ?? 0);
    $izin       = (int)($stat['izin'] ?? 0);
    $sakit      = (int)($stat['sakit'] ?? 0);
    $tercatat   = (int)($stat['total_tercatat'] ?? 0);

    // Belum Absen = guru aktif yang belum punya catatan sama sekali
    $belum_absen = max(0, $total_guru - $tercatat);

    // Batas waktu alpa
    $q_batas = mysqli_prepare($conn,
        "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'batas_waktu_alpa'");
    mysqli_stmt_execute($q_batas);
    $row_batas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_batas));
    $batas_waktu_alpa = $row_batas['nilai_pengaturan'] ?? '09:00:00';

    // Apakah batas waktu sudah terlewati (untuk hari ini)?
    $today = date('Y-m-d');
    $batas_terlewati = ($tanggal < $today) ||
        ($tanggal === $today && strtotime(date('H:i:s')) > strtotime($batas_waktu_alpa));

    return [
        'total_guru'       => $total_guru,
        'hadir'            => $hadir,
        'terlambat'        => $terlambat,
        'alpha'            => $alpha,
        'izin'             => $izin,
        'sakit'            => $sakit,
        'belum_absen'      => $belum_absen,
        'batas_waktu_alpa' => $batas_waktu_alpa,
        'batas_terlewati'  => $batas_terlewati,
        'persen_hadir'     => $total_guru > 0
            ? round((($hadir + $terlambat) / $total_guru) * 100) : 0,
    ];
}

/**
 * Dapatkan status kehadiran seorang guru untuk tanggal tertentu,
 * termasuk "Belum Absen" jika belum ada catatan.
 *
 * @param mysqli $conn
 * @param int    $id_guru
 * @param string $tanggal Format 'Y-m-d'
 * @return array ['status'=>string, 'jam_masuk'=>string|null, 'jam_pulang'=>string|null, 'batas_terlewati'=>bool]
 */
function get_status_guru($conn, $id_guru, $tanggal) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil catatan absensi
    $q = mysqli_prepare($conn,
        "SELECT status, jam_masuk, jam_pulang FROM absensi WHERE id_guru = ? AND tanggal = ? LIMIT 1");
    mysqli_stmt_bind_param($q, "is", $id_guru, $tanggal);
    mysqli_stmt_execute($q);
    $absen = mysqli_fetch_assoc(mysqli_stmt_get_result($q));

    // Batas waktu alpa
    $q_batas = mysqli_prepare($conn,
        "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'batas_waktu_alpa'");
    mysqli_stmt_execute($q_batas);
    $row_batas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_batas));
    $batas_waktu_alpa = $row_batas['nilai_pengaturan'] ?? '09:00:00';

    $today = date('Y-m-d');
    $batas_terlewati = ($tanggal < $today) ||
        ($tanggal === $today && strtotime(date('H:i:s')) > strtotime($batas_waktu_alpa));

    if ($absen) {
        return [
            'status'          => $absen['status'],
            'jam_masuk'       => $absen['jam_masuk'],
            'jam_pulang'      => $absen['jam_pulang'],
            'batas_terlewati' => $batas_terlewati,
            'batas_waktu_alpa'=> $batas_waktu_alpa,
            'ada_catatan'     => true,
        ];
    }

    // Belum ada catatan
    $status_tampil = $batas_terlewati ? 'Alpha' : 'Belum Absen';

    return [
        'status'          => $status_tampil,
        'jam_masuk'       => null,
        'jam_pulang'      => null,
        'batas_terlewati' => $batas_terlewati,
        'batas_waktu_alpa'=> $batas_waktu_alpa,
        'ada_catatan'     => false,
    ];
}
