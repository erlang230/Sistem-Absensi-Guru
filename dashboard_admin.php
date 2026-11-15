<?php
require_once 'auth.php';
check_login();
check_role('admin');

require_once 'config/database.php';

date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d');
$today_formatted = date('l, d F Y');

// Terjemahan nama hari & bulan bahasa Indonesia
$hari_indo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulan_indo = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli',
    'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober',
    'November' => 'November', 'December' => 'Desember'
];
$hari_ini_teks = $hari_indo[date('l')] . ', ' . date('d') . ' ' . $bulan_indo[date('F')] . ' ' . date('Y');

// ============================================================
// JALANKAN ENGINE ALPA OTOMATIS
// ============================================================
require_once 'proses_alpa.php';
proses_alpa_otomatis($conn, $today);

// ============================================================
// 1. STATISTIK UTAMA (DATA REAL DARI DATABASE)
// ============================================================

// Total Guru Aktif
$q_guru = mysqli_query($conn, "SELECT COUNT(*) as total FROM guru g JOIN users u ON g.id_user = u.id WHERE u.status = 'aktif'");
$total_guru = (int) mysqli_fetch_assoc($q_guru)['total'];

// Hadir Hari Ini (tepat waktu)
$q_hadir = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM absensi WHERE tanggal = ? AND status = 'Hadir'");
mysqli_stmt_bind_param($q_hadir, "s", $today);
mysqli_stmt_execute($q_hadir);
$hadir = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_hadir))['total'];

// Terlambat Hari Ini
$q_terlambat = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM absensi WHERE tanggal = ? AND status = 'Terlambat'");
mysqli_stmt_bind_param($q_terlambat, "s", $today);
mysqli_stmt_execute($q_terlambat);
$terlambat = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_terlambat))['total'];

// Alpha Hari Ini (tercatat di DB oleh sistem)
$q_alpha = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM absensi WHERE tanggal = ? AND status = 'Alpha'");
mysqli_stmt_bind_param($q_alpha, "s", $today);
mysqli_stmt_execute($q_alpha);
$alpha = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_alpha))['total'];

// Total yang sudah punya catatan hari ini
$q_tercatat = mysqli_prepare($conn, "SELECT COUNT(DISTINCT id_guru) as total FROM absensi WHERE tanggal = ?");
mysqli_stmt_bind_param($q_tercatat, "s", $today);
mysqli_stmt_execute($q_tercatat);
$tercatat = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($q_tercatat))['total'];

// Belum Absen = guru aktif yang belum punya catatan apapun
$belum_absen = max(0, $total_guru - $tercatat);

// Konfigurasi Pengaturan Waktu Absensi
$q_set_all = mysqli_query($conn, "SELECT nama_pengaturan, nilai_pengaturan FROM pengaturan WHERE nama_pengaturan IN ('jam_masuk_sekolah', 'toleransi_keterlambatan', 'batas_waktu_alpa')");
$settings_waktu = [];
while ($row_s = mysqli_fetch_assoc($q_set_all)) {
    $settings_waktu[$row_s['nama_pengaturan']] = $row_s['nilai_pengaturan'];
}
$jam_masuk_sekolah = $settings_waktu['jam_masuk_sekolah'] ?? '07:00:00';
$toleransi_menit   = intval($settings_waktu['toleransi_keterlambatan'] ?? 15);
$batas_waktu_alpa  = $settings_waktu['batas_waktu_alpa'] ?? '09:00:00';

$jam_sekarang_ts    = strtotime(date('H:i:s'));
$jam_masuk_ts       = strtotime($jam_masuk_sekolah);
$batas_terlambat_ts = strtotime("+{$toleransi_menit} minutes", $jam_masuk_ts);
$batas_alpa_ts      = strtotime($batas_waktu_alpa);

$batas_terlewati    = ($jam_sekarang_ts > $batas_alpa_ts);
$sudah_lewat_masuk  = ($jam_sekarang_ts > $batas_terlambat_ts);

// Persentase Kehadiran Hari Ini
$persen_hadir = $total_guru > 0 ? round((($hadir + $terlambat) / $total_guru) * 100) : 0;

// Guru dengan Wajah Terdaftar
$q_face = mysqli_query($conn, "SELECT COUNT(*) as total FROM guru WHERE face_data IS NOT NULL AND face_data != ''");
$face_registered = (int) mysqli_fetch_assoc($q_face)['total'];

// ============================================================
// 2. DATA GRAFIK 7 HARI TERAKHIR (DATA REAL DATABASE)
// ============================================================
$chart_labels = [];
$chart_hadir = [];
$chart_terlambat = [];
$chart_alpha = [];
$chart_belum_hadir = [];

for ($i = 6; $i >= 0; $i--) {
    $tgl_loop = date('Y-m-d', strtotime("-$i days"));
    $label_loop = $hari_indo[date('l', strtotime($tgl_loop))] . ' (' . date('d/m', strtotime($tgl_loop)) . ')';
    $chart_labels[] = $label_loop;

    $q_loop = mysqli_prepare($conn, "SELECT 
        SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as h,
        SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) as t,
        SUM(CASE WHEN status = 'Alpha' THEN 1 ELSE 0 END) as al,
        COUNT(DISTINCT id_guru) as total_absen
        FROM absensi WHERE tanggal = ?");
    mysqli_stmt_bind_param($q_loop, "s", $tgl_loop);
    mysqli_stmt_execute($q_loop);
    $row_loop = mysqli_fetch_assoc(mysqli_stmt_get_result($q_loop));

    $h  = (int)($row_loop['h'] ?? 0);
    $t  = (int)($row_loop['t'] ?? 0);
    $al = (int)($row_loop['al'] ?? 0);
    $absen_count = (int)($row_loop['total_absen'] ?? 0);
    $bh = max(0, $total_guru - $absen_count);

    $chart_hadir[]       = $h;
    $chart_terlambat[]   = $t;
    $chart_alpha[]       = $al;
    $chart_belum_hadir[] = $bh;
}

// ============================================================
// 3. AKTIVITAS ABSENSI TERBARU (DATA REAL DATABASE)
// ============================================================
$q_recent = mysqli_query($conn, "SELECT a.*, g.nama_guru, g.nip, g.foto 
    FROM absensi a 
    JOIN guru g ON a.id_guru = g.id_guru 
    ORDER BY a.tanggal DESC, a.jam_masuk DESC 
    LIMIT 8");

// ============================================================
// 4. DATA DAFTAR GURU UNTUK PENGINGAT ABSENSI HARI INI
// ============================================================
$stmt_pengingat = mysqli_prepare($conn, "
    SELECT 
        g.id_guru, 
        g.nama_guru, 
        g.nip, 
        g.foto,
        g.email as email_guru,
        gg.email_google, 
        gg.status_tautan as status_google,
        a.id_absensi,
        a.jam_masuk,
        a.jam_pulang,
        a.status as status_absen,
        (
            SELECT MAX(rp.created_at) 
            FROM riwayat_pengingat rp 
            WHERE rp.id_guru = g.id_guru 
              AND rp.tanggal_absensi = ? 
              AND rp.status_pengiriman = 'berhasil'
        ) as terakhir_diingatkan
    FROM guru g
    JOIN users u ON g.id_user = u.id AND u.status = 'aktif'
    LEFT JOIN guru_google gg ON g.id_guru = gg.id_guru
    LEFT JOIN absensi a ON g.id_guru = a.id_guru AND a.tanggal = ?
    ORDER BY 
        CASE 
            WHEN a.status = 'Alpha' THEN 1
            WHEN a.status IS NULL THEN 2
            WHEN a.status = 'Terlambat' THEN 3
            WHEN a.status = 'Hadir' THEN 4
            ELSE 5
        END,
        g.nama_guru ASC
");
mysqli_stmt_bind_param($stmt_pengingat, "ss", $today, $today);
mysqli_stmt_execute($stmt_pengingat);
$res_pengingat = mysqli_stmt_get_result($stmt_pengingat);
$list_guru_pengingat = [];
while ($row_p = mysqli_fetch_assoc($res_pengingat)) {
    $list_guru_pengingat[] = $row_p;
}
mysqli_stmt_close($stmt_pengingat);
?>
<?php include 'includes/header.php'; ?>

<!-- Header Title -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-speedometer2 text-primary me-2"></i>Dashboard Monitoring Admin</h3>
        <p class="text-muted mb-0"><i class="bi bi-calendar-event me-1"></i><?= $hari_ini_teks ?> &bull; Data Real-Time Sekolah</p>
    </div>
    <div class="d-flex gap-2">
        <a href="data_absensi.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="bi bi-card-checklist me-1"></i> Data Absensi Lengkap
        </a>
        <a href="registrasi_wajah.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="bi bi-person-bounding-box me-1"></i> Registrasi Wajah
        </a>
    </div>
</div>

<!-- Info Batas Waktu Alpa -->
<?php if ($batas_terlewati && $alpha > 0): ?>
<div class="alert alert-danger border-0 rounded-4 shadow-sm mb-3 d-flex align-items-center py-2 px-3" style="font-size:0.88rem;">
    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
    <span>Batas waktu absensi (<strong><?= substr($batas_waktu_alpa,0,5) ?> WIB</strong>) telah terlewati. Sistem telah mencatat <strong><?= $alpha ?> guru</strong> sebagai <strong>Alpha</strong>.</span>
</div>
<?php elseif (!$batas_terlewati && $belum_absen > 0): ?>
<div class="alert alert-warning border-0 rounded-4 shadow-sm mb-3 d-flex align-items-center py-2 px-3" style="font-size:0.88rem;">
    <i class="bi bi-clock-fill text-warning me-2"></i>
    <span>Batas waktu Alpa: <strong><?= substr($batas_waktu_alpa,0,5) ?> WIB</strong>. <strong><?= $belum_absen ?> guru</strong> belum absen. Status akan otomatis menjadi Alpha setelah batas waktu.</span>
</div>
<?php endif; ?>

<!-- 5 Stat Cards Real Data -->
<div class="row g-3 mb-4">
    <!-- Total Guru -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f8faff); border-left:4px solid #0d6efd !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #0d6efd, #0b5ed7);">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Total Guru</span>
                    <h2 class="fw-bold mb-0 text-dark"><?= $total_guru ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;"><i class="bi bi-shield-check text-success me-1"></i><?= $face_registered ?> wajah terdaftar</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Hadir Hari Ini -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f4fbf7); border-left:4px solid #198754 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #198754, #157347);">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Hadir</span>
                    <h2 class="fw-bold mb-0 text-success"><?= $hadir ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;">Tepat waktu</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Terlambat Hari Ini -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fffdf4); border-left:4px solid #ffc107 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #ffc107, #e0a800);">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Terlambat</span>
                    <h2 class="fw-bold mb-0 text-warning"><?= $terlambat ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;">Lewat batas jam</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Alpha / Tidak Hadir -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fdf6f6); border-left:4px solid #dc3545 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #dc3545, #b02a37);">
                    <i class="bi bi-person-x-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Alpha / Tidak Hadir</span>
                    <h2 class="fw-bold mb-0 text-danger"><?= $alpha ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;">Melewati batas waktu</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Belum Absen -->
    <div class="col-12 col-sm-6 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f3f0ff); border-left:4px solid #6f42c1 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #6f42c1, #59359a);">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Belum Absen</span>
                    <h2 class="fw-bold mb-0" style="color:#6f42c1;"><?= $belum_absen ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;"><?= $batas_terlewati ? 'Sudah melewati batas' : 'Batas '.substr($batas_waktu_alpa,0,5).' WIB' ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row Grafik Kehadiran (Chart.js) -->
<div class="row g-4 mb-4">
    <!-- Grafik Tren 7 Hari Terakhir -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-graph-up me-2 text-primary"></i>Tren Kehadiran Guru (7 Hari Terakhir)</h5>
                    <p class="text-muted small mb-0">Grafik data real status absensi harian guru</p>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-semibold">
                    Kehadiran Hari Ini: <?= $persen_hadir ?>%
                </span>
            </div>
            <div class="card-body p-4">
                <div style="position:relative; height:280px; width:100%;">
                    <canvas id="attendanceTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafik Distribusi Hari Ini -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-pie-chart me-2 text-primary"></i>Status Hari Ini</h5>
                <p class="text-muted small mb-0">Komposisi kehadiran tanggal <?= date('d/m/Y') ?></p>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                <div style="position:relative; height:200px; width:200px;" class="mb-3">
                    <canvas id="todayDistributionChart"></canvas>
                </div>
                <div class="w-100 mt-2">
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span class="small text-muted"><span class="badge rounded-circle bg-success me-2" style="width:10px;height:10px;display:inline-block;"></span>Hadir Tepat Waktu</span>
                        <span class="fw-bold text-success"><?= $hadir ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span class="small text-muted"><span class="badge rounded-circle bg-warning me-2" style="width:10px;height:10px;display:inline-block;"></span>Terlambat</span>
                        <span class="fw-bold text-warning"><?= $terlambat ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span class="small text-muted"><span class="badge rounded-circle bg-danger me-2" style="width:10px;height:10px;display:inline-block;"></span>Alpha / Tidak Hadir</span>
                        <span class="fw-bold text-danger"><?= $alpha ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span class="small text-muted"><span class="badge rounded-circle me-2" style="width:10px;height:10px;display:inline-block;background:#6f42c1;"></span>Belum Absen</span>
                        <span class="fw-bold" style="color:#6f42c1;"><?= $belum_absen ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row Pengingat Absensi Guru Hari Ini -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-bell-fill text-warning me-2"></i>Pengingat Absensi Guru Hari Ini
                    </h5>
                    <p class="text-muted small mb-0">Daftar seluruh guru aktif yang wajib melakukan absensi masuk hari ini (<?= $hari_ini_teks ?>)</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
                        <i class="bi bi-clock me-1 text-primary"></i> Batas Waktu Alpa: <strong><?= substr($batas_waktu_alpa, 0, 5) ?> WIB</strong>
                    </span>
                    <a href="data_absensi.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm">
                        <i class="bi bi-card-checklist me-1"></i> Data Absensi Lengkap
                    </a>
                </div>
            </div>

            <!-- Alert Notifikasi Interaktif Pengingat Dashboard -->
            <div class="px-4 pt-2">
                <div id="dashboardReminderAlert" class="alert d-none rounded-3 py-2 px-3 small mb-2 shadow-sm" role="alert"></div>
            </div>

            <div class="card-body p-4 pt-2">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablePengingatGuru">
                        <thead class="table-light">
                            <tr>
                                <th class="text-muted fw-semibold small py-3 ps-3">Guru</th>
                                <th class="text-muted fw-semibold small py-3">Jam Masuk</th>
                                <th class="text-muted fw-semibold small py-3">Status</th>
                                <th class="text-muted fw-semibold small py-3 text-center" width="220">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($list_guru_pengingat)): ?>
                                <?php foreach ($list_guru_pengingat as $gp): ?>
                                    <?php
                                        // 1. Tentukan Kategori & Keterangan Status Absensi
                                        $status_db = $gp['status_absen'];
                                        $keterangan_teks = '';
                                        $keterangan_badge = '';
                                        $kategori_status = ''; // 'sudah_absen', 'belum_absen', 'terlambat', 'tidak_absen'
                                        $bisa_diingatkan = false;

                                        // Jam Masuk Standar / Jam Masuk Tercatat
                                        $jam_masuk_display = substr($jam_masuk_sekolah, 0, 5);

                                        if ($status_db === 'Hadir') {
                                            $kategori_status = 'sudah_absen';
                                            $keterangan_teks = 'Sudah Absen';
                                            $jam_info = $gp['jam_masuk'] ? substr($gp['jam_masuk'], 0, 5) . ' WIB' : '';
                                            $keterangan_badge = '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> Sudah Absen ' . ($jam_info ? "({$jam_info})" : '') . '</span>';
                                            $bisa_diingatkan = false;
                                        } elseif ($status_db === 'Terlambat') {
                                            $kategori_status = 'sudah_absen';
                                            $keterangan_teks = 'Sudah Absen (Terlambat)';
                                            $jam_info = $gp['jam_masuk'] ? substr($gp['jam_masuk'], 0, 5) . ' WIB' : '';
                                            $keterangan_badge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-clock-history me-1"></i> Terlambat ' . ($jam_info ? "({$jam_info})" : '') . '</span>';
                                            $bisa_diingatkan = false;
                                        } elseif (in_array($status_db, ['Izin', 'Sakit'])) {
                                            $kategori_status = 'sudah_absen';
                                            $keterangan_teks = $status_db;
                                            $keterangan_badge = '<span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3 py-2 rounded-pill">' . htmlspecialchars($status_db) . '</span>';
                                            $bisa_diingatkan = false;
                                        } elseif ($status_db === 'Alpha') {
                                            $kategori_status = 'tidak_absen';
                                            $keterangan_teks = 'Tidak Absen';
                                            $keterangan_badge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-person-x-fill me-1"></i> Tidak Absen</span>';
                                            $bisa_diingatkan = false;
                                        } else {
                                            // Belum ada catatan absensi di database
                                            if ($batas_terlewati) {
                                                $kategori_status = 'tidak_absen';
                                                $keterangan_teks = 'Tidak Absen';
                                                $keterangan_badge = '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-person-x-fill me-1"></i> Tidak Absen</span>';
                                                $bisa_diingatkan = false;
                                            } elseif ($sudah_lewat_masuk) {
                                                $kategori_status = 'terlambat';
                                                $keterangan_teks = 'Terlambat';
                                                $keterangan_badge = '<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-exclamation-triangle-fill me-1"></i> Terlambat</span>';
                                                $bisa_diingatkan = true;
                                            } else {
                                                $kategori_status = 'belum_absen';
                                                $keterangan_teks = 'Belum Absen';
                                                $keterangan_badge = '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2 rounded-pill"><i class="bi bi-hourglass-split me-1"></i> Belum Absen</span>';
                                                $bisa_diingatkan = true;
                                            }
                                        }

                                        // Tentukan email aktif guru (Prioritas: Akun Google terhubung, lalu email profil guru)
                                        $active_email = '';
                                        $is_google_linked = ($gp['status_google'] === 'terhubung' && !empty($gp['email_google']));
                                        if ($is_google_linked) {
                                            $active_email = $gp['email_google'];
                                        } elseif (!empty($gp['email_guru']) && filter_var($gp['email_guru'], FILTER_VALIDATE_EMAIL)) {
                                            $active_email = $gp['email_guru'];
                                        }

                                        // Inisial Nama untuk Avatar
                                        $nama_arr = explode(' ', $gp['nama_guru']);
                                        $inisial = strtoupper(substr($nama_arr[0], 0, 1) . (isset($nama_arr[1]) ? substr($nama_arr[1], 0, 1) : ''));
                                    ?>
                                    <tr id="row-guru-<?= $gp['id_guru'] ?>">
                                        <td class="ps-3 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-3 shadow-sm" style="width:40px; height:40px; background:linear-gradient(135deg, #0d6efd, #0b5ed7); font-size:0.85rem; flex-shrink:0;">
                                                    <?= $inisial ?>
                                                </div>
                                                <div>
                                                    <span class="d-block fw-bold text-dark mb-0"><?= htmlspecialchars($gp['nama_guru']) ?></span>
                                                    <small class="text-muted">NIP. <?= htmlspecialchars($gp['nip'] ?? '-') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="fw-semibold text-secondary">
                                                <i class="bi bi-clock me-1 text-primary"></i><?= $jam_masuk_display ?> WIB
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <?= $keterangan_badge ?>
                                        </td>
                                        <td class="py-3 text-center">
                                            <?php if ($bisa_diingatkan): ?>
                                                <?php if (!empty($active_email)): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm btn-ingatkan-admin"
                                                            data-id="<?= $gp['id_guru'] ?>"
                                                            data-nama="<?= htmlspecialchars($gp['nama_guru']) ?>"
                                                            data-email="<?= htmlspecialchars($active_email) ?>"
                                                            data-tanggal="<?= $today ?>"
                                                            data-kategori="<?= $kategori_status ?>">
                                                        <i class="bi bi-bell-fill me-1"></i> Ingatkan
                                                    </button>
                                                    <?php if (!empty($gp['terakhir_diingatkan'])): ?>
                                                        <small class="d-block text-success mt-1" style="font-size:0.75rem;">
                                                             <i class="bi bi-check2-all me-1"></i>Terkirim <?= date('H:i', strtotime($gp['terakhir_diingatkan'])) ?> WIB
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-secondary rounded-pill px-3 btn-email-missing"
                                                            data-nama="<?= htmlspecialchars($gp['nama_guru']) ?>"
                                                            title="Alamat email guru belum tersedia. Silakan lengkapi data email guru terlebih dahulu.">
                                                        <i class="bi bi-bell-slash me-1"></i> Ingatkan
                                                    </button>
                                                    <small class="d-block text-muted mt-1" style="font-size:0.75rem;">
                                                        <i class="bi bi-exclamation-circle me-1"></i>Email belum tersedia
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small fw-semibold">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                        Tidak ada data guru aktif pada sistem.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row Aktivitas Absensi Terbaru -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-activity text-primary me-2"></i>Aktivitas Absensi Terbaru</h5>
                    <p class="text-muted small mb-0">Catatan log kehadiran terbaru dari database sistem</p>
                </div>
                <a href="data_absensi.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-4 pt-2">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-muted fw-semibold small py-3 ps-3">GURU</th>
                                <th class="text-muted fw-semibold small py-3">TANGGAL</th>
                                <th class="text-muted fw-semibold small py-3">JAM MASUK</th>
                                <th class="text-muted fw-semibold small py-3">JAM PULANG</th>
                                <th class="text-muted fw-semibold small py-3">STATUS</th>
                                <th class="text-muted fw-semibold small py-3 text-center">METODE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($q_recent) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($q_recent)): ?>
                                    <?php
                                        // Inisial avatar
                                        $nama_parts = explode(' ', $row['nama_guru']);
                                        $initials = strtoupper(substr($nama_parts[0], 0, 1) . (isset($nama_parts[1]) ? substr($nama_parts[1], 0, 1) : ''));
                                        
                                        // Warna status
                                        $badge_status = 'bg-secondary';
                                        if($row['status'] == 'Hadir') $badge_status = 'bg-success';
                                        elseif($row['status'] == 'Terlambat') $badge_status = 'bg-warning text-dark';
                                        elseif(in_array($row['status'], ['Izin', 'Sakit'])) $badge_status = 'bg-info text-dark';
                                        elseif($row['status'] == 'Alpha') $badge_status = 'bg-danger';
                                    ?>
                                    <tr>
                                        <td class="ps-3 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-3 shadow-sm" style="width:40px; height:40px; background:linear-gradient(135deg, #0d6efd, #0b5ed7); font-size:0.85rem; flex-shrink:0;">
                                                    <?= $initials ?>
                                                </div>
                                                <div>
                                                    <span class="d-block fw-bold text-dark mb-0" style="font-size:0.92rem;"><?= htmlspecialchars($row['nama_guru']) ?></span>
                                                    <small class="text-muted">NIP. <?= htmlspecialchars($row['nip'] ?? '-') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="fw-medium text-dark"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-light text-dark border px-2 py-1 font-monospace">
                                                <i class="bi bi-box-arrow-in-right text-success me-1"></i><?= $row['jam_masuk'] ? substr($row['jam_masuk'], 0, 5) : '--:--' ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-light text-dark border px-2 py-1 font-monospace">
                                                <i class="bi bi-box-arrow-right text-danger me-1"></i><?= $row['jam_pulang'] ? substr($row['jam_pulang'], 0, 5) : '--:--' ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge <?= $badge_status ?> px-3 py-2 rounded-pill fw-semibold">
                                                <?= htmlspecialchars($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-center">
                                            <?php if($row['metode_verifikasi'] === 'AI Face Recognition'): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill">
                                                    <i class="bi bi-shield-check me-1"></i>AI Face Recognition
                                                </span>
                                            <?php elseif($row['metode_verifikasi'] === 'Sistem'): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill">
                                                    <i class="bi bi-robot me-1"></i>Auto (Sistem)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border px-3 py-2 rounded-pill">
                                                    <i class="bi bi-pencil me-1"></i>Manual
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                        Belum ada aktivitas absensi tercatat di database.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Chart Tren 7 Hari Terakhir
    const ctxTrend = document.getElementById('attendanceTrendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Hadir',
                    data: <?= json_encode($chart_hadir) ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.85)',
                    borderColor: '#198754',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    label: 'Terlambat',
                    data: <?= json_encode($chart_terlambat) ?>,
                    backgroundColor: 'rgba(255, 193, 7, 0.85)',
                    borderColor: '#ffc107',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    label: 'Alpha',
                    data: <?= json_encode($chart_alpha) ?>,
                    backgroundColor: 'rgba(220, 53, 69, 0.85)',
                    borderColor: '#dc3545',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    label: 'Belum Absen',
                    data: <?= json_encode($chart_belum_hadir) ?>,
                    backgroundColor: 'rgba(111, 66, 193, 0.35)',
                    borderColor: '#6f42c1',
                    borderWidth: 1,
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { font: { family: 'Inter, sans-serif', size: 12 }, boxWidth: 14 }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 12,
                    cornerRadius: 8,
                    bodyFont: { family: 'Inter, sans-serif' }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Inter, sans-serif', size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, font: { family: 'Inter, sans-serif', size: 11 } },
                    grid: { color: 'rgba(0,0,0,0.06)' }
                }
            }
        }
    });

    // 2. Chart Distribusi Donut Hari Ini
    const ctxDonut = document.getElementById('todayDistributionChart').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Terlambat', 'Alpha', 'Belum Absen'],
            datasets: [{
                data: [<?= $hadir ?>, <?= $terlambat ?>, <?= $alpha ?>, <?= $belum_absen ?>],
                backgroundColor: [
                    '#198754', // Hadir (Hijau)
                    '#ffc107', // Terlambat (Kuning)
                    '#dc3545', // Alpha (Merah)
                    '#6f42c1'  // Belum Absen (Ungu)
                ],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 10,
                    cornerRadius: 8
                }
            }
        }
    });
});
</script>

<!-- Modal Konfirmasi Kirim Pengingat dari Dashboard Admin -->
<div class="modal fade" id="modalConfirmDashboardReminder" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary">
                    <i class="bi bi-bell-fill text-warning me-2"></i>Kirim Pengingat Absensi Guru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-muted mb-3">
                    Kirimkan notifikasi email pengingat resmi ke guru berikut?
                </p>
                <div class="p-3 bg-light rounded-3 border">
                    <div class="row mb-1">
                        <div class="col-4 text-muted small fw-semibold">Nama Guru:</div>
                        <div class="col-8 fw-bold text-dark" id="dashModalNama">-</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 text-muted small fw-semibold">Email Gmail:</div>
                        <div class="col-8 font-monospace text-primary fw-semibold" id="dashModalEmail">-</div>
                    </div>
                    <div class="row">
                        <div class="col-4 text-muted small fw-semibold">Tanggal Absen:</div>
                        <div class="col-8 text-secondary small" id="dashModalTanggal">-</div>
                    </div>
                </div>
                <div class="alert alert-info py-2 px-3 small rounded-3 mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i> Format pesan email yang akan dikirim:
                    <div class="bg-white p-2 rounded border mt-2 font-monospace" id="dashModalPesanPreview" style="font-size:0.8rem; white-space:pre-line;">
                        "Assalamu'alaikum, Bapak/Ibu [Nama Guru]. Kami ingin mengingatkan bahwa Anda belum melakukan absensi masuk hari ini. Mohon segera melakukan absensi melalui website SAG. Terima kasih atas perhatian dan kerja samanya. Wassalamu'alaikum."
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnDashConfirmSend">
                    <span id="dashReminderSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                    <span id="dashReminderBtnText"><i class="bi bi-send me-1"></i> Kirim Sekarang</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const alertBox = document.getElementById('dashboardReminderAlert');
    const modalEl = document.getElementById('modalConfirmDashboardReminder');
    const modalInstance = modalEl ? new bootstrap.Modal(modalEl) : null;

    const modalNama = document.getElementById('dashModalNama');
    const modalEmail = document.getElementById('dashModalEmail');
    const modalTanggal = document.getElementById('dashModalTanggal');
    const modalPreview = document.getElementById('dashModalPesanPreview');
    const btnConfirmSend = document.getElementById('btnDashConfirmSend');
    const spinner = document.getElementById('dashReminderSpinner');
    const btnText = document.getElementById('dashReminderBtnText');

    let activeGuruId = 0;
    let activeTanggal = '';
    let activeKategori = 'belum_absen';
    let currentActiveButton = null;

    function showDashboardAlert(type, message) {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} alert-dismissible fade show rounded-3 py-2 px-3 small mb-2 shadow-sm`;
        alertBox.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="bi ${type === 'success' ? 'bi-check-circle-fill text-success' : (type === 'warning' ? 'bi-exclamation-triangle-fill text-warning' : 'bi-x-circle-fill text-danger')} fs-5 me-2"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Event tombol guru yang belum memiliki email / akun Google
    document.querySelectorAll('.btn-email-missing, .btn-google-unlinked').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const nama = this.getAttribute('data-nama') || 'Guru';
            showDashboardAlert('warning', `Alamat email untuk <strong>${nama}</strong> belum tersedia. Silakan lengkapi alamat email guru terlebih dahulu pada menu Data Guru.`);
        });
    });

    // Event tombol Ingatkan untuk guru yang sudah tertaut
    document.querySelectorAll('.btn-ingatkan-admin').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            activeGuruId = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const email = this.getAttribute('data-email');
            activeTanggal = this.getAttribute('data-tanggal');
            activeKategori = this.getAttribute('data-kategori') || 'belum_absen';
            currentActiveButton = this;

            if (modalNama) modalNama.textContent = nama;
            if (modalEmail) modalEmail.textContent = email;
            if (modalTanggal) modalTanggal.textContent = activeTanggal;

            if (modalPreview) {
                if (activeKategori === 'terlambat') {
                    modalPreview.textContent = `"Assalamu'alaikum, Bapak/Ibu ${nama}.\n\nBerdasarkan sistem SAG, Anda belum melakukan absensi masuk dan telah melewati jam masuk yang ditentukan.\n\nMohon segera melakukan absensi. Absensi Anda akan tercatat sebagai terlambat.\n\nTerima kasih.\n\nWassalamu'alaikum."`;
                } else {
                    modalPreview.textContent = `"Assalamu'alaikum, Bapak/Ibu ${nama}.\n\nKami ingin mengingatkan bahwa Anda belum melakukan absensi masuk hari ini.\n\nMohon segera melakukan absensi melalui website Sistem Absensi Guru (SAG).\n\nTerima kasih atas perhatian dan kerja samanya.\n\nWassalamu'alaikum."`;
                }
            }

            if (modalInstance) modalInstance.show();
        });
    });

    // Event tombol Konfirmasi Kirim Sekarang di dalam modal
    if (btnConfirmSend) {
        btnConfirmSend.addEventListener('click', function() {
            if (!activeGuruId) return;

            // Cegah klik ganda & ubah state tombol modal
            btnConfirmSend.disabled = true;
            if (spinner) spinner.classList.remove('d-none');
            if (btnText) btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Mengirim...';

            // Ubah juga tombol di baris tabel menjadi Mengirim...
            if (currentActiveButton) {
                currentActiveButton.disabled = true;
                currentActiveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Mengirim...';
            }

            const formData = new FormData();
            formData.append('action', 'kirim');
            formData.append('id_guru', activeGuruId);
            formData.append('tanggal', activeTanggal);

            fetch('api_pengingat_absensi.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                // Kembalikan state tombol modal
                btnConfirmSend.disabled = false;
                if (spinner) spinner.classList.add('d-none');
                if (btnText) btnText.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Sekarang';
                if (modalInstance) modalInstance.hide();

                if (data.success) {
                    showDashboardAlert('success', data.message);
                    if (currentActiveButton) {
                        const cell = currentActiveButton.closest('td');
                        if (cell) {
                            const timeStr = data.waktu ? data.waktu.split(' ')[0] : 'Hari Ini';
                            cell.innerHTML = `
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                    <i class="bi bi-check2-circle me-1"></i> Terkirim
                                </span>
                                <small class="d-block text-success mt-1" style="font-size:0.75rem;">
                                    <i class="bi bi-clock me-1"></i>Pukul ${timeStr} WIB
                                </small>
                            `;
                        }
                    }
                } else {
                    showDashboardAlert('danger', data.message);
                    // Jika gagal, ubah status tombol menjadi Coba Lagi
                    if (currentActiveButton) {
                        currentActiveButton.disabled = false;
                        currentActiveButton.classList.remove('btn-primary');
                        currentActiveButton.classList.add('btn-outline-danger');
                        currentActiveButton.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi';
                    }
                }
            })
            .catch(err => {
                btnConfirmSend.disabled = false;
                if (spinner) spinner.classList.add('d-none');
                if (btnText) btnText.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Sekarang';
                if (modalInstance) modalInstance.hide();

                showDashboardAlert('danger', 'Terjadi gangguan jaringan saat menghubungi server pengingat: ' + err.message);
                if (currentActiveButton) {
                    currentActiveButton.disabled = false;
                    currentActiveButton.classList.remove('btn-primary');
                    currentActiveButton.classList.add('btn-outline-danger');
                    currentActiveButton.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi';
                }
                    currentActiveButton.innerHTML = '<i class="bi bi-bell-fill me-1"></i> Ingatkan';
                }
            });
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
