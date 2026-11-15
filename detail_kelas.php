<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

// Validasi id_kelas
$id_kelas = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_kelas <= 0) {
    $_SESSION['error'] = "ID Kelas tidak valid.";
    header("Location: data_kelas.php");
    exit;
}

// Ambil data detail kelas dengan prepared statement
$stmt_kelas = mysqli_prepare($conn, "SELECT * FROM kelas WHERE id_kelas = ?");
mysqli_stmt_bind_param($stmt_kelas, "i", $id_kelas);
mysqli_stmt_execute($stmt_kelas);
$res_kelas = mysqli_stmt_get_result($stmt_kelas);
$kelas = mysqli_fetch_assoc($res_kelas);
mysqli_stmt_close($stmt_kelas);

if (!$kelas) {
    $_SESSION['error'] = "Data kelas tidak ditemukan.";
    header("Location: data_kelas.php");
    exit;
}

// Ambil seluruh jadwal kelas dari Senin sampai Jumat
$sql_jadwal = "SELECT j.*, g.nama_guru, m.nama_mapel, m.kode_mapel 
               FROM jadwal j 
               JOIN guru g ON j.id_guru = g.id_guru 
               JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel 
               WHERE j.id_kelas = ? 
               ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC";

$stmt_jadwal = mysqli_prepare($conn, $sql_jadwal);
mysqli_stmt_bind_param($stmt_jadwal, "i", $id_kelas);
mysqli_stmt_execute($stmt_jadwal);
$result_jadwal = mysqli_stmt_get_result($stmt_jadwal);
$total_jadwal = mysqli_num_rows($result_jadwal);

// Format badge warna untuk hari
$badge_hari = [
    'Senin'  => 'bg-primary',
    'Selasa' => 'bg-info text-dark',
    'Rabu'   => 'bg-success',
    'Kamis'  => 'bg-warning text-dark',
    'Jumat'  => 'bg-danger',
    'Sabtu'  => 'bg-secondary',
    'Minggu' => 'bg-dark'
];
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="data_kelas.php" class="text-decoration-none">Data Kelas</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($kelas['nama_kelas']) ?></li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0">Jadwal Mengajar — <?= htmlspecialchars($kelas['nama_kelas']) ?></h3>
    </div>
    <div class="d-flex gap-2">
        <a href="data_kelas.php" class="btn btn-outline-secondary shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Data Kelas
        </a>
        <a href="tambah_jadwal.php" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> Tambah Jadwal
        </a>
    </div>
</div>

<!-- Informasi Kelas Banner -->
<div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
    <div class="card-body p-4">
        <div class="row align-items-center g-3">
            <div class="col-auto">
                <div class="icon-box bg-gradient-primary rounded-4 text-white p-3">
                    <i class="bi bi-easel-fill fs-3"></i>
                </div>
            </div>
            <div class="col">
                <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($kelas['nama_kelas']) ?></h4>
                <div class="d-flex flex-wrap gap-2 align-items-center text-muted small">
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-mortarboard me-1 text-primary"></i> Tingkat: <strong><?= htmlspecialchars($kelas['tingkat']) ?></strong>
                    </span>
                    <span class="badge bg-light text-dark border">
                        <i class="bi bi-book me-1 text-primary"></i> Jurusan: <strong><?= htmlspecialchars($kelas['jurusan']) ?></strong>
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <i class="bi bi-calendar-check me-1"></i> Total: <strong><?= $total_jadwal ?> Pelajaran</strong>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Jadwal Mengajar Kelas -->
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0">
            <i class="bi bi-calendar-week me-2 text-primary"></i>Daftar Jadwal Mengajar (Senin – Jumat)
        </h5>
        <span class="text-muted small">Diurutkan berdasarkan hari & jam</span>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="120">Hari</th>
                        <th width="170">Waktu</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru Pengajar</th>
                        <th width="100" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($total_jadwal > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result_jadwal)): ?>
                            <?php $badgeColor = $badge_hari[$row['hari']] ?? 'bg-primary'; ?>
                            <tr>
                                <td>
                                    <span class="badge <?= $badgeColor ?> px-3 py-2 fs-7 fw-semibold">
                                        <?= htmlspecialchars($row['hari']) ?>
                                    </span>
                                </td>
                                <td class="fw-medium text-dark">
                                    <i class="bi bi-clock me-1 text-muted"></i>
                                    <?= substr($row['jam_mulai'], 0, 5) ?> – <?= substr($row['jam_selesai'], 0, 5) ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_mapel']) ?></span>
                                    <span class="badge bg-light text-secondary border ms-1"><?= htmlspecialchars($row['kode_mapel']) ?></span>
                                </td>
                                <td>
                                    <i class="bi bi-person-fill text-muted me-1"></i>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['nama_guru']) ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="edit_jadwal.php?id=<?= $row['id_jadwal'] ?>" class="btn btn-sm btn-warning" title="Edit Jadwal">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="hapus_jadwal.php?id=<?= $row['id_jadwal'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus jadwal ini?');" title="Hapus Jadwal">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-3 text-secondary"></i>
                                <span class="fw-medium">Belum ada jadwal mengajar untuk kelas ini.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
mysqli_stmt_close($stmt_jadwal);
include 'includes/footer.php'; 
?>
