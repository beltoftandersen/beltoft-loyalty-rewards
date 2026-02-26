<?php

namespace LoyaltyRewards\WooCommerce;

use LoyaltyRewards\Support\Options;
use LoyaltyRewards\Support\Logger;
use LoyaltyRewards\Core\Calculator;
use LoyaltyRewards\Core\PointsService;

defined( 'ABSPATH' ) || exit;

class RedeemHooks {

    const COUPON_CODE = 'wclr-loyalty-discount';

    public static function init() {
        // Virtual coupon data.
        add_filter( 'woocommerce_get_shop_coupon_data', [ __CLASS__, 'virtual_coupon_data' ], 10, 2 );

        // Coupon label.
        add_filter( 'woocommerce_cart_totals_coupon_label', [ __CLASS__, 'coupon_label' ], 10, 2 );

        // Prevent "coupon doesn't exist" notice.
        add_filter( 'woocommerce_coupon_is_valid', [ __CLASS__, 'validate_coupon' ], 10, 2 );

        // Render redemption form (logged-in) or guest message on cart / checkout.
        if ( Options::get( 'redeem_show_on_cart' ) === '1' ) {
            add_action( 'woocommerce_before_cart_totals', [ __CLASS__, 'render_redeem_form' ] );
            add_action( 'woocommerce_before_cart_totals', [ __CLASS__, 'render_guest_message' ] );
        }
        if ( Options::get( 'redeem_show_on_checkout' ) === '1' ) {
            add_action( 'woocommerce_review_order_before_payment', [ __CLASS__, 'render_redeem_form' ] );
            add_action( 'woocommerce_review_order_before_payment', [ __CLASS__, 'render_guest_message' ] );
        }

        // Custom hook for themes/plugins to place the form anywhere.
        add_action( 'wclr_redeem_form', [ __CLASS__, 'render_redeem_form' ] );

        // AJAX handlers.
        add_action( 'wc_ajax_wclr_apply_points', [ __CLASS__, 'ajax_apply_points' ] );
        add_action( 'wc_ajax_wclr_remove_points', [ __CLASS__, 'ajax_remove_points' ] );

        // Persist pending redemption to order meta at checkout (before payment).
        add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'save_pending_redeem' ] );

        // Finalize on payment complete (not checkout_order_processed, which fires before payment).
        add_action( 'woocommerce_payment_complete', [ __CLASS__, 'finalize_redemption' ] );

        // Fallback for offline/manual flows (COD, BACS, admin status change) where
        // woocommerce_payment_complete never fires. Idempotency guard in
        // finalize_redemption() prevents double-debit.
        add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'finalize_redemption' ] );
        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'finalize_redemption' ] );

        // Clear session on coupon removal.
        add_action( 'woocommerce_removed_coupon', [ __CLASS__, 'on_coupon_removed' ] );
    }

    /**
     * Supply virtual coupon data so WooCommerce treats our code as valid.
     */
    public static function virtual_coupon_data( $data, $code ) {
        if ( strtolower( $code ) !== self::COUPON_CODE ) {
            return $data;
        }

        $session_points = self::get_session_points();
        if ( $session_points <= 0 ) {
            return $data;
        }

        $discount = Calculator::discount_for_points( $session_points );

        return [
            'id'                         => 0,
            'amount'                     => $discount,
            'discount_type'              => 'fixed_cart',
            'individual_use'             => false,
            'usage_limit'                => 0,
            'usage_count'                => 0,
            'date_created'               => '',
            'date_modified'              => '',
            'date_expires'               => null,
            'free_shipping'              => false,
            'product_ids'                => [],
            'excluded_product_ids'       => [],
            'product_categories'         => [],
            'excluded_product_categories' => [],
            'exclude_sale_items'         => false,
            'minimum_amount'             => '',
            'maximum_amount'             => '',
            'email_restrictions'         => [],
            'virtual'                    => true,
        ];
    }

    /**
     * Show friendly label instead of coupon code.
     */
    public static function coupon_label( $label, $coupon ) {
        if ( strtolower( $coupon->get_code() ) === self::COUPON_CODE ) {
            $points = self::get_session_points();
            return sprintf(
                /* translators: %s: points being redeemed */
                __( 'Points Redemption (%s pts)', 'beltoft-loyalty-rewards' ),
                number_format_i18n( $points )
            );
        }
        return $label;
    }

    /**
     * Validate virtual coupon.
     */
    public static function validate_coupon( $valid, $coupon ) {
        if ( strtolower( $coupon->get_code() ) === self::COUPON_CODE ) {
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                return false;
            }

            $balance = PointsService::get_balance( $user_id );
            $points  = self::get_session_points();

            return $points > 0 && $points <= $balance;
        }
        return $valid;
    }

    /**
     * Render the points redemption form.
     *
     * Automatically outputs on cart/checkout (if enabled in settings).
     * Can also be triggered anywhere via: do_action( 'wclr_redeem_form' );
     */
    public static function render_redeem_form() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Ensure assets are loaded when the form is rendered via custom hook.
        self::maybe_enqueue_assets();

        $user_id        = get_current_user_id();
        $balance        = PointsService::get_balance( $user_id );
        $cart_total     = self::get_cart_subtotal();
        $points_to_earn = Calculator::points_for_amount( $cart_total );

        // Nothing to show if user has no balance and no points to earn.
        if ( $balance <= 0 && $points_to_earn <= 0 ) {
            return;
        }

        $max_points     = $balance > 0 ? Calculator::max_redeemable_points( $cart_total, $balance ) : 0;
        $session_points = self::get_session_points();
        $is_applied     = $session_points > 0;
        $can_redeem     = $balance > 0 && ( $max_points > 0 || $is_applied );

        $max_discount   = $max_points > 0 ? Calculator::discount_for_points( $max_points ) : 0;
        $min_points     = max( 1, (int) Options::get( 'redeem_min_points' ) );
        $max_percent    = (int) Options::get( 'redeem_max_percent' );
        ?>
        <div class="wclr-redeem-form" id="wclr-redeem-form">
            <div class="wclr-redeem-header">
                <span class="wclr-redeem-title"><?php esc_html_e( 'Loyalty Points', 'beltoft-loyalty-rewards' ); ?></span>
                <span class="wclr-redeem-balance"><?php echo esc_html( number_format_i18n( $balance ) ); ?> <?php esc_html_e( 'pts', 'beltoft-loyalty-rewards' ); ?></span>
            </div>

            <?php if ( $points_to_earn > 0 ) : ?>
                <p class="wclr-redeem-earn">
                    <?php
                    printf(
                        /* translators: %s: number of points */
                        wp_kses( __( 'Place this order and earn <strong>%s</strong> points', 'beltoft-loyalty-rewards' ), [ 'strong' => [] ] ),
                        esc_html( number_format_i18n( $points_to_earn ) )
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php
            /**
             * Fires inside the redeem form after the earn estimate.
             * Used by Pro to render tier badge and campaign banner.
             */
            do_action( 'wclr_redeem_form_after_earn', $user_id, $points_to_earn );
            ?>

            <?php if ( $is_applied ) : ?>
                <div class="wclr-redeem-applied">
                    <div class="wclr-redeem-applied__info">
                        <span class="wclr-redeem-applied__label"><?php esc_html_e( 'Points discount applied', 'beltoft-loyalty-rewards' ); ?></span>
                        <span class="wclr-redeem-applied__detail">
                            <?php
                            printf(
                                /* translators: 1: points, 2: discount amount */
                                esc_html__( '%1$s pts = %2$s off', 'beltoft-loyalty-rewards' ),
                                esc_html( number_format_i18n( $session_points ) ),
                                wp_kses_post( wc_price( Calculator::discount_for_points( $session_points ) ) )
                            );
                            ?>
                        </span>
                    </div>
                    <button type="button" class="wclr-redeem-remove" id="wclr-remove-points">
                        <?php esc_html_e( 'Remove', 'beltoft-loyalty-rewards' ); ?>
                    </button>
                </div>
            <?php elseif ( $can_redeem ) : ?>
                <p class="wclr-redeem-available">
                    <?php
                    printf(
                        /* translators: 1: max points, 2: max discount */
                        wp_kses( __( 'Redeem your points for up to <strong>%1$s</strong> off this order (%2$s points)', 'beltoft-loyalty-rewards' ), [ 'strong' => [] ] ),
                        wp_kses_post( wc_price( $max_discount ) ),
                        esc_html( number_format_i18n( $max_points ) )
                    );
                    ?>
                </p>
                <div class="wclr-redeem-input-row">
                    <label for="wclr-points-input" class="screen-reader-text">
                        <?php esc_html_e( 'Points to redeem', 'beltoft-loyalty-rewards' ); ?>
                    </label>
                    <input type="number" id="wclr-points-input" name="wclr_points"
                           class="input-text"
                           placeholder="<?php esc_attr_e( 'Points to redeem', 'beltoft-loyalty-rewards' ); ?>"
                           min="<?php echo esc_attr( $min_points ); ?>"
                           max="<?php echo esc_attr( $max_points ); ?>"
                           value="<?php echo esc_attr( $max_points ); ?>"
                           step="1" />
                    <button type="button" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?> wclr-apply-points" id="wclr-apply-points">
                        <?php esc_html_e( 'Apply', 'beltoft-loyalty-rewards' ); ?>
                    </button>
                </div>
                <?php if ( $min_points > 1 && $max_percent < 100 ) : ?>
                    <span class="wclr-redeem-hint">
                        <?php
                        printf(
                            /* translators: 1: minimum points, 2: max percentage */
                            esc_html__( 'Min %1$s pts · Max %2$s%% of cart', 'beltoft-loyalty-rewards' ),
                            esc_html( number_format_i18n( $min_points ) ),
                            esc_html( $max_percent )
                        );
                        ?>
                    </span>
                <?php elseif ( $min_points > 1 ) : ?>
                    <span class="wclr-redeem-hint">
                        <?php
                        printf(
                            /* translators: %s: minimum points */
                            esc_html__( 'Min %s pts', 'beltoft-loyalty-rewards' ),
                            esc_html( number_format_i18n( $min_points ) )
                        );
                        ?>
                    </span>
                <?php elseif ( $max_percent < 100 ) : ?>
                    <span class="wclr-redeem-hint">
                        <?php
                        printf(
                            /* translators: %s: max percentage */
                            esc_html__( 'Max %s%% of cart', 'beltoft-loyalty-rewards' ),
                            esc_html( $max_percent )
                        );
                        ?>
                    </span>
                <?php endif; ?>
            <?php endif; ?>

            <div class="wclr-redeem-notice" id="wclr-redeem-notice" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * Render a sign-up encouragement box for guests on cart/checkout.
     */
    public static function render_guest_message() {
        if ( is_user_logged_in() ) {
            return;
        }

        if ( Options::get( 'enabled' ) !== '1' ) {
            return;
        }

        $cart_total = self::get_cart_subtotal();
        if ( $cart_total <= 0 ) {
            return;
        }

        $points = Calculator::points_for_amount( $cart_total );
        if ( $points <= 0 ) {
            return;
        }

        // Calculate monetary value of points.
        $redeem_rate_points   = max( 1, (int) Options::get( 'redeem_rate_points' ) );
        $redeem_rate_currency = (float) Options::get( 'redeem_rate_currency' );
        $value                = ( $points / $redeem_rate_points ) * $redeem_rate_currency;

        $signup_url = Options::get_signup_url();

        wp_enqueue_style( 'wclr-frontend', WCLR_URL . 'assets/css/frontend.css', [], WCLR_VER );

        /**
         * Filter the guest points message parts.
         *
         * @param array $parts {
         *     @type int    $points     Points to earn.
         *     @type float  $value      Monetary value of points.
         *     @type string $signup_url Sign-up page URL.
         *     @type string $extra      Extra message (e.g. campaign info from Pro).
         * }
         * @param float $cart_total Cart subtotal.
         */
        $parts = apply_filters( 'wclr_guest_points_message_parts', [
            'points'     => $points,
            'value'      => $value,
            'signup_url' => $signup_url,
            'extra'      => '',
        ], $cart_total );

        $points     = $parts['points'];
        $value      = $parts['value'];
        $signup_url = $parts['signup_url'];
        $extra      = $parts['extra'];

        ?>
        <div class="wclr-redeem-form wclr-guest-message">
            <div class="wclr-redeem-header">
                <span class="wclr-redeem-title"><?php esc_html_e( 'Loyalty Points', 'beltoft-loyalty-rewards' ); ?></span>
            </div>
            <p class="wclr-guest-message__text">
                <?php
                printf(
                    wp_kses(
                        /* translators: 1: opening link tag, 2: closing link tag, 3: points, 4: monetary value */
                        __( 'Log in or %1$screate an account%2$s to earn <strong>%3$s points</strong> (worth %4$s) on this order!', 'beltoft-loyalty-rewards' ),
                        [ 'a' => [ 'href' => [] ], 'strong' => [] ]
                    ),
                    '<a href="' . esc_url( $signup_url ) . '">',
                    '</a>',
                    esc_html( number_format_i18n( $points ) ),
                    wp_kses_post( wc_price( $value ) )
                );
                ?>
            </p>
            <?php if ( $extra ) : ?>
                <p class="wclr-guest-message__extra"><?php echo wp_kses_post( $extra ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Validate and apply points to the cart session.
     *
     * Shared by AJAX, Store API, and REST handlers.
     *
     * @return int|\WP_Error Final points applied, or WP_Error on failure.
     */
    public static function apply_points_to_cart( int $user_id, int $points ) {
        $balance = PointsService::get_balance( $user_id );

        if ( $points <= 0 || $points > $balance ) {
            return new \WP_Error( 'invalid_points', __( 'Invalid points amount.', 'beltoft-loyalty-rewards' ) );
        }

        $cart_total = (float) WC()->cart->get_subtotal();
        $max_points = Calculator::max_redeemable_points( $cart_total, $balance );
        $points     = min( $points, $max_points );

        $min_points = max( 1, (int) Options::get( 'redeem_min_points' ) );
        if ( $points < $min_points ) {
            return new \WP_Error(
                'below_minimum',
                sprintf(
                    /* translators: %s: minimum points */
                    __( 'Minimum %s points required to redeem.', 'beltoft-loyalty-rewards' ),
                    number_format_i18n( $min_points )
                )
            );
        }

        WC()->session->set( 'wclr_redeem_points', $points );

        if ( ! WC()->cart->has_discount( self::COUPON_CODE ) ) {
            WC()->cart->apply_coupon( self::COUPON_CODE );
        } else {
            WC()->cart->calculate_totals();
        }

        return $points;
    }

    /**
     * Remove points from the cart session.
     *
     * Shared by AJAX, Store API, and REST handlers.
     */
    public static function remove_points_from_cart(): void {
        if ( WC()->session ) {
            WC()->session->set( 'wclr_redeem_points', 0 );
        }
        if ( WC()->cart ) {
            WC()->cart->remove_coupon( self::COUPON_CODE );
        }
    }

    /**
     * AJAX: Apply points.
     */
    public static function ajax_apply_points() {
        check_ajax_referer( 'wclr-redeem', 'security' );

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            Logger::debug( '[RedeemHooks] apply_points rejected — not logged in' );
            wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'beltoft-loyalty-rewards' ) ] );
        }

        $points = isset( $_POST['points'] ) ? absint( $_POST['points'] ) : 0;
        Logger::debug( sprintf( '[RedeemHooks] apply_points — user #%d requested %d pts', $user_id, $points ) );

        $result = self::apply_points_to_cart( $user_id, $points );

        if ( is_wp_error( $result ) ) {
            Logger::debug( sprintf( '[RedeemHooks] apply_points rejected — %s', $result->get_error_message() ) );
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        Logger::debug( sprintf( '[RedeemHooks] Applied %d pts for user #%d', $result, $user_id ) );
        wc_clear_notices();

        ob_start();
        self::render_redeem_form();
        $form_html = ob_get_clean();

        wp_send_json_success( [
            'message'   => __( 'Points applied!', 'beltoft-loyalty-rewards' ),
            'points'    => $result,
            'discount'  => Calculator::discount_for_points( $result ),
            'form_html' => $form_html,
        ] );
    }

    /**
     * AJAX: Remove points.
     */
    public static function ajax_remove_points() {
        check_ajax_referer( 'wclr-redeem', 'security' );

        Logger::debug( sprintf( '[RedeemHooks] remove_points — user #%d', get_current_user_id() ) );

        self::remove_points_from_cart();
        wc_clear_notices();

        ob_start();
        self::render_redeem_form();
        $form_html = ob_get_clean();

        wp_send_json_success( [
            'message'   => __( 'Points discount removed.', 'beltoft-loyalty-rewards' ),
            'form_html' => $form_html,
        ] );
    }

    /**
     * When the virtual coupon is removed via WC UI, clear session.
     */
    public static function on_coupon_removed( $code ) {
        if ( strtolower( $code ) === self::COUPON_CODE && WC()->session ) {
            WC()->session->set( 'wclr_redeem_points', 0 );
        }
    }

    /**
     * Save pending redemption points to order meta at checkout time.
     * This ensures the data survives for async payment gateways where
     * the WC session may no longer be available at payment_complete.
     *
     * @param \WC_Order $order
     */
    public static function save_pending_redeem( $order ) {
        $points = self::get_session_points();
        if ( $points > 0 ) {
            $order->update_meta_data( '_wclr_pending_redeem_points', $points );
            $order->save();
        }
    }

    /**
     * Finalize redemption after payment: debit points + set order meta.
     *
     * Hooked on woocommerce_payment_complete so points are only debited
     * after the payment gateway confirms success.
     *
     * @param int $order_id
     */
    public static function finalize_redemption( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check session first; fall back to order meta for off-session payments (e.g. async gateways).
        $points = self::get_session_points();
        if ( $points <= 0 ) {
            $points = (int) $order->get_meta( '_wclr_pending_redeem_points' );
        }
        if ( $points <= 0 ) {
            return;
        }

        // Idempotency: don't double-debit.
        if ( $order->get_meta( '_wclr_points_spent' ) ) {
            return;
        }

        $user_id = $order->get_customer_id();
        if ( ! $user_id ) {
            return;
        }

        $discount = Calculator::discount_for_points( $points );

        Logger::debug( sprintf( '[RedeemHooks] Finalizing redemption — user #%d, %d pts, discount: %.2f, order #%d', $user_id, $points, $discount, $order_id ) );

        PointsService::debit(
            $user_id,
            $points,
            'redeem',
            sprintf(
                /* translators: %s: order number */
                __( 'Points redeemed for order #%s', 'beltoft-loyalty-rewards' ),
                $order->get_order_number()
            ),
            $order->get_id()
        );

        $order->update_meta_data( '_wclr_points_spent', $points );
        $order->update_meta_data( '_wclr_discount_amount', wc_format_decimal( $discount ) );
        $order->save();

        do_action( 'wclr_points_redeemed', $user_id, $points, $discount, $order );

        // Clear session (may be null in webhook/cron context).
        if ( WC()->session ) {
            WC()->session->set( 'wclr_redeem_points', 0 );
        }
    }

    /**
     * Get the points stored in the WC session.
     */
    private static function get_session_points() {
        if ( ! WC()->session ) {
            return 0;
        }
        return (int) WC()->session->get( 'wclr_redeem_points', 0 );
    }

    /**
     * Get cart subtotal for calculations (excluding tax and shipping).
     */
    private static function get_cart_subtotal() {
        if ( ! WC()->cart ) {
            return 0;
        }
        return (float) WC()->cart->get_subtotal();
    }

    /**
     * Shortcode [blr_redeem_form] - renders the redeem form.
     */
    public static function shortcode_redeem_form() {
        ob_start();
        self::render_redeem_form();
        return ob_get_clean();
    }

    /**
     * Ensure redeem CSS + JS are loaded when the form renders via custom hook.
     */
    private static function maybe_enqueue_assets() {
        if ( wp_script_is( 'wclr-redeem', 'enqueued' ) ) {
            return;
        }

        wp_enqueue_style( 'wclr-frontend', WCLR_URL . 'assets/css/frontend.css', [], WCLR_VER );
        wp_enqueue_script( 'wclr-redeem', WCLR_URL . 'assets/js/redeem.js', [ 'jquery' ], WCLR_VER, true );
        wp_localize_script( 'wclr-redeem', 'wclr_redeem', [
            'ajax_url' => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
            'nonce'    => wp_create_nonce( 'wclr-redeem' ),
            'i18n'     => [
                'invalid_points' => __( 'Please enter a valid number of points.', 'beltoft-loyalty-rewards' ),
                'apply_error'    => __( 'Error applying points.', 'beltoft-loyalty-rewards' ),
                'request_failed' => __( 'Request failed. Please try again.', 'beltoft-loyalty-rewards' ),
                'removing'       => __( 'Removing…', 'beltoft-loyalty-rewards' ),
                'remove'         => __( 'Remove', 'beltoft-loyalty-rewards' ),
            ],
        ] );
    }
}
