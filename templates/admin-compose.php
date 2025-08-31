<?php
/**
 * Admin Compose Template
 *
 * Gmail プラグインのメール作成画面
 */

if (!defined('ABSPATH')) {
    exit;
}

// 認証チェック
if (!wp_gmail_check_configuration()) {
    return;
}

// メール送信処理
if (isset($_POST['send_email']) && wp_verify_nonce($_POST['wp_gmail_nonce'], 'wp_gmail_compose')) {
    $email_manager = new Gmail_Email_Manager();
    $result = $email_manager->send_email($_POST);

    if ($result['success']) {
        wp_gmail_show_success($result['message']);
        // フォームをリセット
        $_POST = array();
    } else {
        wp_gmail_show_error($result['error']);
    }
}

// 下書き保存処理
if (isset($_POST['save_draft']) && wp_verify_nonce($_POST['wp_gmail_nonce'], 'wp_gmail_compose')) {
    // 下書きをローカルに保存（オプション）
    $draft_data = array(
        'to' => sanitize_email($_POST['to']),
        'cc' => sanitize_text_field($_POST['cc']),
        'bcc' => sanitize_text_field($_POST['bcc']),
        'subject' => sanitize_text_field($_POST['subject']),
        'body' => wp_kses_post($_POST['body']),
        'is_html' => isset($_POST['is_html']) ? '1' : '0'
    );

    update_option('wp_gmail_plugin_draft', $draft_data);
    wp_gmail_show_success(__('Draft saved successfully!', 'wp-gmail-plugin'));
}

// 下書き読み込み
$draft = get_option('wp_gmail_plugin_draft', array());
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="gmail-compose-container">
        <form method="post" action="" id="gmail-compose-form">
            <?php wp_gmail_nonce_field('wp_gmail_compose'); ?>

            <div class="postbox">
                <h2 class="hndle"><span><?php _e('Compose Email', 'wp-gmail-plugin'); ?></span></h2>
                <div class="inside">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="to"><?php _e('To', 'wp-gmail-plugin'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="email" id="to" name="to"
                                       value="<?php echo wp_gmail_esc_attr($_POST['to'] ?? $draft['to'] ?? ''); ?>"
                                       class="regular-text" required>
                                <p class="description">
                                    <?php _e('Enter recipient email address. For multiple recipients, separate with commas.', 'wp-gmail-plugin'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="cc"><?php _e('CC', 'wp-gmail-plugin'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="cc" name="cc"
                                       value="<?php echo wp_gmail_esc_attr($_POST['cc'] ?? $draft['cc'] ?? ''); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('Carbon copy recipients (optional).', 'wp-gmail-plugin'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="bcc"><?php _e('BCC', 'wp-gmail-plugin'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="bcc" name="bcc"
                                       value="<?php echo wp_gmail_esc_attr($_POST['bcc'] ?? $draft['bcc'] ?? ''); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('Blind carbon copy recipients (optional).', 'wp-gmail-plugin'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="subject"><?php _e('Subject', 'wp-gmail-plugin'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" id="subject" name="subject"
                                       value="<?php echo wp_gmail_esc_attr($_POST['subject'] ?? $draft['subject'] ?? ''); ?>"
                                       class="regular-text" required>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="body"><?php _e('Message', 'wp-gmail-plugin'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <?php
                                $content = $_POST['body'] ?? $draft['body'] ?? '';
                                $is_html = ($_POST['is_html'] ?? $draft['is_html'] ?? '0') === '1';

                                if ($is_html) {
                                    wp_editor($content, 'body', array(
                                        'textarea_name' => 'body',
                                        'media_buttons' => true,
                                        'textarea_rows' => 15,
                                        'teeny' => false,
                                        'tinymce' => true,
                                        'quicktags' => true
                                    ));
                                } else {
                                    echo '<textarea id="body" name="body" rows="15" class="large-text" required>' .
                                         wp_gmail_esc_html($content) . '</textarea>';
                                }
                                ?>

                                <div style="margin-top: 10px;">
                                    <label>
                                        <input type="checkbox" name="is_html" value="1"
                                               <?php checked($is_html); ?> id="is_html_checkbox">
                                        <?php _e('Send as HTML email', 'wp-gmail-plugin'); ?>
                                    </label>
                                </div>
                            </td>
                        </tr>
                    </table>

                    <div class="gmail-compose-actions">
                        <input type="submit" name="send_email"
                               value="<?php _e('Send Email', 'wp-gmail-plugin'); ?>"
                               class="button button-primary button-large" id="send-email-btn">

                        <input type="submit" name="save_draft"
                               value="<?php _e('Save Draft', 'wp-gmail-plugin'); ?>"
                               class="button button-secondary button-large">

                        <button type="button" class="button button-secondary button-large" id="clear-form">
                            <?php _e('Clear Form', 'wp-gmail-plugin'); ?>
                        </button>

                        <?php if (!empty($draft)): ?>
                            <button type="button" class="button button-secondary" id="delete-draft">
                                <?php _e('Delete Draft', 'wp-gmail-plugin'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>

        <!-- 送信履歴 -->
        <div class="postbox">
            <h2 class="hndle"><span><?php _e('Email Templates', 'wp-gmail-plugin'); ?></span></h2>
            <div class="inside">
                <div class="email-templates">
                    <button type="button" class="button template-btn"
                            data-subject="<?php _e('Welcome!', 'wp-gmail-plugin'); ?>"
                            data-body="<?php _e('Thank you for joining us!', 'wp-gmail-plugin'); ?>">
                        <?php _e('Welcome Template', 'wp-gmail-plugin'); ?>
                    </button>

                    <button type="button" class="button template-btn"
                            data-subject="<?php _e('Meeting Reminder', 'wp-gmail-plugin'); ?>"
                            data-body="<?php _e('This is a reminder about our upcoming meeting.', 'wp-gmail-plugin'); ?>">
                        <?php _e('Meeting Reminder', 'wp-gmail-plugin'); ?>
                    </button>

                    <button type="button" class="button template-btn"
                            data-subject="<?php _e('Follow Up', 'wp-gmail-plugin'); ?>"
                            data-body="<?php _e('I wanted to follow up on our previous conversation.', 'wp-gmail-plugin'); ?>">
                        <?php _e('Follow Up', 'wp-gmail-plugin'); ?>
                    </button>
                </div>

                <p class="description">
                    <?php _e('Click on a template to populate the email form with predefined content.', 'wp-gmail-plugin'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.gmail-compose-container {
    max-width: 900px;
}

.required {
    color: #dc3232;
}

.gmail-compose-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.gmail-compose-actions .button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.email-templates {
    margin-bottom: 15px;
}

.template-btn {
    margin-right: 10px;
    margin-bottom: 10px;
}

.form-table th {
    width: 120px;
    vertical-align: top;
    padding-top: 15px;
}

.large-text {
    width: 100%;
}

#send-email-btn:disabled {
    opacity: 0.6;
}

.sending {
    opacity: 0.6;
    pointer-events: none;
}

@media (max-width: 782px) {
    .gmail-compose-actions .button {
        width: 100%;
        margin-right: 0;
        text-align: center;
    }

    .template-btn {
        width: 100%;
        margin-right: 0;
        text-align: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // HTMLエディター切り替え
    $('#is_html_checkbox').on('change', function() {
        var isHtml = $(this).is(':checked');
        var currentContent = '';

        if (isHtml) {
            // テキストエリアからWPエディターに切り替え
            currentContent = $('#body').val();
            $('#body').replaceWith('<div id="body-editor-container"></div>');

            // WPエディターを初期化（実際の実装では、AJAXでサーバーサイドからエディターHTMLを取得する必要があります）
            alert('<?php _e("Please save the form to enable HTML editor.", "wp-gmail-plugin"); ?>');
            $(this).prop('checked', false);
        } else {
            // WPエディターからテキストエリアに切り替え
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('body')) {
                currentContent = tinyMCE.get('body').getContent();
            }

            $('#body-editor-container').replaceWith(
                '<textarea id="body" name="body" rows="15" class="large-text" required>' +
                currentContent + '</textarea>'
            );
        }
    });

    // フォーム送信時の処理
    $('#gmail-compose-form').on('submit', function(e) {
        var submitBtn = $(this).find('input[type="submit"]:focus');

        if (submitBtn.attr('name') === 'send_email') {
            // バリデーション
            var to = $('#to').val().trim();
            var subject = $('#subject').val().trim();
            var body = $('#body').val().trim();

            if (!to || !subject || !body) {
                alert('<?php _e("Please fill in all required fields.", "wp-gmail-plugin"); ?>');
                e.preventDefault();
                return;
            }

            // メールアドレスの妥当性チェック
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            var emails = to.split(',').map(function(email) { return email.trim(); });

            for (var i = 0; i < emails.length; i++) {
                if (!emailRegex.test(emails[i])) {
                    alert('<?php _e("Please enter valid email addresses.", "wp-gmail-plugin"); ?>');
                    e.preventDefault();
                    return;
                }
            }

            // 送信中の表示
            submitBtn.val('<?php _e("Sending...", "wp-gmail-plugin"); ?>').prop('disabled', true);
            $(this).addClass('sending');
        }
    });

    // テンプレートボタンのクリック処理
    $('.template-btn').on('click', function() {
        var subject = $(this).data('subject');
        var body = $(this).data('body');

        if (confirm('<?php _e("This will replace the current content. Continue?", "wp-gmail-plugin"); ?>')) {
            $('#subject').val(subject);

            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('body')) {
                tinyMCE.get('body').setContent(body);
            } else {
                $('#body').val(body);
            }
        }
    });

    // フォームクリア
    $('#clear-form').on('click', function() {
        if (confirm('<?php _e("Are you sure you want to clear the form?", "wp-gmail-plugin"); ?>')) {
            $('#to, #cc, #bcc, #subject').val('');

            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('body')) {
                tinyMCE.get('body').setContent('');
            } else {
                $('#body').val('');
            }
        }
    });

    // 下書き削除
    $('#delete-draft').on('click', function() {
        if (confirm('<?php _e("Are you sure you want to delete the saved draft?", "wp-gmail-plugin"); ?>')) {
            $.post(ajaxurl, {
                action: 'gmail_delete_draft',
                nonce: '<?php echo wp_create_nonce("wp_gmail_plugin_admin_nonce"); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('<?php _e("Failed to delete draft.", "wp-gmail-plugin"); ?>');
                }
            });
        }
    });

    // 自動保存（5分ごと）
    setInterval(function() {
        var formData = $('#gmail-compose-form').serialize();
        formData += '&action=gmail_auto_save_draft';
        formData += '&nonce=<?php echo wp_create_nonce("wp_gmail_plugin_admin_nonce"); ?>';

        $.post(ajaxurl, formData);
    }, 300000); // 5分 = 300000ms
});
</script>
