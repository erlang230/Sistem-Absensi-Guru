<?php
require_once 'auth.php';
check_login();
check_role('kepala_sekolah');
require_once 'config/database.php';

// Filter Tanggal
$tanggal_filter = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

$sql = "SELECT a.*, g.nama_guru, g.nip 
        FROM absensi a 
        JOIN guru g ON a.id_guru = g.id_guru 
        WHERE a.tanggal = ? 
        ORDER BY a.jam_masuk ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $tanggal_filter);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <h3 class="fw-bold mb-3 mb-md-0">Monitoring Absensi Harian</h3>
    
    <form method="GET" class="d-flex align-items-center">
        <label class="me-2 fw-medium">Pilih Tanggal:</label>
        <input type="date" name="tanggal" class="form-control me-2 shadow-sm border-primary" value="<?= htmlspecialchars($tanggal_filter) ?>" onchange="this.form.submit()">
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h5 class="fw-bold text-muted mb-4 border-bottom pb-3">Daftar Kehadiran: <?= date('d F Y', strtotime($tanggal_filter)) ?></h5>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="50">No</th>
                        <th>Nama Guru</th>
                        <th>Waktu Masuk</th>
                        <th>Waktu Pulang</th>
                        <th>Status Verifikasi</th>
                        <th>Status Kehadiran</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php $no=1; while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <span class="d-block fw-bold text-dark"><?= htmlspecialchars($row['nama_guru']) ?></span>
                                <small class="text-muted">NIP. <?= htmlspecialchars($row['nip']) ?></small>
                            </td>
                            <td class="fw-medium text-dark"><i class="bi bi-box-arrow-in-right text-success me-1"></i> <?= $row['jam_masuk'] ?></td>
                            <td class="fw-medium text-dark">
                                <?php if($row['jam_pulang']): ?>
                                    <i class="bi bi-box-arrow-right text-danger me-1"></i> <?= $row['jam_pulang'] ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Belum Pulang</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light border text-dark"><?= htmlspecialchars($row['metode_verifikasi']) ?></span></td>
                            <td>
                                <?php if($row['status'] == 'Hadir'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i> Hadir</span>
                                <?php elseif($row['status'] == 'Terlambat'): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill"><i class="bi bi-clock me-1"></i> Terlambat</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 rounded-pill"><?= htmlspecialchars($row['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-journal-x fs-2 d-block mb-3"></i> Belum ada data absensi yang terekam pada hari ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
