<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: data_guru.php");
    exit;
}

$id_guru = intval($_GET['id']);

// Ambil data guru saat ini
$stmt_get = mysqli_prepare($conn, "SELECT g.*, u.username FROM guru g JOIN users u ON g.id_user = u.id WHERE g.id_guru = ?");
mysqli_stmt_bind_param($stmt_get, "i", $id_guru);
mysqli_stmt_execute($stmt_get);
$result = mysqli_stmt_get_result($stmt_get);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Data guru tidak ditemukan.";
    header("Location: data_guru.php");
    exit;
}

$guru = mysqli_fetch_assoc($result);
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nip = trim($_POST['nip']);
    $nama = trim($_POST['nama_guru']);
    $jk = $_POST['jenis_kelamin'];
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $username = trim($_POST['username']);
    $password = $_POST['password']; // opsional jika diisi berarti ganti password
    
    // Cek Username Unik (Kecuali milik dia sendiri)
    $stmt_cek = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? AND id != ?");
    mysqli_stmt_bind_param($stmt_cek, "si", $username, $guru['id_user']);
    mysqli_stmt_execute($stmt_cek);
    if (mysqli_stmt_get_result($stmt_cek)->num_rows > 0) {
        $error = "Username sudah digunakan oleh pengguna lain!";
    }
    
    // Cek NIP Unik (Kecuali miliknya)
    if (!$error) {
        $stmt_cek2 = mysqli_prepare($conn, "SELECT id_guru FROM guru WHERE nip = ? AND id_guru != ?");
        mysqli_stmt_bind_param($stmt_cek2, "si", $nip, $id_guru);
        mysqli_stmt_execute($stmt_cek2);
        if (mysqli_stmt_get_result($stmt_cek2)->num_rows > 0) {
            $error = "NIP sudah terdaftar pada guru lain!";
        }
    }
    
    if (!$error) {
        mysqli_begin_transaction($conn);
        try {
            $foto = $guru['foto']; // Default foto lama
            
            // Proses Upload Foto Baru
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png'];
                $filename = $_FILES['foto']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $new_filename = time() . '_' . rand(1000,9999) . '.' . $ext;
                    $upload_dir = 'uploads/profil/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $new_filename)) {
                        // Hapus foto lama jika ada
                        if ($foto && file_exists($upload_dir . $foto)) {
                            unlink($upload_dir . $foto);
                        }
                        $foto = $new_filename;
                    }
                } else {
                    throw new Exception("Format foto tidak didukung.");
                }
            }

            // Update users
            if (!empty($password)) {
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt1 = mysqli_prepare($conn, "UPDATE users SET nama = ?, username = ?, password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt1, "sssi", $nama, $username, $hashed_pass, $guru['id_user']);
            } else {
                $stmt1 = mysqli_prepare($conn, "UPDATE users SET nama = ?, username = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt1, "ssi", $nama, $username, $guru['id_user']);
            }
            mysqli_stmt_execute($stmt1);
            
            // Update guru
            $stmt2 = mysqli_prepare($conn, "UPDATE guru SET nip = ?, nama_guru = ?, jenis_kelamin = ?, email = ?, no_hp = ?, foto = ? WHERE id_guru = ?");
            mysqli_stmt_bind_param($stmt2, "ssssssi", $nip, $nama, $jk, $email, $no_hp, $foto, $id_guru);
            mysqli_stmt_execute($stmt2);
            
            mysqli_commit($conn);
            $_SESSION['success'] = "Data guru berhasil diperbarui.";
            header("Location: data_guru.php");
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Edit Data Guru</h3>
    <a href="data_guru.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
</div>

<?php if(!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 p-md-5">
        <form action="" method="POST" enctype="multipart/form-data">
            <h5 class="fw-bold text-warning mb-4 border-bottom pb-2">Informasi Pribadi</h5>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium">NIP <span class="text-danger">*</span></label>
                    <input type="text" name="nip" class="form-control" required value="<?= htmlspecialchars($guru['nip']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Nama Lengkap & Gelar <span class="text-danger">*</span></label>
                    <input type="text" name="nama_guru" class="form-control" required value="<?= htmlspecialchars($guru['nama_guru']) ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Jenis Kelamin <span class="text-danger">*</span></label>
                    <select name="jenis_kelamin" class="form-select" required>
                        <option value="L" <?= $guru['jenis_kelamin'] == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= $guru['jenis_kelamin'] == 'P' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Nomor HP</label>
                    <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($guru['no_hp']) ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-medium">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($guru['email']) ?>">
            </div>

            <div class="mb-5 row align-items-center">
                <div class="col-md-2">
                    <?php if($guru['foto'] && file_exists("uploads/profil/".$guru['foto'])): ?>
                        <img src="uploads/profil/<?= $guru['foto'] ?>" alt="Foto" class="img-thumbnail rounded-3 shadow-sm" style="max-width: 100px;">
                    <?php else: ?>
                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 d-flex justify-content-center align-items-center mx-auto" style="width: 100px; height: 100px; font-size: 2rem;">
                            <i class="bi bi-person"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <label class="form-label fw-medium">Ubah Foto Wajah</label>
                    <input type="file" name="foto" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    <div class="form-text">Biarkan kosong jika tidak ingin mengubah foto.</div>
                </div>
            </div>

            <h5 class="fw-bold text-warning mb-4 border-bottom pb-2">Informasi Akun Login</h5>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-medium">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($guru['username']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium">Password Baru</label>
                    <input type="password" name="password" class="form-control" placeholder="Isi untuk mengubah password">
                    <div class="form-text">Kosongkan jika tidak ingin mengubah password saat ini.</div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-warning px-5 py-2 fw-medium shadow-sm">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
