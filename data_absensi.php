<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';
require_once 'proses_alpa.php';

date_default_timezone_set('Asia/Jakarta');

// Filter Tanggal
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$tanggal_filter = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_filter) ? $tanggal_filter : date('Y-m-d');

// Jalankan engine alpa untuk tanggal yang difilter (hanya jika hari ini)
$today = date('Y-m-d');
if ($tanggal_filter === $today) {
    proses_alpa_otomatis($conn, $today);
}

// Ambil pengaturan batas waktu alpa
$q_batas = mysqli_query($conn, "SELECT nilai_pengaturan FROM pengaturan WHERE nama_pengaturan = 'batas_waktu_alpa'");
$batas_waktu_alpa = mysqli_fetch_assoc($q_batas)['nilai_pengaturan'] ?? '09:00:00';

// Apakah batas waktu sudah terlewati untuk tanggal yang dipilih?
$batas_terlewati = ($tanggal_filter < $today) ||
    ($tanggal_filter === $today && strtotime(date('H:i:s')) > strtotime($batas_waktu_alpa));

// Ambil semua data absensi untuk tanggal terpilih + status Google
$sql = "SELECT a.*, g.nama_guru, g.nip, gg.email_google, gg.status_tautan as status_google
        FROM absensi a 
        JOIN guru g ON a.id_guru = g.id_guru 
        LEFT JOIN guru_google gg ON g.id_guru = gg.id_guru
        WHERE a.tanggal = ? 
        ORDER BY 
            FIELD(a.status, 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpha'),
            a.jam_masuk ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $tanggal_filter);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Ambil guru yang BELUM punya catatan pada tanggal tersebut + status Google
$sql_belum = "SELECT g.id_guru, g.nama_guru, g.nip, gg.email_google, gg.status_tautan as status_google
              FROM guru g 
              JOIN users u ON g.id_user = u.id
              LEFT JOIN guru_google gg ON g.id_guru = gg.id_guru
              WHERE u.status = 'aktif'
              AND g.id_guru NOT IN (
                  SELECT id_guru FROM absensi WHERE tanggal = ?
              )
              ORDER BY g.nama_guru ASC";
$stmt_belum = mysqli_prepare($conn, $sql_belum);
mysqli_stmt_bind_param($stmt_belum, "s", $tanggal_filter);
mysqli_stmt_execute($stmt_belum);
$result_belum = mysqli_stmt_get_result($stmt_belum);
$guru_belum_absen = [];
while ($rb = mysqli_fetch_assoc($result_belum)) {
    $guru_belum_absen[] = $rb;
}

// Statistik
$q_stat = mysqli_prepare($conn, "SELECT 
    SUM(CASE WHEN status='Hadir' THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN status='Terlambat' THEN 1 ELSE 0 END) as terlambat,
    SUM(CASE WHEN status='Alpha' THEN 1 ELSE 0 END) as alpha,
    SUM(CASE WHEN status='Izin' THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN status='Sakit' THEN 1 ELSE 0 END) as sakit
    FROM absensi WHERE tanggal = ?");
mysqli_stmt_bind_param($q_stat, "s", $tanggal_filter);
mysqli_stmt_execute($q_stat);
$stat = mysqli_fetch_assoc(mysqli_stmt_get_result($q_stat));
$stat_hadir    = (int)($stat['hadir'] ?? 0);
$stat_terlambat = (int)($stat['terlambat'] ?? 0);
$stat_alpha    = (int)($stat['alpha'] ?? 0);
$stat_izin     = (int)($stat['izin'] ?? 0);
$stat_sakit    = (int)($stat['sakit'] ?? 0);
$stat_belum    = count($guru_belum_absen);

$hari_indo = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa',
    'Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$tanggal_label = $hari_indo[date('l', strtotime($tanggal_filter))] . ', ' . date('d F Y', strtotime($tanggal_filter));
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-card-checklist text-primary me-2"></i>Data Absensi Guru</h3>
        <p class="text-muted mb-0"><i class="bi bi-calendar-event me-1"></i><?= $tanggal_label ?></p>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <button type="button" class="btn btn-outline-primary rounded-pill shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#modalRiwayatPengingat" id="btnBukaRiwayat">
            <i class="bi bi-clock-history me-1"></i> Riwayat Pengingat
        </button>

        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="fw-medium text-muted small d-none d-sm-block">Tanggal:</label>
            <input type="date" name="tanggal" class="form-control shadow-sm rounded-pill" 
                   style="max-width:170px;" value="<?= htmlspecialchars($tanggal_filter) ?>" onchange="this.form.submit()">
            <button type="submit" class="btn btn-primary rounded-pill shadow-sm px-3">
                <i class="bi bi-filter"></i>
            </button>
        </form>
    </div>
</div>

<!-- Toast / Alert Notifikasi Interaktif -->
<div id="adminReminderAlert" class="alert d-none rounded-4 shadow-sm py-2 px-3 mb-3" role="alert"></div>

<!-- Info Banner Batas Waktu -->
<?php if ($batas_terlewati && $stat_alpha > 0): ?>
<div class="alert alert-danger border-0 rounded-4 shadow-sm mb-3 d-flex align-items-center py-2 px-3" style="font-size:0.88rem;">
    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
    <span>Batas waktu absensi (<strong><?= substr($batas_waktu_alpa,0,5) ?> WIB</strong>) telah terlewati. 
    Sistem mencatat <strong><?= $stat_alpha ?> guru</strong> sebagai <strong>Alpha</strong>.</span>
</div>
<?php elseif (!$batas_terlewati && $stat_belum > 0 && $tanggal_filter === $today): ?>
<div class="alert alert-warning border-0 rounded-4 shadow-sm mb-3 d-flex align-items-center py-2 px-3" style="font-size:0.88rem;">
    <i class="bi bi-clock-fill text-warning me-2"></i>
    <span>Batas waktu Alpa: <strong><?= substr($batas_waktu_alpa,0,5) ?> WIB</strong>. 
    <strong><?= $stat_belum ?> guru</strong> belum melakukan absensi.</span>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg,#fff,#f4fbf7);border-left:4px solid #198754!important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px;height:44px;background:#198754;"><i class="bi bi-check-circle-fill fs-5"></i></div>
                <div><span class="text-muted small fw-semibold text-uppercase" style="font-size:.73rem;">Hadir</span><h3 class="fw-bold mb-0 text-success"><?= $stat_hadir ?></h3></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg,#fff,#fffdf4);border-left:4px solid #ffc107!important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px;height:44px;background:#ffc107;"><i class="bi bi-clock-history fs-5"></i></div>
                <div><span class="text-muted small fw-semibold text-uppercase" style="font-size:.73rem;">Terlambat</span><h3 class="fw-bold mb-0 text-warning"><?= $stat_terlambat ?></h3></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg,#fff,#fdf6f6);border-left:4px solid #dc3545!important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px;height:44px;background:#dc3545;"><i class="bi bi-person-x-fill fs-5"></i></div>
                <div><span class="text-muted small fw-semibold text-uppercase" style="font-size:.73rem;">Alpha</span><h3 class="fw-bold mb-0 text-danger"><?= $stat_alpha ?></h3></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg,#fff,#f3f0ff);border-left:4px solid #6f42c1!important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px;height:44px;background:#6f42c1;"><i class="bi bi-hourglass-split fs-5"></i></div>
                <div><span class="text-muted small fw-semibold text-uppercase" style="font-size:.73rem;">Belum Absen</span><h3 class="fw-bold mb-0" style="color:#6f42c1;"><?= $stat_belum ?></h3></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg,#fff,#f0faff);border-left:4px solid #0dcaf0!important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px;height:44px;background:#0dcaf0;"><i class="bi bi-info-circle-fill fs-5"></i></div>
                <div><span class="text-muted small fw-semibold text-uppercase" style="font-size:.73rem;">Izin / Sakit</span><h3 class="fw-bold mb-0 text-info"><?= $stat_izin + $stat_sakit ?></h3></div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Data Absensi -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-table text-primary me-2"></i>Rekapitulasi Kehadiran</h5>
            <p class="text-muted small mb-0"><?= $tanggal_label ?></p>
        </div>
    </div>
    <div class="card-body p-4 pt-2">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="45">No</th>
                        <th>Nama Guru</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status Absensi</th>
                        <th>Akun Google</th>
                        <th>Metode</th>
                        <th class="text-center" width="130">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0 || count($guru_belum_absen) > 0): ?>
                        <?php $no=1; ?>
                        
                        <!-- Guru yang sudah punya catatan absensi -->
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <span class="d-block fw-bold text-dark"><?= htmlspecialchars($row['nama_guru']) ?></span>
                                <small class="text-muted">NIP. <?= htmlspecialchars($row['nip']) ?></small>
                            </td>
                            <td class="fw-medium text-dark">
                                <?= $row['jam_masuk'] ? substr($row['jam_masuk'],0,5) : '<span class="text-muted">--:--</span>' ?>
                            </td>
                            <td class="fw-medium text-dark">
                                <?= $row['jam_pulang'] ? substr($row['jam_pulang'],0,5) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td>
                                <?php if($row['status'] == 'Hadir'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill">Hadir</span>
                                <?php elseif($row['status'] == 'Terlambat'): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill">Terlambat</span>
                                <?php elseif($row['status'] == 'Alpha'): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">Alpha</span>
                                <?php elseif($row['status'] == 'Izin'): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill">Izin</span>
                                <?php elseif($row['status'] == 'Sakit'): ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">Sakit</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['status_google'] === 'terhubung'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill">
                                        <i class="bi bi-google me-1"></i> Terhubung
                                    </span>
                                    <small class="d-block text-muted font-monospace mt-1" style="font-size:0.75rem;"><?= htmlspecialchars($row['email_google']) ?></small>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill">
                                        <i class="bi bi-google me-1"></i> Belum Ditautkan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['metode_verifikasi'] === 'AI Face Recognition'): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 rounded-pill">
                                        <i class="bi bi-shield-check me-1"></i>AI Face
                                    </span>
                                <?php elseif($row['metode_verifikasi'] === 'Sistem'): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill">
                                        <i class="bi bi-robot me-1"></i>Auto
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-light border text-dark px-2 py-1 rounded-pill">
                                        <i class="bi bi-pencil me-1"></i>Manual
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($row['status'] === 'Alpha'): ?>
                                    <?php if ($row['status_google'] === 'terhubung'): ?>
                                        <button class="btn btn-sm btn-outline-warning rounded-pill px-3 btn-ingatkan" 
                                                data-id="<?= $row['id_guru'] ?>" 
                                                data-nama="<?= htmlspecialchars($row['nama_guru']) ?>" 
                                                data-email="<?= htmlspecialchars($row['email_google']) ?>"
                                                data-tanggal="<?= $tanggal_filter ?>">
                                            <i class="bi bi-bell-fill me-1"></i> Ingatkan
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled title="Guru belum menautkan akun Google">
                                            <i class="bi bi-bell me-1"></i> Ingatkan
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <!-- Guru yang BELUM absen -->
                        <?php foreach($guru_belum_absen as $gb): ?>
                        <tr class="<?= $batas_terlewati ? 'table-danger bg-opacity-10' : 'table-warning bg-opacity-10' ?>">
                            <td><?= $no++ ?></td>
                            <td>
                                <span class="d-block fw-bold text-dark"><?= htmlspecialchars($gb['nama_guru']) ?></span>
                                <small class="text-muted">NIP. <?= htmlspecialchars($gb['nip'] ?? '-') ?></small>
                            </td>
                            <td><span class="text-muted">--:--</span></td>
                            <td><span class="text-muted">-</span></td>
                            <td>
                                <?php if ($batas_terlewati): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill">
                                        <i class="bi bi-hourglass-bottom me-1"></i>Alpha (Proses)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">
                                        <i class="bi bi-hourglass me-1"></i>Belum Absen
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($gb['status_google'] === 'terhubung'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill">
                                        <i class="bi bi-google me-1"></i> Terhubung
                                    </span>
                                    <small class="d-block text-muted font-monospace mt-1" style="font-size:0.75rem;"><?= htmlspecialchars($gb['email_google']) ?></small>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border px-2 py-1 rounded-pill">
                                        <i class="bi bi-google me-1"></i> Belum Ditautkan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light border text-muted px-2 py-1">-</span></td>
                            <td class="text-center">
                                <?php if ($gb['status_google'] === 'terhubung'): ?>
                                    <button class="btn btn-sm btn-primary rounded-pill px-3 btn-ingatkan" 
                                            data-id="<?= $gb['id_guru'] ?>" 
                                            data-nama="<?= htmlspecialchars($gb['nama_guru']) ?>" 
                                            data-email="<?= htmlspecialchars($gb['email_google']) ?>"
                                            data-tanggal="<?= $tanggal_filter ?>">
                                        <i class="bi bi-bell-fill me-1"></i> Ingatkan
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" disabled title="Guru belum menautkan akun Google">
                                        <i class="bi bi-bell me-1"></i> Ingatkan
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-3"></i>Belum ada data absensi untuk tanggal ini.
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Kirim Pengingat -->
<div class="modal fade" id="modalConfirmReminder" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-envelope-paper-heart-fill me-2"></i>Kirim Pengingat Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-muted mb-3">
                    Kirimkan notifikasi email pengingat resmi ke guru berikut?
                </p>
                <div class="p-3 bg-light rounded-3 border">
                    <div class="row mb-1">
                        <div class="col-4 text-muted small fw-semibold">Nama Guru:</div>
                        <div class="col-8 fw-bold text-dark" id="modalReminderNama">-</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 text-muted small fw-semibold">Email Gmail:</div>
                        <div class="col-8 font-monospace text-primary fw-semibold" id="modalReminderEmail">-</div>
                    </div>
                    <div class="row">
                        <div class="col-4 text-muted small fw-semibold">Tanggal Absen:</div>
                        <div class="col-8 text-secondary small" id="modalReminderTanggal">-</div>
                    </div>
                </div>
                <div class="alert alert-info py-2 px-3 small rounded-3 mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i> Email pengingat akan dikirimkan secara langsung ke kotak masuk Google guru.
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnExecuteReminder">
                    <span id="spinnerReminder" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                    <span id="btnReminderText"><i class="bi bi-send me-1"></i> Kirim Sekarang</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Riwayat Pengingat -->
<div class="modal fade" id="modalRiwayatPengingat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>Riwayat Pengiriman Pengingat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div id="loadingRiwayat" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted small mt-2">Memuat riwayat pengiriman...</p>
                </div>
                <div id="contentRiwayat" class="table-responsive d-none">
                    <table class="table table-sm table-hover align-middle" style="font-size:0.87rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Waktu Kirim</th>
                                <th>Nama Guru</th>
                                <th>Email Tujuan</th>
                                <th>Jenis</th>
                                <th>Pengirim</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyRiwayat">
                            <!-- Diisi via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-top py-2 px-4">
                <button type="button" class="btn btn-secondary rounded-pill btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Script Interaktif Pengingat Admin -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reminderAlert = document.getElementById('adminReminderAlert');
    const modalConfirmEl = document.getElementById('modalConfirmReminder');
    const modalConfirm = new bootstrap.Modal(modalConfirmEl);

    const modalReminderNama = document.getElementById('modalReminderNama');
    const modalReminderEmail = document.getElementById('modalReminderEmail');
    const modalReminderTanggal = document.getElementById('modalReminderTanggal');
    const btnExecuteReminder = document.getElementById('btnExecuteReminder');
    const spinnerReminder = document.getElementById('spinnerReminder');
    const btnReminderText = document.getElementById('btnReminderText');

    let activeGuruId = 0;
    let activeTanggal = '';
    let currentTriggerButton = null;

    function showAlert(type, msg) {
        reminderAlert.className = `alert alert-${type} alert-dismissible fade show rounded-4 shadow-sm py-2 px-3 mb-3`;
        reminderAlert.innerHTML = `
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
            ${msg}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        reminderAlert.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Klik tombol Ingatkan di baris tabel
    document.querySelectorAll('.btn-ingatkan').forEach(btn => {
        btn.addEventListener('click', function() {
            activeGuruId = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const email = this.getAttribute('data-email');
            activeTanggal = this.getAttribute('data-tanggal');
            currentTriggerButton = this;

            modalReminderNama.textContent = nama;
            modalReminderEmail.textContent = email;
            modalReminderTanggal.textContent = activeTanggal;

            modalConfirm.show();
        });
    });

    // Eksekusi pengiriman pengingat
    btnExecuteReminder.addEventListener('click', function() {
        if (!activeGuruId) return;

        btnExecuteReminder.disabled = true;
        spinnerReminder.classList.remove('d-none');
        btnReminderText.textContent = 'Mengirim...';

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
            spinnerReminder.classList.add('d-none');
            btnReminderText.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Sekarang';
            btnExecuteReminder.disabled = false;
            modalConfirm.hide();

            if (data.success) {
                showAlert('success', data.message);
                if (currentTriggerButton) {
                    currentTriggerButton.classList.remove('btn-primary');
                    currentTriggerButton.classList.add('btn-outline-success');
                    currentTriggerButton.innerHTML = '<i class="bi bi-check-lg me-1"></i> Terkirim';
                    currentTriggerButton.disabled = true;
                }
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(err => {
            spinnerReminder.classList.add('d-none');
            btnReminderText.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Sekarang';
            btnExecuteReminder.disabled = false;
            modalConfirm.hide();
            showAlert('danger', 'Terjadi kesalahan sistem saat menghubungi server pengingat.');
        });
    });

    // Buka modal riwayat pengingat
    const modalRiwayatEl = document.getElementById('modalRiwayatPengingat');
    const loadingRiwayat = document.getElementById('loadingRiwayat');
    const contentRiwayat = document.getElementById('contentRiwayat');
    const tbodyRiwayat = document.getElementById('tbodyRiwayat');

    modalRiwayatEl.addEventListener('show.bs.modal', function() {
        loadingRiwayat.classList.remove('d-none');
        contentRiwayat.classList.add('d-none');
        tbodyRiwayat.innerHTML = '';

        fetch('api_pengingat_absensi.php?action=riwayat')
            .then(res => res.json())
            .then(data => {
                loadingRiwayat.classList.add('d-none');
                contentRiwayat.classList.remove('d-none');

                if (data.success && data.data.length > 0) {
                    let html = '';
                    data.data.forEach(item => {
                        const statusBadge = item.status_pengiriman === 'berhasil'
                            ? '<span class="badge bg-success-subtle text-success border border-success">Berhasil</span>'
                            : '<span class="badge bg-danger-subtle text-danger border border-danger" title="'+ (item.keterangan || '') +'">Gagal</span>';
                        
                        const jenisBadge = item.jenis_pengingat === 'otomatis'
                            ? '<span class="badge bg-info-subtle text-info">Otomatis</span>'
                            : '<span class="badge bg-primary-subtle text-primary">Manual</span>';

                        html += `
                            <tr>
                                <td class="text-nowrap">${item.created_at}</td>
                                <td class="fw-semibold">${item.nama_guru}</td>
                                <td class="font-monospace small text-truncate" style="max-width:160px;">${item.email_tujuan}</td>
                                <td>${jenisBadge}</td>
                                <td>${item.pengirim}</td>
                                <td>${statusBadge}</td>
                            </tr>
                        `;
                    });
                    tbodyRiwayat.innerHTML = html;
                } else {
                    tbodyRiwayat.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat pengiriman pengingat.</td></tr>';
                }
            })
            .catch(err => {
                loadingRiwayat.classList.add('d-none');
                contentRiwayat.classList.remove('d-none');
                tbodyRiwayat.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Gagal memuat riwayat pengingat.</td></tr>';
            });
    });
});
</script>
