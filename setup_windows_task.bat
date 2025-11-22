@echo off
REM Setup Windows Scheduled Task for Automatic Billing System
REM Ky skedar krijon një task të planifikuar në Windows për ekzekutimin e faturimit automatik

echo.
echo ========================================
echo   SISTEMI I FATURIMIT AUTOMATIK
echo   Konfigurimi i Task Scheduler
echo ========================================
echo.

REM Kontrollo nëse jemi duke ekzekutuar si administrator
net session >nul 2>&1
if %errorLevel% NEQ 0 (
    echo GABIM: Ky skedar duhet të ekzekutohet si Administrator!
    echo Kliko të djathtën në skedarin .bat dhe zgjidh "Run as administrator"
    echo.
    pause
    exit /b 1
)

REM Përcakto path-in për PHP
set PHP_PATH="C:\xampp\php\php.exe"
if not exist %PHP_PATH% (
    echo GABIM: PHP nuk u gjet në %PHP_PATH%
    echo Ju lutemi përditësoni PHP_PATH në këtë skedar
    echo.
    pause
    exit /b 1
)

REM Përcakto path-in për skedarin e faturimit
set BILLING_SCRIPT="%~dp0auto_billing_system.php"
if not exist %BILLING_SCRIPT% (
    echo GABIM: Skedari i faturimit nuk u gjet në %BILLING_SCRIPT%
    echo.
    pause
    exit /b 1
)

echo Konfigurimi i Task Scheduler...
echo.

REM Fshi task-un ekzistues nëse ekziston
schtasks /delete /tn "NoteriaAutoBilling" /f >nul 2>&1

REM Krijo task-un e ri
schtasks /create ^
    /tn "NoteriaAutoBilling" ^
    /tr "%PHP_PATH% %BILLING_SCRIPT%" ^
    /sc daily ^
    /st 07:00 ^
    /ru "SYSTEM" ^
    /rl highest ^
    /f

if %errorLevel% EQU 0 (
    echo.
    echo ✓ Task Scheduler u konfigurua me sukses!
    echo.
    echo DETAJET E TASK-UT:
    echo - Emri: NoteriaAutoBilling
    echo - Ora e ekzekutimit: 07:00 çdo ditë
    echo - Skedari: %BILLING_SCRIPT%
    echo - Përdoruesi: SYSTEM
    echo.
    
    REM Shfaq task-un e krijuar
    echo Detajet e plotë të task-ut:
    schtasks /query /tn "NoteriaAutoBilling" /fo LIST /v
    
    echo.
    echo HAPAT E ARDHSHËM:
    echo 1. Testo task-un duke ekzekutuar: schtasks /run /tn "NoteriaAutoBilling"
    echo 2. Monitoroni log files në: billing_log.txt dhe billing_error.log
    echo 3. Hapni Task Scheduler për të bërë ndryshime shtesë nëse nevojitet
    echo.
    
    REM Pyet nëse dëshiron të testojë task-un tani
    set /p TEST_NOW="Dëshironi të testoni task-un tani? (y/n): "
    if /i "%TEST_NOW%"=="y" (
        echo.
        echo Duke testuar task-un...
        schtasks /run /tn "NoteriaAutoBilling"
        if %errorLevel% EQU 0 (
            echo ✓ Task-u u ekzekutua! Kontrolloni billing_log.txt për rezultatet.
        ) else (
            echo ✗ Gabim gjatë ekzekutimit të task-ut.
        )
    )
    
) else (
    echo.
    echo ✗ GABIM gjatë konfigurimit të Task Scheduler!
    echo Kontrolloni nëse jeni duke ekzekutuar si Administrator.
)

echo.
echo KOMANDA PËR MENAXHIMIN E TASK-UT:
echo - Shiko task-un: schtasks /query /tn "NoteriaAutoBilling"
echo - Ekzekuto manualisht: schtasks /run /tn "NoteriaAutoBilling"
echo - Fshi task-un: schtasks /delete /tn "NoteriaAutoBilling" /f
echo - Ndalo task-un: schtasks /change /tn "NoteriaAutoBilling" /disable
echo - Aktivizo task-un: schtasks /change /tn "NoteriaAutoBilling" /enable
echo.

pause