/**
 * Travel Map Settings Page Script
 * 设置页面交互脚本
 */

(function($) {
    'use strict';

    const cfg = window.travelMapSettings || {};
    const i18n = cfg.i18n || {};

    function showCopyMessage(type, message) {
        $('.travel-map-copy-message').remove();
        const $message = $('<div class="travel-map-copy-message ' + type + '">' + message + '</div>');
        $('body').append($message);

        setTimeout(() => {
            $message.fadeOut(() => {
                $message.remove();
            });
        }, type === 'success' ? 2000 : 3000);
    }

    function fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopyMessage('success', i18n.copySuccess || '✓ 代码已复制到剪贴板');
            } else {
                showCopyMessage('error', i18n.copyError || '✗ 复制失败，请手动复制');
            }
        } catch (err) {
            showCopyMessage('error', i18n.copyError || '✗ 复制失败，请手动复制');
        }

        document.body.removeChild(textArea);
    }

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                showCopyMessage('success', i18n.copySuccess || '✓ 代码已复制到剪贴板');
            }).catch(() => {
                fallbackCopyTextToClipboard(text);
            });
        } else {
            fallbackCopyTextToClipboard(text);
        }
    }

    $(document).on('click', '.travel-map-copy-btn', function(e) {
        e.preventDefault();
        const textToCopy = $(this).attr('data-copy');
        if (textToCopy) {
            copyToClipboard(textToCopy);
        }
    });
})(jQuery);
