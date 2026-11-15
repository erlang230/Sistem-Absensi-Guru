<?php
require_once 'auth.php';
check_login();
check_role('guru');
require_once 'config/database.php';

date_default_timezone_set('Asia/Jakarta');

$id_user = $_SESSION['user_id'];
$stmt_g = mysqli_prepare($conn, "SELECT id_guru, nama_guru, nip FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_g, "i", $id_user);
mysqli_stmt_execute($stmt_g);
$guru_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_g));

if (!$guru_info) {
    die("Data guru tidak ditemukan.");
}

$id_guru   = $guru_info['id_guru'];
$nama_guru = $guru_info['nama_guru'];
$nip_guru  = $guru_info['nip'] ?? '-';

// ============================================================
// FILTER BULAN & TAHUN
// ============================================================
$filter_bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';
$filter_tahun = isset($_GET['tahun']) ? trim($_GET['tahun']) : date('Y');

$nama_bulan = [
    '1' => 'Januari', '2' => 'Februari', '3' => 'Maret',
    '4' => 'April', '5' => 'Mei', '6' => 'Juni',
    '7' => 'Juli', '8' => 'Agustus', '9' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Daftar tahun yang ada pada riwayat guru ini
$q_thn = mysqli_query($conn, "SELECT DISTINCT YEAR(tanggal) as thn FROM absensi WHERE id_guru = $id_guru ORDER BY thn DESC");
$list_tahun = [];
while ($t = mysqli_fetch_assoc($q_thn)) {
    if (!empty($t['thn'])) $list_tahun[] = $t['thn'];
}
if (!in_array(date('Y'), $list_tahun)) {
    $list_tahun[] = date('Y');
    rsort($list_tahun);
}

// Build WHERE SQL
$where_conditions = ["id_guru = $id_guru"];
if (!empty($filter_bulan)) {
    $where_conditions[] = "MONTH(tanggal) = " . intval($filter_bulan);
}
if (!empty($filter_tahun)) {
    $where_conditions[] = "YEAR(tanggal) = " . intval($filter_tahun);
}
$where_sql = implode(' AND ', $where_conditions);

// ============================================================
// STATISTIK KEHADIRAN PRIBADI
// ============================================================
$sql_stat = "SELECT 
    SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir,
    SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) as total_terlambat,
    SUM(CASE WHEN status = 'Izin' THEN 1 ELSE 0 END) as total_izin,
    SUM(CASE WHEN status = 'Sakit' THEN 1 ELSE 0 END) as total_sakit,
    SUM(CASE WHEN status = 'Alpha' THEN 1 ELSE 0 END) as total_alpha,
    COUNT(*) as total_semua
    FROM absensi 
    WHERE $where_sql";
$stat = mysqli_fetch_assoc(mysqli_query($conn, $sql_stat));

$total_hadir     = (int)($stat['total_hadir'] ?? 0);
$total_terlambat = (int)($stat['total_terlambat'] ?? 0);
$total_izin      = (int)($stat['total_izin'] ?? 0);
$total_sakit     = (int)($stat['total_sakit'] ?? 0);
$total_alpha     = (int)($stat['total_alpha'] ?? 0);
$total_semua     = (int)($stat['total_semua'] ?? 0);

// ============================================================
// DATA RIWAYAT
// ============================================================
$sql = "SELECT * FROM absensi WHERE $where_sql ORDER BY tanggal DESC, jam_masuk DESC";
$result = mysqli_query($conn, $sql);
?>
<?php include 'includes/header.php'; ?>

<!-- Print Stylesheet -->
<style>
@media print {
    #sidebar, .navbar, footer, .no-print, .btn, form {
        display: none !important;
    }
    #content {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    body {
        background-color: #fff !important;
        color: #000 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .table {
        border-color: #dee2e6 !important;
    }
    .print-header {
        display: block !important;
    }
}
.print-header {
    display: none;
}
</style>

<!-- KOP SURAT KHUSUS PRINT -->
<div class="print-header mb-4 text-center border-bottom pb-3">
    <h4 class="fw-bold text-uppercase mb-1">SISTEM ABSENSI GURU (SAG)</h4>
    <h5 class="fw-semibold text-secondary mb-1">Riwayat Kehadiran Guru</h5>
    <p class="mb-0">
        Nama: <strong><?= htmlspecialchars($nama_guru) ?></strong> &bull; NIP: <strong><?= htmlspecialchars($nip_guru) ?></strong>
    </p>
    <small class="text-muted">
        Periode: <?= !empty($filter_bulan) ? $nama_bulan[$filter_bulan] : 'Semua Bulan' ?> <?= !empty($filter_tahun) ? $filter_tahun : 'Semua Tahun' ?>
        &bull; Dicetak pada: <?= date('d/m/Y H:i') ?> WIB
    </small>
</div>

<!-- Header Page -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-clock-history text-primary me-2"></i>Riwayat Absensi Anda
        </h3>
        <p class="text-muted mb-0">
            Log kehadiran pribadi untuk <strong><?= htmlspecialchars($nama_guru) ?></strong>
        </p>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4 shadow-sm">
            <i class="bi bi-printer me-2"></i> Cetak Riwayat Sendiri
        </button>
    </div>
</div>

<!-- Statistik Personal Kehadiran -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f4fbf7); border-left:4px solid #198754 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px; height:44px; background:#198754;">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Hadir</span>
                    <h3 class="fw-bold mb-0 text-success"><?= $total_hadir ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fffdf4); border-left:4px solid #ffc107 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px; height:44px; background:#ffc107;">
                    <i class="bi bi-clock-history fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Terlambat</span>
                    <h3 class="fw-bold mb-0 text-warning"><?= $total_terlambat ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f0faff); border-left:4px solid #0dcaf0 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px; height:44px; background:#0dcaf0;">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Izin</span>
                    <h3 class="fw-bold mb-0 text-info"><?= $total_izin ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f8f0fc); border-left:4px solid #6f42c1 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px; height:44px; background:#6f42c1;">
                    <i class="bi bi-heart-pulse-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Sakit</span>
                    <h3 class="fw-bold mb-0" style="color:#6f42c1;"><?= $total_sakit ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fdf6f6); border-left:4px solid #dc3545 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:44px; height:44px; background:#dc3545;">
                    <i class="bi bi-x-circle-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Alpha</span>
                    <h3 class="fw-bold mb-0 text-danger"><?= $total_alpha ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Bulan & Tahun -->
<div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
    <div class="card-body p-4">
        <form method="GET" action="riwayat_absensi.php" class="row g-3 align-items-end">
            <div class="col-12 col-sm-5 col-md-4">
                <label class="form-label small fw-bold text-muted">Pilih Bulan</label>
                <select name="bulan" class="form-select rounded-pill shadow-sm">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($nama_bulan as $num => $nama): ?>
                        <option value="<?= $num ?>" <?= $filter_bulan == $num ? 'selected' : '' ?>>
                            <?= $nama ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-4 col-md-3">
                <label class="form-label small fw-bold text-muted">Pilih Tahun</label>
                <select name="tahun" class="form-select rounded-pill shadow-sm">
                    <option value="">Semua Tahun</option>
                    <?php foreach ($list_tahun as $thn): ?>
                        <option value="<?= $thn ?>" <?= $filter_tahun == $thn ? 'selected' : '' ?>>
                            <?= $thn ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-3 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4 flex-grow-1 shadow-sm">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="riwayat_absensi.php" class="btn btn-outline-secondary rounded-pill px-3" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Riwayat Kehadiran -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-list-check text-primary me-2"></i>Catatan Presensi Pribadi</h5>
            <p class="text-muted small mb-0">Total <strong><?= $total_semua ?></strong> catatan absensi ditemukan</p>
        </div>
    </div>
    <div class="card-body p-4 pt-2">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 border-top">
                <thead class="table-light">
                    <tr>
                        <th class="text-muted fw-semibold small py-3 ps-3" style="width:50px;">NO</th>
                        <th class="text-muted fw-semibold small py-3">TANGGAL</th>
                        <th class="text-muted fw-semibold small py-3">JAM MASUK</th>
                        <th class="text-muted fw-semibold small py-3">JAM PULANG</th>
                        <th class="text-muted fw-semibold small py-3">STATUS</th>
                        <th class="text-muted fw-semibold small py-3 text-center">METODE VERIFIKASI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php $no = 1; while($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                            $badge_class = 'bg-secondary';
                            if($row['status'] == 'Hadir') $badge_class = 'bg-success';
                            elseif($row['status'] == 'Terlambat') $badge_class = 'bg-warning text-dark';
                            elseif($row['status'] == 'Izin') $badge_class = 'bg-info text-dark';
                            elseif($row['status'] == 'Sakit') $badge_class = 'bg-purple text-white" style="background:#6f42c1;"';
                            elseif($row['status'] == 'Alpha') $badge_class = 'bg-danger';
                        ?>
                        <tr>
                            <td class="ps-3 py-3 fw-bold text-muted"><?= $no++ ?></td>
                            <td class="py-3">
                                <i class="bi bi-calendar-check text-primary me-1"></i>
                                <span class="fw-bold text-dark"><?= date('d M Y', strtotime($row['tanggal'])) ?></span>
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
                                <span class="badge <?= $badge_class ?> px-3 py-2 rounded-pill fw-semibold">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td class="py-3 text-center">
                                <?php if($row['metode_verifikasi'] === 'AI Face Recognition'): ?>
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
                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada riwayat absensi untuk kriteria yang dipilih.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
