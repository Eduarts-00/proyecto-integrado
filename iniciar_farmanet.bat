@echo off
chcp 65001 >nul
title FarmaNet Pro - Servidor de Desarrollo (Laravel 11 + MySQL XAMPP)
cd /d "%~dp0"

echo ================================================================
echo   FarmaNet Pro - Sistema Web Farmacia Privada (INACAP)
echo ================================================================
echo.

REM 1. Verificar si MySQL (mysqld.exe) de XAMPP esta en ejecucion
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe" >NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] Motor relacional MySQL/MariaDB ya se encuentra activo.
) else (
    echo [*] Iniciando motor MySQL/MariaDB de XAMPP en segundo plano...
    start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    timeout /t 3 /nobreak >nul
    echo [OK] Motor MySQL/MariaDB iniciado en 127.0.0.1:3306.
)

echo [*] Abriendo navegador en http://127.0.0.1:8000 ...
start "" "http://127.0.0.1:8000"

echo [*] Ejecutando servidor Laravel (presiona Ctrl+C para detener)...
echo.
"C:\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8000
pause
