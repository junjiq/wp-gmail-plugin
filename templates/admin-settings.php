<?php
/**
 * Admin Settings Template
 *
 * Gmail プラグインの設定画面
 */

if (!defined('ABSPATH')) {
    exit;
}

// 設定の保存処理
if (isset($_POST['submit']) && wp_verify_nonce($_POST['wp_gmail_nonce'], 'wp_gmail_settings')) {
    $options = get_option('wp_gmail_plugin_options', array());

    $options['client_id'] = sanitize_text_field($_POST['client_id']);
    $options['client_secret'] = sanitize_text_field($_POST['client_secret']);
    $options['redirect_uri'] = esc_url_raw($_POST['redirect_uri']);
    $options['sync_interval'] = intval($_POST['sync_interval']);
    $options['max_emails'] = intval($_POST['max_emails']);

    update_option('wp_gmail_plugin_options', $options);

    wp_gmail_show_success(__('Settings saved successfully!', 'wp-gmail-plugin'));
}

// OAuth認証処理
if (isset($_GET['code']) && !empty($_GET['code'])) {
    $oauth = new Gmail_OAuth();
    $result = $oauth->handle_callback($_GET);

    if ($result['success']) {
        wp_gmail_show_success($result['message']);
    } else {
        wp_gmail_show_error($result['error']);
    }
}

// 認証リセット処理
if (isset($_POST['reset_auth']) && wp_verify_nonce($_POST['wp_gmail_nonce'], 'wp_gmail_settings')) {
    $oauth = new Gmail_OAuth();
    $result = $oauth->reset_auth();

    if ($result['success']) {
        wp_gmail_show_success($result['message']);
    }
}

$options = get_option('wp_gmail_plugin_options', array());
$oauth = new Gmail_OAuth();
$auth_status = $oauth->check_auth_status();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="gmail-plugin-settings">
        <div class="postbox-container" style="width: 70%;">
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Gmail API Settings', 'wp-gmail-plugin'); ?></span></h2>
                <div class="inside">
                    <form method="post" action="">
                        <?php wp_gmail_nonce_field('wp_gmail_settings'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="client_id"><?php _e('Client ID', 'wp-gmail-plugin'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="client_id" name="client_id"
                                           value="<?php echo wp_gmail_esc_attr($options['client_id'] ?? ''); ?>"
                                           class="regular-text" required>
                                    <p class="description">
                                        <?php _e('Google Cloud ConsoleでGmail APIを有効にして取得したClient IDを入力してください。', 'wp-gmail-plugin'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="client_secret"><?php _e('Client Secret', 'wp-gmail-plugin'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="client_secret" name="client_secret"
                                           value="<?php echo wp_gmail_esc_attr($options['client_secret'] ?? ''); ?>"
                                           class="regular-text" required>
                                    <p class="description">
                                        <?php _e('Google Cloud ConsoleでGmail APIを有効にして取得したClient Secretを入力してください。', 'wp-gmail-plugin'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="redirect_uri"><?php _e('Redirect URI', 'wp-gmail-plugin'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="redirect_uri" name="redirect_uri"
                                           value="<?php echo wp_gmail_esc_attr($options['redirect_uri'] ?? admin_url('admin.php?page=wp-gmail-plugin-settings')); ?>"
                                           class="regular-text" readonly>
                                    <p class="description">
                                        <?php _e('この URLをGoogle Cloud Consoleの認証済みリダイレクト URIに追加してください。', 'wp-gmail-plugin'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="sync_interval"><?php _e('Sync Interval (seconds)', 'wp-gmail-plugin'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="sync_interval" name="sync_interval"
                                           value="<?php echo wp_gmail_esc_attr($options['sync_interval'] ?? 300); ?>"
                                           min="60" max="3600" class="small-text">
                                    <p class="description">
                                        <?php _e('メール同期の間隔を秒単位で指定してください（60-3600秒）。', 'wp-gmail-plugin'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="max_emails"><?php _e('Max Emails to Fetch', 'wp-gmail-plugin'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="max_emails" name="max_emails"
                                           value="<?php echo wp_gmail_esc_attr($options['max_emails'] ?? 100); ?>"
                                           min="10" max="1000" class="small-text">
                                    <p class="description">
                                        <?php _e('一度に取得するメールの最大数を指定してください（10-1000件）。', 'wp-gmail-plugin'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(__('Save Settings', 'wp-gmail-plugin')); ?>
                    </form>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Authentication Status', 'wp-gmail-plugin'); ?></span></h2>
                <div class="inside">
                    <?php if ($auth_status['authenticated']): ?>
                        <div class="gmail-auth-status authenticated">
                            <p><strong><?php _e('Status:', 'wp-gmail-plugin'); ?></strong>
                               <span class="status-success"><?php _e('Authenticated', 'wp-gmail-plugin'); ?></span></p>
                            <p><strong><?php _e('Email:', 'wp-gmail-plugin'); ?></strong>
                               <?php echo wp_gmail_esc_html($auth_status['email']); ?></p>
                            <p><strong><?php _e('Total Messages:', 'wp-gmail-plugin'); ?></strong>
                               <?php echo number_format($auth_status['messages_total']); ?></p>
                            <p><strong><?php _e('Total Threads:', 'wp-gmail-plugin'); ?></strong>
                               <?php echo number_format($auth_status['threads_total']); ?></p>
                        </div>

                        <form method="post" action="" style="margin-top: 15px;">
                            <?php wp_gmail_nonce_field('wp_gmail_settings'); ?>
                            <input type="submit" name="reset_auth"
                                   value="<?php _e('Reset Authentication', 'wp-gmail-plugin'); ?>"
                                   class="button button-secondary"
                                   onclick="return confirm('<?php _e('Are you sure you want to reset the authentication?', 'wp-gmail-plugin'); ?>');">
                        </form>
                    <?php else: ?>
                        <div class="gmail-auth-status not-authenticated">
                            <p><strong><?php _e('Status:', 'wp-gmail-plugin'); ?></strong>
                               <span class="status-error"><?php _e('Not Authenticated', 'wp-gmail-plugin'); ?></span></p>
                            <p><?php _e('Please authenticate with Gmail to use the plugin features.', 'wp-gmail-plugin'); ?></p>
                        </div>

                        <?php if (wp_gmail_is_configured()): ?>
                            <a href="<?php echo esc_url((new Gmail_API_Client())->get_auth_url()); ?>"
                               class="button button-primary" target="_blank">
                                <?php _e('Authenticate with Gmail', 'wp-gmail-plugin'); ?>
                            </a>
                        <?php else: ?>
                            <p class="description">
                                <?php _e('Please save your API credentials first, then authenticate with Gmail.', 'wp-gmail-plugin'); ?>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="postbox-container" style="width: 25%; margin-left: 5%;">
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Setup Instructions', 'wp-gmail-plugin'); ?></span></h2>
                <div class="inside">
                    <ol>
                        <li><?php _e('Google Cloud Consoleにアクセス', 'wp-gmail-plugin'); ?></li>
                        <li><?php _e('新しいプロジェクトを作成または既存のプロジェクトを選択', 'wp-gmail-plugin'); ?></li>
                        <li><?php _e('Gmail APIを有効化', 'wp-gmail-plugin'); ?></li>
                        <li><?php _e('認証情報を作成（OAuth 2.0 クライアント ID）', 'wp-gmail-plugin'); ?></li>
                        <li><?php _e('リダイレクトURIを設定', 'wp-gmail-plugin'); ?></li>
                        <li><?php _e('Client IDとClient Secretをこのページに入力', 'wp-gmail-plugin'); ?></li>
                        <li><?php _e('Gmailで認証を実行', 'wp-gmail-plugin'); ?></li>
                    </ol>

                    <p><strong><?php _e('Required Scopes:', 'wp-gmail-plugin'); ?></strong></p>
                    <ul>
                        <li><code>gmail.readonly</code></li>
                        <li><code>gmail.send</code></li>
                        <li><code>gmail.modify</code></li>
                    </ul>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Plugin Information', 'wp-gmail-plugin'); ?></span></h2>
                <div class="inside">
                    <p><strong><?php _e('Version:', 'wp-gmail-plugin'); ?></strong> <?php echo WP_GMAIL_PLUGIN_VERSION; ?></p>
                    <p><strong><?php _e('Plugin Directory:', 'wp-gmail-plugin'); ?></strong><br>
                       <code><?php echo WP_GMAIL_PLUGIN_PLUGIN_DIR; ?></code></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gmail-auth-status {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.gmail-auth-status.authenticated {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.gmail-auth-status.not-authenticated {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}

.status-success {
    color: #155724;
    font-weight: bold;
}

.status-error {
    color: #721c24;
    font-weight: bold;
}

.gmail-plugin-settings .postbox-container {
    float: left;
}

.gmail-plugin-settings::after {
    content: "";
    display: table;
    clear: both;
}
</style>
