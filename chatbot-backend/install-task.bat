@echo off
REM ============================================================
REM Pasang Task Scheduler untuk SAG Chatbot Backend
REM Menjalankan backend otomatis saat Windows Startup & Login
REM ============================================================
setlocal EnableDelayedExpansion

set TASK_NAME=SAG_Chatbot_Backend
set XML_PATH=C:\xampp\htdocs\SAG\chatbot-backend\task-template.xml
set VBS_PATH=C:\xampp\htdocs\SAG\chatbot-backend\start-hidden.vbs

echo ============================================================
echo   PEMASANGAN LAYANAN OTOMATIS SAG CHATBOT BACKEND
echo ============================================================
echo.

REM Periksa apakah running sebagai Administrator
net session >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [PERINGATAN] File ini belum dijalankan sebagai Administrator!
    echo Silakan klik kanan file 'install-task.bat' lalu pilih 'Run as administrator'.
    echo.
    echo Mencoba mendaftarkan task untuk user saat ini...
    schtasks /create /tn "%TASK_NAME%" /tr "wscript.exe \"%VBS_PATH%\"" /sc onlogon /f
) else (
    echo [INFO] Terdeteksi izin Administrator penuh.
    if exist "%XML_PATH%" (
        echo Mendaftarkan task dari template XML tingkat lanjut...
        schtasks /create /tn "%TASK_NAME%" /xml "%XML_PATH%" /f
    ) else (
        echo Mendaftarkan task onlogon & startup...
        schtasks /create /tn "%TASK_NAME%" /tr "wscript.exe \"%VBS_PATH%\"" /sc onlogon /rl highest /f
    )
)

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ============================================================
    echo [SUKSES] Task Scheduler '%TASK_NAME%' berhasil dipasang!
    echo Backend Node.js akan otomatis berjalan saat Windows login/startup.
    echo.
    echo Menjalankan layanan sekarang juga...
    schtasks /run /tn "%TASK_NAME%" >nul 2>&1
    echo ============================================================
) else (
    echo.
    echo ============================================================
    echo [GAGAL] Tidak dapat membuat task.
    echo Pastikan Anda memilih 'Run as administrator' saat menjalankan file ini.
    echo ============================================================
)

echo.
pause
