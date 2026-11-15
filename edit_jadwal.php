<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: jadwal_mengajar.php"); exit;
}
$id_jadwal = intval($_GET['id']);

$stmt_get = mysqli_prepare($conn, "SELECT * FROM jadwal WHERE id_jadwal = ?");
mysqli_stmt_bind_param($stmt_get, "i", $id_jadwal);
mysqli_stmt_execute($stmt_get);
$result_get = mysqli_stmt_get_result($stmt_get);
if(mysqli_num_rows($result_get) == 0) {
    header("Location: jadwal_mengajar.php"); exit;
}
$jadwal = mysqli_fetch_assoc($result_get);

$gurus = mysqli_query($conn, "SELECT id_guru, nama_guru FROM guru ORDER BY nama_guru ASC");
$mapels = mysqli_query($conn, "SELECT id_mapel, nama_mapel FROM mata_pelajaran ORDER BY nama_mapel ASC");
$kelas = mysqli_query($conn, "SELECT id_kelas, nama_kelas FROM kelas ORDER BY tingkat ASC, nama_kelas ASC");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_guru = $_POST['id_guru'];
    $id_mapel = $_POST['id_mapel'];
    $id_kelas = $_POST['id_kelas'];
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    if (strtotime($jam_mulai) >= strtotime($jam_selesai)) {
        $_SESSION['error'] = "Jam mulai harus lebih awal dari jam selesai.";
    } else {
        $stmt_cek = mysqli_prepare($conn, "SELECT id_jadwal, id_guru, id_kelas FROM jadwal WHERE hari = ? AND jam_mulai < ? AND jam_selesai > ? AND (id_guru = ? OR id_kelas = ?) AND id_jadwal != ?");
        mysqli_stmt_bind_param($stmt_cek, "sssiii", $hari, $jam_selesai, $jam_mulai, $id_guru, $id_kelas, $id_jadwal);
        mysqli_stmt_execute($stmt_cek);
        $result_cek = mysqli_stmt_get_result($stmt_cek);
        
        if (mysqli_num_rows($result_cek) > 0) {
            $conflict = mysqli_fetch_assoc($result_cek);
            if ($conflict['id_guru'] == $id_guru) $_SESSION['error'] = "Konflik: Guru tersebut sudah memiliki jadwal lain!";
            else $_SESSION['error'] = "Konflik: Kelas tersebut sudah digunakan!";
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE jadwal SET id_guru=?, id_mapel=?, id_kelas=?, hari=?, jam_mulai=?, jam_selesai=? WHERE id_jadwal=?");
            mysqli_stmt_bind_param($stmt, "iiisssi", $id_guru, $id_mapel, $id_kelas, $hari, $jam_mulai, $jam_selesai, $id_jadwal);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Jadwal berhasil diperbarui!";
                header("Location: jadwal_mengajar.php"); exit;
            } else {
                $_SESSION['error'] = "Gagal memperbarui jadwal.";
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Edit Jadwal</h3>
    <a href="jadwal_mengajar.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
</div>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4" style="max-width: 800px;">
    <div class="card-body p-4 p-md-5">
        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-medium">Guru Pengajar <span class="text-danger">*</span></label>
                    <select name="id_guru" class="form-select" required>
                        <?php while($g = mysqli_fetch_assoc($gurus)): ?>
                            <option value="<?= $g['id_guru'] ?>" <?= $g['id_guru']==$jadwal['id_guru']?'selected':'' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select name="id_mapel" class="form-select" required>
                        <?php while($m = mysqli_fetch_assoc($mapels)): ?>
                            <option value="<?= $m['id_mapel'] ?>" <?= $m['id_mapel']==$jadwal['id_mapel']?'selected':'' ?>><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium">Kelas <span class="text-danger">*</span></label>
                    <select name="id_kelas" class="form-select" required>
                        <?php while($k = mysqli_fetch_assoc($kelas)): ?>
                            <option value="<?= $k['id_kelas'] ?>" <?= $k['id_kelas']==$jadwal['id_kelas']?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Hari <span class="text-danger">*</span></label>
                    <select name="hari" class="form-select" required>
                        <?php $haris = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu']; foreach($haris as $h): ?>
                            <option value="<?= $h ?>" <?= $h==$jadwal['hari']?'selected':'' ?>><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Jam Mulai <span class="text-danger">*</span></label>
                    <input type="time" name="jam_mulai" class="form-control" value="<?= substr($jadwal['jam_mulai'],0,5) ?>" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Jam Selesai <span class="text-danger">*</span></label>
                    <input type="time" name="jam_selesai" class="form-control" value="<?= substr($jadwal['jam_selesai'],0,5) ?>" required>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-warning px-5 py-2 fw-medium shadow-sm">Update Jadwal</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
