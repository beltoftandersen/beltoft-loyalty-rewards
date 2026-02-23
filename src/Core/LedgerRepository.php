<?php

namespace LoyaltyRewards\Core;

defined( 'ABSPATH' ) || exit;

class LedgerRepository {

    const CACHE_GROUP = 'wclr_ledger';

    /**
     * Human-readable label for a ledger entry type.
     */
    public static function type_label( string $type ): string {
        $labels = [
            'earn'            => __( 'Earned', 'beltoft-loyalty-rewards' ),
            'redeem'          => __( 'Redeemed', 'beltoft-loyalty-rewards' ),
            'admin_add'       => __( 'Admin Add', 'beltoft-loyalty-rewards' ),
            'admin_deduct'    => __( 'Admin Deduct', 'beltoft-loyalty-rewards' ),
            'refund_reversal' => __( 'Refund/Cancel', 'beltoft-loyalty-rewards' ),
            'expire'          => __( 'Expired', 'beltoft-loyalty-rewards' ),
        ];
        return apply_filters( 'wclr_ledger_type_label', $labels[ $type ] ?? ucfirst( str_replace( '_', ' ', $type ) ), $type );
    }

    /**
     * Get the ledger table name.
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wclr_ledger';
    }

    /**
     * Bust caches when ledger data changes.
     */
    public static function flush_cache( $user_id = 0, $order_id = 0 ) {
        if ( $user_id ) {
            wp_cache_delete( 'sum_' . $user_id, self::CACHE_GROUP );
            wp_cache_delete( 'count_user_' . $user_id, self::CACHE_GROUP );

            // Flush paginated list caches for this user (we don't know which
            // page/limit combos are cached, so increment a version key).
            $ver = (int) wp_cache_get( 'user_ver_' . $user_id, self::CACHE_GROUP );
            wp_cache_set( 'user_ver_' . $user_id, $ver + 1, self::CACHE_GROUP );
        }
        if ( $order_id ) {
            wp_cache_delete( 'order_' . $order_id, self::CACHE_GROUP );
        }
        wp_cache_delete( 'summary_stats', self::CACHE_GROUP );
    }

    /**
     * Insert a ledger row.
     *
     * @return int|false  Inserted row ID or false on failure.
     */
    public static function insert( array $data ) {
        global $wpdb;

        $defaults = [
            'user_id'       => 0,
            'order_id'      => null,
            'points_delta'  => 0,
            'balance_after' => 0,
            'type'          => 'earn',
            'description'   => '',
            'meta'          => null,
            'created_at'    => current_time( 'mysql', true ),
        ];

        $row = wp_parse_args( $data, $defaults );

        if ( $row['meta'] !== null && is_array( $row['meta'] ) ) {
            $row['meta'] = wp_json_encode( $row['meta'] );
        }

        $formats = [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table with no WP API.
        $result = $wpdb->insert( self::table_name(), $row, $formats );

        if ( $result ) {
            self::flush_cache( $row['user_id'], $row['order_id'] ? (int) $row['order_id'] : 0 );
        }

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get ledger entries for a user, newest first.
     */
    public static function get_by_user( $user_id, $limit = 20, $offset = 0 ) {
        global $wpdb;

        $ver       = (int) wp_cache_get( 'user_ver_' . $user_id, self::CACHE_GROUP );
        $cache_key = "user_{$user_id}_{$limit}_{$offset}_v{$ver}";
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached above.
        $results = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM %i WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d',
            self::table_name(),
            $user_id,
            $limit,
            $offset
        ) );

        wp_cache_set( $cache_key, $results, self::CACHE_GROUP, 60 );
        return $results;
    }

    /**
     * Count ledger entries for a user.
     */
    public static function count_by_user( $user_id ) {
        global $wpdb;

        $cache_key = 'count_user_' . $user_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return (int) $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached above.
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM %i WHERE user_id = %d',
            self::table_name(),
            $user_id
        ) );

        wp_cache_set( $cache_key, $count, self::CACHE_GROUP, 60 );
        return $count;
    }

    /**
     * Get ledger entries for an order.
     */
    public static function get_by_order( $order_id ) {
        global $wpdb;

        $cache_key = 'order_' . $order_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached above.
        $results = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM %i WHERE order_id = %d ORDER BY created_at ASC',
            self::table_name(),
            $order_id
        ) );

        wp_cache_set( $cache_key, $results, self::CACHE_GROUP, 60 );
        return $results;
    }

    /**
     * Get all ledger entries, paginated (admin use).
     */
    public static function get_all_paginated( $args = [] ) {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'offset'   => 0,
            'type'     => '',
            'user_id'  => 0,
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];

        $args = wp_parse_args( $args, $defaults );

        $has_type = ! empty( $args['type'] );
        $has_user = ! empty( $args['user_id'] );

        // Build ORDER BY value from allow-listed columns, then pass through sanitize_sql_orderby().
        $allowed_cols = [ 'created_at', 'points_delta', 'balance_after', 'user_id' ];
        $col          = in_array( $args['orderby'], $allowed_cols, true ) ? $args['orderby'] : 'created_at';
        $dir          = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $sort_sql     = sanitize_sql_orderby( $col . ' ' . $dir );
        if ( ! $sort_sql ) {
            $sort_sql = 'created_at DESC';
        }

        // Build WHERE clause dynamically.
        $where  = [];
        $values = [ self::table_name() ];

        if ( $has_type ) {
            $where[]  = 'type = %s';
            $values[] = sanitize_key( $args['type'] );
        }
        if ( $has_user ) {
            $where[]  = 'user_id = %d';
            $values[] = (int) $args['user_id'];
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $values[]  = (int) $args['per_page'];
        $values[]  = (int) $args['offset'];

        // phpcs:disable WordPress.DB -- Dynamic WHERE + ORDER BY built from validated values; splat operator for prepare().
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM %i {$where_sql} ORDER BY {$sort_sql} LIMIT %d OFFSET %d",
            ...$values
        ) );
        // phpcs:enable WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Count all ledger entries with optional filters (for pagination).
     */
    public static function count_all( $args = [] ) {
        global $wpdb;

        $where  = [];
        $values = [ self::table_name() ];

        if ( ! empty( $args['type'] ) ) {
            $where[]  = 'type = %s';
            $values[] = sanitize_key( $args['type'] );
        }
        if ( ! empty( $args['user_id'] ) ) {
            $where[]  = 'user_id = %d';
            $values[] = (int) $args['user_id'];
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        // phpcs:disable WordPress.DB -- Dynamic WHERE built from validated values; splat operator for prepare().
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM %i {$where_sql}",
            ...$values
        ) );
        // phpcs:enable WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Sum points_delta for a user (for balance verification).
     */
    public static function sum_by_user( $user_id ) {
        global $wpdb;

        $cache_key = 'sum_' . $user_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return (int) $cached;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached above.
        $sum = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(points_delta), 0) FROM %i WHERE user_id = %d',
            self::table_name(),
            $user_id
        ) );

        wp_cache_set( $cache_key, $sum, self::CACHE_GROUP, 60 );
        return $sum;
    }

    /**
     * Get expired earn entries for cron processing.
     * Returns earn rows older than $days that haven't been expired yet.
     */
    public static function get_expirable_entries( $days, $limit = 100 ) {
        global $wpdb;

        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $cache_key = 'expirable_' . $days . '_' . $limit;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        // The NOT EXISTS subquery must be correlated to l.id so that each earn
        // row is only excluded if it already has a matching expire entry.
        // Meta stores {"source_ledger_id":NNN} — match with LIKE pattern.
        // Build the LIKE pattern parts via esc_like, then pass through %s placeholder.
        $like_prefix = $wpdb->esc_like( '"source_ledger_id":' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached above.
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.* FROM %i l
             WHERE l.type = %s
               AND l.created_at < %s
               AND l.points_delta > 0
               AND NOT EXISTS (
                   SELECT 1 FROM %i e
                   WHERE e.type = %s
                     AND e.meta LIKE CONCAT( %s, CAST(l.id AS CHAR), %s )
               )
             ORDER BY l.created_at ASC
             LIMIT %d",
            self::table_name(),
            'earn',
            $cutoff,
            self::table_name(),
            'expire',
            '%' . $like_prefix,
            '}%',
            $limit
        ) );

        wp_cache_set( $cache_key, $results, self::CACHE_GROUP, 30 );
        return $results;
    }

    /**
     * Get summary stats for reporting.
     */
    public static function get_summary_stats() {
        $cached = wp_cache_get( 'summary_stats', self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;

        $table_name  = self::table_name();
        $month_start = gmdate( 'Y-m-01 00:00:00' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached below.
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(points_delta), 0) FROM %i',
            $table_name
        ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached below.
        $earned = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(points_delta), 0) FROM %i WHERE type = %s AND created_at >= %s',
            $table_name,
            'earn',
            $month_start
        ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached below.
        $redeemed = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(ABS(SUM(points_delta)), 0) FROM %i WHERE type = %s AND created_at >= %s',
            $table_name,
            'redeem',
            $month_start
        ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, cached below.
        $expired = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(ABS(SUM(points_delta)), 0) FROM %i WHERE type = %s AND created_at >= %s',
            $table_name,
            'expire',
            $month_start
        ) );

        $stats = [
            'total_in_circulation' => $total,
            'earned_this_month'    => $earned,
            'redeemed_this_month'  => $redeemed,
            'expired_this_month'   => $expired,
        ];

        wp_cache_set( 'summary_stats', $stats, self::CACHE_GROUP, 60 );
        return $stats;
    }
}
