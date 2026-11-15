-- ==================================================
-- DATABASE: db_absensi_guru_ai
-- ==================================================

CREATE DATABASE IF NOT EXISTS db_absensi_guru_ai;
USE db_absensi_guru_ai;

-- ==================================================
-- 1. TABLE: users
-- ==================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, -- Disimpan dengan password_hash()
    role ENUM('admin', 'guru', 'kepala_sekolah') NOT NULL,
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==================================================
-- 2. TABLE: guru
-- ==================================================
CREATE TABLE IF NOT EXISTS guru (
    id_guru INT AUTO_INCREMENT PRIMARY KEY,
    id_user INT NOT NULL,
    nip VARCHAR(25) UNIQUE,
    nama_guru VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    email VARCHAR(100) UNIQUE,
    no_hp VARCHAR(20),
    foto VARCHAR(255) DEFAULT NULL,
    face_data TEXT DEFAULT NULL, -- Data untuk Face Recognition (Tahap Berikutnya)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE
);

-- ==================================================
-- 3. TABLE: kelas
-- ==================================================
CREATE TABLE IF NOT EXISTS kelas (
    id_kelas INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(50) NOT NULL,
    tingkat VARCHAR(10) NOT NULL, -- Contoh: 'X', 'XI', 'XII'
    jurusan VARCHAR(50) NOT NULL  -- Contoh: 'MIPA', 'IPS', 'Bahasa'
);

-- ==================================================
-- 4. TABLE: mata_pelajaran
-- ==================================================
CREATE TABLE IF NOT EXISTS mata_pelajaran (
    id_mapel INT AUTO_INCREMENT PRIMARY KEY,
    nama_mapel VARCHAR(100) NOT NULL,
    kode_mapel VARCHAR(20) NOT NULL UNIQUE
);

-- ==================================================
-- 5. TABLE: jadwal
-- ==================================================
CREATE TABLE IF NOT EXISTS jadwal (
    id_jadwal INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    id_mapel INT NOT NULL,
    id_kelas INT NOT NULL,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru) ON DELETE CASCADE,
    FOREIGN KEY (id_mapel) REFERENCES mata_pelajaran(id_mapel) ON DELETE CASCADE,
    FOREIGN KEY (id_kelas) REFERENCES kelas(id_kelas) ON DELETE CASCADE
);

-- ==================================================
-- 6. TABLE: absensi
-- ==================================================
CREATE TABLE IF NOT EXISTS absensi (
    id_absensi INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_masuk TIME DEFAULT NULL,
    jam_pulang TIME DEFAULT NULL,
    status ENUM('Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpha') NOT NULL,
    metode_verifikasi ENUM('AI Face Recognition', 'Manual') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru) ON DELETE CASCADE,
    
    -- Index & Unique Constraint untuk mencegah duplikasi absensi guru pada hari yang sama
    UNIQUE KEY uq_guru_tanggal (id_guru, tanggal),
    INDEX idx_tanggal (tanggal),
    INDEX idx_status (status)
);

-- ==================================================
-- 7. TABLE: pengaturan
-- ==================================================
CREATE TABLE IF NOT EXISTS pengaturan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_pengaturan VARCHAR(50) NOT NULL UNIQUE,
    nilai_pengaturan VARCHAR(255) NOT NULL
);

-- ==================================================
-- 8. TABLE: guru_google (Akun Google Guru Terhubung)
-- ==================================================
CREATE TABLE IF NOT EXISTS guru_google (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL UNIQUE,
    email_google VARCHAR(150) NOT NULL UNIQUE,
    status_tautan ENUM('terhubung', 'menunggu_verifikasi') NOT NULL DEFAULT 'menunggu_verifikasi',
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================================================
-- 9. TABLE: otp_verifikasi (Token OTP Verifikasi Email)
-- ==================================================
CREATE TABLE IF NOT EXISTS otp_verifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    email_tujuan VARCHAR(150) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 5,
    is_used TINYINT(1) DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_otp_guru (id_guru, is_used, expires_at),
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================================================
-- 10. TABLE: riwayat_pengingat (Log Pengingat Absensi)
-- ==================================================
CREATE TABLE IF NOT EXISTS riwayat_pengingat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_guru INT NOT NULL,
    dikirim_oleh INT NULL,
    jenis_pengingat ENUM('manual', 'otomatis') NOT NULL DEFAULT 'manual',
    email_tujuan VARCHAR(150) NOT NULL,
    tanggal_absensi DATE NOT NULL,
    status_pengiriman ENUM('berhasil', 'gagal') NOT NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_riwayat_guru_tgl (id_guru, tanggal_absensi),
    FOREIGN KEY (id_guru) REFERENCES guru(id_guru) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================================================
-- DUMMY DATA INSERTION
-- ==================================================

-- Catatan Akun Testing Awal (Bcrypt Hash):
-- 1. ADMIN:          username: admin  | password: admin123  (role: admin)
-- 2. GURU:           username: guru   | password: guru123   (role: guru)
-- 3. KEPALA SEKOLAH: username: kepsek | password: kepsek123 (role: kepala_sekolah)

INSERT INTO users (id, nama, username, password, role, status) VALUES 
(1, 'Administrator', 'admin', '$2y$10$19dqXHNgS5ZTXngh/yMW/OhEih.rk3ZxGiH5uCb93K/3Dl2qUwrzS', 'admin', 'aktif'),
(2, 'Budi Santoso, S.Pd', 'guru', '$2y$10$z9q.RawEcy/vCsDyDh0tluNiltQ/8dxH8iJtPcM02Il7UYblXASoy', 'guru', 'aktif'),
(3, 'Siti Aminah, M.Pd', 'guru_siti', '$2y$10$z9q.RawEcy/vCsDyDh0tluNiltQ/8dxH8iJtPcM02Il7UYblXASoy', 'guru', 'aktif'),
(4, 'Drs. H. Ahmad Dahlan, M.Pd', 'kepsek', '$2y$10$L5tYH7Zxyi6gc7toJotz0ukSI4Fp2rIcVx6iIYdXnxtVjEm0RP/gi', 'kepala_sekolah', 'aktif');

INSERT INTO guru (id_guru, id_user, nip, nama_guru, jenis_kelamin, email, no_hp) VALUES 
(1, 2, '198001012005011001', 'Budi Santoso, S.Pd', 'L', 'budi.santoso@sekolah.sch.id', '081234567890'),
(2, 3, '198502022010022002', 'Siti Aminah, M.Pd', 'P', 'siti.aminah@sekolah.sch.id', '081987654321');

INSERT INTO kelas (id_kelas, nama_kelas, tingkat, jurusan) VALUES 
(1, 'X MIPA 1', 'X', 'MIPA'),
(2, 'XI IPS 2', 'XI', 'IPS'),
(3, 'XII Bahasa 1', 'XII', 'Bahasa');

INSERT INTO mata_pelajaran (id_mapel, nama_mapel, kode_mapel) VALUES 
(1, 'Matematika', 'MAT01'),
(2, 'Fisika', 'FIS01'),
(3, 'Bahasa Inggris', 'BIG01'),
(4, 'Sejarah', 'SEJ01');

INSERT INTO jadwal (id_jadwal, id_guru, id_mapel, id_kelas, hari, jam_mulai, jam_selesai) VALUES 
(1, 1, 1, 1, 'Senin', '07:30:00', '09:00:00'), -- Budi mengajar Matematika
(2, 2, 3, 2, 'Senin', '09:30:00', '11:00:00'); -- Siti mengajar B. Inggris

INSERT INTO pengaturan (nama_pengaturan, nilai_pengaturan) VALUES 
('jam_masuk_sekolah', '07:00:00'),
('jam_pulang_sekolah', '15:00:00'),
('toleransi_keterlambatan', '15'); -- Toleransi dalam menit

INSERT INTO absensi (id_absensi, id_guru, tanggal, jam_masuk, jam_pulang, status, metode_verifikasi) VALUES 
(1, 1, CURRENT_DATE(), '06:50:00', '15:10:00', 'Hadir', 'Manual'),
(2, 2, CURRENT_DATE(), '07:15:00', '15:00:00', 'Terlambat', 'Manual');
