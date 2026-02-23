<?php

namespace LoyaltyRewards\Admin;

use LoyaltyRewards\Core\LedgerRepository;

defined( 'ABSPATH' ) || exit;

class LedgerListTable {

    public static function init() {
        add_action( 'admin_post_wclr_export_ledger', [ __CLASS__, 'export_csv' ] );
    }

    /**
     * Export ledger as CSV (batched to limit memory usage).
     */
    public static function export_csv() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'beltoft-loyalty-rewards' ) );
        }

        check_admin_referer( 'wclr_export' );

        global $wpdb;
        $table = LedgerRepository::table_name();
        $batch = 1000;

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=loyalty-points-ledger-' . gmdate( 'Y-m-d' ) . '.csv' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Writing to php://output for CSV download.
        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'ID', 'User ID', 'Order ID', 'Points', 'Balance After', 'Type', 'Description', 'Date' ] );

        $offset = 0;
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Batched CSV export of custom table.
            $rows = $wpdb->get_results( $wpdb->prepare(
                'SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $table,
                $batch,
                $offset
            ), ARRAY_A );

            foreach ( $rows as $row ) {
                fputcsv( $output, [
                    $row['id'],
                    $row['user_id'],
                    $row['order_id'] ?? '',
                    $row['points_delta'],
                    $row['balance_after'],
                    $row['type'],
                    $row['description'],
                    $row['created_at'],
                ] );
            }

            $offset += $batch;
        } while ( count( $rows ) === $batch );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://output stream.
        fclose( $output );
        exit;
    }
}
