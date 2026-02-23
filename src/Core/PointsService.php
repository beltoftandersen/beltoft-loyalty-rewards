<?php

namespace LoyaltyRewards\Core;

use LoyaltyRewards\Support\Options;
use LoyaltyRewards\Support\Logger;

defined( 'ABSPATH' ) || exit;

class PointsService {

    /**
     * Get the current points balance for a user.
     */
    public static function get_balance( $user_id ) {
        return (int) get_user_meta( $user_id, '_wclr_balance', true );
    }

    /**
     * Credit points to a user.
     *
     * @param int         $user_id
     * @param int         $points   Positive number.
     * @param string      $type     Ledger type (earn, admin_add, refund_reversal).
     * @param string      $desc     Human-readable description.
     * @param int|null    $order_id Related order ID.
     * @param array|null  $meta     Additional metadata.
     * @return int|false  New balance or false on failure.
     */
    public static function credit( $user_id, $points, $type, $desc = '', $order_id = null, $meta = null ) {
        Logger::debug( sprintf( '[PointsService] credit() called — user #%d, %d pts, type: %s, order: %s', $user_id, $points, $type, $order_id ?: 'none' ) );

        $points = absint( $points );
        if ( $points <= 0 ) {
            return false;
        }

        $balance     = self::get_balance( $user_id );
        $new_balance = $balance + $points;

        // Update user meta.
        update_user_meta( $user_id, '_wclr_balance', $new_balance );

        $lifetime = (int) get_user_meta( $user_id, '_wclr_lifetime_earned', true );
        update_user_meta( $user_id, '_wclr_lifetime_earned', $lifetime + $points );

        // Write ledger entry.
        LedgerRepository::insert( [
            'user_id'       => $user_id,
            'order_id'      => $order_id,
            'points_delta'  => $points,
            'balance_after' => $new_balance,
            'type'          => $type,
            'description'   => $desc,
            'meta'          => $meta,
        ] );

        do_action( 'wclr_points_credited', $user_id, $points, $type, $order_id );

        Logger::debug( sprintf( '[PointsService] Credited %d pts to user #%d (type: %s, order: %s, new balance: %d)', $points, $user_id, $type, $order_id ?: 'none', $new_balance ) );

        return $new_balance;
    }

    /**
     * Debit points from a user.
     *
     * @param int         $user_id
     * @param int         $points   Positive number to deduct.
     * @param string      $type     Ledger type (redeem, admin_deduct, expire).
     * @param string      $desc     Human-readable description.
     * @param int|null    $order_id Related order ID.
     * @param array|null  $meta     Additional metadata.
     * @return int|false  New balance or false on failure.
     */
    public static function debit( $user_id, $points, $type, $desc = '', $order_id = null, $meta = null ) {
        Logger::debug( sprintf( '[PointsService] debit() called — user #%d, %d pts, type: %s, order: %s', $user_id, $points, $type, $order_id ?: 'none' ) );

        $points = absint( $points );
        if ( $points <= 0 ) {
            return false;
        }

        $balance = self::get_balance( $user_id );

        // Clamp to current balance so ledger sum stays in sync with stored balance.
        $original_points = $points;
        $points          = min( $points, $balance );
        $new_balance     = $balance - $points;

        if ( $original_points !== $points ) {
            Logger::debug( sprintf( '[PointsService] Debit clamped from %d to %d (user #%d balance: %d)', $original_points, $points, $user_id, $balance ) );
        }

        if ( $points <= 0 ) {
            Logger::debug( sprintf( '[PointsService] Debit skipped — user #%d has zero balance', $user_id ) );
            return $new_balance;
        }

        // Update user meta.
        update_user_meta( $user_id, '_wclr_balance', $new_balance );

        $lifetime = (int) get_user_meta( $user_id, '_wclr_lifetime_spent', true );
        update_user_meta( $user_id, '_wclr_lifetime_spent', $lifetime + $points );

        // Write ledger entry.
        LedgerRepository::insert( [
            'user_id'       => $user_id,
            'order_id'      => $order_id,
            'points_delta'  => -$points,
            'balance_after' => $new_balance,
            'type'          => $type,
            'description'   => $desc,
            'meta'          => $meta,
        ] );

        do_action( 'wclr_points_debited', $user_id, $points, $type, $order_id );

        Logger::debug( sprintf( '[PointsService] Debited %d pts from user #%d (type: %s, order: %s, new balance: %d)', $points, $user_id, $type, $order_id ?: 'none', $new_balance ) );

        return $new_balance;
    }

    /**
     * Admin manual adjustment.
     *
     * @param int    $user_id
     * @param int    $points   Positive = add, negative = deduct.
     * @param string $reason   Admin-provided reason.
     * @return int|false New balance.
     */
    public static function admin_adjust( $user_id, $points, $reason = '' ) {
        Logger::debug( sprintf( '[PointsService] admin_adjust() — user #%d, %+d pts, reason: %s', $user_id, $points, $reason ?: '(none)' ) );

        if ( $points > 0 ) {
            return self::credit(
                $user_id,
                $points,
                'admin_add',
                $reason ?: __( 'Admin adjustment (add)', 'beltoft-loyalty-rewards' )
            );
        }

        if ( $points < 0 ) {
            return self::debit(
                $user_id,
                abs( $points ),
                'admin_deduct',
                $reason ?: __( 'Admin adjustment (deduct)', 'beltoft-loyalty-rewards' )
            );
        }

        return false;
    }

    /**
     * Process point expiry (called by cron).
     */
    public static function process_expiry() {
        $opts = Options::get();

        if ( $opts['expiry_enabled'] !== '1' ) {
            Logger::debug( '[PointsService] process_expiry() skipped — expiry disabled' );
            return;
        }

        $days  = max( 1, (int) $opts['expiry_days'] );
        $batch = LedgerRepository::get_expirable_entries( $days, 100 );

        Logger::info( sprintf( '[PointsService] process_expiry() started — %d entries to check (expiry: %d days)', count( $batch ), $days ) );

        $expired_count = 0;
        foreach ( $batch as $entry ) {
            $balance     = self::get_balance( $entry->user_id );
            $expire_pts  = min( $entry->points_delta, $balance );

            if ( $expire_pts <= 0 ) {
                continue;
            }

            $expired_count++;
            Logger::debug( sprintf( '[PointsService] Expiring %d pts for user #%d (ledger #%d)', $expire_pts, $entry->user_id, $entry->id ) );

            self::debit(
                $entry->user_id,
                $expire_pts,
                'expire',
                sprintf(
                    /* translators: %d: number of days */
                    __( 'Points expired after %d days', 'beltoft-loyalty-rewards' ),
                    $days
                ),
                $entry->order_id,
                [ 'source_ledger_id' => $entry->id ]
            );

            do_action( 'wclr_points_expired', $entry->user_id, $expire_pts, $entry->id );
        }

        Logger::info( sprintf( '[PointsService] process_expiry() finished — %d entries expired', $expired_count ) );
    }
}
