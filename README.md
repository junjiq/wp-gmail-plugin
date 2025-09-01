# WP Gmail Plugin

WordPressサイトでGmailのSMTP機能を使用してメール送信を行うプラグインです。Gmail APIは使用せず、SMTPプロトコルを使用してWordPressの`wp_mail()`関数を完全に置き換えます。

## 概要

このプラグインは、WordPressのデフォルトメール送信機能を、より信頼性の高いGmail SMTPサーバーを使用した送信に置き換えます。Gmail APIではなく、従来のSMTPプロトコルを使用するため、シンプルで安定した動作を提供します。

## 主な機能

### メール送信機能
- `wp_mail()`関数の完全置換（`pre_wp_mail`フィルターを使用）
- Gmail SMTPサーバー（`smtp.gmail.com`）を使用した送信
- TLS（ポート587）およびSSL（ポート465）暗号化に対応
- HTML/テキストメール、添付ファイル、CC/BCC、Reply-Toヘッダーに対応

### 認証・セキュリティ
- Googleアプリパスワードを使用した認証
- 設定保存時の自動SMTP接続検証
- From アドレスの固定設定（セキュリティ向上）

### ログ・監視機能
- 送信ログの自動記録（成功/失敗）
- ログファイルの保持日数・最大サイズ設定
- ログローテーション機能
- 管理画面でのエラー通知表示
- ログの閲覧・ダウンロード・クリア機能

### 管理機能
- 直感的な設定画面
- テストメール送信機能
- 多言語対応（日本語・英語）
- WordPress標準のアイコン・バナー画像同梱

## プロジェクト構成

```
wp-gmail-plugin/
├── wp-gmail-plugin.php          # メインプラグインファイル
├── README.md                    # プロジェクト説明書
├── BUILD.md                     # ビルド手順書
├── uninstall.php               # アンインストール処理
├── package.json                # Node.js依存関係
├── includes/                   # PHPクラスファイル - 将来の拡張用
│   ├── class-gmail-api-client.php      # Gmail API クライアント
│   ├── class-gmail-oauth.php           # OAuth認証処理
│   ├── class-gmail-email-manager.php   # メール管理
│   └── functions.php                   # 共通関数
├── templates/                  # テンプレートファイル - 将来の拡張用
│   ├── admin-main.php          # 管理画面メイン
│   ├── admin-settings.php      # 設定画面
│   ├── admin-inbox.php         # 受信箱表示
│   ├── admin-compose.php       # メール作成
│   ├── shortcode-inbox.php     # フロントエンド受信箱
│   └── shortcode-compose.php   # フロントエンドメール作成
├── assets/                     # 静的リソース
│   ├── css/                    # スタイルシート
│   │   ├── style.css           # フロントエンド用CSS
│   │   └── admin-style.css     # 管理画面用CSS
│   ├── js/                     # JavaScript
│   │   ├── script.js           # フロントエンド用JS
│   │   └── admin-script.js     # 管理画面用JS
│   ├── icon.svg               # プラグインアイコン
│   ├── banner-772x250.svg     # バナー画像（小）
│   └── banner-1544x500.svg    # バナー画像（大）
├── languages/                  # 多言語ファイル
│   ├── wp-gmail-plugin.pot     # 翻訳テンプレート
│   ├── en_US.po               # 英語翻訳
│   └── ja_JP.po               # 日本語翻訳
├── build-plugin.ps1           # PowerShellビルドスクリプト
├── build-plugin.bat           # Windowsバッチファイル
├── build-tool.js              # Node.jsビルドツール
└── dist/                       # ビルド出力（自動生成）
    └── wp-gmail-plugin-v*.zip # 配布用ZIPファイル
```

## インストール方法

### 1. 手動インストール
1. プロジェクトをダウンロードまたはクローン
2. `wp-gmail-plugin`フォルダを`wp-content/plugins/`に配置
3. WordPress管理画面 > プラグイン から「WP Gmail Mailer」を有効化

### 2. ZIPファイルからのインストール
1. ビルドツールを使用してZIPファイルを作成（[BUILD.md](BUILD.md)参照）
2. WordPress管理画面 > プラグイン > 新規追加 > プラグインのアップロード
3. 作成したZIPファイルを選択してインストール・有効化

## 初期設定

### Gmail側の設定
1. **2段階認証の有効化**
   - Googleアカウント > セキュリティ > 2段階認証プロセス を有効化

2. **アプリパスワードの生成**
   - Googleアカウント > セキュリティ > アプリパスワード
   - 新しいアプリパスワードを生成（16桁のパスワードが発行される）

### WordPress側の設定
1. WordPress管理画面 > 設定 > WP Gmail Mailer を開く
2. 以下の項目を設定：
   - **Gmail アドレス**: 送信に使用するGmailアドレス
   - **アプリ パスワード**: 上記で生成した16桁のパスワード
   - **From アドレス**: 送信者アドレス（省略時はGmailアドレス）
   - **From 名称**: 送信者名
   - **暗号化方式**: TLS（推奨）またはSSL
   - **ポート**: 自動設定（TLS:587, SSL:465）
   - **ログ設定**: 必要に応じて調整

3. **設定の保存と検証**
   - 「変更を保存」をクリック
   - 自動的にSMTP接続検証が実行される
   - 失敗した場合はエラーメッセージが表示され、設定がリセットされる

4. **動作確認**
   - 設定画面下部の「テスト送信」でメール送信をテスト
   - ログビューアで送信結果を確認

## ログ機能

### ログファイル
- **保存場所**: `wp-content/uploads/wpgp-logs/wpgp-mail.log`
- **記録内容**: メール送信の成功/失敗、エラー詳細、送信先情報
- **設定項目**:
  - 保持日数（0-365日、デフォルト14日）
  - 最大ファイルサイズ（32KB-10MB、デフォルト1MB）
  - サイズ超過時の自動ローテーション（`.1`拡張子で保存）

### ログ管理機能
- 設定画面での最新200行の表示
- ログファイルのダウンロード機能
- ログファイルのクリア機能
- 管理画面でのエラー通知表示

## 多言語対応

### サポート言語
- 日本語（ja_JP）
- 英語（en_US）

### 翻訳ファイル
- **テキストドメイン**: `wp-gmail-mailer`
- **翻訳テンプレート**: `languages/wp-gmail-plugin.pot`
- **言語ファイル**:
  - `languages/ja_JP.po` - 日本語翻訳
  - `languages/en_US.po` - 英語翻訳

### 翻訳ファイルの生成
必要に応じて`.mo`ファイルを生成してください：
```bash
# 日本語
msgfmt -o languages/ja_JP.mo languages/ja_JP.po

# 英語
msgfmt -o languages/en_US.mo languages/en_US.po
```

## 技術仕様

### システム要件
- **WordPress**: 5.8以上
- **PHP**: 7.2以上
- **必要な拡張**: PHPMailer（WordPress標準同梱）

### 使用技術
- **メール送信**: PHPMailer + Gmail SMTP
- **認証方式**: Googleアプリパスワード
- **暗号化**: TLS/SSL
- **フィルター**: `pre_wp_mail`によるwp_mail()の置換

## 注意事項・制限事項

### Gmail側の制限
- **送信制限**: Googleアカウントの1日の送信制限に従う
- **From アドレス**: Gmailアカウントと整合性のあるアドレス推奨
- **独自ドメイン**: DMARC/SPF/DKIM設定の整合性が必要

### プラグインの制限
- **受信機能**: なし（送信専用）
- **Gmail API**: 使用しない（SMTPのみ）
- **他のSMTPプラグインとの競合**: `wp_mail()`を置換するため競合の可能性

### セキュリティ考慮事項
- アプリパスワードは暗号化して保存
- 送信失敗時のメール通知は実装しない（無限ループ防止）
- 設定変更時の自動接続検証でセキュリティを確保

## よくある質問（FAQ）

### Q: Gmail APIを使用しますか？
A: いいえ、従来のSMTPプロトコルのみを使用します。

### Q: 受信機能はありますか？
A: ありません。このプラグインは送信専用です。

### Q: HTMLメールは送信できますか？
A: はい。`wp_mail_content_type`フィルターで`text/html`を指定すれば送信可能です。

### Q: 添付ファイル、CC、BCC、Reply-Toは使用できますか？
A: はい。WordPressの`wp_mail()`と同様の指定方法で使用できます。

### Q: 他のSMTPプラグインと併用できますか？
A: 推奨しません。`wp_mail()`を置換するため競合する可能性があります。

### Q: 送信が失敗した場合はどうなりますか？
A: ログに記録され、設定に応じて管理画面に通知が表示されます。

## バージョン履歴

### v1.2.0
- クラスベースアーキテクチャへの変更
- 設定保存時の自動SMTP接続検証機能
- 多言語対応（i18n）の実装
- WordPress標準アセット（アイコン・バナー）の追加
- README.mdの統合・改善

### v1.1.0
- ログ機能の実装
- エラーレポート機能
- ログ保持日数・最大サイズ設定
- ログローテーション機能
- 管理画面でのログビューア
- エラー通知機能

### v1.0.0
- 初期リリース
- Gmail SMTP による `wp_mail()` 完全置換
- 基本設定画面
- テストメール送信機能

## 開発・ビルド

このプラグインの開発環境構築とビルド方法については、[BUILD.md](BUILD.md)を参照してください。

### クイックスタート
```bash
# Node.jsビルドツールを使用
npm install
npm run build

# PowerShellビルドツール（Windows）
.\build-plugin.ps1 -Version "1.2.0"

# 出力: dist/wp-gmail-plugin-v1.2.0.zip
```

## ライセンス

GPL-2.0-or-later

## サポート・貢献

- **バグレポート**: GitHubのIssuesページ
- **機能要望**: GitHubのIssuesページ
- **プルリクエスト**: 歓迎します

## 作者

Your Name
