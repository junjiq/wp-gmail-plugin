<?php
/**
 * Gmail OAuth Class
 *
 * OAuth認証プロセスを管理するクラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gmail_OAuth {

    private $api_client;

    public function __construct() {
        $this->api_client = new Gmail_API_Client();
    }

    /**
     * 認証プロセスを開始
     */
    public function start_auth() {
        $auth_url = $this->api_client->get_auth_url();

        return array(
            'success' => true,
            'auth_url' => $auth_url,
            'message' => __('Please visit the authorization URL to complete the authentication process.', 'wp-gmail-plugin')
        );
    }

    /**
     * 認証コールバックを処理
     */
    public function handle_callback($data) {
        if (!isset($data['code'])) {
            return array(
                'success' => false,
                'error' => __('Authorization code not provided.', 'wp-gmail-plugin')
            );
        }

        $result = $this->api_client->exchange_code_for_token($data['code']);

        if ($result['success']) {
            return array(
                'success' => true,
                'message' => __('Authentication successful! You can now use Gmail features.', 'wp-gmail-plugin')
            );
        }

        return array(
            'success' => false,
            'error' => __('Failed to complete authentication: ', 'wp-gmail-plugin') . $result['error']
        );
    }

    /**
     * 認証状態をチェック
     */
    public function check_auth_status() {
        $is_authenticated = $this->api_client->is_authenticated();

        if ($is_authenticated) {
            // プロフィール情報を取得して認証が有効かテスト
            $profile_result = $this->api_client->get_profile();

            if ($profile_result['success']) {
                return array(
                    'success' => true,
                    'authenticated' => true,
                    'email' => $profile_result['data']['emailAddress'] ?? '',
                    'messages_total' => $profile_result['data']['messagesTotal'] ?? 0,
                    'threads_total' => $profile_result['data']['threadsTotal'] ?? 0
                );
            }
        }

        return array(
            'success' => true,
            'authenticated' => false,
            'message' => __('Not authenticated or authentication expired.', 'wp-gmail-plugin')
        );
    }

    /**
     * 認証をリセット（ログアウト）
     */
    public function reset_auth() {
        $options = get_option('wp_gmail_plugin_options', array());
        $options['access_token'] = '';
        $options['refresh_token'] = '';
        $options['token_expires'] = 0;
        update_option('wp_gmail_plugin_options', $options);

        return array(
            'success' => true,
            'message' => __('Authentication has been reset.', 'wp-gmail-plugin')
        );
    }

    /**
     * アクセストークンを手動で更新
     */
    public function refresh_token() {
        $result = $this->api_client->refresh_access_token();

        if ($result['success']) {
            return array(
                'success' => true,
                'message' => __('Access token refreshed successfully.', 'wp-gmail-plugin')
            );
        }

        return array(
            'success' => false,
            'error' => __('Failed to refresh access token: ', 'wp-gmail-plugin') . $result['error']
        );
    }
}
