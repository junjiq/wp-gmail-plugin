@echo off
REM WordPress Plugin Build Tool (Batch Version)
REM WP Gmail Plugin用の簡易ビルドスクリプト

setlocal enabledelayedexpansion

REM 設定
set PLUGIN_NAME=wp-gmail-plugin
set VERSION=1.0.0
set OUTPUT_DIR=dist
set TEMP_DIR=%OUTPUT_DIR%\temp
set PLUGIN_DIR=%TEMP_DIR%\%PLUGIN_NAME%

echo.
echo === WordPress Plugin Build Tool ===
echo Building: %PLUGIN_NAME% v%VERSION%
echo.

REM 1. ディレクトリ作成
echo [INFO] Creating build directories...
if exist "%OUTPUT_DIR%" rmdir /s /q "%OUTPUT_DIR%"
mkdir "%OUTPUT_DIR%" 2>nul
mkdir "%TEMP_DIR%" 2>nul
mkdir "%PLUGIN_DIR%" 2>nul

REM 2. ファイルコピー
echo [INFO] Copying plugin files...

REM メインファイル
copy "wp-gmail-plugin.php" "%PLUGIN_DIR%\" >nul
copy "README.md" "%PLUGIN_DIR%\" >nul
copy "uninstall.php" "%PLUGIN_DIR%\" >nul

REM ディレクトリをコピー
xcopy "includes" "%PLUGIN_DIR%\includes\" /s /i /q >nul
xcopy "templates" "%PLUGIN_DIR%\templates\" /s /i /q >nul
xcopy "assets" "%PLUGIN_DIR%\assets\" /s /i /q >nul
xcopy "languages" "%PLUGIN_DIR%\languages\" /s /i /q >nul

echo [SUCCESS] Plugin files copied successfully

REM 3. ZIPファイル作成
echo [INFO] Creating ZIP package...

REM PowerShellを使用してZIPファイルを作成
powershell -command "Compress-Archive -Path '%PLUGIN_DIR%' -DestinationPath '%OUTPUT_DIR%\%PLUGIN_NAME%-v%VERSION%.zip' -CompressionLevel Optimal"

if exist "%OUTPUT_DIR%\%PLUGIN_NAME%-v%VERSION%.zip" (
    echo [SUCCESS] ZIP package created: %PLUGIN_NAME%-v%VERSION%.zip
) else (
    echo [ERROR] Failed to create ZIP package
    goto :error
)

REM 4. クリーンアップ
echo [INFO] Cleaning up temporary files...
rmdir /s /q "%TEMP_DIR%"

REM 5. 完了
echo.
echo === Build Completed Successfully ===
echo Package: %PLUGIN_NAME%-v%VERSION%.zip
echo Location: %OUTPUT_DIR%\
echo Ready for WordPress installation!
echo.

REM フォルダを開く
start "" "%OUTPUT_DIR%"

goto :end

:error
echo [ERROR] Build failed!
if exist "%TEMP_DIR%" rmdir /s /q "%TEMP_DIR%"
pause
exit /b 1

:end
pause
exit /b 0
