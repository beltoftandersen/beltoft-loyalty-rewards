/**
 * WooCommerce Loyalty Points - Redemption AJAX
 *
 * After apply/remove, the form HTML is swapped for instant visual feedback,
 * then the page reloads to ensure cart totals are fully consistent.
 */
(function ($) {
    'use strict';

    if (typeof wclr_redeem === 'undefined') {
        return;
    }

    var i18n = wclr_redeem.i18n || {};

    function getAjaxUrl(endpoint) {
        return wclr_redeem.ajax_url.replace('%%endpoint%%', endpoint);
    }

    function showNotice(message, type) {
        var $notice = $('#wclr-redeem-notice');
        if (!$notice.length) return;
        $notice
            .removeClass('wclr-redeem-notice--error wclr-redeem-notice--success')
            .addClass('wclr-redeem-notice--' + type)
            .text(message)
            .show();
    }

    function hideNotice() {
        $('#wclr-redeem-notice').hide().removeClass('wclr-redeem-notice--error wclr-redeem-notice--success');
    }

    // Apply points.
    $(document.body).on('click', '#wclr-apply-points', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var points = parseInt($('#wclr-points-input').val(), 10);

        if (!points || points <= 0) {
            showNotice(i18n.invalid_points || 'Please enter a valid number of points.', 'error');
            return;
        }

        hideNotice();
        $btn.prop('disabled', true).addClass('loading');

        $.post(getAjaxUrl('wclr_apply_points'), {
            security: wclr_redeem.nonce,
            points: points
        })
        .done(function (response) {
            if (response.success) {
                location.reload();
            } else {
                showNotice(
                    response.data && response.data.message ? response.data.message : (i18n.apply_error || 'Error applying points.'),
                    'error'
                );
                $btn.prop('disabled', false).removeClass('loading');
            }
        })
        .fail(function () {
            showNotice(i18n.request_failed || 'Request failed. Please try again.', 'error');
            $btn.prop('disabled', false).removeClass('loading');
        });
    });

    // When WC removes our virtual coupon via its own [Remove] link in cart
    // totals, hide the "Coupon removed" notice and reload so the form updates.
    $(document.body).on('removed_coupon removed_coupon_in_checkout', function (e, coupon) {
        if (coupon === 'wclr-loyalty-discount') {
            $('.woocommerce-message, .woocommerce-info, .woocommerce-error').hide();
            location.reload();
        }
    });

    // Remove points (button inside our redeem form).
    $(document.body).on('click', '#wclr-remove-points', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var removeLabel = i18n.remove || 'Remove';

        hideNotice();
        $btn.prop('disabled', true).text(i18n.removing || 'Removing\u2026');

        $.post(getAjaxUrl('wclr_remove_points'), {
            security: wclr_redeem.nonce
        })
        .done(function (response) {
            if (response.success) {
                location.reload();
            } else {
                $btn.prop('disabled', false).text(removeLabel);
            }
        })
        .fail(function () {
            $btn.prop('disabled', false).text(removeLabel);
        });
    });

})(jQuery);
