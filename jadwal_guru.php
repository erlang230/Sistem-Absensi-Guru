<?php
require_once 'auth.php';
check_login();
check_role('guru');
require_once 'config/database.php';

// Ambil id_guru berdasarkan user_id dari session
$id_user = $_SESSION['user_id'];
$stmt_g = mysqli_prepare($conn, "SELECT id_guru FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_g, "i", $id_user);
mysqli_stmt_execute($stmt_g);
$guru_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_g));
$id_guru = $guru_data['id_guru'];

$sql = "SELECT j.*, m.nama_mapel, k.nama_kelas 
        FROM jadwal j 
        JOIN mata_pelajaran m ON j.id_mapel = m.id_mapel
        JOIN kelas k ON j.id_kelas = k.id_kelas
        WHERE j.id_guru = ?
        ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_guru);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Jadwal Mengajar Saya</h3>
</div>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="100">Hari</th>
                        <th width="150">Waktu</th>
                        <th>Mata Pelajaran</th>
                        <th>Kelas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><span class="badge bg-primary px-3 py-2"><?= htmlspecialchars($row['hari']) ?></span></td>
                            <td class="fw-medium text-dark"><i class="bi bi-clock me-1 text-muted"></i> <?= substr($row['jam_mulai'], 0, 5) ?> - <?= substr($row['jam_selesai'], 0, 5) ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_mapel']) ?></td>
                            <td><span class="badge bg-secondary border"><?= htmlspecialchars($row['nama_kelas']) ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-calendar-x fs-1 d-block mb-3"></i> Anda belum memiliki jadwal mengajar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
