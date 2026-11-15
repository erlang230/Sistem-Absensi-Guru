<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

// Proses CRUD Mata Pelajaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah'])) {
        $kode = trim($_POST['kode_mapel']);
        $nama = trim($_POST['nama_mapel']);
        $stmt = mysqli_prepare($conn, "INSERT INTO mata_pelajaran (kode_mapel, nama_mapel) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $kode, $nama);
        if (mysqli_stmt_execute($stmt)) $_SESSION['success'] = "Mata Pelajaran berhasil ditambahkan.";
        else $_SESSION['error'] = "Gagal menambah mata pelajaran. Kode mungkin sudah ada.";
        header("Location: mata_pelajaran.php"); exit;
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id_mapel'];
        $kode = trim($_POST['kode_mapel']);
        $nama = trim($_POST['nama_mapel']);
        $stmt = mysqli_prepare($conn, "UPDATE mata_pelajaran SET kode_mapel=?, nama_mapel=? WHERE id_mapel=?");
        mysqli_stmt_bind_param($stmt, "ssi", $kode, $nama, $id);
        if (mysqli_stmt_execute($stmt)) $_SESSION['success'] = "Mata Pelajaran berhasil diperbarui.";
        else $_SESSION['error'] = "Gagal memperbarui. Kode mungkin sudah terpakai.";
        header("Location: mata_pelajaran.php"); exit;
    } elseif (isset($_POST['hapus'])) {
        $id = $_POST['id_mapel'];
        $stmt = mysqli_prepare($conn, "DELETE FROM mata_pelajaran WHERE id_mapel=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) $_SESSION['success'] = "Mata Pelajaran berhasil dihapus.";
        else $_SESSION['error'] = "Gagal menghapus mata pelajaran.";
        header("Location: mata_pelajaran.php"); exit;
    }
}

$sql = "SELECT * FROM mata_pelajaran ORDER BY nama_mapel ASC";
$result = mysqli_query($conn, $sql);
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Mata Pelajaran</h3>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-circle me-1"></i> Tambah Mapel</button>
</div>

<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-triangle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="50" class="text-center">No</th>
                        <th width="150">Kode Mapel</th>
                        <th>Nama Mata Pelajaran</th>
                        <th width="120" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php $no=1; while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><span class="badge bg-dark px-2 py-1"><i class="bi bi-upc-scan me-1"></i> <?= htmlspecialchars($row['kode_mapel']) ?></span></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($row['nama_mapel']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_mapel'] ?>"><i class="bi bi-pencil-square"></i></button>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_mapel'] ?>"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="modalEdit<?= $row['id_mapel'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Edit Mata Pelajaran</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="id_mapel" value="<?= $row['id_mapel'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label fw-medium">Kode Mata Pelajaran <span class="text-danger">*</span></label>
                                                <input type="text" name="kode_mapel" class="form-control" value="<?= htmlspecialchars($row['kode_mapel']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-medium">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                                                <input type="text" name="nama_mapel" class="form-control" value="<?= htmlspecialchars($row['nama_mapel']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="edit" class="btn btn-warning">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Hapus -->
                        <div class="modal fade" id="modalHapus<?= $row['id_mapel'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content border-danger">
                                    <form method="POST">
                                        <div class="modal-body text-center p-4">
                                            <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3">Hapus Mata Pelajaran?</h5>
                                            <p class="text-muted">Data jadwal yang menggunakan mata pelajaran ini juga akan ikut terhapus secara permanen.</p>
                                            <input type="hidden" name="id_mapel" value="<?= $row['id_mapel'] ?>">
                                            <div class="mt-4">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" name="hapus" class="btn btn-danger">Ya, Hapus</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4">Belum ada data mata pelajaran.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Tambah Mata Pelajaran</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Kode Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" name="kode_mapel" class="form-control" required placeholder="Contoh: MAT01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" name="nama_mapel" class="form-control" required placeholder="Contoh: Matematika">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
