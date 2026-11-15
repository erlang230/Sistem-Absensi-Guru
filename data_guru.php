<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

// Fitur Pencarian
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$where = "";
$params = [];
$types = "";

if ($search != '') {
    $where = "WHERE g.nama_guru LIKE ? OR g.nip LIKE ?";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$sql = "SELECT g.*, u.username FROM guru g JOIN users u ON g.id_user = u.id $where ORDER BY g.nama_guru ASC";
$stmt = mysqli_prepare($conn, $sql);

if ($search != '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Data Guru</h3>
    <a href="tambah_guru.php" class="btn btn-primary shadow-sm"><i class="bi bi-person-plus me-1"></i> Tambah Guru</a>
</div>

<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <!-- Form Pencarian -->
        <form method="GET" action="data_guru.php" class="mb-4">
            <div class="input-group shadow-sm" style="max-width: 450px;">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Cari NIP atau Nama Guru..." value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary px-4" type="submit">Cari</button>
                <?php if($search != ''): ?>
                    <a href="data_guru.php" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="50" class="text-center">No</th>
                        <th width="70" class="text-center">Foto</th>
                        <th>NIP / Nama</th>
                        <th>Kontak</th>
                        <th>Akun (Username)</th>
                        <th width="150" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php $no = 1; while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="text-center">
                                    <?php if($row['foto'] && file_exists("uploads/profil/".$row['foto'])): ?>
                                        <img src="uploads/profil/<?= $row['foto'] ?>" alt="Foto" class="rounded-circle object-fit-cover shadow-sm" width="45" height="45">
                                    <?php else: ?>
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center mx-auto shadow-sm" style="width: 45px; height: 45px; font-size: 1.2rem;">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="d-block fw-bold text-dark"><?= htmlspecialchars($row['nama_guru']) ?></span>
                                    <small class="text-muted">NIP. <?= htmlspecialchars($row['nip']) ?></small>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem;">
                                        <div class="text-muted mb-1"><i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($row['email']) ?></div>
                                        <div class="text-muted"><i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($row['no_hp']) ?></div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($row['username']) ?></span></td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <a href="detail_guru.php?id=<?= $row['id_guru'] ?>" class="btn btn-sm btn-info text-white" title="Detail"><i class="bi bi-eye"></i></a>
                                        <a href="edit_guru.php?id=<?= $row['id_guru'] ?>" class="btn btn-sm btn-warning text-dark" title="Edit"><i class="bi bi-pencil-square"></i></a>
                                        <a href="hapus_guru.php?id=<?= $row['id_guru'] ?>" class="btn btn-sm btn-danger" title="Hapus" onclick="return confirm('Peringatan: Menghapus data guru juga akan menghapus akun login dan riwayat absensinya. Anda yakin?');"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2">Data guru tidak ditemukan.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
