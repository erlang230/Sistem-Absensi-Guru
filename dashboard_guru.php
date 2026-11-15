<?php
require_once 'auth.php';
check_login();
check_role('guru');

require_once 'config/database.php';
require_once 'proses_alpa.php';

date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d');
$user_id = $_SESSION['user_id'];

// Ambil data guru dari database berdasarkan id_user session
$stmt_g = mysqli_prepare($conn, "SELECT * FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_g, "i", $user_id);
mysqli_stmt_execute($stmt_g);
$guru = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_g));

if (!$guru) {
    die("Data guru tidak ditemukan di sistem.");
}

$id_guru = $guru['id_guru'];
$nama_guru = $guru['nama_guru'];
$face_registered = !empty($guru['face_data']);

// Terjemahan hari & bulan
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
$hari_sekarang = $hari_indo[date('l')];

// ============================================================
// 1. STATUS KEHADIRAN SENDIRI HARI INI (DATA REAL DATABASE)
// ============================================================
$stmt_absen = mysqli_prepare($conn, "SELECT * FROM absensi WHERE id_guru = ? AND tanggal = ?");
mysqli_stmt_bind_param($stmt_absen, "is", $id_guru, $today);
mysqli_stmt_execute($stmt_absen);
$absen_hari_ini = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_absen));

$status_hadir_teks = 'Belum Absen';
$status_badge_class = 'bg-secondary';
$jam_masuk_teks = '--:--';
$jam_pulang_teks = '--:--';

// Ambil batas waktu alpa untuk menentukan status tampilan
$q_batas_dash = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'batas_waktu_alpa'");
$batas_waktu_alpa_dash = mysqli_fetch_assoc($q_batas_dash)['nilai_pengaturan'] ?? '09:00:00';
$batas_terlewati_dash = strtotime(date('H:i:s')) > strtotime($batas_waktu_alpa_dash);

if ($absen_hari_ini) {
    $status_hadir_teks = $absen_hari_ini['status'];
    if ($absen_hari_ini['status'] == 'Hadir') {
        $status_badge_class = 'bg-success';
    } elseif ($absen_hari_ini['status'] == 'Terlambat') {
        $status_badge_class = 'bg-warning text-dark';
    } elseif ($absen_hari_ini['status'] == 'Alpha') {
        $status_badge_class = 'bg-danger';
    } else {
        $status_badge_class = 'bg-info text-dark';
    }

    $jam_masuk_teks = $absen_hari_ini['jam_masuk'] ? substr($absen_hari_ini['jam_masuk'], 0, 5) : '--:--';
    $jam_pulang_teks = $absen_hari_ini['jam_pulang'] ? substr($absen_hari_ini['jam_pulang'], 0, 5) : ($absen_hari_ini['jam_masuk'] ? 'Belum Pulang' : '--:--');
} else {
    // Belum ada catatan absensi - cek apakah sudah melewati batas waktu
    if ($batas_terlewati_dash) {
        $status_hadir_teks = 'Alpha';
        $status_badge_class = 'bg-danger';
    } else {
        $status_hadir_teks = 'Belum Absen';
        $status_badge_class = 'bg-secondary';
    }
}

// ============================================================
// 2. JADWAL HARI INI (DATA REAL DATABASE)
// ============================================================
$stmt_jadwal = mysqli_prepare($conn, "SELECT j.*, m.nama_mapel, m.kode_mapel, k.nama_kelas, k.tingkat, k.jurusan 
    FROM jadwal j 
    JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
    JOIN kelas k ON j.id_kelas = k.id_kelas 
    WHERE j.id_guru = ? AND j.hari = ? 
    ORDER BY j.jam_mulai ASC");
mysqli_stmt_bind_param($stmt_jadwal, "is", $id_guru, $hari_sekarang);
mysqli_stmt_execute($stmt_jadwal);
$res_jadwal = mysqli_stmt_get_result($stmt_jadwal);
$total_jadwal_hari_ini = mysqli_num_rows($res_jadwal);

// ============================================================
// 3. STATISTIK PRIBADI BULAN INI (UNTUK CHART.JS)
// ============================================================
$bulan_ini = date('Y-m');
$q_stat = mysqli_query($conn, "SELECT 
    SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) as terlambat,
    SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) as sakit,
    SUM(CASE WHEN status = 'Alpha' THEN 1 ELSE 0 END) as alpha,
    COUNT(*) as total
    FROM absensi 
    WHERE id_guru = $id_guru AND DATE_FORMAT(tanggal, '%Y-%m') = '$bulan_ini'");
$stat_pribadi = mysqli_fetch_assoc($q_stat);
$pribadi_hadir = (int)($stat_pribadi['hadir'] ?? 0);
$pribadi_terlambat = (int)($stat_pribadi['terlambat'] ?? 0);
$pribadi_izin = (int)($stat_pribadi['izin'] ?? 0);
$pribadi_sakit = (int)($stat_pribadi['sakit'] ?? 0);
$pribadi_alpha = (int)($stat_pribadi['alpha'] ?? 0);
$pribadi_total = (int)($stat_pribadi['total'] ?? 0);

// ============================================================
// 4. RIWAYAT ABSENSI TERBARU GURU (DATA REAL DATABASE)
// ============================================================
$stmt_riwayat = mysqli_prepare($conn, "SELECT * FROM absensi 
    WHERE id_guru = ? 
    ORDER BY tanggal DESC, jam_masuk DESC 
    LIMIT 7");
mysqli_stmt_bind_param($stmt_riwayat, "i", $id_guru);
mysqli_stmt_execute($stmt_riwayat);
$res_riwayat = mysqli_stmt_get_result($stmt_riwayat);
?>
<?php include 'includes/header.php'; ?>

<!-- Header Guru -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-person-badge text-primary me-2"></i>Dashboard Guru</h3>
        <p class="text-muted mb-0"><i class="bi bi-calendar-event me-1"></i><?= $hari_ini_teks ?> &bull; Selamat datang, <strong><?= htmlspecialchars($nama_guru) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (!$face_registered): ?>
            <span class="badge bg-warning bg-opacity-25 text-dark border border-warning px-3 py-2 rounded-pill d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Wajah Belum Terdaftar
            </span>
        <?php else: ?>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill d-flex align-items-center">
                <i class="bi bi-shield-check me-2"></i>Wajah Terdaftar (AI Ready)
            </span>
        <?php endif; ?>
        <a href="jadwal_guru.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="bi bi-calendar3 me-1"></i> Semua Jadwal
        </a>
    </div>
</div>

<!-- 4 Stat Cards Real Data (Guru) -->
<div class="row g-3 mb-4">
    <!-- Status Kehadiran Sendiri -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f8faff); border-left:4px solid #0d6efd !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #0d6efd, #0b5ed7);">
                    <i class="bi bi-person-check-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Kehadiran Hari Ini</span>
                    <h5 class="fw-bold mb-0 mt-1">
                        <span class="badge <?= $status_badge_class ?> px-3 py-2 rounded-pill"><?= $status_hadir_teks ?></span>
                    </h5>
                    <?php if($absen_hari_ini): ?>
                        <small class="text-muted" style="font-size:0.75rem;"><i class="bi bi-check2-all text-success me-1"></i><?= $absen_hari_ini['metode_verifikasi'] ?></small>
                    <?php else: ?>
                        <small class="text-danger" style="font-size:0.75rem;">Silakan lakukan absensi</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Jam Masuk -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f4fbf7); border-left:4px solid #198754 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #198754, #157347);">
                    <i class="bi bi-box-arrow-in-right fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Jam Masuk</span>
                    <h2 class="fw-bold mb-0 text-success font-monospace"><?= $jam_masuk_teks ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;"><?= $absen_hari_ini ? 'Tercatat di sistem' : 'Belum tercatat' ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Jam Pulang -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fffdf4); border-left:4px solid #ffc107 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #ffc107, #e0a800);">
                    <i class="bi bi-box-arrow-right fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Jam Pulang</span>
                    <h2 class="fw-bold mb-0 text-dark font-monospace" style="font-size:1.6rem;"><?= $jam_pulang_teks ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;"><?= ($absen_hari_ini && $absen_hari_ini['jam_pulang']) ? 'Selesai bertugas' : 'Belum absen pulang' ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Jadwal Hari Ini -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f3f0ff); border-left:4px solid #6f42c1 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #6f42c1, #59359a);">
                    <i class="bi bi-calendar-check-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Jadwal Hari Ini</span>
                    <h2 class="fw-bold mb-0 text-dark"><?= $total_jadwal_hari_ini ?> <span class="fs-6 fw-normal text-muted">Kelas</span></h2>
                    <small class="text-muted" style="font-size:0.75rem;">Hari <?= $hari_sekarang ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CTA Absensi Pintar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 text-white overflow-hidden position-relative" style="background:linear-gradient(135deg, #0d6efd 0%, #0a4ebd 100%);">
            <div class="card-body p-4 position-relative" style="z-index:2;">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center me-3" style="width:60px; height:60px; flex-shrink:0;">
                            <i class="bi bi-camera-video-fill text-white fs-3"></i>
                        </div>
                        <div>
                            <?php if (!$absen_hari_ini): ?>
                                <h4 class="fw-bold mb-1">Siap Melakukan Absensi Masuk?</h4>
                                <p class="mb-0 text-white-50">Lakukan verifikasi wajah dengan teknologi Face Recognition AI & Liveness Detection.</p>
                            <?php elseif (!$absen_hari_ini['jam_pulang']): ?>
                                <h4 class="fw-bold mb-1">Waktu Pulang Bertugas</h4>
                                <p class="mb-0 text-white-50">Anda sudah absen masuk pada jam <?= substr($absen_hari_ini['jam_masuk'], 0, 5) ?>. Klik tombol untuk absen pulang.</p>
                            <?php else: ?>
                                <h4 class="fw-bold mb-1">Absensi Hari Ini Lengkap ✓</h4>
                                <p class="mb-0 text-white-50">Terima kasih atas dedikasi Anda hari ini. Selamat beristirahat!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <?php if (!$absen_hari_ini): ?>
                            <a href="absensi.php" class="btn btn-light btn-lg px-4 rounded-pill shadow fw-bold text-primary">
                                <i class="bi bi-qr-code-scan me-2"></i>Absen Masuk Sekarang
                            </a>
                        <?php elseif (!$absen_hari_ini['jam_pulang']): ?>
                            <a href="absensi.php" class="btn btn-warning btn-lg px-4 rounded-pill shadow fw-bold text-dark">
                                <i class="bi bi-box-arrow-right me-2"></i>Absen Pulang Sekarang
                            </a>
                        <?php else: ?>
                            <a href="riwayat_absensi.php" class="btn btn-outline-light btn-lg px-4 rounded-pill">
                                <i class="bi bi-clock-history me-2"></i>Lihat Riwayat
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Background Decorative Circle -->
            <div style="position:absolute; right:-30px; bottom:-40px; width:180px; height:180px; background:rgba(255,255,255,0.06); border-radius:50%; z-index:1;"></div>
        </div>
    </div>
</div>

<!-- Row Jadwal Hari Ini & Grafik Bulan Ini -->
<div class="row g-4 mb-4">
    <!-- Jadwal Hari Ini (Data Real) -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-calendar3-event text-primary me-2"></i>Jadwal Mengajar Hari Ini</h5>
                    <p class="text-muted small mb-0">Hari <?= $hari_sekarang ?>, <?= date('d M Y') ?></p>
                </div>
                <a href="jadwal_guru.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    Lihat Jadwal Lengkap <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-4 pt-2">
                <?php if($total_jadwal_hari_ini > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-muted fw-semibold small py-3 ps-3">JAM</th>
                                    <th class="text-muted fw-semibold small py-3">MATA PELAJARAN</th>
                                    <th class="text-muted fw-semibold small py-3">KELAS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($j = mysqli_fetch_assoc($res_jadwal)): ?>
                                    <tr>
                                        <td class="ps-3 py-3">
                                            <span class="badge bg-light text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill font-monospace fw-bold">
                                                <i class="bi bi-clock me-1"></i><?= substr($j['jam_mulai'], 0, 5) ?> - <?= substr($j['jam_selesai'], 0, 5) ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($j['nama_mapel']) ?></span>
                                            <small class="text-muted"><?= htmlspecialchars($j['kode_mapel']) ?></small>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-secondary bg-opacity-10 text-dark px-3 py-2 rounded-pill fw-semibold">
                                                <i class="bi bi-door-open me-1 text-primary"></i><?= htmlspecialchars($j['nama_kelas']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:70px; height:70px;">
                            <i class="bi bi-calendar-heart text-muted fs-2"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">Tidak Ada Jadwal Mengajar Hari Ini</h6>
                        <p class="text-muted small mb-3">Hari <?= $hari_sekarang ?> tidak ada jam pelajaran tatap muka yang dijadwalkan.</p>
                        <a href="jadwal_guru.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-calendar-week me-1"></i>Periksa Jadwal Hari Lain
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Grafik Kehadiran Pribadi Bulan Ini (Chart.js) -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-pie-chart text-primary me-2"></i>Kehadiran Anda Bulan Ini</h5>
                <p class="text-muted small mb-0"><?= $bulan_indo[date('F')] . ' ' . date('Y') ?></p>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                <?php if($pribadi_total > 0): ?>
                    <div style="position:relative; height:180px; width:180px;" class="mb-3">
                        <canvas id="personalAttendanceChart"></canvas>
                    </div>
                    <div class="w-100 mt-2">
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span class="small text-muted"><span class="badge rounded-circle bg-success me-2" style="width:10px;height:10px;display:inline-block;"></span>Hadir Tepat Waktu</span>
                            <span class="fw-bold text-success"><?= $pribadi_hadir ?> Hari</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span class="small text-muted"><span class="badge rounded-circle bg-warning me-2" style="width:10px;height:10px;display:inline-block;"></span>Terlambat</span>
                            <span class="fw-bold text-warning"><?= $pribadi_terlambat ?> Hari</span>
                        </div>
                        <?php if($pribadi_izin > 0 || $pribadi_sakit > 0): ?>
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <span class="small text-muted"><span class="badge rounded-circle bg-info me-2" style="width:10px;height:10px;display:inline-block;"></span>Izin / Sakit</span>
                            <span class="fw-bold text-info"><?= $pribadi_izin + $pribadi_sakit ?> Hari</span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="small text-muted"><span class="badge rounded-circle bg-primary me-2" style="width:10px;height:10px;display:inline-block;"></span>Total Hari Hadir</span>
                            <span class="fw-bold text-dark"><?= $pribadi_total ?> Hari</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                        Belum ada catatan absensi bulan ini.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Row Riwayat Terbaru (Data Real) -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Kehadiran Terbaru Anda</h5>
                    <p class="text-muted small mb-0">Catatan log presensi pribadi dari database</p>
                </div>
                <a href="riwayat_absensi.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    Lihat Semua Riwayat <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="card-body p-4 pt-2">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-muted fw-semibold small py-3 ps-3">TANGGAL</th>
                                <th class="text-muted fw-semibold small py-3">JAM MASUK</th>
                                <th class="text-muted fw-semibold small py-3">JAM PULANG</th>
                                <th class="text-muted fw-semibold small py-3">STATUS</th>
                                <th class="text-muted fw-semibold small py-3 text-center">METODE VERIFIKASI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($res_riwayat) > 0): ?>
                                <?php while($r = mysqli_fetch_assoc($res_riwayat)): ?>
                                    <?php
                                        $badge_class = 'bg-secondary';
                                        if($r['status'] == 'Hadir') $badge_class = 'bg-success';
                                        elseif($r['status'] == 'Terlambat') $badge_class = 'bg-warning text-dark';
                                        elseif(in_array($r['status'], ['Izin', 'Sakit'])) $badge_class = 'bg-info text-dark';
                                        elseif($r['status'] == 'Alpha') $badge_class = 'bg-danger';
                                    ?>
                                    <tr>
                                        <td class="ps-3 py-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-calendar-check text-primary me-2"></i>
                                                <span class="fw-semibold text-dark"><?= date('d M Y', strtotime($r['tanggal'])) ?></span>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-light text-dark border px-2 py-1 font-monospace">
                                                <i class="bi bi-box-arrow-in-right text-success me-1"></i><?= $r['jam_masuk'] ? substr($r['jam_masuk'], 0, 5) : '--:--' ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-light text-dark border px-2 py-1 font-monospace">
                                                <i class="bi bi-box-arrow-right text-danger me-1"></i><?= $r['jam_pulang'] ? substr($r['jam_pulang'], 0, 5) : '--:--' ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge <?= $badge_class ?> px-3 py-2 rounded-pill fw-semibold">
                                                <?= htmlspecialchars($r['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-center">
                                            <?php if($r['metode_verifikasi'] === 'AI Face Recognition'): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill">
                                                    <i class="bi bi-shield-check me-1"></i>AI Face Recognition
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
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                        Belum ada riwayat absensi tercatat untuk akun Anda.
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

<?php if($pribadi_total > 0): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctxPersonal = document.getElementById('personalAttendanceChart').getContext('2d');
    new Chart(ctxPersonal, {
        type: 'doughnut',
        data: {
            labels: ['Hadir Tepat Waktu', 'Terlambat'<?= ($pribadi_izin + $pribadi_sakit > 0) ? ", 'Izin/Sakit'" : "" ?>],
            datasets: [{
                data: [<?= $pribadi_hadir ?>, <?= $pribadi_terlambat ?><?= ($pribadi_izin + $pribadi_sakit > 0) ? ", " . ($pribadi_izin + $pribadi_sakit) : "" ?>],
                backgroundColor: ['#198754', '#ffc107'<?= ($pribadi_izin + $pribadi_sakit > 0) ? ", '#0dcaf0'" : "" ?>],
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
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
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
