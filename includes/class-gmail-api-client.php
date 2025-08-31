<?php
/**
 * Gmail API Client Class
 *
 * Gmail APIとの通信を管理するクラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gmail_API_Client {

    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $access_token;
    private $refresh_token;
    private $api_base_url = 'https://gmail.googleapis.com/gmail/v1';
    private $oauth_base_url = 'https://accounts.google.com/o/oauth2/v2';

    public function __construct() {
        $options = get_option('wp_gmail_plugin_options', array());
        $this->client_id = $options['client_id'] ?? '';
        $this->client_secret = $options['client_secret'] ?? '';
        $this->redirect_uri = $options['redirect_uri'] ?? '';
        $this->access_token = $options['access_token'] ?? '';
        $this->refresh_token = $options['refresh_token'] ?? '';
    }

    /**
     * OAuth認証URLを生成
     */
    public function get_auth_url() {
        $params = array(
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/gmail.modify',
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        );

        return $this->oauth_base_url . '/auth?' . http_build_query($params);
    }

    /**
     * 認証コードからアクセストークンを取得
     */
    public function exchange_code_for_token($code) {
        $url = $this->oauth_base_url . '/token';

        $data = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirect_uri
        );

        $response = wp_remote_post($url, array(
            'body' => $data,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $token_data = json_decode($body, true);

        if (isset($token_data['access_token'])) {
            $this->access_token = $token_data['access_token'];
            $this->refresh_token = $token_data['refresh_token'] ?? '';

            // オプションを更新
            $options = get_option('wp_gmail_plugin_options', array());
            $options['access_token'] = $this->access_token;
            $options['refresh_token'] = $this->refresh_token;
            $options['token_expires'] = time() + ($token_data['expires_in'] ?? 3600);
            update_option('wp_gmail_plugin_options', $options);

            return array('success' => true, 'token_data' => $token_data);
        }

        return array('success' => false, 'error' => 'Failed to get access token', 'response' => $token_data);
    }

    /**
     * リフレッシュトークンを使用してアクセストークンを更新
     */
    public function refresh_access_token() {
        if (empty($this->refresh_token)) {
            return array('success' => false, 'error' => 'No refresh token available');
        }

        $url = $this->oauth_base_url . '/token';

        $data = array(
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'refresh_token' => $this->refresh_token,
            'grant_type' => 'refresh_token'
        );

        $response = wp_remote_post($url, array(
            'body' => $data,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $token_data = json_decode($body, true);

        if (isset($token_data['access_token'])) {
            $this->access_token = $token_data['access_token'];

            // オプションを更新
            $options = get_option('wp_gmail_plugin_options', array());
            $options['access_token'] = $this->access_token;
            $options['token_expires'] = time() + ($token_data['expires_in'] ?? 3600);
            update_option('wp_gmail_plugin_options', $options);

            return array('success' => true, 'token_data' => $token_data);
        }

        return array('success' => false, 'error' => 'Failed to refresh access token', 'response' => $token_data);
    }

    /**
     * APIリクエストを実行
     */
    private function make_request($endpoint, $method = 'GET', $data = null) {
        // トークンの有効性をチェック
        $options = get_option('wp_gmail_plugin_options', array());
        if (time() >= ($options['token_expires'] ?? 0)) {
            $refresh_result = $this->refresh_access_token();
            if (!$refresh_result['success']) {
                return $refresh_result;
            }
        }

        $url = $this->api_base_url . $endpoint;
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/json'
        );

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30
        );

        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = is_array($data) ? json_encode($data) : $data;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code >= 200 && $status_code < 300) {
            return array('success' => true, 'data' => $data);
        }

        return array(
            'success' => false,
            'error' => $data['error']['message'] ?? 'API request failed',
            'status_code' => $status_code,
            'response' => $data
        );
    }

    /**
     * ユーザープロフィール情報を取得
     */
    public function get_profile() {
        return $this->make_request('/users/me/profile');
    }

    /**
     * メールリストを取得
     */
    public function get_messages($query = '', $max_results = 10, $page_token = '') {
        $params = array(
            'q' => $query,
            'maxResults' => $max_results
        );

        if (!empty($page_token)) {
            $params['pageToken'] = $page_token;
        }

        $endpoint = '/users/me/messages?' . http_build_query($params);
        return $this->make_request($endpoint);
    }

    /**
     * 特定のメッセージを取得
     */
    public function get_message($message_id, $format = 'full') {
        $endpoint = '/users/me/messages/' . $message_id . '?format=' . $format;
        return $this->make_request($endpoint);
    }

    /**
     * メールを送信
     */
    public function send_message($raw_message) {
        $data = array(
            'raw' => $raw_message
        );

        return $this->make_request('/users/me/messages/send', 'POST', $data);
    }

    /**
     * メールを既読にする
     */
    public function mark_as_read($message_id) {
        $data = array(
            'removeLabelIds' => array('UNREAD')
        );

        return $this->make_request('/users/me/messages/' . $message_id . '/modify', 'POST', $data);
    }

    /**
     * メールを未読にする
     */
    public function mark_as_unread($message_id) {
        $data = array(
            'addLabelIds' => array('UNREAD')
        );

        return $this->make_request('/users/me/messages/' . $message_id . '/modify', 'POST', $data);
    }

    /**
     * メールにスターを付ける
     */
    public function star_message($message_id) {
        $data = array(
            'addLabelIds' => array('STARRED')
        );

        return $this->make_request('/users/me/messages/' . $message_id . '/modify', 'POST', $data);
    }

    /**
     * メールからスターを外す
     */
    public function unstar_message($message_id) {
        $data = array(
            'removeLabelIds' => array('STARRED')
        );

        return $this->make_request('/users/me/messages/' . $message_id . '/modify', 'POST', $data);
    }

    /**
     * ラベル一覧を取得
     */
    public function get_labels() {
        return $this->make_request('/users/me/labels');
    }

    /**
     * 認証状態をチェック
     */
    public function is_authenticated() {
        return !empty($this->access_token);
    }

    /**
     * 設定を更新
     */
    public function update_credentials($client_id, $client_secret, $redirect_uri) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->redirect_uri = $redirect_uri;

        $options = get_option('wp_gmail_plugin_options', array());
        $options['client_id'] = $client_id;
        $options['client_secret'] = $client_secret;
        $options['redirect_uri'] = $redirect_uri;
        update_option('wp_gmail_plugin_options', $options);
    }
}
