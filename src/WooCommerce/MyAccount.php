<?php

namespace LoyaltyRewards\WooCommerce;

use LoyaltyRewards\Support\Options;
use LoyaltyRewards\Core\PointsService;
use LoyaltyRewards\Core\LedgerRepository;

defined( 'ABSPATH' ) || exit;

class MyAccount {

    public static function init() {
        // Register endpoint.
        add_action( 'init', [ __CLASS__, 'add_endpoint' ] );

        // Add menu item.
        add_filter( 'woocommerce_account_menu_items', [ __CLASS__, 'menu_item' ] );

        // Render content.
        add_action( 'woocommerce_account_loyalty-points_endpoint', [ __CLASS__, 'render' ] );

        // Page title.
        add_filter( 'the_title', [ __CLASS__, 'endpoint_title' ], 10, 2 );
    }

    /**
     * Register the rewrite endpoint.
     */
    public static function add_endpoint() {
        add_rewrite_endpoint( 'loyalty-points', EP_ROOT | EP_PAGES );
    }

    /**
     * Add "My Points" to My Account menu.
     */
    public static function menu_item( $items ) {
        $new_items = [];

        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;

            // Insert after "Orders".
            if ( $key === 'orders' ) {
                $new_items['loyalty-points'] = __( 'My Points', 'beltoft-loyalty-rewards' );
            }
        }

        return $new_items;
    }

    /**
     * Set page title for the endpoint.
     */
    public static function endpoint_title( $title, $id = null ) {
        global $wp_query;

        if ( isset( $wp_query->query_vars['loyalty-points'] )
             && ! is_admin()
             && is_main_query()
             && in_the_loop()
             && is_account_page()
        ) {
            return __( 'My Points', 'beltoft-loyalty-rewards' );
        }
        return $title;
    }

    /**
     * Render the My Points page.
     */
    public static function render() {
        $user_id  = get_current_user_id();
        $balance  = PointsService::get_balance( $user_id );
        $lifetime = (int) get_user_meta( $user_id, '_wclr_lifetime_earned', true );
        $spent    = (int) get_user_meta( $user_id, '_wclr_lifetime_spent', true );
        $opts     = Options::get();

        // Pagination.
        $page     = max( 1, absint( get_query_var( 'paged', 1 ) ) );
        $per_page = 15;
        $offset   = ( $page - 1 ) * $per_page;
        $total    = LedgerRepository::count_by_user( $user_id );
        $entries  = LedgerRepository::get_by_user( $user_id, $per_page, $offset );
        $pages    = (int) ceil( $total / $per_page );
        ?>

        <div class="wclr-myaccount">
            <div class="wclr-balance-summary">
                <div class="wclr-balance-card">
                    <span class="wclr-label"><?php esc_html_e( 'Current Balance', 'beltoft-loyalty-rewards' ); ?></span>
                    <span class="wclr-value"><?php echo esc_html( number_format_i18n( $balance ) ); ?></span>
                </div>
                <div class="wclr-balance-card">
                    <span class="wclr-label"><?php esc_html_e( 'Lifetime Earned', 'beltoft-loyalty-rewards' ); ?></span>
                    <span class="wclr-value"><?php echo esc_html( number_format_i18n( $lifetime ) ); ?></span>
                </div>
                <div class="wclr-balance-card">
                    <span class="wclr-label"><?php esc_html_e( 'Lifetime Spent', 'beltoft-loyalty-rewards' ); ?></span>
                    <span class="wclr-value"><?php echo esc_html( number_format_i18n( $spent ) ); ?></span>
                </div>
            </div>

            <p class="wclr-earn-info">
                <?php
                $effective_rate = apply_filters( 'wclr_displayed_earn_rate', (float) $opts['earn_rate'], $user_id );
                printf(
                    /* translators: 1: earn rate, 2: currency amount */
                    wp_kses( __( 'You earn %1$s point(s) for every %2$s spent.', 'beltoft-loyalty-rewards' ), [ 'strong' => [] ] ),
                    '<strong>' . esc_html( number_format_i18n( $effective_rate, $effective_rate == (int) $effective_rate ? 0 : 2 ) ) . '</strong>',
                    '<strong>' . wp_kses_post( wc_price( 1 ) ) . '</strong>'
                );
                ?>
                <?php if ( $opts['redeem_enabled'] === '1' ) : ?>
                    <?php
                    printf(
                        /* translators: 1: points, 2: currency amount */
                        wp_kses( __( 'Redeem %1$s points for %2$s off at checkout.', 'beltoft-loyalty-rewards' ), [ 'strong' => [] ] ),
                        '<strong>' . esc_html( $opts['redeem_rate_points'] ) . '</strong>',
                        '<strong>' . wp_kses_post( wc_price( $opts['redeem_rate_currency'] ) ) . '</strong>'
                    );
                    ?>
                <?php endif; ?>
            </p>

            <?php if ( ! empty( $entries ) ) : ?>
                <h3><?php esc_html_e( 'Transaction History', 'beltoft-loyalty-rewards' ); ?></h3>
                <table class="wclr-transactions woocommerce-orders-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'beltoft-loyalty-rewards' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'beltoft-loyalty-rewards' ); ?></th>
                            <th><?php esc_html_e( 'Points', 'beltoft-loyalty-rewards' ); ?></th>
                            <th><?php esc_html_e( 'Balance', 'beltoft-loyalty-rewards' ); ?></th>
                            <th><?php esc_html_e( 'Description', 'beltoft-loyalty-rewards' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $entries as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ); ?></td>
                                <td>
                                    <span class="wclr-type wclr-type--<?php echo esc_attr( $entry->type ); ?>">
                                        <?php echo esc_html( LedgerRepository::type_label( $entry->type ) ); ?>
                                    </span>
                                </td>
                                <td class="<?php echo $entry->points_delta >= 0 ? 'wclr-positive' : 'wclr-negative'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static strings. ?>">
                                    <?php echo esc_html( ( $entry->points_delta >= 0 ? '+' : '' ) . number_format_i18n( $entry->points_delta ) ); ?>
                                </td>
                                <td><?php echo esc_html( number_format_i18n( $entry->balance_after ) ); ?></td>
                                <td><?php echo esc_html( $entry->description ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $pages > 1 ) : ?>
                    <nav class="wclr-pagination woocommerce-pagination">
                        <?php
                        echo wp_kses_post( paginate_links( [
                            'base'    => esc_url( wc_get_endpoint_url( 'loyalty-points' ) ) . '%_%',
                            'format'  => 'page/%#%/',
                            'current' => $page,
                            'total'   => $pages,
                        ] ) );
                        ?>
                    </nav>
                <?php endif; ?>

            <?php else : ?>
                <p><?php esc_html_e( 'No transactions yet. Start shopping to earn points!', 'beltoft-loyalty-rewards' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

}
