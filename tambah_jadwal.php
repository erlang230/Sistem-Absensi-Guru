<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

// Ambil dropdown data
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
    
    // Validasi Waktu
    if (strtotime($jam_mulai) >= strtotime($jam_selesai)) {
        $_SESSION['error'] = "Jam mulai harus lebih awal dari jam selesai.";
    } else {
        // Cek Konflik Jadwal (Guru bertabrakan atau Kelas bertabrakan)
        $stmt_cek = mysqli_prepare($conn, "
            SELECT id_jadwal, id_guru, id_kelas FROM jadwal 
            WHERE hari = ? 
            AND jam_mulai < ? 
            AND jam_selesai > ?
            AND (id_guru = ? OR id_kelas = ?)
        ");
        mysqli_stmt_bind_param($stmt_cek, "sssii", $hari, $jam_selesai, $jam_mulai, $id_guru, $id_kelas);
        mysqli_stmt_execute($stmt_cek);
        $result_cek = mysqli_stmt_get_result($stmt_cek);
        
        if (mysqli_num_rows($result_cek) > 0) {
            $conflict = mysqli_fetch_assoc($result_cek);
            if ($conflict['id_guru'] == $id_guru) {
                $_SESSION['error'] = "Konflik: Guru tersebut sudah memiliki jadwal lain pada waktu tersebut!";
            } else {
                $_SESSION['error'] = "Konflik: Kelas tersebut sudah digunakan untuk mata pelajaran lain pada waktu tersebut!";
            }
        } else {
            // Insert jika aman
            $stmt = mysqli_prepare($conn, "INSERT INTO jadwal (id_guru, id_mapel, id_kelas, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiisss", $id_guru, $id_mapel, $id_kelas, $hari, $jam_mulai, $jam_selesai);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "Jadwal berhasil dibuat!";
                header("Location: jadwal_mengajar.php");
                exit;
            } else {
                $_SESSION['error'] = "Gagal membuat jadwal.";
            }
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Tambah Jadwal Mengajar</h3>
    <a href="jadwal_mengajar.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
</div>

<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4" style="max-width: 800px;">
    <div class="card-body p-4 p-md-5">
        <form method="POST">
            <div class="mb-4 border-bottom pb-3">
                <h5 class="fw-bold text-primary"><i class="bi bi-calendar-plus me-2"></i> Form Jadwal Baru</h5>
                <p class="text-muted small">Sistem akan secara otomatis memvalidasi jam dan hari untuk mencegah konflik jadwal guru maupun ruang kelas.</p>
            </div>

            <div class="row mb-3">
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-medium">Guru Pengajar <span class="text-danger">*</span></label>
                    <select name="id_guru" class="form-select" required>
                        <option value="">-- Pilih Guru --</option>
                        <?php while($g = mysqli_fetch_assoc($gurus)): ?>
                            <option value="<?= $g['id_guru'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select name="id_mapel" class="form-select" required>
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <?php while($m = mysqli_fetch_assoc($mapels)): ?>
                            <option value="<?= $m['id_mapel'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium">Kelas <span class="text-danger">*</span></label>
                    <select name="id_kelas" class="form-select" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php while($k = mysqli_fetch_assoc($kelas)): ?>
                            <option value="<?= $k['id_kelas'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Hari <span class="text-danger">*</span></label>
                    <select name="hari" class="form-select" required>
                        <option value="">-- Pilih Hari --</option>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                        <option value="Minggu">Minggu</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Jam Mulai <span class="text-danger">*</span></label>
                    <input type="time" name="jam_mulai" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-medium">Jam Selesai <span class="text-danger">*</span></label>
                    <input type="time" name="jam_selesai" class="form-control" required>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary px-5 py-2 fw-medium shadow-sm">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
