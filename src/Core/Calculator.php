<?php

namespace LoyaltyRewards\Core;

use LoyaltyRewards\Support\Options;

defined( 'ABSPATH' ) || exit;

class Calculator {

    /**
     * Calculate points earned for an order.
     *
     * @param \WC_Order $order
     * @return int Points to award.
     */
    public static function points_for_order( $order ) {
        $opts = Options::get();

        $total = (float) $order->get_total();

        // Subtract tax if excluded.
        if ( $opts['exclude_tax'] === '1' ) {
            $total -= (float) $order->get_total_tax();
        }

        // Subtract shipping if excluded.
        if ( $opts['exclude_shipping'] === '1' ) {
            $total -= (float) $order->get_shipping_total();
            if ( $opts['exclude_tax'] === '1' ) {
                $total -= (float) $order->get_shipping_tax();
            }
        }

        // Subtract all coupon discounts (includes the loyalty virtual coupon if active,
        // so no separate _wclr_discount_amount subtraction needed — that would double-count).
        $total -= (float) $order->get_discount_total();

        $total = max( 0, $total );

        $earn_rate = (float) apply_filters( 'wclr_earn_rate', $opts['earn_rate'] );
        $points    = self::round_points( $total * $earn_rate );

        return (int) apply_filters( 'wclr_points_for_order', max( 0, $points ), $order );
    }

    /**
     * Calculate points for a given amount (product page / cart display).
     *
     * The `wclr_points_for_amount` filter lets add-ons (tiers, campaigns)
     * adjust the displayed estimate so it matches what the customer will
     * actually receive when the order is processed.
     */
    public static function points_for_amount( $amount ) {
        $opts      = Options::get();
        $earn_rate = (float) apply_filters( 'wclr_earn_rate', $opts['earn_rate'] );
        $points    = self::round_points( (float) $amount * $earn_rate );

        return (int) apply_filters( 'wclr_points_for_amount', max( 0, $points ), $amount );
    }

    /**
     * Round a raw points value using the admin-configured rounding mode.
     *
     * Exposed as a public helper so add-ons (tiers, campaigns) can apply
     * the same rounding when multiplying points.
     *
     * @param float $raw Raw (unrounded) points value.
     * @return int Rounded points.
     */
    public static function round_points( float $raw ): int {
        switch ( Options::get( 'rounding' ) ) {
            case 'ceil':
                return (int) ceil( $raw );
            case 'round':
                return (int) round( $raw );
            case 'floor':
            default:
                return (int) floor( $raw );
        }
    }

    /**
     * Calculate the currency discount for a given number of points.
     */
    public static function discount_for_points( $points ) {
        $opts            = Options::get();
        $rate_points     = max( 1, (int) $opts['redeem_rate_points'] );
        $rate_currency   = (float) $opts['redeem_rate_currency'];

        $discount = ( $points / $rate_points ) * $rate_currency;

        return (float) apply_filters( 'wclr_discount_for_points', $discount, $points );
    }

    /**
     * Calculate the maximum redeemable points for a given cart total and balance.
     *
     * @param float $cart_total  Cart total before loyalty discount.
     * @param int   $balance     Customer's current points balance.
     * @return int Maximum points that can be redeemed.
     */
    public static function max_redeemable_points( $cart_total, $balance ) {
        $opts = Options::get();

        if ( $opts['redeem_enabled'] !== '1' || $balance <= 0 ) {
            return 0;
        }

        $min_points  = max( 1, (int) $opts['redeem_min_points'] );
        $max_percent = max( 1, min( 100, (int) $opts['redeem_max_percent'] ) );

        // Max discount allowed.
        $max_discount = $cart_total * ( $max_percent / 100 );

        // Points needed for that max discount.
        $rate_points   = max( 1, (int) $opts['redeem_rate_points'] );
        $rate_currency = max( 0.01, (float) $opts['redeem_rate_currency'] );
        $max_points    = (int) floor( ( $max_discount / $rate_currency ) * $rate_points );

        // Cap by balance.
        $redeemable = min( $balance, $max_points );

        // Must meet minimum.
        if ( $redeemable < $min_points ) {
            return 0;
        }

        return (int) apply_filters( 'wclr_max_redeemable_points', $redeemable, $cart_total, $balance );
    }
}
