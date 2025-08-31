<?php
/**
 * Uninstall Script for WP Gmail Plugin
 *
 * プラグイン削除時に実行されるスクリプト
 */

// WordPressから呼び出されていない場合は終了
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * プラグインデータの削除
 */
function wp_gmail_plugin_uninstall() {
    global $wpdb;

    // オプションの削除
    delete_option('wp_gmail_plugin_options');
    delete_option('wp_gmail_plugin_draft');

    // データベーステーブルの削除
    $table_name = $wpdb->prefix . 'gmail_plugin_emails';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");

    // トランジェントの削除
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_wp_gmail_%'
         OR option_name LIKE '_transient_timeout_wp_gmail_%'"
    );

    // ユーザーメタの削除
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta}
         WHERE meta_key LIKE 'wp_gmail_%'"
    );

    // カスタム投稿タイプの削除（もしあれば）
    $posts = get_posts(array(
        'numberposts' => -1,
        'post_type' => 'gmail_template',
        'post_status' => 'any'
    ));

    foreach ($posts as $post) {
        wp_delete_post($post->ID, true);
    }

    // アップロードディレクトリ内のファイル削除
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/wp-gmail-plugin';

    if (is_dir($plugin_upload_dir)) {
        wp_gmail_remove_directory($plugin_upload_dir);
    }

    // キャッシュのクリア
    wp_cache_flush();
}

/**
 * ディレクトリを再帰的に削除
 */
function wp_gmail_remove_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $files = array_diff(scandir($dir), array('.', '..'));

    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;

        if (is_dir($path)) {
            wp_gmail_remove_directory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

// アンインストール処理の実行
wp_gmail_plugin_uninstall();
