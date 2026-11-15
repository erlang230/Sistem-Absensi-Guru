<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

// Proses CRUD Kelas
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah'])) {
        $nama = trim($_POST['nama_kelas']);
        $tingkat = $_POST['tingkat'];
        $jurusan = $_POST['jurusan'];
        $stmt = mysqli_prepare($conn, "INSERT INTO kelas (nama_kelas, tingkat, jurusan) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $nama, $tingkat, $jurusan);
        if (mysqli_stmt_execute($stmt)) $_SESSION['success'] = "Kelas berhasil ditambahkan.";
        else $_SESSION['error'] = "Gagal menambah kelas.";
        header("Location: data_kelas.php"); exit;
    } elseif (isset($_POST['edit'])) {
        $id = $_POST['id_kelas'];
        $nama = trim($_POST['nama_kelas']);
        $tingkat = $_POST['tingkat'];
        $jurusan = $_POST['jurusan'];
        $stmt = mysqli_prepare($conn, "UPDATE kelas SET nama_kelas=?, tingkat=?, jurusan=? WHERE id_kelas=?");
        mysqli_stmt_bind_param($stmt, "sssi", $nama, $tingkat, $jurusan, $id);
        if (mysqli_stmt_execute($stmt)) $_SESSION['success'] = "Kelas berhasil diperbarui.";
        else $_SESSION['error'] = "Gagal memperbarui kelas.";
        header("Location: data_kelas.php"); exit;
    } elseif (isset($_POST['hapus'])) {
        $id = $_POST['id_kelas'];
        $stmt = mysqli_prepare($conn, "DELETE FROM kelas WHERE id_kelas=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) $_SESSION['success'] = "Kelas berhasil dihapus.";
        else $_SESSION['error'] = "Gagal menghapus kelas.";
        header("Location: data_kelas.php"); exit;
    }
}

$sql = "SELECT * FROM kelas ORDER BY tingkat ASC, nama_kelas ASC";
$result = mysqli_query($conn, $sql);
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Data Kelas</h3>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah"><i class="bi bi-plus-circle me-1"></i> Tambah Kelas</button>
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
                        <th>Nama Kelas</th>
                        <th>Tingkat</th>
                        <th>Jurusan</th>
                        <th width="140" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <?php 
                        $no = 1; 
                        $daftar_kelas = [];
                        while($row = mysqli_fetch_assoc($result)): 
                            $daftar_kelas[] = $row;
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td>
                                <button type="button" 
                                        class="btn btn-link text-decoration-none fw-bold text-primary p-0 d-inline-flex align-items-center gap-1 border-0 bg-transparent btn-view-jadwal" 
                                        data-id="<?= $row['id_kelas'] ?>" 
                                        data-nama="<?= htmlspecialchars($row['nama_kelas']) ?>"
                                        data-tingkat="<?= htmlspecialchars($row['tingkat']) ?>"
                                        data-jurusan="<?= htmlspecialchars($row['jurusan']) ?>"
                                        title="Klik untuk melihat seluruh jadwal mengajar kelas <?= htmlspecialchars($row['nama_kelas']) ?>">
                                    <i class="bi bi-easel me-1"></i>
                                    <span><?= htmlspecialchars($row['nama_kelas']) ?></span>
                                    <i class="bi bi-calendar3 small text-muted ms-1"></i>
                                </button>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['tingkat']) ?></span></td>
                            <td><?= htmlspecialchars($row['jurusan']) ?></td>
                            <td class="text-center">
                                <button type="button" 
                                        class="btn btn-sm btn-info text-white btn-view-jadwal" 
                                        data-id="<?= $row['id_kelas'] ?>" 
                                        data-nama="<?= htmlspecialchars($row['nama_kelas']) ?>"
                                        data-tingkat="<?= htmlspecialchars($row['tingkat']) ?>"
                                        data-jurusan="<?= htmlspecialchars($row['jurusan']) ?>"
                                        title="Lihat Jadwal Mengajar">
                                    <i class="bi bi-calendar-week"></i>
                                </button>
                                <button class="btn btn-sm btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_kelas'] ?>" title="Edit Kelas"><i class="bi bi-pencil-square"></i></button>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_kelas'] ?>" title="Hapus Kelas"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data kelas.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL DETAIL JADWAL MENGAJAR KELAS                           -->
<!-- ============================================================ -->
<div class="modal fade" id="modalJadwalKelas" tabindex="-1" aria-labelledby="modalJadwalKelasLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar-week fs-4"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalJadwalKelasLabel">
                            Jadwal Mengajar — <span id="lblNamaKelas">-</span>
                        </h5>
                        <small class="opacity-75" id="lblInfoKelas">Memuat informasi kelas...</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4" id="modalJadwalBody">
                <div class="text-center py-5 text-muted" id="modalJadwalLoading">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                    <p class="mb-0 fw-medium">Sedang memuat jadwal mengajar...</p>
                </div>
                <div class="table-responsive d-none" id="modalJadwalTableContainer">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="110">Hari</th>
                                <th width="160">Waktu</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru Pengajar</th>
                            </tr>
                        </thead>
                        <tbody id="modalJadwalTbody">
                            <!-- Baris jadwal diinjeksi via JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="text-center py-5 text-muted d-none" id="modalJadwalEmpty">
                    <i class="bi bi-calendar-x fs-1 d-block mb-3 text-secondary"></i>
                    <h6 class="fw-bold mb-1">Belum Ada Jadwal</h6>
                    <p class="mb-0 fw-medium text-muted">Belum ada jadwal mengajar untuk kelas ini.</p>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light py-2 px-4 d-flex justify-content-between">
                <a href="#" id="btnDetailLengkap" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka Halaman Lengkap
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL EDIT & HAPUS KELAS                                     -->
<!-- ============================================================ -->
<?php if(!empty($daftar_kelas)): ?>
    <?php foreach($daftar_kelas as $row): ?>
        <!-- Modal Edit -->
        <div class="modal fade" id="modalEdit<?= $row['id_kelas'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow rounded-4">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Edit Kelas</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id_kelas" value="<?= $row['id_kelas'] ?>">
                            <div class="mb-3">
                                <label class="form-label fw-medium">Nama Kelas <span class="text-danger">*</span></label>
                                <input type="text" name="nama_kelas" class="form-control" value="<?= htmlspecialchars($row['nama_kelas']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Tingkat <span class="text-danger">*</span></label>
                                <select name="tingkat" class="form-select" required>
                                    <option value="X" <?= $row['tingkat']=='X'?'selected':'' ?>>X (Sepuluh)</option>
                                    <option value="XI" <?= $row['tingkat']=='XI'?'selected':'' ?>>XI (Sebelas)</option>
                                    <option value="XII" <?= $row['tingkat']=='XII'?'selected':'' ?>>XII (Dua Belas)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-medium">Jurusan <span class="text-danger">*</span></label>
                                <input type="text" name="jurusan" class="form-control" value="<?= htmlspecialchars($row['jurusan']) ?>" required>
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
        <div class="modal fade" id="modalHapus<?= $row['id_kelas'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content border-0 shadow rounded-4">
                    <form method="POST">
                        <div class="modal-body text-center p-4">
                            <i class="bi bi-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 fw-bold">Hapus Kelas?</h5>
                            <p class="text-muted">Anda yakin ingin menghapus kelas <strong><?= htmlspecialchars($row['nama_kelas']) ?></strong>? Data jadwal yang terhubung ke kelas ini juga akan terhapus.</p>
                            <input type="hidden" name="id_kelas" value="<?= $row['id_kelas'] ?>">
                            <div class="mt-4">
                                <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="hapus" class="btn btn-danger">Ya, Hapus</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Modal Tambah Kelas -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Tambah Kelas Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kelas" class="form-control" required placeholder="Contoh: X MIPA 1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Tingkat <span class="text-danger">*</span></label>
                        <select name="tingkat" class="form-select" required>
                            <option value="">Pilih Tingkat</option>
                            <option value="X">X (Sepuluh)</option>
                            <option value="XI">XI (Sebelas)</option>
                            <option value="XII">XII (Dua Belas)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Jurusan <span class="text-danger">*</span></label>
                        <input type="text" name="jurusan" class="form-control" required placeholder="Contoh: MIPA / IPS">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah" class="btn btn-primary">Simpan Kelas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script Dynamic Modal Loader -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const modalEl = document.getElementById('modalJadwalKelas');
    const modalBs = new bootstrap.Modal(modalEl);
    
    const lblNama = document.getElementById('lblNamaKelas');
    const lblInfo = document.getElementById('lblInfoKelas');
    const elLoading = document.getElementById('modalJadwalLoading');
    const elContainer = document.getElementById('modalJadwalTableContainer');
    const elTbody = document.getElementById('modalJadwalTbody');
    const elEmpty = document.getElementById('modalJadwalEmpty');
    const btnLengkap = document.getElementById('btnDetailLengkap');

    const badgeColors = {
        'Senin': 'bg-primary',
        'Selasa': 'bg-info text-dark',
        'Rabu': 'bg-success',
        'Kamis': 'bg-warning text-dark',
        'Jumat': 'bg-danger',
        'Sabtu': 'bg-secondary',
        'Minggu': 'bg-dark'
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    document.querySelectorAll('.btn-view-jadwal').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const tingkat = this.getAttribute('data-tingkat');
            const jurusan = this.getAttribute('data-jurusan');

            lblNama.textContent = nama;
            lblInfo.textContent = 'Tingkat: ' + tingkat + ' | Jurusan: ' + jurusan;
            btnLengkap.href = 'detail_kelas.php?id=' + encodeURIComponent(id);

            // Reset state
            elLoading.classList.remove('d-none');
            elContainer.classList.add('d-none');
            elEmpty.classList.add('d-none');
            elTbody.innerHTML = '';

            modalBs.show();

            // Fetch data
            fetch('api_jadwal_kelas.php?id=' + encodeURIComponent(id))
                .then(res => res.json())
                .then(data => {
                    elLoading.classList.add('d-none');
                    if (data.status === 'success' && data.jadwal && data.jadwal.length > 0) {
                        let html = '';
                        data.jadwal.forEach(item => {
                            const badge = badgeColors[item.hari] || 'bg-primary';
                            html += `
                                <tr>
                                    <td><span class="badge ${badge} px-2 py-1">${escapeHtml(item.hari)}</span></td>
                                    <td class="fw-medium text-dark"><i class="bi bi-clock me-1 text-muted"></i> ${escapeHtml(item.jam_mulai)} – ${escapeHtml(item.jam_selesai)}</td>
                                    <td><span class="fw-semibold text-dark">${escapeHtml(item.nama_mapel)}</span></td>
                                    <td><i class="bi bi-person-fill text-muted me-1"></i><span class="fw-bold text-dark">${escapeHtml(item.nama_guru)}</span></td>
                                </tr>
                            `;
                        });
                        elTbody.innerHTML = html;
                        elContainer.classList.remove('d-none');
                    } else {
                        elEmpty.classList.remove('d-none');
                    }
                })
                .catch(err => {
                    elLoading.classList.add('d-none');
                    elEmpty.classList.remove('d-none');
                });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
