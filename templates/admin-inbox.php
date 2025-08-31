<?php
/**
 * Admin Inbox Template
 *
 * Gmail プラグインの受信箱画面
 */

if (!defined('ABSPATH')) {
    exit;
}

// 認証チェック
if (!wp_gmail_check_configuration()) {
    return;
}

// ページネーション設定
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// 検索クエリ
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// メール管理インスタンス
$email_manager = new Gmail_Email_Manager();

// データベースからメールを取得
$result = $email_manager->get_emails_from_db($per_page, $offset, $search);
$emails = $result['emails'] ?? array();
$total_emails = $result['total_count'] ?? 0;
$total_pages = ceil($total_emails / $per_page);

// メールアクション処理
if (isset($_POST['email_action']) && isset($_POST['email_ids']) && wp_verify_nonce($_POST['wp_gmail_nonce'], 'wp_gmail_inbox')) {
    $action = sanitize_text_field($_POST['email_action']);
    $email_ids = array_map('sanitize_text_field', $_POST['email_ids']);

    $success_count = 0;
    foreach ($email_ids as $message_id) {
        $result = $email_manager->mark_email($message_id, $action);
        if ($result['success']) {
            $success_count++;
        }
    }

    if ($success_count > 0) {
        wp_gmail_show_success(
            sprintf(_n('%d email updated successfully.', '%d emails updated successfully.', $success_count, 'wp-gmail-plugin'), $success_count)
        );
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="gmail-inbox-container">
        <!-- 検索とフィルター -->
        <div class="gmail-inbox-header">
            <div class="gmail-search-form">
                <form method="get" action="">
                    <input type="hidden" name="page" value="wp-gmail-plugin-inbox">
                    <input type="search" name="search"
                           value="<?php echo wp_gmail_esc_attr($search); ?>"
                           placeholder="<?php _e('Search emails...', 'wp-gmail-plugin'); ?>"
                           class="search-input">
                    <input type="submit" value="<?php _e('Search', 'wp-gmail-plugin'); ?>" class="button">

                    <?php if ($search): ?>
                        <a href="<?php echo admin_url('admin.php?page=wp-gmail-plugin-inbox'); ?>"
                           class="button"><?php _e('Clear', 'wp-gmail-plugin'); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="gmail-inbox-stats">
                <?php
                printf(
                    _n('%d email', '%d emails', $total_emails, 'wp-gmail-plugin'),
                    number_format($total_emails)
                );
                if ($search) {
                    printf(' ' . __('matching "%s"', 'wp-gmail-plugin'), wp_gmail_esc_html($search));
                }
                ?>
            </div>

            <div class="gmail-sync-button">
                <button type="button" id="sync-emails" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Sync', 'wp-gmail-plugin'); ?>
                </button>
            </div>
        </div>

        <?php if (empty($emails)): ?>
            <div class="gmail-no-emails">
                <div class="postbox">
                    <div class="inside">
                        <?php if ($search): ?>
                            <p><?php _e('No emails found matching your search criteria.', 'wp-gmail-plugin'); ?></p>
                        <?php else: ?>
                            <p><?php _e('No emails found. Click "Sync" to fetch emails from Gmail.', 'wp-gmail-plugin'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <form method="post" action="" id="gmail-inbox-form">
                <?php wp_gmail_nonce_field('wp_gmail_inbox'); ?>

                <!-- バルクアクション -->
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="email_action" id="bulk-action-selector">
                            <option value=""><?php _e('Bulk Actions', 'wp-gmail-plugin'); ?></option>
                            <option value="read"><?php _e('Mark as Read', 'wp-gmail-plugin'); ?></option>
                            <option value="unread"><?php _e('Mark as Unread', 'wp-gmail-plugin'); ?></option>
                            <option value="star"><?php _e('Add Star', 'wp-gmail-plugin'); ?></option>
                            <option value="unstar"><?php _e('Remove Star', 'wp-gmail-plugin'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Apply', 'wp-gmail-plugin'); ?>"
                               onclick="return confirm('<?php _e('Are you sure you want to apply this action to selected emails?', 'wp-gmail-plugin'); ?>');">
                    </div>

                    <?php
                    // ページネーション（上部）
                    if ($total_pages > 1) {
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'current' => $current_page,
                            'total' => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;'
                        );

                        if ($search) {
                            $pagination_args['add_args'] = array('search' => $search);
                        }

                        echo '<div class="tablenav-pages">';
                        echo paginate_links($pagination_args);
                        echo '</div>';
                    }
                    ?>
                </div>

                <!-- メールリスト -->
                <div class="gmail-email-list">
                    <div class="email-list-header">
                        <div class="email-checkbox">
                            <input type="checkbox" id="select-all-emails">
                        </div>
                        <div class="email-star"></div>
                        <div class="email-from"><?php _e('From', 'wp-gmail-plugin'); ?></div>
                        <div class="email-subject"><?php _e('Subject', 'wp-gmail-plugin'); ?></div>
                        <div class="email-date"><?php _e('Date', 'wp-gmail-plugin'); ?></div>
                    </div>

                    <?php foreach ($emails as $email): ?>
                        <div class="email-item <?php echo $email['is_read'] ? 'read' : 'unread'; ?>">
                            <div class="email-checkbox">
                                <input type="checkbox" name="email_ids[]"
                                       value="<?php echo wp_gmail_esc_attr($email['message_id']); ?>"
                                       class="email-select">
                            </div>

                            <div class="email-star">
                                <button type="button" class="star-btn <?php echo $email['is_starred'] ? 'starred' : ''; ?>"
                                        data-message-id="<?php echo wp_gmail_esc_attr($email['message_id']); ?>"
                                        title="<?php echo $email['is_starred'] ? __('Remove star', 'wp-gmail-plugin') : __('Add star', 'wp-gmail-plugin'); ?>">
                                    <span class="dashicons dashicons-star-<?php echo $email['is_starred'] ? 'filled' : 'empty'; ?>"></span>
                                </button>
                            </div>

                            <div class="email-from">
                                <strong><?php echo wp_gmail_esc_html($email['from_name'] ?: $email['from_email']); ?></strong>
                                <div class="email-address"><?php echo wp_gmail_esc_html($email['from_email']); ?></div>
                            </div>

                            <div class="email-subject">
                                <div class="subject-line">
                                    <?php echo wp_gmail_esc_html($email['subject'] ?: __('(No Subject)', 'wp-gmail-plugin')); ?>
                                </div>
                                <div class="email-snippet">
                                    <?php echo wp_gmail_esc_html(wp_gmail_truncate_content($email['snippet'], 100)); ?>
                                </div>
                            </div>

                            <div class="email-date">
                                <div class="date-main">
                                    <?php echo wp_gmail_format_date($email['received_date'], 'M j'); ?>
                                </div>
                                <div class="date-time">
                                    <?php echo wp_gmail_format_date($email['received_date'], 'H:i'); ?>
                                </div>
                            </div>

                            <div class="email-actions">
                                <button type="button" class="button button-small view-email"
                                        data-message-id="<?php echo wp_gmail_esc_attr($email['message_id']); ?>">
                                    <?php _e('View', 'wp-gmail-plugin'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ページネーション（下部） -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php echo paginate_links($pagination_args); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- メール詳細モーダル -->
<div id="email-modal" class="gmail-modal" style="display: none;">
    <div class="gmail-modal-content">
        <div class="gmail-modal-header">
            <h2 id="modal-subject"></h2>
            <button type="button" class="gmail-modal-close">&times;</button>
        </div>
        <div class="gmail-modal-body">
            <div class="email-details">
                <div class="email-meta">
                    <p><strong><?php _e('From:', 'wp-gmail-plugin'); ?></strong> <span id="modal-from"></span></p>
                    <p><strong><?php _e('To:', 'wp-gmail-plugin'); ?></strong> <span id="modal-to"></span></p>
                    <p><strong><?php _e('Date:', 'wp-gmail-plugin'); ?></strong> <span id="modal-date"></span></p>
                </div>
                <div class="email-body" id="modal-body"></div>
            </div>
        </div>
        <div class="gmail-modal-footer">
            <button type="button" class="button button-primary" id="modal-reply">
                <?php _e('Reply', 'wp-gmail-plugin'); ?>
            </button>
            <button type="button" class="button button-secondary gmail-modal-close">
                <?php _e('Close', 'wp-gmail-plugin'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.gmail-inbox-container {
    max-width: 1200px;
}

.gmail-inbox-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.gmail-search-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-input {
    width: 300px;
}

.gmail-inbox-stats {
    color: #646970;
    font-size: 14px;
}

.gmail-email-list {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
}

.email-list-header {
    display: grid;
    grid-template-columns: 40px 40px 200px 1fr 120px;
    gap: 15px;
    padding: 12px 15px;
    background: #f9f9f9;
    border-bottom: 1px solid #ccd0d4;
    font-weight: bold;
    font-size: 13px;
    color: #646970;
}

.email-item {
    display: grid;
    grid-template-columns: 40px 40px 200px 1fr 120px;
    gap: 15px;
    padding: 15px;
    border-bottom: 1px solid #eee;
    align-items: center;
    transition: background-color 0.2s;
}

.email-item:hover {
    background-color: #f9f9f9;
}

.email-item.unread {
    background-color: #f0f8ff;
    font-weight: bold;
}

.email-item:last-child {
    border-bottom: none;
}

.email-checkbox {
    text-align: center;
}

.email-star {
    text-align: center;
}

.star-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0;
    color: #ccc;
}

.star-btn.starred {
    color: #ffb900;
}

.email-from strong {
    display: block;
    margin-bottom: 2px;
}

.email-address {
    font-size: 12px;
    color: #646970;
}

.subject-line {
    margin-bottom: 4px;
}

.email-snippet {
    font-size: 13px;
    color: #646970;
    font-weight: normal;
}

.email-date {
    text-align: right;
    font-size: 13px;
}

.date-main {
    margin-bottom: 2px;
}

.date-time {
    color: #646970;
    font-size: 12px;
}

.email-actions {
    opacity: 0;
    transition: opacity 0.2s;
}

.email-item:hover .email-actions {
    opacity: 1;
}

/* モーダルスタイル */
.gmail-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.gmail-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    border: 1px solid #888;
    border-radius: 5px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.gmail-modal-header {
    padding: 20px;
    background: #f9f9f9;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.gmail-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #aaa;
}

.gmail-modal-close:hover {
    color: #000;
}

.gmail-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.email-meta {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.email-meta p {
    margin: 5px 0;
}

.email-body {
    line-height: 1.6;
}

.gmail-modal-footer {
    padding: 20px;
    background: #f9f9f9;
    border-top: 1px solid #ddd;
    text-align: right;
}

.gmail-modal-footer .button {
    margin-left: 10px;
}

/* レスポンシブ */
@media (max-width: 782px) {
    .gmail-inbox-header {
        flex-direction: column;
        align-items: stretch;
    }

    .search-input {
        width: 100%;
    }

    .email-list-header,
    .email-item {
        grid-template-columns: 30px 30px 1fr 80px;
        gap: 10px;
    }

    .email-from {
        grid-column: 1 / -1;
        grid-row: 2;
        margin-top: 5px;
    }

    .gmail-modal-content {
        width: 95%;
        margin: 2% auto;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // 全選択/全解除
    $('#select-all-emails').on('change', function() {
        $('.email-select').prop('checked', $(this).is(':checked'));
    });

    // 同期ボタン
    $('#sync-emails').on('click', function() {
        var $button = $(this);
        var originalHtml = $button.html();

        $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> <?php _e("Syncing...", "wp-gmail-plugin"); ?>');

        $.post(ajaxurl, {
            action: 'gmail_get_emails',
            nonce: '<?php echo wp_create_nonce("wp_gmail_plugin_admin_nonce"); ?>',
            max_results: 50
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('<?php _e("Sync failed: ", "wp-gmail-plugin"); ?>' + (response.data ? response.data.error : 'Unknown error'));
            }
        }).always(function() {
            $button.prop('disabled', false).html(originalHtml);
        });
    });

    // スター切り替え
    $('.star-btn').on('click', function() {
        var $btn = $(this);
        var messageId = $btn.data('message-id');
        var isStarred = $btn.hasClass('starred');
        var action = isStarred ? 'unstar' : 'star';

        $.post(ajaxurl, {
            action: 'gmail_mark_email',
            nonce: '<?php echo wp_create_nonce("wp_gmail_plugin_admin_nonce"); ?>',
            message_id: messageId,
            email_action: action
        }, function(response) {
            if (response.success) {
                $btn.toggleClass('starred');
                $btn.find('.dashicons').toggleClass('dashicons-star-empty dashicons-star-filled');
                $btn.attr('title', isStarred ? '<?php _e("Add star", "wp-gmail-plugin"); ?>' : '<?php _e("Remove star", "wp-gmail-plugin"); ?>');
            }
        });
    });

    // メール詳細表示
    $('.view-email').on('click', function() {
        var messageId = $(this).data('message-id');
        var $emailItem = $(this).closest('.email-item');

        // メール詳細を取得
        $.post(ajaxurl, {
            action: 'gmail_get_email_details',
            nonce: '<?php echo wp_create_nonce("wp_gmail_plugin_admin_nonce"); ?>',
            message_id: messageId
        }, function(response) {
            if (response.success && response.data) {
                var email = response.data;

                $('#modal-subject').text(email.subject || '<?php _e("(No Subject)", "wp-gmail-plugin"); ?>');
                $('#modal-from').text(email.from_name + ' <' + email.from_email + '>');
                $('#modal-to').text(email.to_email);
                $('#modal-date').text(email.received_date);
                $('#modal-body').html(email.body);

                $('#email-modal').show();

                // 未読の場合は既読にする
                if ($emailItem.hasClass('unread')) {
                    $.post(ajaxurl, {
                        action: 'gmail_mark_email',
                        nonce: '<?php echo wp_create_nonce("wp_gmail_plugin_admin_nonce"); ?>',
                        message_id: messageId,
                        email_action: 'read'
                    }, function(response) {
                        if (response.success) {
                            $emailItem.removeClass('unread').addClass('read');
                        }
                    });
                }
            } else {
                alert('<?php _e("Failed to load email details.", "wp-gmail-plugin"); ?>');
            }
        });
    });

    // モーダルを閉じる
    $('.gmail-modal-close').on('click', function() {
        $('#email-modal').hide();
    });

    // モーダル外クリックで閉じる
    $(window).on('click', function(event) {
        if (event.target.id === 'email-modal') {
            $('#email-modal').hide();
        }
    });

    // 返信ボタン
    $('#modal-reply').on('click', function() {
        // 作成画面にリダイレクト（返信情報付き）
        var subject = $('#modal-subject').text();
        var from = $('#modal-from').text();

        var replySubject = subject.startsWith('Re: ') ? subject : 'Re: ' + subject;
        var composeUrl = '<?php echo admin_url("admin.php?page=wp-gmail-plugin-compose"); ?>' +
                        '&reply_to=' + encodeURIComponent(from) +
                        '&reply_subject=' + encodeURIComponent(replySubject);

        window.location.href = composeUrl;
    });
});
</script>
