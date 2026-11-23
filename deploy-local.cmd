@echo off
REM Copy this app into XAMPP so it runs at http://localhost/vms-bongabong/
set TARGET=C:\xampp\htdocs\vms-bongabong
echo Deploying to %TARGET% ...
REM /XD .git: the source folder is the git working copy, and /MIR was
REM dragging the whole history into htdocs. The .htaccess 404s it, so it
REM was never served - but a few thousand objects copied on every deploy
REM is pointless, and one day the copy lands somewhere without that rule.
robocopy "%~dp0." "%TARGET%" /MIR /XD .git /XF deploy-local.cmd start-local.bat /NFL /NDL /NJH /NJS
if %ERRORLEVEL% GEQ 8 (echo FAILED & exit /b 1)
echo Done. Open http://localhost/vms-bongabong/install.php
exit /b 0
