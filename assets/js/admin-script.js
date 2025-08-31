/**
 * Admin JavaScript for WP Gmail Plugin
 *
 * 管理画面用のJavaScript
 */

(function($) {
    'use strict';

    var WPGmailAdmin = {

        init: function() {
            this.initDashboard();
            this.initSettings();
            this.initCompose();
            this.initInbox();
            this.bindEvents();
        },

        /**
         * ダッシュボードの初期化
         */
        initDashboard: function() {
            // 同期ボタンの処理
            $('#sync-emails').on('click', function() {
                WPGmailAdmin.syncEmails($(this));
            });

            // 最近のメール読み込み
            if ($('#recent-emails-list').length) {
                this.loadRecentEmails();
            }

            // 統計情報のアニメーション
            this.animateStats();
        },

        /**
         * 設定画面の初期化
         */
        initSettings: function() {
            // 認証ボタンの処理
            $('.gmail-auth-btn').on('click', function(e) {
                e.preventDefault();
                var authUrl = $(this).attr('href');
                WPGmailAdmin.openAuthWindow(authUrl);
            });

            // 設定フォームの検証
            $('form').on('submit', function() {
                return WPGmailAdmin.validateSettings($(this));
            });

            // リダイレクトURIのコピー機能
            this.initCopyToClipboard();
        },

        /**
         * メール作成画面の初期化
         */
        initCompose: function() {
            // HTMLエディター切り替え
            $('#is_html_checkbox').on('change', function() {
                WPGmailAdmin.toggleHtmlEditor($(this).is(':checked'));
            });

            // テンプレートボタン
            $('.template-btn').on('click', function() {
                WPGmailAdmin.applyTemplate($(this));
            });

            // フォームクリア
            $('#clear-form').on('click', function() {
                WPGmailAdmin.clearComposeForm();
            });

            // 下書き削除
            $('#delete-draft').on('click', function() {
                WPGmailAdmin.deleteDraft();
            });

            // 自動保存
            this.initAutoSave();

            // フォーム送信処理
            $('#gmail-compose-form').on('submit', function(e) {
                WPGmailAdmin.handleComposeSubmit($(this), e);
            });
        },

        /**
         * 受信箱の初期化
         */
        initInbox: function() {
            // 全選択/解除
            $('#select-all-emails').on('change', function() {
                $('.email-select').prop('checked', $(this).is(':checked'));
            });

            // バルクアクション
            $('#gmail-inbox-form').on('submit', function(e) {
                return WPGmailAdmin.handleBulkAction($(this), e);
            });

            // スター切り替え
            $('.star-btn').on('click', function() {
                WPGmailAdmin.toggleStar($(this));
            });

            // メール詳細表示
            $('.view-email').on('click', function() {
                WPGmailAdmin.viewEmailDetails($(this));
            });

            // 同期ボタン
            $('#sync-emails').on('click', function() {
                WPGmailAdmin.syncEmails($(this));
            });
        },

        /**
         * イベントのバインド
         */
        bindEvents: function() {
            // モーダル関連
            $('.gmail-modal-close').on('click', function() {
                $(this).closest('.gmail-modal').fadeOut(300);
            });

            $(window).on('click', function(e) {
                if ($(e.target).hasClass('gmail-modal')) {
                    $(e.target).fadeOut(300);
                }
            });

            // ESCキーでモーダルを閉じる
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) {
                    $('.gmail-modal:visible').fadeOut(300);
                }
            });

            // 確認ダイアログ
            $('input[type="submit"][onclick*="confirm"]').on('click', function(e) {
                var confirmMessage = $(this).attr('onclick').match(/confirm\('([^']+)'\)/);
                if (confirmMessage && !confirm(confirmMessage[1])) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * メール同期処理
         */
        syncEmails: function($button) {
            var originalHtml = $button.html();

            $button.prop('disabled', true)
                   .html('<span class="dashicons dashicons-update dashicons-spin"></span> Syncing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gmail_get_emails',
                    nonce: wpGmailPluginAdmin.nonce,
                    max_results: 50
                },
                timeout: 60000,
                success: function(response) {
                    if (response.success) {
                        WPGmailAdmin.showNotice('success', 'Emails synced successfully!');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        WPGmailAdmin.showNotice('error', 'Sync failed: ' + (response.data ? response.data.error : 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Network error occurred.';
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    }
                    WPGmailAdmin.showNotice('error', errorMessage);
                },
                complete: function() {
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        },

        /**
         * 最近のメール読み込み
         */
        loadRecentEmails: function() {
            var $container = $('#recent-emails-list');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gmail_get_emails',
                    nonce: wpGmailPluginAdmin.nonce,
                    source: 'database',
                    limit: 5
                },
                success: function(response) {
                    if (response.success && response.data.emails) {
                        var html = '';
                        $.each(response.data.emails, function(i, email) {
                            html += WPGmailAdmin.buildEmailItemHtml(email);
                        });
                        $container.html(html);
                    } else {
                        $container.html('<p>No recent emails found.</p>');
                    }
                },
                error: function() {
                    $container.html('<p>Failed to load recent emails.</p>');
                }
            });
        },

        /**
         * メールアイテムのHTML生成
         */
        buildEmailItemHtml: function(email) {
            var date = new Date(email.received_date).toLocaleDateString();
            var fromName = email.from_name || email.from_email;
            var subject = email.subject || '(No Subject)';

            return `
                <div class="email-item-preview">
                    <div class="email-subject-preview">${WPGmailAdmin.escapeHtml(subject)}</div>
                    <div class="email-from-preview">${WPGmailAdmin.escapeHtml(fromName)}</div>
                    <div class="email-date-preview">${date}</div>
                </div>
            `;
        },

        /**
         * 統計情報のアニメーション
         */
        animateStats: function() {
            $('.stat-number').each(function() {
                var $this = $(this);
                var countTo = parseInt($this.text().replace(/,/g, ''));

                $({ countNum: 0 }).animate({
                    countNum: countTo
                }, {
                    duration: 2000,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.floor(this.countNum).toLocaleString());
                    },
                    complete: function() {
                        $this.text(countTo.toLocaleString());
                    }
                });
            });
        },

        /**
         * 認証ウィンドウを開く
         */
        openAuthWindow: function(authUrl) {
            var width = 600;
            var height = 700;
            var left = (screen.width - width) / 2;
            var top = (screen.height - height) / 2;

            var authWindow = window.open(
                authUrl,
                'gmail_auth',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
            );

            // 認証完了の監視
            var checkClosed = setInterval(function() {
                if (authWindow.closed) {
                    clearInterval(checkClosed);
                    // 認証完了後にページをリロード
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            }, 1000);
        },

        /**
         * 設定の検証
         */
        validateSettings: function($form) {
            var clientId = $form.find('#client_id').val().trim();
            var clientSecret = $form.find('#client_secret').val().trim();

            if (!clientId || !clientSecret) {
                WPGmailAdmin.showNotice('error', 'Client ID and Client Secret are required.');
                return false;
            }

            // Client IDの形式チェック
            if (!clientId.includes('.googleusercontent.com')) {
                WPGmailAdmin.showNotice('error', 'Invalid Client ID format.');
                return false;
            }

            return true;
        },

        /**
         * クリップボードコピー機能
         */
        initCopyToClipboard: function() {
            var $redirectUri = $('#redirect_uri');
            if ($redirectUri.length) {
                var $copyBtn = $('<button type="button" class="button button-secondary">Copy</button>');
                $redirectUri.after($copyBtn);

                $copyBtn.on('click', function() {
                    $redirectUri.select();
                    document.execCommand('copy');

                    $(this).text('Copied!').addClass('success');
                    setTimeout(function() {
                        $copyBtn.text('Copy').removeClass('success');
                    }, 2000);
                });
            }
        },

        /**
         * HTMLエディター切り替え
         */
        toggleHtmlEditor: function(isHtml) {
            var $body = $('#body');
            var currentContent = $body.val();

            if (isHtml) {
                // WordPress エディターに切り替え（実際の実装では、サーバーサイドで処理）
                alert('Please save the form to enable HTML editor.');
                $('#is_html_checkbox').prop('checked', false);
            }
        },

        /**
         * テンプレート適用
         */
        applyTemplate: function($button) {
            var subject = $button.data('subject');
            var body = $button.data('body');

            if (confirm('This will replace the current content. Continue?')) {
                $('#subject').val(subject);

                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('body')) {
                    tinyMCE.get('body').setContent(body);
                } else {
                    $('#body').val(body);
                }
            }
        },

        /**
         * 作成フォームクリア
         */
        clearComposeForm: function() {
            if (confirm('Are you sure you want to clear the form?')) {
                $('#to, #cc, #bcc, #subject').val('');

                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('body')) {
                    tinyMCE.get('body').setContent('');
                } else {
                    $('#body').val('');
                }
            }
        },

        /**
         * 下書き削除
         */
        deleteDraft: function() {
            if (confirm('Are you sure you want to delete the saved draft?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gmail_delete_draft',
                        nonce: wpGmailPluginAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            WPGmailAdmin.showNotice('error', 'Failed to delete draft.');
                        }
                    }
                });
            }
        },

        /**
         * 自動保存の初期化
         */
        initAutoSave: function() {
            var autoSaveInterval = setInterval(function() {
                var $form = $('#gmail-compose-form');
                if ($form.length && $form.find('#to').val().trim()) {
                    WPGmailAdmin.autoSaveDraft();
                }
            }, 300000); // 5分ごと

            // ページを離れる時にクリア
            $(window).on('beforeunload', function() {
                clearInterval(autoSaveInterval);
            });
        },

        /**
         * 自動下書き保存
         */
        autoSaveDraft: function() {
            var formData = $('#gmail-compose-form').serialize();
            formData += '&action=gmail_auto_save_draft';
            formData += '&nonce=' + wpGmailPluginAdmin.nonce;

            $.post(ajaxurl, formData);
        },

        /**
         * メール作成フォーム送信処理
         */
        handleComposeSubmit: function($form, e) {
            var submitBtn = $form.find('input[type="submit"]:focus');

            if (submitBtn.attr('name') === 'send_email') {
                // バリデーション
                if (!this.validateComposeForm($form)) {
                    e.preventDefault();
                    return false;
                }

                // 送信中の表示
                submitBtn.val('Sending...').prop('disabled', true);
                $form.addClass('sending');
            }
        },

        /**
         * メール作成フォームのバリデーション
         */
        validateComposeForm: function($form) {
            var to = $('#to').val().trim();
            var subject = $('#subject').val().trim();
            var body = $('#body').val().trim();

            if (!to || !subject || !body) {
                WPGmailAdmin.showNotice('error', 'Please fill in all required fields.');
                return false;
            }

            // メールアドレスの妥当性チェック
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            var emails = to.split(',').map(function(email) { return email.trim(); });

            for (var i = 0; i < emails.length; i++) {
                if (!emailRegex.test(emails[i])) {
                    WPGmailAdmin.showNotice('error', 'Please enter valid email addresses.');
                    return false;
                }
            }

            return true;
        },

        /**
         * バルクアクション処理
         */
        handleBulkAction: function($form, e) {
            var action = $form.find('#bulk-action-selector').val();
            var selectedEmails = $form.find('.email-select:checked');

            if (!action) {
                e.preventDefault();
                WPGmailAdmin.showNotice('error', 'Please select an action.');
                return false;
            }

            if (selectedEmails.length === 0) {
                e.preventDefault();
                WPGmailAdmin.showNotice('error', 'Please select at least one email.');
                return false;
            }

            var actionText = $form.find('#bulk-action-selector option:selected').text();
            if (!confirm(`Are you sure you want to "${actionText}" ${selectedEmails.length} selected emails?`)) {
                e.preventDefault();
                return false;
            }

            return true;
        },

        /**
         * スター切り替え
         */
        toggleStar: function($button) {
            var messageId = $button.data('message-id');
            var isStarred = $button.hasClass('starred');
            var action = isStarred ? 'unstar' : 'star';

            $button.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gmail_mark_email',
                    nonce: wpGmailPluginAdmin.nonce,
                    message_id: messageId,
                    email_action: action
                },
                success: function(response) {
                    if (response.success) {
                        $button.toggleClass('starred');
                        $button.find('.dashicons').toggleClass('dashicons-star-empty dashicons-star-filled');
                        var newTitle = isStarred ? 'Add star' : 'Remove star';
                        $button.attr('title', newTitle);
                    } else {
                        WPGmailAdmin.showNotice('error', 'Failed to update star status.');
                    }
                },
                error: function() {
                    WPGmailAdmin.showNotice('error', 'Network error occurred.');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * メール詳細表示
         */
        viewEmailDetails: function($button) {
            var messageId = $button.data('message-id');
            var $emailItem = $button.closest('.email-item');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gmail_get_email_details',
                    nonce: wpGmailPluginAdmin.nonce,
                    message_id: messageId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        WPGmailAdmin.showEmailModal(response.data);

                        // 未読の場合は既読にする
                        if ($emailItem.hasClass('unread')) {
                            WPGmailAdmin.markAsRead(messageId, $emailItem);
                        }
                    } else {
                        WPGmailAdmin.showNotice('error', 'Failed to load email details.');
                    }
                },
                error: function() {
                    WPGmailAdmin.showNotice('error', 'Network error occurred.');
                }
            });
        },

        /**
         * メールモーダル表示
         */
        showEmailModal: function(emailData) {
            var $modal = $('#email-modal');

            $modal.find('#modal-subject').text(emailData.subject || '(No Subject)');
            $modal.find('#modal-from').text(emailData.from_name + ' <' + emailData.from_email + '>');
            $modal.find('#modal-to').text(emailData.to_email);
            $modal.find('#modal-date').text(emailData.received_date);
            $modal.find('#modal-body').html(emailData.body);

            $modal.fadeIn(400);
        },

        /**
         * メールを既読にマーク
         */
        markAsRead: function(messageId, $emailItem) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gmail_mark_email',
                    nonce: wpGmailPluginAdmin.nonce,
                    message_id: messageId,
                    email_action: 'read'
                },
                success: function(response) {
                    if (response.success) {
                        $emailItem.removeClass('unread').addClass('read');
                    }
                }
            });
        },

        /**
         * 通知表示
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);

            // 自動で消す
            setTimeout(function() {
                $notice.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 5000);

            // 手動で消すボタン
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(400, function() {
                    $(this).remove();
                });
            });
        },

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
        }
    };

    // DOM読み込み完了後に初期化
    $(document).ready(function() {
        WPGmailAdmin.init();
    });

    // グローバルに公開
    window.WPGmailAdmin = WPGmailAdmin;

})(jQuery);
