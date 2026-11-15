<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nip = trim($_POST['nip']);
    $nama = trim($_POST['nama_guru']);
    $jk = $_POST['jenis_kelamin'];
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $error = "";
    
    // Validasi input dasar
    if (empty($nip) || empty($nama) || empty($username) || empty($password)) {
        $error = "NIP, Nama, Username, dan Password wajib diisi!";
    }
    
    // Cek Username Unik
    if (!$error) {
        $stmt_cek = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt_cek, "s", $username);
        mysqli_stmt_execute($stmt_cek);
        if (mysqli_stmt_get_result($stmt_cek)->num_rows > 0) {
            $error = "Username sudah digunakan oleh pengguna lain!";
        }
    }
    
    // Cek NIP Unik
    if (!$error) {
        $stmt_cek2 = mysqli_prepare($conn, "SELECT id_guru FROM guru WHERE nip = ?");
        mysqli_stmt_bind_param($stmt_cek2, "s", $nip);
        mysqli_stmt_execute($stmt_cek2);
        if (mysqli_stmt_get_result($stmt_cek2)->num_rows > 0) {
            $error = "NIP sudah terdaftar di sistem!";
        }
    }
    
    if (!$error) {
        mysqli_begin_transaction($conn);
        try {
            // Proses Upload Foto
            $foto = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png'];
                $filename = $_FILES['foto']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $new_filename = time() . '_' . rand(1000,9999) . '.' . $ext;
                    $upload_dir = 'uploads/profil/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $new_filename)) {
                        $foto = $new_filename;
                    }
                } else {
                    throw new Exception("Format foto tidak didukung. Harap gunakan JPG, JPEG, atau PNG.");
                }
            }

            // 1. Insert ke tabel users
            $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
            $role = 'guru';
            $stmt1 = mysqli_prepare($conn, "INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt1, "ssss", $nama, $username, $hashed_pass, $role);
            mysqli_stmt_execute($stmt1);
            $id_user = mysqli_insert_id($conn);
            
            // 2. Insert ke tabel guru
            $stmt2 = mysqli_prepare($conn, "INSERT INTO guru (id_user, nip, nama_guru, jenis_kelamin, email, no_hp, foto) VALUES (?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, "issssss", $id_user, $nip, $nama, $jk, $email, $no_hp, $foto);
            mysqli_stmt_execute($stmt2);
            
            mysqli_commit($conn);
            $_SESSION['success'] = "Data guru berhasil ditambahkan beserta akun loginnya.";
            header("Location: data_guru.php");
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Tambah Data Guru</h3>
    <a href="data_guru.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali</a>
</div>

<?php if(!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                <form action="" method="POST" enctype="multipart/form-data">
                    <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">Informasi Pribadi</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">NIP <span class="text-danger">*</span></label>
                            <input type="text" name="nip" class="form-control" required value="<?= isset($_POST['nip']) ? htmlspecialchars($_POST['nip']) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Nama Lengkap & Gelar <span class="text-danger">*</span></label>
                            <input type="text" name="nama_guru" class="form-control" required value="<?= isset($_POST['nama_guru']) ? htmlspecialchars($_POST['nama_guru']) : '' ?>">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select name="jenis_kelamin" class="form-select" required>
                                <option value="L" <?= (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'L') ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] == 'P') ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Nomor HP</label>
                            <input type="text" name="no_hp" class="form-control" value="<?= isset($_POST['no_hp']) ? htmlspecialchars($_POST['no_hp']) : '' ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-medium">Foto Wajah (Maks. 2MB)</label>
                        <input type="file" name="foto" class="form-control" accept="image/png, image/jpeg, image/jpg">
                        <div class="form-text">Digunakan untuk profil (dan dipersiapkan untuk dataset AI).</div>
                    </div>

                    <h5 class="fw-bold text-primary mb-4 border-bottom pb-2">Informasi Akun Login</h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary px-5 py-2 fw-medium shadow-sm">Simpan Data Guru</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 d-none d-xl-block">
        <div class="card border-0 shadow-sm rounded-4 bg-primary text-white">
            <div class="card-body p-4">
                <h5 class="fw-bold"><i class="bi bi-info-circle me-2"></i> Panduan Pengisian</h5>
                <hr class="border-white opacity-25">
                <ul class="mb-0 ps-3">
                    <li class="mb-2">Gunakan <strong>NIP</strong> yang valid karena ini merupakan identitas unik.</li>
                    <li class="mb-2">Pengisian akun otomatis akan membuatkan sesi login bagi guru.</li>
                    <li class="mb-2">Pastikan foto terlihat jelas, disarankan berlatar belakang polos untuk mempermudah tahap integrasi AI ke depannya.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
