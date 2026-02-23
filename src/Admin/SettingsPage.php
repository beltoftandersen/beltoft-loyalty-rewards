<?php

namespace LoyaltyRewards\Admin;

use LoyaltyRewards\Support\Options;
use LoyaltyRewards\Core\LedgerRepository;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

    const GROUP = 'wclr_settings_group';
    const SLUG  = 'wclr-loyalty';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    /**
     * Add single "Loyalty Rewards" submenu under WooCommerce.
     */
    public static function add_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Loyalty Rewards', 'beltoft-loyalty-rewards' ),
            __( 'Loyalty Rewards', 'beltoft-loyalty-rewards' ),
            'manage_woocommerce',
            self::SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Register settings.
     */
    public static function register_settings() {
        register_setting( self::GROUP, Options::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ Options::class, 'sanitize' ],
            'default'           => Options::defaults(),
            'show_in_rest'      => false,
        ] );

        $settings_slug = self::SLUG . '-settings';

        // ── General Section ──
        add_settings_section( 'wclr_general', __( 'General', 'beltoft-loyalty-rewards' ), '__return_null', $settings_slug );
        self::add_checkbox( 'enabled', __( 'Enable Loyalty Points', 'beltoft-loyalty-rewards' ), 'wclr_general', $settings_slug );

        // ── Earning Section ──
        add_settings_section( 'wclr_earning', __( 'Earning Rules', 'beltoft-loyalty-rewards' ), function () {
            echo '<p>' . esc_html__( 'Configure how customers earn points on purchases.', 'beltoft-loyalty-rewards' ) . '</p>';
        }, $settings_slug );

        self::add_number( 'earn_rate', sprintf(
            /* translators: %s: currency symbol (e.g. $, €, £) */
            __( 'Earn Rate (points per %s1)', 'beltoft-loyalty-rewards' ),
            get_woocommerce_currency_symbol()
        ), 'wclr_earning', '0.01', '0', '', $settings_slug );
        self::add_select( 'award_on_status', __( 'Award Points On', 'beltoft-loyalty-rewards' ), 'wclr_earning', [
            'completed'  => __( 'Order Completed', 'beltoft-loyalty-rewards' ),
            'processing' => __( 'Order Processing', 'beltoft-loyalty-rewards' ),
        ], $settings_slug );
        self::add_checkbox( 'exclude_tax', __( 'Exclude Tax from Points Calculation', 'beltoft-loyalty-rewards' ), 'wclr_earning', $settings_slug );
        self::add_checkbox( 'exclude_shipping', __( 'Exclude Shipping from Points Calculation', 'beltoft-loyalty-rewards' ), 'wclr_earning', $settings_slug );
        self::add_select( 'rounding', __( 'Rounding', 'beltoft-loyalty-rewards' ), 'wclr_earning', [
            'floor' => __( 'Round Down', 'beltoft-loyalty-rewards' ),
            'round' => __( 'Round Nearest', 'beltoft-loyalty-rewards' ),
            'ceil'  => __( 'Round Up', 'beltoft-loyalty-rewards' ),
        ], $settings_slug );

        // ── Redemption Section ──
        add_settings_section( 'wclr_redeem', __( 'Redemption Rules', 'beltoft-loyalty-rewards' ), function () {
            echo '<p>' . esc_html__( 'Configure how customers redeem points for discounts.', 'beltoft-loyalty-rewards' ) . '</p>';
        }, $settings_slug );

        self::add_checkbox( 'redeem_enabled', __( 'Enable Redemption', 'beltoft-loyalty-rewards' ), 'wclr_redeem', $settings_slug );
        self::add_number( 'redeem_rate_points', __( 'Points Required', 'beltoft-loyalty-rewards' ), 'wclr_redeem', '1', '1',
            __( 'This many points equals the currency amount below.', 'beltoft-loyalty-rewards' ), $settings_slug );
        self::add_number( 'redeem_rate_currency', sprintf(
            /* translators: %s: currency symbol (e.g. $, €, £) */
            __( 'Currency Value (%s)', 'beltoft-loyalty-rewards' ),
            get_woocommerce_currency_symbol()
        ), 'wclr_redeem', '0.01', '0.01',
            __( 'The discount value for the points above.', 'beltoft-loyalty-rewards' ), $settings_slug );
        self::add_number( 'redeem_min_points', __( 'Minimum Points to Redeem', 'beltoft-loyalty-rewards' ), 'wclr_redeem', '1', '0', '', $settings_slug );
        self::add_number( 'redeem_max_percent', __( 'Max Cart Discount (%)', 'beltoft-loyalty-rewards' ), 'wclr_redeem', '1', '1',
            __( 'Max % of cart payable with points. Set 100 to allow full payment.', 'beltoft-loyalty-rewards' ), $settings_slug );

        // ── Display Section ──
        add_settings_section( 'wclr_display', __( 'Display', 'beltoft-loyalty-rewards' ), '__return_null', $settings_slug );
        self::add_checkbox( 'show_myaccount_tab', __( 'Show "My Points" Tab in My Account', 'beltoft-loyalty-rewards' ), 'wclr_display', $settings_slug );
        $product_hook_desc = __( 'To display the points message in a custom location, use the shortcode:', 'beltoft-loyalty-rewards' )
                           . '<br><code>[wclr_points_message]</code>';
        self::add_checkbox( 'show_product_message', __( 'Show "Earn X Points" on Product Pages', 'beltoft-loyalty-rewards' ), 'wclr_display', $settings_slug, $product_hook_desc );
        $hook_desc = __( 'To display the redeem form in a custom location, use the shortcode:', 'beltoft-loyalty-rewards' )
                   . '<br><code>[wclr_redeem_form]</code><br>'
                   . __( 'Uncheck the options above to avoid compatibility issues.', 'beltoft-loyalty-rewards' );
        self::add_checkbox( 'redeem_show_on_cart', __( 'Show Redeem Form on Cart Page', 'beltoft-loyalty-rewards' ), 'wclr_display', $settings_slug, $hook_desc );
        self::add_checkbox( 'redeem_show_on_checkout', __( 'Show Redeem Form on Checkout Page', 'beltoft-loyalty-rewards' ), 'wclr_display', $settings_slug, $hook_desc );
        self::add_text( 'signup_url', __( 'Sign Up Page URL', 'beltoft-loyalty-rewards' ), 'wclr_display', $settings_slug,
            __( 'URL for the guest sign-up link shown on cart and checkout.', 'beltoft-loyalty-rewards' ) );

        // ── Expiry Section ──
        add_settings_section( 'wclr_expiry', __( 'Expiry', 'beltoft-loyalty-rewards' ), '__return_null', $settings_slug );
        self::add_checkbox( 'expiry_enabled', __( 'Enable Points Expiry', 'beltoft-loyalty-rewards' ), 'wclr_expiry', $settings_slug );
        self::add_number( 'expiry_days', __( 'Expire After (days)', 'beltoft-loyalty-rewards' ), 'wclr_expiry', '1', '1', '', $settings_slug );

        // ── Advanced Section ──
        add_settings_section( 'wclr_advanced', __( 'Advanced', 'beltoft-loyalty-rewards' ), '__return_null', $settings_slug );
        self::add_checkbox( 'cleanup_on_uninstall', __( 'Delete All Data on Uninstall', 'beltoft-loyalty-rewards' ), 'wclr_advanced', $settings_slug );
        self::add_checkbox(
            'debug_logging',
            __( 'Enable Debug Logging', 'beltoft-loyalty-rewards' ),
            'wclr_advanced',
            $settings_slug,
            __( 'Log loyalty events to WooCommerce > Status > Logs. Disable in production for best performance.', 'beltoft-loyalty-rewards' )
        );
    }

    /**
     * Get the current active tab.
     */
    private static function current_tab() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab display.
        return isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
    }

    /**
     * Render the tabbed page.
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $current_tab = self::current_tab();
        $tabs = apply_filters( 'wclr_admin_tabs', [
            'settings' => __( 'Settings', 'beltoft-loyalty-rewards' ),
            'ledger'   => __( 'Points Ledger', 'beltoft-loyalty-rewards' ),
        ] );
        ?>
        <div class="wrap wclr-settings-wrap">
            <h1><?php esc_html_e( 'Loyalty Rewards', 'beltoft-loyalty-rewards' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ( $tabs as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $slug ) ); ?>"
                       class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php
            if ( 'ledger' === $current_tab ) {
                self::render_ledger_tab();
            } elseif ( has_action( "wclr_admin_tab_{$current_tab}" ) ) {
                do_action( "wclr_admin_tab_{$current_tab}" );
            } else {
                self::render_settings_tab();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render the settings tab content.
     */
    private static function render_settings_tab() {
        settings_errors();
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields( self::GROUP );
            do_settings_sections( self::SLUG . '-settings' );
            submit_button();
            ?>
        </form>
        <?php
    }

    /**
     * Render the ledger tab content.
     */
    private static function render_ledger_tab() {
        require_once __DIR__ . '/LedgerTable.php';

        $table = new LedgerTable();
        $table->prepare_items();

        $stats = LedgerRepository::get_summary_stats();
        ?>
        <div class="wclr-ledger-wrap" style="margin-top:16px;">
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wclr_export_ledger' ), 'wclr_export' ) ); ?>"
               class="page-title-action" style="margin-bottom:12px;display:inline-block;">
                <?php esc_html_e( 'Export CSV', 'beltoft-loyalty-rewards' ); ?>
            </a>

            <div class="wclr-stats-cards" style="display:flex;gap:16px;margin:16px 0;">
                <?php
                $cards = [
                    __( 'In Circulation', 'beltoft-loyalty-rewards' )     => number_format_i18n( $stats['total_in_circulation'] ),
                    __( 'Earned This Month', 'beltoft-loyalty-rewards' )  => number_format_i18n( $stats['earned_this_month'] ),
                    __( 'Redeemed This Month', 'beltoft-loyalty-rewards' ) => number_format_i18n( $stats['redeemed_this_month'] ),
                    __( 'Expired This Month', 'beltoft-loyalty-rewards' ) => number_format_i18n( $stats['expired_this_month'] ),
                ];
                foreach ( $cards as $label => $value ) :
                ?>
                    <div style="background:#fff;border:1px solid #c3c4c7;padding:12px 20px;min-width:140px;">
                        <div style="font-size:11px;text-transform:uppercase;color:#646970;"><?php echo esc_html( $label ); ?></div>
                        <div style="font-size:22px;font-weight:600;margin-top:4px;"><?php echo esc_html( $value ); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
                <input type="hidden" name="tab" value="ledger" />
                <?php
                $table->search_box( __( 'Search User ID', 'beltoft-loyalty-rewards' ), 'wclr_user_search' );
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /* ── Field Helpers ─────────────────────────────────────────── */

    private static function add_checkbox( $key, $label, $section, $page = '', $desc = '' ) {
        if ( ! $page ) {
            $page = self::SLUG . '-settings';
        }
        add_settings_field( "wclr_{$key}", $label, function () use ( $key, $desc ) {
            $val = Options::get( $key );
            printf(
                '<input type="checkbox" name="%s[%s]" value="1" %s />',
                esc_attr( Options::OPTION ),
                esc_attr( $key ),
                checked( $val, '1', false )
            );
            if ( $desc ) {
                printf( '<p class="description">%s</p>', wp_kses( $desc, [ 'code' => [], 'br' => [] ] ) );
            }
        }, $page, $section );
    }

    private static function add_number( $key, $label, $section, $step = '1', $min = '0', $desc = '', $page = '' ) {
        if ( ! $page ) {
            $page = self::SLUG . '-settings';
        }
        add_settings_field( "wclr_{$key}", $label, function () use ( $key, $step, $min, $desc ) {
            $val = Options::get( $key );
            printf(
                '<input type="number" name="%s[%s]" value="%s" step="%s" min="%s" class="small-text" />',
                esc_attr( Options::OPTION ),
                esc_attr( $key ),
                esc_attr( $val ),
                esc_attr( $step ),
                esc_attr( $min )
            );
            if ( $desc ) {
                printf( '<p class="description">%s</p>', esc_html( $desc ) );
            }
        }, $page, $section );
    }

    private static function add_text( $key, $label, $section, $page = '', $desc = '' ) {
        if ( ! $page ) {
            $page = self::SLUG . '-settings';
        }
        add_settings_field( "wclr_{$key}", $label, function () use ( $key, $desc ) {
            $val = Options::get( $key );
            printf(
                '<input type="text" name="%s[%s]" value="%s" class="regular-text" />',
                esc_attr( Options::OPTION ),
                esc_attr( $key ),
                esc_attr( $val )
            );
            if ( $desc ) {
                printf( '<p class="description">%s</p>', esc_html( $desc ) );
            }
        }, $page, $section );
    }

    private static function add_select( $key, $label, $section, $choices, $page = '' ) {
        if ( ! $page ) {
            $page = self::SLUG . '-settings';
        }
        add_settings_field( "wclr_{$key}", $label, function () use ( $key, $choices ) {
            $val = Options::get( $key );
            printf( '<select name="%s[%s]">', esc_attr( Options::OPTION ), esc_attr( $key ) );
            foreach ( $choices as $value => $text ) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( $value ),
                    selected( $val, $value, false ),
                    esc_html( $text )
                );
            }
            echo '</select>';
        }, $page, $section );
    }
}
