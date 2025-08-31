<?php
/*
Plugin Name: WP Gmail Mailer (SMTP Replacement)
Description: Gmail アカウントのSMTP経由で WordPress のメール送信を完全に置き換えます（受信機能なし、Gmail API は未使用）。
Version: 1.1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit;
}

// Option key / transients
define('WPGP_OPTIONS_KEY', 'wpgp_options');
define('WPGP_TRANSIENT_ERROR_NOTICE', 'wpgp_last_error_notice');

// Default options
function wpgp_default_options() {
    return array(
        'gmail_user'      => '',  // Gmail アドレス
        'gmail_pass'      => '',  // アプリ パスワード（16桁）
        'from_email'      => '',  // 省略時は gmail_user
        'from_name'       => get_bloginfo('name'),
        'encryption'      => 'tls', // tls or ssl
        'port'            => 0,     // 0 の場合は自動（tls:587/ssl:465）
        // ログ / 通知
        'logging_enabled' => true,
        'log_retain_days' => 14,
        'log_max_size_kb' => 1024, // 約 1MB
        'notify_admin'    => true,  // エラー時に管理画面通知
    );
}

function wpgp_get_options() {
    $opts = get_option(WPGP_OPTIONS_KEY, array());
    if (!is_array($opts)) $opts = array();
    return array_merge(wpgp_default_options(), $opts);
}

function wpgp_update_options($new) {
    $current = wpgp_get_options();
    $merged = array_merge($current, $new);
    update_option(WPGP_OPTIONS_KEY, $merged, false);
}

// 管理画面: 設定ページ
add_action('admin_menu', function () {
    add_options_page(
        'WP Gmail Mailer',
        'WP Gmail Mailer',
        'manage_options',
        'wpgp-settings',
        'wpgp_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('wpgp_settings_group', WPGP_OPTIONS_KEY, [
        'sanitize_callback' => 'wpgp_sanitize_options',
    ]);
});

function wpgp_sanitize_options($input) {
    $opts = wpgp_get_options();
    $out = array();

    $out['gmail_user'] = isset($input['gmail_user']) ? sanitize_email($input['gmail_user']) : $opts['gmail_user'];

    // パスワードは空なら変更しない
    if (isset($input['gmail_pass']) && $input['gmail_pass'] !== '') {
        $out['gmail_pass'] = (string)$input['gmail_pass'];
    } else {
        $out['gmail_pass'] = $opts['gmail_pass'];
    }

    $out['from_email'] = isset($input['from_email']) ? sanitize_email($input['from_email']) : $opts['from_email'];
    $out['from_name']  = isset($input['from_name']) ? sanitize_text_field($input['from_name']) : $opts['from_name'];

    $enc = isset($input['encryption']) ? strtolower(sanitize_text_field($input['encryption'])) : $opts['encryption'];
    $out['encryption'] = in_array($enc, array('tls', 'ssl'), true) ? $enc : 'tls';

    $port = isset($input['port']) ? intval($input['port']) : 0;
    $out['port'] = ($port > 0 && $port < 65536) ? $port : 0;

    // ログ/通知
    $out['logging_enabled'] = isset($input['logging_enabled']) ? (bool)$input['logging_enabled'] : false;
    $retain = isset($input['log_retain_days']) ? intval($input['log_retain_days']) : $opts['log_retain_days'];
    $out['log_retain_days'] = ($retain >= 0 && $retain <= 365) ? $retain : 14;
    $max_kb = isset($input['log_max_size_kb']) ? intval($input['log_max_size_kb']) : $opts['log_max_size_kb'];
    $out['log_max_size_kb'] = ($max_kb >= 32 && $max_kb <= 10240) ? $max_kb : 1024;
    $out['notify_admin'] = isset($input['notify_admin']) ? (bool)$input['notify_admin'] : false;

    return $out;
}

function wpgp_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $opts = wpgp_get_options();

    // テスト送信ハンドリング
    $test_result = '';
    if (isset($_POST['wpgp_test_send']) && check_admin_referer('wpgp_test_send')) {
        $to = isset($_POST['wpgp_test_to']) ? sanitize_email(wp_unslash($_POST['wpgp_test_to'])) : '';
        if ($to) {
            $subject = 'WP Gmail Mailer テスト送信';
            $body = 'このメールは WP Gmail Mailer プラグインからのテスト送信です。';
            $sent = wp_mail($to, $subject, $body);
            if (is_wp_error($sent)) {
                $test_result = '<div class="notice notice-error"><p>送信エラー: ' . esc_html($sent->get_error_message()) . '</p></div>';
            } elseif ($sent) {
                $test_result = '<div class="notice notice-success"><p>テストメールを送信しました。</p></div>';
            } else {
                $test_result = '<div class="notice notice-error"><p>送信に失敗しました。</p></div>';
            }
        } else {
            $test_result = '<div class="notice notice-warning"><p>テスト送信先のメールアドレスを入力してください。</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1>WP Gmail Mailer 設定</h1>
        <p>WordPress のメール送信を Gmail SMTP によって置き換えます。Gmail API は使用しません。</p>
        <?php echo $test_result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <form method="post" action="options.php">
            <?php settings_fields('wpgp_settings_group'); ?>
            <?php $opts = wpgp_get_options(); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="wpgp_gmail_user">Gmail アドレス（ユーザー名）</label></th>
                    <td>
                        <input type="email" id="wpgp_gmail_user" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[gmail_user]" value="<?php echo esc_attr($opts['gmail_user']); ?>" class="regular-text" required>
                        <p class="description">2段階認証を有効にし、アプリ パスワードを使用してください。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpgp_gmail_pass">アプリ パスワード</label></th>
                    <td>
                        <input type="password" id="wpgp_gmail_pass" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[gmail_pass]" value="" placeholder="変更しない場合は空のまま" class="regular-text" autocomplete="new-password">
                        <?php if (!empty($opts['gmail_pass'])): ?>
                            <p class="description">保存済みのパスワードがあります。更新する場合のみ入力してください。</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpgp_from_email">From アドレス</label></th>
                    <td>
                        <input type="email" id="wpgp_from_email" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[from_email]" value="<?php echo esc_attr($opts['from_email']); ?>" class="regular-text" placeholder="省略時は Gmail アドレスを使用">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpgp_from_name">From 名称</label></th>
                    <td>
                        <input type="text" id="wpgp_from_name" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[from_name]" value="<?php echo esc_attr($opts['from_name']); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">暗号化方式</th>
                    <td>
                        <fieldset>
                            <label><input type="radio" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[encryption]" value="tls" <?php checked($opts['encryption'], 'tls'); ?>> TLS (推奨)</label><br>
                            <label><input type="radio" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[encryption]" value="ssl" <?php checked($opts['encryption'], 'ssl'); ?>> SSL</label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpgp_port">ポート</label></th>
                    <td>
                        <input type="number" id="wpgp_port" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[port]" value="<?php echo esc_attr((string)($opts['port'] ?: '')); ?>" class="small-text" placeholder="自動">
                        <p class="description">未指定の場合は TLS: 587 / SSL: 465</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">ログ記録</th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[logging_enabled]" value="1" <?php checked($opts['logging_enabled'], true); ?>> 有効にする</label>
                        <p class="description">送信結果やエラーをアップロードディレクトリ内のログに記録します。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpgp_log_retain_days">ログ保持日数</label></th>
                    <td>
                        <input type="number" id="wpgp_log_retain_days" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[log_retain_days]" value="<?php echo esc_attr((string)$opts['log_retain_days']); ?>" class="small-text" min="0" max="365"> 日
                        <p class="description">0 で保持期限なし（サイズ上限でローテーション）。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpgp_log_max_size_kb">ログ最大サイズ</label></th>
                    <td>
                        <input type="number" id="wpgp_log_max_size_kb" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[log_max_size_kb]" value="<?php echo esc_attr((string)$opts['log_max_size_kb']); ?>" class="small-text" min="32" max="10240"> KB
                        <p class="description">上限超過時は新しいファイルにローテーションします。</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">エラー通知</th>
                    <td>
                        <label><input type="checkbox" name="<?php echo esc_attr(WPGP_OPTIONS_KEY); ?>[notify_admin]" value="1" <?php checked($opts['notify_admin'], true); ?>> 管理画面に通知する</label>
                        <p class="description">送信失敗時にダッシュボードへ通知を表示します。</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('変更を保存'); ?>
        </form>

        <hr>
        <h2>テスト送信</h2>
        <form method="post">
            <?php wp_nonce_field('wpgp_test_send'); ?>
            <input type="email" name="wpgp_test_to" class="regular-text" placeholder="送信先メールアドレス">
            <?php submit_button('テストメールを送信', 'secondary', 'wpgp_test_send', false); ?>
        </form>
        <p class="description">注意: Gmail 側の送信制限やセキュリティ設定により送信できない場合があります。2段階認証 + アプリパスワードの利用を推奨します。</p>

        <hr>
        <h2>ログビューア</h2>
        <?php
        $log_path = wpgp_get_log_file_path();
        $log_exists = $log_path && file_exists($log_path);
        ?>
        <p>ログファイル: <code><?php echo esc_html($log_path ? $log_path : '未作成'); ?></code></p>
        <?php if ($log_exists): ?>
            <pre style="max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;white-space:pre-wrap;">
<?php echo esc_html(wpgp_tail_file($log_path, 200)); ?>
            </pre>
            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpgp_download_log'), 'wpgp_download_log')); ?>">ログをダウンロード</a>
                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpgp_clear_log'), 'wpgp_clear_log')); ?>" onclick="return confirm('ログを削除します。よろしいですか？');">ログをクリア</a>
            </p>
        <?php else: ?>
            <p>ログがまだありません。</p>
        <?php endif; ?>
    </div>
    <?php
}

// wp_mail を完全に置き換え: pre_wp_mail でショートサーキット
add_filter('pre_wp_mail', 'wpgp_pre_wp_mail', 10, 2);

function wpgp_pre_wp_mail($null, $atts) {
    $opts = wpgp_get_options();

    if (empty($opts['gmail_user']) || empty($opts['gmail_pass'])) {
        wpgp_log('error', '設定未完了のため送信できません', array());
        wpgp_maybe_admin_notice('設定が未完了です。Gmail アドレスとアプリパスワードを設定してください。');
        return new WP_Error('wpgp_not_configured', 'WP Gmail Mailer: 設定が未完了です。Gmail アドレスとアプリ パスワードを設定してください。');
    }

    // 互換のためフィルターを適用
    $from_email = $opts['from_email'] ?: $opts['gmail_user'];
    $from_email = apply_filters('wp_mail_from', $from_email);
    $from_name  = apply_filters('wp_mail_from_name', $opts['from_name']);

    $content_type = 'text/plain';
    $charset = get_bloginfo('charset');

    // $atts 正規化
    $to          = isset($atts['to']) ? $atts['to'] : '';
    $subject     = isset($atts['subject']) ? (string)$atts['subject'] : '';
    $message     = isset($atts['message']) ? (string)$atts['message'] : '';
    $headers     = isset($atts['headers']) ? $atts['headers'] : array();
    $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();

    $cc  = array();
    $bcc = array();
    $reply_to = array();

    // ヘッダー解析（代表的なもの）
    if (!empty($headers)) {
        if (is_string($headers)) {
            $headers = preg_split('/\r\n|\r|\n/', $headers);
        }
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (!is_string($header) || strpos($header, ':') === false) continue;
                list($name, $value) = array_map('trim', explode(':', $header, 2));
                $lname = strtolower($name);
                if ('content-type' === $lname) {
                    // 例: text/html; charset=UTF-8
                    $parts = array_map('trim', explode(';', $value));
                    if (!empty($parts[0])) {
                        $content_type = $parts[0];
                    }
                    foreach ($parts as $p) {
                        if (stripos($p, 'charset=') === 0) {
                            $charset = trim(substr($p, 8));
                        }
                    }
                } elseif ('cc' === $lname) {
                    $cc = array_merge($cc, wpgp_parse_address_list($value));
                } elseif ('bcc' === $lname) {
                    $bcc = array_merge($bcc, wpgp_parse_address_list($value));
                } elseif ('reply-to' === $lname) {
                    $reply_to = array_merge($reply_to, wpgp_parse_address_list($value));
                } elseif ('from' === $lname) {
                    // セキュリティのため From は設定で強制。ヘッダーの From は無視し、Reply-To で代替。
                    $reply_to = array_merge($reply_to, wpgp_parse_address_list($value));
                }
            }
        }
    }

    // 添付ファイル正規化
    if (is_string($attachments) && $attachments !== '') {
        $attachments = preg_split('/\r\n|\r|\n/', $attachments);
    }
    if (!is_array($attachments)) {
        $attachments = array();
    }

    // 送信実行
    try {
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            // WordPress 同梱の PHPMailer をロード（通常は不要）
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }

        $phpmailer = new PHPMailer\\PHPMailer\\PHPMailer(true);

        // SMTP 設定（Gmail）
        $phpmailer->isSMTP();
        $phpmailer->Host       = 'smtp.gmail.com';
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = $opts['gmail_user'];
        $phpmailer->Password   = $opts['gmail_pass'];
        $enc = $opts['encryption'] === 'ssl' ? PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS;
        $phpmailer->SMTPSecure = $enc;
        $phpmailer->Port       = intval($opts['port']) > 0 ? intval($opts['port']) : ($opts['encryption'] === 'ssl' ? 465 : 587);
        $phpmailer->CharSet    = $charset;

        // From（Gmail アカウントに揃える）
        $phpmailer->setFrom($from_email, $from_name, false);
        // 送信者（エンベロープ）
        $phpmailer->Sender = $opts['gmail_user'];

        // 宛先
        foreach (wpgp_parse_address_mixed($to) as $addr) {
            if (!empty($addr['email'])) {
                $phpmailer->addAddress($addr['email'], $addr['name']);
            }
        }
        foreach ($cc as $addr) {
            if (!empty($addr['email'])) $phpmailer->addCC($addr['email'], $addr['name']);
        }
        foreach ($bcc as $addr) {
            if (!empty($addr['email'])) $phpmailer->addBCC($addr['email'], $addr['name']);
        }
        foreach ($reply_to as $addr) {
            if (!empty($addr['email'])) $phpmailer->addReplyTo($addr['email'], $addr['name']);
        }

        // コンテンツ
        $content_type = apply_filters('wp_mail_content_type', $content_type); // 互換
        if (stripos($content_type, 'text/html') !== false) {
            $phpmailer->isHTML(true);
            $phpmailer->Body    = $message;
            $phpmailer->AltBody = wp_strip_all_tags($message);
        } else {
            $phpmailer->isHTML(false);
            $phpmailer->Body = $message;
        }

        $phpmailer->Subject = $subject;

        // 添付
        foreach ($attachments as $file) {
            $file = trim($file);
            if ($file !== '' && file_exists($file)) {
                try {
                    $phpmailer->addAttachment($file);
                } catch (Exception $e) {
                    // 無視し、他の添付は継続
                }
            }
        }

        // 送信
        $sent = $phpmailer->send();
        if (!$sent) {
            wpgp_log('error', 'メール送信に失敗', array(
                'to' => $to,
                'subject' => $subject,
            ));
            wpgp_maybe_admin_notice('メール送信に失敗しました: ' . $subject);
            return new WP_Error('wpgp_send_failed', 'メール送信に失敗しました。');
        }
        wpgp_log('info', 'メール送信に成功', array(
            'to' => $to,
            'subject' => $subject,
        ));
        return true; // pre_wp_mail では true でショートサーキット
    } catch (Exception $e) {
        wpgp_log('error', '送信エラー: ' . $e->getMessage(), array(
            'to' => $to,
            'subject' => $subject,
        ));
        wpgp_maybe_admin_notice('送信エラー: ' . $e->getMessage());
        return new WP_Error('wpgp_exception', '送信エラー: ' . $e->getMessage());
    } catch (\Throwable $t) {
        wpgp_log('error', '送信エラー: ' . $t->getMessage(), array(
            'to' => $to,
            'subject' => $subject,
        ));
        wpgp_maybe_admin_notice('送信エラー: ' . $t->getMessage());
        return new WP_Error('wpgp_throwable', '送信エラー: ' . $t->getMessage());
    }
}

// アドレス文字列を配列にパース（"Name <email>", email,...）
function wpgp_parse_address_list($value) {
    $out = array();
    $parts = preg_split('/,/', $value);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $out[] = wpgp_parse_single_address($p);
    }
    return $out;
}

function wpgp_parse_address_mixed($to) {
    $out = array();
    if (is_array($to)) {
        foreach ($to as $item) {
            if (is_array($item)) {
                $email = isset($item['email']) ? sanitize_email($item['email']) : '';
                $name  = isset($item['name']) ? sanitize_text_field($item['name']) : '';
                if ($email) $out[] = array('email' => $email, 'name' => $name);
            } elseif (is_string($item)) {
                $out[] = wpgp_parse_single_address($item);
            }
        }
    } elseif (is_string($to)) {
        $lines = preg_split('/\r\n|\r|\n|,/', $to);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') $out[] = wpgp_parse_single_address($line);
        }
    }
    return $out;
}

function wpgp_parse_single_address($str) {
    $name = '';
    $email = '';
    if (preg_match('/^(.+)<([^>]+)>$/', $str, $m)) {
        $name = trim($m[1], " \"'");
        $email = sanitize_email(trim($m[2]));
    } else {
        $email = sanitize_email(trim($str));
    }
    return array('email' => $email, 'name' => $name);
}

// ===== ログユーティリティ =====
function wpgp_get_log_file_path() {
    $opts = wpgp_get_options();
    if (empty($opts['logging_enabled'])) return '';
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) return '';
    $dir = trailingslashit($uploads['basedir']) . 'wpgp-logs';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    $file = trailingslashit($dir) . 'wpgp-mail.log';

    // 保持期限とサイズによるローテーション
    $retain_days = max(0, intval($opts['log_retain_days']));
    $max_size = max(32, intval($opts['log_max_size_kb'])) * 1024;
    if (file_exists($file)) {
        // 期限切れなら削除
        if ($retain_days > 0 && (time() - filemtime($file)) > ($retain_days * DAY_IN_SECONDS)) {
            @unlink($file);
        } elseif (filesize($file) > $max_size) {
            // ローテーション（.1 に移動、既存 .1 は上書き）
            @rename($file, $file . '.1');
        }
    }
    return $file;
}

function wpgp_log($level, $message, $context = array()) {
    $opts = wpgp_get_options();
    if (empty($opts['logging_enabled'])) return;
    $file = wpgp_get_log_file_path();
    if (!$file) return;
    $date = gmdate('Y-m-d H:i:s');
    $line = sprintf('[%s] %s: %s', $date, strtoupper($level), $message);
    if (!empty($context)) {
        // 個人情報に注意しつつ、コンテキストを JSON 化
        $safe = $context;
        if (isset($safe['to'])) {
            if (is_array($safe['to'])) {
                $safe['to'] = implode(',', array_map(function($x){ return is_array($x)?($x['email']??''): (string)$x; }, $safe['to']));
            } else {
                $safe['to'] = (string)$safe['to'];
            }
        }
        if (isset($safe['subject'])) {
            $safe['subject'] = (string)$safe['subject'];
        }
        $line .= ' | ' . wp_json_encode($safe);
    }
    $line .= "\n";
    // 追記
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function wpgp_tail_file($file, $lines = 200) {
    if (!file_exists($file)) return '';
    $f = @fopen($file, 'r');
    if (!$f) return '';
    $buffer = '';
    $pos = -1;
    $lineCount = 0;
    $stat = fstat($f);
    $filesize = $stat['size'];
    while ($lineCount < $lines && -$pos <= $filesize) {
        fseek($f, $pos, SEEK_END);
        $char = fgetc($f);
        if ($char === "\n") {
            $lineCount++;
            if ($lineCount > 1) $buffer = $char . $buffer; // keep newlines between lines
        } else {
            $buffer = $char . $buffer;
        }
        $pos--;
    }
    fclose($f);
    return trim($buffer);
}

// 管理画面通知
function wpgp_maybe_admin_notice($message) {
    $opts = wpgp_get_options();
    if (empty($opts['notify_admin'])) return;
    set_transient(WPGP_TRANSIENT_ERROR_NOTICE, (string)$message, HOUR_IN_SECONDS);
}

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    $msg = get_transient(WPGP_TRANSIENT_ERROR_NOTICE);
    if (!$msg) return;
    echo '<div class="notice notice-error is-dismissible">'
        . '<p><strong>WP Gmail Mailer:</strong> ' . esc_html($msg) . ' '
        . '<a href="' . esc_url(admin_url('options-general.php?page=wpgp-settings')) . '">設定/ログを確認</a></p>'
        . '</div>';
});

// ログのダウンロード/クリア
add_action('admin_post_wpgp_download_log', function(){
    if (!current_user_can('manage_options')) wp_die('forbidden');
    check_admin_referer('wpgp_download_log');
    $path = wpgp_get_log_file_path();
    if (!$path || !file_exists($path)) wp_die('ログが見つかりません');
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="wpgp-mail.log"');
    readfile($path);
    exit;
});

add_action('admin_post_wpgp_clear_log', function(){
    if (!current_user_can('manage_options')) wp_die('forbidden');
    check_admin_referer('wpgp_clear_log');
    $path = wpgp_get_log_file_path();
    if ($path && file_exists($path)) @unlink($path);
    wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('options-general.php?page=wpgp-settings'));
    exit;
});

?>

