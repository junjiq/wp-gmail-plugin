# WP Gmail Mailer (SMTP Replacement)

Gmail の SMTP を使って WordPress の `wp_mail()` を完全に置き換えます。Gmail API は使いません。受信機能は含みません。ログ、エラー通知、保存時の接続検証、i18n に対応します。

## 機能

- `wp_mail` の完全置換（`pre_wp_mail` でショートサーキット）
- Gmail SMTP で送信（`smtp.gmail.com` / TLS:587, SSL:465）
- HTML/テキスト、添付、CC/BCC、Reply-To に対応
- From は設定で固定（ヘッダーの From は無視し、Reply-To を尊重）
- 保存時の接続検証（アカウント/パスワード/暗号化/ポート変更時にSMTP接続・認証を試行）
- 送信ログ（保持日数/最大サイズ・ローテーション）、失敗時の管理画面通知
- 設定画面からテスト送信、ログの閲覧/ダウンロード/クリア
- 多言語対応（en_US / ja_JP）、言語ファイル同梱
- アセット同梱（アイコン/バナー）

## インストールと設定

1. このフォルダ `wp-gmail-plugin` を `wp-content/plugins/` に配置
2. 管理画面 > プラグイン から有効化
3. 管理画面 > 設定 > WP Gmail Mailer を開き、以下を設定
   - Gmail アドレス（ユーザー名）
   - アプリ パスワード（16 桁、2 段階認証有効時に発行）
   - From 名称/From アドレス（省略時は Gmail アドレス）
   - 暗号化（TLS 推奨）/ポート（未指定で自動）
   - ログ記録/保持日数/最大サイズ、エラー通知
4. 保存時に接続検証が行われ、失敗するとエラーメッセージが表示され入力がリセットされます
5. 画面下部の「テストメールを送信」で動作確認

### Gmail のアプリ パスワード（概要）

1. Google アカウント > セキュリティ > 2 段階認証プロセス を有効化
2. 「アプリ パスワード」から新しいパスワードを発行
3. 発行された 16 桁の値を本プラグインの「アプリ パスワード」に入力

## ログ

- 保存場所: `wp-content/uploads/wpgp-logs/wpgp-mail.log`
- 保持日数と最大サイズ(KB)を設定可能。サイズ超過時はローテーション（`.1`）
- 設定画面内で最新 200 行を表示、ダウンロード/クリア可

## 国際化（i18n）

- テキストドメイン: `wp-gmail-mailer`
- 言語ファイル: `languages/wp-gmail-mailer.pot`, `languages/ja_JP.po`, `languages/en_US.po`
- 必要に応じて `.mo` を生成してください（例）
  - `msgfmt -o languages/ja_JP.mo languages/ja_JP.po`
  - `msgfmt -o languages/en_US.mo languages/en_US.po`

## 注意事項

- Gmail のポリシー上、From はアカウントに整合する値の利用を推奨します。独自ドメイン From を使う場合は DMARC/SPF/DKIM の整合に注意してください。
- 他の SMTP プラグインと併用すると競合します（本プラグインが `wp_mail` を掌握）。
- 送信失敗時の「メールによる通知」は実装していません（送信自体が失敗している可能性があるため）。

## よくある質問

- Gmail API を使いますか？ → いいえ、SMTP のみです。
- 受信機能はありますか？ → ありません。送信のみです。
- HTML メールは？ → `wp_mail_content_type` で `text/html` を指定すれば送れます。
- 添付/CC/BCC/Reply-To は？ → WordPress の `wp_mail` と同様の指定が可能です。

## 変更履歴

- 1.2.0: クラスベース化、保存時の接続検証、i18n/アセット追加、README.md に統合
- 1.1.0: ログ/エラーレポート（保持日数/最大サイズ/ローテーション、通知、ログビューア）
- 1.0.0: 初期リリース（Gmail SMTP による `wp_mail` 完全置換、テスト送信、基本設定）

## ビルド（配布用ZIPの作成）

環境に応じて次のいずれかを実行してください。出力は `dist/wp-gmail-plugin-<version>.zip` です。

- PHP（推奨・クロスプラットフォーム）
  - コマンド: `php tools/build-zip.php`
  - オプション: `php tools/build-zip.php <plugin_dir> <dist_dir>`

- PowerShell（Windows）
  - コマンド: `powershell -ExecutionPolicy Bypass -File tools/build-zip.ps1 -PluginDir wp-gmail-plugin -DistDir dist`

- Bash（macOS/Linux）
  - コマンド: `bash tools/build-zip.sh`
  - 事前に実行権限: `chmod +x tools/build-zip.sh`

補足: PHP の ZipArchive が無効な環境では PowerShell/Bash 版をご利用ください。翻訳を同梱する場合は `.mo` 生成後にビルドしてください。
