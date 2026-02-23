<?php

namespace LoyaltyRewards\WooCommerce;

use LoyaltyRewards\Support\Options;
use LoyaltyRewards\Support\Logger;
use LoyaltyRewards\Core\Calculator;
use LoyaltyRewards\Core\PointsService;

defined( 'ABSPATH' ) || exit;

class EarnHooks {

    public static function init() {
        $status = Options::get( 'award_on_status' );

        // Award points when order reaches target status.
        add_action( "woocommerce_order_status_{$status}", [ __CLASS__, 'maybe_award_points' ], 10, 2 );

        // Reverse points on cancel/refund/failed.
        add_action( 'woocommerce_order_status_cancelled', [ __CLASS__, 'maybe_reverse_points' ], 10, 2 );
        add_action( 'woocommerce_order_status_refunded', [ __CLASS__, 'maybe_reverse_points' ], 10, 2 );
        add_action( 'woocommerce_order_status_failed', [ __CLASS__, 'maybe_reverse_points' ], 10, 2 );
    }

    /**
     * Award points when order reaches the configured status.
     *
     * @param int            $order_id
     * @param \WC_Order|null $order
     */
    public static function maybe_award_points( $order_id, $order = null ) {
        if ( Options::get( 'enabled' ) !== '1' ) {
            Logger::debug( sprintf( '[EarnHooks] Skipping order #%d — loyalty program disabled', $order_id ) );
            return;
        }

        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            return;
        }

        // Idempotency: don't double-award.
        if ( $order->get_meta( '_wclr_points_awarded' ) === 'yes' ) {
            Logger::debug( sprintf( '[EarnHooks] Skipping order #%d — points already awarded', $order_id ) );
            return;
        }

        $user_id = $order->get_customer_id();
        if ( ! $user_id ) {
            Logger::debug( sprintf( '[EarnHooks] Skipping order #%d — guest order', $order_id ) );
            return;
        }

        $points = Calculator::points_for_order( $order );
        if ( $points <= 0 ) {
            Logger::debug( sprintf( '[EarnHooks] Skipping order #%d — calculated 0 points', $order_id ) );
            return;
        }

        PointsService::credit(
            $user_id,
            $points,
            'earn',
            sprintf(
                /* translators: %s: order number */
                __( 'Points earned for order #%s', 'beltoft-loyalty-rewards' ),
                $order->get_order_number()
            ),
            $order->get_id()
        );

        $order->update_meta_data( '_wclr_points_awarded', 'yes' );
        $order->update_meta_data( '_wclr_points_earned', $points );
        $order->save();

        Logger::debug( sprintf( '[EarnHooks] Awarded %d pts to user #%d for order #%d', $points, $user_id, $order_id ) );

        do_action( 'wclr_points_earned', $user_id, $points, $order );
    }

    /**
     * Reverse points when order is cancelled or fully refunded.
     *
     * @param int            $order_id
     * @param \WC_Order|null $order
     */
    public static function maybe_reverse_points( $order_id, $order = null ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
            return;
        }

        // Already reversed?
        if ( $order->get_meta( '_wclr_points_reversed' ) === 'yes' ) {
            Logger::debug( sprintf( '[EarnHooks] Skipping reversal for order #%d — already reversed', $order_id ) );
            return;
        }

        $user_id = $order->get_customer_id();
        if ( ! $user_id ) {
            Logger::debug( sprintf( '[EarnHooks] Skipping reversal for order #%d — guest order', $order_id ) );
            return;
        }

        // Reverse earned points.
        $earned = (int) $order->get_meta( '_wclr_points_earned' );
        if ( $earned > 0 ) {
            Logger::debug( sprintf( '[EarnHooks] Reversing %d earned pts for user #%d, order #%d', $earned, $user_id, $order_id ) );
            PointsService::debit(
                $user_id,
                $earned,
                'refund_reversal',
                sprintf(
                    /* translators: %s: order number */
                    __( 'Points reversed for cancelled/refunded order #%s', 'beltoft-loyalty-rewards' ),
                    $order->get_order_number()
                ),
                $order->get_id()
            );
        }

        // Restore spent points (if any were redeemed).
        $spent = (int) $order->get_meta( '_wclr_points_spent' );
        if ( $spent > 0 ) {
            Logger::debug( sprintf( '[EarnHooks] Restoring %d redeemed pts for user #%d, order #%d', $spent, $user_id, $order_id ) );
            PointsService::credit(
                $user_id,
                $spent,
                'refund_reversal',
                sprintf(
                    /* translators: %s: order number */
                    __( 'Redeemed points restored for cancelled/refunded order #%s', 'beltoft-loyalty-rewards' ),
                    $order->get_order_number()
                ),
                $order->get_id()
            );
        }

        $order->update_meta_data( '_wclr_points_reversed', 'yes' );
        $order->save();

        Logger::debug( sprintf( '[EarnHooks] Reversal complete for order #%d (earned: %d, spent: %d)', $order_id, $earned, $spent ) );
    }
}
