<?php

namespace LoyaltyRewards\WooCommerce;

defined( 'ABSPATH' ) || exit;

class OrderAdmin {

    public static function init() {
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'display_order_points' ] );
    }

    /**
     * Display points info on the admin order edit screen.
     *
     * @param \WC_Order $order
     */
    public static function display_order_points( $order ) {
        $earned   = (int) $order->get_meta( '_wclr_points_earned' );
        $spent    = (int) $order->get_meta( '_wclr_points_spent' );
        $discount = $order->get_meta( '_wclr_discount_amount' );
        $reversed = $order->get_meta( '_wclr_points_reversed' ) === 'yes';

        if ( ! $earned && ! $spent ) {
            return;
        }
        ?>
        <div class="wclr-order-info" style="margin-top:12px;padding:8px 12px;background:#f8f8f8;border-left:3px solid #7b2d8a;">
            <h4 style="margin:0 0 6px;"><?php esc_html_e( 'Loyalty Points', 'beltoft-loyalty-rewards' ); ?></h4>

            <?php if ( $earned ) : ?>
                <p style="margin:2px 0;">
                    <?php
                    printf(
                        /* translators: %s: points earned */
                        wp_kses( __( 'Points Earned: %s', 'beltoft-loyalty-rewards' ), [ 'strong' => [] ] ),
                        '<strong>' . esc_html( number_format_i18n( $earned ) ) . '</strong>'
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if ( $spent ) : ?>
                <p style="margin:2px 0;">
                    <?php
                    printf(
                        /* translators: %s: points spent */
                        wp_kses( __( 'Points Spent: %s', 'beltoft-loyalty-rewards' ), [ 'strong' => [] ] ),
                        '<strong>' . esc_html( number_format_i18n( $spent ) ) . '</strong>'
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if ( $discount ) : ?>
                <p style="margin:2px 0;">
                    <?php
                    printf(
                        /* translators: %s: discount amount */
                        wp_kses( __( 'Loyalty Discount: %s', 'beltoft-loyalty-rewards' ), [ 'strong' => [] ] ),
                        '<strong>' . wp_kses_post( wc_price( $discount ) ) . '</strong>'
                    );
                    ?>
                </p>
            <?php endif; ?>

            <?php if ( $reversed ) : ?>
                <p style="margin:2px 0;color:#b32d2e;">
                    <em><?php esc_html_e( 'Points have been reversed (order cancelled/refunded).', 'beltoft-loyalty-rewards' ); ?></em>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
