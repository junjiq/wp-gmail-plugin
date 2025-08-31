# WP Gmail Plugin

WordPressからGmail APIを使用してメールの送受信を行うプラグインです。

## 概要

このプラグインは、Gmail APIを使用してWordPressサイトから直接メールの送信と受信を可能にします。管理画面からの操作だけでなく、フロントエンドでのショートコード表示にも対応しています。

## 主な機能

### 📧 メール送信機能
- 管理画面からのメール作成・送信
- HTMLメールとテキストメールの両方に対応
- CC/BCC機能
- メールテンプレート機能
- 下書き保存機能
- 自動保存機能

### 📨 メール受信機能
- Gmailからのメール同期
- 受信メールの一覧表示
- メール詳細表示
- 既読/未読管理
- スター機能
- メール検索機能

### 🔧 管理機能
- Gmail API認証設定
- OAuth 2.0認証
- メール統計表示
- バルクアクション
- ページネーション

### 🎨 フロントエンド機能
- メール作成フォームのショートコード
- 受信箱表示のショートコード
- レスポンシブデザイン
- アクセシビリティ対応

## インストール方法

### 1. ファイルのアップロード
プラグインフォルダを `/wp-content/plugins/` ディレクトリにアップロードしてください。

### 2. プラグインの有効化
WordPress管理画面の「プラグイン」メニューから「WP Gmail Plugin」を有効化してください。

### 3. Gmail API設定

#### 3.1 Google Cloud Consoleでの設定
1. [Google Cloud Console](https://console.cloud.google.com/)にアクセス
2. 新しいプロジェクトを作成または既存のプロジェクトを選択
3. Gmail APIを有効化
4. 認証情報を作成（OAuth 2.0 クライアント ID）
5. 承認済みのリダイレクト URIを設定

#### 3.2 プラグイン設定
1. WordPress管理画面で「Gmail」→「Settings」に移動
2. Google Cloud Consoleで取得したClient IDとClient Secretを入力
3. 設定を保存
4. 「Authenticate with Gmail」ボタンをクリックしてGmailと連携

## 使用方法

### 管理画面での使用

#### メール送信
1. 「Gmail」→「Compose」に移動
2. 宛先、件名、本文を入力
3. 「Send Email」ボタンをクリック

#### 受信メール確認
1. 「Gmail」→「Inbox」に移動
2. メール一覧から確認したいメールをクリック
3. メール詳細がモーダルで表示されます

#### メール同期
- 「Sync」ボタンをクリックしてGmailからメールを取得

### ショートコードの使用

#### メール作成フォーム
```php
[gmail_compose]
```

オプション：
```php
[gmail_compose to="example@example.com" subject="件名" class="custom-class"]
```

#### 受信箱表示
```php
[gmail_inbox]
```

オプション：
```php
[gmail_inbox limit="20" class="custom-inbox"]
```

### フック・フィルター

#### アクションフック
```php
// メール送信前
do_action('wp_gmail_before_send_email', $email_data);

// メール送信後
do_action('wp_gmail_after_send_email', $email_data, $result);

// メール同期前
do_action('wp_gmail_before_sync_emails');

// メール同期後
do_action('wp_gmail_after_sync_emails', $emails);
```

#### フィルターフック
```php
// メール送信データの修正
$email_data = apply_filters('wp_gmail_send_email_data', $email_data);

// メール一覧の修正
$emails = apply_filters('wp_gmail_inbox_emails', $emails);

// ショートコード属性の修正
$atts = apply_filters('wp_gmail_compose_shortcode_atts', $atts);
```

## 設定オプション

### 基本設定
- **Client ID**: Google Cloud ConsoleのOAuth 2.0 クライアント ID
- **Client Secret**: Google Cloud ConsoleのOAuth 2.0 クライアント シークレット
- **Redirect URI**: OAuth認証後のリダイレクト先URL（自動設定）

### 高度な設定
- **Sync Interval**: メール同期間隔（秒）
- **Max Emails**: 一度に取得するメールの最大数

## データベース構造

### テーブル: `wp_gmail_plugin_emails`
```sql
CREATE TABLE wp_gmail_plugin_emails (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    message_id varchar(255) NOT NULL,
    thread_id varchar(255) NOT NULL,
    from_email varchar(255) NOT NULL,
    from_name varchar(255) NOT NULL,
    to_email text NOT NULL,
    subject text NOT NULL,
    body longtext NOT NULL,
    snippet text NOT NULL,
    labels text NOT NULL,
    is_read tinyint(1) DEFAULT 0,
    is_starred tinyint(1) DEFAULT 0,
    received_date datetime NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY message_id (message_id)
);
```

## セキュリティ

### 認証
- OAuth 2.0による安全な認証
- アクセストークンの自動更新
- リフレッシュトークンの暗号化保存

### データ保護
- SQLインジェクション対策
- XSS対策
- CSRF対策（ナンス使用）
- 入力値のサニタイズ

### 権限管理
- 管理者権限（`manage_options`）が必要
- ユーザー権限の適切なチェック

## トラブルシューティング

### よくある問題

#### 1. 認証エラー
**症状**: 「Authentication failed」エラー
**解決方法**:
- Client IDとClient Secretを再確認
- リダイレクトURIがGoogle Cloud Consoleに正しく設定されているか確認
- Gmail APIが有効になっているか確認

#### 2. メール送信エラー
**症状**: 「Failed to send email」エラー
**解決方法**:
- Gmail APIの送信制限を確認
- アクセストークンが有効か確認
- メールアドレスの形式を確認

#### 3. メール同期エラー
**症状**: メールが同期されない
**解決方法**:
- Gmail APIの読み取り権限を確認
- 同期間隔の設定を確認
- サーバーのタイムアウト設定を確認

### ログの確認
デバッグログは以下の場所に記録されます：
```
/wp-content/debug.log
```

デバッグモードを有効にするには、`wp-config.php`に以下を追加：
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## パフォーマンス最適化

### キャッシュ
- メールデータのローカルキャッシュ
- APIレスポンスのトランジェント使用
- 画像の遅延読み込み

### データベース最適化
- インデックスの適切な設定
- 不要なデータの定期削除
- クエリの最適化

## カスタマイズ

### CSS カスタマイズ
プラグインのスタイルをカスタマイズするには、テーマの`style.css`に以下を追加：

```css
/* メール作成フォーム */
.wp-gmail-compose-form {
    background: #f9f9f9;
    border-radius: 10px;
}

/* 受信箱 */
.wp-gmail-inbox {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
```

### JavaScript カスタマイズ
カスタムJavaScriptを追加するには：

```javascript
// メール送信後のカスタム処理
jQuery(document).on('wp_gmail_email_sent', function(e, data) {
    console.log('Email sent:', data);
});
```

## API リファレンス

### PHP クラス

#### `Gmail_API_Client`
Gmail APIとの通信を管理

```php
$client = new Gmail_API_Client();
$result = $client->send_message($raw_message);
```

#### `Gmail_Email_Manager`
メールの送受信を管理

```php
$manager = new Gmail_Email_Manager();
$result = $manager->send_email($email_data);
```

#### `Gmail_OAuth`
OAuth認証を管理

```php
$oauth = new Gmail_OAuth();
$auth_url = $oauth->get_auth_url();
```

### JavaScript API

#### `WPGmailPlugin`
フロントエンド機能を管理

```javascript
// メール送信
WPGmailPlugin.sendEmail(formData);

// 受信箱更新
WPGmailPlugin.refreshInbox();
```

## 要件

### システム要件
- WordPress 5.0以上
- PHP 7.4以上
- MySQL 5.6以上
- cURL拡張機能
- JSON拡張機能

### Gmail API要件
- Google Cloud Consoleアカウント
- Gmail API有効化
- OAuth 2.0認証情報

## ライセンス

このプラグインはGPL v2以降のライセンスで配布されています。

## サポート

### ドキュメント
詳細なドキュメントは[プラグインサイト](https://example.com)で確認できます。

### 問題報告
バグや機能要望は[GitHub Issues](https://github.com/example/wp-gmail-plugin/issues)で報告してください。

### 貢献
プルリクエストやコントリビューションを歓迎します。

## 更新履歴

### Version 1.0.0
- 初回リリース
- Gmail APIによるメール送受信機能
- 管理画面インターフェース
- ショートコード機能
- OAuth 2.0認証

## 作者

**Your Name**
- Website: [https://example.com](https://example.com)
- Email: your-email@example.com

## 謝辞

このプラグインの開発にあたり、以下のライブラリやリソースを使用させていただきました：
- Gmail API
- WordPress REST API
- jQuery
- Font Awesome (アイコン)

---

**注意**: このプラグインを使用する前に、必ずテスト環境で動作確認を行ってください。本番環境での使用は自己責任でお願いします。
