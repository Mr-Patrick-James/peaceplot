@echo off
echo ================================================
echo  PeacePlot - Sync + Version Bump
echo ================================================

set SOURCE=C:\wamp64\www\peaceplot
set DEST=C:\wamp64\www\peaceplot\desktop\www
set PACKAGE=C:\wamp64\www\peaceplot\desktop\package.json

:: ── Read current version ─────────────────────────
for /f "tokens=2 delims=:, " %%a in ('findstr /i "\"version\"" "%PACKAGE%"') do set CURRENT_VERSION=%%~a

echo Current version: %CURRENT_VERSION%
echo.
set /p NEW_VERSION=Enter new version (or press Enter to keep %CURRENT_VERSION%): 

if "%NEW_VERSION%"=="" (
    set NEW_VERSION=%CURRENT_VERSION%
    echo Keeping version %CURRENT_VERSION%
) else (
    powershell -Command "(Get-Content '%PACKAGE%') -replace '\"version\": \"%CURRENT_VERSION%\"', '\"version\": \"%NEW_VERSION%\"' | Set-Content '%PACKAGE%'"
    echo Bumped to v%NEW_VERSION%
)

echo.
echo ── Cleaning old dist/ ──────────────────────────
if exist "%~dp0dist" (rmdir /s /q "%~dp0dist" && echo Cleaned dist/)

echo.
echo ── Syncing files ───────────────────────────────
robocopy "%SOURCE%" "%DEST%" /E /PURGE ^
  /XD "%SOURCE%\desktop" ^
  /XD "%SOURCE%\.git" ^
  /XD "%SOURCE%\node_modules" ^
  /XF "*.bat" ^
  /NFL /NDL /NJH

echo.
echo ── Restoring router.php ────────────────────────
copy /Y "%~dp0www\router.php" "%DEST%\router.php" >nul
echo router.php restored!

echo.
echo ================================================
echo  Done! Ready to build v%NEW_VERSION%
echo  Run: npm run build
echo ================================================
pause
