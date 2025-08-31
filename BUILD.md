# BUILD ガイド（wp-gmail-plugin）

このドキュメントは、WordPress 向けプラグイン「wp-gmail-plugin」をローカルで開発し、配布用にビルド（ZIP 化）してリリースするまでの手順をまとめたものです。

## 1) 前提条件

- PHP 7.4+（推奨: 8.x）
- WordPress 6.x（ローカル実行環境）
- Git（任意: バージョン管理とタグ付け）
- ZIP ユーティリティ（`zip` コマンド or OS の圧縮機能）
- 任意: WP-CLI（`wp` コマンド）
- 任意: Composer（サードパーティ PHP ライブラリを使う場合）

> 備考: 本プラグインが追加ツール（npm, composer 等）に依存している場合は、プロジェクトルートの `README.md` や `composer.json` も参照してください。

## 2) ディレクトリ構成（例）

```
wp-gmail-plugin/
├─ wp-gmail-plugin.php         # メインプラグインファイル（ヘッダにバージョン）
├─ readme.txt                  # WordPress.org 互換の readme（任意）
├─ includes/                   # PHP 機能モジュール
├─ assets/                     # 画像・CSS・JS など
├─ languages/                  # 翻訳ファイル（.pot/.po/.mo）
└─ BUILD.md                    # 本ドキュメント
```

実際の構成はプロジェクトに合わせて読み替えてください。

## 3) ローカル開発

- 既存の WordPress 環境がある場合:
  - `wp-content/plugins/` に本プラグインディレクトリ（`wp-gmail-plugin/`）を配置
  - 管理画面 > プラグイン で「有効化」
- Docker で新規に用意する場合（例）:
  - 任意の WordPress 用 Compose テンプレートを使うか、`wordpress`/`mariadb` 公式イメージで環境を起動
  - プロジェクトフォルダを `wp-content/plugins` にマウント

### Gmail/Google API の設定（概要）

- Google Cloud Console で「OAuth 2.0 クライアント ID」を作成
- 対象 API として Gmail API を有効化
- スコープは必要最小限（例: `https://www.googleapis.com/auth/gmail.send` など）
- 認可リダイレクト URI は、プラグインのコールバック URL に合わせて登録
- 発行された `Client ID` と `Client Secret` を本プラグインの設定画面または `.env` で管理

> セキュリティ: 認証情報は `.env` や WP 設定に保存し、Git にコミットしないでください。

`.env` の例（必要な場合）:

```
GOOGLE_CLIENT_ID="xxxxxxxxxx.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="xxxxxxxxxxxxxxxxxxxxxx"
GOOGLE_REDIRECT_URI="https://example.com/wp-admin/admin.php?page=wp-gmail-plugin-callback"
```

## 4) コーディング規約・品質（任意）

- PHP_CodeSniffer + WordPress Coding Standards を推奨
- `composer.json` にスクリプトがある場合:

```
composer install
composer run lint
composer run fix
```

- Lint/Format の導入がまだの場合は、開発ポリシーに従って追加を検討

## 5) バージョン更新

- 配布前に、以下を同一の新バージョンへ更新
  - メインプラグインファイル（例: `wp-gmail-plugin.php`）のヘッダ `Version`
  - `readme.txt` の `Stable tag`（運用している場合）
  - 変更点を `CHANGELOG` または `readme.txt` に追記

## 6) 配布用 ZIP の作成（ビルド）

最小構成では、ソースをそのまま ZIP 化します。開発用ファイル（`.git`, `node_modules`, `tests`, `.*` など）は含めないでください。

### 6.1 Bash の例（macOS/Linux/WSL）

```bash
# 作業ディレクトリの一つ上で実行する例
cd ..
zip -r "wp-gmail-plugin.zip" "wp-gmail-plugin" \
  -x "*.git*" \
  -x "*/node_modules/*" \
  -x "*/vendor/*" \
  -x "*.DS_Store" \
  -x "*/.vscode/*" \
  -x "*/.idea/*" \
  -x "*/tests/*" \
  -x "*/.env*"
```

### 6.2 PowerShell の例（Windows）

```powershell
# 作業ディレクトリの一つ上で実行する例
Set-Location ..
$src = "wp-gmail-plugin"
$dst = "wp-gmail-plugin.zip"
$exclude = @(
  "**/.git*","**/node_modules/*","**/vendor/*",
  "**/.vscode/*","**/.idea/*","**/tests/*","**/.env*","**/.DS_Store"
)
if (Test-Path $dst) { Remove-Item $dst }
Compress-Archive -Path $src -DestinationPath $dst -CompressionLevel Optimal -Force -Exclude $exclude
```

> Composer を使う場合は `composer install --no-dev` を実行してから ZIP に含めるとよいです（`vendor/` を含める）。

## 7) 動作確認チェックリスト

- プラグインを新規インストールして有効化できる
- 設定画面で Google 認証が完了する
- 期待どおりに Gmail 送信/取得などのコア機能が動作する
- ログ/エラーが出ていない（`wp-content/debug.log` など）
- 翻訳・表記・アクセシビリティに問題がない

## 8) リリース手順（例）

1. 変更点を最終確認（ローカルで動作確認）
2. バージョン更新（プラグインヘッダ、readme 等）
3. ZIP を作成（上記手順）
4. Git にタグ付け（例: `v1.2.3`）
5. 配布: 
   - 自社サイト配布なら ZIP を配布ページへアップロード
   - WordPress.org へ公開する場合は SVN リポジトリにコミット
6. リリース後に本番サイトで最終動作確認

## 9) トラブルシュート

- 「Google で承認エラー」: リダイレクト URI とスコープを再確認。テストユーザー制限が有効な場合は対象アカウントを追加。
- 「メール送信が失敗」: PHP エラーログ、HTTP リクエストログ、リフレッシュトークンの有効性を確認。スコープに `gmail.send` が含まれているか確認。
- 「ZIP が大きすぎる」: `node_modules/` やテスト資材、不要なアセットを除外して再作成。

## 10) よくある追加タスク（任意）

- WP-CLI コマンドの追加（バッチ送信・再認証など）
- 自動テスト（Unit/E2E）と CI での ZIP 生成
- 翻訳ファイル（`.pot`）の生成と更新
- セキュリティレビュー（Nonce, Capability, エスケープ/サニタイズの徹底）

---

本ドキュメントはテンプレート的な内容を含みます。プロジェクトに合わせてコマンド・手順・除外パターンを調整してください。必要であれば、あなたの運用に合わせてさらに具体化します。
