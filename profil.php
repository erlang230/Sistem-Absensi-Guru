<?php
/**
 * Halaman Profil Guru & Penautan Akun Google
 * Sistem Absensi Guru (SAG)
 */

require_once 'auth.php';
check_login();

require_once 'config/database.php';

$role = $_SESSION['role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);
$nama_user = $_SESSION['nama'] ?? '';

// Ambil data spesifik berdasarkan role
$guru_data = null;
$google_data = null;

if ($role === 'guru') {
    $stmt_g = mysqli_prepare($conn, "
        SELECT g.*, u.username, u.status as status_user
        FROM guru g
        JOIN users u ON g.id_user = u.id
        WHERE g.id_user = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt_g, "i", $user_id);
    mysqli_stmt_execute($stmt_g);
    $guru_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_g));
    mysqli_stmt_close($stmt_g);

    if ($guru_data) {
        $id_guru = (int)$guru_data['id_guru'];
        // Ambil data akun google
        $stmt_gg = mysqli_prepare($conn, "SELECT email_google, status_tautan, verified_at FROM guru_google WHERE id_guru = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_gg, "i", $id_guru);
        mysqli_stmt_execute($stmt_gg);
        $google_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_gg));
        mysqli_stmt_close($stmt_gg);
    }
} else {
    // Role admin atau kepala sekolah
    $stmt_u = mysqli_prepare($conn, "SELECT id, nama, username, role, status, created_at FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_u, "i", $user_id);
    mysqli_stmt_execute($stmt_u);
    $user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u));
    mysqli_stmt_close($stmt_u);
}

include 'includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h3 class="fw-bold mb-1"><i class="bi bi-person-badge text-primary me-2"></i>Profil Pengguna</h3>
        <p class="text-muted mb-0">Kelola informasi data diri, keamanan akun, dan tautan layanan Google.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill text-uppercase">
            <i class="bi bi-shield-check me-1"></i> Role: <?= htmlspecialchars($role) ?>
        </span>
    </div>
</div>

<?php if ($role === 'guru' && $guru_data): ?>
    <div class="row g-4">
        <!-- Kolom Kiri: Ringkasan & Info Guru -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-body text-center p-4" style="background: linear-gradient(180deg, #f0f7ff 0%, #ffffff 100%);">
                    <div class="position-relative d-inline-block mb-3">
                        <?php if (!empty($guru_data['foto'])): ?>
                            <img src="<?= htmlspecialchars($guru_data['foto']) ?>" alt="Foto Guru" class="rounded-circle shadow" width="110" height="110" style="object-fit: cover; border: 4px solid #fff;">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($guru_data['nama_guru']) ?>&background=0D6EFD&color=fff&size=110&bold=true" alt="Avatar" class="rounded-circle shadow" width="110" height="110" style="border: 4px solid #fff;">
                        <?php endif; ?>
                        
                        <?php if (!empty($guru_data['face_data'])): ?>
                            <span class="position-absolute bottom-0 end-0 badge bg-success rounded-pill p-2 shadow" title="Wajah Terdaftar">
                                <i class="bi bi-camera-fill"></i>
                            </span>
                        <?php endif; ?>
                    </div>

                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($guru_data['nama_guru']) ?></h5>
                    <p class="text-muted mb-2 font-monospace">NIP: <?= htmlspecialchars($guru_data['nip'] ?? '-') ?></p>

                    <div class="d-flex justify-content-center gap-2 mt-2">
                        <?php if (!empty($guru_data['face_data'])): ?>
                            <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                <i class="bi bi-check-circle-fill me-1"></i> Wajah Terdaftar
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Wajah Belum Terdaftar
                            </span>
                        <?php endif; ?>

                        <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill">
                            <i class="bi bi-gender-ambiguous me-1"></i> <?= $guru_data['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?>
                        </span>
                    </div>
                </div>

                <div class="card-body p-4 border-top">
                    <h6 class="fw-bold mb-3 text-secondary text-uppercase" style="font-size: 0.8rem; letter-spacing: 0.5px;">Informasi Akun</h6>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted"><i class="bi bi-person me-2"></i>Username</span>
                            <span class="fw-semibold font-monospace"><?= htmlspecialchars($guru_data['username']) ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted"><i class="bi bi-telephone me-2"></i>No. HP</span>
                            <span class="fw-semibold"><?= htmlspecialchars($guru_data['no_hp'] ?? '-') ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted"><i class="bi bi-envelope me-2"></i>Email Terdaftar</span>
                            <span class="fw-semibold text-truncate" style="max-width: 200px;"><?= htmlspecialchars($guru_data['email'] ?? '-') ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span class="text-muted"><i class="bi bi-activity me-2"></i>Status Akun</span>
                            <span class="badge bg-success">Aktif</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Pengaturan Akun Google & Verifikasi OTP -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <img src="https://www.gstatic.com/images/branding/product/1x/googleg_48dp.png" alt="Google Logo" width="28" height="28" class="me-2">
                        <h5 class="mb-0 fw-bold">Akun Google</h5>
                    </div>
                    <span id="googleStatusBadge" class="badge bg-secondary px-3 py-2 rounded-pill">Memeriksa...</span>
                </div>

                <div class="card-body p-4">
                    <!-- Alert Notifikasi Interaktif -->
                    <div id="alertFeedback" class="alert d-none" role="alert"></div>

                    <!-- KONDISI 1: SUDAH TERHUBUNG -->
                    <div id="sectionConnected" class="d-none">
                        <div class="p-4 rounded-4 border bg-light mb-3">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success text-white rounded-circle p-3 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-check-lg fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 text-success">Akun Google Terhubung & Terverifikasi</h6>
                                    <p class="text-muted mb-0 small">Email ini digunakan untuk menerima notifikasi resmi dan pengingat kehadiran SAG.</p>
                                </div>
                            </div>

                            <div class="bg-white p-3 rounded-3 border">
                                <div class="row align-items-center">
                                    <div class="col-sm-4 text-muted small fw-semibold">Alamat Gmail:</div>
                                    <div class="col-sm-8 fw-bold text-dark font-monospace" id="connectedEmailDisplay">-</div>
                                </div>
                                <hr class="my-2 text-muted opacity-25">
                                <div class="row align-items-center">
                                    <div class="col-sm-4 text-muted small fw-semibold">Waktu Verifikasi:</div>
                                    <div class="col-sm-8 text-secondary small" id="connectedDateDisplay">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2">
                            <span class="text-muted small"><i class="bi bi-shield-lock me-1 text-primary"></i>Hanya Anda yang dapat mengelola akun ini.</span>
                            <button type="button" class="btn btn-outline-danger btn-sm px-3 rounded-pill" id="btnUnlinkModal" data-bs-toggle="modal" data-bs-target="#modalConfirmUnlink">
                                <i class="bi bi-link-45deg me-1"></i> Putuskan Tautan
                            </button>
                        </div>
                    </div>

                    <!-- KONDISI 2: BELUM TERHUBUNG / FORM PENAUTAN -->
                    <div id="sectionNotConnected">
                        <p class="text-muted mb-3" style="font-size: 0.95rem; line-height: 1.6;">
                            Tautkan akun Google (Gmail) Anda untuk menerima pengingat absensi sekolah secara langsung ke inbox email Anda. Proses penautan memerlukan verifikasi kode <strong>OTP 6-digit</strong>.
                        </p>

                        <!-- Box Peringatan SMTP jika belum disetting admin -->
                        <div id="smtpWarningAlert" class="alert alert-warning d-none py-2 px-3 small rounded-3" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i><span id="smtpWarningText"></span>
                        </div>

                        <!-- Langkah 1: Input Email & Tombol Kirim OTP -->
                        <div class="card bg-light border-0 rounded-3 p-3 mb-3">
                            <label class="form-label fw-bold small text-secondary text-uppercase mb-2">1. Masukkan Alamat Gmail Anda</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-envelope-at text-muted"></i></span>
                                <input type="email" class="form-control border-start-0" id="inputGoogleEmail" placeholder="nama_anda@gmail.com" required autocomplete="off">
                                <button class="btn btn-primary px-4 fw-semibold" type="button" id="btnSendOtp">
                                    <span id="btnSendOtpSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                    <span id="btnSendOtpText"><i class="bi bi-send me-1"></i> Kirim Kode OTP</span>
                                </button>
                            </div>
                            <div class="form-text mt-1 text-muted small">
                                Pastikan alamat email aktif dan dapat menerima pesan dari server SAG.
                            </div>
                        </div>

                        <!-- Langkah 2: Input OTP & Verifikasi (Awalnya tersembunyi, muncul setelah OTP dikirim) -->
                        <div id="sectionOtpInput" class="card border border-primary-subtle bg-primary-subtle bg-opacity-10 rounded-3 p-3 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold small text-primary text-uppercase mb-0">2. Masukkan Kode OTP 6-Digit</label>
                                <span class="badge bg-danger rounded-pill" id="otpTimerBadge"><i class="bi bi-clock me-1"></i> Masa berlaku: 05:00</span>
                            </div>
                            
                            <p class="text-muted small mb-3">
                                Kode verifikasi telah dikirimkan ke <strong id="sentTargetEmailText" class="text-dark"></strong>. Periksa kotak masuk atau spam.
                            </p>

                            <div class="row g-2 align-items-center">
                                <div class="col-sm-7">
                                    <input type="text" class="form-control form-control-lg text-center font-monospace fw-bold fs-4 tracking-widest" id="inputOtpCode" maxlength="6" placeholder="• • • • • •" autocomplete="one-time-code">
                                </div>
                                <div class="col-sm-5">
                                    <button class="btn btn-success btn-lg w-100 fw-bold" type="button" id="btnVerifyOtp">
                                        <span id="btnVerifySpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                        <span id="btnVerifyText"><i class="bi bi-patch-check-fill me-1"></i> Verifikasi & Tautkan</span>
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-primary-subtle">
                                <span class="text-muted small">Belum menerima email kode?</span>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 fw-semibold" id="btnResendOtp" disabled>
                                    Kirim Ulang (<span id="cooldownSeconds">60</span>s)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light px-4 py-3 border-top small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i> Keamanan: Kode OTP hanya berlaku 5 menit dan bersifat rahasia. Jangan pernah membagikan kode ini kepada pihak lain.
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Putuskan Tautan -->
    <div class="modal fade" id="modalConfirmUnlink" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Putuskan Tautan Akun Google?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-muted mb-0">
                        Apakah Anda yakin ingin memutuskan tautan akun Google Anda? Setelah diputuskan, sistem SAG tidak akan dapat mengirimkan notifikasi pengingat absensi ke email tersebut hingga Anda menautkannya kembali.
                    </p>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger rounded-pill px-4 fw-semibold" id="btnConfirmUnlinkAction">
                        <span id="btnUnlinkSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                        Ya, Putuskan Tautan
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Tampilan Profil untuk Administrator / Kepala Sekolah -->
    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card shadow-sm border-0 rounded-4 p-4 text-center">
                <div class="mb-3">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($nama_user) ?>&background=0D6EFD&color=fff&size=100&bold=true" alt="Avatar" class="rounded-circle shadow" width="100" height="100">
                </div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($nama_user) ?></h4>
                <p class="text-muted font-monospace mb-3">@<?= htmlspecialchars($user_info['username'] ?? '') ?></p>
                
                <div class="d-inline-block mx-auto mb-4">
                    <span class="badge bg-primary px-3 py-2 rounded-pill text-uppercase fs-6">
                        <i class="bi bi-person-gear me-1"></i> <?= htmlspecialchars($role) ?>
                    </span>
                </div>

                <div class="bg-light p-3 rounded-3 text-start small border">
                    <div class="row mb-2">
                        <div class="col-4 text-muted fw-semibold">Status Akun:</div>
                        <div class="col-8 fw-bold text-success">Aktif</div>
                    </div>
                    <div class="row">
                        <div class="col-4 text-muted fw-semibold">Tugas Utama:</div>
                        <div class="col-8 text-secondary">
                            <?= $role === 'admin' ? 'Pengelolaan Data Master, Presensi, Pengaturan, & Pengingat Email' : 'Monitoring Presensi dan Rekapitulasi Laporan' ?>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="dashboard_<?= $role === 'admin' ? 'admin' : 'kepala_sekolah' ?>.php" class="btn btn-outline-primary rounded-pill px-4">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<!-- Script Interaktif Penautan Google Guru -->
<?php if ($role === 'guru'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusBadge = document.getElementById('googleStatusBadge');
    const alertFeedback = document.getElementById('alertFeedback');
    const sectionConnected = document.getElementById('sectionConnected');
    const sectionNotConnected = document.getElementById('sectionNotConnected');
    const sectionOtpInput = document.getElementById('sectionOtpInput');
    const connectedEmailDisplay = document.getElementById('connectedEmailDisplay');
    const connectedDateDisplay = document.getElementById('connectedDateDisplay');

    const inputGoogleEmail = document.getElementById('inputGoogleEmail');
    const btnSendOtp = document.getElementById('btnSendOtp');
    const btnSendOtpSpinner = document.getElementById('btnSendOtpSpinner');
    const btnSendOtpText = document.getElementById('btnSendOtpText');

    const inputOtpCode = document.getElementById('inputOtpCode');
    const btnVerifyOtp = document.getElementById('btnVerifyOtp');
    const btnVerifySpinner = document.getElementById('btnVerifySpinner');
    const btnVerifyText = document.getElementById('btnVerifyText');
    const btnResendOtp = document.getElementById('btnResendOtp');
    const cooldownSecondsSpan = document.getElementById('cooldownSeconds');
    const sentTargetEmailText = document.getElementById('sentTargetEmailText');
    const otpTimerBadge = document.getElementById('otpTimerBadge');

    const smtpWarningAlert = document.getElementById('smtpWarningAlert');
    const smtpWarningText = document.getElementById('smtpWarningText');

    const btnConfirmUnlinkAction = document.getElementById('btnConfirmUnlinkAction');
    const btnUnlinkSpinner = document.getElementById('btnUnlinkSpinner');

    let cooldownTimer = null;
    let otpExpiryTimer = null;
    let targetEmailCurrent = '';

    function showAlert(type, message) {
        alertFeedback.className = `alert alert-${type} alert-dismissible fade show rounded-3 shadow-sm`;
        alertFeedback.innerHTML = `
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        alertFeedback.classList.remove('d-none');
    }

    function hideAlert() {
        alertFeedback.classList.add('d-none');
    }

    // Load status terkini dari server
    function loadGoogleStatus() {
        fetch('api_google_auth.php?action=get_status')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                // Tampilkan info SMTP jika belum siap
                if (!data.smtp_ready) {
                    smtpWarningAlert.classList.remove('d-none');
                    smtpWarningText.textContent = data.smtp_message;
                } else {
                    smtpWarningAlert.classList.add('d-none');
                }

                if (data.status === 'terhubung') {
                    statusBadge.className = 'badge bg-success px-3 py-2 rounded-pill';
                    statusBadge.innerHTML = '<i class="bi bi-patch-check-fill me-1"></i> Terhubung';
                    
                    connectedEmailDisplay.textContent = data.email_google || '-';
                    connectedDateDisplay.textContent = data.verified_at ? new Date(data.verified_at).toLocaleString('id-ID') : '-';
                    
                    sectionConnected.classList.remove('d-none');
                    sectionNotConnected.classList.add('d-none');
                } else if (data.status === 'menunggu_verifikasi') {
                    statusBadge.className = 'badge bg-warning text-dark px-3 py-2 rounded-pill';
                    statusBadge.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Menunggu Verifikasi';
                    
                    sectionConnected.classList.add('d-none');
                    sectionNotConnected.classList.remove('d-none');
                    sectionOtpInput.classList.remove('d-none');
                    
                    targetEmailCurrent = data.pending_email || '';
                    inputGoogleEmail.value = targetEmailCurrent;
                    inputGoogleEmail.disabled = true;
                    sentTargetEmailText.textContent = targetEmailCurrent;

                    if (data.remaining_seconds > 0) {
                        startExpiryTimer(data.remaining_seconds);
                    }
                } else {
                    statusBadge.className = 'badge bg-secondary px-3 py-2 rounded-pill';
                    statusBadge.innerHTML = '<i class="bi bi-x-circle me-1"></i> Belum Ditautkan';

                    sectionConnected.classList.add('d-none');
                    sectionNotConnected.classList.remove('d-none');
                    sectionOtpInput.classList.add('d-none');
                    inputGoogleEmail.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                statusBadge.textContent = 'Gagal memuat status';
            });
    }

    // Timer kedaluwarsa 5 menit
    function startExpiryTimer(durationSeconds) {
        if (otpExpiryTimer) clearInterval(otpExpiryTimer);
        let remain = durationSeconds;
        function updateDisplay() {
            if (remain <= 0) {
                clearInterval(otpExpiryTimer);
                otpTimerBadge.innerHTML = '<i class="bi bi-x-circle me-1"></i> Kedaluwarsa';
                otpTimerBadge.className = 'badge bg-danger rounded-pill';
                btnVerifyOtp.disabled = true;
                return;
            }
            const m = Math.floor(remain / 60);
            const s = remain % 60;
            otpTimerBadge.innerHTML = `<i class="bi bi-clock me-1"></i> Masa berlaku: ${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            remain--;
        }
        updateDisplay();
        otpExpiryTimer = setInterval(updateDisplay, 1000);
    }

    // Cooldown Kirim Ulang OTP
    function startCooldown(seconds) {
        btnResendOtp.disabled = true;
        let s = seconds;
        cooldownSecondsSpan.textContent = s;
        if (cooldownTimer) clearInterval(cooldownTimer);
        cooldownTimer = setInterval(() => {
            s--;
            cooldownSecondsSpan.textContent = s;
            if (s <= 0) {
                clearInterval(cooldownTimer);
                btnResendOtp.disabled = false;
                btnResendOtp.textContent = 'Kirim Ulang Kode OTP';
            }
        }, 1000);
    }

    // Event Kirim OTP
    function handleSendOtp(emailVal) {
        if (!emailVal || !emailVal.includes('@')) {
            showAlert('warning', 'Harap masukkan alamat email yang valid.');
            return;
        }

        hideAlert();
        btnSendOtp.disabled = true;
        btnSendOtpSpinner.classList.remove('d-none');
        btnSendOtpText.textContent = 'Mengirim OTP...';

        const formData = new FormData();
        formData.append('action', 'send_otp');
        formData.append('email', emailVal);

        fetch('api_google_auth.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSendOtpSpinner.classList.add('d-none');
            btnSendOtpText.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Kode OTP';
            btnSendOtp.disabled = false;

            if (data.success) {
                targetEmailCurrent = emailVal;
                showAlert('success', data.message);
                sectionOtpInput.classList.remove('d-none');
                sentTargetEmailText.textContent = targetEmailCurrent;
                inputGoogleEmail.disabled = true;
                inputOtpCode.value = '';
                inputOtpCode.focus();
                btnVerifyOtp.disabled = false;

                startExpiryTimer(300); // 5 menit
                startCooldown(data.cooldown || 60);
                loadGoogleStatus();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(err => {
            btnSendOtpSpinner.classList.add('d-none');
            btnSendOtpText.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Kode OTP';
            btnSendOtp.disabled = false;
            showAlert('danger', 'Terjadi gangguan jaringan saat mengirim kode OTP.');
        });
    }

    btnSendOtp.addEventListener('click', function() {
        handleSendOtp(inputGoogleEmail.value.trim());
    });

    btnResendOtp.addEventListener('click', function() {
        handleSendOtp(targetEmailCurrent || inputGoogleEmail.value.trim());
    });

    // Event Verifikasi OTP
    btnVerifyOtp.addEventListener('click', function() {
        const otpCode = inputOtpCode.value.trim();
        if (otpCode.length !== 6 || !/^\d+$/.test(otpCode)) {
            showAlert('warning', 'Kode OTP harus 6 digit angka.');
            return;
        }

        hideAlert();
        btnVerifyOtp.disabled = true;
        btnVerifySpinner.classList.remove('d-none');
        btnVerifyText.textContent = 'Memverifikasi...';

        const formData = new FormData();
        formData.append('action', 'verify_otp');
        formData.append('email', targetEmailCurrent);
        formData.append('otp', otpCode);

        fetch('api_google_auth.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnVerifySpinner.classList.add('d-none');
            btnVerifyText.innerHTML = '<i class="bi bi-patch-check-fill me-1"></i> Verifikasi & Tautkan';
            btnVerifyOtp.disabled = false;

            if (data.success) {
                showAlert('success', data.message);
                if (otpExpiryTimer) clearInterval(otpExpiryTimer);
                if (cooldownTimer) clearInterval(cooldownTimer);
                loadGoogleStatus();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(err => {
            btnVerifySpinner.classList.add('d-none');
            btnVerifyText.innerHTML = '<i class="bi bi-patch-check-fill me-1"></i> Verifikasi & Tautkan';
            btnVerifyOtp.disabled = false;
            showAlert('danger', 'Terjadi kesalahan sistem saat memverifikasi kode OTP.');
        });
    });

    // Event Putuskan Tautan
    btnConfirmUnlinkAction.addEventListener('click', function() {
        btnConfirmUnlinkAction.disabled = true;
        btnUnlinkSpinner.classList.remove('d-none');

        const formData = new FormData();
        formData.append('action', 'unlink_google');

        fetch('api_google_auth.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnUnlinkSpinner.classList.add('d-none');
            btnConfirmUnlinkAction.disabled = false;
            
            // Tutup modal
            const modalEl = document.getElementById('modalConfirmUnlink');
            const modalInstance = bootstrap.Modal.getInstance(modalEl);
            if (modalInstance) modalInstance.hide();

            if (data.success) {
                showAlert('success', data.message);
                inputGoogleEmail.value = '';
                inputGoogleEmail.disabled = false;
                loadGoogleStatus();
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(err => {
            btnUnlinkSpinner.classList.add('d-none');
            btnConfirmUnlinkAction.disabled = false;
            showAlert('danger', 'Gagal memutuskan tautan akun Google.');
        });
    });

    // Inisialisasi awal
    loadGoogleStatus();
});
</script>
<?php endif; ?>
