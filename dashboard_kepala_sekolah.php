<?php
require_once 'auth.php';
check_login();
check_role('kepala_sekolah');

require_once 'config/database.php';

date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d');

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
// 1. STATISTIK UTAMA (DATA REAL DARI DATABASE)
// ============================================================

// Total Guru
$q_guru = mysqli_query($conn, "SELECT COUNT(*) as total FROM guru");
$total_guru = (int) mysqli_fetch_assoc($q_guru)['total'];

// Hadir Hari Ini
$q_hadir = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi WHERE tanggal = '$today' AND status = 'Hadir'");
$hadir = (int) mysqli_fetch_assoc($q_hadir)['total'];

// Terlambat Hari Ini
$q_terlambat = mysqli_query($conn, "SELECT COUNT(*) as total FROM absensi WHERE tanggal = '$today' AND status = 'Terlambat'");
$terlambat = (int) mysqli_fetch_assoc($q_terlambat)['total'];

// Total yang sudah absen hari ini
$q_sudah_absen = mysqli_query($conn, "SELECT COUNT(DISTINCT id_guru) as total FROM absensi WHERE tanggal = '$today'");
$sudah_absen = (int) mysqli_fetch_assoc($q_sudah_absen)['total'];

// Tidak Hadir (Total guru dikurangi yang sudah absen)
$tidak_hadir = max(0, $total_guru - $sudah_absen);

// Persentase Kehadiran
$persen_hadir = $total_guru > 0 ? round((($hadir + $terlambat) / $total_guru) * 100) : 0;

// ============================================================
// 2. DATA GRAFIK 7 HARI TERAKHIR (DATA REAL DATABASE)
// ============================================================
$chart_labels = [];
$chart_hadir = [];
$chart_terlambat = [];
$chart_tidak_hadir = [];

for ($i = 6; $i >= 0; $i--) {
    $tgl_loop = date('Y-m-d', strtotime("-$i days"));
    $label_loop = $hari_indo[date('l', strtotime($tgl_loop))] . ' (' . date('d/m', strtotime($tgl_loop)) . ')';
    $chart_labels[] = $label_loop;

    $q_loop = mysqli_query($conn, "SELECT 
        SUM(CASE WHEN status = 'Hadir' THEN 1 ELSE 0 END) as h,
        SUM(CASE WHEN status = 'Terlambat' THEN 1 ELSE 0 END) as t,
        COUNT(DISTINCT id_guru) as total_absen
        FROM absensi WHERE tanggal = '$tgl_loop'");
    $row_loop = mysqli_fetch_assoc($q_loop);

    $h = (int)($row_loop['h'] ?? 0);
    $t = (int)($row_loop['t'] ?? 0);
    $absen_count = (int)($row_loop['total_absen'] ?? 0);
    $th = max(0, $total_guru - $absen_count);

    $chart_hadir[] = $h;
    $chart_terlambat[] = $t;
    $chart_tidak_hadir[] = $th;
}

// ============================================================
// 3. DAFTAR ABSENSI TERBARU (READ ONLY)
// ============================================================
$filter_tanggal = isset($_GET['tanggal']) && !empty($_GET['tanggal']) ? $_GET['tanggal'] : $today;
$filter_status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : '';

$where_clauses = ["a.tanggal = '" . mysqli_real_escape_string($conn, $filter_tanggal) . "'"];
if (!empty($filter_status)) {
    $where_clauses[] = "a.status = '" . mysqli_real_escape_string($conn, $filter_status) . "'";
}
$where_sql = implode(' AND ', $where_clauses);

$q_absensi = mysqli_query($conn, "SELECT a.*, g.nama_guru, g.nip, g.foto 
    FROM absensi a 
    JOIN guru g ON a.id_guru = g.id_guru 
    WHERE $where_sql 
    ORDER BY a.jam_masuk DESC");
?>
<?php include 'includes/header.php'; ?>

<!-- Header Title & Executive Summary -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <div class="d-flex align-items-center gap-2">
            <h3 class="fw-bold mb-0 text-dark"><i class="bi bi-person-workspace text-primary me-2"></i>Dashboard Eksekutif Kepala Sekolah</h3>
            <span class="badge bg-secondary bg-opacity-25 text-dark rounded-pill px-3 py-1 small fw-semibold">
                <i class="bi bi-eye me-1"></i>Read Only
            </span>
        </div>
        <p class="text-muted mb-0 mt-1"><i class="bi bi-calendar-event me-1"></i><?= $hari_ini_teks ?> &bull; Pemantauan Kehadiran Guru</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm">
            <i class="bi bi-printer me-1"></i> Cetak Ringkasan
        </button>
        <a href="jadwal_kepsek.php" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
            <i class="bi bi-calendar3 me-1"></i> Jadwal Pelajaran
        </a>
    </div>
</div>

<!-- 4 Stat Cards Real Data (Kepala Sekolah) -->
<div class="row g-3 mb-4">
    <!-- Total Guru -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f8faff); border-left:4px solid #0d6efd !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #0d6efd, #0b5ed7);">
                    <i class="bi bi-people-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Total Guru</span>
                    <h2 class="fw-bold mb-0 text-dark"><?= $total_guru ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;">Tenaga pendidik terdaftar</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Guru Hadir -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f4fbf7); border-left:4px solid #198754 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #198754, #157347);">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Guru Hadir</span>
                    <h2 class="fw-bold mb-0 text-success"><?= $hadir ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;">Tepat waktu hari ini</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Guru Terlambat -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fffdf4); border-left:4px solid #ffc107 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #ffc107, #e0a800);">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Guru Terlambat</span>
                    <h2 class="fw-bold mb-0 text-warning"><?= $terlambat ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;">Toleransi terlewati</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Tidak Hadir -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fdf6f6); border-left:4px solid #dc3545 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:54px; height:54px; background:linear-gradient(135deg, #dc3545, #b02a37);">
                    <i class="bi bi-x-octagon-fill fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing:0.5px;">Tidak Hadir</span>
                    <h2 class="fw-bold mb-0 text-danger"><?= $tidak_hadir ?></h2>
                    <small class="text-muted" style="font-size:0.75rem;">Belum tercatat absen</small>
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
                    <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-graph-up me-2 text-primary"></i>Statistik Kehadiran Guru (7 Hari Terakhir)</h5>
                    <p class="text-muted small mb-0">Tren absensi tenaga pendidik berdasarkan database riil</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill fw-semibold">
                        Tingkat Kehadiran: <?= $persen_hadir ?>%
                    </span>
                </div>
            </div>
            <div class="card-body p-4">
                <div style="position:relative; height:280px; width:100%;">
                    <canvas id="kepsekTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Donut Distribusi Status -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-pie-chart me-2 text-primary"></i>Rangkuman Hari Ini</h5>
                <p class="text-muted small mb-0">Komposisi kehadiran tanggal <?= date('d/m/Y') ?></p>
            </div>
            <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                <div style="position:relative; height:200px; width:200px;" class="mb-3">
                    <canvas id="kepsekDonutChart"></canvas>
                </div>
                <div class="w-100 mt-2">
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span class="small text-muted"><span class="badge rounded-circle bg-success me-2" style="width:10px;height:10px;display:inline-block;"></span>Hadir</span>
                        <span class="fw-bold text-success"><?= $hadir ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span class="small text-muted"><span class="badge rounded-circle bg-warning me-2" style="width:10px;height:10px;display:inline-block;"></span>Terlambat</span>
                        <span class="fw-bold text-warning"><?= $terlambat ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span class="small text-muted"><span class="badge rounded-circle bg-danger me-2" style="width:10px;height:10px;display:inline-block;"></span>Tidak Hadir</span>
                        <span class="fw-bold text-danger"><?= $tidak_hadir ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row Daftar Absensi Terbaru (READ ONLY) -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-card-checklist text-primary me-2"></i>Daftar Absensi Guru</h5>
                        <p class="text-muted small mb-0">Data kehadiran tanggal <strong><?= date('d M Y', strtotime($filter_tanggal)) ?></strong> (Read-Only)</p>
                    </div>
                    <!-- Form Filter Tanggal & Status (Read-Only Viewer) -->
                    <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                        <div>
                            <input type="date" name="tanggal" value="<?= htmlspecialchars($filter_tanggal) ?>" class="form-control form-control-sm rounded-pill shadow-sm" style="font-size:0.85rem;">
                        </div>
                        <div>
                            <select name="status" class="form-select form-select-sm rounded-pill shadow-sm" style="font-size:0.85rem;">
                                <option value="">Semua Status</option>
                                <option value="Hadir" <?= $filter_status == 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                                <option value="Terlambat" <?= $filter_status == 'Terlambat' ? 'selected' : '' ?>>Terlambat</option>
                                <option value="Izin" <?= $filter_status == 'Izin' ? 'selected' : '' ?>>Izin</option>
                                <option value="Sakit" <?= $filter_status == 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                                <option value="Alpha" <?= $filter_status == 'Alpha' ? 'selected' : '' ?>>Alpha</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                            <i class="bi bi-filter me-1"></i> Filter
                        </button>
                        <?php if($filter_tanggal != $today || !empty($filter_status)): ?>
                            <a href="dashboard_kepala_sekolah.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                Reset
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card-body p-4 pt-2">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 border-top">
                        <thead class="table-light">
                            <tr>
                                <th class="text-muted fw-semibold small py-3 ps-3">NAMA GURU</th>
                                <th class="text-muted fw-semibold small py-3">JAM MASUK</th>
                                <th class="text-muted fw-semibold small py-3">JAM PULANG</th>
                                <th class="text-muted fw-semibold small py-3">STATUS</th>
                                <th class="text-muted fw-semibold small py-3 text-center">METODE VERIFIKASI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($q_absensi) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($q_absensi)): ?>
                                    <?php
                                        // Inisial avatar
                                        $nama_parts = explode(' ', $row['nama_guru']);
                                        $initials = strtoupper(substr($nama_parts[0], 0, 1) . (isset($nama_parts[1]) ? substr($nama_parts[1], 0, 1) : ''));
                                        
                                        // Badge status
                                        $badge_class = 'bg-secondary';
                                        if($row['status'] == 'Hadir') $badge_class = 'bg-success';
                                        elseif($row['status'] == 'Terlambat') $badge_class = 'bg-warning text-dark';
                                        elseif(in_array($row['status'], ['Izin', 'Sakit'])) $badge_class = 'bg-info text-dark';
                                        elseif($row['status'] == 'Alpha') $badge_class = 'bg-danger';
                                    ?>
                                    <tr>
                                        <td class="ps-3 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-3 shadow-sm" style="width:38px; height:38px; background:linear-gradient(135deg, #0d6efd, #0b5ed7); font-size:0.85rem; flex-shrink:0;">
                                                    <?= $initials ?>
                                                </div>
                                                <div>
                                                    <span class="d-block fw-bold text-dark mb-0"><?= htmlspecialchars($row['nama_guru']) ?></span>
                                                    <small class="text-muted">NIP. <?= htmlspecialchars($row['nip'] ?? '-') ?></small>
                                                </div>
                                            </div>
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
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>
                                        Tidak ada catatan absensi untuk kriteria yang dipilih.
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
    const ctxTrend = document.getElementById('kepsekTrendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Hadir',
                    data: <?= json_encode($chart_hadir) ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Terlambat',
                    data: <?= json_encode($chart_terlambat) ?>,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Tidak Hadir',
                    data: <?= json_encode($chart_tidak_hadir) ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.05)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5
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
                    cornerRadius: 8
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

    // 2. Donut Chart Hari Ini
    const ctxDonut = document.getElementById('kepsekDonutChart').getContext('2d');
    new Chart(ctxDonut, {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Terlambat', 'Tidak Hadir'],
            datasets: [{
                data: [<?= $hadir ?>, <?= $terlambat ?>, <?= $tidak_hadir ?>],
                backgroundColor: ['#198754', '#ffc107', '#dc3545'],
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

<?php include 'includes/footer.php'; ?>
