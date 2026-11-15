<?php
require_once 'auth.php';
check_login();
check_roles(['admin', 'kepala_sekolah']);

require_once 'config/database.php';

date_default_timezone_set('Asia/Jakarta');

// Parameter Filter
$filter_bulan   = isset($_GET['bulan']) ? trim($_GET['bulan']) : '';
$filter_tahun   = isset($_GET['tahun']) ? trim($_GET['tahun']) : '';
$filter_id_guru = isset($_GET['id_guru']) ? trim($_GET['id_guru']) : '';
$filter_status  = isset($_GET['status']) ? trim($_GET['status']) : '';

$nama_bulan = [
    '1' => 'Januari', '2' => 'Februari', '3' => 'Maret',
    '4' => 'April', '5' => 'Mei', '6' => 'Juni',
    '7' => 'Juli', '8' => 'Agustus', '9' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Nama Guru terpilih jika ada
$nama_guru_filter = 'Semua Guru';
if (!empty($filter_id_guru)) {
    $q_g = mysqli_query($conn, "SELECT nama_guru FROM guru WHERE id_guru = " . intval($filter_id_guru));
    if ($rg = mysqli_fetch_assoc($q_g)) {
        $nama_guru_filter = $rg['nama_guru'];
    }
}

// Build Query
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

// Statistik
$sql_stat = "SELECT 
    SUM(CASE WHEN a.status = 'Hadir' THEN 1 ELSE 0 END) as total_hadir,
    SUM(CASE WHEN a.status = 'Terlambat' THEN 1 ELSE 0 END) as total_terlambat,
    SUM(CASE WHEN a.status = 'Izin' THEN 1 ELSE 0 END) as total_izin,
    SUM(CASE WHEN a.status = 'Sakit' THEN 1 ELSE 0 END) as total_sakit,
    SUM(CASE WHEN a.status = 'Alpha' THEN 1 ELSE 0 END) as total_alpha,
    COUNT(*) as total_semua
    FROM absensi a 
    WHERE $where_sql";
$stat = mysqli_fetch_assoc(mysqli_query($conn, $sql_stat));

// Data Absensi
$sql_data = "SELECT a.*, g.nama_guru, g.nip 
    FROM absensi a 
    JOIN guru g ON a.id_guru = g.id_guru 
    WHERE $where_sql 
    ORDER BY a.tanggal DESC, a.jam_masuk DESC";
$res_data = mysqli_query($conn, $sql_data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan Absensi Guru - SAG.AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background: #f8f9fa;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            margin: 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .kop-surat {
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .table-laporan th {
            background-color: #f1f5f9 !important;
            color: #334155;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #cbd5e1;
        }
        .table-laporan td {
            font-size: 0.88rem;
            vertical-align: middle;
        }
        @media print {
            body {
                background: white;
                margin: 0;
            }
            .page {
                width: 100%;
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Action Bar (Hanya tampil di layar) -->
    <div class="no-print bg-dark text-white py-2 px-4 sticky-top shadow-sm d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
            <strong>Pratinjau Cetak / Export PDF</strong>
            <small class="text-white-50 ms-2">Gunakan opsi "Save as PDF" pada dialog cetak browser untuk menyimpan file PDF.</small>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm px-3 rounded-pill fw-bold">
                <i class="bi bi-printer me-1"></i> Cetak / Simpan PDF
            </button>
            <button onclick="window.close()" class="btn btn-outline-light btn-sm px-3 rounded-pill">
                <i class="bi bi-x me-1"></i> Tutup
            </button>
        </div>
    </div>

    <div class="page">
        <!-- KOP SURAT RESMI -->
        <div class="kop-surat text-center position-relative">
            <h4 class="fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">PEMERINTAH DAERAH PROVINSI</h4>
            <h3 class="fw-bold mb-1 text-uppercase" style="letter-spacing: 1.5px;">SISTEM ABSENSI GURU (SAG)</h3>
            <p class="mb-0 small text-muted">Jalan Pendidikan No. 45, Kompleks Edukasi Terpadu &bull; Telp. (021) 555-1234</p>
            <small class="text-muted">Website: www.sekolah.sch.id &bull; E-mail: info@sekolah.sch.id</small>
        </div>

        <!-- JUDUL & METADATA -->
        <div class="text-center mb-4">
            <h5 class="fw-bold mb-1 text-uppercase text-decoration-underline">LAPORAN REKAPITULASI KEHADIRAN GURU</h5>
            <p class="mb-0 small text-muted">
                Periode: <strong><?= !empty($filter_bulan) ? $nama_bulan[$filter_bulan] : 'Semua Bulan' ?> <?= !empty($filter_tahun) ? $filter_tahun : 'Semua Tahun' ?></strong>
                &bull; Guru: <strong><?= htmlspecialchars($nama_guru_filter) ?></strong>
                &bull; Status: <strong><?= !empty($filter_status) ? htmlspecialchars($filter_status) : 'Semua Status' ?></strong>
            </p>
        </div>

        <!-- STATISTIK KEHADIRAN -->
        <div class="row g-2 mb-4">
            <div class="col">
                <div class="stat-card" style="background:#f4fbf7; border-left:3px solid #198754;">
                    <small class="text-muted d-block text-uppercase" style="font-size:0.7rem;">Hadir</small>
                    <strong class="text-success fs-5"><?= (int)($stat['total_hadir'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" style="background:#fffdf4; border-left:3px solid #ffc107;">
                    <small class="text-muted d-block text-uppercase" style="font-size:0.7rem;">Terlambat</small>
                    <strong class="text-warning fs-5"><?= (int)($stat['total_terlambat'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" style="background:#f0faff; border-left:3px solid #0dcaf0;">
                    <small class="text-muted d-block text-uppercase" style="font-size:0.7rem;">Izin</small>
                    <strong class="text-info fs-5"><?= (int)($stat['total_izin'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" style="background:#f8f0fc; border-left:3px solid #6f42c1;">
                    <small class="text-muted d-block text-uppercase" style="font-size:0.7rem;">Sakit</small>
                    <strong class="fs-5" style="color:#6f42c1;"><?= (int)($stat['total_sakit'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" style="background:#fdf6f6; border-left:3px solid #dc3545;">
                    <small class="text-muted d-block text-uppercase" style="font-size:0.7rem;">Alpha</small>
                    <strong class="text-danger fs-5"><?= (int)($stat['total_alpha'] ?? 0) ?></strong>
                </div>
            </div>
            <div class="col">
                <div class="stat-card" style="background:#f8f9fa; border-left:3px solid #495057;">
                    <small class="text-muted d-block text-uppercase" style="font-size:0.7rem;">Total</small>
                    <strong class="text-dark fs-5"><?= (int)($stat['total_semua'] ?? 0) ?></strong>
                </div>
            </div>
        </div>

        <!-- TABEL DATA -->
        <table class="table table-bordered table-sm table-laporan mb-4">
            <thead>
                <tr class="text-center">
                    <th style="width: 35px;">No</th>
                    <th>Nama Guru</th>
                    <th>Tanggal</th>
                    <th>Jam Masuk</th>
                    <th>Jam Pulang</th>
                    <th>Status</th>
                    <th>Metode Verifikasi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(mysqli_num_rows($res_data) > 0): ?>
                    <?php $no = 1; while($row = mysqli_fetch_assoc($res_data)): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['nama_guru']) ?></strong><br>
                            <small class="text-muted">NIP. <?= htmlspecialchars($row['nip'] ?? '-') ?></small>
                        </td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                        <td class="text-center font-monospace"><?= $row['jam_masuk'] ? substr($row['jam_masuk'], 0, 5) : '-' ?></td>
                        <td class="text-center font-monospace"><?= $row['jam_pulang'] ? substr($row['jam_pulang'], 0, 5) : '-' ?></td>
                        <td class="text-center fw-bold">
                            <?= htmlspecialchars($row['status']) ?>
                        </td>
                        <td class="text-center small"><?= htmlspecialchars($row['metode_verifikasi']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Tidak ada data absensi untuk filter ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- TANDA TANGAN RESMI -->
        <div class="row mt-5" style="page-break-inside: avoid;">
            <div class="col-6 text-center">
                <p class="mb-5">
                    Mengetahui,<br>
                    <strong>Kepala Sekolah</strong>
                </p>
                <br><br>
                <p class="mb-0 text-decoration-underline fw-bold">Drs. H. Ahmad Dahlan, M.Pd</p>
                <small class="text-muted">NIP. 197503152000031001</small>
            </div>
            <div class="col-6 text-center">
                <p class="mb-5">
                    Kota Pelajar, <?= date('d') . ' ' . $nama_bulan[date('n')] . ' ' . date('Y') ?><br>
                    <strong>Petugas Absensi / Admin</strong>
                </p>
                <br><br>
                <p class="mb-0 text-decoration-underline fw-bold">Administrator Sistem</p>
                <small class="text-muted">NIP. 198804202011011002</small>
            </div>
        </div>
    </div>

</body>
</html>
