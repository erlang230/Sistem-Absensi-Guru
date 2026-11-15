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

$sql = "SELECT g.*, u.username, u.created_at as user_created, gg.email_google, gg.status_tautan as status_google, gg.verified_at as google_verified 
        FROM guru g 
        JOIN users u ON g.id_user = u.id 
        LEFT JOIN guru_google gg ON g.id_guru = gg.id_guru 
        WHERE g.id_guru = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_guru);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Data guru tidak ditemukan.";
    header("Location: data_guru.php");
    exit;
}

$guru = mysqli_fetch_assoc($result);

// Hitung total absen sebagai tambahan visual
$stmt_absen = mysqli_prepare($conn, "SELECT count(*) as total_absen FROM absensi WHERE id_guru = ?");
mysqli_stmt_bind_param($stmt_absen, "i", $id_guru);
mysqli_stmt_execute($stmt_absen);
$absen_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_absen));
$total_absen = $absen_data['total_absen'];
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0">Detail Profil Guru</h3>
    <div>
        <a href="edit_guru.php?id=<?= $id_guru ?>" class="btn btn-warning shadow-sm me-2"><i class="bi bi-pencil-square"></i> Edit</a>
        <a href="data_guru.php" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<div class="row">
    <!-- Profil Card -->
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm rounded-4 text-center">
            <div class="card-body p-4">
                <div class="mb-4 mt-3">
                    <?php if($guru['foto'] && file_exists("uploads/profil/".$guru['foto'])): ?>
                        <img src="uploads/profil/<?= $guru['foto'] ?>" alt="Foto" class="rounded-circle object-fit-cover shadow" width="150" height="150" style="border: 4px solid #fff;">
                    <?php else: ?>
                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center mx-auto shadow" style="width: 150px; height: 150px; font-size: 4rem; border: 4px solid #fff;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($guru['nama_guru']) ?></h4>
                <p class="text-muted mb-3">NIP. <?= htmlspecialchars($guru['nip']) ?></p>
                
                <span class="badge bg-primary px-3 py-2 rounded-pill"><i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($guru['username']) ?></span>
                
                <hr class="my-4">
                
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <h4 class="fw-bold text-dark mb-0"><?= $total_absen ?></h4>
                        <span class="text-muted small">Total Absensi</span>
                    </div>
                    <div class="col-6">
                        <h4 class="fw-bold text-success mb-0">Aktif</h4>
                        <span class="text-muted small">Status Akun</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Informasi Detail -->
    <div class="col-md-8 mb-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 p-md-5">
                <h5 class="fw-bold text-primary mb-4 border-bottom pb-3"><i class="bi bi-info-circle me-2"></i> Informasi Lengkap</h5>
                
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Nama Lengkap</div>
                    <div class="col-sm-8 fw-medium text-dark"><?= htmlspecialchars($guru['nama_guru']) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Nomor Induk Pegawai (NIP)</div>
                    <div class="col-sm-8 fw-medium text-dark"><?= htmlspecialchars($guru['nip']) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Jenis Kelamin</div>
                    <div class="col-sm-8 fw-medium text-dark">
                        <?= $guru['jenis_kelamin'] == 'L' ? '<i class="bi bi-gender-male text-primary me-1"></i> Laki-laki' : '<i class="bi bi-gender-female text-danger me-1"></i> Perempuan' ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-4 text-muted">Email</div>
                    <div class="col-sm-8 fw-medium text-dark"><?= $guru['email'] ? htmlspecialchars($guru['email']) : '-' ?></div>
                </div>
                <div class="row mb-4">
                    <div class="col-sm-4 text-muted">Nomor HP / WhatsApp</div>
                    <div class="col-sm-8 fw-medium text-dark"><?= $guru['no_hp'] ? htmlspecialchars($guru['no_hp']) : '-' ?></div>
                </div>

                <h5 class="fw-bold text-primary mb-4 border-bottom pb-3 mt-5"><i class="bi bi-robot me-2"></i> Kesiapan AI (Face Data)</h5>
                
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
                    <i class="bi bi-exclamation-circle-fill fs-3 text-info me-3"></i>
                    <div>
                        <strong class="d-block mb-1">Status: <?= $guru['face_data'] ? 'Telah Terdaftar' : 'Belum Terdaftar' ?></strong>
                        <span class="small">Data wajah untuk fitur Face Recognition belum diekstrak. Fitur ini akan aktif pada pembaruan tahap AI selanjutnya.</span>
                    </div>
                </div>
                
                <h5 class="fw-bold text-primary mb-3 border-bottom pb-3 mt-4"><i class="bi bi-google me-2"></i> Akun Google (Email Pengingat)</h5>
                
                <?php if ($guru['status_google'] === 'terhubung'): ?>
                    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-0">
                        <i class="bi bi-patch-check-fill fs-3 text-success me-3"></i>
                        <div>
                            <strong class="d-block text-success mb-1">Status: Terhubung & Terverifikasi</strong>
                            <span class="small font-monospace text-dark"><?= htmlspecialchars($guru['email_google']) ?></span>
                            <span class="small d-block text-muted">Diverifikasi pada: <?= date('d M Y, H:i', strtotime($guru['google_verified'])) ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary border-0 shadow-sm d-flex align-items-center mb-0">
                        <i class="bi bi-x-circle-fill fs-3 text-secondary me-3"></i>
                        <div>
                            <strong class="d-block text-secondary mb-1">Status: Belum Ditautkan</strong>
                            <span class="small text-muted">Guru belum menautkan dan memverifikasi akun Google (Gmail) melalui halaman profil guru.</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="text-muted small mt-4 text-end">
                    Data ditambahkan pada: <?= date('d M Y, H:i', strtotime($guru['created_at'])) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
