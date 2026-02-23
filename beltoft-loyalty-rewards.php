<?php
/**
 * Plugin Name: Beltoft Loyalty Rewards for WooCommerce
 * Description: Earn points on purchases and redeem them for cart discounts.
 * Version:     1.2.8
 * Author:      beltoft.net
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.6
 * License:     GPLv2 or later
 * Text Domain: beltoft-loyalty-rewards
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

/* ── Autoloader ────────────────────────────────────────────────── */
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'LoyaltyRewards\\' ) !== 0 ) {
        return;
    }
    $rel  = substr( $class, strlen( 'LoyaltyRewards\\' ) );
    $rel  = str_replace( '\\', DIRECTORY_SEPARATOR, $rel ) . '.php';
    $file = plugin_dir_path( __FILE__ ) . 'src/' . $rel;
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/* ── Constants ─────────────────────────────────────────────────── */
define( 'WCLR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCLR_URL',  plugin_dir_url( __FILE__ ) );
define( 'WCLR_VER',  '1.2.8' );

/* ── Activation / Deactivation ─────────────────────────────────── */
register_activation_hook( __FILE__, [ 'LoyaltyRewards\\Support\\Installer', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'LoyaltyRewards\\Support\\Installer', 'deactivate' ] );

/* ── HPOS Compatibility ────────────────────────────────────────── */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

/* ── Bootstrap ─────────────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'Beltoft Loyalty Rewards for WooCommerce requires WooCommerce to be installed and active.', 'beltoft-loyalty-rewards' );
            echo '</p></div>';
        } );
        return;
    }
    \LoyaltyRewards\Plugin::init();
} );

/* ── Settings link ─────────────────────────────────────────────── */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
    $url = admin_url( 'admin.php?page=wclr-loyalty' );
    array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'beltoft-loyalty-rewards' ) . '</a>' );
    return $links;
} );
