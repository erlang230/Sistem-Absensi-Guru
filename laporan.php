<?php
require_once 'auth.php';
check_login();
check_roles(['admin', 'kepala_sekolah']);

require_once 'config/database.php';

date_default_timezone_set('Asia/Jakarta');

// ============================================================
// FILTER PARAMETERS
// ============================================================
$filter_bulan   = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';
$filter_tahun   = isset($_GET['tahun']) ? trim($_GET['tahun']) : date('Y');
$filter_id_guru = isset($_GET['id_guru']) ? trim($_GET['id_guru']) : '';
$filter_status  = isset($_GET['status']) ? trim($_GET['status']) : '';

// Data nama-nama bulan
$nama_bulan = [
    '1' => 'Januari', '2' => 'Februari', '3' => 'Maret',
    '4' => 'April', '5' => 'Mei', '6' => 'Juni',
    '7' => 'Juli', '8' => 'Agustus', '9' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Ambil daftar tahun dari data absensi untuk dropdown
$q_tahun = mysqli_query($conn, "SELECT DISTINCT YEAR(tanggal) as thn FROM absensi ORDER BY thn DESC");
$list_tahun = [];
while ($t = mysqli_fetch_assoc($q_tahun)) {
    if (!empty($t['thn'])) $list_tahun[] = $t['thn'];
}
if (!in_array(date('Y'), $list_tahun)) {
    $list_tahun[] = date('Y');
    rsort($list_tahun);
}

// Ambil daftar guru untuk dropdown filter
$q_list_guru = mysqli_query($conn, "SELECT id_guru, nama_guru, nip FROM guru ORDER BY nama_guru ASC");
$list_guru = [];
while ($g = mysqli_fetch_assoc($q_list_guru)) {
    $list_guru[] = $g;
}

// ============================================================
// BUILD QUERY DENGAN FILTER
// ============================================================
$where_conditions = ["1=1"];

if (!empty($filter_bulan)) {
    $where_conditions[] = "MONTH(a.tanggal) = " . intval($filter_bulan);
}
if (!empty($filter_tahun)) {
    $where_conditions[] = "YEAR(a.tanggal) = " . intval($filter_tahun);
}
if (!empty($filter_id_guru)) {
    $where_conditions[] = "a.id_guru = " . intval($filter_id_guru);
}
if (!empty($filter_status)) {
    $where_conditions[] = "a.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}

$where_sql = implode(' AND ', $where_conditions);

// ============================================================
// 1. STATISTIK SESUAI FILTER
// ============================================================
$sql_stat = "SELECT 
    SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir,
    SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) as total_terlambat,
    SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) as total_izin,
    SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) as total_sakit,
    SUM(CASE WHEN a.status = 'Alpha' THEN 1 ELSE 0 END) as total_alpha,
    COUNT(*) as total_semua
    FROM absensi a 
    WHERE $where_sql";
$q_stat = mysqli_query($conn, $sql_stat);
$stat = mysqli_fetch_assoc($q_stat);

$total_hadir     = (int)($stat['total_hadir'] ?? 0);
$total_terlambat = (int)($stat['total_terlambat'] ?? 0);
$total_izin      = (int)($stat['total_izin'] ?? 0);
$total_sakit     = (int)($stat['total_sakit'] ?? 0);
$total_alpha     = (int)($stat['total_alpha'] ?? 0);
$total_semua     = (int)($stat['total_semua'] ?? 0);

// ============================================================
// 2. DATA TABEL ABSENSI
// ============================================================
$sql_data = "SELECT a.*, g.nama_guru, g.nip 
    FROM absensi a 
    JOIN guru g ON a.id_guru = g.id_guru 
    WHERE $where_sql 
    ORDER BY a.tanggal DESC, a.jam_masuk DESC";
$q_data = mysqli_query($conn, $sql_data);

// URL Query String untuk tombol Export (Excel & PDF)
$export_params = http_build_query([
    'bulan'   => $filter_bulan,
    'tahun'   => $filter_tahun,
    'id_guru' => $filter_id_guru,
    'status'  => $filter_status
]);
?>
<?php include 'includes/header.php'; ?>

<!-- Styling Khusus Cetak / Print Mode -->
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

<!-- KOP SURAT / HEADER CETAK KHUSUS PRINT -->
<div class="print-header mb-4 text-center border-bottom pb-3">
    <h3 class="fw-bold text-uppercase mb-1">Sistem Absensi Guru (SAG) Berbasis AI</h3>
    <h5 class="fw-semibold text-secondary mb-1">Laporan Rekapitulasi Kehadiran Guru</h5>
    <p class="small text-muted mb-0">
        Periode: <?= !empty($filter_bulan) ? $nama_bulan[$filter_bulan] : 'Semua Bulan' ?> <?= !empty($filter_tahun) ? $filter_tahun : 'Semua Tahun' ?>
        &bull; Dicetak pada: <?= date('d/m/Y H:i') ?> WIB
    </p>
</div>

<!-- Header Judul & Tombol Export -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Laporan Rekapitulasi Absensi
        </h3>
        <p class="text-muted mb-0">
            Pemantauan dan ekspor riwayat absensi guru terintegrasi database
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <!-- Print Button -->
        <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
            <i class="bi bi-printer me-1"></i> Print
        </button>
        <!-- Export PDF Button -->
        <a href="export_pdf.php?<?= $export_params ?>" target="_blank" class="btn btn-danger rounded-pill px-3 shadow-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i> Export PDF
        </a>
        <!-- Export Excel Button -->
        <a href="export_excel.php?<?= $export_params ?>" class="btn btn-success rounded-pill px-3 shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
        </a>
    </div>
</div>

<!-- ============================================================ -->
<!-- 1. STATISTIK KEHADIRAN (STAT CARDS)                          -->
<!-- ============================================================ -->
<div class="row g-3 mb-4">
    <!-- Total Hadir -->
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f4fbf7); border-left:4px solid #198754 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:46px; height:46px; background:#198754;">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Total Hadir</span>
                    <h3 class="fw-bold mb-0 text-success"><?= $total_hadir ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Terlambat -->
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fffdf4); border-left:4px solid #ffc107 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:46px; height:46px; background:#ffc107;">
                    <i class="bi bi-clock-history fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Total Terlambat</span>
                    <h3 class="fw-bold mb-0 text-warning"><?= $total_terlambat ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Izin -->
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f0faff); border-left:4px solid #0dcaf0 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:46px; height:46px; background:#0dcaf0;">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Total Izin</span>
                    <h3 class="fw-bold mb-0 text-info"><?= $total_izin ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Sakit -->
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f8f0fc); border-left:4px solid #6f42c1 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:46px; height:46px; background:#6f42c1;">
                    <i class="bi bi-heart-pulse-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Total Sakit</span>
                    <h3 class="fw-bold mb-0 text-purple" style="color:#6f42c1;"><?= $total_sakit ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Alpha -->
    <div class="col-6 col-md-4 col-xl">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fdf6f6); border-left:4px solid #dc3545 !important;">
            <div class="d-flex align-items-center">
                <div class="rounded-4 p-2 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:46px; height:46px; background:#dc3545;">
                    <i class="bi bi-x-circle-fill fs-5"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Total Alpha</span>
                    <h3 class="fw-bold mb-0 text-danger"><?= $total_alpha ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- 2. FILTER BOX (BULAN, TAHUN, GURU, STATUS)                   -->
<!-- ============================================================ -->
<div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
    <div class="card-body p-4">
        <form method="GET" action="laporan.php" class="row g-3 align-items-end">
            <!-- Filter Bulan -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted">Bulan</label>
                <select name="bulan" class="form-select rounded-pill shadow-sm">
                    <option value="">Semua Bulan</option>
                    <?php foreach ($nama_bulan as $num => $nama): ?>
                        <option value="<?= $num ?>" <?= $filter_bulan == $num ? 'selected' : '' ?>>
                            <?= $nama ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Tahun -->
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted">Tahun</label>
                <select name="tahun" class="form-select rounded-pill shadow-sm">
                    <option value="">Semua Tahun</option>
                    <?php foreach ($list_tahun as $thn): ?>
                        <option value="<?= $thn ?>" <?= $filter_tahun == $thn ? 'selected' : '' ?>>
                            <?= $thn ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Guru -->
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted">Guru</label>
                <select name="id_guru" class="form-select rounded-pill shadow-sm">
                    <option value="">Semua Guru</option>
                    <?php foreach ($list_guru as $g): ?>
                        <option value="<?= $g['id_guru'] ?>" <?= $filter_id_guru == $g['id_guru'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nama_guru']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filter Status -->
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted">Status</label>
                <select name="status" class="form-select rounded-pill shadow-sm">
                    <option value="">Semua Status</option>
                    <option value="Hadir" <?= $filter_status == 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                    <option value="Terlambat" <?= $filter_status == 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    <option value="Izin" <?= $filter_status == 'Izin' ? 'selected' : '' ?>>Izin</option>
                    <option value="Sakit" <?= $filter_status == 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                    <option value="Alpha" <?= $filter_status == 'Alpha' ? 'selected' : '' ?>>Alpha</option>
                </select>
            </div>

            <!-- Tombol Filter & Reset -->
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-4 flex-grow-1 shadow-sm">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="laporan.php" class="btn btn-outline-secondary rounded-pill px-3" title="Reset Filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- 3. TABEL DATA LAPORAN ABSENSI                                -->
<!-- ============================================================ -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-table text-primary me-2"></i>Data Kehadiran Guru</h5>
            <p class="text-muted small mb-0">
                Menampilkan <strong><?= $total_semua ?></strong> baris data absensi berdasarkan filter yang aktif
            </p>
        </div>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
            Total: <?= $total_semua ?> Data
        </span>
    </div>
    <div class="card-body p-4 pt-2">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 border-top">
                <thead class="table-light">
                    <tr>
                        <th class="text-muted fw-semibold small py-3 ps-3" style="width:60px;">NO</th>
                        <th class="text-muted fw-semibold small py-3">NAMA GURU</th>
                        <th class="text-muted fw-semibold small py-3">TANGGAL</th>
                        <th class="text-muted fw-semibold small py-3">JAM MASUK</th>
                        <th class="text-muted fw-semibold small py-3">JAM PULANG</th>
                        <th class="text-muted fw-semibold small py-3">STATUS</th>
                        <th class="text-muted fw-semibold small py-3 text-center">METODE VERIFIKASI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($q_data) > 0): ?>
                        <?php $no = 1; while($row = mysqli_fetch_assoc($q_data)): ?>
                            <?php
                                // Warna badge status
                                $badge_status = 'bg-secondary';
                                if($row['status'] == 'Hadir') $badge_status = 'bg-success';
                                elseif($row['status'] == 'Terlambat') $badge_status = 'bg-warning text-dark';
                                elseif($row['status'] == 'Izin') $badge_status = 'bg-info text-dark';
                                elseif($row['status'] == 'Sakit') $badge_status = 'bg-purple text-white" style="background:#6f42c1;"';
                                elseif($row['status'] == 'Alpha') $badge_status = 'bg-danger';

                                // Inisial nama guru
                                $parts = explode(' ', $row['nama_guru']);
                                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                            ?>
                            <tr>
                                <td class="ps-3 py-3 fw-bold text-muted"><?= $no++ ?></td>
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-3 shadow-sm" style="width:38px; height:38px; background:linear-gradient(135deg, #0d6efd, #0b5ed7); font-size:0.82rem; flex-shrink:0;">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <span class="d-block fw-bold text-dark mb-0"><?= htmlspecialchars($row['nama_guru']) ?></span>
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
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                Tidak ada data absensi yang sesuai dengan kriteria filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
