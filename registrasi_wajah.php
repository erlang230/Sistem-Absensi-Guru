<?php
require_once 'auth.php';
check_login();
check_role('admin');
require_once 'config/database.php';

// Ambil daftar guru beserta status wajah + statistik
$sql = "SELECT g.id_guru, g.nama_guru, g.nip, g.foto, g.face_data,
               (SELECT COUNT(*) FROM absensi a WHERE a.id_guru = g.id_guru) AS total_absensi
        FROM guru g ORDER BY g.nama_guru ASC";
$result = mysqli_query($conn, $sql);

$total_guru     = 0;
$total_terdaftar = 0;
$rows = [];
while($row = mysqli_fetch_assoc($result)) {
    $total_guru++;
    if(!empty($row['face_data'])) $total_terdaftar++;
    $rows[] = $row;
}
$total_belum = $total_guru - $total_terdaftar;
$pct = $total_guru > 0 ? round(($total_terdaftar / $total_guru) * 100) : 0;
?>
<?php include 'includes/header.php'; ?>

<style>
/* ===== REGISTRASI WAJAH LIST - PREMIUM UI ===== */
.rw-page-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f172a 100%);
    border: 1px solid rgba(99,102,241,0.18);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}
.rw-page-header::after {
    content: '';
    position: absolute;
    top: -40%;
    right: -5%;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(99,102,241,0.12) 0%, transparent 70%);
    pointer-events: none;
}

/* Stat card */
.stat-card {
    background: #111827;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
    padding: 20px 24px;
    transition: transform 0.25s, box-shadow 0.25s;
}
.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(0,0,0,0.25);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

/* Table */
.rw-table-wrap {
    background: #111827;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 18px;
    overflow: hidden;
}
.rw-table thead th {
    background: rgba(255,255,255,0.04);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.45);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    padding: 14px 20px;
    white-space: nowrap;
}
.rw-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.2s;
}
.rw-table tbody tr:last-child { border-bottom: none; }
.rw-table tbody tr:hover { background: rgba(255,255,255,0.025); }
.rw-table tbody td { padding: 14px 20px; vertical-align: middle; }

/* Guru avatar */
.guru-av {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(99,102,241,0.3);
    flex-shrink: 0;
}
.guru-av-placeholder {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(99,102,241,0.12);
    border: 2px solid rgba(99,102,241,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #818cf8;
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Status badges */
.badge-terdaftar {
    background: rgba(16,185,129,0.12);
    border: 1px solid rgba(16,185,129,0.3);
    color: #34d399;
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.badge-belum {
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.25);
    color: #f87171;
    border-radius: 20px;
    padding: 5px 12px;
    font-size: 0.72rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.badge-sampel {
    background: rgba(99,102,241,0.1);
    border: 1px solid rgba(99,102,241,0.2);
    color: #a5b4fc;
    border-radius: 8px;
    padding: 2px 8px;
    font-size: 0.65rem;
    font-weight: 600;
}

/* Btn actions */
.btn-register {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 6px 14px;
    font-size: 0.78rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-register:hover {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(99,102,241,0.35);
}
.btn-update {
    background: rgba(234,179,8,0.12);
    color: #fbbf24;
    border: 1px solid rgba(234,179,8,0.3);
    border-radius: 10px;
    padding: 6px 14px;
    font-size: 0.78rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-update:hover {
    background: rgba(234,179,8,0.2);
    color: #fbbf24;
    transform: translateY(-1px);
}
.btn-hapus {
    background: rgba(239,68,68,0.1);
    color: #f87171;
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 10px;
    padding: 6px 10px;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s;
    text-decoration: none;
}
.btn-hapus:hover {
    background: rgba(239,68,68,0.2);
    color: #f87171;
    transform: translateY(-1px);
}

/* Overall progress bar */
.overall-bar-wrap {
    background: rgba(255,255,255,0.05);
    border-radius: 99px;
    height: 8px;
    overflow: hidden;
    margin-top: 8px;
}
.overall-bar {
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg, #6366f1, #10b981);
    transition: width 1s cubic-bezier(0.4,0,0.2,1);
}

/* Alert custom */
.alert-custom {
    background: rgba(16,185,129,0.08);
    border: 1px solid rgba(16,185,129,0.2);
    border-radius: 14px;
    padding: 16px 20px;
    color: #6ee7b7;
}
.alert-custom-danger {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 14px;
    padding: 16px 20px;
    color: #fca5a5;
}

/* Search box */
.search-box {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 10px 16px;
    color: white;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.2s;
}
.search-box::placeholder { color: rgba(255,255,255,0.3); }
.search-box:focus { border-color: rgba(99,102,241,0.5); }

/* Empty state */
.empty-state {
    padding: 60px 20px;
    text-align: center;
}
</style>

<!-- ===== HEADER ===== -->
<div class="rw-page-header p-4 mb-4 shadow-lg">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-3 mb-2">
                <div style="width:50px;height:50px;border-radius:15px;background:rgba(99,102,241,0.2);border:1px solid rgba(99,102,241,0.35);display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-person-bounding-box" style="font-size:1.6rem;color:#818cf8;"></i>
                </div>
                <div>
                    <h4 class="fw-bold text-white mb-0">Registrasi Wajah Guru</h4>
                    <p class="text-white-50 mb-0 small">Kelola data wajah guru untuk sistem Face Recognition AI</p>
                </div>
            </div>
        </div>
        <div class="text-end">
            <div class="text-white-50 small mb-1">Progress Pendaftaran</div>
            <div class="fw-bold text-white" style="font-size:1.4rem;"><?= $pct ?>%</div>
            <div class="text-white-50 small"><?= $total_terdaftar ?>/<?= $total_guru ?> Guru</div>
        </div>
    </div>

    <!-- Overall Progress Bar -->
    <div class="mt-3">
        <div class="overall-bar-wrap">
            <div class="overall-bar" style="width:<?= $pct ?>%"></div>
        </div>
    </div>
</div>

<?php if(isset($_SESSION['success'])): ?>
<div class="alert-custom d-flex align-items-center gap-3 mb-4 shadow-sm">
    <i class="bi bi-check-circle-fill" style="font-size:1.3rem;"></i>
    <div><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <button type="button" class="btn-close btn-close-white ms-auto opacity-50" onclick="this.closest('.alert-custom').remove()"></button>
</div>
<?php endif; ?>
<?php if(isset($_SESSION['error'])): ?>
<div class="alert-custom-danger d-flex align-items-center gap-3 mb-4 shadow-sm">
    <i class="bi bi-exclamation-triangle-fill" style="font-size:1.3rem;"></i>
    <div><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <button type="button" class="btn-close btn-close-white ms-auto opacity-50" onclick="this.closest('.alert-custom-danger').remove()"></button>
</div>
<?php endif; ?>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.25);">
                    <i class="bi bi-people" style="color:#818cf8;"></i>
                </div>
                <div>
                    <div style="font-size:1.8rem;font-weight:800;color:white;line-height:1.1;"><?= $total_guru ?></div>
                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.4);font-weight:600;text-transform:uppercase;letter-spacing:0.8px;">Total Guru</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);">
                    <i class="bi bi-patch-check" style="color:#34d399;"></i>
                </div>
                <div>
                    <div style="font-size:1.8rem;font-weight:800;color:#34d399;line-height:1.1;"><?= $total_terdaftar ?></div>
                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.4);font-weight:600;text-transform:uppercase;letter-spacing:0.8px;">Sudah Terdaftar</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="stat-icon" style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);">
                    <i class="bi bi-person-x" style="color:#f87171;"></i>
                </div>
                <div>
                    <div style="font-size:1.8rem;font-weight:800;color:#f87171;line-height:1.1;"><?= $total_belum ?></div>
                    <div style="font-size:0.75rem;color:rgba(255,255,255,0.4);font-weight:600;text-transform:uppercase;letter-spacing:0.8px;">Belum Terdaftar</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TABLE ===== -->
<div class="rw-table-wrap shadow">
    <!-- Table Header / Search -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-4"
        style="border-bottom:1px solid rgba(255,255,255,0.06);">
        <div>
            <h6 class="fw-bold text-white mb-0" style="font-size:0.9rem;">Daftar Guru</h6>
            <small style="color:rgba(255,255,255,0.35);">Kelola registrasi wajah setiap guru</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="position-relative">
                <i class="bi bi-search position-absolute"
                    style="left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,0.3);font-size:0.8rem;"></i>
                <input type="text" id="searchGuru" class="search-box" placeholder="Cari nama atau NIP..."
                    style="padding-left:36px;width:220px;" oninput="filterTable()">
            </div>
            <select id="filterStatus" class="search-box" style="width:auto;cursor:pointer;" onchange="filterTable()">
                <option value="">Semua Status</option>
                <option value="terdaftar">Sudah Terdaftar</option>
                <option value="belum">Belum Terdaftar</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="rw-table w-100" id="guruTable">
            <thead>
                <tr>
                    <th width="50" class="text-center">No</th>
                    <th width="56" class="text-center">Foto</th>
                    <th>Nama Guru / NIP</th>
                    <th class="text-center">Status Wajah</th>
                    <th class="text-center">Sampel</th>
                    <th class="text-center" width="190">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($rows) > 0): ?>
                    <?php $no = 1; foreach($rows as $row): ?>
                    <?php
                        $terdaftar = !empty($row['face_data']);
                        $jumlah_sampel = 0;
                        if($terdaftar) {
                            $fd = json_decode($row['face_data'], true);
                            $jumlah_sampel = isset($fd['descriptors']) ? count($fd['descriptors']) : 0;
                        }
                        $status_val = $terdaftar ? 'terdaftar' : 'belum';
                    ?>
                    <tr data-nama="<?= strtolower(htmlspecialchars($row['nama_guru'])) ?>"
                        data-nip="<?= htmlspecialchars($row['nip'] ?? '') ?>"
                        data-status="<?= $status_val ?>">
                        <td class="text-center">
                            <span style="color:rgba(255,255,255,0.3);font-size:0.8rem;"><?= $no++ ?></span>
                        </td>
                        <td class="text-center">
                            <?php if($row['foto'] && file_exists("uploads/profil/".$row['foto'])): ?>
                                <img src="uploads/profil/<?= $row['foto'] ?>" class="guru-av" alt="Foto">
                            <?php else: ?>
                                <div class="guru-av-placeholder mx-auto">
                                    <i class="bi bi-person-fill"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold text-white" style="font-size:0.88rem;">
                                <?= htmlspecialchars($row['nama_guru']) ?>
                            </div>
                            <div style="color:rgba(255,255,255,0.35);font-size:0.75rem;">
                                NIP. <?= htmlspecialchars($row['nip'] ?? '-') ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if($terdaftar): ?>
                                <div class="badge-terdaftar">
                                    <i class="bi bi-shield-fill-check"></i> Sudah Terdaftar
                                </div>
                            <?php else: ?>
                                <div class="badge-belum">
                                    <i class="bi bi-shield-x"></i> Belum Terdaftar
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($terdaftar): ?>
                                <span class="badge-sampel">
                                    <i class="bi bi-layers me-1"></i><?= $jumlah_sampel ?> Sampel
                                </span>
                            <?php else: ?>
                                <span style="color:rgba(255,255,255,0.2);font-size:0.75rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                                <?php if($terdaftar): ?>
                                    <a href="kamera_wajah.php?id=<?= $row['id_guru'] ?>" class="btn-update">
                                        <i class="bi bi-arrow-repeat"></i> Perbarui
                                    </a>
                                <?php else: ?>
                                    <a href="kamera_wajah.php?id=<?= $row['id_guru'] ?>" class="btn-register">
                                        <i class="bi bi-camera-video"></i> Daftarkan
                                    </a>
                                <?php endif; ?>
                                <?php if($terdaftar): ?>
                                    <a href="hapus_wajah.php?id=<?= $row['id_guru'] ?>"
                                        class="btn-hapus"
                                        onclick="return confirm('Hapus data wajah <?= addslashes($row['nama_guru']) ?>?\n\nGuru tidak akan dapat melakukan absensi AI.')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="bi bi-people" style="font-size:3rem;color:rgba(255,255,255,0.1);display:block;margin-bottom:16px;"></i>
                                <div class="text-white-50 fw-semibold">Belum ada data guru</div>
                                <small style="color:rgba(255,255,255,0.3);">Tambahkan guru terlebih dahulu melalui menu Data Guru</small>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Table Footer -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 px-4 py-3"
        style="border-top:1px solid rgba(255,255,255,0.05);">
        <small style="color:rgba(255,255,255,0.3);" id="table-footer-text">
            Menampilkan <?= count($rows) ?> dari <?= $total_guru ?> guru
        </small>
        <div class="d-flex gap-2 align-items-center">
            <div class="badge-terdaftar" style="font-size:0.65rem;">
                <i class="bi bi-shield-fill-check"></i> <?= $total_terdaftar ?> Terdaftar
            </div>
            <div class="badge-belum" style="font-size:0.65rem;">
                <i class="bi bi-shield-x"></i> <?= $total_belum ?> Belum
            </div>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const searchVal  = document.getElementById('searchGuru').value.toLowerCase().trim();
    const statusVal  = document.getElementById('filterStatus').value;
    const rows       = document.querySelectorAll('#guruTable tbody tr[data-nama]');
    let visible = 0;

    rows.forEach(row => {
        const nama   = row.dataset.nama   || '';
        const nip    = row.dataset.nip    || '';
        const status = row.dataset.status || '';

        const matchSearch = !searchVal || nama.includes(searchVal) || nip.includes(searchVal);
        const matchStatus = !statusVal || status === statusVal;

        if(matchSearch && matchStatus) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    const footer = document.getElementById('table-footer-text');
    if(footer) footer.textContent = `Menampilkan ${visible} dari <?= $total_guru ?> guru`;
}
</script>

<?php include 'includes/footer.php'; ?>
