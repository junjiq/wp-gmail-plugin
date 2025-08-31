<?php
/**
 * Shortcode Compose Template
 *
 * フロントエンド用メール作成ショートコードのテンプレート
 */

if (!defined('ABSPATH')) {
    exit;
}

// 認証チェック
if (!wp_gmail_is_authenticated()) {
    echo '<div class="gmail-error">' . __('Gmail authentication required.', 'wp-gmail-plugin') . '</div>';
    return;
}

// ユニークIDを生成（複数のフォームが同じページにある場合に対応）
$form_id = 'gmail-compose-' . wp_rand(1000, 9999);
?>

<div class="wp-gmail-compose-form <?php echo wp_gmail_esc_attr($atts['class']); ?>" id="<?php echo $form_id; ?>">
    <form class="gmail-compose-form" method="post" action="#<?php echo $form_id; ?>">
        <?php wp_nonce_field('wp_gmail_compose_frontend', 'wp_gmail_nonce'); ?>

        <div class="gmail-form-row">
            <label for="<?php echo $form_id; ?>_to" class="gmail-label">
                <?php _e('To', 'wp-gmail-plugin'); ?> <span class="required">*</span>
            </label>
            <input type="email"
                   id="<?php echo $form_id; ?>_to"
                   name="to"
                   value="<?php echo wp_gmail_esc_attr($atts['to']); ?>"
                   class="gmail-input gmail-input-to"
                   required>
        </div>

        <div class="gmail-form-row">
            <label for="<?php echo $form_id; ?>_subject" class="gmail-label">
                <?php _e('Subject', 'wp-gmail-plugin'); ?> <span class="required">*</span>
            </label>
            <input type="text"
                   id="<?php echo $form_id; ?>_subject"
                   name="subject"
                   value="<?php echo wp_gmail_esc_attr($atts['subject']); ?>"
                   class="gmail-input gmail-input-subject"
                   required>
        </div>

        <div class="gmail-form-row">
            <label for="<?php echo $form_id; ?>_body" class="gmail-label">
                <?php _e('Message', 'wp-gmail-plugin'); ?> <span class="required">*</span>
            </label>
            <textarea id="<?php echo $form_id; ?>_body"
                      name="body"
                      rows="8"
                      class="gmail-textarea gmail-input-body"
                      required></textarea>
        </div>

        <div class="gmail-form-row gmail-form-actions">
            <button type="submit" name="send_email" class="gmail-button gmail-button-primary">
                <span class="gmail-button-text"><?php _e('Send Email', 'wp-gmail-plugin'); ?></span>
                <span class="gmail-button-loading" style="display: none;">
                    <span class="gmail-spinner"></span>
                    <?php _e('Sending...', 'wp-gmail-plugin'); ?>
                </span>
            </button>

            <button type="button" class="gmail-button gmail-button-secondary gmail-clear-form">
                <?php _e('Clear', 'wp-gmail-plugin'); ?>
            </button>
        </div>
    </form>

    <div class="gmail-messages" style="display: none;">
        <div class="gmail-success-message" style="display: none;"></div>
        <div class="gmail-error-message" style="display: none;"></div>
    </div>
</div>

<style>
.wp-gmail-compose-form {
    max-width: 600px;
    margin: 20px 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.gmail-form-row {
    margin-bottom: 20px;
}

.gmail-label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #333;
}

.required {
    color: #dc3232;
}

.gmail-input,
.gmail-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    line-height: 1.4;
    box-sizing: border-box;
    transition: border-color 0.2s;
}

.gmail-input:focus,
.gmail-textarea:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 1px #0073aa;
}

.gmail-textarea {
    resize: vertical;
    min-height: 120px;
    font-family: inherit;
}

.gmail-form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.gmail-button {
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    min-height: 44px;
    box-sizing: border-box;
}

.gmail-button-primary {
    background-color: #0073aa;
    color: white;
}

.gmail-button-primary:hover {
    background-color: #005a87;
}

.gmail-button-secondary {
    background-color: #f1f1f1;
    color: #333;
    border: 1px solid #ccc;
}

.gmail-button-secondary:hover {
    background-color: #e6e6e6;
}

.gmail-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.gmail-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: gmail-spin 1s linear infinite;
    margin-right: 8px;
}

@keyframes gmail-spin {
    to { transform: rotate(360deg); }
}

.gmail-messages {
    margin-top: 15px;
}

.gmail-success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 10px;
}

.gmail-error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 10px;
}

/* レスポンシブ */
@media (max-width: 600px) {
    .wp-gmail-compose-form {
        margin: 10px 0;
        padding: 15px;
    }

    .gmail-form-actions {
        flex-direction: column;
    }

    .gmail-button {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#<?php echo $form_id; ?> .gmail-compose-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $container = $form.closest('.wp-gmail-compose-form');
        var $button = $form.find('button[name="send_email"]');
        var $messages = $container.find('.gmail-messages');
        var $success = $container.find('.gmail-success-message');
        var $error = $container.find('.gmail-error-message');

        // バリデーション
        var to = $form.find('input[name="to"]').val().trim();
        var subject = $form.find('input[name="subject"]').val().trim();
        var body = $form.find('textarea[name="body"]').val().trim();

        if (!to || !subject || !body) {
            $error.text('<?php _e("Please fill in all required fields.", "wp-gmail-plugin"); ?>').show();
            $success.hide();
            $messages.show();
            return;
        }

        // メールアドレスの妥当性チェック
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(to)) {
            $error.text('<?php _e("Please enter a valid email address.", "wp-gmail-plugin"); ?>').show();
            $success.hide();
            $messages.show();
            return;
        }

        // 送信中の表示
        $button.prop('disabled', true);
        $button.find('.gmail-button-text').hide();
        $button.find('.gmail-button-loading').show();
        $messages.hide();

        // AJAX送信
        $.post('<?php echo wp_gmail_ajax_url(); ?>', {
            action: 'gmail_send_email',
            nonce: $form.find('input[name="wp_gmail_nonce"]').val(),
            to: to,
            subject: subject,
            body: body,
            from_frontend: '1'
        }, function(response) {
            if (response.success) {
                $success.text(response.data.message || '<?php _e("Email sent successfully!", "wp-gmail-plugin"); ?>').show();
                $error.hide();
                $form[0].reset(); // フォームをリセット
            } else {
                $error.text(response.data.error || '<?php _e("Failed to send email.", "wp-gmail-plugin"); ?>').show();
                $success.hide();
            }
            $messages.show();
        }).fail(function() {
            $error.text('<?php _e("Network error. Please try again.", "wp-gmail-plugin"); ?>').show();
            $success.hide();
            $messages.show();
        }).always(function() {
            // ボタンを元に戻す
            $button.prop('disabled', false);
            $button.find('.gmail-button-text').show();
            $button.find('.gmail-button-loading').hide();
        });
    });

    // クリアボタン
    $('#<?php echo $form_id; ?> .gmail-clear-form').on('click', function() {
        if (confirm('<?php _e("Are you sure you want to clear the form?", "wp-gmail-plugin"); ?>')) {
            var $form = $(this).closest('.gmail-compose-form');
            $form[0].reset();
            $form.closest('.wp-gmail-compose-form').find('.gmail-messages').hide();
        }
    });
});
</script>
