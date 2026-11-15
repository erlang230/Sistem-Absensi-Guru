<?php
/**
 * Halaman Pengaturan Akun Email Sistem SAG
 * Khusus Administrator Sistem
 */

require_once 'auth.php';
check_login();
check_role('admin');

require_once 'config/database.php';
require_once 'includes/Mailer.php';

// Generate CSRF Token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (\Throwable $e) {
        $_SESSION['csrf_token'] = bin2hex(hash('sha256', uniqid(mt_rand(), true), true));
    }
}
$csrf_token = $_SESSION['csrf_token'];

// Ambil konfigurasi publik saat ini
$publicSettings = SAGMailer::get_public_settings();
$isConfigured   = $publicSettings['is_configured'];
$hasPassword    = $publicSettings['has_password'];
$usernameVal    = htmlspecialchars($publicSettings['smtp_username'] ?? '', ENT_QUOTES, 'UTF-8');
$fromNameVal    = htmlspecialchars($publicSettings['from_name'] ?? 'Sistem Absensi Guru (SAG)', ENT_QUOTES, 'UTF-8');
$fromEmailVal   = htmlspecialchars($publicSettings['from_email'] ?? '', ENT_QUOTES, 'UTF-8');
$lastTestedAt   = $publicSettings['last_tested_at'];
$lastTestStatus = $publicSettings['last_test_status'];
$lastTestMsg    = $publicSettings['last_test_message'];

// Ambil email admin yang sedang login untuk rekomendasi email uji coba
$adminEmail = '';
$adminId = (int)$_SESSION['user_id'];
$qAdmin = mysqli_query($conn, "SELECT username FROM users WHERE id = {$adminId} LIMIT 1");
if ($rAdmin = mysqli_fetch_assoc($qAdmin)) {
    if (filter_var($rAdmin['username'], FILTER_VALIDATE_EMAIL)) {
        $adminEmail = $rAdmin['username'];
    }
}
?>
<?php include 'includes/header.php'; ?>

<!-- Header Halaman -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">
            <i class="bi bi-envelope-gear text-primary me-2"></i>Pengaturan Email Sistem
        </h3>
        <p class="text-muted mb-0">
            Kelola akun Gmail pengirim kode OTP verifikasi akun, pemulihan, dan pengingat kehadiran guru SAG.
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small">
            <i class="bi bi-shield-check text-success me-1"></i>Enkripsi AES-256 Aktif
        </span>
    </div>
</div>

<!-- Alert Notifikasi Global (AJAX Feedback) -->
<div id="globalEmailAlert" class="alert d-none rounded-4 border-0 shadow-sm mb-4" role="alert">
    <div class="d-flex align-items-center">
        <div id="globalAlertIcon" class="me-3 fs-4"></div>
        <div id="globalAlertMessage" class="flex-grow-1 small"></div>
        <button type="button" class="btn-close ms-2" onclick="dismissGlobalAlert()"></button>
    </div>
</div>

<!-- Status Overview Cards (4 Kartu Ringkasan) -->
<div class="row g-3 mb-4">
    <!-- Status Konfigurasi -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f8faff); border-left:4px solid #0d6efd !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:50px; height:50px; background:linear-gradient(135deg, #0d6efd, #0b5ed7);">
                    <i class="bi bi-gear-wide-connected fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Status Konfigurasi</span>
                    <div id="badgeStatusKonfigurasi" class="mt-1">
                        <?php if ($isConfigured): ?>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                <i class="bi bi-check-circle-fill me-1"></i> Terkonfigurasi
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1 rounded-pill">
                                <i class="bi bi-exclamation-circle me-1"></i> Belum Dikonfigurasi
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Koneksi SMTP -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f4fbf7); border-left:4px solid #198754 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:50px; height:50px; background:linear-gradient(135deg, #198754, #157347);">
                    <i class="bi bi-hdd-network fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Koneksi SMTP</span>
                    <div id="badgeStatusSmtp" class="mt-1">
                        <?php if ($lastTestStatus === 'berhasil'): ?>
                            <span class="badge bg-success px-2 py-1 rounded-pill">
                                <i class="bi bi-link-45deg me-1"></i> Terhubung & Berhasil
                            </span>
                        <?php elseif ($lastTestStatus === 'gagal'): ?>
                            <span class="badge bg-danger px-2 py-1 rounded-pill">
                                <i class="bi bi-x-circle me-1"></i> Pengujian Gagal
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1 rounded-pill">
                                <i class="bi bi-hourglass me-1"></i> Belum Diuji
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Akun Pengirim Aktif -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #fdfaf2); border-left:4px solid #ffc107 !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:50px; height:50px; background:linear-gradient(135deg, #ffc107, #d39e00);">
                    <i class="bi bi-envelope-at fs-4"></i>
                </div>
                <div class="overflow-hidden">
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Pengirim Sistem</span>
                    <h6 id="displaySenderEmail" class="fw-bold mb-0 text-dark text-truncate" title="<?= !empty($usernameVal) ? $usernameVal : 'Belum diatur' ?>" style="font-size:0.88rem;">
                        <?= !empty($usernameVal) ? $usernameVal : '<span class="text-muted fw-normal fst-italic">Belum diatur</span>' ?>
                    </h6>
                </div>
            </div>
        </div>
    </div>

    <!-- Waktu Terakhir Pengujian -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm rounded-4 h-100 p-3" style="background:linear-gradient(135deg, #ffffff, #f7f9fc); border-left:4px solid #6c757d !important;">
            <div class="card-body p-1 d-flex align-items-center">
                <div class="rounded-4 p-3 d-flex align-items-center justify-content-center text-white me-3 shadow-sm" style="width:50px; height:50px; background:linear-gradient(135deg, #6c757d, #495057);">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
                <div>
                    <span class="text-muted small fw-semibold text-uppercase" style="font-size:0.75rem;">Terakhir Diuji</span>
                    <h6 id="displayLastTested" class="fw-bold mb-0 text-dark" style="font-size:0.85rem;">
                        <?= !empty($lastTestedAt) ? date('d/m/Y H:i', strtotime($lastTestedAt)) . ' WIB' : '<span class="text-muted fw-normal fst-italic">Belum pernah</span>' ?>
                    </h6>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Form Konfigurasi Email -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-2">
                        <i class="bi bi-sliders fs-5"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Form Pengaturan Email Gmail</h5>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill small">
                    SMTP TLS 587
                </span>
            </div>

            <div class="card-body p-4">
                <form id="formEmailSettings" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <!-- Alamat Gmail Pengirim -->
                    <div class="mb-3">
                        <label for="smtp_username" class="form-label fw-semibold text-dark small">
                            Alamat Gmail Pengirim <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-envelope"></i>
                            </span>
                            <input type="email" 
                                   class="form-control border-start-0 ps-0" 
                                   id="smtp_username" 
                                   name="smtp_username" 
                                   value="<?= $usernameVal ?>" 
                                   placeholder="misal: notifikasi.sag@gmail.com" 
                                   required 
                                   autocomplete="off">
                        </div>
                        <div class="form-text text-muted small">
                            Gunakan akun Gmail khusus sistem sekolah SAG untuk mengirimkan email notifikasi otomatis.
                        </div>
                    </div>

                    <!-- Password Aplikasi Gmail (App Password) -->
                    <div class="mb-3">
                        <label for="app_password" class="form-label fw-semibold text-dark small d-flex justify-content-between align-items-center">
                            <span>Password Aplikasi Gmail (App Password 16 Karakter) <span class="text-danger">*</span></span>
                            <?php if ($hasPassword): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" style="font-size:0.75rem;">
                                    <i class="bi bi-lock-fill me-1"></i> Tersimpan (Terenkripsi)
                                </span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-key"></i>
                            </span>
                            <input type="password" 
                                   class="form-control border-start-0 border-end-0 ps-0 font-monospace" 
                                   id="app_password" 
                                   name="app_password" 
                                   placeholder="<?= $hasPassword ? '•••••••••••••••• (Kosongkan jika tidak ingin mengubah)' : '16 karakter (misal: abcd efgh ijkl mnop)' ?>" 
                                   autocomplete="new-password">
                            <button class="btn btn-outline-secondary border-start-0" 
                                    type="button" 
                                    id="btnTogglePassword" 
                                    title="Tampilkan / Sembunyikan Password">
                                <i class="bi bi-eye" id="iconTogglePassword"></i>
                            </button>
                        </div>
                        <div class="form-text text-muted small mt-1">
                            <i class="bi bi-shield-exclamation text-warning me-1"></i>
                            <strong>PENTING:</strong> Jangan gunakan kata sandi akun Gmail utama Anda. Gunakan <strong>Sandi Aplikasi (App Password)</strong> yang dibuat dari Pengaturan Keamanan Google Akun.
                        </div>
                    </div>

                    <!-- Nama Pengirim -->
                    <div class="mb-3">
                        <label for="from_name" class="form-label fw-semibold text-dark small">
                            Nama Pengirim (Display Name)
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-person-badge"></i>
                            </span>
                            <input type="text" 
                                   class="form-control border-start-0 ps-0" 
                                   id="from_name" 
                                   name="from_name" 
                                   value="<?= $fromNameVal ?>" 
                                   placeholder="Sistem Absensi Guru (SAG)" 
                                   required>
                        </div>
                        <div class="form-text text-muted small">
                            Nama yang akan tampil pada kotak masuk penerima email (Default: Sistem Absensi Guru (SAG)).
                        </div>
                    </div>

                    <!-- Parameter SMTP Statis (Read-Only) -->
                    <div class="p-3 bg-light rounded-4 border mb-4">
                        <h6 class="fw-bold mb-2 text-dark small"><i class="bi bi-info-circle me-1 text-primary"></i>Parameter SMTP Baku</h6>
                        <div class="row g-2 text-muted" style="font-size:0.8rem;">
                            <div class="col-6 col-sm-3">
                                <span class="d-block fw-semibold text-dark">SMTP Host</span>
                                <code>smtp.gmail.com</code>
                            </div>
                            <div class="col-6 col-sm-3">
                                <span class="d-block fw-semibold text-dark">Port</span>
                                <code>587</code>
                            </div>
                            <div class="col-6 col-sm-3">
                                <span class="d-block fw-semibold text-dark">Enkripsi</span>
                                <code>STARTTLS</code>
                            </div>
                            <div class="col-6 col-sm-3">
                                <span class="d-block fw-semibold text-dark">Autentikasi</span>
                                <span class="badge bg-success bg-opacity-10 text-success">Aktif</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi Simpan -->
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 pt-2 border-top">
                        <div class="text-muted small">
                            <i class="bi bi-lock me-1"></i>Kredensial dienkripsi dengan standar AES-256 server-side.
                        </div>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" id="btnSimpanPengaturan">
                            <span id="spinnerSimpan" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                            <i class="bi bi-check2-circle me-1" id="iconSimpan"></i>
                            <span id="textSimpan">Simpan Pengaturan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Panel Uji Kirim Email & Panduan App Password -->
    <div class="col-lg-5">
        <!-- Panel Uji Kirim Email -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success p-2 rounded-3 me-2">
                        <i class="bi bi-send-check fs-5"></i>
                    </div>
                    <h5 class="fw-bold mb-0 text-dark">Uji Kirim Email</h5>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill small">
                    Live SMTP Test
                </span>
            </div>

            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    Kirim email percobaan untuk memastikan kredensial Gmail, koneksi server SMTP, dan hak pengiriman telah berjalan lancar sebelum digunakan pada fitur absensi & OTP.
                </p>

                <form id="formTestEmail" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div class="mb-3">
                        <label for="test_email" class="form-label fw-semibold text-dark small">
                            Alamat Email Tujuan Pengujian <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="bi bi-inbox"></i>
                            </span>
                            <input type="email" 
                                   class="form-control border-start-0 ps-0" 
                                   id="test_email" 
                                   name="test_email" 
                                   value="<?= htmlspecialchars($adminEmail) ?>" 
                                   placeholder="misal: email.anda@gmail.com" 
                                   required>
                        </div>
                        <div class="form-text text-muted small">
                            Masukkan email aktif Anda yang dapat dibuka untuk memeriksa apakah email percobaan berhasil diterima.
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="button" class="btn btn-success rounded-pill py-2 fw-semibold shadow-sm" id="btnUjiKirimEmail">
                            <span id="spinnerTest" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                            <i class="bi bi-send me-1" id="iconTest"></i>
                            <span id="textTest">Uji Kirim Email Sekarang</span>
                        </button>
                    </div>
                </form>

                <!-- Box Hasil Pengujian Terakhir -->
                <div id="testResultBox" class="p-3 rounded-4 border bg-light mt-3 <?= empty($lastTestMsg) ? 'd-none' : '' ?>">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold small text-dark"><i class="bi bi-clipboard-pulse me-1 text-primary"></i>Hasil Pengujian Terakhir:</span>
                        <span id="testResultBadge">
                            <?php if ($lastTestStatus === 'berhasil'): ?>
                                <span class="badge bg-success">Berhasil</span>
                            <?php elseif ($lastTestStatus === 'gagal'): ?>
                                <span class="badge bg-danger">Gagal</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <p id="testResultMessage" class="text-secondary small mb-1" style="line-height:1.5;">
                        <?= htmlspecialchars($lastTestMsg ?? '-') ?>
                    </p>
                    <div class="text-muted" style="font-size:0.75rem;">
                        <i class="bi bi-clock me-1"></i>Waktu: <span id="testResultTime"><?= !empty($lastTestedAt) ? date('d/m/Y H:i:s', strtotime($lastTestedAt)) . ' WIB' : '-' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Panduan Keamanan & Langkah Mendapatkan App Password -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom">
                <div class="d-flex align-items-center">
                    <i class="bi bi-question-circle text-primary me-2 fs-5"></i>
                    <h6 class="fw-bold mb-0 text-dark">Panduan App Password Gmail</h6>
                </div>
            </div>
            <div class="card-body p-4 small text-secondary">
                <p class="mb-2">
                    Untuk menggunakan akun Gmail sebagai pengirim sistem, Google mewajibkan <strong>Sandi Aplikasi (App Password 16 Karakter)</strong>:
                </p>
                <ol class="ps-3 mb-3" style="line-height: 1.7;">
                    <li>Buka menu <a href="https://myaccount.google.com/security" target="_blank" class="fw-semibold text-decoration-none">Keamanan Akun Google</a>.</li>
                    <li>Pastikan <strong>Verifikasi 2 Langkah (2-Step Verification)</strong> sudah aktif.</li>
                    <li>Ketik atau cari menu <strong>Sandi Aplikasi</strong> (App Passwords).</li>
                    <li>Beri nama aplikasi, misalnya <code>SAG Absensi</code>, lalu klik <strong>Buat</strong>.</li>
                    <li>Salin 16 karakter yang muncul ke kolom App Password di sebelah kiri.</li>
                </ol>
                <div class="alert alert-warning border-0 rounded-3 p-2 small mb-0 d-flex align-items-start">
                    <i class="bi bi-shield-lock-fill text-warning fs-5 me-2 flex-shrink-0"></i>
                    <div>
                        App Password disimpan dengan enkripsi AES-256 dan dilindungi dari akses publik. Nilai password asli tidak pernah dikirimkan ke peramban.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Penggantian Konfigurasi Email -->
<div class="modal fade" id="modalConfirmChangeEmail" tabindex="-1" aria-labelledby="modalConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalConfirmLabel">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>Konfirmasi Perubahan Email
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-secondary mb-3">
                    Anda akan mengubah konfigurasi akun email pengirim sistem SAG. Seluruh pengiriman kode OTP dan pengingat kehadiran guru akan dialihkan menggunakan akun baru ini.
                </p>
                <div class="p-3 bg-light rounded-3 border">
                    <div class="row mb-1">
                        <div class="col-4 text-muted small fw-semibold">Gmail Pengirim:</div>
                        <div class="col-8 fw-bold text-dark font-monospace small" id="confirmSenderEmail">-</div>
                    </div>
                    <div class="row">
                        <div class="col-4 text-muted small fw-semibold">Nama Pengirim:</div>
                        <div class="col-8 text-secondary small" id="confirmSenderName">-</div>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    Apakah Anda yakin ingin menyimpan dan memperbarui konfigurasi ini?
                </p>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnExecuteSave">
                    Ya, Simpan Konfigurasi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const formSettings      = document.getElementById('formEmailSettings');
    const inputUsername     = document.getElementById('smtp_username');
    const inputPassword     = document.getElementById('app_password');
    const inputFromName     = document.getElementById('from_name');
    const btnTogglePassword = document.getElementById('btnTogglePassword');
    const iconTogglePassword= document.getElementById('iconTogglePassword');

    const btnSimpan         = document.getElementById('btnSimpanPengaturan');
    const spinnerSimpan     = document.getElementById('spinnerSimpan');
    const iconSimpan        = document.getElementById('iconSimpan');
    const textSimpan        = document.getElementById('textSimpan');

    const formTest          = document.getElementById('formTestEmail');
    const inputTestEmail    = document.getElementById('test_email');
    const btnUjiKirim       = document.getElementById('btnUjiKirimEmail');
    const spinnerTest       = document.getElementById('spinnerTest');
    const iconTest          = document.getElementById('iconTest');
    const textTest          = document.getElementById('textTest');

    const alertBox          = document.getElementById('globalEmailAlert');
    const alertIcon         = document.getElementById('globalAlertIcon');
    const alertMsg          = document.getElementById('globalAlertMessage');

    const modalConfirmEl    = document.getElementById('modalConfirmChangeEmail');
    const modalConfirm      = modalConfirmEl ? new bootstrap.Modal(modalConfirmEl) : null;
    const btnExecuteSave    = document.getElementById('btnExecuteSave');
    const confirmSenderEmail= document.getElementById('confirmSenderEmail');
    const confirmSenderName = document.getElementById('confirmSenderName');

    const hasStoredPassword = <?= json_encode($hasPassword) ?>;
    const currentUsername   = <?= json_encode($publicSettings['smtp_username'] ?? '') ?>;

    // 1. Tampilkan / Sembunyikan Password
    btnTogglePassword.addEventListener('click', function() {
        if (inputPassword.type === 'password') {
            inputPassword.type = 'text';
            iconTogglePassword.className = 'bi bi-eye-slash';
        } else {
            inputPassword.type = 'password';
            iconTogglePassword.className = 'bi bi-eye';
        }
    });

    // 2. Tampilkan Alert Global
    function showAlert(type, message) {
        if (!alertBox) return;
        alertBox.className = `alert alert-${type} rounded-4 border-0 shadow-sm mb-4`;
        
        let iconHtml = '';
        if (type === 'success') {
            iconHtml = '<i class="bi bi-check-circle-fill text-success"></i>';
        } else if (type === 'warning') {
            iconHtml = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>';
        } else {
            iconHtml = '<i class="bi bi-x-circle-fill text-danger"></i>';
        }

        alertIcon.innerHTML = iconHtml;
        alertMsg.innerHTML = message;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    window.dismissGlobalAlert = function() {
        if (alertBox) alertBox.classList.add('d-none');
    };

    // 3. Validasi & Konfirmasi Sebelum Menyimpan
    btnSimpan.addEventListener('click', function() {
        const username = inputUsername.value.trim();
        const password = inputPassword.value.trim();

        if (!username) {
            showAlert('danger', 'Alamat Gmail pengirim wajib diisi.');
            inputUsername.focus();
            return;
        }

        if (!hasStoredPassword && !password) {
            showAlert('danger', 'Password Aplikasi Gmail (App Password 16 karakter) wajib diisi untuk konfigurasi awal.');
            inputPassword.focus();
            return;
        }

        if (password) {
            const cleanPass = password.replace(/[\s-]/g, '');
            if (cleanPass.length < 16) {
                showAlert('warning', 'App Password Gmail biasanya terdiri dari 16 karakter (tanpa spasi). Pastikan format sudah benar.');
            }
        }

        // Tampilkan modal konfirmasi jika sudah ada konfigurasi sebelumnya
        if (currentUsername && currentUsername !== '') {
            confirmSenderEmail.textContent = username;
            confirmSenderName.textContent = inputFromName.value.trim() || 'Sistem Absensi Guru (SAG)';
            if (modalConfirm) {
                modalConfirm.show();
                return;
            }
        }

        // Jika belum ada konfigurasi sebelumnya, langsung simpan
        executeSave();
    });

    if (btnExecuteSave) {
        btnExecuteSave.addEventListener('click', function() {
            if (modalConfirm) modalConfirm.hide();
            executeSave();
        });
    }

    // Eksekusi Simpan via AJAX
    function executeSave() {
        btnSimpan.disabled = true;
        spinnerSimpan.classList.remove('d-none');
        iconSimpan.classList.add('d-none');
        textSimpan.textContent = 'Menyimpan...';

        const formData = new FormData(formSettings);
        formData.append('action', 'save');

        fetch('api_pengaturan_email.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnSimpan.disabled = false;
            spinnerSimpan.classList.add('d-none');
            iconSimpan.classList.remove('d-none');
            textSimpan.textContent = 'Simpan Pengaturan';

            if (data.success) {
                showAlert('success', data.message);
                // Kosongkan kolom password setelah disimpan (jangan tampilkan nilainya)
                inputPassword.value = '';
                inputPassword.placeholder = '•••••••••••••••• (Kosongkan jika tidak ingin mengubah)';

                // Update tampilan ringkasan
                const senderDisplay = document.getElementById('displaySenderEmail');
                if (senderDisplay) senderDisplay.textContent = inputUsername.value.trim();

                const badgeKonfig = document.getElementById('badgeStatusKonfigurasi');
                if (badgeKonfig) {
                    badgeKonfig.innerHTML = `
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                            <i class="bi bi-check-circle-fill me-1"></i> Terkonfigurasi
                        </span>
                    `;
                }
            } else {
                showAlert('danger', data.message || 'Gagal menyimpan pengaturan.');
            }
        })
        .catch(err => {
            btnSimpan.disabled = false;
            spinnerSimpan.classList.add('d-none');
            iconSimpan.classList.remove('d-none');
            textSimpan.textContent = 'Simpan Pengaturan';
            showAlert('danger', 'Terjadi gangguan koneksi ke server saat menyimpan konfigurasi.');
        });
    }

    // 4. Uji Kirim Email via AJAX
    btnUjiKirim.addEventListener('click', function() {
        const testEmail = inputTestEmail.value.trim();
        if (!testEmail) {
            showAlert('danger', 'Harap isi alamat email tujuan pengujian.');
            inputTestEmail.focus();
            return;
        }

        btnUjiKirim.disabled = true;
        spinnerTest.classList.remove('d-none');
        iconTest.classList.add('d-none');
        textTest.textContent = 'Mengirim Pesan Uji Coba...';

        const formData = new FormData(formTest);
        formData.append('action', 'test');

        fetch('api_pengaturan_email.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            btnUjiKirim.disabled = false;
            spinnerTest.classList.add('d-none');
            iconTest.classList.remove('d-none');
            textTest.textContent = 'Uji Kirim Email Sekarang';

            const resultBox = document.getElementById('testResultBox');
            const resultBadge = document.getElementById('testResultBadge');
            const resultMsg = document.getElementById('testResultMessage');
            const resultTime = document.getElementById('testResultTime');
            const badgeSmtp = document.getElementById('badgeStatusSmtp');
            const displayLastTested = document.getElementById('displayLastTested');

            if (resultBox) resultBox.classList.remove('d-none');
            if (resultMsg) resultMsg.textContent = data.message;
            if (resultTime) resultTime.textContent = data.tested_at || 'Baru Saja';
            if (displayLastTested) displayLastTested.textContent = data.tested_at || 'Baru Saja';

            if (data.success) {
                showAlert('success', data.message);
                if (resultBadge) resultBadge.innerHTML = '<span class="badge bg-success">Berhasil</span>';
                if (badgeSmtp) {
                    badgeSmtp.innerHTML = `
                        <span class="badge bg-success px-2 py-1 rounded-pill">
                            <i class="bi bi-link-45deg me-1"></i> Terhubung & Berhasil
                        </span>
                    `;
                }
            } else {
                showAlert('danger', data.message);
                if (resultBadge) resultBadge.innerHTML = '<span class="badge bg-danger">Gagal</span>';
                if (badgeSmtp) {
                    badgeSmtp.innerHTML = `
                        <span class="badge bg-danger px-2 py-1 rounded-pill">
                            <i class="bi bi-x-circle me-1"></i> Pengujian Gagal
                        </span>
                    `;
                }
            }
        })
        .catch(err => {
            btnUjiKirim.disabled = false;
            spinnerTest.classList.add('d-none');
            iconTest.classList.remove('d-none');
            textTest.textContent = 'Uji Kirim Email Sekarang';
            showAlert('danger', 'Terjadi gangguan jaringan saat menghubungi server pengujian email.');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
