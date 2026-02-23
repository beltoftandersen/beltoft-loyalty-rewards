<?php

namespace LoyaltyRewards\Support;

defined( 'ABSPATH' ) || exit;

class Installer {

    const DB_VERSION_KEY = 'wclr_db_version';
    const DB_VERSION     = '1.0';

    /**
     * Activation hook.
     */
    public static function activate() {
        self::create_tables();

        if ( get_option( Options::OPTION, null ) === null ) {
            add_option( Options::OPTION, Options::defaults(), '', 'no' );
        }

        update_option( self::DB_VERSION_KEY, self::DB_VERSION );

        // Flush rewrite rules for My Account endpoint.
        add_rewrite_endpoint( 'loyalty-points', EP_ROOT | EP_PAGES );
        flush_rewrite_rules();
    }

    /**
     * Deactivation hook.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'wclr_expiry_cron' );
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables via dbDelta.
     */
    public static function create_tables() {
        global $wpdb;

        $table   = $wpdb->prefix . 'wclr_ledger';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            points_delta int NOT NULL,
            balance_after int NOT NULL DEFAULT 0,
            type varchar(20) NOT NULL DEFAULT 'earn',
            description varchar(255) NOT NULL DEFAULT '',
            meta longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY created_at (created_at),
            KEY type (type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Run on every load to handle upgrades.
     */
    public static function maybe_upgrade() {
        $installed = get_option( self::DB_VERSION_KEY, '0' );

        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::create_tables();
            update_option( self::DB_VERSION_KEY, self::DB_VERSION );
        }
    }
}
