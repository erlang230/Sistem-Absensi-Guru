# Sistem Absensi Guru (SAG)

## 1.  Identitas Mahasiswa & Proyek RPL

| Atribut | Keterangan |
|---|---|
| **Nama** | Rafi Erlangga Dwi Tama |
| **NIM** | [41257240 |
| **Kelas** | 1C - 2025 |
| **Program Studi** | Teknik Informatika |
| **Tema Topik Kelompok** | Sistem Absensi Untuk SMK |
| **Institusi Mitra / Studi Kasus** | SMK NU Wargabinangun |
| **Repositori Publik Pribadi** | [Isi Link Repository GitHub] |
| **Desain UI/UX (Figma)** | [Isi Link Figma] |
| **Berkas Dokumen Lengkap (PDF)** | [Isi Link / Lokasi File PDF] |
---

## 2. Tema atau Topik Kelompok

**Sistem Absensi Guru (SAG) Berbasis Web dengan Integrasi Artificial Intelligence (AI)**

Sistem ini merupakan aplikasi berbasis web yang digunakan untuk mengelola proses absensi guru, jadwal mengajar, data guru, laporan kehadiran, pengingat absensi melalui email, serta menyediakan chatbot AI untuk membantu pengguna mendapatkan informasi berdasarkan data yang tersedia pada sistem.

---

# 3. Deskripsi Aplikasi

## 3.1 Penjelasan Aplikasi

Sistem Absensi Guru (SAG) adalah aplikasi berbasis web yang dibuat untuk membantu sekolah dalam mengelola dan memantau kehadiran guru secara terkomputerisasi.

Aplikasi memiliki beberapa jenis pengguna, yaitu:

- Admin
- Guru
- Kepala Sekolah

Admin dapat mengelola data guru, kelas, mata pelajaran, jadwal, pengaturan absensi, email, laporan, serta chatbot AI.

Guru dapat melakukan absensi masuk dan pulang, melakukan verifikasi wajah, melihat riwayat absensi, melihat jadwal mengajar, serta menggunakan chatbot AI.

Kepala Sekolah dapat memantau data absensi, melihat jadwal, dan melihat laporan kehadiran guru.

Sistem juga memiliki fitur pengingat melalui email berdasarkan kondisi absensi guru, seperti belum absen, terlambat, atau tidak melakukan absensi.

---

## 3.2 Tujuan Pembuatan Aplikasi

Tujuan pembuatan Sistem Absensi Guru (SAG) adalah:

1. Mempermudah proses pencatatan absensi guru.
2. Mengurangi proses pencatatan absensi secara manual.
3. Membantu Admin memantau kehadiran guru.
4. Menentukan status absensi berdasarkan waktu yang telah ditentukan.
5. Membantu mengetahui guru yang belum melakukan absensi.
6. Memberikan informasi mengenai keterlambatan guru.
7. Mencatat guru yang tidak melakukan absensi setelah batas waktu.
8. Mempermudah pengelolaan jadwal guru.
9. Mempermudah pembuatan dan pemantauan laporan absensi.
10. Memanfaatkan AI untuk membantu pengguna mendapatkan informasi dari sistem.

---

## 3.3 Permasalahan yang Ingin Diselesaikan

Aplikasi ini dibuat untuk menyelesaikan beberapa permasalahan dalam pengelolaan absensi guru, antara lain:

- Proses absensi yang masih dapat dilakukan secara manual.
- Sulitnya Admin memantau guru yang sudah atau belum melakukan absensi.
- Sulit mengetahui guru yang terlambat.
- Tidak adanya pemberitahuan secara langsung kepada guru yang belum melakukan absensi.
- Pencatatan guru yang tidak melakukan absensi setelah batas waktu.
- Pengelolaan jadwal guru yang kurang terstruktur.
- Pembuatan laporan absensi yang membutuhkan waktu.
- Kesulitan pengguna dalam mendapatkan informasi tertentu dari data sistem.

---

# 4. Fitur Aplikasi

## 4.1 Login dan Hak Akses

Sistem menyediakan login berdasarkan role pengguna.

Role yang tersedia:

- Admin
- Guru
- Kepala Sekolah

Setiap pengguna mendapatkan akses sesuai dengan hak aksesnya.

---

## 4.2 Manajemen Data Guru

Admin dapat:

- Menambahkan data guru.
- Mengubah data guru.
- Menghapus data guru.
- Melihat detail guru.
- Mengelola informasi email dan nomor telepon.
- Mengelola data yang berkaitan dengan akun Google guru.

---

## 4.3 Manajemen Data Kelas

Admin dapat mengelola data kelas yang digunakan dalam sistem.

Data kelas dapat digunakan untuk mendukung pengelolaan jadwal mengajar dan informasi akademik.

---

## 4.4 Manajemen Mata Pelajaran

Admin dapat:

- Menambahkan mata pelajaran.
- Mengubah mata pelajaran.
- Menghapus mata pelajaran.
- Mengatur kode mata pelajaran.

---

## 4.5 Manajemen Jadwal

Admin dapat mengelola jadwal mengajar yang terdiri dari:

- Guru.
- Mata pelajaran.
- Kelas.
- Hari.
- Jam mulai.
- Jam selesai.

Guru dapat melihat jadwal mengajarnya, sedangkan Kepala Sekolah dapat melihat jadwal yang tersedia pada sistem.

---

## 4.6 Absensi Guru

Guru dapat melakukan:

- Absensi masuk.
- Absensi pulang.
- Verifikasi wajah.

Data absensi menyimpan informasi seperti:

- Tanggal.
- Jam masuk.
- Jam pulang.
- Status.
- Metode verifikasi.

Status absensi yang digunakan antara lain:

- Hadir.
- Terlambat.
- Izin.
- Sakit.
- Alpha.

---

## 4.7 Verifikasi Wajah

Sistem menyediakan fitur registrasi dan verifikasi wajah untuk mendukung proses absensi menggunakan teknologi **AI Face Recognition**.

Guru melakukan registrasi wajah terlebih dahulu, kemudian data tersebut digunakan sebagai bagian dari proses verifikasi absensi.

---

## 4.8 Pengaturan Absensi

Admin dapat mengatur ketentuan waktu absensi, seperti:

- Jam masuk sekolah.
- Toleransi keterlambatan.
- Batas waktu Alpha.
- Jam pulang sekolah.

Pengaturan tersebut digunakan sistem untuk menentukan status absensi secara otomatis.

Contoh:

```text
Jam masuk       : 07:00
Toleransi       : 15 menit
Batas Alpha     : 09:00
```

Dengan pengaturan tersebut sistem dapat membedakan guru yang hadir, terlambat, atau tidak melakukan absensi.

---

## 4.9 Monitoring Absensi

Admin dapat memantau kondisi absensi guru.

Informasi yang dapat dipantau meliputi:

- Sudah absen.
- Belum absen.
- Terlambat.
- Alpha/Tidak Absen.

Monitoring digunakan untuk membantu Admin mengetahui kondisi kehadiran guru pada hari tersebut.

---

## 4.10 Pengingat Absensi melalui Email

Sistem menyediakan fitur pengingat absensi kepada guru melalui email.

Jenis pemberitahuan dapat disesuaikan dengan kondisi absensi:

### Belum Absen

Memberikan pengingat kepada guru agar segera melakukan absensi.

### Terlambat

Memberikan informasi bahwa guru belum melakukan absensi dan telah melewati jam masuk.

### Tidak Absen / Alpha

Memberikan informasi bahwa guru tercatat tidak melakukan absensi karena telah melewati batas waktu yang ditentukan.

Sistem menggunakan SMTP dan PHPMailer untuk proses pengiriman email.

---

## 4.11 Riwayat Pengingat

Sistem menyimpan riwayat pengiriman email pengingat.

Informasi yang dapat dicatat antara lain:

- Guru.
- Email tujuan.
- Jenis pengingat.
- Tanggal.
- Status pengiriman.
- Keterangan.

---

## 4.12 Google Account

Sistem menyediakan fitur untuk menghubungkan akun Google guru.

Fitur ini digunakan untuk:

- Menghubungkan email Google guru.
- Melakukan proses verifikasi.
- Menyimpan status hubungan akun Google.
- Mendukung penggunaan email Google dalam sistem.

---

## 4.13 Laporan Absensi

Sistem menyediakan laporan mengenai data absensi guru.

Laporan dapat digunakan untuk melihat:

- Data kehadiran.
- Keterlambatan.
- Alpha.
- Riwayat absensi.

Sistem juga menyediakan fitur ekspor laporan dalam format:

- PDF.
- Excel.

---

## 4.14 Chatbot AI

Sistem menyediakan chatbot AI yang dapat digunakan oleh pengguna untuk mendapatkan informasi mengenai sistem.

Chatbot dapat membantu menjawab pertanyaan seperti:

```text
Siapa saya?

Siapa saja guru yang belum absen?

Jadwal saya hari ini?

Siapa guru yang terlambat?

Bagaimana status absensi saya?
```

Jawaban chatbot disesuaikan dengan role pengguna dan data yang tersedia pada sistem.

---

# 5. Fitur AI

## 5.1 Penjelasan Fitur AI

SAG menggunakan Artificial Intelligence (AI) dalam dua bagian utama:

1. **AI Face Recognition**
2. **AI Chatbot**

AI Face Recognition digunakan untuk mendukung proses verifikasi wajah saat absensi.

AI Chatbot digunakan sebagai asisten informasi yang dapat membantu pengguna memperoleh informasi berdasarkan data SAG.

---

## 5.2 Fungsi AI dalam Aplikasi

AI Chatbot berfungsi untuk:

- Memahami pertanyaan pengguna.
- Memahami role pengguna.
- Mengambil konteks dari sistem.
- Mengakses informasi guru.
- Mengakses informasi absensi.
- Mengakses informasi jadwal.
- Mengakses informasi kelas dan mata pelajaran.
- Memberikan jawaban berdasarkan data SAG.

Contoh:

**Pertanyaan:**

```text
Siapa saja guru yang belum absen?
```

Chatbot akan mengambil data absensi dari sistem kemudian memberikan daftar guru yang belum melakukan absensi.

---

## 5.3 Teknologi atau Model AI yang Digunakan

Sistem menggunakan backend Node.js sebagai penghubung antara aplikasi SAG dengan provider AI.

Provider AI yang disiapkan pada sistem:

### Primary AI Provider
**Groq**

Model AI dikonfigurasi melalui sistem backend dan dapat diubah melalui pengaturan chatbot.

### Fallback AI Provider
**Google Gemini**

Gemini digunakan sebagai provider cadangan apabila provider utama mengalami kendala seperti:

- Rate limit.
- Timeout.
- Gangguan server.
- Kegagalan koneksi.

Konfigurasi provider dan model dilakukan melalui backend chatbot.

---

## 5.4 Cara AI Terintegrasi dengan Aplikasi

Integrasi AI pada SAG menggunakan arsitektur berikut:

```text
Pengguna
   ↓
Chatbot pada Website SAG
   ↓
API Chat Proxy
   ↓
Backend Node.js
   ↓
Primary AI Provider
   ↓
Jika gagal
   ↓
Fallback AI Provider
   ↓
Backend
   ↓
Website SAG
   ↓
Jawaban ditampilkan kepada pengguna
```

Sebelum mengirim pertanyaan ke AI, sistem dapat menyiapkan konteks dari database SAG, seperti:

- Data pengguna.
- Role pengguna.
- Data guru.
- Data absensi.
- Data jadwal.
- Data kelas.
- Data mata pelajaran.

Dengan demikian chatbot tidak hanya memberikan jawaban umum, tetapi dapat memberikan jawaban berdasarkan informasi yang tersedia pada aplikasi SAG.

---

## 5.5 Backend AI

Backend chatbot berada pada folder:

```text
chatbot-backend/
```

Backend menggunakan:

- Node.js
- Express.js
- CORS
- dotenv

Backend berjalan pada port:

```text
3000
```

Endpoint kesehatan backend digunakan untuk mengetahui apakah layanan chatbot sedang berjalan.

---

# 6. Link Video YouTube

**Link Video YouTube:**

[ISI LINK VIDEO YOUTUBE DI SINI]

---

# 7. Panduan Instalasi Aplikasi

## 7.1 Persyaratan Perangkat dan Software

Perangkat yang diperlukan:

- Komputer/Laptop.
- Windows.
- Koneksi internet.

Software yang diperlukan:

- XAMPP.
- PHP.
- MySQL/MariaDB.
- Apache.
- Node.js dan npm.
- Browser seperti Google Chrome atau Microsoft Edge.

---

# 8. Instalasi Aplikasi

## 8.1 Menempatkan Project

Ekstrak project SAG ke folder:

```text
C:\xampp\htdocs\SAG
```

Pastikan struktur utama project seperti:

```text
C:\xampp\htdocs\SAG
├── config
├── includes
├── assets
├── chatbot-backend
├── dashboard_admin.php
├── dashboard_guru.php
├── dashboard_kepala_sekolah.php
├── login.php
├── database.sql
└── ...
```

---

## 8.2 Menjalankan XAMPP

Buka XAMPP Control Panel.

Aktifkan:

```text
Apache
MySQL
```

Pastikan kedua service tersebut berjalan.

---

# 9. Konfigurasi Database

## 9.1 Membuka phpMyAdmin

Buka browser dan akses:

```text
http://localhost/phpmyadmin
```

---

## 9.2 Membuat Database

Database yang digunakan oleh SAG adalah:

```text
db_absensi_guru_ai
```

Buka menu **Import** pada phpMyAdmin.

Pilih file:

```text
database.sql
```

yang terdapat pada folder:

```text
C:\xampp\htdocs\SAG\
```

Kemudian jalankan proses import.

File database tersebut akan membuat database:

```text
db_absensi_guru_ai
```

beserta tabel yang diperlukan oleh aplikasi.

---

## 9.3 Konfigurasi Koneksi Database

File konfigurasi database berada di:

```text
config/database.php
```

Konfigurasi default:

```php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_absensi_guru_ai";
```

Jika konfigurasi MySQL pada komputer berbeda, sesuaikan nilai tersebut.

---

# 10. Konfigurasi Email

Sistem menggunakan SMTP untuk mengirim email.

File konfigurasi email berada pada:

```text
config/mail_config.json
```

Contoh konfigurasi:

```json
{
  "smtp_host": "smtp.gmail.com",
  "smtp_port": 587,
  "smtp_secure": "tls",
  "smtp_auth": true,
  "smtp_username": "alamat_email_anda@gmail.com",
  "smtp_password": "APP_PASSWORD",
  "from_email": "alamat_email_anda@gmail.com",
  "from_name": "Sistem Absensi Guru (SAG)"
}
```

Untuk Gmail, gunakan **Google App Password**, bukan password Gmail biasa.

Jangan memasukkan API key, password, atau App Password ke dalam repository publik.

---

# 11. Konfigurasi Environment Variable

Backend AI menggunakan file:

```text
chatbot-backend/.env
```

Konfigurasi yang diperlukan antara lain:

```env
PORT=3000

GROQ_API_KEY=ISI_API_KEY_GROQ
GROQ_MODEL=MODEL_GROQ
GROQ_STATUS=aktif

GEMINI_API_KEY=ISI_API_KEY_GEMINI
GEMINI_MODEL=MODEL_GEMINI
GEMINI_STATUS=aktif

AI_PRIMARY_PROVIDER=groq
AI_BACKUP_PROVIDER=gemini
```

API key harus diisi pada komputer/server tempat aplikasi dijalankan.

**Jangan menggunakan API key yang terdapat pada contoh README atau membagikan API key melalui repository publik.**

---

# 12. Instalasi Dependencies Backend AI

Buka Command Prompt atau Terminal.

Masuk ke folder:

```text
C:\xampp\htdocs\SAG\chatbot-backend
```

Kemudian jalankan:

```bash
npm install
```

Perintah tersebut akan memasang dependency Node.js berdasarkan file:

```text
package.json
```

Dependency utama yang digunakan antara lain:

- Express
- CORS
- dotenv

---

# 13. Menjalankan Backend AI

Masih pada folder:

```text
C:\xampp\htdocs\SAG\chatbot-backend
```

jalankan:

```bash
npm start
```

Backend akan berjalan pada:

```text
http://localhost:3000
```

---

# 14. Menjalankan Backend Secara Otomatis

Project menyediakan file:

```text
install-task.bat
```

File tersebut digunakan untuk memasang Task Scheduler Windows agar backend chatbot dapat dijalankan secara otomatis.

Untuk memasangnya:

1. Buka folder:

```text
C:\xampp\htdocs\SAG\chatbot-backend
```

2. Klik kanan:

```text
install-task.bat
```

3. Pilih **Run as administrator**.
4. Ikuti proses instalasi sampai selesai.

Sistem juga menyediakan:

```text
run-service.bat
```

untuk menjalankan backend dengan mekanisme pemantauan dan pemulihan apabila proses backend berhenti.

---

# 15. Menjalankan Aplikasi SAG

Pastikan:

```text
Apache  → Running
MySQL   → Running
```

Kemudian buka browser dan akses:

```text
http://localhost/SAG/
```

atau:

```text
http://localhost/SAG/login.php
```

---

# 16. Mengakses Chatbot AI

Pastikan backend Node.js sudah berjalan.

Alamat backend:

```text
http://localhost:3000
```

Setelah itu login ke aplikasi SAG dan gunakan fitur chatbot sesuai dengan hak akses pengguna.

Status chatbot juga dapat dikontrol melalui menu pengaturan chatbot pada Dashboard Admin.

---

# 17. Struktur Teknologi

Sistem SAG menggunakan beberapa teknologi:

| Teknologi | Fungsi |
|---|---|
| HTML | Struktur halaman |
| CSS | Tampilan aplikasi |
| JavaScript | Interaksi pada halaman |
| Bootstrap | Komponen antarmuka |
| PHP | Backend aplikasi utama |
| MySQL/MariaDB | Database |
| Node.js | Backend chatbot AI |
| Express.js | API backend chatbot |
| PHPMailer | Pengiriman email |
| SMTP | Komunikasi email |
| AI Face Recognition | Verifikasi wajah |
| Groq | Primary AI Provider |
| Google Gemini | Fallback AI Provider |

---

# 18. Catatan Keamanan

Jangan membagikan file yang berisi:

- API Key.
- Password database.
- Password SMTP.
- Google App Password.
- Credential lainnya.

File konfigurasi yang berisi credential sebaiknya tidak dimasukkan ke repository publik.

Untuk penggunaan repository, gunakan file contoh konfigurasi seperti:

```text
mail_config.example.json
```

dan buat konfigurasi lokal sendiri.

---

# 19. Kesimpulan

Sistem Absensi Guru (SAG) merupakan aplikasi berbasis web yang dirancang untuk membantu sekolah dalam mengelola absensi guru secara lebih terstruktur.

Sistem tidak hanya menyediakan fitur absensi, tetapi juga mendukung pengelolaan data guru, kelas, mata pelajaran, jadwal, laporan, pengingat melalui email, verifikasi wajah, integrasi Google Account, serta chatbot AI.

Dengan adanya integrasi AI, pengguna dapat memperoleh informasi dari data SAG melalui percakapan dengan chatbot. Penggunaan Primary dan Fallback AI Provider juga memungkinkan sistem tetap memiliki alternatif ketika provider AI utama mengalami gangguan atau batas penggunaan.
