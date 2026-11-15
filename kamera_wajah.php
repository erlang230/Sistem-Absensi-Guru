<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: registrasi_wajah.php"); exit;
}

$id_guru = intval($_GET['id']);
$stmt = mysqli_prepare($conn, "SELECT g.*, u.username FROM guru g JOIN users u ON g.id_user = u.id WHERE g.id_guru = ?");
mysqli_stmt_bind_param($stmt, "i", $id_guru);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($result) === 0) {
    $_SESSION['Apakah implementasi widget chatbot SAG sudah selesai dan semua perubahan sudah disimpan? Tolong sebutkan file apa saja yang dibuat atau diubah, lalu jelaskan cara menguji chatbot melalui browser. Jangan mengubah kode lagi dulu.rror'] = "Data guru tidak ditemukan.";
    header("Location: registrasi_wajah.php"); exit;
}
$guru = mysqli_fetch_assoc($result);
$sudah_terdaftar = !empty($guru['face_data']);

// Hitung jumlah sampel existing jika sudah terdaftar
$sampel_lama = 0;
if ($sudah_terdaftar) {
    $fd_existing = json_decode($guru['face_data'], true);
    $sampel_lama = isset($fd_existing['descriptors']) ? count($fd_existing['descriptors']) : 0;
}
?>
<?php include 'includes/header.php'; ?>

<style>
/* ===== REGISTRASI WAJAH - PREMIUM UI ===== */
.rw-header-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}
.rw-header-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
    pointer-events: none;
}

/* Step Indicator */
.step-track {
    display: flex;
    align-items: center;
    gap: 0;
    padding: 0 8px;
}
.step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    flex: 1;
    min-width: 0;
}
.step-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
    background: rgba(255,255,255,0.07);
    border: 2px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.4);
    transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
    position: relative;
    z-index: 2;
}
.step-circle.active {
    background: #6366f1;
    border-color: #818cf8;
    color: #fff;
    box-shadow: 0 0 20px rgba(99,102,241,0.5);
}
.step-circle.done {
    background: #10b981;
    border-color: #34d399;
    color: #fff;
    box-shadow: 0 0 16px rgba(16,185,129,0.4);
}
.step-label {
    font-size: 0.68rem;
    font-weight: 600;
    color: rgba(255,255,255,0.35);
    margin-top: 6px;
    text-align: center;
    letter-spacing: 0.3px;
    transition: color 0.4s;
    white-space: nowrap;
}
.step-label.active { color: #818cf8; }
.step-label.done   { color: #34d399; }
.step-connector {
    flex: 1;
    height: 2px;
    background: rgba(255,255,255,0.08);
    margin: 0 4px;
    margin-bottom: 24px;
    border-radius: 2px;
    transition: background 0.4s;
}
.step-connector.done { background: #10b981; }

/* Camera Box */
.camera-box {
    border-radius: 18px;
    overflow: hidden;
    background: #0a0a14;
    box-shadow: 0 24px 48px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.05);
    position: relative;
    aspect-ratio: 4/3;
    width: 100%;
}

/* Scan line animation */
@keyframes scanline {
    0%   { top: 8%; opacity: 0; }
    5%   { opacity: 1; }
    95%  { opacity: 1; }
    100% { top: 92%; opacity: 0; }
}
.scan-line {
    position: absolute;
    left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(99,102,241,0.8), transparent);
    box-shadow: 0 0 12px rgba(99,102,241,0.6);
    animation: scanline 2.5s ease-in-out infinite;
    pointer-events: none;
    z-index: 8;
    display: none;
}
.scan-line.active { display: block; }

/* Corner guides */
.camera-guide {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: min(55%, 200px);
    aspect-ratio: 1;
    pointer-events: none;
    z-index: 7;
    display: none;
}
.camera-guide.active { display: block; }
.camera-guide::before, .camera-guide::after,
.camera-guide span::before, .camera-guide span::after {
    content: '';
    position: absolute;
    width: 24px;
    height: 24px;
    border-color: rgba(99,102,241,0.9);
    border-style: solid;
}
.camera-guide::before   { top:0; left:0;   border-width: 3px 0 0 3px; border-radius: 4px 0 0 0; }
.camera-guide::after    { top:0; right:0;  border-width: 3px 3px 0 0; border-radius: 0 4px 0 0; }
.camera-guide span::before { bottom:0; left:0;  border-width: 0 0 3px 3px; border-radius: 0 0 0 4px; }
.camera-guide span::after  { bottom:0; right:0; border-width: 0 3px 3px 0; border-radius: 0 0 4px 0; }

/* Sample slots */
.sample-slot {
    border-radius: 12px;
    overflow: hidden;
    background: rgba(255,255,255,0.04);
    border: 2px dashed rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    aspect-ratio: 1;
    transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
    position: relative;
}
.sample-slot:hover { border-color: rgba(99,102,241,0.4); }
.sample-slot.active {
    border-color: #6366f1;
    border-style: solid;
    box-shadow: 0 0 16px rgba(99,102,241,0.25);
    animation: pulseSlot 1.2s ease-in-out infinite;
}
.sample-slot.done {
    border: 2px solid #10b981;
    border-style: solid;
    box-shadow: 0 0 12px rgba(16,185,129,0.3);
    animation: none;
}
@keyframes pulseSlot {
    0%, 100% { box-shadow: 0 0 8px rgba(99,102,241,0.2); }
    50%       { box-shadow: 0 0 24px rgba(99,102,241,0.5); }
}
.sample-slot img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.sample-slot .slot-label {
    font-size: 0.7rem;
    color: rgba(255,255,255,0.3);
    text-align: center;
    padding: 4px;
}
.sample-slot .slot-done-badge {
    position: absolute;
    bottom: 4px;
    right: 4px;
    background: #10b981;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    color: white;
}

/* Status panel dark */
.panel-dark {
    background: #111827;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
}

/* Progress ring */
.progress-ring-wrap {
    position: relative;
    width: 90px;
    height: 90px;
}
.progress-ring-wrap svg {
    transform: rotate(-90deg);
}
.progress-ring-val {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    font-size: 1.4rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
}
.progress-ring-sub {
    position: absolute;
    top: calc(50% + 18px);
    left: 50%;
    transform: translate(-50%,-50%);
    font-size: 0.6rem;
    color: rgba(255,255,255,0.4);
    letter-spacing: 0.5px;
    text-transform: uppercase;
    white-space: nowrap;
}

/* Status badge camera */
.cam-badge {
    position: absolute;
    z-index: 10;
    backdrop-filter: blur(10px);
    border-radius: 10px;
    padding: 6px 12px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.12);
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    gap: 6px;
    color: #fff;
    transition: all 0.3s;
}
.cam-badge.top-left  { top: 12px; left: 12px; }
.cam-badge.top-right { top: 12px; right: 12px; }
.cam-badge.bottom    { bottom: 12px; left: 50%; transform: translateX(-50%); }

/* Capture flash */
@keyframes captureFlash {
    0% { opacity: 0; }
    20% { opacity: 0.85; }
    100% { opacity: 0; }
}
.capture-flash {
    position: absolute;
    inset: 0;
    background: white;
    opacity: 0;
    pointer-events: none;
    z-index: 20;
    border-radius: 18px;
}
.capture-flash.flash { animation: captureFlash 0.4s ease-out forwards; }

/* Info row dots */
.info-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #374151;
    flex-shrink: 0;
    transition: all 0.3s;
}

/* Guru avatar card */
.guru-avatar-card {
    background: rgba(99,102,241,0.08);
    border: 1px solid rgba(99,102,241,0.2);
    border-radius: 14px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.guru-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(99,102,241,0.4);
}
.guru-avatar-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(99,102,241,0.2);
    border: 2px solid rgba(99,102,241,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #818cf8;
    font-size: 1.3rem;
    flex-shrink: 0;
}

/* Btn sampel animation */
.btn-sampel-pulse {
    animation: btnPulse 1.5s ease-in-out infinite;
}
@keyframes btnPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.4); }
    50% { box-shadow: 0 0 0 10px rgba(99,102,241,0); }
}

/* Success overlay */
.success-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    backdrop-filter: blur(8px);
}
.success-overlay.show { display: flex; }
.success-icon-wrap {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(16,185,129,0.15);
    border: 3px solid #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #10b981;
    margin-bottom: 24px;
    animation: successPop 0.6s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes successPop {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}
</style>

<!-- ===== SUCCESS OVERLAY ===== -->
<div class="success-overlay" id="success-overlay">
    <div class="success-icon-wrap">
        <i class="bi bi-check-lg"></i>
    </div>
    <h3 class="text-white fw-bold mb-2">Registrasi Berhasil!</h3>
    <p class="text-white-50 mb-4">5 sampel wajah berhasil dikumpulkan.</p>
    <div class="d-flex gap-3 flex-wrap justify-content-center">
        <button type="button" id="btn-simpan-final" class="btn btn-success btn-lg px-5 rounded-pill fw-bold shadow-lg">
            <i class="bi bi-save me-2"></i> Simpan Data Wajah
        </button>
        <button type="button" id="btn-ulangi-overlay" class="btn btn-outline-light btn-lg px-4 rounded-pill">
            <i class="bi bi-arrow-counterclockwise me-2"></i> Ulangi
        </button>
    </div>
</div>

<!-- ===== HEADER ===== -->
<div class="rw-header-card p-4 mb-4 shadow-lg">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:14px;background:rgba(99,102,241,0.2);border:1px solid rgba(99,102,241,0.4);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-person-bounding-box text-indigo" style="font-size:1.5rem;color:#818cf8;"></i>
            </div>
            <div>
                <h4 class="fw-bold text-white mb-0">Registrasi Wajah Guru</h4>
                <p class="text-white-50 mb-0 small">Pengambilan sampel wajah untuk Face Recognition AI</p>
            </div>
        </div>
        <a href="registrasi_wajah.php" class="btn btn-outline-light btn-sm rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php if($sudah_terdaftar): ?>
    <div class="alert mt-4 mb-0 border-0 rounded-3" style="background:rgba(234,179,8,0.12);border-left:3px solid #eab308!important;border-left-style:solid!important;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-warning"></i>
            <span class="text-warning">Guru ini sudah memiliki <strong><?= $sampel_lama ?> sampel</strong> wajah terdaftar. Melanjutkan proses akan <strong>menimpa</strong> data wajah yang lama.</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== STEP INDICATOR ===== -->
<div class="panel-dark p-4 mb-4 shadow">
    <div class="step-track">
        <div class="step-item">
            <div class="step-circle" id="sc-1"><i class="bi bi-webcam"></i></div>
            <span class="step-label" id="sl-1">Kamera</span>
        </div>
        <div class="step-connector" id="sconn-1"></div>
        <div class="step-item">
            <div class="step-circle" id="sc-2"><i class="bi bi-cpu"></i></div>
            <span class="step-label" id="sl-2">Model AI</span>
        </div>
        <div class="step-connector" id="sconn-2"></div>
        <div class="step-item">
            <div class="step-circle" id="sc-3"><i class="bi bi-person-bounding-box"></i></div>
            <span class="step-label" id="sl-3">Deteksi</span>
        </div>
        <div class="step-connector" id="sconn-3"></div>
        <div class="step-item">
            <div class="step-circle" id="sc-4"><span>1-5</span></div>
            <span class="step-label" id="sl-4">Sampel</span>
        </div>
        <div class="step-connector" id="sconn-4"></div>
        <div class="step-item">
            <div class="step-circle" id="sc-5"><i class="bi bi-check-lg"></i></div>
            <span class="step-label" id="sl-5">Selesai</span>
        </div>
    </div>
</div>

<!-- ===== MAIN ROW ===== -->
<div class="row g-4">

    <!-- ===== KOLOM KAMERA ===== -->
    <div class="col-xl-7 col-lg-7">
        <div class="panel-dark p-4 shadow h-100">

            <!-- Info Guru -->
            <div class="guru-avatar-card mb-4">
                <?php if($guru['foto'] && file_exists("uploads/profil/".$guru['foto'])): ?>
                    <img src="uploads/profil/<?= $guru['foto'] ?>" alt="Foto" class="guru-avatar">
                <?php else: ?>
                    <div class="guru-avatar-placeholder"><i class="bi bi-person-fill"></i></div>
                <?php endif; ?>
                <div>
                    <div class="fw-bold text-white" style="font-size:0.95rem;"><?= htmlspecialchars($guru['nama_guru']) ?></div>
                    <div class="text-white-50 small">NIP. <?= htmlspecialchars($guru['nip'] ?? '-') ?></div>
                    <div class="mt-1">
                        <?php if($sudah_terdaftar): ?>
                            <span class="badge rounded-pill px-2 py-1" style="background:rgba(234,179,8,0.15);color:#fbbf24;font-size:0.65rem;"><i class="bi bi-arrow-repeat me-1"></i>Perbarui Data</span>
                        <?php else: ?>
                            <span class="badge rounded-pill px-2 py-1" style="background:rgba(99,102,241,0.15);color:#818cf8;font-size:0.65rem;"><i class="bi bi-plus-circle me-1"></i>Registrasi Baru</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Camera Box -->
            <div class="camera-box" id="camera-box">
                <video id="video-stream" autoplay playsinline muted
                    style="width:100%;height:100%;object-fit:cover;display:none;transform:scaleX(-1);"></video>
                <canvas id="canvas-overlay"
                    style="position:absolute;top:0;left:0;width:100%;height:100%;transform:scaleX(-1);"></canvas>
                <canvas id="canvas-snapshot" style="display:none;"></canvas>

                <!-- Scan Line -->
                <div class="scan-line" id="scan-line"></div>

                <!-- Corner Guide -->
                <div class="camera-guide" id="camera-guide"><span></span></div>

                <!-- Capture Flash -->
                <div class="capture-flash" id="capture-flash"></div>

                <!-- Badge: Status Wajah (kiri atas) -->
                <div class="cam-badge top-left" id="badge-face" style="display:none;">
                    <i id="badge-face-icon" class="bi bi-hourglass-split"></i>
                    <span id="badge-face-text">Mendeteksi...</span>
                </div>

                <!-- Badge: Jumlah Wajah (kanan atas) -->
                <div class="cam-badge top-right" id="badge-count" style="display:none;">
                    <i class="bi bi-people"></i>
                    <span id="badge-count-text">0 Wajah</span>
                </div>

                <!-- Badge: Progress bawah -->
                <div class="cam-badge bottom" id="badge-progress" style="display:none;">
                    <i class="bi bi-layers"></i>
                    <span id="badge-progress-text">Sampel 0/5</span>
                </div>

                <!-- Placeholder -->
                <div id="camera-placeholder" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center"
                    style="position:absolute;top:0;left:0;background:#0a0a14;">
                    <div style="width:80px;height:80px;border-radius:50%;background:rgba(99,102,241,0.1);border:2px solid rgba(99,102,241,0.25);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                        <i class="bi bi-camera-video" style="font-size:2.5rem;color:rgba(99,102,241,0.5);"></i>
                    </div>
                    <p class="text-white-50 mb-0" style="font-size:0.9rem;">Kamera belum aktif</p>
                    <small class="text-white-50" style="opacity:0.4;">Klik tombol di bawah untuk memulai</small>
                </div>

                <!-- Loading Model Overlay -->
                <div id="ai-loading-overlay" class="w-100 h-100 flex-column align-items-center justify-content-center"
                    style="position:absolute;top:0;left:0;background:rgba(0,0,0,0.88);display:none;z-index:15;">
                    <div style="width:64px;height:64px;border-radius:50%;background:rgba(99,102,241,0.15);border:2px solid rgba(99,102,241,0.4);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                        <div class="spinner-border text-indigo" style="color:#6366f1;width:2rem;height:2rem;"></div>
                    </div>
                    <p class="fw-bold text-white mb-1" id="loading-text">Memuat Model AI...</p>
                    <small class="text-white-50">Harap tunggu, proses ini hanya sekali</small>
                </div>
            </div>

            <!-- Tombol & Status -->
            <div class="mt-4 text-center">
                <button id="btn-aktifkan" class="btn btn-lg rounded-pill px-5 fw-semibold shadow"
                    style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;border:none;">
                    <i class="bi bi-webcam me-2"></i> Aktifkan Kamera
                </button>
                <button id="btn-ambil-sampel" class="btn btn-lg rounded-pill px-5 fw-bold shadow" style="display:none;"
                    disabled
                    style="background:linear-gradient(135deg,#10b981,#059669);color:white;border:none;">
                    <i class="bi bi-camera me-2"></i> Ambil Sampel
                    <span id="btn-sampel-counter" class="badge rounded-pill ms-2"
                        style="background:rgba(255,255,255,0.25);font-size:0.7rem;">0/5</span>
                </button>
            </div>
            <div id="status-text" class="mt-3 text-center small"
                style="color:rgba(255,255,255,0.4);min-height:20px;">
                Klik "Aktifkan Kamera" untuk memulai proses registrasi wajah.
            </div>
        </div>
    </div>

    <!-- ===== KOLOM PANEL STATUS ===== -->
    <div class="col-xl-5 col-lg-5">
        <div class="d-flex flex-column gap-4 h-100">

            <!-- Progress Sampel -->
            <div class="panel-dark p-4 shadow">
                <h6 class="text-white-50 fw-bold text-uppercase mb-4" style="font-size:0.68rem;letter-spacing:1.5px;">
                    <i class="bi bi-layers me-1"></i> Progress Sampel Wajah
                </h6>

                <div class="d-flex align-items-center gap-4 mb-4">
                    <!-- Ring Progress -->
                    <div class="progress-ring-wrap flex-shrink-0">
                        <svg width="90" height="90" viewBox="0 0 90 90">
                            <circle cx="45" cy="45" r="38" fill="none"
                                stroke="rgba(255,255,255,0.06)" stroke-width="7"/>
                            <circle id="ring-progress" cx="45" cy="45" r="38" fill="none"
                                stroke="#10b981" stroke-width="7"
                                stroke-linecap="round"
                                stroke-dasharray="238.76"
                                stroke-dashoffset="238.76"
                                style="transition:stroke-dashoffset 0.5s cubic-bezier(0.4,0,0.2,1);"/>
                        </svg>
                        <div class="progress-ring-val" id="ring-val">0</div>
                        <div class="progress-ring-sub">/ 5</div>
                    </div>
                    <!-- Label -->
                    <div class="flex-grow-1">
                        <div class="fw-bold text-white mb-1" id="progress-main-label" style="font-size:1rem;">
                            Belum Dimulai
                        </div>
                        <div class="text-white-50 small mb-3" id="progress-sub-label">
                            Aktifkan kamera dan posisikan wajah
                        </div>
                        <div class="progress rounded-pill" style="height:6px;background:rgba(255,255,255,0.06);">
                            <div id="progress-bar" class="progress-bar rounded-pill"
                                style="width:0%;background:linear-gradient(90deg,#6366f1,#10b981);transition:width 0.5s cubic-bezier(0.4,0,0.2,1);"></div>
                        </div>
                    </div>
                </div>

                <!-- Sample Grid 5 Slots -->
                <div class="row g-2" id="sample-grid">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                    <div class="col">
                        <div class="sample-slot" id="slot-<?= $i ?>">
                            <div class="slot-label text-center" id="slot-lbl-<?= $i ?>">
                                <i class="bi bi-image d-block mb-1" style="font-size:1.2rem;opacity:0.2;"></i>
                                <span style="font-size:0.65rem;"><?= $i ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Status Panel -->
            <div class="panel-dark p-4 shadow">
                <h6 class="text-white-50 fw-bold text-uppercase mb-3" style="font-size:0.68rem;letter-spacing:1.5px;">
                    <i class="bi bi-activity me-1"></i> Status Sistem
                </h6>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="info-dot" id="dot-kamera"></div>
                        <small class="text-white-50" id="info-kamera">Kamera: Tidak Aktif</small>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="info-dot" id="dot-model"></div>
                        <small class="text-white-50" id="info-model">Model AI: Belum Dimuat</small>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="info-dot" id="dot-wajah"></div>
                        <small class="text-white-50" id="info-wajah">Deteksi Wajah: Menunggu</small>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="info-dot" id="dot-sampel"></div>
                        <small class="text-white-50" id="info-sampel">Sampel: 0 / 5</small>
                    </div>
                </div>
            </div>

            <!-- Panduan -->
            <div class="panel-dark p-4 shadow flex-grow-1">
                <h6 class="text-white-50 fw-bold text-uppercase mb-3" style="font-size:0.68rem;letter-spacing:1.5px;">
                    <i class="bi bi-lightbulb me-1"></i> Panduan Registrasi
                </h6>
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex gap-3 align-items-start">
                        <div style="width:28px;height:28px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-1-circle" style="color:#818cf8;font-size:0.85rem;"></i>
                        </div>
                        <div>
                            <div class="text-white small fw-semibold">Wajah jelas & pencahayaan baik</div>
                            <div class="text-white-50" style="font-size:0.72rem;">Pastikan wajah guru terlihat jelas di kamera</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div style="width:28px;height:28px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-2-circle" style="color:#818cf8;font-size:0.85rem;"></i>
                        </div>
                        <div>
                            <div class="text-white small fw-semibold">Hanya satu wajah di kamera</div>
                            <div class="text-white-50" style="font-size:0.72rem;">Pastikan tidak ada wajah lain yang terlihat</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div style="width:28px;height:28px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-3-circle" style="color:#818cf8;font-size:0.85rem;"></i>
                        </div>
                        <div>
                            <div class="text-white small fw-semibold">Ambil 5 sampel berbeda</div>
                            <div class="text-white-50" style="font-size:0.72rem;">Variasikan posisi kepala sedikit per sampel</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-start">
                        <div style="width:28px;height:28px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-4-circle" style="color:#818cf8;font-size:0.85rem;"></i>
                        </div>
                        <div>
                            <div class="text-white small fw-semibold">Simpan setelah semua sampel</div>
                            <div class="text-white-50" style="font-size:0.72rem;">Klik Simpan untuk menyimpan ke database</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Form tersembunyi -->
<form id="form-registrasi" action="proses_registrasi_wajah.php" method="POST" style="display:none;">
    <input type="hidden" name="id_guru" value="<?= $id_guru ?>">
    <input type="hidden" name="face_data_json" id="face_data_json">
</form>

<!-- face-api.js -->
<script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js"></script>

<script>
// ============================================================
// KONFIGURASI
// ============================================================
const MODEL_URL   = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model';
const TOTAL_SAMPEL = 5;
const CIRCUMFERENCE = 2 * Math.PI * 38; // SVG ring circumference

// ============================================================
// STATE
// ============================================================
let stream         = null;
let modelLoaded    = false;
let detectionLoopId = null;
let faceCount      = 0;   // jumlah wajah saat ini di frame
let sampelData     = [];   // array Float32Array descriptors
let isCapturing    = false; // sedang proses capture

// ============================================================
// DOM REFS
// ============================================================
const video           = document.getElementById('video-stream');
const canvasOverlay   = document.getElementById('canvas-overlay');
const canvasSnapshot  = document.getElementById('canvas-snapshot');
const placeholder     = document.getElementById('camera-placeholder');
const aiLoadingOverlay = document.getElementById('ai-loading-overlay');
const loadingText     = document.getElementById('loading-text');
const scanLine        = document.getElementById('scan-line');
const cameraGuide     = document.getElementById('camera-guide');
const captureFlash    = document.getElementById('capture-flash');

const btnAktifkan    = document.getElementById('btn-aktifkan');
const btnAmbilSampel = document.getElementById('btn-ambil-sampel');
const btnSampelCounter = document.getElementById('btn-sampel-counter');
const statusText     = document.getElementById('status-text');

// Badges kamera
const badgeFace      = document.getElementById('badge-face');
const badgeFaceIcon  = document.getElementById('badge-face-icon');
const badgeFaceText  = document.getElementById('badge-face-text');
const badgeCount     = document.getElementById('badge-count');
const badgeCountText = document.getElementById('badge-count-text');
const badgeProgress  = document.getElementById('badge-progress');
const badgeProgressText = document.getElementById('badge-progress-text');

// Progress
const ringProgress   = document.getElementById('ring-progress');
const ringVal        = document.getElementById('ring-val');
const progressBar    = document.getElementById('progress-bar');
const progressMainLabel = document.getElementById('progress-main-label');
const progressSubLabel  = document.getElementById('progress-sub-label');

// Status dots
const dotKamera  = document.getElementById('dot-kamera');
const dotModel   = document.getElementById('dot-model');
const dotWajah   = document.getElementById('dot-wajah');
const dotSampel  = document.getElementById('dot-sampel');
const infoKamera = document.getElementById('info-kamera');
const infoModel  = document.getElementById('info-model');
const infoWajah  = document.getElementById('info-wajah');
const infoSampel = document.getElementById('info-sampel');

// Step circles
const stepCircles = [1,2,3,4,5].map(i => document.getElementById(`sc-${i}`));
const stepLabels  = [1,2,3,4,5].map(i => document.getElementById(`sl-${i}`));
const stepConns   = [1,2,3,4].map(i => document.getElementById(`sconn-${i}`));

// Overlay
const successOverlay = document.getElementById('success-overlay');
const btnSimpanFinal = document.getElementById('btn-simpan-final');
const btnUlangiOverlay = document.getElementById('btn-ulangi-overlay');

// ============================================================
// HELPERS
// ============================================================
function setDot(el, color) {
    el.style.background = color;
    el.style.boxShadow  = `0 0 6px ${color}`;
}

function setStep(stepIdx, state) {
    // stepIdx: 0-4, state: 'active'|'done'|'idle'
    const circle = stepCircles[stepIdx];
    const label  = stepLabels[stepIdx];
    if(!circle) return;
    circle.className = 'step-circle ' + (state === 'done' ? 'done' : state === 'active' ? 'active' : '');
    label.className  = 'step-label '  + (state === 'done' ? 'done' : state === 'active' ? 'active' : '');
    if(stepIdx > 0 && state !== 'idle') {
        const conn = stepConns[stepIdx - 1];
        if(conn && state === 'done') conn.classList.add('done');
    }
}

function updateProgressUI(n) {
    const pct = Math.round((n / TOTAL_SAMPEL) * 100);
    const offset = CIRCUMFERENCE - (CIRCUMFERENCE * n / TOTAL_SAMPEL);

    ringProgress.style.strokeDashoffset = offset;
    ringVal.textContent = n;
    progressBar.style.width = pct + '%';
    btnSampelCounter.textContent = `${n}/5`;

    badgeProgressText.textContent = `Sampel ${n}/5`;

    if(n === 0) {
        progressMainLabel.textContent = 'Belum Dimulai';
        progressSubLabel.textContent  = 'Posisikan wajah ke kamera';
        setDot(dotSampel, '#374151');
        infoSampel.textContent = 'Sampel: 0 / 5';
    } else if(n < TOTAL_SAMPEL) {
        progressMainLabel.textContent = `Sampel ${n} / ${TOTAL_SAMPEL}`;
        progressSubLabel.textContent  = `${TOTAL_SAMPEL - n} sampel lagi dibutuhkan`;
        setDot(dotSampel, '#6366f1');
        infoSampel.textContent = `Sampel: ${n} / 5`;
    } else {
        progressMainLabel.textContent = '✓ Semua Sampel Terkumpul';
        progressSubLabel.textContent  = 'Klik Simpan untuk menyimpan data wajah';
        setDot(dotSampel, '#10b981');
        infoSampel.textContent = 'Sampel: 5 / 5 ✓';
        setStep(3, 'done'); // step sampel done
        setStep(4, 'active'); // step selesai active
    }
}

function updateFaceUI(count) {
    faceCount = count;
    badgeCount.style.display = 'flex';
    badgeCountText.textContent = count + (count === 1 ? ' Wajah' : ' Wajah');

    if(count === 0) {
        badgeFace.style.display = 'flex';
        badgeFaceIcon.className = 'bi bi-x-circle-fill';
        badgeFaceIcon.style.color = '#ef4444';
        badgeFaceText.textContent = 'Wajah Tidak Terdeteksi';
        badgeFaceText.style.color = '#fca5a5';

        setDot(dotWajah, '#ef4444');
        infoWajah.textContent = 'Deteksi Wajah: Tidak Ada';
        if(sampelData.length < TOTAL_SAMPEL) {
            btnAmbilSampel.disabled = true;
            statusText.textContent = '⚠ Wajah tidak terdeteksi. Posisikan wajah dengan jelas ke kamera.';
            statusText.style.color = '#ef4444';
        }
    } else if(count === 1) {
        badgeFace.style.display = 'flex';
        badgeFaceIcon.className = 'bi bi-check-circle-fill';
        badgeFaceIcon.style.color = '#10b981';
        badgeFaceText.textContent = 'Wajah Terdeteksi';
        badgeFaceText.style.color = '#6ee7b7';

        setDot(dotWajah, '#10b981');
        infoWajah.textContent = 'Deteksi Wajah: OK (1 Wajah)';
        if(sampelData.length < TOTAL_SAMPEL && !isCapturing) {
            btnAmbilSampel.disabled = false;
            btnAmbilSampel.classList.add('btn-sampel-pulse');
            statusText.textContent = `✓ Wajah terdeteksi! Klik "Ambil Sampel" (${sampelData.length}/${TOTAL_SAMPEL})`;
            statusText.style.color = '#10b981';
        }
    } else {
        badgeFace.style.display = 'flex';
        badgeFaceIcon.className = 'bi bi-exclamation-triangle-fill';
        badgeFaceIcon.style.color = '#eab308';
        badgeFaceText.textContent = `Lebih dari satu wajah (${count})`;
        badgeFaceText.style.color = '#fde047';

        setDot(dotWajah, '#eab308');
        infoWajah.textContent = `Deteksi: ${count} Wajah (Tolak)`;
        if(sampelData.length < TOTAL_SAMPEL) {
            btnAmbilSampel.disabled = true;
            btnAmbilSampel.classList.remove('btn-sampel-pulse');
            statusText.textContent = `⚠ Terdeteksi ${count} wajah. Pastikan hanya guru yang ada di depan kamera.`;
            statusText.style.color = '#eab308';
        }
    }
}

// ============================================================
// LOAD MODEL AI
// ============================================================
async function loadModels() {
    aiLoadingOverlay.style.display = 'flex';
    loadingText.textContent = 'Memuat Model AI...';
    statusText.textContent  = 'Memuat model AI (Face Detection + Recognition)...';
    statusText.style.color  = 'rgba(255,255,255,0.4)';
    setStep(1, 'active');

    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
        ]);
        modelLoaded = true;
        setDot(dotModel, '#10b981');
        infoModel.textContent = 'Model AI: Siap ✓';
        setStep(1, 'done');
        setStep(2, 'active');
        aiLoadingOverlay.style.display = 'none';
        statusText.textContent = 'Model siap. Posisikan wajah guru ke kamera.';
        statusText.style.color = '#6ee7b7';
        return true;
    } catch(err) {
        aiLoadingOverlay.style.display = 'none';
        setDot(dotModel, '#ef4444');
        infoModel.textContent = 'Model AI: Gagal Dimuat';
        statusText.textContent = 'Gagal memuat model AI. Periksa koneksi internet.';
        statusText.style.color = '#ef4444';
        return false;
    }
}

// ============================================================
// DETECTION LOOP
// ============================================================
async function startDetectionLoop() {
    if(!modelLoaded || !stream) return;

    const displaySize = { width: video.videoWidth, height: video.videoHeight };
    faceapi.matchDimensions(canvasOverlay, displaySize);

    // Tampilkan badge progress
    badgeProgress.style.display = 'flex';

    // Aktifkan scanline & guide
    scanLine.classList.add('active');
    cameraGuide.classList.add('active');

    detectionLoopId = setInterval(async () => {
        if(video.paused || video.ended || !stream) return;
        if(isCapturing) return; // skip saat sedang capture
        try {
            const detections = await faceapi
                .detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.55 }))
                .withFaceLandmarks(true);

            const resized = faceapi.resizeResults(detections, displaySize);
            const ctx = canvasOverlay.getContext('2d');
            ctx.clearRect(0, 0, canvasOverlay.width, canvasOverlay.height);

            const count = resized.length;
            updateFaceUI(count);

            // Gambar bounding boxes
            resized.forEach((det, idx) => {
                const box = det.detection.box;
                let boxColor;
                if(count === 1)      boxColor = '#10b981';
                else if(count === 0) boxColor = '#ef4444';
                else                 boxColor = idx === 0 ? '#eab308' : '#ef4444';

                // Kotak wajah
                ctx.strokeStyle = boxColor;
                ctx.lineWidth = 2.5;
                ctx.shadowColor = boxColor;
                ctx.shadowBlur  = 12;
                ctx.strokeRect(box.x, box.y, box.width, box.height);

                // Landmark dots (hanya 1 wajah)
                if(count === 1) {
                    ctx.shadowBlur = 0;
                    ctx.fillStyle  = 'rgba(16,185,129,0.5)';
                    det.landmarks.positions.forEach(pt => {
                        ctx.beginPath();
                        ctx.arc(pt.x, pt.y, 1.8, 0, 2 * Math.PI);
                        ctx.fill();
                    });
                }

                // Label
                ctx.shadowBlur = 0;
                ctx.fillStyle  = boxColor;
                ctx.font       = 'bold 11px system-ui, sans-serif';
                const lbl = count > 1 ? `Wajah ${idx+1}` : 'Wajah Terdeteksi ✓';
                ctx.fillText(lbl, box.x + 4, box.y > 16 ? box.y - 5 : box.y + 14);
            });

        } catch(e) { /* silent */ }
    }, 220);
}

// ============================================================
// TOMBOL AKTIFKAN KAMERA
// ============================================================
btnAktifkan.addEventListener('click', async () => {
    btnAktifkan.disabled = true;
    btnAktifkan.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Menghubungkan...`;
    statusText.textContent = 'Meminta izin kamera...';
    statusText.style.color = 'rgba(255,255,255,0.4)';
    setStep(0, 'active');

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
            audio: false
        });
        video.srcObject = stream;
        await video.play();

        // Show video
        placeholder.style.display = 'none';
        video.style.display = 'block';
        canvasOverlay.style.display = 'block';
        btnAktifkan.style.display = 'none';
        btnAmbilSampel.style.display = 'inline-block';
        badgeFace.style.display = 'flex';

        setDot(dotKamera, '#10b981');
        infoKamera.textContent = 'Kamera: Aktif ✓';
        setStep(0, 'done');

        // Load model
        const ok = await loadModels();
        if(ok) await startDetectionLoop();

    } catch(err) {
        btnAktifkan.disabled = false;
        btnAktifkan.innerHTML = `<i class="bi bi-webcam me-2"></i> Aktifkan Kamera`;
        let msg = 'Kamera tidak ditemukan.';
        if(err.name === 'NotAllowedError') msg = 'Izin kamera ditolak. Harap izinkan di pengaturan browser.';
        if(err.name === 'NotFoundError')   msg = 'Tidak ada perangkat kamera yang terdeteksi.';
        statusText.textContent = '✗ ' + msg;
        statusText.style.color = '#ef4444';
        setDot(dotKamera, '#ef4444');
        infoKamera.textContent = 'Kamera: ' + msg;
        setStep(0, 'idle');
    }
});

// ============================================================
// TOMBOL AMBIL SAMPEL
// ============================================================
btnAmbilSampel.addEventListener('click', async () => {
    if(faceCount !== 1) {
        statusText.textContent = faceCount === 0
            ? '⚠ Tidak ada wajah terdeteksi. Posisikan wajah dengan benar.'
            : `⚠ Terdeteksi ${faceCount} wajah. Hanya boleh 1 wajah.`;
        statusText.style.color = '#eab308';
        return;
    }
    if(sampelData.length >= TOTAL_SAMPEL) return;

    isCapturing = true;
    btnAmbilSampel.disabled = true;
    btnAmbilSampel.classList.remove('btn-sampel-pulse');
    statusText.textContent = `Memproses sampel ${sampelData.length + 1}...`;
    statusText.style.color = 'rgba(255,255,255,0.6)';

    // Flash effect
    captureFlash.classList.add('flash');
    setTimeout(() => captureFlash.classList.remove('flash'), 500);

    try {
        // Deteksi lengkap + descriptor
        const detection = await faceapi
            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.55 }))
            .withFaceLandmarks(true)
            .withFaceDescriptor();

        if(!detection) {
            statusText.textContent = 'Gagal mendeteksi wajah saat pengambilan. Coba lagi.';
            statusText.style.color = '#ef4444';
            isCapturing = false;
            btnAmbilSampel.disabled = false;
            return;
        }

        // Simpan descriptor
        sampelData.push(Array.from(detection.descriptor));
        const slotIdx = sampelData.length;

        // Thumbnail untuk preview
        canvasSnapshot.width  = video.videoWidth;
        canvasSnapshot.height = video.videoHeight;
        const ctxSnap = canvasSnapshot.getContext('2d');
        ctxSnap.save();
        ctxSnap.translate(canvasSnapshot.width, 0);
        ctxSnap.scale(-1, 1);
        ctxSnap.drawImage(video, 0, 0);
        ctxSnap.restore();
        const thumbnail = canvasSnapshot.toDataURL('image/jpeg', 0.65);

        // Update slot preview
        const slot = document.getElementById(`slot-${slotIdx}`);
        slot.innerHTML = `
            <img src="${thumbnail}" style="width:100%;height:100%;object-fit:cover;">
            <div class="slot-done-badge"><i class="bi bi-check-lg"></i></div>
        `;
        slot.classList.add('done');
        slot.classList.remove('active');

        // Update progress
        updateProgressUI(slotIdx);

        // Set step 3 (sampel) active/done
        if(slotIdx < TOTAL_SAMPEL) {
            setStep(3, 'active');
            // Mark next slot as active
            const nextSlot = document.getElementById(`slot-${slotIdx + 1}`);
            if(nextSlot) nextSlot.classList.add('active');
        }

        if(slotIdx >= TOTAL_SAMPEL) {
            // Semua sampel terkumpul
            clearInterval(detectionLoopId);
            scanLine.classList.remove('active');
            cameraGuide.classList.remove('active');
            btnAmbilSampel.style.display = 'none';

            // Set face_data_json
            const payload = JSON.stringify({
                descriptors: sampelData,
                total: TOTAL_SAMPEL,
                timestamp: new Date().toISOString(),
                guru_id: <?= $id_guru ?>
            });
            document.getElementById('face_data_json').value = payload;

            setStep(3, 'done');
            setStep(4, 'active');
            setDot(dotWajah, '#10b981');

            // Show success overlay setelah 600ms
            setTimeout(() => {
                successOverlay.classList.add('show');
            }, 600);

        } else {
            statusText.textContent = `✓ Sampel ${slotIdx} berhasil. Ambil ${TOTAL_SAMPEL - slotIdx} sampel lagi.`;
            statusText.style.color = '#6ee7b7';
            setTimeout(() => {
                isCapturing = false;
                if(faceCount === 1) btnAmbilSampel.disabled = false;
            }, 700);
        }

    } catch(err) {
        console.error(err);
        statusText.textContent = '✗ Gagal memproses wajah. Coba lagi.';
        statusText.style.color = '#ef4444';
        isCapturing = false;
        btnAmbilSampel.disabled = false;
    }
});

// ============================================================
// OVERLAY BUTTONS
// ============================================================
btnSimpanFinal.addEventListener('click', () => {
    btnSimpanFinal.disabled = true;
    btnSimpanFinal.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan...`;
    if(stream) stream.getTracks().forEach(t => t.stop());
    document.getElementById('form-registrasi').submit();
});

btnUlangiOverlay.addEventListener('click', () => {
    // Reset semua dan mulai ulang
    successOverlay.classList.remove('show');
    sampelData   = [];
    isCapturing  = false;
    faceCount    = 0;

    // Reset slot visuals
    for(let i = 1; i <= TOTAL_SAMPEL; i++) {
        const slot = document.getElementById(`slot-${i}`);
        slot.innerHTML = `
            <div class="slot-label text-center" id="slot-lbl-${i}">
                <i class="bi bi-image d-block mb-1" style="font-size:1.2rem;opacity:0.2;"></i>
                <span style="font-size:0.65rem;">${i}</span>
            </div>`;
        slot.classList.remove('done', 'active');
    }

    // Reset progress
    updateProgressUI(0);
    setStep(3, 'active');

    // Restart detection loop
    scanLine.classList.add('active');
    cameraGuide.classList.add('active');
    btnAmbilSampel.style.display = 'inline-block';
    btnAmbilSampel.disabled = (faceCount !== 1);

    statusText.textContent = 'Posisikan wajah ke kamera dan ambil 5 sampel.';
    statusText.style.color = 'rgba(255,255,255,0.4)';

    // Tandai slot 1 sebagai active
    document.getElementById('slot-1').classList.add('active');

    // Restart loop
    if(stream) startDetectionLoop();
});

// ============================================================
// INITIAL SLOT ACTIVE
// ============================================================
document.getElementById('slot-1').classList.add('active');

// ============================================================
// CLEANUP
// ============================================================
window.addEventListener('beforeunload', () => {
    clearInterval(detectionLoopId);
    if(stream) stream.getTracks().forEach(t => t.stop());
});
</script>

<?php include 'includes/footer.php'; ?>
