@echo off
chcp 65001 >nul
title FarmaNet Pro - Detener Servicios
cd /d "%~dp0"

echo ================================================================
echo   FarmaNet Pro - Apagando Servidor Web y Base de Datos
echo ================================================================
echo.

echo [*] Deteniendo servidor de desarrollo Laravel (php.exe artisan serve)...
taskkill /F /IM php.exe >NUL 2>&1
if "%ERRORLEVEL%"=="0" (
    echo [OK] Servidor Laravel detenido correctamente.
) else (
    echo [i] El servidor Laravel ya estaba apagado.
)

echo [*] Deteniendo motor MySQL/MariaDB de XAMPP...
"C:\xampp\mysql\bin\mysqladmin.exe" -u root -h 127.0.0.1 -P 3306 shutdown >NUL 2>&1
if "%ERRORLEVEL%"=="0" (
    echo [OK] Motor MySQL/MariaDB apagado de forma segura.
) else (
    taskkill /F /IM mysqld.exe >NUL 2>&1
    echo [i] Motor MySQL/MariaDB detenido.
)

echo.
echo [LISTO] Todos los servicios de FarmaNet Pro han sido apagados.
timeout /t 3
