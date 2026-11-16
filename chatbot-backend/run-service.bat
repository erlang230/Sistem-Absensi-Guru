@echo off
REM ============================================================
REM run-service.bat - Launcher Background untuk SAG Chatbot
REM Menjaga hanya ada 1 instance backend dan auto-recovery jika crash
REM ============================================================
setlocal
cd /d "C:\xampp\htdocs\SAG\chatbot-backend"

if not exist logs mkdir logs

REM Pastikan Node.js ada di PATH sistem
set "PATH=C:\Program Files\nodejs;%PATH%"

echo ============================================================ >> logs\service.log
echo [%DATE% %TIME%] Memulai Pengawas Layanan SAG Chatbot... >> logs\service.log

:LOOP
REM Periksa apakah port 3000 sudah digunakan oleh proses listening
netstat -ano | findstr /R /C:":3000 .*LISTENING" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo [%DATE% %TIME%] Port 3000 sudah aktif. Menunggu 15 detik... >> logs\service.log
    timeout /t 15 /nobreak >nul
    goto LOOP
)

echo [%DATE% %TIME%] Menjalankan server.js dengan node... >> logs\service.log

node server.js >> logs\output.log 2>> logs\error.log

echo [%DATE% %TIME%] Server berhenti dengan exit code %ERRORLEVEL%. Auto-recovery dalam 5 detik... >> logs\service.log
timeout /t 5 /nobreak >nul
goto LOOP
