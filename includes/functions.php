<?php
/**
 * Helper Functions
 *
 * プラグインで使用するヘルパー関数
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * プラグインのオプションを取得
 */
function wp_gmail_get_option($key = '', $default = null) {
    $options = get_option('wp_gmail_plugin_options', array());

    if (empty($key)) {
        return $options;
    }

    return isset($options[$key]) ? $options[$key] : $default;
}

/**
 * プラグインのオプションを更新
 */
function wp_gmail_update_option($key, $value) {
    $options = get_option('wp_gmail_plugin_options', array());
    $options[$key] = $value;
    return update_option('wp_gmail_plugin_options', $options);
}

/**
 * 認証状態をチェック
 */
function wp_gmail_is_authenticated() {
    $access_token = wp_gmail_get_option('access_token', '');
    return !empty($access_token);
}

/**
 * 安全にHTMLを出力
 */
function wp_gmail_esc_html($text) {
    return esc_html($text);
}

/**
 * 安全に属性を出力
 */
function wp_gmail_esc_attr($text) {
    return esc_attr($text);
}

/**
 * 日付を日本語フォーマットで表示
 */
function wp_gmail_format_date($date, $format = 'Y年m月d日 H:i') {
    if (empty($date)) {
        return '';
    }

    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * メールアドレスの妥当性をチェック
 */
function wp_gmail_is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 複数のメールアドレスを検証
 */
function wp_gmail_validate_email_list($email_list) {
    $emails = array_map('trim', explode(',', $email_list));
    $valid_emails = array();
    $invalid_emails = array();

    foreach ($emails as $email) {
        if (wp_gmail_is_valid_email($email)) {
            $valid_emails[] = $email;
        } else {
            $invalid_emails[] = $email;
        }
    }

    return array(
        'valid' => $valid_emails,
        'invalid' => $invalid_emails
    );
}

/**
 * HTMLメールかテキストメールかを判定
 */
function wp_gmail_is_html_content($content) {
    return $content !== strip_tags($content);
}

/**
 * メール本文をプレビュー用に短縮
 */
function wp_gmail_truncate_content($content, $length = 100) {
    $content = wp_strip_all_tags($content);

    if (mb_strlen($content) <= $length) {
        return $content;
    }

    return mb_substr($content, 0, $length) . '...';
}

/**
 * ファイルサイズを人間が読みやすい形式に変換
 */
function wp_gmail_format_file_size($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * エラーメッセージを表示
 */
function wp_gmail_show_error($message) {
    echo '<div class="notice notice-error"><p>' . wp_gmail_esc_html($message) . '</p></div>';
}

/**
 * 成功メッセージを表示
 */
function wp_gmail_show_success($message) {
    echo '<div class="notice notice-success"><p>' . wp_gmail_esc_html($message) . '</p></div>';
}

/**
 * 情報メッセージを表示
 */
function wp_gmail_show_info($message) {
    echo '<div class="notice notice-info"><p>' . wp_gmail_esc_html($message) . '</p></div>';
}

/**
 * 警告メッセージを表示
 */
function wp_gmail_show_warning($message) {
    echo '<div class="notice notice-warning"><p>' . wp_gmail_esc_html($message) . '</p></div>';
}

/**
 * ナンスフィールドを生成
 */
function wp_gmail_nonce_field($action = 'wp_gmail_plugin_nonce') {
    wp_nonce_field($action, 'wp_gmail_nonce');
}

/**
 * ナンスを検証
 */
function wp_gmail_verify_nonce($nonce, $action = 'wp_gmail_plugin_nonce') {
    return wp_verify_nonce($nonce, $action);
}

/**
 * AJAX URLを取得
 */
function wp_gmail_ajax_url() {
    return admin_url('admin-ajax.php');
}

/**
 * プラグインのアセットURLを取得
 */
function wp_gmail_asset_url($path = '') {
    return WP_GMAIL_PLUGIN_PLUGIN_URL . 'assets/' . ltrim($path, '/');
}

/**
 * プラグインのテンプレートパスを取得
 */
function wp_gmail_template_path($template = '') {
    return WP_GMAIL_PLUGIN_PLUGIN_DIR . 'templates/' . ltrim($template, '/');
}

/**
 * テンプレートを読み込み
 */
function wp_gmail_load_template($template, $vars = array()) {
    $template_path = wp_gmail_template_path($template);

    if (file_exists($template_path)) {
        extract($vars);
        include $template_path;
    }
}

/**
 * デバッグログを記録
 */
function wp_gmail_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[WP Gmail Plugin] [' . strtoupper($level) . '] ' . $message);
    }
}

/**
 * メールラベルを日本語に変換
 */
function wp_gmail_translate_label($label) {
    $translations = array(
        'INBOX' => __('受信トレイ', 'wp-gmail-plugin'),
        'SENT' => __('送信済み', 'wp-gmail-plugin'),
        'DRAFT' => __('下書き', 'wp-gmail-plugin'),
        'SPAM' => __('スパム', 'wp-gmail-plugin'),
        'TRASH' => __('ゴミ箱', 'wp-gmail-plugin'),
        'UNREAD' => __('未読', 'wp-gmail-plugin'),
        'STARRED' => __('スター付き', 'wp-gmail-plugin'),
        'IMPORTANT' => __('重要', 'wp-gmail-plugin')
    );

    return isset($translations[$label]) ? $translations[$label] : $label;
}

/**
 * 設定が完了しているかチェック
 */
function wp_gmail_is_configured() {
    $client_id = wp_gmail_get_option('client_id', '');
    $client_secret = wp_gmail_get_option('client_secret', '');

    return !empty($client_id) && !empty($client_secret);
}

/**
 * 設定完了チェック用のメッセージを表示
 */
function wp_gmail_check_configuration() {
    if (!wp_gmail_is_configured()) {
        wp_gmail_show_warning(
            sprintf(
                __('Gmail APIの設定が完了していません。<a href="%s">設定ページ</a>でClient IDとClient Secretを設定してください。', 'wp-gmail-plugin'),
                admin_url('admin.php?page=wp-gmail-plugin-settings')
            )
        );
        return false;
    }

    if (!wp_gmail_is_authenticated()) {
        wp_gmail_show_info(
            sprintf(
                __('Gmail APIの認証が完了していません。<a href="%s">設定ページ</a>で認証を完了してください。', 'wp-gmail-plugin'),
                admin_url('admin.php?page=wp-gmail-plugin-settings')
            )
        );
        return false;
    }

    return true;
}
