<?php

namespace LoyaltyRewards;

use LoyaltyRewards\Support\Installer;
use LoyaltyRewards\Support\Options;
use LoyaltyRewards\Core\PointsService;
use LoyaltyRewards\WooCommerce\EarnHooks;
use LoyaltyRewards\WooCommerce\RedeemHooks;
use LoyaltyRewards\WooCommerce\MyAccount;
use LoyaltyRewards\WooCommerce\OrderAdmin;
use LoyaltyRewards\Admin\SettingsPage;
use LoyaltyRewards\Admin\UserProfile;
use LoyaltyRewards\Admin\LedgerListTable;

defined( 'ABSPATH' ) || exit;

class Plugin {

    public static function init() {
        Installer::maybe_upgrade();

        $opts = Options::get();

        // Always load earn hooks (order processing).
        EarnHooks::init();

        // Admin-only classes.
        if ( is_admin() ) {
            SettingsPage::init();
            UserProfile::init();
            LedgerListTable::init();
            OrderAdmin::init();
        }

        // Frontend + AJAX.
        if ( ! is_admin() || wp_doing_ajax() ) {
            if ( $opts['show_myaccount_tab'] === '1' ) {
                MyAccount::init();
            }
            if ( $opts['redeem_enabled'] === '1' ) {
                RedeemHooks::init();
            }
        }

        // Product message on single product pages (conditional).
        if ( $opts['show_product_message'] === '1' ) {
            add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'product_points_message' ], 25 );
        }

        // Custom hook for themes/plugins to place the product points message anywhere.
        add_action( 'wclr_product_points_message', [ __CLASS__, 'product_points_message' ] );

        // Shortcodes.
        add_shortcode( 'blr_points_message', [ __CLASS__, 'shortcode_points_message' ] );
        add_shortcode( 'blr_redeem_form', [ RedeemHooks::class, 'shortcode_redeem_form' ] );

        // Assets.
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );

        // Expiry cron.
        if ( $opts['expiry_enabled'] === '1' ) {
            add_action( 'wclr_expiry_cron', [ PointsService::class, 'process_expiry' ] );
            if ( ! wp_next_scheduled( 'wclr_expiry_cron' ) ) {
                wp_schedule_event( time(), 'daily', 'wclr_expiry_cron' );
            }
        }

        // Block cart/checkout Store API data (balance, redeem, earn estimate).
        // Frontend UI is provided by the Pro plugin; this just exposes the data.
        $init_store_api = function () {
            if ( class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
                \LoyaltyRewards\Blocks\StoreApiExtension::init();
            }
        };
        if ( did_action( 'woocommerce_blocks_loaded' ) ) {
            $init_store_api();
        } else {
            add_action( 'woocommerce_blocks_loaded', $init_store_api );
        }
    }

    /**
     * Show "Earn X points" message on single product pages.
     */
    public static function product_points_message() {
        if ( Options::get( 'enabled' ) !== '1' ) {
            return;
        }

        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $price = (float) $product->get_price();
        if ( $price <= 0 ) {
            return;
        }

        $points = \LoyaltyRewards\Core\Calculator::points_for_amount( $price );
        if ( $points > 0 ) {
            printf(
                '<p class="wclr-product-message">%s</p>',
                sprintf(
                    /* translators: %s: number of points */
                    wp_kses( __( 'Purchase this product and earn %s points!', 'beltoft-loyalty-rewards' ), [ 'strong' => [] ] ),
                    '<strong>' . esc_html( number_format_i18n( $points ) ) . '</strong>'
                )
            );

            /**
             * Fires after the product earn-points message.
             *
             * @param int        $points  Estimated points (already adjusted by tier/campaign filters).
             * @param WC_Product $product The current product.
             */
            do_action( 'wclr_product_message_after', $points, $product );
        }
    }

    /**
     * Shortcode [blr_points_message] - renders the "earn X points" message.
     */
    public static function shortcode_points_message() {
        ob_start();
        self::product_points_message();
        return ob_get_clean();
    }

    /**
     * Admin CSS/JS - only on our pages.
     */
    public static function enqueue_admin_assets( $hook ) {
        $our_pages = [ 'woocommerce_page_wclr-loyalty' ];
        $is_user   = in_array( $hook, [ 'user-edit.php', 'profile.php' ], true );

        if ( in_array( $hook, $our_pages, true ) || $is_user ) {
            wp_enqueue_style( 'wclr-admin', WCLR_URL . 'assets/css/admin.css', [], WCLR_VER );
        }
    }

    /**
     * Frontend CSS/JS.
     */
    public static function enqueue_frontend_assets() {
        if ( ! is_account_page() && ! is_cart() && ! is_checkout() && ! is_product() ) {
            return;
        }

        wp_enqueue_style( 'wclr-frontend', WCLR_URL . 'assets/css/frontend.css', [], WCLR_VER );

        if ( is_cart() || is_checkout() ) {
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
}
