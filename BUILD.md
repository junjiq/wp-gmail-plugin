# WP Gmail Plugin ビルドガイド

このドキュメントでは、WP Gmail Pluginの開発環境構築から配布用ZIPファイル作成までの手順を説明します。複数のビルドツールを提供しており、環境に応じて選択できます。

## 概要

WP Gmail Pluginは、WordPressプラグインとして配布するために、開発用ファイルを除外し、最適化されたZIPパッケージを作成する必要があります。このプロジェクトでは、以下のビルドツールを提供しています：

1. **Node.js ビルドツール** (`build-tool.js`) - 推奨
2. **PowerShell ビルドツール** (`build-plugin.ps1`) - Windows環境
3. **PHP ビルドツール** (`tools/build-zip.php`) - クロスプラットフォーム
4. **Bash ビルドツール** (`tools/build-zip.sh`) - Unix系OS

## システム要件

### 基本要件
- **PHP**: 7.2以上（開発・実行環境）
- **WordPress**: 5.8以上（テスト環境）
- **ZIP拡張**: ZIPファイル作成用

### ビルドツール別要件
- **Node.js版**: Node.js 14.0以上、npm
- **PowerShell版**: PowerShell 5.0以上（Windows）
- **PHP版**: PHP ZipArchive拡張
- **Bash版**: bash、zipコマンド（macOS/Linux）

## プロジェクト構成

```
wp-gmail-plugin/
├── wp-gmail-plugin.php          # メインプラグインファイル
├── README.md                    # プロジェクト説明
├── BUILD.md                     # ビルドガイド（本ファイル）
├── package.json                 # Node.js依存関係
├── uninstall.php               # アンインストール処理
├── build-tool.js               # Node.jsビルドツール（推奨）
├── build-plugin.ps1            # PowerShellビルドツール
├── build-plugin.bat            # Windowsバッチファイル
├── includes/                   # PHPクラスファイル
├── templates/                  # テンプレートファイル
├── assets/                     # 静的リソース
├── languages/                  # 多言語ファイル
├── tools/                      # 追加ビルドツール
│   ├── build-zip.php          # PHP版ビルドツール
│   ├── build-zip.ps1          # PowerShell版ビルドツール
│   └── build-zip.sh           # Bash版ビルドツール
└── dist/                       # ビルド出力（自動生成）
    ├── wp-gmail-plugin-v*.zip  # 配布用ZIPファイル
    └── install-info.json       # インストール情報
```

## 開発環境セットアップ

### ローカルWordPress環境

#### 既存WordPress環境を使用する場合
1. `wp-gmail-plugin`フォルダを`wp-content/plugins/`に配置
2. WordPress管理画面 > プラグイン で「WP Gmail Mailer」を有効化
3. 設定 > WP Gmail Mailer で設定を行う

#### Docker環境を使用する場合
```bash
# WordPress + MySQL環境の起動例
docker-compose up -d

# プラグインフォルダをマウント
# docker-compose.yml例:
# volumes:
#   - ./wp-gmail-plugin:/var/www/html/wp-content/plugins/wp-gmail-plugin
```

### Gmail SMTP設定

このプラグインはGmail APIではなく、Gmail SMTPを使用します：

1. **Googleアカウント設定**
   - 2段階認証を有効化
   - アプリパスワードを生成

2. **WordPress設定**
   - 管理画面 > 設定 > WP Gmail Mailer
   - Gmailアドレスとアプリパスワードを入力

> 注意: Gmail APIの設定は不要です。SMTPのみを使用します。

## 開発ツール・品質管理

### コーディング規約
- WordPress Coding Standards準拠
- PHP_CodeSniffer推奨（オプション）

### 品質チェック（オプション）
```bash
# Composerを使用する場合
composer install
composer run lint    # 構文チェック
composer run fix     # 自動修正
```

## ビルドツール詳細

このプロジェクトでは、環境に応じて複数のビルドツールを提供しています。いずれも同様の機能を持ちますが、実行環境に応じて選択してください。

### 共通機能

全てのビルドツールは以下の機能を提供します：

- **ファイル除外**: 開発用ファイル（`.git`、`node_modules`、テストファイル等）の自動除外
- **バージョン更新**: プラグインファイル内のバージョン情報を自動更新
- **ファイル検証**: 必須ファイルの存在確認
- **最適化**: CSS/JavaScriptファイルの基本的な最適化（オプション）
- **ZIPパッケージ作成**: WordPress互換の配布用ZIPファイル生成
- **インストール情報生成**: インストール手順書の自動生成

### 除外ファイルパターン

以下のファイル・フォルダは自動的にビルドから除外されます：

```
*.log, *.tmp          # ログ・一時ファイル
.git*, .vscode/       # バージョン管理・エディタ設定
.idea/, .DS_Store     # IDE・システムファイル
node_modules/         # Node.js依存関係
dist/                 # ビルド出力フォルダ
tests/                # テストファイル
*.dev.*               # 開発用ファイル
build-*.{ps1,bat,js}  # ビルドスクリプト
package*.json         # Node.js設定ファイル
.env*                 # 環境設定ファイル
```

## ビルドツール使用方法

### 1. Node.js ビルドツール（推奨）

**特徴**: 最も高機能で詳細な出力を提供

```bash
# 依存関係のインストール
npm install

# 基本ビルド
npm run build

# 開発ビルド（最適化なし）
npm run build:dev

# 本番ビルド（最適化あり）
npm run build:prod

# 検証のみ（ビルドしない）
npm run validate

# カスタムオプション
node build-tool.js --version 1.2.0 --optimize --verbose
```

**オプション**:
- `--version`: バージョン指定
- `--dev`: 開発モード
- `--prod`: 本番モード
- `--optimize`: 最適化有効
- `--validate-only`: 検証のみ
- `--verbose`: 詳細出力

### 2. PowerShell ビルドツール（Windows）

**特徴**: Windows環境に最適化、GUI統合

```powershell
# 基本実行
.\build-plugin.ps1

# バージョン指定
.\build-plugin.ps1 -Version "1.2.0"

# 詳細出力
.\build-plugin.ps1 -Version "1.2.0" -VerboseOutput

# テストスキップ
.\build-plugin.ps1 -SkipTests
```

**特徴**:
- Windows エクスプローラーで結果フォルダを自動オープン
- カラー出力対応
- PHP構文チェック（オプション）
- ログファイル出力

### 3. PHP ビルドツール（クロスプラットフォーム）

**特徴**: PHP環境のみで動作、軽量

```bash
# 基本実行
php tools/build-zip.php

# オプション指定
php tools/build-zip.php wp-gmail-plugin dist
```

**引数**:
1. プラグインディレクトリ（デフォルト: 現在のディレクトリ）
2. 出力ディレクトリ（デフォルト: `dist`）

### 4. Bash ビルドツール（Unix系）

**特徴**: Linux/macOS/WSLで軽量動作

```bash
# 実行権限付与
chmod +x tools/build-zip.sh

# 実行
bash tools/build-zip.sh
```

## ビルド出力

### 生成されるファイル

ビルドが正常に完了すると、`dist/`フォルダに以下のファイルが生成されます：

```
dist/
├── wp-gmail-plugin-v1.2.0.zip    # 配布用ZIPファイル
└── install-info.json             # インストール情報JSON
```

### ZIPファイルの内容

生成されるZIPファイルには以下が含まれます：

```
wp-gmail-plugin/
├── wp-gmail-plugin.php          # メインファイル（バージョン更新済み）
├── README.md                    # プロジェクト説明
├── uninstall.php               # アンインストール処理
├── includes/                   # PHPクラスファイル
├── templates/                  # テンプレートファイル
├── assets/                     # 静的リソース（最適化済み）
└── languages/                  # 多言語ファイル
```

## WordPress インストール手順

### 1. ZIPファイルからのインストール

1. ビルドで生成された`wp-gmail-plugin-v*.zip`をダウンロード
2. WordPress管理画面 > プラグイン > 新規追加
3. 「プラグインのアップロード」をクリック
4. ZIPファイルを選択して「今すぐインストール」
5. インストール完了後「有効化」をクリック

### 2. 手動インストール

1. ZIPファイルを解凍
2. `wp-gmail-plugin`フォルダを`wp-content/plugins/`にアップロード
3. WordPress管理画面 > プラグイン で有効化

### 3. 初期設定

1. 管理画面 > 設定 > WP Gmail Mailer
2. Gmailアドレスとアプリパスワードを設定
3. 「変更を保存」で設定を保存（自動検証実行）
4. テストメール送信で動作確認

## 品質チェックリスト

### ビルド前チェック

- [ ] プラグインバージョンの確認・更新
- [ ] 必須ファイルの存在確認
- [ ] PHP構文エラーがないことを確認
- [ ] 翻訳ファイル（.mo）の生成
- [ ] 変更履歴の更新

### ビルド後チェック

- [ ] ZIPファイルの生成確認
- [ ] ファイルサイズの確認（適切な範囲内）
- [ ] 不要ファイルが除外されていることを確認
- [ ] install-info.jsonの内容確認

### インストールテスト

- [ ] 新規WordPressサイトでのインストール
- [ ] プラグインの有効化
- [ ] 設定画面の表示・操作
- [ ] SMTP接続テスト
- [ ] メール送信テスト
- [ ] ログ機能の動作確認
- [ ] 多言語表示の確認（該当する場合）

## トラブルシューティング

### ビルドエラー

**「必須ファイルが見つからない」**
- ファイル構成を確認
- パスの大文字・小文字を確認

**「ZIPファイル作成に失敗」**
- ディスク容量を確認
- 書き込み権限を確認
- ファイルロックの解除

**「PHP構文エラー」**
- PHP構文チェッカーで確認
- 使用しているPHPバージョンとの互換性確認

### インストールエラー

**「プラグインが認識されない」**
- ZIPファイル内のフォルダ構造を確認
- メインファイル（wp-gmail-plugin.php）の存在確認

**「SMTP接続エラー」**
- Googleアプリパスワードの確認
- 2段階認証の有効化確認
- ネットワーク・ファイアウォール設定確認

## リリース管理

### バージョン管理

1. 機能追加・修正の完了
2. バージョン番号の決定（セマンティックバージョニング）
3. プラグインファイル内のバージョン更新
4. CHANGELOG/README.mdの更新
5. Git タグの作成

### リリース手順

1. **開発完了・テスト**
   ```bash
   # 品質チェック
   npm run validate
   ```

2. **ビルド実行**
   ```bash
   # 本番ビルド
   npm run build:prod
   ```

3. **配布**
   - 自社サイト: ZIPファイルをアップロード
   - WordPress.org: SVNリポジトリにコミット
   - GitHub: Releaseページで公開

4. **リリース後確認**
   - 本番環境での動作テスト
   - ユーザーフィードバックの監視

## 継続的改善

### 推奨する追加機能

- CI/CDパイプライン（GitHub Actions等）
- 自動テスト（PHPUnit、E2Eテスト）
- コード品質チェック（PHPCS、PHPMD）
- セキュリティスキャン
- 翻訳管理ツール連携

### 監視・メンテナンス

- WordPress/PHP新バージョンとの互換性確認
- セキュリティアップデート対応
- ユーザーフィードバックに基づく改善
- パフォーマンス最適化

---

このビルドガイドは、WP Gmail Pluginの開発・配布プロセスを効率化し、品質を保つためのものです。環境や要件に応じてカスタマイズしてください。
