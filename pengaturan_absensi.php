<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

date_default_timezone_set('Asia/Jakarta');

$msg_success = '';
$msg_error   = '';

// ============================================================
// Proses Simpan Pengaturan
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jam_masuk     = trim($_POST['jam_masuk_sekolah'] ?? '');
    $jam_pulang    = trim($_POST['jam_pulang_sekolah'] ?? '');
    $toleransi     = intval($_POST['toleransi_keterlambatan'] ?? 15);
    $batas_alpa    = trim($_POST['batas_waktu_alpa'] ?? '');

    // Validasi format waktu
    $valid_jam_masuk  = preg_match('/^\d{2}:\d{2}$/', $jam_masuk);
    $valid_jam_pulang = preg_match('/^\d{2}:\d{2}$/', $jam_pulang);
    $valid_batas_alpa = preg_match('/^\d{2}:\d{2}$/', $batas_alpa);

    if (!$valid_jam_masuk || !$valid_jam_pulang || !$valid_batas_alpa) {
        $msg_error = 'Format waktu tidak valid. Gunakan format HH:MM.';
    } elseif ($toleransi < 0 || $toleransi > 120) {
        $msg_error = 'Toleransi keterlambatan harus antara 0-120 menit.';
    } else {
        // Simpan semua pengaturan
        $updates = [
            'jam_masuk_sekolah'      => $jam_masuk . ':00',
            'jam_pulang_sekolah'     => $jam_pulang . ':00',
            'toleransi_keterlambatan'=> strval($toleransi),
            'batas_waktu_alpa'       => $batas_alpa . ':00',
        ];

        $ok = true;
        foreach ($updates as $nama => $nilai) {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE nilai_pengaturan = VALUES(nilai_pengaturan)");
            mysqli_stmt_bind_param($stmt, "ss", $nama, $nilai);
            if (!mysqli_stmt_execute($stmt)) {
                $ok = false;
            }
        }

        if ($ok) {
            $msg_success = 'Pengaturan berhasil disimpan!';
        } else {
            $msg_error = 'Gagal menyimpan pengaturan. Silakan coba lagi.';
        }
    }
}

// ============================================================
// Ambil Nilai Pengaturan Saat Ini
// ============================================================
$q_all = mysqli_query($conn, "SELECT nama_pengaturan, nilai_pengaturan FROM pengaturan");
$settings = [];
while ($row = mysqli_fetch_assoc($q_all)) {
    $settings[$row['nama_pengaturan']] = $row['nilai_pengaturan'];
}

$cur_jam_masuk  = substr($settings['jam_masuk_sekolah'] ?? '07:00:00', 0, 5);
$cur_jam_pulang = substr($settings['jam_pulang_sekolah'] ?? '15:00:00', 0, 5);
$cur_toleransi  = $settings['toleransi_keterlambatan'] ?? '15';
$cur_batas_alpa = substr($settings['batas_waktu_alpa'] ?? '09:00:00', 0, 5);

// Hitung jam batas terlambat (jam masuk + toleransi)
$batas_terlambat = date('H:i', strtotime("+{$cur_toleransi} minutes", strtotime($cur_jam_masuk . ':00')));

// CSRF Token untuk keamanan AJAX tindakan chatbot
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = bin2hex(hash('sha256', uniqid(mt_rand(), true), true));
    }
}
$csrf_token = $_SESSION['csrf_token'];
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="fw-bold mb-1"><i class="bi bi-gear text-primary me-2"></i>Pengaturan Absensi</h3>
        <p class="text-muted mb-0">Konfigurasi jam masuk, batas terlambat, dan batas waktu Alpha / Tidak Hadir</p>
    </div>
</div>

<?php if ($msg_success): ?>
<div class="alert alert-success border-0 rounded-4 shadow-sm d-flex align-items-center mb-4">
    <i class="bi bi-check-circle-fill text-success me-2 fs-5"></i>
    <span><?= htmlspecialchars($msg_success) ?></span>
</div>
<?php endif; ?>

<?php if ($msg_error): ?>
<div class="alert alert-danger border-0 rounded-4 shadow-sm d-flex align-items-center mb-4">
    <i class="bi bi-exclamation-triangle-fill text-danger me-2 fs-5"></i>
    <span><?= htmlspecialchars($msg_error) ?></span>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Pengaturan -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-sliders text-primary me-2"></i>Konfigurasi Waktu</h5>
                <p class="text-muted small mb-0">Aturan waktu yang digunakan untuk menentukan status kehadiran guru</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="pengaturan_absensi.php">
                    <!-- Jam Masuk -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">
                            <i class="bi bi-box-arrow-in-right text-success me-1"></i>Jam Masuk Sekolah
                        </label>
                        <input type="time" name="jam_masuk_sekolah" class="form-control rounded-3 shadow-sm"
                               value="<?= htmlspecialchars($cur_jam_masuk) ?>" required>
                        <div class="form-text">Jam batas absensi masuk dianggap tepat waktu.</div>
                    </div>

                    <!-- Toleransi Keterlambatan -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">
                            <i class="bi bi-clock-history text-warning me-1"></i>Toleransi Keterlambatan (menit)
                        </label>
                        <input type="number" name="toleransi_keterlambatan" class="form-control rounded-3 shadow-sm"
                               value="<?= htmlspecialchars($cur_toleransi) ?>" min="0" max="120" required>
                        <div class="form-text">
                            Guru akan dianggap <strong>Terlambat</strong> jika absen setelah 
                            jam masuk + toleransi ini. Batas terlambat saat ini: 
                            <strong class="text-warning"><?= $batas_terlambat ?></strong>.
                        </div>
                    </div>

                    <!-- Batas Waktu Alpha -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">
                            <i class="bi bi-person-x text-danger me-1"></i>Batas Waktu Alpha / Tidak Hadir
                        </label>
                        <input type="time" name="batas_waktu_alpa" class="form-control rounded-3 shadow-sm border-danger"
                               value="<?= htmlspecialchars($cur_batas_alpa) ?>" required>
                        <div class="form-text">
                            Guru yang belum absen setelah jam ini akan otomatis dicatat sebagai 
                            <strong class="text-danger">Alpha / Tidak Hadir</strong> oleh sistem.
                            Nilai awal: <strong>09:00</strong>.
                        </div>
                    </div>

                    <!-- Jam Pulang -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">
                            <i class="bi bi-box-arrow-right text-secondary me-1"></i>Jam Pulang Sekolah
                        </label>
                        <input type="time" name="jam_pulang_sekolah" class="form-control rounded-3 shadow-sm"
                               value="<?= htmlspecialchars($cur_jam_pulang) ?>" required>
                        <div class="form-text">Jam pulang standar untuk keperluan referensi laporan.</div>
                    </div>

                    <hr class="my-3">
                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm fw-bold">
                        <i class="bi bi-save me-2"></i>Simpan Pengaturan
                    </button>
                    <a href="dashboard_admin.php" class="btn btn-outline-secondary rounded-pill px-4 ms-2">
                        Kembali
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Ringkasan Aturan -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Ringkasan Aturan Status</h5>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-start mb-3">
                    <span class="badge bg-success px-3 py-2 rounded-pill me-3 mt-1" style="min-width:80px;">Hadir</span>
                    <div class="small text-muted">Absen masuk sebelum atau sama dengan jam <strong><?= $batas_terlambat ?></strong> (jam masuk + toleransi <?= $cur_toleransi ?> menit).</div>
                </div>
                <div class="d-flex align-items-start mb-3">
                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill me-3 mt-1" style="min-width:80px;">Terlambat</span>
                    <div class="small text-muted">Absen masuk setelah jam <strong><?= $batas_terlambat ?></strong> tetapi sebelum jam <strong><?= $cur_batas_alpa ?></strong>.</div>
                </div>
                <div class="d-flex align-items-start mb-3">
                    <span class="badge bg-secondary px-3 py-2 rounded-pill me-3 mt-1" style="min-width:80px;">Belum Absen</span>
                    <div class="small text-muted">Belum absen dan batas waktu Alpha (<strong><?= $cur_batas_alpa ?></strong>) belum terlewati.</div>
                </div>
                <div class="d-flex align-items-start">
                    <span class="badge bg-danger px-3 py-2 rounded-pill me-3 mt-1" style="min-width:80px;">Alpha</span>
                    <div class="small text-muted">Belum absen setelah jam <strong class="text-danger"><?= $cur_batas_alpa ?></strong>. Sistem otomatis mencatat status ini.</div>
                </div>
            </div>
        </div>

        <!-- Info -->
        <div class="card border-0 shadow-sm rounded-4" style="background:linear-gradient(135deg,#fff8f0,#fff);">
            <div class="card-body p-4">
                <h6 class="fw-bold text-warning mb-2"><i class="bi bi-lightbulb me-2"></i>Catatan Penting</h6>
                <ul class="small text-muted mb-0 ps-3">
                    <li class="mb-1">Status <strong>Alpha</strong> hanya dicatat otomatis jika guru belum absen sama sekali dan batas waktu sudah terlewati.</li>
                    <li class="mb-1">Guru yang sudah <strong>Hadir</strong> atau <strong>Terlambat</strong> tidak akan diubah menjadi Alpha.</li>
                    <li class="mb-1">Proses otomatis berjalan saat Admin/Guru membuka halaman Dashboard atau Data Absensi.</li>
                    <li class="mb-1">Status Izin dan Sakit harus diinput secara manual oleh Admin.</li>
                    <li>Hari Minggu tidak diproses otomatis.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- BAGIAN BARU: PENGATURAN CHATBOT AI                           -->
<!-- ============================================================ -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
                <div>
                    <h5 class="fw-bold mb-1 text-dark">
                        <i class="bi bi-robot text-primary me-2"></i>Pengaturan Chatbot AI
                    </h5>
                    <p class="text-muted small mb-0">Kontrol ketersediaan fitur asisten AI, pantau koneksi backend, dan status multi-provider</p>
                </div>
                <div class="mt-2 mt-sm-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btn-refresh-chatbot">
                        <i class="bi bi-arrow-clockwise me-1"></i>Periksa Status
                    </button>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    <!-- Status Utama Chatbot -->
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted small fw-semibold">Status Fitur:</span>
                                <span id="cb-badge-status" class="badge bg-secondary px-3 py-2 rounded-pill fs-6">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span> Memeriksa...
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Koneksi Backend:</span>
                                <span id="cb-backend-conn" class="badge bg-light text-dark border px-2 py-1">Memeriksa...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Detail & Waktu Cek -->
                    <div class="col-md-5">
                        <div class="small text-muted">
                            <div class="mb-1">
                                <i class="bi bi-cpu text-primary me-2"></i>Provider AI: 
                                <strong id="cb-provider-info" class="text-dark">Memuat...</strong>
                            </div>
                            <div class="mb-1">
                                <i class="bi bi-clock-history text-secondary me-2"></i>Pemeriksaan Terakhir: 
                                <span id="cb-last-checked">-</span>
                            </div>
                            <div class="mb-0">
                                <i class="bi bi-person-check text-success me-2"></i>Perubahan Terakhir: 
                                <span id="cb-last-changed">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi Kontrol -->
                    <div class="col-md-3 text-md-end">
                        <div class="d-grid gap-2 d-md-block">
                            <button type="button" class="btn btn-success rounded-pill px-3 shadow-sm fw-semibold mb-2 mb-md-0 me-md-2" id="btn-enable-chatbot">
                                <i class="bi bi-power me-1"></i>Aktifkan Chatbot
                            </button>
                            <button type="button" class="btn btn-danger rounded-pill px-3 shadow-sm fw-semibold" id="btn-disable-chatbot">
                                <i class="bi bi-slash-circle me-1"></i>Nonaktifkan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Alert Error / Warning Box jika terjadi kendala -->
                <div id="cb-alert-box" class="alert border-0 rounded-4 shadow-sm mt-4 d-none" role="alert">
                    <div class="d-flex align-items-start">
                        <i id="cb-alert-icon" class="bi bi-info-circle-fill me-2 fs-5 mt-1"></i>
                        <div id="cb-alert-message" class="small"></div>
                    </div>
                </div>

                <!-- Petunjuk Otomatisasi Service Windows -->
                <div class="bg-light p-3 rounded-4 mt-3 border">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-shield-check text-primary fs-5 me-2 mt-1"></i>
                        <div class="small text-muted">
                            <strong class="text-dark">Informasi Layanan Mandiri (Windows):</strong> 
                            Backend Node.js berjalan di background pada port <code>3000</code>. Skrip otomatisasi tersedia di folder <code>SAG/chatbot-backend/</code> (<code>run-service.bat</code> &amp; <code>install-task.bat</code>) agar backend berjalan otomatis saat Windows dinyalakan tanpa perlu terminal manual.
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- ============================================================ -->
                <!-- DAFTAR KONFIGURASI PROVIDER AI (MULTI API KEY & FALLBACK)     -->
                <!-- ============================================================ -->
                <div class="pt-2">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h6 class="fw-bold text-dark mb-1">
                                <i class="bi bi-cpu text-primary me-2"></i>Daftar Provider &amp; API Key AI
                            </h6>
                            <p class="text-muted small mb-0">
                                Simpan beberapa API Key AI (Groq &amp; Gemini). Sistem otomatis memanggil <strong>AI Utama</strong> dan beralih ke <strong>AI Cadangan</strong> jika kuota habis atau error.
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill small">
                                <i class="bi bi-lock-fill text-success me-1"></i>Enkripsi AES-256 Aktif
                            </span>
                            <button type="button" class="btn btn-primary rounded-pill px-3 shadow-sm btn-sm fw-bold" id="btn-open-modal-add">
                                <i class="bi bi-plus-lg me-1"></i>Tambah API Key
                            </button>
                        </div>
                    </div>

                    <!-- Alert Khusus Form Konfigurasi -->
                    <div id="ai-config-alert" class="alert border-0 rounded-4 shadow-sm mb-3 d-none" role="alert">
                        <div class="d-flex align-items-start">
                            <i id="ai-config-alert-icon" class="bi bi-info-circle-fill me-2 fs-5 mt-1"></i>
                            <div id="ai-config-alert-msg" class="small flex-grow-1"></div>
                            <button type="button" class="btn-close ms-2" onclick="document.getElementById('ai-config-alert').classList.add('d-none')"></button>
                        </div>
                    </div>

                    <!-- Tabel Provider AI -->
                    <div class="table-responsive rounded-3 border bg-white shadow-sm">
                        <table class="table table-hover align-middle mb-0" id="table-ai-providers">
                            <thead class="table-light small text-secondary">
                                <tr>
                                    <th scope="col" class="ps-3 py-3">Provider</th>
                                    <th scope="col" class="py-3">Model</th>
                                    <th scope="col" class="py-3 text-center">Prioritas</th>
                                    <th scope="col" class="py-3 text-center">Status</th>
                                    <th scope="col" class="py-3">API Key</th>
                                    <th scope="col" class="py-3">Hasil Uji</th>
                                    <th scope="col" class="pe-3 py-3 text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-ai-providers" class="small">
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Memuat daftar konfigurasi provider AI...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Nonaktifkan Chatbot -->
<div class="modal fade" id="modalConfirmDisable" tabindex="-1" aria-labelledby="modalConfirmDisableLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <h5 class="modal-title fw-bold text-danger" id="modalConfirmDisableLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Nonaktifkan Chatbot
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted mb-0">
                    Apakah Anda yakin ingin menonaktifkan fitur Chatbot AI SAG?
                </p>
                <div class="alert alert-warning border-0 rounded-3 mt-3 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Ketika dinonaktifkan, seluruh pengguna (Guru, Admin, Kepala Sekolah) tidak dapat mengirim pertanyaan baru. Fitur website lainnya seperti Absensi, MySQL, dan Apache tetap berjalan normal.
                </div>
            </div>
            <div class="modal-footer border-0 pb-4 px-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger rounded-pill px-4 shadow-sm fw-bold" id="btn-confirm-disable">
                    <i class="bi bi-slash-circle me-1"></i>Ya, Nonaktifkan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah / Edit Konfigurasi AI -->
<div class="modal fade" id="modalAiProvider" tabindex="-1" aria-labelledby="modalAiProviderLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <h5 class="modal-title fw-bold text-dark" id="modalAiProviderLabel">
                    <i class="bi bi-plus-circle text-primary me-2" id="modal-ai-icon"></i><span id="modal-ai-title">Tambah Konfigurasi AI</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form id="form-modal-ai">
                <input type="hidden" id="modal-provider-id" value="0">
                <div class="modal-body p-4">
                    <div id="modal-ai-alert" class="alert border-0 rounded-3 mb-3 d-none small" role="alert"></div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Provider AI <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3 shadow-sm" id="modal-provider" placeholder="Contoh: Google Gemini" required>
                        <div class="form-text">Pilih penyedia layanan AI.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">API Key <span class="text-danger" id="modal-key-req">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control rounded-start-3 shadow-sm" id="modal-api-key" placeholder="Masukkan API Key" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" id="modal-btn-toggle-key" title="Tampilkan/Sembunyikan">
                                <i class="bi bi-eye" id="modal-icon-toggle-key"></i>
                            </button>
                        </div>
                        <div class="form-text" id="modal-key-hint">API Key otomatis dienkripsi dengan standar AES-256 sebelum disimpan.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Nama Model AI <span class="text-danger">*</span></label>
                        <select class="form-select rounded-3 shadow-sm" id="modal-model" required>
                            <option value="" disabled selected>-- Isi Provider AI terlebih dahulu --</option>
                        </select>
                        <div class="form-text">Pilih model AI yang sesuai dengan provider.</div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark small">Prioritas</label>
                            <select class="form-select rounded-3 shadow-sm" id="modal-priority">
                                <option value="utama">Utama (Primary)</option>
                                <option value="cadangan" selected>Cadangan (Fallback)</option>
                            </select>
                            <div class="form-text">Jika Utama, provider lain otomatis menjadi Cadangan.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark small">Status</label>
                            <select class="form-select rounded-3 shadow-sm" id="modal-status">
                                <option value="aktif" selected>Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                            <div class="form-text">Aktifkan untuk dapat digunakan.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4 pt-0 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-info rounded-pill px-3 shadow-sm fw-bold" id="modal-btn-test">
                        <i class="bi bi-reception-4 me-1"></i>Uji Koneksi
                    </button>
                    <div>
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm fw-bold" id="modal-btn-save">
                            <i class="bi bi-save me-1"></i>Simpan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript Interaktif Pengaturan Chatbot -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = <?= json_encode($csrf_token) ?>;
    const btnRefresh = document.getElementById('btn-refresh-chatbot');
    const btnEnable  = document.getElementById('btn-enable-chatbot');
    const btnDisable = document.getElementById('btn-disable-chatbot');
    const btnConfirmDisable = document.getElementById('btn-confirm-disable');
    
    const badgeStatus   = document.getElementById('cb-badge-status');
    const backendConn   = document.getElementById('cb-backend-conn');
    const providerInfo  = document.getElementById('cb-provider-info');
    const lastChecked   = document.getElementById('cb-last-checked');
    const lastChanged   = document.getElementById('cb-last-changed');
    const alertBox      = document.getElementById('cb-alert-box');
    const alertIcon     = document.getElementById('cb-alert-icon');
    const alertMsg      = document.getElementById('cb-alert-message');

    let modalDisable = null;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        const modalEl = document.getElementById('modalConfirmDisable');
        if (modalEl) modalDisable = new bootstrap.Modal(modalEl);
    }

    function showAlert(type, message) {
        alertBox.className = 'alert border-0 rounded-4 shadow-sm mt-4 alert-' + type;
        alertIcon.className = (type === 'success') ? 'bi bi-check-circle-fill me-2 fs-5 text-success' :
                              (type === 'danger')  ? 'bi bi-exclamation-triangle-fill me-2 fs-5 text-danger' :
                              'bi bi-info-circle-fill me-2 fs-5 text-warning';
        alertMsg.innerHTML = message;
        alertBox.classList.remove('d-none');
    }

    function hideAlert() {
        alertBox.classList.add('d-none');
    }

    async function loadChatbotStatus() {
        btnRefresh.disabled = true;
        btnRefresh.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memeriksa...';

        try {
            const res = await fetch('api_pengaturan_chatbot.php?action=status');
            const data = await res.json();

            if (data.success && data.data) {
                const info = data.data;

                // Update badge status
                badgeStatus.className = 'badge px-3 py-2 rounded-pill fs-6 bg-' + info.status_color;
                badgeStatus.textContent = info.status_badge;

                // Update backend conn badge
                if (info.backend_online) {
                    backendConn.className = 'badge bg-success-subtle text-success border border-success px-2 py-1';
                    backendConn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Backend Online (Port 3000)';
                } else {
                    backendConn.className = 'badge bg-danger-subtle text-danger border border-danger px-2 py-1';
                    backendConn.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i>Backend Offline';
                }

                providerInfo.textContent = info.provider_info || '-';
                lastChecked.textContent = info.last_checked || '-';
                
                let changedText = '-';
                if (info.last_changed_at && info.last_changed_at !== '-') {
                    changedText = info.last_changed_at + (info.last_changed_by && info.last_changed_by !== '-' ? ' oleh ' + info.last_changed_by : '');
                }
                lastChanged.textContent = changedText;

                // Toggle tombol aktif/nonaktif
                if (info.enabled) {
                    btnEnable.disabled = true;
                    btnDisable.disabled = false;
                } else {
                    btnEnable.disabled = false;
                    btnDisable.disabled = true;
                }

                // Tampilkan info alert jika ada gangguan
                if (info.status_label === 'gangguan') {
                    showAlert('danger', '<strong>Status Gangguan:</strong> Chatbot diaktifkan, namun backend AI Node.js (port 3000) tidak merespon. Pastikan server Node.js berjalan.');
                } else if (info.status_label === 'nonaktif') {
                    showAlert('secondary', '<strong>Chatbot Sedang Nonaktif:</strong> Fitur asisten AI dimatikan oleh Administrator. Pengguna tidak dapat mengirim pertanyaan baru.');
                } else {
                    hideAlert();
                }
            } else {
                showAlert('danger', data.message || 'Gagal memuat status chatbot.');
            }
        } catch (err) {
            showAlert('danger', 'Gagal menghubungi server untuk memeriksa status chatbot: ' + err.message);
        } finally {
            btnRefresh.disabled = false;
            btnRefresh.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Periksa Status';
        }
    }

    btnRefresh.addEventListener('click', loadChatbotStatus);

    btnEnable.addEventListener('click', async function () {
        btnEnable.disabled = true;
        btnEnable.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengaktifkan...';
        hideAlert();

        try {
            const formData = new FormData();
            formData.append('action', 'enable');
            formData.append('csrf_token', csrfToken);

            const res = await fetch('api_pengaturan_chatbot.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                showAlert('success', '<strong>Berhasil:</strong> ' + data.message);
                if (typeof window.SAG_CHATBOT_SET_ENABLED === 'function') {
                    window.SAG_CHATBOT_SET_ENABLED(true);
                }
            } else {
                showAlert('danger', '<strong>Gagal:</strong> ' + (data.message || 'Terjadi kesalahan'));
            }
        } catch (err) {
            showAlert('danger', 'Gagal memproses permintaan aktivasi: ' + err.message);
        } finally {
            btnEnable.innerHTML = '<i class="bi bi-power me-1"></i>Aktifkan Chatbot';
            loadChatbotStatus();
        }
    });

    btnDisable.addEventListener('click', function () {
        if (modalDisable) {
            modalDisable.show();
        } else if (confirm('Apakah Anda yakin ingin menonaktifkan chatbot AI? Pengguna tidak akan dapat mengirim pesan.')) {
            executeDisable();
        }
    });

    if (btnConfirmDisable) {
        btnConfirmDisable.addEventListener('click', function () {
            if (modalDisable) modalDisable.hide();
            executeDisable();
        });
    }

    async function executeDisable() {
        btnDisable.disabled = true;
        btnDisable.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menonaktifkan...';
        hideAlert();

        try {
            const formData = new FormData();
            formData.append('action', 'disable');
            formData.append('csrf_token', csrfToken);

            const res = await fetch('api_pengaturan_chatbot.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                showAlert('warning', '<strong>Dinonaktifkan:</strong> ' + data.message);
                if (typeof window.SAG_CHATBOT_SET_ENABLED === 'function') {
                    window.SAG_CHATBOT_SET_ENABLED(false);
                }
            } else {
                showAlert('danger', '<strong>Gagal:</strong> ' + (data.message || 'Terjadi kesalahan'));
            }
        } catch (err) {
            showAlert('danger', 'Gagal memproses permintaan nonaktif: ' + err.message);
        } finally {
            btnDisable.innerHTML = '<i class="bi bi-slash-circle me-1"></i>Nonaktifkan';
            loadChatbotStatus();
        }
    }

    // Panggil status awal saat halaman dimuat
    loadChatbotStatus();

    // =========================================================
    // LOGIKA MULTI PROVIDER AI (GROQ & GEMINI)
    // =========================================================
    const tbodyProviders       = document.getElementById('tbody-ai-providers');
    const btnOpenModalAdd      = document.getElementById('btn-open-modal-add');
    const modalAiEl            = document.getElementById('modalAiProvider');
    const modalAi              = modalAiEl ? new bootstrap.Modal(modalAiEl) : null;
    const modalAiTitle         = document.getElementById('modal-ai-title');
    const modalAiIcon          = document.getElementById('modal-ai-icon');
    const modalProviderId      = document.getElementById('modal-provider-id');
    const modalProvider        = document.getElementById('modal-provider');
    const modalApiKey          = document.getElementById('modal-api-key');
    const modalKeyReq          = document.getElementById('modal-key-req');
    const modalKeyHint         = document.getElementById('modal-key-hint');
    const modalBtnToggleKey    = document.getElementById('modal-btn-toggle-key');
    const modalIconToggleKey   = document.getElementById('modal-icon-toggle-key');
    const modalModel           = document.getElementById('modal-model');
    // modalModelSuggestions tidak lagi digunakan (sudah diganti ke <select>)
    const modalPriority        = document.getElementById('modal-priority');
    const modalStatus          = document.getElementById('modal-status');
    const formModalAi          = document.getElementById('form-modal-ai');
    const modalBtnSave         = document.getElementById('modal-btn-save');
    const modalBtnTest         = document.getElementById('modal-btn-test');
    const modalAiAlert         = document.getElementById('modal-ai-alert');

    const aiAlertBox           = document.getElementById('ai-config-alert');
    const aiAlertIcon          = document.getElementById('ai-config-alert-icon');
    const aiAlertMsg           = document.getElementById('ai-config-alert-msg');

    const defaultModels = {
        groq: [
            'openai/gpt-oss-20b',
            'llama-3.3-70b-versatile',
            'llama-3.1-8b-instant',
            'mixtral-8x7b-32768',
            'gemma2-9b-it'
        ],
        gemini: [
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemma-4-26b-a4b-it'
        ]
    };

    function showAiAlert(type, message) {
        if (!aiAlertBox) return;
        aiAlertBox.className = 'alert border-0 rounded-4 shadow-sm mb-3 alert-' + type;
        aiAlertIcon.className = (type === 'success') ? 'bi bi-check-circle-fill me-2 fs-5 text-success' :
                                (type === 'danger')  ? 'bi bi-exclamation-triangle-fill me-2 fs-5 text-danger' :
                                'bi bi-info-circle-fill me-2 fs-5 text-warning';
        aiAlertMsg.innerHTML = message;
        aiAlertBox.classList.remove('d-none');
    }

    function hideAiAlert() {
        if (aiAlertBox) aiAlertBox.classList.add('d-none');
    }

    function showModalAlert(type, message) {
        if (!modalAiAlert) return;
        modalAiAlert.className = 'alert border-0 rounded-3 mb-3 alert-' + type;
        modalAiAlert.innerHTML = message;
        modalAiAlert.classList.remove('d-none');
    }

    function hideModalAlert() {
        if (modalAiAlert) modalAiAlert.classList.add('d-none');
    }

    // Mengisi dropdown <select id="modal-model"> sesuai provider
    function updateModalModelOptions(prov, currentValue) {
        if (!modalModel) return;
        const list = defaultModels[prov] || [];
        modalModel.innerHTML = '';
        if (list.length === 0) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '-- Pilih provider terlebih dahulu --';
            opt.disabled = true;
            opt.selected = true;
            modalModel.appendChild(opt);
        } else {
            list.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m;
                opt.textContent = m;
                if (currentValue && m === currentValue) opt.selected = true;
                modalModel.appendChild(opt);
            });
            // Jika currentValue tidak ada dalam list, pilih item pertama
            if (!currentValue || !list.includes(currentValue)) {
                modalModel.value = list[0];
            }
        }
    }

    if (modalBtnToggleKey) {
        modalBtnToggleKey.addEventListener('click', function () {
            if (modalApiKey.type === 'password') {
                modalApiKey.type = 'text';
                modalIconToggleKey.className = 'bi bi-eye-slash';
            } else {
                modalApiKey.type = 'password';
                modalIconToggleKey.className = 'bi bi-eye';
            }
        });
    }

    if (modalProvider) {
        modalProvider.addEventListener('input', function () {
            const prov = this.value.trim().toLowerCase();
            updateModalModelOptions(prov, '');
        });
    }

    if (btnOpenModalAdd) {
        btnOpenModalAdd.addEventListener('click', function () {
            hideModalAlert();
            modalProviderId.value = '0';
            modalAiTitle.textContent = 'Tambah Konfigurasi AI';
            modalAiIcon.className = 'bi bi-plus-circle text-primary me-2';
            modalProvider.value = 'groq';
            updateModalModelOptions('groq', '');
            modalApiKey.value = '';
            modalApiKey.placeholder = 'Masukkan API Key baru';
            modalApiKey.required = true;
            modalKeyReq.style.display = 'inline';
            modalKeyHint.textContent = 'API Key otomatis dienkripsi dengan standar AES-256 sebelum disimpan.';
            modalApiKey.type = 'password';
            modalIconToggleKey.className = 'bi bi-eye';
            modalPriority.value = 'cadangan';
            modalStatus.value = 'aktif';
            if (modalAi) modalAi.show();
        });
    }

    async function loadProviders() {
        if (!tbodyProviders) return;
        try {
            const res = await fetch('api_pengaturan_chatbot.php?action=list_providers');
            const json = await res.json();
            if (!json.success || !Array.isArray(json.data)) {
                tbodyProviders.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Gagal memuat daftar provider.</td></tr>';
                return;
            }

            const providers = json.data;
            if (providers.length === 0) {
                tbodyProviders.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle fs-4 d-block mb-2 text-secondary"></i>
                            Belum ada konfigurasi AI. Klik tombol <strong>+ Tambah API Key</strong> di atas untuk menambahkan.
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            providers.forEach(p => {
                const isGroq = (p.provider === 'groq');
                const provBadge = isGroq 
                    ? '<span class="badge bg-dark px-2 py-1"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>Groq</span>'
                    : '<span class="badge bg-primary px-2 py-1"><i class="bi bi-stars me-1"></i>Google Gemini</span>';

                const priorityBadge = p.is_primary
                    ? '<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bi bi-star-fill text-warning me-1"></i>Utama</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary border px-2 py-1">Cadangan</span>';

                const statusBadge = p.is_active
                    ? '<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Aktif</span>'
                    : '<span class="badge bg-secondary-subtle text-muted border px-2 py-1"><i class="bi bi-slash-circle me-1"></i>Nonaktif</span>';

                let testBadge = '<span class="badge bg-secondary-subtle text-muted border px-2 py-1">Belum Diuji</span>';
                if (p.last_test_status && p.last_test_status !== '-') {
                    const isSuccess = p.last_test_status.toLowerCase().includes('berhasil') || p.last_test_status.includes('200');
                    if (isSuccess) {
                        const connLabel = p.is_active ? 'Aktif — Terkoneksi' : 'Terkoneksi';
                        testBadge = `<span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="bi bi-check2 me-1"></i>${connLabel}</span>`;
                    } else {
                        const connLabel = p.is_active ? 'Aktif — Koneksi Gagal' : 'Gagal';
                        testBadge = `<span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1"><i class="bi bi-x-circle me-1"></i>${connLabel}</span>`;
                    }
                    testBadge += `<div class="text-muted" style="font-size: 0.72rem;">${p.last_test_at}</div>`;
                }

                const primaryBtn = !p.is_primary
                    ? `<button type="button" class="btn btn-outline-warning btn-sm rounded-pill py-0 px-2 btn-action" data-action="set_primary" data-id="${p.id}" title="Jadikan AI Utama">
                         <i class="bi bi-star"></i> Utama
                       </button>`
                    : '';

                const toggleBtn = p.is_active
                    ? `<button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 btn-action" data-action="toggle_status" data-id="${p.id}" title="Nonaktifkan provider">
                         <i class="bi bi-toggle-on text-success"></i>
                       </button>`
                    : `<button type="button" class="btn btn-outline-success btn-sm rounded-pill py-0 px-2 btn-action" data-action="toggle_status" data-id="${p.id}" title="Aktifkan provider">
                         <i class="bi bi-toggle-off"></i>
                       </button>`;

                html += `
                    <tr>
                        <td class="ps-3 fw-bold">${provBadge}</td>
                        <td><code>${p.model}</code></td>
                        <td class="text-center">${priorityBadge}</td>
                        <td class="text-center">${statusBadge}</td>
                        <td><code>${p.masked_key || '-'}</code></td>
                        <td>${testBadge}</td>
                        <td class="pe-3 text-end">
                            <div class="d-inline-flex gap-1">
                                ${primaryBtn}
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2 btn-action" data-action="test_conn" data-id="${p.id}" title="Uji Koneksi API">
                                    <i class="bi bi-reception-4"></i> Uji
                                </button>
                                ${toggleBtn}
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill py-0 px-2 btn-action" data-action="edit" data-id="${p.id}" title="Edit Konfigurasi">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill py-0 px-2 btn-action" data-action="delete" data-id="${p.id}" title="Hapus Konfigurasi">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbodyProviders.innerHTML = html;
        } catch (err) {
            console.error('Error loadProviders:', err);
            tbodyProviders.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Gagal menghubungi server.</td></tr>';
        }
    }

    // Event delegation untuk tombol aksi tabel
    if (tbodyProviders) {
        tbodyProviders.addEventListener('click', async function (e) {
            const btn = e.target.closest('.btn-action');
            if (!btn) return;

            const action = btn.getAttribute('data-action');
            const id = btn.getAttribute('data-id');
            if (!action || !id) return;

            hideAiAlert();

            if (action === 'edit') {
                try {
                    btn.disabled = true;
                    const res = await fetch(`api_pengaturan_chatbot.php?action=get_provider&id=${id}`);
                    const json = await res.json();
                    if (!json.success || !json.data) {
                        showAiAlert('danger', json.message || 'Gagal memuat detail provider.');
                        return;
                    }
                    const d = json.data;
                    hideModalAlert();
                    modalProviderId.value = d.id;
                    modalAiTitle.textContent = 'Edit Konfigurasi AI';
                    modalAiIcon.className = 'bi bi-pencil-square text-primary me-2';
                    modalProvider.value = d.provider;
                    updateModalModelOptions(d.provider, d.model);
                    modalApiKey.value = '';
                    modalApiKey.placeholder = `Kosongkan jika tidak diubah (${d.masked_key})`;
                    modalApiKey.required = false;
                    modalKeyReq.style.display = 'none';
                    modalKeyHint.textContent = `Tersimpan saat ini: ${d.masked_key}. Masukkan teks baru hanya jika ingin menggantinya.`;
                    modalApiKey.type = 'password';
                    modalIconToggleKey.className = 'bi bi-eye';
                    modalPriority.value = d.is_primary ? 'utama' : 'cadangan';
                    modalStatus.value = d.is_active ? 'aktif' : 'nonaktif';
                    if (modalAi) modalAi.show();
                } catch (err) {
                    showAiAlert('danger', 'Gagal memuat detail provider: ' + err.message);
                } finally {
                    btn.disabled = false;
                }
            } else if (action === 'set_primary') {
                try {
                    btn.disabled = true;
                    const fd = new FormData();
                    fd.append('action', 'set_primary_provider');
                    fd.append('csrf_token', csrfToken);
                    fd.append('id', id);
                    const res = await fetch('api_pengaturan_chatbot.php', { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json.success) {
                        showAiAlert('success', json.message);
                        await loadProviders();
                        loadChatbotStatus();
                    } else {
                        showAiAlert('danger', json.message || 'Gagal mengatur provider utama.');
                    }
                } catch (err) {
                    showAiAlert('danger', 'Gagal memproses aksi: ' + err.message);
                } finally {
                    btn.disabled = false;
                }
            } else if (action === 'toggle_status') {
                try {
                    btn.disabled = true;
                    const fd = new FormData();
                    fd.append('action', 'toggle_provider_status');
                    fd.append('csrf_token', csrfToken);
                    fd.append('id', id);
                    const res = await fetch('api_pengaturan_chatbot.php', { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json.success) {
                        showAiAlert('success', json.message);
                        await loadProviders();
                        loadChatbotStatus();
                    } else {
                        showAiAlert('danger', json.message || 'Gagal mengubah status.');
                    }
                } catch (err) {
                    showAiAlert('danger', 'Gagal memproses aksi: ' + err.message);
                } finally {
                    btn.disabled = false;
                }
            } else if (action === 'test_conn') {
                const oldHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                try {
                    const fd = new FormData();
                    fd.append('action', 'test_connection');
                    fd.append('csrf_token', csrfToken);
                    fd.append('id', id);
                    const res = await fetch('api_pengaturan_chatbot.php', { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json.success) {
                        showAiAlert('success', '<i class="bi bi-check-circle me-1"></i>' + json.message);
                    } else {
                        showAiAlert('danger', '<i class="bi bi-x-circle me-1"></i>' + json.message);
                    }
                    await loadProviders();
                } catch (err) {
                    showAiAlert('danger', 'Gagal melakukan uji koneksi: ' + err.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = oldHtml;
                }
            } else if (action === 'delete') {
                if (!confirm('Apakah Anda yakin ingin menghapus konfigurasi provider AI ini?')) return;
                try {
                    btn.disabled = true;
                    const fd = new FormData();
                    fd.append('action', 'delete_provider');
                    fd.append('csrf_token', csrfToken);
                    fd.append('id', id);
                    const res = await fetch('api_pengaturan_chatbot.php', { method: 'POST', body: fd });
                    const json = await res.json();
                    if (json.success) {
                        showAiAlert('warning', json.message);
                        await loadProviders();
                        loadChatbotStatus();
                    } else {
                        showAiAlert('danger', json.message || 'Gagal menghapus konfigurasi.');
                    }
                } catch (err) {
                    showAiAlert('danger', 'Gagal memproses penghapusan: ' + err.message);
                } finally {
                    btn.disabled = false;
                }
            }
        });
    }

    // Uji Koneksi langsung dari Modal (menggunakan form input yang sedang aktif)
    if (modalBtnTest) {
        modalBtnTest.addEventListener('click', async function () {
            hideModalAlert();
            const id = modalProviderId.value;
            const prov = modalProvider.value.trim();
            const key = modalApiKey.value.trim();
            const model = modalModel.value.trim();

            if (id === '0' && !key) {
                showModalAlert('danger', 'Masukkan API Key terlebih dahulu untuk melakukan uji koneksi.');
                return;
            }

            if (!model) {
                showModalAlert('danger', 'Silakan pilih model AI terlebih dahulu.');
                return;
            }

            const oldHtml = modalBtnTest.innerHTML;
            modalBtnTest.disabled = true;
            modalBtnTest.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menguji...';

            try {
                const fd = new FormData();
                fd.append('action', 'test_connection');
                fd.append('csrf_token', csrfToken);
                fd.append('id', id);
                fd.append('provider', prov);
                fd.append('api_key', key);
                fd.append('model', model);

                const res = await fetch('api_pengaturan_chatbot.php', { method: 'POST', body: fd });
                const json = await res.json();

                if (json.success) {
                    showModalAlert('success', '<i class="bi bi-check-circle-fill me-1"></i>' + json.message);
                } else {
                    showModalAlert('danger', '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + json.message);
                }
                await loadProviders();
            } catch (err) {
                showModalAlert('danger', 'Gagal melakukan uji koneksi: ' + err.message);
            } finally {
                modalBtnTest.disabled = false;
                modalBtnTest.innerHTML = oldHtml;
            }
        });
    }

    // Submit form modal Tambah/Edit Provider
    if (formModalAi) {
        formModalAi.addEventListener('submit', async function (e) {
            e.preventDefault();
            hideModalAlert();

            const id = modalProviderId.value;
            const prov = modalProvider.value;
            const key = modalApiKey.value.trim();
            const model = modalModel.value.trim();
            const priority = modalPriority.value;
            const status = modalStatus.value;

            if (id === '0' && !key) {
                showModalAlert('danger', 'API Key wajib diisi untuk konfigurasi baru.');
                return;
            }

            if (!prov || !prov.trim()) {
                showModalAlert('danger', 'Provider AI wajib diisi.');
                return;
            }

            if (!model || !model.trim()) {
                showModalAlert('danger', 'Silakan pilih model AI terlebih dahulu.');
                return;
            }

            modalBtnSave.disabled = true;
            modalBtnSave.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

            try {
                const fd = new FormData();
                fd.append('action', 'save_provider');
                fd.append('csrf_token', csrfToken);
                fd.append('id', id);
                fd.append('provider', prov);
                fd.append('api_key', key);
                fd.append('model', model);
                fd.append('priority', priority);
                fd.append('status', status);

                const res = await fetch('api_pengaturan_chatbot.php', { method: 'POST', body: fd });
                const json = await res.json();

                if (json.success) {
                    if (modalAi) modalAi.hide();
                    showAiAlert('success', json.message);
                    await loadProviders();
                    loadChatbotStatus();
                } else {
                    showModalAlert('danger', json.message || 'Gagal menyimpan.');
                }
            } catch (err) {
                showModalAlert('danger', 'Gagal menyimpan: ' + err.message);
            } finally {
                modalBtnSave.disabled = false;
                modalBtnSave.innerHTML = '<i class="bi bi-save me-1"></i>Simpan';
            }
        });
    }

    // Panggil daftar provider awal
    loadProviders();
});
</script>

<?php include 'includes/footer.php'; ?>
