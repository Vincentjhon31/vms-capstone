@echo off
setlocal EnableExtensions
REM ======================================================================
REM  Bongabong VMS - run it on this computer
REM
REM  Double-click this file. It
REM    1. starts XAMPP's MySQL and Apache, if they are not running yet,
REM    2. copies the latest code into C:\xampp\htdocs\vms-bongabong
REM       (deploy-local.cmd), and
REM    3. opens http://localhost/vms-bongabong/ in your browser.
REM
REM  Keep this window open while you use the site. Press a key in it to
REM  stop the servers again - only the ones this window started. If they
REM  were already running (XAMPP Control Panel) they are left alone.
REM ======================================================================
title Bongabong VMS - localhost

set "XAMPP=C:\xampp"
set "SITE=http://localhost/vms-bongabong/"
REM Windows' own tools by full path, so another "ping" or "find" on PATH
REM (Git's, for one) cannot stand in for them.
set "SYS=%SystemRoot%\System32"
set "STARTED_MYSQL="
set "STARTED_APACHE="

if not exist "%XAMPP%\apache\bin\httpd.exe" (
  echo XAMPP was not found in %XAMPP%.
  echo Install XAMPP there, or change the XAMPP line near the top of this file.
  pause
  exit /b 1
)

echo.
echo  Bongabong VMS - starting on this computer
echo  ------------------------------------------

REM ---- 1. MySQL ---------------------------------------------------------
call :listening 3306
if not errorlevel 1 goto mysql_running
REM A MySQL that is still closing keeps its files locked for a few seconds,
REM and one started next to it stalls. Let it finish first.
call :running mysqld.exe
if errorlevel 1 goto mysql_start
echo  Waiting for a MySQL from an earlier run to finish ...
call :settle 3306 mysqld.exe 60
call :listening 3306
if not errorlevel 1 goto mysql_running
call :running mysqld.exe
if not errorlevel 1 goto mysql_stuck

:mysql_start
echo  Starting MySQL ...
start "VMS - MySQL (leave open)" /min /D "%XAMPP%" "%XAMPP%\mysql\bin\mysqld.exe" --defaults-file=mysql\bin\my.ini --standalone
set "STARTED_MYSQL=1"
call :wait 3306 60
if errorlevel 1 goto mysql_failed
goto apache

:mysql_running
echo  MySQL is already running.

REM ---- 2. Apache --------------------------------------------------------
:apache
call :listening 80
if not errorlevel 1 (
  echo  Apache is already running.
  goto copy
)
echo  Starting Apache ...
start "VMS - Apache (leave open)" /min /D "%XAMPP%" "%XAMPP%\apache\bin\httpd.exe"
set "STARTED_APACHE=1"
call :wait 80 30
if errorlevel 1 goto apache_failed

REM ---- 3. The latest code into htdocs ----------------------------------
:copy
echo  Copying the app into %XAMPP%\htdocs\vms-bongabong ...
call "%~dp0deploy-local.cmd" >nul
if errorlevel 1 (
  echo  The copy failed. Is a file in htdocs\vms-bongabong open in another program?
  pause
  goto stop
)

REM ---- 4. First run, or code newer than the database? ------------------
REM The 2026 Charter tables are the newest part of the schema; without them
REM the database still needs install.php.
set "PAGE=%SITE%"
"%XAMPP%\mysql\bin\mysql.exe" -u root -e "SELECT 1 FROM vms_bongabong.charter_offices LIMIT 1" >nul 2>&1
if errorlevel 1 (
  set "PAGE=%SITE%install.php"
  echo.
  echo  The database is missing or older than the code, so install.php opens.
  echo  If the system is already installed, sign in as admin, then open install.php again.
)

REM ---- 5. Does it answer? ------------------------------------------------
set "CODE="
if exist "%SYS%\curl.exe" for /f %%c in ('""%SYS%\curl.exe" -s -o nul -w "%%{http_code}" "%SITE%""') do set "CODE=%%c"
if defined CODE if not "%CODE%"=="200" if not "%CODE%"=="302" (
  echo.
  echo  Warning: %SITE% answered %CODE%.
  echo  If another program is using port 80 ^(Skype, IIS, another web server^),
  echo  close it and run this file again.
)

echo.
echo  Opening %PAGE%
start "" "%PAGE%"

echo.
echo  ------------------------------------------
echo   Site:   %SITE%
echo   Admin:  %SITE%Admin
echo  ------------------------------------------
echo.
if defined STARTED_MYSQL goto wait_for_stop
if defined STARTED_APACHE goto wait_for_stop
echo  MySQL and Apache were already running, so they stay running.
echo  Press any key to close this window.
pause >nul
exit /b 0

:wait_for_stop
echo  Leave this window open while you use the site.
echo  Press any key here to STOP the servers and close.
pause >nul

REM ---- Stop only what this window started ------------------------------
:stop
if not defined STARTED_APACHE goto stop_mysql
echo  Stopping Apache ...
set "APID="
if exist "%XAMPP%\apache\logs\httpd.pid" set /p APID=<"%XAMPP%\apache\logs\httpd.pid"
if defined APID (
  "%SYS%\taskkill.exe" /PID %APID% /T /F >nul 2>&1
) else (
  REM No PID file: port 80 was free when this window started Apache, so
  REM every httpd.exe here is the one it started.
  "%SYS%\taskkill.exe" /IM httpd.exe /F >nul 2>&1
)
if exist "%XAMPP%\apache\logs\httpd.pid" del "%XAMPP%\apache\logs\httpd.pid"

:stop_mysql
if not defined STARTED_MYSQL goto stopped
echo  Stopping MySQL ...
"%XAMPP%\mysql\bin\mysqladmin.exe" -u root shutdown >nul 2>&1
REM mysqladmin returns before mysqld has finished closing its files. Wait for
REM the process to end, so the next start does not find them still locked.
call :gone mysqld.exe 30
if errorlevel 1 (
  echo  MySQL did not close by itself, so it is being ended.
  "%SYS%\taskkill.exe" /IM mysqld.exe /F >nul 2>&1
)

:stopped
echo  Stopped.
"%SYS%\PING.EXE" -n 3 127.0.0.1 >nul
exit /b 0

:mysql_stuck
echo.
echo  A MySQL process is running but has not opened its port for a minute.
echo  End "mysqld.exe" in Task Manager ^(or Stop MySQL in the XAMPP Control
echo  Panel^), then run this file again.
pause
exit /b 1

:mysql_failed
echo.
echo  MySQL did not start within a minute. Open the XAMPP Control Panel to
echo  see why ^(often another MySQL is already using port 3306^).
pause
goto stop

:apache_failed
echo.
echo  Apache did not start within 30 seconds. Port 80 may be taken by
echo  another program ^(Skype, IIS, another web server^). The XAMPP Control
echo  Panel shows the reason.
pause
goto stop

REM ---- helpers -----------------------------------------------------------
REM ping, not timeout, for the one-second pauses: timeout fails at once
REM when the window's input is redirected.

:listening
REM errorlevel 0 when something is listening on TCP port %1
"%SYS%\NETSTAT.EXE" -ano | "%SYS%\findstr.exe" /R /C:":%~1 .*LISTENING" >nul
exit /b %errorlevel%

:running
REM errorlevel 0 when a process named %1 is running
"%SYS%\tasklist.exe" /FI "IMAGENAME eq %~1" /NH | "%SYS%\findstr.exe" /I /C:"%~1" >nul
exit /b %errorlevel%

:wait
REM waits up to %2 seconds for port %1; errorlevel 0 once it is listening
set /a "_tries=0"
:wait_loop
call :listening %~1
if not errorlevel 1 exit /b 0
set /a "_tries+=1"
if %_tries% GEQ %~2 exit /b 1
"%SYS%\PING.EXE" -n 2 127.0.0.1 >nul
goto wait_loop

:settle
REM waits up to %3 seconds until port %1 is listening or process %2 has ended
set /a "_tries=0"
:settle_loop
call :listening %~1
if not errorlevel 1 exit /b 0
call :running %~2
if errorlevel 1 exit /b 0
set /a "_tries+=1"
if %_tries% GEQ %~3 exit /b 1
"%SYS%\PING.EXE" -n 2 127.0.0.1 >nul
goto settle_loop

:gone
REM waits up to %2 seconds for process %1 to end; errorlevel 0 once it has
set /a "_tries=0"
:gone_loop
call :running %~1
if errorlevel 1 exit /b 0
set /a "_tries+=1"
if %_tries% GEQ %~2 exit /b 1
"%SYS%\PING.EXE" -n 2 127.0.0.1 >nul
goto gone_loop
