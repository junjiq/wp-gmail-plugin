# WordPress Plugin Build Tools

WP Gmail Pluginを配布可能なZIPファイルに変換するためのビルドツール集です。

## 🛠️ 利用可能なビルドツール

### 1. PowerShell版（推奨）
**ファイル**: `build-plugin.ps1`

最も高機能で、詳細な設定とエラーハンドリングを提供します。

#### 使用方法
```powershell
# 基本的なビルド
.\build-plugin.ps1

# バージョン指定
.\build-plugin.ps1 -Version "1.2.0"

# 詳細出力
.\build-plugin.ps1 -Version "1.2.0" -Verbose

# テストスキップ
.\build-plugin.ps1 -SkipTests

# 出力ディレクトリ指定
.\build-plugin.ps1 -OutputDir "release"
```

#### 機能
- ✅ PHP構文チェック
- ✅ ファイル検証
- ✅ バージョン自動更新
- ✅ CSS/JS最適化
- ✅ 詳細ログ
- ✅ エラーハンドリング
- ✅ インストール情報生成

### 2. バッチファイル版
**ファイル**: `build-plugin.bat`

Windowsで手軽に使える簡易版です。

#### 使用方法
```cmd
# ダブルクリックで実行
build-plugin.bat
```

#### 機能
- ✅ ファイルコピー
- ✅ ZIP作成
- ✅ 基本的なクリーンアップ

### 3. Node.js版（高機能）
**ファイル**: `build-tool.js` + `package.json`

最も柔軟で拡張可能なビルドツールです。

#### セットアップ
```bash
# 依存関係をインストール
npm install
```

#### 使用方法
```bash
# 基本ビルド
npm run build

# 開発版ビルド
npm run build:dev

# 本番版ビルド（最適化あり）
npm run build:prod

# パッケージング
npm run package

# バリデーションのみ
npm run validate

# バージョンアップ
npm run version:patch  # 1.0.0 → 1.0.1
npm run version:minor  # 1.0.0 → 1.1.0
npm run version:major  # 1.0.0 → 2.0.0

# カスタムオプション
node build-tool.js --version 1.5.0 --optimize --verbose
```

#### 機能
- ✅ 高度なファイル管理
- ✅ CSS/JS最適化
- ✅ 詳細なバリデーション
- ✅ 柔軟な設定オプション
- ✅ カラー出力
- ✅ 進捗表示
- ✅ エラー詳細

## 📦 ビルド結果

すべてのツールは以下の構造でファイルを生成します：

```
dist/
├── wp-gmail-plugin-v1.0.0.zip    # インストール用ZIPファイル
├── install-info.json             # インストール情報
└── build.log                     # ビルドログ（Verbose時）
```

## 🎯 ZIPファイルの内容

生成されるZIPファイルには以下が含まれます：

```
wp-gmail-plugin/
├── wp-gmail-plugin.php           # メインプラグインファイル
├── README.md                     # ドキュメント
├── uninstall.php                 # アンインストール処理
├── includes/                     # PHPクラス
│   ├── class-gmail-api-client.php
│   ├── class-gmail-oauth.php
│   ├── class-gmail-email-manager.php
│   └── functions.php
├── templates/                    # テンプレートファイル
│   ├── admin-main.php
│   ├── admin-settings.php
│   ├── admin-compose.php
│   ├── admin-inbox.php
│   ├── shortcode-compose.php
│   └── shortcode-inbox.php
├── assets/                       # CSS/JS
│   ├── css/
│   │   ├── style.css
│   │   └── admin-style.css
│   └── js/
│       ├── script.js
│       └── admin-script.js
└── languages/                    # 翻訳ファイル
    └── wp-gmail-plugin.pot
```

## 🚀 WordPressへのインストール方法

1. **ZIPファイルをダウンロード**
   - `dist/wp-gmail-plugin-v1.0.0.zip`をダウンロード

2. **WordPressにアップロード**
   - WordPress管理画面 → プラグイン → 新規追加
   - 「プラグインのアップロード」をクリック
   - ZIPファイルを選択して「今すぐインストール」

3. **プラグインを有効化**
   - インストール完了後「プラグインを有効化」をクリック

4. **設定を完了**
   - Gmail → 設定 でAPI認証情報を設定

## ⚙️ ビルド設定のカスタマイズ

### PowerShell版の設定
`build-plugin.ps1`の上部で設定を変更できます：

```powershell
$PluginName = "wp-gmail-plugin"
$PluginMainFile = "wp-gmail-plugin.php"
$BuildDir = Join-Path $PSScriptRoot $OutputDir
```

### Node.js版の設定
`build-tool.js`の`CONFIG`オブジェクトで設定を変更できます：

```javascript
const CONFIG = {
    pluginName: 'wp-gmail-plugin',
    pluginMainFile: 'wp-gmail-plugin.php',
    outputDir: 'dist',
    excludePatterns: [
        '*.log',
        '*.tmp',
        // 追加の除外パターン
    ]
};
```

## 🔍 トラブルシューティング

### よくある問題

#### 1. PowerShell実行ポリシーエラー
```powershell
# 実行ポリシーを一時的に変更
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

# または、バイパスして実行
powershell -ExecutionPolicy Bypass -File .\build-plugin.ps1
```

#### 2. PHP構文エラー
- PHPがインストールされているか確認
- `php -v`でバージョンを確認
- `-SkipTests`オプションでスキップ可能

#### 3. Node.js依存関係エラー
```bash
# キャッシュクリア
npm cache clean --force

# 再インストール
rm -rf node_modules package-lock.json
npm install
```

#### 4. ファイルアクセスエラー
- 管理者権限で実行
- アンチウイルスソフトの除外設定
- ファイルが使用中でないか確認

## 📋 チェックリスト

ビルド前に以下を確認してください：

- [ ] 必須ファイルが存在する
- [ ] PHP構文エラーがない
- [ ] バージョン番号が正しい
- [ ] 不要なファイルが含まれていない
- [ ] 設定ファイルが適切

## 🔄 継続的インテグレーション

### GitHub Actionsの例
```yaml
name: Build Plugin

on:
  push:
    tags:
      - 'v*'

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm install
      - run: npm run build:prod
      - uses: actions/upload-artifact@v3
        with:
          name: plugin-zip
          path: dist/*.zip
```

## 📝 ログとデバッグ

### PowerShell版
- `-Verbose`フラグで詳細ログ
- `dist/build.log`にログファイル生成

### Node.js版
- `--verbose`オプションで詳細出力
- カラー出力で見やすい表示

## 🤝 貢献

ビルドツールの改善提案やバグ報告は、GitHubのIssuesまでお願いします。

## 📄 ライセンス

このビルドツールはプラグイン本体と同じGPL v2以降のライセンスで配布されています。
