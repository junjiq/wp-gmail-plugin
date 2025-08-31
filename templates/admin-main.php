<?php
/**
 * Admin Main Template
 *
 * Gmail プラグインのメイン管理画面
 */

if (!defined('ABSPATH')) {
    exit;
}

$oauth = new Gmail_OAuth();
$auth_status = $oauth->check_auth_status();
$email_manager = new Gmail_Email_Manager();

// 統計情報を取得
$stats = array(
    'total_emails' => 0,
    'unread_emails' => 0,
    'starred_emails' => 0
);

if ($auth_status['authenticated']) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gmail_plugin_emails';

    $stats['total_emails'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $stats['unread_emails'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_read = 0");
    $stats['starred_emails'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_starred = 1");
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (!wp_gmail_is_configured()): ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    __('Gmail APIの設定が完了していません。<a href="%s">設定ページ</a>でAPI認証情報を設定してください。', 'wp-gmail-plugin'),
                    admin_url('admin.php?page=wp-gmail-plugin-settings')
                );
                ?>
            </p>
        </div>
    <?php elseif (!$auth_status['authenticated']): ?>
        <div class="notice notice-info">
            <p>
                <?php
                printf(
                    __('Gmailとの認証が完了していません。<a href="%s">設定ページ</a>で認証を完了してください。', 'wp-gmail-plugin'),
                    admin_url('admin.php?page=wp-gmail-plugin-settings')
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="gmail-plugin-dashboard">
        <!-- 統計情報 -->
        <div class="gmail-stats-row">
            <div class="gmail-stat-box">
                <div class="stat-icon">
                    <span class="dashicons dashicons-email-alt"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['total_emails']); ?></div>
                    <div class="stat-label"><?php _e('Total Emails', 'wp-gmail-plugin'); ?></div>
                </div>
            </div>

            <div class="gmail-stat-box">
                <div class="stat-icon unread">
                    <span class="dashicons dashicons-marker"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['unread_emails']); ?></div>
                    <div class="stat-label"><?php _e('Unread Emails', 'wp-gmail-plugin'); ?></div>
                </div>
            </div>

            <div class="gmail-stat-box">
                <div class="stat-icon starred">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($stats['starred_emails']); ?></div>
                    <div class="stat-label"><?php _e('Starred Emails', 'wp-gmail-plugin'); ?></div>
                </div>
            </div>
        </div>

        <!-- アクションボタン -->
        <div class="gmail-actions">
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Quick Actions', 'wp-gmail-plugin'); ?></span></h2>
                <div class="inside">
                    <div class="gmail-action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=wp-gmail-plugin-compose'); ?>"
                           class="button button-primary button-large">
                            <span class="dashicons dashicons-edit"></span>
                            <?php _e('Compose Email', 'wp-gmail-plugin'); ?>
                        </a>

                        <a href="<?php echo admin_url('admin.php?page=wp-gmail-plugin-inbox'); ?>"
                           class="button button-secondary button-large">
                            <span class="dashicons dashicons-inbox"></span>
                            <?php _e('View Inbox', 'wp-gmail-plugin'); ?>
                        </a>

                        <?php if ($auth_status['authenticated']): ?>
                            <button type="button" id="sync-emails" class="button button-secondary button-large">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Sync Emails', 'wp-gmail-plugin'); ?>
                            </button>
                        <?php endif; ?>

                        <a href="<?php echo admin_url('admin.php?page=wp-gmail-plugin-settings'); ?>"
                           class="button button-secondary button-large">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Settings', 'wp-gmail-plugin'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 認証状態 -->
        <?php if ($auth_status['authenticated']): ?>
            <div class="gmail-auth-info">
                <div class="postbox">
                    <h2 class="hndle"><span><?php _e('Account Information', 'wp-gmail-plugin'); ?></span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Email Address', 'wp-gmail-plugin'); ?></th>
                                <td><?php echo wp_gmail_esc_html($auth_status['email']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Total Messages', 'wp-gmail-plugin'); ?></th>
                                <td><?php echo number_format($auth_status['messages_total']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Total Threads', 'wp-gmail-plugin'); ?></th>
                                <td><?php echo number_format($auth_status['threads_total']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Authentication Status', 'wp-gmail-plugin'); ?></th>
                                <td><span class="status-authenticated"><?php _e('Connected', 'wp-gmail-plugin'); ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 最近のメール -->
        <?php if ($auth_status['authenticated'] && $stats['total_emails'] > 0): ?>
            <div class="gmail-recent-emails">
                <div class="postbox">
                    <h2 class="hndle">
                        <span><?php _e('Recent Emails', 'wp-gmail-plugin'); ?></span>
                        <a href="<?php echo admin_url('admin.php?page=wp-gmail-plugin-inbox'); ?>"
                           class="page-title-action"><?php _e('View All', 'wp-gmail-plugin'); ?></a>
                    </h2>
                    <div class="inside">
                        <div id="recent-emails-list">
                            <p><?php _e('Loading recent emails...', 'wp-gmail-plugin'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ショートコード情報 -->
        <div class="gmail-shortcodes">
            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Available Shortcodes', 'wp-gmail-plugin'); ?></span></h2>
                <div class="inside">
                    <div class="shortcode-info">
                        <h4><?php _e('Compose Email Form', 'wp-gmail-plugin'); ?></h4>
                        <code>[gmail_compose]</code>
                        <p class="description">
                            <?php _e('Displays an email composition form on the frontend.', 'wp-gmail-plugin'); ?>
                        </p>

                        <h4><?php _e('Email Inbox', 'wp-gmail-plugin'); ?></h4>
                        <code>[gmail_inbox limit="10"]</code>
                        <p class="description">
                            <?php _e('Displays a list of emails from the inbox on the frontend.', 'wp-gmail-plugin'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gmail-plugin-dashboard {
    max-width: 1200px;
}

.gmail-stats-row {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.gmail-stat-box {
    flex: 1;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 5px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #0073aa;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-icon.unread {
    background: #dc3232;
}

.stat-icon.starred {
    background: #ffb900;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    line-height: 1;
    color: #23282d;
}

.stat-label {
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
}

.gmail-action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.gmail-action-buttons .button-large {
    padding: 12px 24px;
    height: auto;
    line-height: 1.2;
}

.gmail-action-buttons .button .dashicons {
    margin-right: 8px;
    vertical-align: middle;
}

.status-authenticated {
    color: #00a32a;
    font-weight: bold;
}

.shortcode-info h4 {
    margin: 15px 0 5px 0;
}

.shortcode-info h4:first-child {
    margin-top: 0;
}

.shortcode-info code {
    background: #f1f1f1;
    padding: 8px 12px;
    border-radius: 3px;
    display: inline-block;
    margin: 5px 0;
    font-size: 14px;
}

#recent-emails-list {
    min-height: 100px;
}

.email-item {
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.email-item:last-child {
    border-bottom: none;
}

.email-subject {
    font-weight: bold;
    margin-bottom: 4px;
}

.email-from {
    color: #646970;
    font-size: 13px;
    margin-bottom: 4px;
}

.email-date {
    color: #646970;
    font-size: 12px;
}

@media (max-width: 782px) {
    .gmail-stats-row {
        flex-direction: column;
    }

    .gmail-action-buttons {
        flex-direction: column;
    }

    .gmail-action-buttons .button {
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // 同期ボタンのクリック処理
    $('#sync-emails').on('click', function() {
        var $button = $(this);
        var originalText = $button.html();

        $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> <?php _e("Syncing...", "wp-gmail-plugin"); ?>');

        $.post(ajaxurl, {
            action: 'gmail_get_emails',
            nonce: '<?php echo wp_create_nonce("wp_gmail_plugin_admin_nonce"); ?>',
            max_results: 20
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('<?php _e("Sync failed: ", "wp-gmail-plugin"); ?>' + response.data.error);
            }
        }).always(function() {
            $button.prop('disabled', false).html(originalText);
        });
    });

    // 最近のメールを読み込み
    <?php if ($auth_status['authenticated'] && $stats['total_emails'] > 0): ?>
    $.post(ajaxurl, {
        action: 'gmail_get_emails',
        nonce: '<?php echo wp_create_nonce("wp_gmail_plugin_admin_nonce"); ?>',
        source: 'database',
        limit: 5
    }, function(response) {
        if (response.success && response.data.emails) {
            var html = '';
            $.each(response.data.emails, function(i, email) {
                html += '<div class="email-item">';
                html += '<div class="email-subject">' + escapeHtml(email.subject) + '</div>';
                html += '<div class="email-from">' + escapeHtml(email.from_name || email.from_email) + '</div>';
                html += '<div class="email-date">' + email.received_date + '</div>';
                html += '</div>';
            });
            $('#recent-emails-list').html(html);
        } else {
            $('#recent-emails-list').html('<p><?php _e("No recent emails found.", "wp-gmail-plugin"); ?></p>');
        }
    });
    <?php endif; ?>

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
