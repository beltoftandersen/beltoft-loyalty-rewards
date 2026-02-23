<?php
/**
 * WooCommerce Loyalty Points - Uninstall
 *
 * Runs when the plugin is deleted from WordPress admin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Check if user opted in to cleanup.
$wclr_options = get_option( 'wclr_options', [] );
if ( empty( $wclr_options['cleanup_on_uninstall'] ) || $wclr_options['cleanup_on_uninstall'] !== '1' ) {
    return;
}

global $wpdb;

// Drop custom table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'wclr_ledger' ) );

// Delete plugin options.
delete_option( 'wclr_options' );
delete_option( 'wclr_db_version' );

// Delete all user meta.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup on uninstall.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s, %s)",
        '_wclr_balance',
        '_wclr_lifetime_earned',
        '_wclr_lifetime_spent'
    )
);

// Delete all order meta (HPOS-compatible).
if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
     && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
) {
    $wclr_meta_table = $wpdb->prefix . 'wc_orders_meta';
} else {
    $wclr_meta_table = $wpdb->postmeta;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup on uninstall.
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE meta_key LIKE %s',
        $wclr_meta_table,
        $wpdb->esc_like( '_wclr_' ) . '%'
    )
);

// Clear scheduled cron.
wp_clear_scheduled_hook( 'wclr_expiry_cron' );

// Flush rewrite rules.
flush_rewrite_rules();
