<?php
require_once 'auth.php';
check_login();
check_role('guru');
require_once 'config/database.php';

$id_user = $_SESSION['user_id'];
$stmt_g = mysqli_prepare($conn, "SELECT id_guru, nama_guru, face_data FROM guru WHERE id_user = ?");
mysqli_stmt_bind_param($stmt_g, "i", $id_user);
mysqli_stmt_execute($stmt_g);
$guru_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_g));
$id_guru = $guru_data['id_guru'];
$nama_guru_session = $guru_data['nama_guru'];
$face_sudah_terdaftar = !empty($guru_data['face_data']);

$tanggal_hari_ini = date('Y-m-d');
$stmt_cek = mysqli_prepare($conn, "SELECT * FROM absensi WHERE id_guru = ? AND tanggal = ?");
mysqli_stmt_bind_param($stmt_cek, "is", $id_guru, $tanggal_hari_ini);
mysqli_stmt_execute($stmt_cek);
$absen_hari_ini = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cek));

$jenis_absen = ''; $label_absen = ''; $warna_absen = '';
if (!$absen_hari_ini) {
    $jenis_absen = 'masuk'; $label_absen = 'Absensi Masuk'; $warna_absen = 'primary';
} elseif ($absen_hari_ini['jam_pulang'] == null) {
    $jenis_absen = 'pulang'; $label_absen = 'Absensi Pulang'; $warna_absen = 'danger';
} else {
    $jenis_absen = 'selesai';
}
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2 text-primary"></i> Verifikasi Kehadiran AI</h3>
    <span id="jam-realtime" class="fw-bold text-dark fs-5">--:--:--</span>
</div>

<?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm">
        <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if(isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($jenis_absen == 'selesai'): ?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body p-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle mb-4" style="width:100px;height:100px;">
                    <i class="bi bi-check-all text-success" style="font-size:3.5rem;"></i>
                </div>
                <h4 class="fw-bold mb-3">Absensi Hari Ini Selesai!</h4>
                <div class="alert alert-success d-inline-block text-start shadow-sm" style="min-width:280px;">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Jam Masuk:</span><strong><?= $absen_hari_ini['jam_masuk'] ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Jam Pulang:</span><strong><?= $absen_hari_ini['jam_pulang'] ?></strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Metode:</span><strong><?= htmlspecialchars($absen_hari_ini['metode_verifikasi']) ?></strong></div>
                </div>
                <p class="text-muted mt-3">Selamat beristirahat, <?= htmlspecialchars($nama_guru_session) ?>!</p>
            </div>
        </div>
    </div>
</div>

<?php elseif (!$face_sudah_terdaftar): ?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center p-5">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size:4rem;"></i>
                <h4 class="fw-bold mt-3 mb-2">Data Wajah Belum Terdaftar</h4>
                <p class="text-muted mx-auto" style="max-width:460px;">Hubungi Admin untuk mendaftarkan data wajah Anda sebelum dapat menggunakan sistem absensi AI.</p>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===== MAIN ABSENSI PANEL ===== -->

<!-- Progress Steps -->
<div class="card border-0 shadow-sm rounded-4 mb-4 py-3 px-4">
    <div class="d-flex align-items-center justify-content-center gap-0 flex-wrap">
        <!-- Step 1: Kamera -->
        <div class="text-center" style="min-width:90px;">
            <div id="step-icon-1" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-1 bg-secondary" style="width:42px;height:42px;transition:all 0.4s;">
                <i class="bi bi-webcam text-white" style="font-size:1.1rem;"></i>
            </div>
            <p class="mb-0 small fw-medium text-muted" id="step-label-1">Kamera</p>
        </div>
        <div id="step-line-1" class="flex-grow-1 border-top border-2 mx-1" style="max-width:60px;opacity:0.2;transition:opacity 0.4s;"></div>
        <!-- Step 2: Deteksi Wajah (BARU) -->
        <div class="text-center" style="min-width:90px;">
            <div id="step-icon-2" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-1 bg-secondary" style="width:42px;height:42px;transition:all 0.4s;">
                <i class="bi bi-person-bounding-box text-white" style="font-size:1.1rem;"></i>
            </div>
            <p class="mb-0 small fw-medium text-muted" id="step-label-2">Deteksi</p>
        </div>
        <div id="step-line-2" class="flex-grow-1 border-top border-2 mx-1" style="max-width:60px;opacity:0.2;transition:opacity 0.4s;"></div>
        <!-- Step 3: Liveness -->
        <div class="text-center" style="min-width:90px;">
            <div id="step-icon-3" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-1 bg-secondary" style="width:42px;height:42px;transition:all 0.4s;">
                <i class="bi bi-eye text-white" style="font-size:1.1rem;"></i>
            </div>
            <p class="mb-0 small fw-medium text-muted" id="step-label-3">Liveness</p>
        </div>
        <div id="step-line-3" class="flex-grow-1 border-top border-2 mx-1" style="max-width:60px;opacity:0.2;transition:opacity 0.4s;"></div>
        <!-- Step 4: Identifikasi -->
        <div class="text-center" style="min-width:90px;">
            <div id="step-icon-4" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-1 bg-secondary" style="width:42px;height:42px;transition:all 0.4s;">
                <i class="bi bi-person-check text-white" style="font-size:1.1rem;"></i>
            </div>
            <p class="mb-0 small fw-medium text-muted" id="step-label-4">Identifikasi</p>
        </div>
        <div id="step-line-4" class="flex-grow-1 border-top border-2 mx-1" style="max-width:60px;opacity:0.2;transition:opacity 0.4s;"></div>
        <!-- Step 5: Absensi -->
        <div class="text-center" style="min-width:90px;">
            <div id="step-icon-5" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-1 bg-secondary" style="width:42px;height:42px;transition:all 0.4s;">
                <i class="bi bi-clipboard-check text-white" style="font-size:1.1rem;"></i>
            </div>
            <p class="mb-0 small fw-medium text-muted" id="step-label-5">Absensi</p>
        </div>
    </div>
</div>

<div class="row">
    <!-- Kolom Kamera -->
    <div class="col-lg-7 mb-4">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4">

                <!-- Area Kamera -->
                <div class="position-relative rounded-4 overflow-hidden bg-dark shadow-lg" style="width:100%;aspect-ratio:4/3;">
                    <video id="video-stream" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;display:none;transform:scaleX(-1);"></video>
                    <canvas id="canvas-overlay" style="position:absolute;top:0;left:0;width:100%;height:100%;transform:scaleX(-1);"></canvas>
                    <canvas id="canvas-snapshot" style="display:none;"></canvas>
                    <img id="photo-preview" style="width:100%;height:100%;object-fit:cover;display:none;" alt="Preview">

                    <!-- Badge Status Wajah (Face Detection) -->
                    <div id="badge-face-detection" style="position:absolute;top:12px;left:12px;display:none;z-index:10;">
                        <span id="badge-fd-text" class="badge px-3 py-2 shadow-sm" style="font-size:0.78rem;background:rgba(0,0,0,0.75);color:#fff;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.15);">
                            <i id="badge-fd-icon" class="bi bi-hourglass-split me-1"></i>
                            <span id="badge-fd-label">Mendeteksi Wajah...</span>
                        </span>
                    </div>

                    <!-- Jumlah Wajah Counter -->
                    <div id="badge-face-count" style="position:absolute;top:12px;right:12px;display:none;z-index:10;">
                        <span class="badge bg-dark px-3 py-2 shadow-sm" style="font-size:0.78rem;backdrop-filter:blur(4px);border:1px solid rgba(255,255,255,0.12);">
                            <i class="bi bi-people me-1"></i>
                            <span id="badge-fc-label">0 Wajah</span>
                        </span>
                    </div>

                    <!-- Challenge Overlay -->
                    <div id="challenge-overlay" style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,0.85));padding:20px 16px 16px;display:none;">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-white-50 d-block mb-1">Tantangan Liveness</small>
                                <h5 class="text-white fw-bold mb-0" id="challenge-text">-</h5>
                            </div>
                            <div class="text-end">
                                <div id="challenge-timer-circle" class="d-inline-flex align-items-center justify-content-center rounded-circle border border-2 border-warning" style="width:48px;height:48px;">
                                    <span id="challenge-timer" class="fw-bold text-warning" style="font-size:1.2rem;">10</span>
                                </div>
                            </div>
                        </div>
                        <!-- Progress liveness -->
                        <div class="progress rounded-pill mt-2" style="height:5px;background:rgba(255,255,255,0.15);">
                            <div id="liveness-progress-bar" class="progress-bar bg-warning rounded-pill progress-bar-striped progress-bar-animated" style="width:0%;transition:width 0.3s;"></div>
                        </div>
                    </div>

                    <!-- Placeholder -->
                    <div id="camera-placeholder" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white" style="position:absolute;top:0;left:0;">
                        <i class="bi bi-camera-video" style="font-size:4rem;opacity:0.4;"></i>
                        <p class="mt-2 text-white-50">Kamera belum aktif</p>
                    </div>

                    <!-- Loading Model -->
                    <div id="ai-loading-overlay" class="w-100 h-100 align-items-center justify-content-center text-white flex-column" style="position:absolute;top:0;left:0;background:rgba(0,0,0,0.88);display:none;">
                        <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;"></div>
                        <p class="mb-1 fw-bold" id="loading-text">Memuat Model AI...</p>
                        <small class="text-white-50">Harap tunggu</small>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="mt-4 text-center" id="action-area">
                    <button id="btn-aktifkan" class="btn btn-dark btn-lg px-5 rounded-pill shadow">
                        <i class="bi bi-webcam me-2"></i> Aktifkan Kamera
                    </button>
                    <!-- Tombol Lanjut ke Liveness (hanya aktif jika 1 wajah terdeteksi) -->
                    <button id="btn-lanjut-liveness" class="btn btn-success btn-lg px-5 rounded-pill shadow" style="display:none;" disabled>
                        <i class="bi bi-arrow-right-circle me-2"></i> Lanjut ke Verifikasi
                    </button>
                    <button id="btn-ulangi-liveness" class="btn btn-outline-warning btn-lg px-5 rounded-pill shadow" style="display:none;">
                        <i class="bi bi-arrow-counterclockwise me-2"></i> Ulangi Liveness
                    </button>
                </div>
                <!-- Status Face Detection (teks informatif) -->
                <div id="fd-status-bar" class="mt-3 text-center" style="display:none;">
                    <span id="fd-status-msg" class="small fw-medium"></span>
                </div>

                <!-- Form Submit -->
                <form id="form-absensi" action="proses_absensi.php" method="POST" style="display:none;">
                    <input type="hidden" name="jenis" value="<?= $jenis_absen ?>">
                    <input type="hidden" name="foto_base64" id="foto_base64">
                    <input type="hidden" name="metode_verifikasi" value="AI Face Recognition">
                    <input type="hidden" name="verified_guru_id" id="verified_guru_id">
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom Panel Status -->
    <div class="col-lg-5 mb-4">

        <!-- Panel Face Detection (BARU) -->
        <div class="card border-0 shadow-sm rounded-4 mb-3" id="panel-face-detection" style="background:#1e1e2d;">
            <div class="card-body p-4">
                <h6 class="text-white-50 fw-bold text-uppercase mb-3" style="font-size:0.7rem;letter-spacing:1.5px;">
                    <i class="bi bi-person-bounding-box me-1"></i> Face Detection
                </h6>
                <div class="text-center mb-3">
                    <div id="fd-icon" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" style="width:64px;height:64px;border:2px solid rgba(255,255,255,0.1);transition:all 0.35s;">
                        <i class="bi bi-camera-video text-white-50" style="font-size:1.8rem;"></i>
                    </div>
                    <p id="fd-panel-status" class="mb-1 fw-semibold" style="font-size:0.92rem;color:#adb5bd;">Menunggu Kamera</p>
                    <div id="fd-face-count-badge" style="display:none;">
                        <span id="fd-count-span" class="badge bg-secondary px-3 py-1">0 wajah terdeteksi</span>
                    </div>
                </div>
                <!-- Mini info bar -->
                <div id="fd-info-bar" class="rounded-3 px-3 py-2" style="background:rgba(255,255,255,0.04);display:none;">
                    <div class="d-flex align-items-center gap-2">
                        <div id="fd-dot" style="width:8px;height:8px;border-radius:50%;background:#555;flex-shrink:0;transition:all 0.3s;"></div>
                        <small id="fd-info-text" class="text-white-50">Menunggu...</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Liveness -->
        <div class="card border-0 shadow-sm rounded-4 mb-3" id="panel-liveness" style="background:#1e1e2d;">
            <div class="card-body p-4">
                <h6 class="text-white-50 fw-bold text-uppercase mb-3" style="font-size:0.7rem;letter-spacing:1.5px;">Liveness Detection</h6>
                <div class="text-center mb-3">
                    <div id="liveness-icon" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" style="width:64px;height:64px;border:2px solid rgba(255,255,255,0.1);">
                        <i class="bi bi-eye text-white-50" style="font-size:1.8rem;"></i>
                    </div>
                    <p id="liveness-status-text" class="text-white-50 mb-0 small">Menunggu deteksi wajah...</p>
                </div>
                <div id="liveness-success-detail" class="alert border-0 text-center py-2 mb-0" style="background:rgba(32,201,151,0.15);display:none;">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                    <span class="text-success fw-bold small">Liveness Terverifikasi!</span>
                </div>
            </div>
        </div>

        <!-- Panel Identifikasi -->
        <div class="card border-0 shadow-sm rounded-4 mb-3" style="background:#1e1e2d;">
            <div class="card-body p-4 text-white">
                <h6 class="text-white-50 fw-bold text-uppercase mb-4" style="font-size:0.7rem;letter-spacing:1.5px;">Hasil Identifikasi</h6>
                <div class="text-center mb-3">
                    <div id="ai-face-icon" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2" style="width:64px;height:64px;border:2px solid rgba(255,255,255,0.1);">
                        <i class="bi bi-person text-white-50" style="font-size:1.8rem;"></i>
                    </div>
                    <h5 id="result-nama" class="fw-bold text-white mb-1">-</h5>
                    <div id="result-badge" class="badge bg-secondary px-3 py-2">Menunggu Liveness</div>
                </div>
                <div class="mb-0">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Kecocokan Wajah</small>
                        <small id="confidence-persen" class="fw-bold text-white">-</small>
                    </div>
                    <div class="progress rounded-pill" style="height:9px;background:rgba(255,255,255,0.1);">
                        <div id="confidence-bar" class="progress-bar rounded-pill" style="width:0%;transition:width 0.4s;"></div>
                    </div>
                </div>
                <!-- TOMBOL SELESAIKAN ABSEN DI SINI -->
                <div class="mt-4" id="action-area-identifikasi">
                    <button id="btn-selesai-absen" class="btn btn-<?= $warna_absen ?> btn-lg w-100 rounded-pill shadow fw-bold" style="display:none;" disabled>
                        <i class="bi bi-check-circle me-2"></i> Konfirmasi dan Simpan Absensi
                    </button>
                </div>
            </div>
        </div>

        <!-- Status Dots -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-3">
                <div class="d-flex align-items-center mb-2"><span id="dot-kamera" class="rounded-circle me-2 flex-shrink-0" style="width:8px;height:8px;background:#555;"></span><small id="info-kamera">Kamera: Menunggu</small></div>
                <div class="d-flex align-items-center mb-2"><span id="dot-model" class="rounded-circle me-2 flex-shrink-0" style="width:8px;height:8px;background:#555;"></span><small id="info-model">Model AI: Belum Dimuat</small></div>
                <div class="d-flex align-items-center mb-2"><span id="dot-deteksi" class="rounded-circle me-2 flex-shrink-0" style="width:8px;height:8px;background:#555;"></span><small id="info-deteksi">Deteksi Wajah: Menunggu</small></div>
                <div class="d-flex align-items-center mb-2"><span id="dot-liveness" class="rounded-circle me-2 flex-shrink-0" style="width:8px;height:8px;background:#555;"></span><small id="info-liveness">Liveness: Menunggu</small></div>
                <div class="d-flex align-items-center"><span id="dot-match" class="rounded-circle me-2 flex-shrink-0" style="width:8px;height:8px;background:#555;"></span><small id="info-match">Identifikasi: Menunggu</small></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js"></script>

<script>
// ============================================================
// JAM REALTIME
// ============================================================
setInterval(() => {
    const now = new Date();
    const el = document.getElementById('jam-realtime');
    if(el) el.textContent = [String(now.getHours()).padStart(2,'0'), String(now.getMinutes()).padStart(2,'0'), String(now.getSeconds()).padStart(2,'0')].join(':');
}, 1000);

<?php if ($jenis_absen != 'selesai' && $face_sudah_terdaftar): ?>

// ============================================================
// KONSTANTA & KONFIGURASI
// ============================================================
const MODEL_URL          = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model';
const THRESHOLD_DISTANCE = 0.50;   // Jarak maksimal Face Recognition
const LIVENESS_TIMEOUT   = 12;     // Detik untuk menyelesaikan challenge
const LOGGED_IN_GURU_ID  = <?= $id_guru ?>;

// Daftar Challenge Liveness
const CHALLENGES = [
    { id: 'blink',      text: 'Silakan kedipkan mata',   icon: 'bi-eye-slash', hint: 'Kedipkan mata Anda' },
    { id: 'turn_left',  text: 'Silakan menoleh ke kiri',  icon: 'bi-arrow-left-circle', hint: 'Menoleh perlahan ke kiri' },
    { id: 'turn_right', text: 'Silakan menoleh ke kanan', icon: 'bi-arrow-right-circle', hint: 'Menoleh perlahan ke kanan' },
    { id: 'open_mouth', text: 'Silakan buka mulut',       icon: 'bi-chat-square', hint: 'Buka mulut secara wajar' },
];

// ============================================================
// STATE MACHINE
// PHASES: 'INIT' → 'LOADING' → 'FACE_DETECTION' → 'LIVENESS' → 'RECOGNITION' → 'CAPTURE_READY'
// ============================================================
let phase = 'INIT';
let stream = null;
let faceMatcher = null;
let detectionLoopId = null;
let fdLoopId = null;          // Loop khusus face detection
let currentFaceCount = 0;    // Jumlah wajah saat ini
let liveness = {
    challenge: null,
    passed: false,
    timerCount: LIVENESS_TIMEOUT,
    timerInterval: null,
    blinkWasOpen: false,
    mouthWasClosed: false,
};
let lastMatchResult = null;

// DOM — Kamera & Overlay
const video           = document.getElementById('video-stream');
const canvasOverlay   = document.getElementById('canvas-overlay');
const canvasSnapshot  = document.getElementById('canvas-snapshot');
const photoPreview    = document.getElementById('photo-preview');
const placeholder     = document.getElementById('camera-placeholder');
const aiLoadingOverlay = document.getElementById('ai-loading-overlay');
const loadingText     = document.getElementById('loading-text');
const challengeOverlay = document.getElementById('challenge-overlay');
const challengeText   = document.getElementById('challenge-text');
const challengeTimerEl = document.getElementById('challenge-timer');
const livenessProgressBar = document.getElementById('liveness-progress-bar');

// DOM — Face Detection Badge & Panel
const badgeFaceDetection = document.getElementById('badge-face-detection');
const badgeFdIcon        = document.getElementById('badge-fd-icon');
const badgeFdLabel       = document.getElementById('badge-fd-label');
const badgeFaceCount     = document.getElementById('badge-face-count');
const badgeFcLabel       = document.getElementById('badge-fc-label');
const fdPanelStatus      = document.getElementById('fd-panel-status');
const fdIconEl           = document.getElementById('fd-icon');
const fdFaceCountBadge   = document.getElementById('fd-face-count-badge');
const fdCountSpan        = document.getElementById('fd-count-span');
const fdInfoBar          = document.getElementById('fd-info-bar');
const fdDot              = document.getElementById('fd-dot');
const fdInfoText         = document.getElementById('fd-info-text');
const fdStatusBar        = document.getElementById('fd-status-bar');
const fdStatusMsg        = document.getElementById('fd-status-msg');

// DOM — Tombol
const btnAktifkan      = document.getElementById('btn-aktifkan');
const btnLanjutLiveness = document.getElementById('btn-lanjut-liveness');
const btnUlangiLiveness = document.getElementById('btn-ulangi-liveness');
const btnSelesaiAbsen  = document.getElementById('btn-selesai-absen');
const formAbsensi      = document.getElementById('form-absensi');
const foto64Input      = document.getElementById('foto_base64');
const verifiedGuruId   = document.getElementById('verified_guru_id');

// DOM — Status dots
const dotKamera  = document.getElementById('dot-kamera');
const dotModel   = document.getElementById('dot-model');
const dotDeteksi = document.getElementById('dot-deteksi');
const dotLiveness = document.getElementById('dot-liveness');
const dotMatch   = document.getElementById('dot-match');
const infoKamera  = document.getElementById('info-kamera');
const infoModel   = document.getElementById('info-model');
const infoDeteksi = document.getElementById('info-deteksi');
const infoLiveness = document.getElementById('info-liveness');
const infoMatch   = document.getElementById('info-match');

// DOM — Panel identifikasi
const resultNama      = document.getElementById('result-nama');
const resultBadge     = document.getElementById('result-badge');
const confidencePersen = document.getElementById('confidence-persen');
const confidenceBar   = document.getElementById('confidence-bar');
const livenessStatusText   = document.getElementById('liveness-status-text');
const livenessSuccessDetail = document.getElementById('liveness-success-detail');

// Step icons (5 steps now)
const stepIcons = [1,2,3,4,5].map(i => document.getElementById(`step-icon-${i}`));
const stepLabels = [1,2,3,4,5].map(i => document.getElementById(`step-label-${i}`));
const stepLines = [1,2,3,4].map(i => document.getElementById(`step-line-${i}`));

function setDot(el, color) { if(el){ el.style.backgroundColor = color; el.style.boxShadow = `0 0 6px ${color}`; } }

function setStep(active) {
    // active: 0=Kamera, 1=Deteksi, 2=Liveness, 3=Identifikasi, 4=Absensi
    const colors = ['#0d6efd', '#6f42c1', '#ffc107', '#20c997', '#fd7e14'];
    stepIcons.forEach((icon, i) => {
        if(!icon) return;
        if(i < active) {
            icon.style.background = colors[i] || '#20c997';
            icon.style.boxShadow = '';
        } else if(i === active) {
            icon.style.background = colors[i];
            icon.style.boxShadow = `0 0 14px ${colors[i]}99`;
        } else {
            icon.style.background = '#6c757d';
            icon.style.boxShadow = '';
        }
    });
    stepLines.forEach((line, i) => {
        if(line) line.style.opacity = i < active ? '1' : '0.2';
    });
}

// ============================================================
// FACE DETECTION — Update Panel & Badge UI
// ============================================================
function updateFaceDetectionUI(count) {
    currentFaceCount = count;
    badgeFaceCount.style.display = 'block';
    badgeFaceDetection.style.display = 'block';
    fdFaceCountBadge.style.display = 'block';
    fdInfoBar.style.display = 'block';
    fdStatusBar.style.display = 'block';

    // Update badge jumlah wajah
    badgeFcLabel.textContent = count + (count === 1 ? ' Wajah' : ' Wajah');

    if(count === 0) {
        // — Tidak ada wajah
        badgeFdIcon.className = 'bi bi-x-circle-fill me-1 text-danger';
        badgeFdLabel.textContent = 'Wajah Tidak Terdeteksi';
        badgeFdLabel.style.color = '#ff6b6b';

        fdPanelStatus.textContent = 'Wajah Tidak Terdeteksi';
        fdPanelStatus.style.color = '#ff6b6b';
        fdIconEl.style.border = '2px solid rgba(220,53,69,0.5)';
        fdIconEl.innerHTML = '<i class="bi bi-person-x text-danger" style="font-size:1.8rem;"></i>';
        fdCountSpan.textContent = '0 wajah terdeteksi';
        fdCountSpan.className = 'badge bg-danger px-3 py-1';
        fdDot.style.background = '#dc3545';
        fdDot.style.boxShadow = '0 0 6px #dc3545';
        fdInfoText.textContent = 'Posisikan wajah Anda ke kamera';
        fdInfoText.style.color = '#ff6b6b';

        fdStatusMsg.textContent = '⚠ Wajah tidak terdeteksi. Posisikan wajah Anda dengan jelas ke kamera.';
        fdStatusMsg.style.color = '#dc3545';

        // Nonaktifkan tombol lanjut
        btnLanjutLiveness.disabled = true;
        btnLanjutLiveness.classList.remove('btn-success');
        btnLanjutLiveness.classList.add('btn-secondary');

        setDot(dotDeteksi, '#dc3545');
        infoDeteksi.textContent = 'Deteksi Wajah: Tidak Ada';

    } else if(count === 1) {
        // — Tepat 1 wajah
        badgeFdIcon.className = 'bi bi-check-circle-fill me-1 text-success';
        badgeFdLabel.textContent = 'Wajah Terdeteksi';
        badgeFdLabel.style.color = '#51cf66';

        fdPanelStatus.textContent = 'Wajah Terdeteksi ✓';
        fdPanelStatus.style.color = '#51cf66';
        fdIconEl.style.border = '2px solid rgba(32,201,151,0.6)';
        fdIconEl.style.boxShadow = '0 0 18px rgba(32,201,151,0.3)';
        fdIconEl.innerHTML = '<i class="bi bi-person-check-fill text-success" style="font-size:1.8rem;"></i>';
        fdCountSpan.textContent = '1 wajah terdeteksi';
        fdCountSpan.className = 'badge bg-success px-3 py-1';
        fdDot.style.background = '#20c997';
        fdDot.style.boxShadow = '0 0 6px #20c997';
        fdInfoText.textContent = 'Wajah terdeteksi dengan baik';
        fdInfoText.style.color = '#20c997';

        fdStatusMsg.textContent = '✓ Wajah terdeteksi. Klik "Lanjut ke Verifikasi" untuk melanjutkan.';
        fdStatusMsg.style.color = '#20c997';

        // Aktifkan tombol lanjut
        btnLanjutLiveness.disabled = false;
        btnLanjutLiveness.classList.remove('btn-secondary');
        btnLanjutLiveness.classList.add('btn-success');

        setDot(dotDeteksi, '#20c997');
        infoDeteksi.textContent = 'Deteksi Wajah: Terdeteksi ✓';

    } else {
        // — Lebih dari 1 wajah
        badgeFdIcon.className = 'bi bi-exclamation-triangle-fill me-1 text-warning';
        badgeFdLabel.textContent = `Lebih dari satu wajah terdeteksi (${count})`;
        badgeFdLabel.style.color = '#ffd43b';

        fdPanelStatus.textContent = `Lebih dari satu wajah terdeteksi`;
        fdPanelStatus.style.color = '#ffd43b';
        fdIconEl.style.border = '2px solid rgba(255,193,7,0.5)';
        fdIconEl.style.boxShadow = '0 0 18px rgba(255,193,7,0.2)';
        fdIconEl.innerHTML = `<i class="bi bi-people-fill text-warning" style="font-size:1.8rem;"></i>`;
        fdCountSpan.textContent = count + ' wajah terdeteksi';
        fdCountSpan.className = 'badge bg-warning text-dark px-3 py-1';
        fdDot.style.background = '#ffc107';
        fdDot.style.boxShadow = '0 0 6px #ffc107';
        fdInfoText.textContent = `${count} wajah — hanya boleh 1`;
        fdInfoText.style.color = '#ffd43b';

        fdStatusMsg.textContent = `⚠ Terdeteksi ${count} wajah. Pastikan hanya Anda yang ada di depan kamera.`;
        fdStatusMsg.style.color = '#ffc107';

        // Nonaktifkan tombol lanjut
        btnLanjutLiveness.disabled = true;
        btnLanjutLiveness.classList.remove('btn-success');
        btnLanjutLiveness.classList.add('btn-secondary');

        setDot(dotDeteksi, '#ffc107');
        infoDeteksi.textContent = `Deteksi Wajah: ${count} Wajah (Lebih dari 1)`;
    }
}

// ============================================================
// FACE DETECTION LOOP — Phase FACE_DETECTION
// ============================================================
async function startFaceDetectionLoop() {
    phase = 'FACE_DETECTION';
    setStep(1); // Step 2 = Deteksi

    // Inisialisasi UI
    badgeFaceDetection.style.display = 'block';
    badgeFaceCount.style.display = 'block';
    badgeFdIcon.className = 'bi bi-hourglass-split me-1';
    badgeFdLabel.textContent = 'Mendeteksi Wajah...';
    badgeFdLabel.style.color = '#adb5bd';
    fdPanelStatus.textContent = 'Mendeteksi Wajah...';
    fdPanelStatus.style.color = '#adb5bd';
    fdInfoBar.style.display = 'block';
    fdStatusBar.style.display = 'block';
    fdStatusMsg.textContent = 'Sedang mendeteksi wajah di depan kamera...';
    fdStatusMsg.style.color = '#adb5bd';
    btnLanjutLiveness.style.display = 'inline-block';
    btnLanjutLiveness.disabled = true;

    const displaySize = { width: video.videoWidth, height: video.videoHeight };
    faceapi.matchDimensions(canvasOverlay, displaySize);

    fdLoopId = setInterval(async () => {
        if(phase !== 'FACE_DETECTION') { clearInterval(fdLoopId); return; }
        if(video.paused || video.ended || !stream) return;
        try {
            const detections = await faceapi
                .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.55 }))
                .withFaceLandmarks(true);

            const resized = faceapi.resizeResults(detections, displaySize);
            const ctx = canvasOverlay.getContext('2d');
            ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);

            const count = resized.length;
            updateFaceDetectionUI(count);

            // Gambar bounding box
            resized.forEach((det, idx) => {
                const box = det.detection.box;
                let boxColor;
                if(count === 1) boxColor = '#20c997';
                else if(count === 0) boxColor = '#dc3545';
                else boxColor = (idx === 0) ? '#ffc107' : '#dc3545';

                ctx.strokeStyle = boxColor;
                ctx.lineWidth = 2.5;
                ctx.shadowColor = boxColor;
                ctx.shadowBlur = 10;
                ctx.strokeRect(box.x, box.y, box.width, box.height);

                // Landmark dots (hanya jika 1 wajah)
                if(count === 1) {
                    ctx.fillStyle = 'rgba(32,201,151,0.55)';
                    det.landmarks.positions.forEach(pt => {
                        ctx.beginPath(); ctx.arc(pt.x, pt.y, 1.8, 0, 2*Math.PI); ctx.fill();
                    });
                }

                // Label jumlah
                ctx.shadowBlur = 0;
                ctx.fillStyle = boxColor;
                ctx.font = 'bold 12px Inter, sans-serif';
                const label = count > 1 ? `Wajah ${idx+1}` : 'Wajah Terdeteksi';
                ctx.fillText(label, box.x + 4, box.y > 18 ? box.y - 5 : box.y + 16);
            });
        } catch(e) { console.warn('FD loop error:', e); }
    }, 250);
}

// ============================================================
// HELPER: KALKULASI LANDMARK
// ============================================================
function dist(p1, p2) {
    return Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
}

// Eye Aspect Ratio (EAR) – menggunakan indeks 68-point standard
function calcEAR(positions, eyeIndices) {
    const [p1, p2, p3, p4, p5, p6] = eyeIndices.map(i => positions[i]);
    return (dist(p2, p6) + dist(p3, p5)) / (2.0 * dist(p1, p4));
}

// Mouth Aspect Ratio (MAR)
function calcMAR(positions) {
    // Outer mouth: 48-59 | Inner: 60-67
    const top    = positions[51]; // titik atas tengah dalam
    const bottom = positions[57]; // titik bawah tengah dalam
    const left   = positions[48]; // sudut kiri
    const right  = positions[54]; // sudut kanan
    return dist(top, bottom) / dist(left, right);
}

// Head Yaw (Left/Right) menggunakan posisi hidung relatif ke kotak wajah
function calcHeadYaw(positions, box) {
    const noseTip = positions[30]; // tip hidung
    const faceCenter = box.x + box.width / 2;
    return (noseTip.x - faceCenter) / box.width; // positif = kanan gambar (kiri user karena mirror)
}

// ============================================================
// LIVENESS: CEK CHALLENGE
// ============================================================
// LIVENESS: CEK CHALLENGE
// ============================================================
function checkChallenge(landmarks, box) {
    const pos = landmarks.positions;
    const ch = liveness.challenge.id;

    if(ch === 'blink') {
        const leftEAR  = calcEAR(pos, [36,37,38,39,40,41]);
        const rightEAR = calcEAR(pos, [42,43,44,45,46,47]);
        const avgEAR   = (leftEAR + rightEAR) / 2;

        // Transisi: mata terbuka → kedip menutup
        if(avgEAR > 0.22) liveness.blinkWasOpen = true;
        if(liveness.blinkWasOpen && avgEAR < 0.185) {
            return true; // mata berkedip terdeteksi
        }
        return false;
    }

    if(ch === 'turn_left') {
        // Toleh kiri user: pada video mirror, hidung bergerak ke kanan gambar
        const yaw = calcHeadYaw(pos, box);
        const noseTip = pos[30];
        const leftJaw = pos[0];
        const rightJaw = pos[16];
        const ratio = dist(noseTip, leftJaw) / (dist(noseTip, rightJaw) + 0.001);
        if(yaw > 0.075 || ratio > 1.3) {
            liveness.holdCount = (liveness.holdCount || 0) + 1;
            if(liveness.holdCount >= 2) return true;
        }
        return false;
    }

    if(ch === 'turn_right') {
        // Toleh kanan user: hidung bergerak ke kiri gambar
        const yaw = calcHeadYaw(pos, box);
        const noseTip = pos[30];
        const leftJaw = pos[0];
        const rightJaw = pos[16];
        const ratio = dist(noseTip, rightJaw) / (dist(noseTip, leftJaw) + 0.001);
        if(yaw < -0.075 || ratio > 1.3) {
            liveness.holdCount = (liveness.holdCount || 0) + 1;
            if(liveness.holdCount >= 2) return true;
        }
        return false;
    }

    if(ch === 'open_mouth') {
        const mar = calcMAR(pos);
        if(mar < 0.32) liveness.mouthWasClosed = true;
        if(liveness.mouthWasClosed && mar > 0.40) {
            liveness.holdCount = (liveness.holdCount || 0) + 1;
            if(liveness.holdCount >= 2) return true; // mulut terbuka terdeteksi
        }
        return false;
    }

    return false;
}

// ============================================================
// MULAI CHALLENGE LIVENESS
// ============================================================
function startLivenessChallenge() {
    phase = 'LIVENESS';
    setStep(2); // Step 3 = Liveness (0-indexed: 0=Kamera,1=Deteksi,2=Liveness,...)

    // Pilih salah satu challenge secara acak
    const picked = CHALLENGES[Math.floor(Math.random() * CHALLENGES.length)];
    liveness.challenge = picked;
    liveness.passed = false;
    liveness.blinkWasOpen = false;
    liveness.mouthWasClosed = false;
    liveness.holdCount = 0;
    liveness.timerCount = LIVENESS_TIMEOUT;

    challengeText.textContent = picked.text;
    challengeOverlay.style.display = 'block';
    livenessProgressBar.style.width = '0%';
    challengeTimerEl.textContent = LIVENESS_TIMEOUT;

    setDot(dotLiveness, '#ffc107');
    infoLiveness.textContent = `Liveness: ${picked.text}...`;
    livenessStatusText.textContent = `${picked.text}`;
    document.getElementById('liveness-icon').innerHTML = `<i class="bi ${picked.icon} text-warning" style="font-size:1.8rem;"></i>`;

    // Countdown timer
    clearInterval(liveness.timerInterval);
    liveness.timerInterval = setInterval(() => {
        liveness.timerCount--;
        challengeTimerEl.textContent = liveness.timerCount;
        const elapsed = LIVENESS_TIMEOUT - liveness.timerCount;
        livenessProgressBar.style.width = ((elapsed / LIVENESS_TIMEOUT) * 100) + '%';

        if(liveness.timerCount <= 0) onLivenessFailed();
    }, 1000);
}

function onLivenessSuccess() {
    clearInterval(liveness.timerInterval);
    liveness.passed = true;
    phase = 'RECOGNITION';
    setStep(3); // Step 4 = Identifikasi

    // UI Liveness berhasil
    challengeOverlay.style.display = 'none';
    livenessProgressBar.style.width = '100%';
    livenessProgressBar.className = 'progress-bar bg-success rounded-pill';
    livenessSuccessDetail.style.display = 'block';
    livenessStatusText.textContent = 'Liveness berhasil';
    document.getElementById('liveness-icon').innerHTML = `<i class="bi bi-check-circle-fill text-success" style="font-size:1.8rem;"></i>`;
    setDot(dotLiveness, '#20c997');
    infoLiveness.textContent = 'Liveness: Berhasil ✓';

    // Mulai recognition setelah 400ms
    setTimeout(() => {
        resultBadge.textContent = 'Mengidentifikasi Wajah...';
        resultBadge.className = 'badge bg-info px-3 py-2';
    }, 400);
}

function onLivenessFailed() {
    clearInterval(liveness.timerInterval);
    phase = 'LIVENESS_FAILED';

    challengeOverlay.style.display = 'none';
    livenessProgressBar.className = 'progress-bar bg-danger rounded-pill';
    livenessProgressBar.style.width = '100%';
    livenessStatusText.textContent = 'Verifikasi liveness gagal, silakan ulangi.';
    document.getElementById('liveness-icon').innerHTML = `<i class="bi bi-x-circle-fill text-danger" style="font-size:1.8rem;"></i>`;
    setDot(dotLiveness, '#dc3545');
    infoLiveness.textContent = 'Liveness: Gagal / Timeout';

    btnUlangiLiveness.style.display = 'inline-block';
}

// ============================================================
// LOAD MODEL + FACE DATA
// ============================================================
async function loadModelsAndFaceData() {
    aiLoadingOverlay.style.display = 'flex';
    loadingText.textContent = 'Memuat Model AI...';
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        setDot(dotModel, '#20c997');
        infoModel.textContent = 'Model AI: Siap';
    } catch(e) {
        aiLoadingOverlay.style.display = 'none';
        setDot(dotModel, '#dc3545');
        infoModel.textContent = 'Model AI: Gagal';
        return false;
    }

    loadingText.textContent = 'Memuat Data Wajah...';
    try {
        const resp = await fetch('get_face_data.php');
        const json = await resp.json();
        if(json.total === 0) {
            aiLoadingOverlay.style.display = 'none';
            return false;
        }
        const labeled = json.data.map(g => {
            const descs = g.descriptors.map(d => new Float32Array(d));
            return new faceapi.LabeledFaceDescriptors(`${g.id_guru}::${g.nama_guru}`, descs);
        });
        faceMatcher = new faceapi.FaceMatcher(labeled, THRESHOLD_DISTANCE);
        infoModel.textContent = `Model AI: Siap (${json.total} guru)`;
        aiLoadingOverlay.style.display = 'none';
        return true;
    } catch(e) {
        aiLoadingOverlay.style.display = 'none';
        return false;
    }
}

// ============================================================
// UPDATE PANEL IDENTIFIKASI
// ============================================================
function updateIdentificationPanel(match, distance) {
    if(!match || match.label === 'unknown') {
        resultNama.textContent = 'Tidak Dikenali';
        resultBadge.textContent = 'Wajah Tidak Dikenali';
        resultBadge.className = 'badge bg-danger px-3 py-2';
        confidencePersen.textContent = '-';
        confidenceBar.style.width = '0%';
        confidenceBar.className = 'progress-bar rounded-pill bg-danger';
        setDot(dotMatch, '#dc3545');
        infoMatch.textContent = 'Identifikasi: Tidak Dikenali';
        btnSelesaiAbsen.style.display = 'block';
        btnSelesaiAbsen.disabled = true;
        lastMatchResult = null;
        return;
    }

    const parts = match.label.split('::');
    const matchedId = parseInt(parts[0]);
    const matchedNama = parts[1];
    const confidence = Math.round(Math.max(0, (1 - distance / THRESHOLD_DISTANCE)) * 100);
    const isMatchSession = matchedId === LOGGED_IN_GURU_ID;

    resultNama.textContent = matchedNama;
    confidencePersen.textContent = confidence + '%';
    confidenceBar.style.width = confidence + '%';

    if(isMatchSession && confidence >= 30) {
        resultBadge.textContent = `✓ Wajah Terverifikasi`;
        resultBadge.className = 'badge bg-success px-3 py-2';
        confidenceBar.className = `progress-bar rounded-pill ${confidence >= 60 ? 'bg-success' : 'bg-warning'}`;
        setDot(dotMatch, '#20c997');
        infoMatch.textContent = 'Identifikasi: Terverifikasi ✓';
        verifiedGuruId.value = matchedId;
        btnSelesaiAbsen.style.display = 'block';
        btnSelesaiAbsen.disabled = false;
        lastMatchResult = { guruId: matchedId, nama: matchedNama, confidence };
        setStep(4); // Step 5 = Absensi siap
    } else {
        const msg = isMatchSession ? 'Keyakinan Rendah' : 'Identitas Tidak Sesuai';
        resultBadge.textContent = msg;
        resultBadge.className = 'badge bg-warning text-dark px-3 py-2';
        confidenceBar.className = 'progress-bar rounded-pill bg-warning';
        setDot(dotMatch, '#ffc107');
        infoMatch.textContent = `Identifikasi: ${msg}`;
        btnSelesaiAbsen.style.display = 'block';
        btnSelesaiAbsen.disabled = true;
        lastMatchResult = null;
    }
}

// ============================================================
// DETECTION LOOP UTAMA
// ============================================================
let isMainLoopBusy = false;

async function startMainLoop() {
    if(!faceMatcher) return;
    const displaySize = { width: video.videoWidth, height: video.videoHeight };
    faceapi.matchDimensions(canvasOverlay, displaySize);

    clearInterval(detectionLoopId);
    detectionLoopId = setInterval(async () => {
        if(isMainLoopBusy) return;
        if(video.paused || video.ended || !stream) return;
        isMainLoopBusy = true;

        try {
            const ctx = canvasOverlay.getContext('2d');

            // -------- JIKA FASE LIVENESS (Ringan & Cepat, Tanpa Descriptors) --------
            if(phase === 'LIVENESS' && !liveness.passed) {
                const detections = await faceapi
                    .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.55 }))
                    .withFaceLandmarks(true);

                const resized = faceapi.resizeResults(detections, displaySize);
                ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);

                if(resized.length !== 1) {
                    if(resized.length === 0) {
                        livenessStatusText.textContent = 'Wajah tidak terdeteksi. Posisikan kembali.';
                    } else {
                        livenessStatusText.textContent = 'Lebih dari 1 wajah. Pastikan hanya Anda.';
                    }
                    return;
                }

                const det = resized[0];
                const box = det.detection.box;
                const landmarks = det.landmarks;

                // Cek challenge liveness
                const success = checkChallenge(landmarks, box);

                // Gambar visual panduan di canvas
                ctx.strokeStyle = success ? '#20c997' : '#ffc107';
                ctx.lineWidth = 3;
                ctx.shadowColor = success ? '#20c997' : '#ffc107';
                ctx.shadowBlur = 12;
                ctx.strokeRect(box.x, box.y, box.width, box.height);

                // Landmark dots
                ctx.fillStyle = success ? 'rgba(32,201,151,0.75)' : 'rgba(255,193,7,0.6)';
                landmarks.positions.forEach(pt => {
                    ctx.beginPath(); ctx.arc(pt.x, pt.y, 2, 0, 2*Math.PI); ctx.fill();
                });

                if(success) onLivenessSuccess();
                return;
            }

            // -------- JIKA FASE RECOGNITION (Liveness Sudah Berhasil) --------
            if(phase === 'RECOGNITION' && liveness.passed) {
                const detections = await faceapi
                    .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.55 }))
                    .withFaceLandmarks(true)
                    .withFaceDescriptors();

                const resized = faceapi.resizeResults(detections, displaySize);
                ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);

                if(resized.length !== 1) {
                    return;
                }

                const det = resized[0];
                const box = det.detection.box;
                const landmarks = det.landmarks;

                const match = faceMatcher.findBestMatch(det.descriptor);
                const distance = match.distance;
                const matchedId = match.label !== 'unknown' ? parseInt(match.label.split('::')[0]) : -1;
                const isMatch = matchedId === LOGGED_IN_GURU_ID;

                // Warna kotak
                const boxColor = match.label === 'unknown' ? '#dc3545' : (isMatch ? '#20c997' : '#ffc107');
                ctx.strokeStyle = boxColor;
                ctx.lineWidth = 3;
                ctx.shadowColor = boxColor;
                ctx.shadowBlur = 12;
                ctx.strokeRect(box.x, box.y, box.width, box.height);

                // Landmark
                ctx.fillStyle = `${boxColor}77`;
                landmarks.positions.forEach(pt => {
                    ctx.beginPath(); ctx.arc(pt.x, pt.y, 2, 0, 2*Math.PI); ctx.fill();
                });

                // Label atas kotak
                const conf = match.label !== 'unknown' ? Math.round(Math.max(0,(1-distance/THRESHOLD_DISTANCE))*100) : 0;
                const namePart = match.label !== 'unknown' ? match.label.split('::')[1].split(' ')[0] : '???';
                ctx.fillStyle = boxColor; ctx.shadowBlur = 0;
                ctx.font = 'bold 13px Inter, sans-serif';
                ctx.fillText(`${namePart} (${conf}%)`, box.x+4, box.y > 20 ? box.y-6 : box.y+18);

                updateIdentificationPanel(match, distance);
                return;
            }

            // -------- JIKA FASE LIVENESS GAGAL --------
            if(phase === 'LIVENESS_FAILED') {
                const detections = await faceapi
                    .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.55 }));
                const resized = faceapi.resizeResults(detections, displaySize);
                ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);
                if(resized.length > 0) {
                    const box = resized[0].box;
                    ctx.strokeStyle = '#dc3545';
                    ctx.lineWidth = 3;
                    ctx.shadowColor = '#dc3545';
                    ctx.shadowBlur = 8;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);
                }
            }

        } catch(e) { console.warn('Main loop error:', e); }
        finally { isMainLoopBusy = false; }
    }, 150);
}

// ============================================================
// TOMBOL AKTIFKAN KAMERA
// ============================================================
btnAktifkan.addEventListener('click', async () => {
    btnAktifkan.disabled = true;
    phase = 'LOADING';
    setStep(0);

    // Update status kamera
    fdPanelStatus.textContent = 'Kamera Aktif';
    fdPanelStatus.style.color = '#74c0fc';
    fdInfoBar.style.display = 'block';
    fdInfoText.textContent = 'Memuat model AI...';
    fdInfoText.style.color = '#adb5bd';

    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 }, audio: false });
        video.srcObject = stream;
        await video.play();
        placeholder.style.display = 'none';
        video.style.display = 'block';
        canvasOverlay.style.display = 'block';
        btnAktifkan.style.display = 'none';
        setDot(dotKamera, '#20c997');
        infoKamera.textContent = 'Kamera: Aktif';

        // Muat model AI (detector + landmark + recognizer)
        const ok = await loadModelsAndFaceData();
        if(!ok) {
            infoModel.textContent = 'Model AI: Gagal';
            fdPanelStatus.textContent = 'Gagal memuat model';
            fdPanelStatus.style.color = '#ff6b6b';
            return;
        }

        // ✅ Setelah model siap → masuk fase FACE DETECTION dulu
        await startFaceDetectionLoop();

    } catch(err) {
        btnAktifkan.disabled = false;
        let msg = err.name === 'NotAllowedError' ? 'Izin ditolak.' : 'Kamera tidak ditemukan.';
        setDot(dotKamera, '#dc3545');
        infoKamera.textContent = 'Kamera: ' + msg;
        fdPanelStatus.textContent = 'Kamera gagal dibuka';
        fdPanelStatus.style.color = '#ff6b6b';
    }
});

// ============================================================
// TOMBOL LANJUT KE LIVENESS (setelah 1 wajah terdeteksi)
// ============================================================
btnLanjutLiveness.addEventListener('click', () => {
    if(currentFaceCount !== 1) return; // Guard: hanya lanjut jika tepat 1 wajah

    // Hentikan face detection loop
    clearInterval(fdLoopId);
    phase = 'PRE_LIVENESS'; // sementara sebelum mulai loop utama

    // Update UI face detection panel
    fdPanelStatus.textContent = 'Wajah Terverifikasi ✓';
    fdPanelStatus.style.color = '#51cf66';
    fdInfoText.textContent = 'Lanjut ke verifikasi liveness';
    fdInfoText.style.color = '#51cf66';
    fdStatusMsg.textContent = '✓ Wajah terdeteksi. Memulai verifikasi liveness...';
    fdStatusMsg.style.color = '#20c997';
    setDot(dotDeteksi, '#20c997');
    infoDeteksi.textContent = 'Deteksi Wajah: Selesai ✓';

    // Sembunyikan tombol lanjut, tampilkan area liveness
    btnLanjutLiveness.style.display = 'none';
    fdStatusBar.style.display = 'none';

    // Mulai detection loop utama (untuk liveness + recognition)
    startMainLoop().then(() => {
        startLivenessChallenge();
    });
});

// ============================================================
// TOMBOL ULANGI LIVENESS
// ============================================================
btnUlangiLiveness.addEventListener('click', () => {
    btnUlangiLiveness.style.display = 'none';
    livenessSuccessDetail.style.display = 'none';
    livenessProgressBar.className = 'progress-bar bg-warning rounded-pill progress-bar-striped progress-bar-animated';
    resultNama.textContent = '-';
    resultBadge.textContent = 'Menunggu Liveness';
    resultBadge.className = 'badge bg-secondary px-3 py-2';
    confidencePersen.textContent = '-';
    confidenceBar.style.width = '0%';
    btnSelesaiAbsen.style.display = 'none';
    btnSelesaiAbsen.disabled = true;
    lastMatchResult = null;
    infoMatch.textContent = 'Identifikasi: Menunggu';
    setDot(dotMatch, '#555');
    setDot(dotLiveness, '#555');
    startLivenessChallenge();
});

// ============================================================
// TOMBOL SELESAI ABSEN (Konfirmasi dan Simpan)
// ============================================================
btnSelesaiAbsen.addEventListener('click', () => {
    if(!lastMatchResult) return;
    
    // Hentikan proses deteksi dan liveness
    clearInterval(detectionLoopId);
    clearInterval(liveness.timerInterval);
    
    // Ambil foto snapshot
    canvasSnapshot.width = video.videoWidth;
    canvasSnapshot.height = video.videoHeight;
    const ctxSnap = canvasSnapshot.getContext('2d');
    ctxSnap.translate(canvasSnapshot.width, 0);
    ctxSnap.scale(-1, 1);
    ctxSnap.drawImage(video, 0, 0);

    const dataUrl = canvasSnapshot.toDataURL('image/png');
    foto64Input.value = dataUrl;

    // Matikan kamera
    if(stream) stream.getTracks().forEach(t => t.stop());

    // Update UI loading
    btnSelesaiAbsen.disabled = true;
    btnSelesaiAbsen.innerHTML = "<span class='spinner-border spinner-border-sm me-2'></span>Menyimpan Absensi...";
    
    // Submit form secara terprogram
    formAbsensi.submit();
});

// ============================================================
// CLEANUP
// ============================================================
window.addEventListener('beforeunload', () => {
    clearInterval(fdLoopId);
    clearInterval(detectionLoopId);
    clearInterval(liveness.timerInterval);
    if(stream) stream.getTracks().forEach(t => t.stop());
});

<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
