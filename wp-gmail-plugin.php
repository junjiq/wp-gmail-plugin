<?php
/*
Plugin Name: WP Gmail Mailer (SMTP Replacement)
Description: Gmail アカウントのSMTPで WordPress の wp_mail を完全置換（受信なし / Gmail API 不使用）。
Version: 1.2.0
Author: Your Name
Text Domain: wp-gmail-mailer
Domain Path: /languages
Requires at least: 5.8
Requires PHP: 7.2
*/

if (!defined('ABSPATH')) exit;

final class WPGP_Plugin {
    const OPTIONS_KEY = 'wpgp_options';
    const TRANSIENT_ERROR_NOTICE = 'wpgp_last_error_notice';

    private static $instance = null;
    public static function instance() { return self::$instance ?: (self::$instance = new self()); }

    public function hooks() {
        add_filter('pre_wp_mail', [$this, 'pre_wp_mail'], 10, 2);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('admin_post_wpgp_download_log', [$this, 'download_log']);
        add_action('admin_post_wpgp_clear_log', [$this, 'clear_log']);
        add_action('init', [$this, 'load_textdomain']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('wp-gmail-mailer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    // ===== Options =====
    private function defaults() {
        return [
            'gmail_user'      => '',
            'gmail_pass'      => '',
            'from_email'      => '',
            'from_name'       => get_bloginfo('name'),
            'encryption'      => 'tls',
            'port'            => 0,
            'logging_enabled' => true,
            'log_retain_days' => 14,
            'log_max_size_kb' => 1024,
            'notify_admin'    => true,
        ];
    }
    private function opts() {
        $o = get_option(self::OPTIONS_KEY, []);
        if (!is_array($o)) $o = [];
        return array_merge($this->defaults(), $o);
    }

    // ===== Admin =====
    public function admin_menu() {
        add_options_page(__('WP Gmail Mailer', 'wp-gmail-mailer'), __('WP Gmail Mailer', 'wp-gmail-mailer'), 'manage_options', 'wpgp-settings', [$this, 'render_settings']);
    }
    public function admin_init() {
        register_setting('wpgp_settings_group', self::OPTIONS_KEY, ['sanitize_callback'=>[$this,'sanitize']]);
    }
    public function sanitize($in) {
        $o = $this->opts(); $out = [];
        $out['gmail_user'] = isset($in['gmail_user']) ? sanitize_email($in['gmail_user']) : $o['gmail_user'];
        $out['gmail_pass'] = (isset($in['gmail_pass']) && $in['gmail_pass']!=='') ? (string)$in['gmail_pass'] : $o['gmail_pass'];
        $out['from_email'] = isset($in['from_email']) ? sanitize_email($in['from_email']) : $o['from_email'];
        $out['from_name']  = isset($in['from_name'])  ? sanitize_text_field($in['from_name']) : $o['from_name'];
        $enc = isset($in['encryption']) ? strtolower(sanitize_text_field($in['encryption'])) : $o['encryption'];
        $out['encryption'] = in_array($enc, ['tls','ssl'], true) ? $enc : 'tls';
        $p = isset($in['port']) ? intval($in['port']) : 0; $out['port'] = ($p>0&&$p<65536)?$p:0;
        $out['logging_enabled'] = !empty($in['logging_enabled']);
        $rd = isset($in['log_retain_days']) ? intval($in['log_retain_days']) : $o['log_retain_days'];
        $out['log_retain_days'] = ($rd>=0&&$rd<=365)?$rd:14;
        $mk = isset($in['log_max_size_kb']) ? intval($in['log_max_size_kb']) : $o['log_max_size_kb'];
        $out['log_max_size_kb'] = ($mk>=32&&$mk<=10240)?$mk:1024;
        $out['notify_admin'] = !empty($in['notify_admin']);

        // 接続検証（対象キーが変わった場合のみ）
        $keys = ['gmail_user','gmail_pass','encryption','port'];
        $changed = false; foreach ($keys as $k) { if ((string)$out[$k] !== (string)$o[$k]) { $changed = true; break; } }
        if ($changed && $out['gmail_user'] && $out['gmail_pass']) {
            $err = '';
            if (!$this->validate_smtp($out, $err)) {
                add_settings_error('wpgp_settings_group', 'wpgp_validate_error', sprintf(__('Gmail 接続検証に失敗しました: %s', 'wp-gmail-mailer'), $err), 'error');
                set_transient('wpgp_reset_fields', 1, MINUTE_IN_SECONDS * 5);
                // 変更破棄
                return $o;
            }
        }
        return $out;
    }
    public function render_settings() {
        if (!current_user_can('manage_options')) return; $o = $this->opts();
        $test = '';
        if (isset($_POST['wpgp_test_send']) && check_admin_referer('wpgp_test_send')) {
            $to = isset($_POST['wpgp_test_to']) ? sanitize_email(wp_unslash($_POST['wpgp_test_to'])) : '';
            if ($to) {
                $ok = wp_mail($to, __('WP Gmail Mailer テスト送信', 'wp-gmail-mailer'), __('このメールは WP Gmail Mailer からのテストです。', 'wp-gmail-mailer'));
                if (is_wp_error($ok)) $test = '<div class="notice notice-error"><p>'.sprintf(__('送信エラー: %s', 'wp-gmail-mailer'), esc_html($ok->get_error_message())).'</p></div>';
                elseif ($ok) $test = '<div class="notice notice-success"><p>'.esc_html__('テストメールを送信しました。', 'wp-gmail-mailer').'</p></div>';
                else $test = '<div class="notice notice-error"><p>'.esc_html__('送信に失敗しました。', 'wp-gmail-mailer').'</p></div>';
            } else {
                $test = '<div class="notice notice-warning"><p>'.esc_html__('テスト送信先のメールアドレスを入力してください。', 'wp-gmail-mailer').'</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WP Gmail Mailer 設定', 'wp-gmail-mailer'); ?></h1>
            <p><?php echo esc_html__('WordPress のメール送信を Gmail SMTP によって置き換えます。Gmail API は使用しません。', 'wp-gmail-mailer'); ?></p>
            <?php settings_errors('wpgp_settings_group'); ?>
            <?php if (get_transient('wpgp_reset_fields')): delete_transient('wpgp_reset_fields'); $o['gmail_user']=''; $o['from_email']=''; ?>
                <div class="notice notice-warning"><p><?php echo esc_html__('認証に失敗したため、入力をリセットしました。正しい情報を再入力してください。', 'wp-gmail-mailer'); ?></p></div>
            <?php endif; ?>
            <?php echo $test; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <form method="post" action="options.php">
                <?php settings_fields('wpgp_settings_group'); $o=$this->opts(); ?>
                <table class="form-table" role="presentation">
                    <tr><th><label for="wpgp_gmail_user"><?php echo esc_html__('Gmail アドレス', 'wp-gmail-mailer'); ?></label></th>
                        <td><input type="email" id="wpgp_gmail_user" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[gmail_user]" value="<?php echo esc_attr($o['gmail_user']); ?>" class="regular-text" required>
                        <p class="description"><?php echo esc_html__('2段階認証 + アプリパスワードの使用を推奨', 'wp-gmail-mailer'); ?></p></td></tr>
                    <tr><th><label for="wpgp_gmail_pass"><?php echo esc_html__('アプリ パスワード', 'wp-gmail-mailer'); ?></label></th>
                        <td><input type="password" id="wpgp_gmail_pass" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[gmail_pass]" value="" class="regular-text" placeholder="変更しない場合は空" autocomplete="new-password">
                        <?php if (!empty($o['gmail_pass'])): ?><p class="description"><?php echo esc_html__('保存済みのパスワードがあります。', 'wp-gmail-mailer'); ?></p><?php endif; ?></td></tr>
                    <tr><th><label for="wpgp_from_email"><?php echo esc_html__('From アドレス', 'wp-gmail-mailer'); ?></label></th>
                        <td><input type="email" id="wpgp_from_email" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[from_email]" value="<?php echo esc_attr($o['from_email']); ?>" class="regular-text" placeholder="省略時は Gmail アドレス"></td></tr>
                    <tr><th><label for="wpgp_from_name"><?php echo esc_html__('From 名称', 'wp-gmail-mailer'); ?></label></th>
                        <td><input type="text" id="wpgp_from_name" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[from_name]" value="<?php echo esc_attr($o['from_name']); ?>" class="regular-text"></td></tr>
                    <tr><th><?php echo esc_html__('暗号化方式', 'wp-gmail-mailer'); ?></th>
                        <td><label><input type="radio" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[encryption]" value="tls" <?php checked($o['encryption'],'tls'); ?>> TLS</label>
                            &nbsp; <label><input type="radio" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[encryption]" value="ssl" <?php checked($o['encryption'],'ssl'); ?>> SSL</label></td></tr>
                    <tr><th><label for="wpgp_port"><?php echo esc_html__('ポート', 'wp-gmail-mailer'); ?></label></th>
                        <td><input type="number" id="wpgp_port" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[port]" value="<?php echo esc_attr((string)($o['port']?:'')); ?>" class="small-text" placeholder="自動">
                        <p class="description"><?php echo esc_html__('未指定: TLS 587 / SSL 465', 'wp-gmail-mailer'); ?></p></td></tr>
                    <tr><th><?php echo esc_html__('ログ記録', 'wp-gmail-mailer'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[logging_enabled]" value="1" <?php checked($o['logging_enabled'],true); ?>> <?php echo esc_html__('有効', 'wp-gmail-mailer'); ?></label>
                        <p class="description"><?php echo esc_html__('送信結果やエラーを uploads/wpgp-logs に記録', 'wp-gmail-mailer'); ?></p></td></tr>
                    <tr><th><label for="wpgp_log_retain_days"><?php echo esc_html__('ログ保持日数', 'wp-gmail-mailer'); ?></label></th>
                        <td><input type="number" id="wpgp_log_retain_days" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[log_retain_days]" value="<?php echo esc_attr((string)$o['log_retain_days']); ?>" class="small-text" min="0" max="365"> 日</td></tr>
                    <tr><th><label for="wpgp_log_max_size_kb"><?php echo esc_html__('ログ最大サイズ', 'wp-gmail-mailer'); ?></label></th>
                        <td><input type="number" id="wpgp_log_max_size_kb" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[log_max_size_kb]" value="<?php echo esc_attr((string)$o['log_max_size_kb']); ?>" class="small-text" min="32" max="10240"> KB</td></tr>
                    <tr><th><?php echo esc_html__('エラー通知', 'wp-gmail-mailer'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTIONS_KEY); ?>[notify_admin]" value="1" <?php checked($o['notify_admin'],true); ?>> <?php echo esc_html__('管理画面に通知', 'wp-gmail-mailer'); ?></label></td></tr>
                </table>
                <?php submit_button(__('変更を保存', 'wp-gmail-mailer')); ?>
            </form>
            <hr>
            <h2><?php echo esc_html__('テスト送信', 'wp-gmail-mailer'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('wpgp_test_send'); ?>
                <input type="email" name="wpgp_test_to" class="regular-text" placeholder="<?php echo esc_attr__('送信先メールアドレス', 'wp-gmail-mailer'); ?>">
                <?php submit_button(__('テストメールを送信', 'wp-gmail-mailer'), 'secondary', 'wpgp_test_send', false); ?>
            </form>
            <p class="description"><?php echo esc_html__('Gmail の送信制限やセキュリティ設定によりブロックされる場合があります。', 'wp-gmail-mailer'); ?></p>
            <hr>
            <h2><?php echo esc_html__('ログビューア', 'wp-gmail-mailer'); ?></h2>
            <?php $path=$this->log_path(); $exists=$path && file_exists($path); ?>
            <p><?php echo esc_html__('ログファイル', 'wp-gmail-mailer'); ?>: <code><?php echo esc_html($path?:__('未作成','wp-gmail-mailer')); ?></code></p>
            <?php if ($exists): ?>
<pre style="max-height:300px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:10px;white-space:pre-wrap;"><?php echo esc_html($this->tail($path,200)); ?></pre>
            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpgp_download_log'),'wpgp_download_log')); ?>"><?php echo esc_html__('ログをダウンロード', 'wp-gmail-mailer'); ?></a>
                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpgp_clear_log'),'wpgp_clear_log')); ?>" onclick="return confirm('<?php echo esc_js(__('ログを削除します。よろしいですか？','wp-gmail-mailer')); ?>');"><?php echo esc_html__('ログをクリア', 'wp-gmail-mailer'); ?></a>
            </p>
            <?php else: ?><p><?php echo esc_html__('ログがまだありません。', 'wp-gmail-mailer'); ?></p><?php endif; ?>
        </div>
        <?php
    }

    // ===== Send (replace wp_mail) =====
    public function pre_wp_mail($null, $atts) {
        $o = $this->opts();
        if (empty($o['gmail_user']) || empty($o['gmail_pass'])) {
            $this->log('error',__('設定未完了のため送信不可', 'wp-gmail-mailer'));
            $this->notice(__('設定が未完了です。Gmail アドレスとアプリパスワードを設定してください。', 'wp-gmail-mailer'));
            return new WP_Error('wpgp_not_configured',__('設定が未完了です。', 'wp-gmail-mailer'));
        }
        $from_email = apply_filters('wp_mail_from', $o['from_email'] ?: $o['gmail_user']);
        $from_name  = apply_filters('wp_mail_from_name', $o['from_name']);
        $content_type = 'text/plain'; $charset = get_bloginfo('charset');
        $to = $atts['to'] ?? ''; $subject=(string)($atts['subject'] ?? ''); $message=(string)($atts['message'] ?? '');
        $headers = $atts['headers'] ?? []; $attachments = $atts['attachments'] ?? [];
        $cc=[]; $bcc=[]; $reply_to=[];
        if ($headers) {
            if (is_string($headers)) $headers = preg_split('/\r\n|\r|\n/', $headers);
            if (is_array($headers)) foreach ($headers as $h) {
                if (!is_string($h) || strpos($h,':')===false) continue;
                list($n,$v)=array_map('trim',explode(':',$h,2)); $ln=strtolower($n);
                if ($ln==='content-type') {
                    $parts=array_map('trim',explode(';',$v)); if (!empty($parts[0])) $content_type=$parts[0];
                    foreach($parts as $p) if (stripos($p,'charset=')===0) $charset=trim(substr($p,8));
                } elseif ($ln==='cc') { $cc=array_merge($cc,$this->parse_list($v)); }
                elseif ($ln==='bcc') { $bcc=array_merge($bcc,$this->parse_list($v)); }
                elseif ($ln==='reply-to') { $reply_to=array_merge($reply_to,$this->parse_list($v)); }
                elseif ($ln==='from') { $reply_to=array_merge($reply_to,$this->parse_list($v)); }
            }
        }
        if (is_string($attachments)&&$attachments!=='') $attachments=preg_split('/\r\n|\r|\n/',$attachments);
        if (!is_array($attachments)) $attachments=[];

        try {
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                require_once ABSPATH.WPINC.'/PHPMailer/PHPMailer.php';
                require_once ABSPATH.WPINC.'/PHPMailer/SMTP.php';
                require_once ABSPATH.WPINC.'/PHPMailer/Exception.php';
            }
            $m=new PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP(); $m->Host='smtp.gmail.com'; $m->SMTPAuth=true; $m->Username=$o['gmail_user']; $m->Password=$o['gmail_pass'];
            $m->SMTPSecure = ($o['encryption']==='ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $m->Port = ($o['port']>0)?intval($o['port']):(($o['encryption']==='ssl')?465:587); $m->CharSet=$charset;
            $m->setFrom($from_email,$from_name,false); $m->Sender=$o['gmail_user'];
            foreach ($this->parse_mixed($to) as $a) if (!empty($a['email'])) $m->addAddress($a['email'],$a['name']);
            foreach ($cc as $a)  if (!empty($a['email'])) $m->addCC($a['email'],$a['name']);
            foreach ($bcc as $a) if (!empty($a['email'])) $m->addBCC($a['email'],$a['name']);
            foreach ($reply_to as $a) if (!empty($a['email'])) $m->addReplyTo($a['email'],$a['name']);
            $content_type = apply_filters('wp_mail_content_type',$content_type);
            if (stripos($content_type,'text/html')!==false) { $m->isHTML(true); $m->Body=$message; $m->AltBody=wp_strip_all_tags($message); }
            else { $m->isHTML(false); $m->Body=$message; }
            $m->Subject=$subject;
            foreach ($attachments as $f) { $f=trim($f); if ($f!==''&&file_exists($f)) { try{$m->addAttachment($f);}catch(\Exception $e){} } }
            $sent=$m->send();
            if (!$sent) { $this->log('error',__('メール送信に失敗','wp-gmail-mailer'),['to'=>$to,'subject'=>$subject]); $this->notice(sprintf(__('メール送信に失敗しました: %s','wp-gmail-mailer'), $subject)); return new WP_Error('wpgp_send_failed',__('メール送信に失敗しました。','wp-gmail-mailer')); }
            $this->log('info',__('メール送信に成功','wp-gmail-mailer'),['to'=>$to,'subject'=>$subject]);
            return true;
        } catch (\Exception $e) {
            $this->log('error',sprintf(__('送信エラー: %s','wp-gmail-mailer'), $e->getMessage()),['to'=>$to,'subject'=>$subject]); $this->notice(sprintf(__('送信エラー: %s','wp-gmail-mailer'), $e->getMessage()));
            return new WP_Error('wpgp_exception',sprintf(__('送信エラー: %s','wp-gmail-mailer'), $e->getMessage()));
        } catch (\Throwable $t) {
            $this->log('error',sprintf(__('送信エラー: %s','wp-gmail-mailer'), $t->getMessage()),['to'=>$to,'subject'=>$subject]); $this->notice(sprintf(__('送信エラー: %s','wp-gmail-mailer'), $t->getMessage()));
            return new WP_Error('wpgp_throwable',sprintf(__('送信エラー: %s','wp-gmail-mailer'), $t->getMessage()));
        }
    }

    // ===== Helpers =====
    private function validate_smtp($opt, &$err='') {
        try {
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                require_once ABSPATH.WPINC.'/PHPMailer/PHPMailer.php';
                require_once ABSPATH.WPINC.'/PHPMailer/SMTP.php';
                require_once ABSPATH.WPINC.'/PHPMailer/Exception.php';
            }
            $m = new PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP();
            $m->Host = 'smtp.gmail.com';
            $m->SMTPAuth = true;
            $m->Username = $opt['gmail_user'];
            $m->Password = $opt['gmail_pass'];
            $m->SMTPSecure = ($opt['encryption']==='ssl') ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $m->Port = intval($opt['port'])>0 ? intval($opt['port']) : (($opt['encryption']==='ssl')?465:587);
            $m->Timeout = 10;
            $ok = $m->smtpConnect();
            if (!$ok) { $err = 'SMTPサーバーに接続できませんでした。'; return false; }
            $m->smtpClose();
            return true;
        } catch (\Throwable $t) {
            $err = $t->getMessage();
            return false;
        }
    }
    private function parse_list($v){ $out=[]; foreach(preg_split('/,/',(string)$v) as $p){ $p=trim($p); if($p!=='') $out[]=$this->parse_one($p);} return $out; }
    private function parse_mixed($to){ $out=[]; if(is_array($to)){ foreach($to as $it){ if(is_array($it)){ $e=sanitize_email($it['email']??''); $n=sanitize_text_field($it['name']??''); if($e) $out[]=['email'=>$e,'name'=>$n]; } elseif(is_string($it)){ $out[]=$this->parse_one($it);} } } elseif(is_string($to)){ foreach(preg_split('/\r\n|\r|\n|,/', $to) as $l){ $l=trim($l); if($l!=='') $out[]=$this->parse_one($l);} } return $out; }
    private function parse_one($s){ if(preg_match('/^(.+)<([^>]+)>$/',$s,$m)){ return ['email'=>sanitize_email(trim($m[2])),'name'=>trim($m[1]," \"'")]; } return ['email'=>sanitize_email(trim($s)),'name'=>'']; }

    // ===== Logging / Notices =====
    private function log_path(){ $o=$this->opts(); if(empty($o['logging_enabled'])) return ''; $u=wp_upload_dir(); if(!empty($u['error'])) return ''; $d=trailingslashit($u['basedir']).'wpgp-logs'; if(!file_exists($d)) wp_mkdir_p($d); $f=trailingslashit($d).'wpgp-mail.log'; $rd=max(0,intval($o['log_retain_days'])); $mx=max(32,intval($o['log_max_size_kb']))*1024; if(file_exists($f)){ if($rd>0 && (time()-filemtime($f))>($rd*DAY_IN_SECONDS)) @unlink($f); elseif(filesize($f)>$mx) @rename($f,$f.'.1'); } return $f; }
    private function log($level,$msg,$ctx=[]){ $o=$this->opts(); if(empty($o['logging_enabled'])) return; $f=$this->log_path(); if(!$f) return; $date=gmdate('Y-m-d H:i:s'); $line=sprintf('[%s] %s: %s',$date,strtoupper($level),$msg); if($ctx){ $s=$ctx; if(isset($s['to'])){ if(is_array($s['to'])) $s['to']=implode(',',array_map(function($x){return is_array($x)?($x['email']??''):(string)$x;},$s['to'])); else $s['to']=(string)$s['to']; } if(isset($s['subject'])) $s['subject']=(string)$s['subject']; $line.=' | '.wp_json_encode($s);} @file_put_contents($f,$line."\n",FILE_APPEND|LOCK_EX); }
    private function tail($file,$lines=200){ if(!file_exists($file)) return ''; $h=@fopen($file,'r'); if(!$h) return ''; $buf='';$pos=-1;$cnt=0;$st=fstat($h);$sz=$st['size']; while($cnt<$lines && -$pos<=$sz){ fseek($h,$pos,SEEK_END); $c=fgetc($h); if($c==="\n"){ $cnt++; if($cnt>1)$buf=$c.$buf; } else { $buf=$c.$buf; } $pos--; } fclose($h); return trim($buf);}    
    private function notice($msg){ $o=$this->opts(); if(empty($o['notify_admin'])) return; set_transient(self::TRANSIENT_ERROR_NOTICE,(string)$msg,HOUR_IN_SECONDS); }
    public function admin_notices(){ if(!current_user_can('manage_options')) return; $m=get_transient(self::TRANSIENT_ERROR_NOTICE); if(!$m) return; echo '<div class="notice notice-error is-dismissible"><p><strong>WP Gmail Mailer:</strong> '.esc_html($m).' <a href="'.esc_url(admin_url('options-general.php?page=wpgp-settings')).'">'.esc_html__('設定/ログを確認','wp-gmail-mailer').'</a></p></div>'; }
    public function download_log(){ if(!current_user_can('manage_options')) wp_die('forbidden'); check_admin_referer('wpgp_download_log'); $p=$this->log_path(); if(!$p||!file_exists($p)) wp_die('ログが見つかりません'); header('Content-Type: text/plain; charset=UTF-8'); header('Content-Disposition: attachment; filename="wpgp-mail.log"'); readfile($p); exit; }
    public function clear_log(){ if(!current_user_can('manage_options')) wp_die('forbidden'); check_admin_referer('wpgp_clear_log'); $p=$this->log_path(); if($p&&file_exists($p)) @unlink($p); wp_safe_redirect(wp_get_referer()?:admin_url('options-general.php?page=wpgp-settings')); exit; }
}

add_action('plugins_loaded', function(){ WPGP_Plugin::instance()->hooks(); });
?>
