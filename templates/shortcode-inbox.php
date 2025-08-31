<?php
/**
 * Shortcode Inbox Template
 *
 * フロントエンド用受信箱ショートコードのテンプレート
 */

if (!defined('ABSPATH')) {
    exit;
}

// 認証チェック
if (!wp_gmail_is_authenticated()) {
    echo '<div class="gmail-error">' . __('Gmail authentication required.', 'wp-gmail-plugin') . '</div>';
    return;
}

// パラメータを取得
$limit = intval($atts['limit']);
if ($limit <= 0) {
    $limit = 10;
}

// メール管理インスタンス
$email_manager = new Gmail_Email_Manager();

// データベースからメールを取得
$result = $email_manager->get_emails_from_db($limit, 0, '');
$emails = $result['emails'] ?? array();

// ユニークIDを生成
$inbox_id = 'gmail-inbox-' . wp_rand(1000, 9999);
?>

<div class="wp-gmail-inbox <?php echo wp_gmail_esc_attr($atts['class']); ?>" id="<?php echo $inbox_id; ?>">
    <div class="gmail-inbox-header">
        <h3 class="gmail-inbox-title"><?php _e('Recent Emails', 'wp-gmail-plugin'); ?></h3>
        <button type="button" class="gmail-refresh-btn" title="<?php _e('Refresh', 'wp-gmail-plugin'); ?>">
            <span class="gmail-refresh-icon">↻</span>
        </button>
    </div>

    <div class="gmail-inbox-content">
        <?php if (empty($emails)): ?>
            <div class="gmail-no-emails">
                <p><?php _e('No emails found.', 'wp-gmail-plugin'); ?></p>
            </div>
        <?php else: ?>
            <div class="gmail-email-list">
                <?php foreach ($emails as $email): ?>
                    <div class="gmail-email-item <?php echo $email['is_read'] ? 'read' : 'unread'; ?>"
                         data-message-id="<?php echo wp_gmail_esc_attr($email['message_id']); ?>">

                        <div class="gmail-email-header">
                            <div class="gmail-email-from">
                                <strong><?php echo wp_gmail_esc_html($email['from_name'] ?: $email['from_email']); ?></strong>
                                <?php if (!$email['is_read']): ?>
                                    <span class="gmail-unread-indicator">●</span>
                                <?php endif; ?>
                            </div>

                            <div class="gmail-email-date">
                                <?php echo wp_gmail_format_date($email['received_date'], 'M j, Y'); ?>
                            </div>
                        </div>

                        <div class="gmail-email-subject">
                            <?php echo wp_gmail_esc_html($email['subject'] ?: __('(No Subject)', 'wp-gmail-plugin')); ?>
                        </div>

                        <div class="gmail-email-snippet">
                            <?php echo wp_gmail_esc_html(wp_gmail_truncate_content($email['snippet'], 120)); ?>
                        </div>

                        <div class="gmail-email-actions">
                            <button type="button" class="gmail-view-btn"
                                    data-message-id="<?php echo wp_gmail_esc_attr($email['message_id']); ?>">
                                <?php _e('View', 'wp-gmail-plugin'); ?>
                            </button>

                            <?php if ($email['is_starred']): ?>
                                <span class="gmail-star-indicator" title="<?php _e('Starred', 'wp-gmail-plugin'); ?>">★</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($emails) >= $limit): ?>
                <div class="gmail-load-more">
                    <button type="button" class="gmail-load-more-btn">
                        <?php _e('Load More', 'wp-gmail-plugin'); ?>
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="gmail-loading" style="display: none;">
        <div class="gmail-spinner"></div>
        <span><?php _e('Loading...', 'wp-gmail-plugin'); ?></span>
    </div>
</div>

<!-- メール詳細モーダル -->
<div id="<?php echo $inbox_id; ?>-modal" class="gmail-modal" style="display: none;">
    <div class="gmail-modal-content">
        <div class="gmail-modal-header">
            <h3 class="gmail-modal-title"></h3>
            <button type="button" class="gmail-modal-close">&times;</button>
        </div>
        <div class="gmail-modal-body">
            <div class="gmail-email-meta">
                <div class="gmail-meta-row">
                    <strong><?php _e('From:', 'wp-gmail-plugin'); ?></strong>
                    <span class="gmail-modal-from"></span>
                </div>
                <div class="gmail-meta-row">
                    <strong><?php _e('Date:', 'wp-gmail-plugin'); ?></strong>
                    <span class="gmail-modal-date"></span>
                </div>
            </div>
            <div class="gmail-email-body"></div>
        </div>
        <div class="gmail-modal-footer">
            <button type="button" class="gmail-button gmail-button-secondary gmail-modal-close">
                <?php _e('Close', 'wp-gmail-plugin'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.wp-gmail-inbox {
    max-width: 800px;
    margin: 20px 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.gmail-inbox-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    background: #f9f9f9;
    border-radius: 5px 5px 0 0;
}

.gmail-inbox-title {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.gmail-refresh-btn {
    background: none;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 5px 10px;
    cursor: pointer;
    font-size: 16px;
    color: #666;
    transition: all 0.2s;
}

.gmail-refresh-btn:hover {
    background: #f1f1f1;
    border-color: #ccc;
}

.gmail-inbox-content {
    padding: 20px;
}

.gmail-no-emails {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.gmail-email-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.gmail-email-item {
    padding: 15px;
    border: 1px solid #eee;
    border-radius: 5px;
    transition: all 0.2s;
    cursor: pointer;
}

.gmail-email-item:hover {
    border-color: #ccc;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.gmail-email-item.unread {
    background-color: #f0f8ff;
    border-color: #0073aa;
}

.gmail-email-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.gmail-email-from {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #333;
}

.gmail-unread-indicator {
    color: #0073aa;
    font-size: 12px;
}

.gmail-email-date {
    color: #666;
    font-size: 13px;
}

.gmail-email-subject {
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.gmail-email-snippet {
    color: #666;
    font-size: 14px;
    line-height: 1.4;
    margin-bottom: 10px;
}

.gmail-email-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.gmail-view-btn {
    background: #0073aa;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 13px;
    transition: background-color 0.2s;
}

.gmail-view-btn:hover {
    background: #005a87;
}

.gmail-star-indicator {
    color: #ffb900;
    font-size: 16px;
}

.gmail-load-more {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.gmail-load-more-btn {
    background: #f1f1f1;
    color: #333;
    border: 1px solid #ccc;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.gmail-load-more-btn:hover {
    background: #e6e6e6;
}

.gmail-loading {
    text-align: center;
    padding: 20px;
    color: #666;
}

.gmail-loading .gmail-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #ddd;
    border-radius: 50%;
    border-top-color: #0073aa;
    animation: gmail-spin 1s linear infinite;
    margin-right: 10px;
}

/* モーダルスタイル */
.gmail-modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.gmail-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    border-radius: 5px;
    width: 90%;
    max-width: 700px;
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

.gmail-modal-title {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.gmail-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #aaa;
    padding: 0;
    line-height: 1;
}

.gmail-modal-close:hover {
    color: #000;
}

.gmail-modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.gmail-email-meta {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.gmail-meta-row {
    margin-bottom: 8px;
}

.gmail-meta-row strong {
    display: inline-block;
    width: 60px;
    color: #333;
}

.gmail-email-body {
    line-height: 1.6;
    color: #333;
}

.gmail-modal-footer {
    padding: 15px 20px;
    background: #f9f9f9;
    border-top: 1px solid #ddd;
    text-align: right;
}

.gmail-button {
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    border: none;
    transition: all 0.2s;
}

.gmail-button-secondary {
    background: #f1f1f1;
    color: #333;
    border: 1px solid #ccc;
}

.gmail-button-secondary:hover {
    background: #e6e6e6;
}

@keyframes gmail-spin {
    to { transform: rotate(360deg); }
}

/* レスポンシブ */
@media (max-width: 600px) {
    .wp-gmail-inbox {
        margin: 10px 0;
    }

    .gmail-inbox-header {
        padding: 10px 15px;
    }

    .gmail-inbox-content {
        padding: 15px;
    }

    .gmail-email-item {
        padding: 12px;
    }

    .gmail-email-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }

    .gmail-modal-content {
        width: 95%;
        margin: 2% auto;
    }

    .gmail-modal-header {
        padding: 15px;
    }

    .gmail-modal-body {
        padding: 15px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var inboxId = '<?php echo $inbox_id; ?>';
    var $inbox = $('#' + inboxId);
    var currentOffset = <?php echo count($emails); ?>;

    // リフレッシュボタン
    $inbox.find('.gmail-refresh-btn').on('click', function() {
        var $btn = $(this);
        var $icon = $btn.find('.gmail-refresh-icon');

        $icon.css('animation', 'gmail-spin 1s linear infinite');

        // 実際の実装では、AJAXでメールを再取得
        setTimeout(function() {
            $icon.css('animation', 'none');
            // location.reload(); // 実際にはページをリロードするか、AJAXで更新
        }, 1000);
    });

    // メール詳細表示
    $inbox.on('click', '.gmail-view-btn', function(e) {
        e.stopPropagation();
        var messageId = $(this).data('message-id');

        // ローディング表示
        $inbox.find('.gmail-loading').show();

        // メール詳細を取得（実際の実装ではAJAX）
        setTimeout(function() {
            // ダミーデータ（実際の実装では、AJAXでサーバーからデータを取得）
            var email = {
                subject: 'Sample Email Subject',
                from_name: 'John Doe',
                from_email: 'john@example.com',
                received_date: '2024-01-15 10:30:00',
                body: 'This is the email body content...'
            };

            var $modal = $('#' + inboxId + '-modal');
            $modal.find('.gmail-modal-title').text(email.subject);
            $modal.find('.gmail-modal-from').text(email.from_name + ' <' + email.from_email + '>');
            $modal.find('.gmail-modal-date').text(email.received_date);
            $modal.find('.gmail-email-body').html(email.body);

            $modal.show();
            $inbox.find('.gmail-loading').hide();
        }, 500);
    });

    // メールアイテムクリック（詳細表示）
    $inbox.on('click', '.gmail-email-item', function(e) {
        if (!$(e.target).hasClass('gmail-view-btn')) {
            $(this).find('.gmail-view-btn').click();
        }
    });

    // モーダルを閉じる
    $(document).on('click', '.gmail-modal-close', function() {
        $(this).closest('.gmail-modal').hide();
    });

    // モーダル外クリックで閉じる
    $(document).on('click', '.gmail-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    // もっと読み込む
    $inbox.on('click', '.gmail-load-more-btn', function() {
        var $btn = $(this);
        var originalText = $btn.text();

        $btn.text('<?php _e("Loading...", "wp-gmail-plugin"); ?>').prop('disabled', true);

        // 実際の実装では、AJAXで追加のメールを取得
        setTimeout(function() {
            // ダミーで現在のメールをコピー（実際の実装では新しいメールを追加）
            var $newEmails = $inbox.find('.gmail-email-item').slice(0, 3).clone();
            $inbox.find('.gmail-email-list').append($newEmails);

            currentOffset += 3;

            $btn.text(originalText).prop('disabled', false);

            // 全て読み込み完了の場合はボタンを非表示
            if (currentOffset >= 20) { // 仮の最大数
                $btn.parent().hide();
            }
        }, 1000);
    });
});
</script>
