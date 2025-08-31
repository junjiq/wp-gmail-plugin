<?php
/**
 * Gmail Email Manager Class
 *
 * メールの送受信や管理を行うクラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class Gmail_Email_Manager {

    private $api_client;

    public function __construct() {
        $this->api_client = new Gmail_API_Client();
    }

    /**
     * メールを送信
     */
    public function send_email($data) {
        // 必須フィールドのチェック
        $required_fields = array('to', 'subject', 'body');
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return array(
                    'success' => false,
                    'error' => sprintf(__('Field "%s" is required.', 'wp-gmail-plugin'), $field)
                );
            }
        }

        // メールヘッダーを構築
        $headers = array();
        $headers[] = 'To: ' . $data['to'];
        $headers[] = 'Subject: ' . $data['subject'];

        if (!empty($data['from'])) {
            $headers[] = 'From: ' . $data['from'];
        }

        if (!empty($data['cc'])) {
            $headers[] = 'Cc: ' . $data['cc'];
        }

        if (!empty($data['bcc'])) {
            $headers[] = 'Bcc: ' . $data['bcc'];
        }

        // Content-Typeを設定
        $is_html = !empty($data['is_html']) && $data['is_html'] === 'true';
        if ($is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        $headers[] = 'MIME-Version: 1.0';

        // メール本文を構築
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $data['body'];

        // Base64エンコード（URL-safe）
        $raw_message = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

        // メールを送信
        $result = $this->api_client->send_message($raw_message);

        if ($result['success']) {
            return array(
                'success' => true,
                'message' => __('Email sent successfully!', 'wp-gmail-plugin'),
                'message_id' => $result['data']['id'] ?? ''
            );
        }

        return array(
            'success' => false,
            'error' => __('Failed to send email: ', 'wp-gmail-plugin') . $result['error']
        );
    }

    /**
     * メールリストを取得
     */
    public function get_emails($data = array()) {
        $query = $data['query'] ?? '';
        $max_results = intval($data['max_results'] ?? 10);
        $page_token = $data['page_token'] ?? '';

        // メッセージリストを取得
        $messages_result = $this->api_client->get_messages($query, $max_results, $page_token);

        if (!$messages_result['success']) {
            return array(
                'success' => false,
                'error' => __('Failed to fetch emails: ', 'wp-gmail-plugin') . $messages_result['error']
            );
        }

        $messages = $messages_result['data']['messages'] ?? array();
        $next_page_token = $messages_result['data']['nextPageToken'] ?? '';

        $emails = array();

        // 各メッセージの詳細を取得
        foreach ($messages as $message) {
            $message_result = $this->api_client->get_message($message['id']);

            if ($message_result['success']) {
                $email_data = $this->parse_message($message_result['data']);
                if ($email_data) {
                    $emails[] = $email_data;

                    // データベースに保存
                    $this->save_email_to_db($email_data);
                }
            }
        }

        return array(
            'success' => true,
            'emails' => $emails,
            'next_page_token' => $next_page_token,
            'total_count' => count($emails)
        );
    }

    /**
     * メッセージデータを解析
     */
    private function parse_message($message_data) {
        $headers = $message_data['payload']['headers'] ?? array();
        $parts = $message_data['payload']['parts'] ?? array();

        // ヘッダー情報を抽出
        $parsed_headers = array();
        foreach ($headers as $header) {
            $parsed_headers[strtolower($header['name'])] = $header['value'];
        }

        // 本文を抽出
        $body = $this->extract_body($message_data['payload']);

        // ラベルを取得
        $labels = $message_data['labelIds'] ?? array();

        return array(
            'message_id' => $message_data['id'],
            'thread_id' => $message_data['threadId'],
            'from_email' => $this->extract_email($parsed_headers['from'] ?? ''),
            'from_name' => $this->extract_name($parsed_headers['from'] ?? ''),
            'to_email' => $parsed_headers['to'] ?? '',
            'subject' => $parsed_headers['subject'] ?? '',
            'body' => $body,
            'snippet' => $message_data['snippet'] ?? '',
            'labels' => json_encode($labels),
            'is_read' => !in_array('UNREAD', $labels),
            'is_starred' => in_array('STARRED', $labels),
            'received_date' => date('Y-m-d H:i:s', intval($message_data['internalDate']) / 1000),
            'raw_data' => json_encode($message_data)
        );
    }

    /**
     * メッセージから本文を抽出
     */
    private function extract_body($payload) {
        $body = '';

        if (isset($payload['body']['data'])) {
            // シンプルなメッセージ
            $body = base64_decode(strtr($payload['body']['data'], '-_', '+/'));
        } elseif (isset($payload['parts'])) {
            // マルチパートメッセージ
            foreach ($payload['parts'] as $part) {
                if ($part['mimeType'] === 'text/plain' || $part['mimeType'] === 'text/html') {
                    if (isset($part['body']['data'])) {
                        $body = base64_decode(strtr($part['body']['data'], '-_', '+/'));
                        break;
                    }
                }
            }
        }

        return $body;
    }

    /**
     * From ヘッダーからメールアドレスを抽出
     */
    private function extract_email($from_header) {
        if (preg_match('/<([^>]+)>/', $from_header, $matches)) {
            return $matches[1];
        }

        if (filter_var($from_header, FILTER_VALIDATE_EMAIL)) {
            return $from_header;
        }

        return '';
    }

    /**
     * From ヘッダーから名前を抽出
     */
    private function extract_name($from_header) {
        if (preg_match('/^(.+?)\s*<[^>]+>$/', $from_header, $matches)) {
            return trim($matches[1], ' "');
        }

        return $this->extract_email($from_header);
    }

    /**
     * メールをデータベースに保存
     */
    private function save_email_to_db($email_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gmail_plugin_emails';

        // 既存のメッセージかチェック
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE message_id = %s",
            $email_data['message_id']
        ));

        if ($existing) {
            // 更新
            $wpdb->update(
                $table_name,
                array(
                    'thread_id' => $email_data['thread_id'],
                    'from_email' => $email_data['from_email'],
                    'from_name' => $email_data['from_name'],
                    'to_email' => $email_data['to_email'],
                    'subject' => $email_data['subject'],
                    'body' => $email_data['body'],
                    'snippet' => $email_data['snippet'],
                    'labels' => $email_data['labels'],
                    'is_read' => $email_data['is_read'],
                    'is_starred' => $email_data['is_starred'],
                    'received_date' => $email_data['received_date']
                ),
                array('message_id' => $email_data['message_id'])
            );
        } else {
            // 挿入
            $wpdb->insert(
                $table_name,
                array(
                    'message_id' => $email_data['message_id'],
                    'thread_id' => $email_data['thread_id'],
                    'from_email' => $email_data['from_email'],
                    'from_name' => $email_data['from_name'],
                    'to_email' => $email_data['to_email'],
                    'subject' => $email_data['subject'],
                    'body' => $email_data['body'],
                    'snippet' => $email_data['snippet'],
                    'labels' => $email_data['labels'],
                    'is_read' => $email_data['is_read'],
                    'is_starred' => $email_data['is_starred'],
                    'received_date' => $email_data['received_date']
                )
            );
        }
    }

    /**
     * データベースからメールを取得
     */
    public function get_emails_from_db($limit = 10, $offset = 0, $search = '') {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gmail_plugin_emails';

        $where = '';
        $params = array();

        if (!empty($search)) {
            $where = "WHERE (subject LIKE %s OR from_email LIKE %s OR from_name LIKE %s OR body LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params = array($search_term, $search_term, $search_term, $search_term);
        }

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name $where ORDER BY received_date DESC LIMIT %d OFFSET %d",
            array_merge($params, array($limit, $offset))
        );

        $emails = $wpdb->get_results($query, ARRAY_A);

        // 総件数を取得
        $count_query = "SELECT COUNT(*) FROM $table_name $where";
        if (!empty($params)) {
            $total_count = $wpdb->get_var($wpdb->prepare($count_query, $params));
        } else {
            $total_count = $wpdb->get_var($count_query);
        }

        return array(
            'success' => true,
            'emails' => $emails,
            'total_count' => intval($total_count)
        );
    }

    /**
     * メールを既読/未読にする
     */
    public function mark_email($message_id, $action) {
        switch ($action) {
            case 'read':
                $result = $this->api_client->mark_as_read($message_id);
                break;
            case 'unread':
                $result = $this->api_client->mark_as_unread($message_id);
                break;
            case 'star':
                $result = $this->api_client->star_message($message_id);
                break;
            case 'unstar':
                $result = $this->api_client->unstar_message($message_id);
                break;
            default:
                return array('success' => false, 'error' => 'Invalid action');
        }

        if ($result['success']) {
            // データベースも更新
            global $wpdb;
            $table_name = $wpdb->prefix . 'gmail_plugin_emails';

            $update_data = array();
            if ($action === 'read') {
                $update_data['is_read'] = 1;
            } elseif ($action === 'unread') {
                $update_data['is_read'] = 0;
            } elseif ($action === 'star') {
                $update_data['is_starred'] = 1;
            } elseif ($action === 'unstar') {
                $update_data['is_starred'] = 0;
            }

            if (!empty($update_data)) {
                $wpdb->update(
                    $table_name,
                    $update_data,
                    array('message_id' => $message_id)
                );
            }

            return array('success' => true, 'message' => 'Email updated successfully');
        }

        return $result;
    }
}
