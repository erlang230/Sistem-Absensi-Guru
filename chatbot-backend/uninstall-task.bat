@echo off
REM ============================================================
REM Hapus Task Scheduler SAG Chatbot Backend
REM ============================================================

set TASK_NAME=SAG_Chatbot_Backend

echo Menghapus Task Scheduler: %TASK_NAME%...
schtasks /delete /tn "%TASK_NAME%" /f

if %ERRORLEVEL% EQU 0 (
    echo [SUKSES] Task Scheduler %TASK_NAME% berhasil dihapus.
) else (
    echo [INFO] Task tidak ditemukan atau sudah dihapus.
)

pause

