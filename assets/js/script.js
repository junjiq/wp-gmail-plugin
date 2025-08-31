/**
 * Frontend JavaScript for WP Gmail Plugin
 *
 * フロントエンド用のJavaScript
 */

(function($) {
    'use strict';

    // プラグインの初期化
    var WPGmailPlugin = {

        init: function() {
            this.initComposeForm();
            this.initInboxList();
            this.initModals();
            this.bindEvents();
        },

        /**
         * メール作成フォームの初期化
         */
        initComposeForm: function() {
            $('.wp-gmail-compose-form').each(function() {
                var $form = $(this);
                var $submitBtn = $form.find('button[name="send_email"]');
                var $clearBtn = $form.find('.gmail-clear-form');

                // フォーム送信処理
                $form.find('.gmail-compose-form').on('submit', function(e) {
                    e.preventDefault();
                    WPGmailPlugin.handleEmailSubmit($(this));
                });

                // クリアボタン処理
                $clearBtn.on('click', function() {
                    WPGmailPlugin.clearForm($form);
                });

                // リアルタイムバリデーション
                $form.find('input, textarea').on('blur', function() {
                    WPGmailPlugin.validateField($(this));
                });

                // 文字数カウンター（件名用）
                var $subjectInput = $form.find('.gmail-input-subject');
                if ($subjectInput.length) {
                    WPGmailPlugin.addCharacterCounter($subjectInput, 78); // Gmail推奨文字数
                }
            });
        },

        /**
         * 受信箱リストの初期化
         */
        initInboxList: function() {
            $('.wp-gmail-inbox').each(function() {
                var $inbox = $(this);
                var $refreshBtn = $inbox.find('.gmail-refresh-btn');
                var $loadMoreBtn = $inbox.find('.gmail-load-more-btn');

                // リフレッシュボタン処理
                $refreshBtn.on('click', function() {
                    WPGmailPlugin.refreshInbox($inbox);
                });

                // もっと読み込むボタン処理
                $loadMoreBtn.on('click', function() {
                    WPGmailPlugin.loadMoreEmails($inbox);
                });

                // メールアイテムクリック処理
                $inbox.on('click', '.gmail-email-item', function(e) {
                    if (!$(e.target).hasClass('gmail-view-btn')) {
                        $(this).find('.gmail-view-btn').click();
                    }
                });

                // メール詳細ボタン処理
                $inbox.on('click', '.gmail-view-btn', function(e) {
                    e.stopPropagation();
                    var messageId = $(this).data('message-id');
                    WPGmailPlugin.showEmailDetails($inbox, messageId);
                });
            });
        },

        /**
         * モーダルの初期化
         */
        initModals: function() {
            // モーダルを閉じる処理
            $(document).on('click', '.gmail-modal-close', function() {
                $(this).closest('.gmail-modal').fadeOut(300);
            });

            // モーダル外クリックで閉じる
            $(document).on('click', '.gmail-modal', function(e) {
                if (e.target === this) {
                    $(this).fadeOut(300);
                }
            });

            // ESCキーでモーダルを閉じる
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // ESC key
                    $('.gmail-modal:visible').fadeOut(300);
                }
            });
        },

        /**
         * イベントのバインド
         */
        bindEvents: function() {
            // ウィンドウリサイズ時の処理
            $(window).on('resize', debounce(function() {
                WPGmailPlugin.handleResize();
            }, 250));

            // フォームの未保存変更警告
            $('form.gmail-compose-form').on('change input', 'input, textarea', function() {
                $(this).closest('form').data('changed', true);
            });

            $(window).on('beforeunload', function(e) {
                var hasChanges = false;
                $('form.gmail-compose-form').each(function() {
                    if ($(this).data('changed')) {
                        hasChanges = true;
                        return false;
                    }
                });

                if (hasChanges) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        },

        /**
         * メール送信処理
         */
        handleEmailSubmit: function($form) {
            var $container = $form.closest('.wp-gmail-compose-form');
            var $button = $form.find('button[name="send_email"]');
            var $messages = $container.find('.gmail-messages');
            var $success = $container.find('.gmail-success-message');
            var $error = $container.find('.gmail-error-message');

            // バリデーション
            if (!this.validateForm($form)) {
                return false;
            }

            // 送信中の表示
            this.setButtonLoading($button, true);
            $messages.hide();

            // フォームデータを収集
            var formData = {
                action: 'gmail_send_email',
                nonce: $form.find('input[name="wp_gmail_nonce"]').val(),
                to: $form.find('input[name="to"]').val().trim(),
                subject: $form.find('input[name="subject"]').val().trim(),
                body: $form.find('textarea[name="body"]').val().trim(),
                from_frontend: '1'
            };

            // AJAX送信
            $.ajax({
                url: wpGmailPlugin.ajax_url,
                type: 'POST',
                data: formData,
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        $success.text(response.data.message || 'Email sent successfully!').show();
                        $error.hide();
                        $form[0].reset();
                        $form.data('changed', false);

                        // 成功時のアニメーション
                        $success.hide().slideDown(400);

                        // 自動で成功メッセージを隠す
                        setTimeout(function() {
                            $success.slideUp(400);
                        }, 5000);

                    } else {
                        $error.text(response.data.error || 'Failed to send email.').show();
                        $success.hide();
                        $error.hide().slideDown(400);
                    }
                    $messages.show();
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Network error. Please try again.';
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    }

                    $error.text(errorMessage).show();
                    $success.hide();
                    $messages.show();
                    $error.hide().slideDown(400);
                },
                complete: function() {
                    WPGmailPlugin.setButtonLoading($button, false);
                }
            });
        },

        /**
         * フォームバリデーション
         */
        validateForm: function($form) {
            var isValid = true;
            var $container = $form.closest('.wp-gmail-compose-form');
            var $error = $container.find('.gmail-error-message');

            // 必須フィールドのチェック
            $form.find('input[required], textarea[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();

                if (!value) {
                    $field.addClass('error');
                    isValid = false;
                } else {
                    $field.removeClass('error');
                }
            });

            // メールアドレスの形式チェック
            var emailValue = $form.find('input[name="to"]').val().trim();
            if (emailValue && !this.isValidEmail(emailValue)) {
                $form.find('input[name="to"]').addClass('error');
                $error.text('Please enter a valid email address.').show();
                $container.find('.gmail-messages').show();
                isValid = false;
            }

            if (!isValid && !$error.is(':visible')) {
                $error.text('Please fill in all required fields.').show();
                $container.find('.gmail-messages').show();
            }

            return isValid;
        },

        /**
         * フィールドバリデーション
         */
        validateField: function($field) {
            var value = $field.val().trim();
            var isRequired = $field.prop('required');
            var fieldType = $field.attr('type');

            if (isRequired && !value) {
                $field.addClass('error');
                return false;
            }

            if (fieldType === 'email' && value && !this.isValidEmail(value)) {
                $field.addClass('error');
                return false;
            }

            $field.removeClass('error');
            return true;
        },

        /**
         * メールアドレスの妥当性チェック
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * フォームクリア
         */
        clearForm: function($container) {
            if (confirm('Are you sure you want to clear the form?')) {
                var $form = $container.find('.gmail-compose-form');
                $form[0].reset();
                $form.data('changed', false);
                $container.find('.gmail-messages').hide();
                $container.find('input, textarea').removeClass('error');
            }
        },

        /**
         * ボタンのローディング状態設定
         */
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true);
                $button.find('.gmail-button-text').hide();
                $button.find('.gmail-button-loading').show();
            } else {
                $button.prop('disabled', false);
                $button.find('.gmail-button-text').show();
                $button.find('.gmail-button-loading').hide();
            }
        },

        /**
         * 受信箱のリフレッシュ
         */
        refreshInbox: function($inbox) {
            var $refreshBtn = $inbox.find('.gmail-refresh-btn');
            var $icon = $refreshBtn.find('.gmail-refresh-icon');

            $icon.addClass('rotating');
            $refreshBtn.prop('disabled', true);

            // 実際の実装では、AJAXでメールを再取得
            setTimeout(function() {
                $icon.removeClass('rotating');
                $refreshBtn.prop('disabled', false);

                // 成功のフィードバック
                $inbox.find('.gmail-inbox-title').text('Recent Emails (Updated)')
                      .css('color', '#28a745');

                setTimeout(function() {
                    $inbox.find('.gmail-inbox-title').text('Recent Emails')
                          .css('color', '');
                }, 2000);
            }, 1500);
        },

        /**
         * より多くのメールを読み込む
         */
        loadMoreEmails: function($inbox) {
            var $btn = $inbox.find('.gmail-load-more-btn');
            var originalText = $btn.text();

            $btn.text('Loading...').prop('disabled', true);

            // 実際の実装では、AJAXで追加のメールを取得
            setTimeout(function() {
                // ダミーでメールを複製（実際の実装では新しいメールを追加）
                var $emailList = $inbox.find('.gmail-email-list');
                var $existingEmails = $emailList.find('.gmail-email-item').slice(0, 3);
                var $newEmails = $existingEmails.clone();

                // 新しいメールを追加
                $newEmails.hide().appendTo($emailList).slideDown(400);

                $btn.text(originalText).prop('disabled', false);

                // 制限に達した場合はボタンを非表示
                if ($emailList.find('.gmail-email-item').length >= 20) {
                    $btn.parent().slideUp(400);
                }
            }, 1200);
        },

        /**
         * メール詳細表示
         */
        showEmailDetails: function($inbox, messageId) {
            var inboxId = $inbox.attr('id');
            var $modal = $('#' + inboxId + '-modal');
            var $loading = $inbox.find('.gmail-loading');

            $loading.show();

            // 実際の実装では、AJAXでメール詳細を取得
            setTimeout(function() {
                // ダミーデータ
                var emailData = {
                    subject: 'Sample Email Subject',
                    from_name: 'John Doe',
                    from_email: 'john@example.com',
                    received_date: new Date().toLocaleString(),
                    body: '<p>This is a sample email body with <strong>HTML formatting</strong>.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>'
                };

                // モーダルにデータを設定
                $modal.find('.gmail-modal-title').text(emailData.subject);
                $modal.find('.gmail-modal-from').text(emailData.from_name + ' <' + emailData.from_email + '>');
                $modal.find('.gmail-modal-date').text(emailData.received_date);
                $modal.find('.gmail-email-body').html(emailData.body);

                // モーダル表示
                $modal.fadeIn(400);
                $loading.hide();

                // 未読メールを既読にマーク
                var $emailItem = $inbox.find('.gmail-email-item[data-message-id="' + messageId + '"]');
                if ($emailItem.hasClass('unread')) {
                    $emailItem.removeClass('unread').addClass('read');
                    $emailItem.find('.gmail-unread-indicator').fadeOut(400);
                }
            }, 800);
        },

        /**
         * 文字数カウンター追加
         */
        addCharacterCounter: function($input, maxLength) {
            var $counter = $('<div class="gmail-char-counter"></div>');
            $input.after($counter);

            var updateCounter = function() {
                var currentLength = $input.val().length;
                var remaining = maxLength - currentLength;
                var className = remaining < 0 ? 'over-limit' : (remaining < 10 ? 'near-limit' : '');

                $counter.text(currentLength + '/' + maxLength)
                       .removeClass('over-limit near-limit')
                       .addClass(className);
            };

            $input.on('input keyup', updateCounter);
            updateCounter();
        },

        /**
         * レスポンシブ対応
         */
        handleResize: function() {
            var windowWidth = $(window).width();

            // モバイルレイアウトの調整
            if (windowWidth < 768) {
                $('.gmail-email-list').addClass('mobile-layout');
            } else {
                $('.gmail-email-list').removeClass('mobile-layout');
            }
        },

        /**
         * アクセシビリティ機能
         */
        initAccessibility: function() {
            // キーボードナビゲーション
            $('.gmail-email-item').attr('tabindex', '0').on('keydown', function(e) {
                if (e.keyCode === 13 || e.keyCode === 32) { // Enter or Space
                    e.preventDefault();
                    $(this).click();
                }
            });

            // スクリーンリーダー用のラベル
            $('.gmail-view-btn').attr('aria-label', 'View email details');
            $('.gmail-refresh-btn').attr('aria-label', 'Refresh email list');
        }
    };

    /**
     * デバウンス関数
     */
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    /**
     * ユーティリティ関数
     */
    var Utils = {
        /**
         * HTMLエスケープ
         */
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * 日付フォーマット
         */
        formatDate: function(date) {
            if (!(date instanceof Date)) {
                date = new Date(date);
            }
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        },

        /**
         * ファイルサイズフォーマット
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };

    // CSS追加（文字数カウンター用）
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .gmail-char-counter {
                font-size: 12px;
                color: #666;
                text-align: right;
                margin-top: 5px;
            }
            .gmail-char-counter.near-limit {
                color: #ff9800;
            }
            .gmail-char-counter.over-limit {
                color: #f44336;
                font-weight: bold;
            }
            .rotating {
                animation: gmail-rotate 1s linear infinite;
            }
            @keyframes gmail-rotate {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .gmail-input.error,
            .gmail-textarea.error {
                border-color: #f44336 !important;
                box-shadow: 0 0 0 1px #f44336 !important;
            }
        `)
        .appendTo('head');

    // DOM読み込み完了後に初期化
    $(document).ready(function() {
        WPGmailPlugin.init();
        WPGmailPlugin.initAccessibility();
    });

    // グローバルに公開
    window.WPGmailPlugin = WPGmailPlugin;
    window.WPGmailUtils = Utils;

})(jQuery);
