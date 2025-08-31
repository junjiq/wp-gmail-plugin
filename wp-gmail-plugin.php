<?php
/**
 * Plugin Name: WP Gmail Plugin
 * Plugin URI: https://example.com/wp-gmail-plugin
 * Description: Gmail APIを使用してWordPressからメールの送受信を行うプラグイン
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-gmail-plugin
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// プラグインが直接アクセスされることを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの定数を定義
define('WP_GMAIL_PLUGIN_VERSION', '1.0.0');
define('WP_GMAIL_PLUGIN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_GMAIL_PLUGIN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_GMAIL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * メインプラグインクラス
 */
class WPGmailPlugin {

    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * フックを初期化
     */
    private function init_hooks() {
        // プラグインが有効化されたとき
        register_activation_hook(__FILE__, array($this, 'activate'));

        // プラグインが無効化されたとき
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // WordPressが初期化されたとき
        add_action('init', array($this, 'init'));

        // 管理画面のメニューを追加
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // スタイルとスクリプトを読み込み
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // ショートコードを登録
        add_shortcode('gmail_compose', array($this, 'compose_shortcode'));
        add_shortcode('gmail_inbox', array($this, 'inbox_shortcode'));

        // AJAX処理
        add_action('wp_ajax_gmail_send_email', array($this, 'ajax_send_email'));
        add_action('wp_ajax_gmail_get_emails', array($this, 'ajax_get_emails'));
        add_action('wp_ajax_gmail_auth_callback', array($this, 'ajax_auth_callback'));
    }

    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        $this->create_tables();
        $this->set_default_options();
        flush_rewrite_rules();
    }

    /**
     * プラグイン無効化時の処理
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * WordPress初期化時の処理
     */
    public function init() {
        // 国際化対応
        load_plugin_textdomain('wp-gmail-plugin', false, dirname(WP_GMAIL_PLUGIN_BASENAME) . '/languages');

        // 必要なクラスファイルを読み込み
        $this->load_dependencies();
    }

    /**
     * 依存ファイルを読み込み
     */
    private function load_dependencies() {
        require_once WP_GMAIL_PLUGIN_PLUGIN_DIR . 'includes/class-gmail-api-client.php';
        require_once WP_GMAIL_PLUGIN_PLUGIN_DIR . 'includes/class-gmail-oauth.php';
        require_once WP_GMAIL_PLUGIN_PLUGIN_DIR . 'includes/class-gmail-email-manager.php';
        require_once WP_GMAIL_PLUGIN_PLUGIN_DIR . 'includes/functions.php';
    }

    /**
     * 管理画面メニューを追加
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Gmail Plugin', 'wp-gmail-plugin'),
            __('Gmail', 'wp-gmail-plugin'),
            'manage_options',
            'wp-gmail-plugin',
            array($this, 'admin_page'),
            'dashicons-email-alt',
            30
        );

        add_submenu_page(
            'wp-gmail-plugin',
            __('Settings', 'wp-gmail-plugin'),
            __('Settings', 'wp-gmail-plugin'),
            'manage_options',
            'wp-gmail-plugin-settings',
            array($this, 'settings_page')
        );

        add_submenu_page(
            'wp-gmail-plugin',
            __('Compose Email', 'wp-gmail-plugin'),
            __('Compose', 'wp-gmail-plugin'),
            'manage_options',
            'wp-gmail-plugin-compose',
            array($this, 'compose_page')
        );

        add_submenu_page(
            'wp-gmail-plugin',
            __('Inbox', 'wp-gmail-plugin'),
            __('Inbox', 'wp-gmail-plugin'),
            'manage_options',
            'wp-gmail-plugin-inbox',
            array($this, 'inbox_page')
        );
    }

    /**
     * フロントエンドのスタイルとスクリプトを読み込み
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'wp-gmail-plugin-style',
            WP_GMAIL_PLUGIN_PLUGIN_URL . 'assets/css/style.css',
            array(),
            WP_GMAIL_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'wp-gmail-plugin-script',
            WP_GMAIL_PLUGIN_PLUGIN_URL . 'assets/js/script.js',
            array('jquery'),
            WP_GMAIL_PLUGIN_VERSION,
            true
        );

        wp_localize_script('wp-gmail-plugin-script', 'wpGmailPlugin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_gmail_plugin_nonce'),
            'plugin_url' => WP_GMAIL_PLUGIN_PLUGIN_URL
        ));
    }

    /**
     * 管理画面のスタイルとスクリプトを読み込み
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'wp-gmail-plugin') === false) {
            return;
        }

        wp_enqueue_style(
            'wp-gmail-plugin-admin-style',
            WP_GMAIL_PLUGIN_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            WP_GMAIL_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'wp-gmail-plugin-admin-script',
            WP_GMAIL_PLUGIN_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            WP_GMAIL_PLUGIN_VERSION,
            true
        );

        wp_localize_script('wp-gmail-plugin-admin-script', 'wpGmailPluginAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_gmail_plugin_admin_nonce'),
            'plugin_url' => WP_GMAIL_PLUGIN_PLUGIN_URL
        ));
    }

    /**
     * メイン管理画面
     */
    public function admin_page() {
        include_once WP_GMAIL_PLUGIN_PLUGIN_DIR . 'templates/admin-main.php';
    }

    /**
     * 設定画面
     */
    public function settings_page() {
        include_once WP_GMAIL_PLUGIN_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * メール作成画面
     */
    public function compose_page() {
        include_once WP_GMAIL_PLUGIN_PLUGIN_DIR . 'templates/admin-compose.php';
    }

    /**
     * 受信箱画面
     */
    public function inbox_page() {
        include_once WP_GMAIL_PLUGIN_PLUGIN_DIR . 'templates/admin-inbox.php';
    }

    /**
     * メール作成ショートコード
     */
    public function compose_shortcode($atts) {
        $atts = shortcode_atts(array(
            'class' => 'wp-gmail-compose',
            'to' => '',
            'subject' => ''
        ), $atts, 'gmail_compose');

        ob_start();
        include WP_GMAIL_PLUGIN_PLUGIN_DIR . 'templates/shortcode-compose.php';
        return ob_get_clean();
    }

    /**
     * 受信箱ショートコード
     */
    public function inbox_shortcode($atts) {
        $atts = shortcode_atts(array(
            'class' => 'wp-gmail-inbox',
            'limit' => '10'
        ), $atts, 'gmail_inbox');

        ob_start();
        include WP_GMAIL_PLUGIN_PLUGIN_DIR . 'templates/shortcode-inbox.php';
        return ob_get_clean();
    }

    /**
     * メール送信AJAX処理
     */
    public function ajax_send_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'wp_gmail_plugin_nonce') &&
            !wp_verify_nonce($_POST['nonce'], 'wp_gmail_plugin_admin_nonce')) {
            wp_die('Security check failed');
        }

        $email_manager = new Gmail_Email_Manager();
        $result = $email_manager->send_email($_POST);

        wp_send_json($result);
    }

    /**
     * メール取得AJAX処理
     */
    public function ajax_get_emails() {
        if (!wp_verify_nonce($_POST['nonce'], 'wp_gmail_plugin_nonce') &&
            !wp_verify_nonce($_POST['nonce'], 'wp_gmail_plugin_admin_nonce')) {
            wp_die('Security check failed');
        }

        $email_manager = new Gmail_Email_Manager();
        $result = $email_manager->get_emails($_POST);

        wp_send_json($result);
    }

    /**
     * OAuth認証コールバック処理
     */
    public function ajax_auth_callback() {
        if (!wp_verify_nonce($_POST['nonce'], 'wp_gmail_plugin_admin_nonce')) {
            wp_die('Security check failed');
        }

        $oauth = new Gmail_OAuth();
        $result = $oauth->handle_callback($_POST);

        wp_send_json($result);
    }

    /**
     * データベーステーブルを作成
     */
    private function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gmail_plugin_emails';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
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
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * デフォルトオプションを設定
     */
    private function set_default_options() {
        $default_options = array(
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => admin_url('admin.php?page=wp-gmail-plugin-settings'),
            'access_token' => '',
            'refresh_token' => '',
            'token_expires' => 0,
            'sync_interval' => 300, // 5分
            'max_emails' => 100
        );

        add_option('wp_gmail_plugin_options', $default_options);
    }
}

// プラグインを初期化
function wp_gmail_plugin_init() {
    return WPGmailPlugin::get_instance();
}

// WordPressが読み込まれた後にプラグインを初期化
add_action('plugins_loaded', 'wp_gmail_plugin_init');
