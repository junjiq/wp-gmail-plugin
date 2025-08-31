# WordPress Plugin Build Tool
# WP Gmail Plugin用のビルドスクリプト

param(
    [Parameter(Mandatory=$false)]
    [string]$Version = "1.0.0",
    
    [Parameter(Mandatory=$false)]
    [string]$OutputDir = "dist",
    
    [Parameter(Mandatory=$false)]
    [switch]$SkipTests = $false,
    
    [Parameter(Mandatory=$false)]
    [switch]$VerboseOutput = $false
)

# 設定
$PluginName = "wp-gmail-plugin"
$PluginMainFile = "wp-gmail-plugin.php"
$BuildDir = Join-Path $PSScriptRoot $OutputDir
$TempDir = Join-Path $BuildDir "temp"
$PluginDir = Join-Path $TempDir $PluginName

# カラー出力用の関数
function Write-ColorOutput {
    param(
        [string]$Message,
        [string]$Color = "White"
    )
    
    $colorMap = @{
        "Red" = [System.ConsoleColor]::Red
        "Green" = [System.ConsoleColor]::Green
        "Yellow" = [System.ConsoleColor]::Yellow
        "Blue" = [System.ConsoleColor]::Blue
        "Magenta" = [System.ConsoleColor]::Magenta
        "Cyan" = [System.ConsoleColor]::Cyan
        "White" = [System.ConsoleColor]::White
    }
    
    Write-Host $Message -ForegroundColor $colorMap[$Color]
}

# ログ出力
function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"
    
    switch ($Level) {
        "ERROR" { Write-ColorOutput $logMessage "Red" }
        "WARN"  { Write-ColorOutput $logMessage "Yellow" }
        "SUCCESS" { Write-ColorOutput $logMessage "Green" }
        default { Write-ColorOutput $logMessage "White" }
    }
    
    if ($VerboseOutput) {
        Add-Content -Path (Join-Path $BuildDir "build.log") -Value $logMessage
    }
}

# エラーハンドリング
function Handle-Error {
    param([string]$ErrorMessage)
    Write-Log "Build failed: $ErrorMessage" "ERROR"
    if (Test-Path $TempDir) {
        Remove-Item $TempDir -Recurse -Force
    }
    exit 1
}

# バージョン更新
function Update-Version {
    param([string]$FilePath, [string]$NewVersion)
    
    try {
        $content = Get-Content $FilePath -Raw
        
        # プラグインヘッダーのバージョンを更新
        $content = $content -replace "Version:\s*[\d\.]+", "Version: $NewVersion"
        
        # 定数のバージョンを更新
        $content = $content -replace "define\('WP_GMAIL_PLUGIN_VERSION',\s*'[\d\.]+'\)", "define('WP_GMAIL_PLUGIN_VERSION', '$NewVersion')"
        
        Set-Content $FilePath -Value $content -Encoding UTF8
        Write-Log "Updated version to $NewVersion in $FilePath" "SUCCESS"
    }
    catch {
        Handle-Error "Failed to update version in $FilePath`: $($_.Exception.Message)"
    }
}

# ファイル検証
function Test-PluginFiles {
    $requiredFiles = @(
        $PluginMainFile,
        "README.md",
        "includes/class-gmail-api-client.php",
        "includes/class-gmail-oauth.php",
        "includes/class-gmail-email-manager.php",
        "includes/functions.php",
        "templates/admin-main.php",
        "templates/admin-settings.php",
        "assets/css/style.css",
        "assets/js/script.js"
    )
    
    $missingFiles = @()
    foreach ($file in $requiredFiles) {
        if (-not (Test-Path $file)) {
            $missingFiles += $file
        }
    }
    
    if ($missingFiles.Count -gt 0) {
        Handle-Error "Missing required files: $($missingFiles -join ', ')"
    }
    
    Write-Log "All required files found" "SUCCESS"
}

# PHP構文チェック
function Test-PHPSyntax {
    if ($SkipTests) {
        Write-Log "Skipping PHP syntax tests" "WARN"
        return
    }
    
    $phpFiles = Get-ChildItem -Path . -Filter "*.php" -Recurse | Where-Object { $_.FullName -notmatch "\\dist\\" }
    
    foreach ($file in $phpFiles) {
        try {
            $result = php -l $file.FullName 2>&1
            if ($LASTEXITCODE -ne 0) {
                Handle-Error "PHP syntax error in $($file.Name): $result"
            }
        }
        catch {
            Write-Log "PHP not found, skipping syntax check" "WARN"
            break
        }
    }
    
    Write-Log "PHP syntax check completed" "SUCCESS"
}

# ファイルコピー（除外ファイル対応）
function Copy-PluginFiles {
    $excludePatterns = @(
        "*.log",
        "*.tmp",
        ".git*",
        ".vscode",
        ".idea",
        "node_modules",
        "dist",
        "tests",
        "*.dev.*",
        "build-plugin.ps1",
        "package-lock.json",
        "composer.lock",
        ".env*"
    )
    
    # 除外パターンを正規表現に変換
    $excludeRegex = ($excludePatterns | ForEach-Object { 
        $_ -replace '\*', '.*' -replace '\.', '\.' 
    }) -join '|'
    
    Get-ChildItem -Path . -Recurse | ForEach-Object {
        $relativePath = $_.FullName.Substring($PSScriptRoot.Length + 1)
        
        # 除外パターンにマッチするかチェック
        if ($relativePath -notmatch $excludeRegex -and $_.FullName -notmatch "\\dist\\") {
            $destPath = Join-Path $PluginDir $relativePath
            $destDir = Split-Path $destPath -Parent
            
            if (-not (Test-Path $destDir)) {
                New-Item -ItemType Directory -Path $destDir -Force | Out-Null
            }
            
            if ($_.PSIsContainer -eq $false) {
                Copy-Item $_.FullName $destPath -Force
                if ($VerboseOutput) {
                    Write-Log "Copied: $relativePath"
                }
            }
        }
    }
    
    Write-Log "Plugin files copied to temporary directory" "SUCCESS"
}

# 最適化とクリーンアップ
function Optimize-Plugin {
    # CSS/JSの最小化（簡易版）
    $cssFiles = Get-ChildItem -Path $PluginDir -Filter "*.css" -Recurse
    $jsFiles = Get-ChildItem -Path $PluginDir -Filter "*.js" -Recurse
    
    foreach ($file in $cssFiles) {
        try {
            $content = Get-Content $file.FullName -Raw
            # 基本的なCSS最小化
            $content = $content -replace '/\*.*?\*/', '' -replace '\s+', ' ' -replace ';\s*}', '}'
            Set-Content $file.FullName -Value $content.Trim() -Encoding UTF8
        }
        catch {
            Write-Log "Failed to optimize $($file.Name)" "WARN"
        }
    }
    
    # 不要なファイルを削除
    $unnecessaryFiles = @(
        "*.md",
        "*.txt",
        "*.log"
    )
    
    foreach ($pattern in $unnecessaryFiles) {
        Get-ChildItem -Path $PluginDir -Filter $pattern -Recurse | Remove-Item -Force
    }
    
    Write-Log "Plugin optimization completed" "SUCCESS"
}

# ZIPファイル作成
function Create-ZipPackage {
    $zipFileName = "$PluginName-v$Version.zip"
    $zipPath = Join-Path $BuildDir $zipFileName
    
    # 既存のZIPファイルを削除
    if (Test-Path $zipPath) {
        Remove-Item $zipPath -Force
    }
    
    try {
        # PowerShell 5.0以降のCompress-Archiveを使用
        Compress-Archive -Path $PluginDir -DestinationPath $zipPath -CompressionLevel Optimal
        
        $zipSize = (Get-Item $zipPath).Length
        $zipSizeMB = [math]::Round($zipSize / 1MB, 2)
        
        Write-Log "ZIP package created: $zipFileName ($zipSizeMB MB)" "SUCCESS"
        return $zipPath
    }
    catch {
        Handle-Error "Failed to create ZIP package: $($_.Exception.Message)"
    }
}

# インストール情報生成
function Generate-InstallInfo {
    $infoFile = Join-Path $BuildDir "install-info.json"
    
    $installInfo = @{
        plugin_name = "WP Gmail Plugin"
        version = $Version
        file_name = "$PluginName-v$Version.zip"
        build_date = (Get-Date -Format "yyyy-MM-dd HH:mm:ss")
        php_version_required = "7.4"
        wordpress_version_required = "5.0"
        installation_steps = @(
            "1. Download the ZIP file",
            "2. Go to WordPress Admin → Plugins → Add New",
            "3. Click 'Upload Plugin'",
            "4. Choose the ZIP file and click 'Install Now'",
            "5. Activate the plugin",
            "6. Go to Gmail → Settings to configure API credentials"
        )
        configuration_required = @{
            google_cloud_console = "Create OAuth 2.0 credentials"
            gmail_api = "Enable Gmail API"
            redirect_uri = "Add redirect URI to Google Cloud Console"
        }
    } | ConvertTo-Json -Depth 3
    
    Set-Content $infoFile -Value $installInfo -Encoding UTF8
    Write-Log "Installation info generated: install-info.json" "SUCCESS"
}

# メイン処理
function Main {
    Write-ColorOutput "`n=== WordPress Plugin Build Tool ===" "Cyan"
    Write-ColorOutput "Building: $PluginName v$Version`n" "Cyan"
    
    # 1. 初期化
    Write-Log "Starting build process..."
    
    if (Test-Path $BuildDir) {
        Remove-Item $BuildDir -Recurse -Force
    }
    New-Item -ItemType Directory -Path $BuildDir -Force | Out-Null
    New-Item -ItemType Directory -Path $TempDir -Force | Out-Null
    
    # 2. ファイル検証
    Write-Log "Validating plugin files..."
    Test-PluginFiles
    
    # 3. PHP構文チェック
    Write-Log "Checking PHP syntax..."
    Test-PHPSyntax
    
    # 4. ファイルコピー
    Write-Log "Copying plugin files..."
    Copy-PluginFiles
    
    # 5. バージョン更新
    Write-Log "Updating version information..."
    $mainFilePath = Join-Path $PluginDir $PluginMainFile
    Update-Version $mainFilePath $Version
    
    # 6. 最適化
    Write-Log "Optimizing plugin files..."
    Optimize-Plugin
    
    # 7. ZIPパッケージ作成
    Write-Log "Creating ZIP package..."
    $zipPath = Create-ZipPackage
    
    # 8. インストール情報生成
    Write-Log "Generating installation info..."
    Generate-InstallInfo
    
    # 9. クリーンアップ
    Remove-Item $TempDir -Recurse -Force
    
    # 10. 完了報告
    Write-ColorOutput "`n=== Build Completed Successfully ===" "Green"
    Write-ColorOutput "Package: $(Split-Path $zipPath -Leaf)" "Green"
    Write-ColorOutput "Location: $BuildDir" "Green"
    Write-ColorOutput "Ready for WordPress installation!" "Green"
    
    # ファイルエクスプローラーで結果を表示
    if (Test-Path $BuildDir) {
        Start-Process explorer.exe -ArgumentList $BuildDir
    }
}

# スクリプト実行
try {
    Main
}
catch {
    Handle-Error "Unexpected error: $($_.Exception.Message)"
}
