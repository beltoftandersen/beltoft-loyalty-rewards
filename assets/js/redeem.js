/**
 * WooCommerce Loyalty Points - Redemption AJAX
 *
 * After apply/remove, the form is swapped for the one the server sends back
 * (form_html) and the totals are refreshed in place, the way WooCommerce does
 * for a coupon: 'update_checkout' on the checkout, 'wc_update_cart' on the
 * classic cart. Only where neither is on the page does it fall back to a full
 * reload, so the totals shown are never stale.
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
        var $notice = $('#blrw-redeem-notice');
        if (!$notice.length) return;
        $notice
            .removeClass('blrw-redeem-notice--error blrw-redeem-notice--success')
            .addClass('blrw-redeem-notice--' + type)
            .text(message)
            .show();
    }

    function hideNotice() {
        $('#blrw-redeem-notice').hide().removeClass('blrw-redeem-notice--error blrw-redeem-notice--success');
    }

    /**
     * Put the server's fresh form in place of the current one. An empty
     * form_html (nothing left to show) removes the form.
     */
    function swapForm(html) {
        var $form = $('#blrw-redeem-form');
        if (!$form.length || typeof html !== 'string') return false;
        if ($.trim(html) === '') {
            $form.remove();
        } else {
            $form.replaceWith(html);
        }
        return true;
    }

    /**
     * Recalculate the totals without leaving the page. Returns false when
     * there is nothing on the page that knows how to refresh itself.
     */
    function refreshTotals() {
        if ($('form.checkout, form.woocommerce-checkout').length) {
            $(document.body).trigger('update_checkout');
            return true;
        }
        if ($('.woocommerce-cart-form').length) {
            $(document.body).trigger('wc_update_cart');
            return true;
        }
        return false;
    }

    function done(response) {
        var swapped = swapForm(response.data && response.data.form_html);
        if (!swapped || !refreshTotals()) {
            location.reload();
        }
    }

    // Apply points.
    $(document.body).on('click', '#blrw-apply-points', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var points = parseInt($('#blrw-points-input').val(), 10);

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
                done(response);
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

    // Enter in the points field applies, instead of submitting the checkout.
    $(document.body).on('keydown', '#blrw-points-input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#blrw-apply-points').trigger('click');
        }
    });

    // When WC removes our virtual coupon via its own [Remove] link in the
    // totals, WooCommerce already refreshes the totals; hide its "Coupon
    // removed" notice and fetch the form so it no longer shows "applied".
    $(document.body).on('removed_coupon removed_coupon_in_checkout', function (e, coupon) {
        if (coupon !== 'blrw-loyalty-discount') return;
        $('.woocommerce-message, .woocommerce-info, .woocommerce-error').hide();

        $.post(getAjaxUrl('wclr_remove_points'), {
            security: wclr_redeem.nonce
        })
        .done(function (response) {
            if (!response.success || !swapForm(response.data && response.data.form_html)) {
                location.reload();
            }
        })
        .fail(function () {
            location.reload();
        });
    });

    // Remove points (button inside our redeem form).
    $(document.body).on('click', '#blrw-remove-points', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var removeLabel = i18n.remove || 'Remove';

        hideNotice();
        $btn.prop('disabled', true).text(i18n.removing || 'Removing…');

        $.post(getAjaxUrl('wclr_remove_points'), {
            security: wclr_redeem.nonce
        })
        .done(function (response) {
            if (response.success) {
                done(response);
            } else {
                $btn.prop('disabled', false).text(removeLabel);
            }
        })
        .fail(function () {
            $btn.prop('disabled', false).text(removeLabel);
        });
    });

})(jQuery);
