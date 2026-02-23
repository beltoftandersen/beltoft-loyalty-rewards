<?php

namespace LoyaltyRewards\Blocks;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use LoyaltyRewards\Support\Options;
use LoyaltyRewards\Core\Calculator;
use LoyaltyRewards\Core\PointsService;
use LoyaltyRewards\WooCommerce\RedeemHooks;

defined( 'ABSPATH' ) || exit;

class StoreApiExtension {

    /**
     * Initialize. Called from within a woocommerce_blocks_loaded callback.
     */
    public static function init() {
        self::register_endpoint_data();
    }

    public static function register_endpoint_data() {
        if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
            return;
        }

        woocommerce_store_api_register_endpoint_data( [
            'endpoint'        => CartSchema::IDENTIFIER,
            'namespace'       => 'beltoft-loyalty-rewards',
            'data_callback'   => [ __CLASS__, 'extend_cart_data' ],
            'schema_callback' => [ __CLASS__, 'extend_cart_schema' ],
            'schema_type'     => ARRAY_A,
        ] );

        // Register update callback for block cart/checkout apply/remove points.
        if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
            woocommerce_store_api_register_update_callback( [
                'namespace' => 'beltoft-loyalty-rewards',
                'callback'  => [ __CLASS__, 'handle_update_callback' ],
            ] );
        }

        // Register custom Store API routes for apply/remove (legacy REST).
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Add loyalty points data to the cart response.
     */
    public static function extend_cart_data() {
        $user_id = get_current_user_id();

        $base = [
            'balance'            => 0,
            'redeeming_points'   => 0,
            'discount_value'     => 0,
            'earn_rate'          => Options::get( 'earn_rate' ),
            'redeem_enabled'     => Options::get( 'redeem_enabled' ) === '1',
            'points_to_earn'     => 0,
            'redeem_rate_points' => (int) Options::get( 'redeem_rate_points' ),
            'redeem_rate_currency' => (float) Options::get( 'redeem_rate_currency' ),
            'redeem_min_points'  => (int) Options::get( 'redeem_min_points' ),
            'redeem_max_percent' => (int) Options::get( 'redeem_max_percent' ),
            'currency_symbol'    => get_woocommerce_currency_symbol(),
        ];

        if ( ! $user_id ) {
            // Show points-to-earn and sign-up URL for guests.
            $subtotal = WC()->cart ? (float) WC()->cart->get_subtotal() : 0;
            $pts      = Calculator::points_for_amount( $subtotal );

            $redeem_rate_points   = max( 1, (int) Options::get( 'redeem_rate_points' ) );
            $redeem_rate_currency = (float) Options::get( 'redeem_rate_currency' );

            $base['points_to_earn'] = $pts;
            $base['points_value']   = ( $pts / $redeem_rate_points ) * $redeem_rate_currency;

            $base['signup_url'] = Options::get_signup_url();
            return $base;
        }

        $balance         = PointsService::get_balance( $user_id );
        $session_points  = WC()->session ? (int) WC()->session->get( 'wclr_redeem_points', 0 ) : 0;
        $discount_value  = $session_points > 0 ? Calculator::discount_for_points( $session_points ) : 0;
        $subtotal        = WC()->cart ? (float) WC()->cart->get_subtotal() : 0;

        $base['balance']          = $balance;
        $base['redeeming_points'] = $session_points;
        $base['discount_value']   = $discount_value;
        $base['points_to_earn']   = Calculator::points_for_amount( $subtotal );

        return $base;
    }

    /**
     * Schema for the extended cart data.
     */
    public static function extend_cart_schema() {
        return [
            'balance'          => [
                'description' => __( 'Current points balance.', 'beltoft-loyalty-rewards' ),
                'type'        => 'integer',
                'readonly'    => true,
            ],
            'redeeming_points' => [
                'description' => __( 'Points currently being redeemed.', 'beltoft-loyalty-rewards' ),
                'type'        => 'integer',
                'readonly'    => true,
            ],
            'discount_value'   => [
                'description' => __( 'Currency value of redeemed points.', 'beltoft-loyalty-rewards' ),
                'type'        => 'number',
                'readonly'    => true,
            ],
            'earn_rate'        => [
                'description' => __( 'Points earned per currency unit.', 'beltoft-loyalty-rewards' ),
                'type'        => 'string',
                'readonly'    => true,
            ],
            'redeem_enabled'   => [
                'description' => __( 'Whether redemption is enabled.', 'beltoft-loyalty-rewards' ),
                'type'        => 'boolean',
                'readonly'    => true,
            ],
            'points_to_earn'   => [
                'description' => __( 'Points the customer will earn with this order.', 'beltoft-loyalty-rewards' ),
                'type'        => 'integer',
                'readonly'    => true,
            ],
            'redeem_rate_points' => [
                'description' => __( 'Number of points per currency unit in redeem rate.', 'beltoft-loyalty-rewards' ),
                'type'        => 'integer',
                'readonly'    => true,
            ],
            'redeem_rate_currency' => [
                'description' => __( 'Currency amount per redeem rate unit.', 'beltoft-loyalty-rewards' ),
                'type'        => 'number',
                'readonly'    => true,
            ],
            'redeem_min_points' => [
                'description' => __( 'Minimum points required to redeem.', 'beltoft-loyalty-rewards' ),
                'type'        => 'integer',
                'readonly'    => true,
            ],
            'redeem_max_percent' => [
                'description' => __( 'Maximum percentage of cart that can be discounted.', 'beltoft-loyalty-rewards' ),
                'type'        => 'integer',
                'readonly'    => true,
            ],
            'currency_symbol' => [
                'description' => __( 'Store currency symbol.', 'beltoft-loyalty-rewards' ),
                'type'        => 'string',
                'readonly'    => true,
            ],
            'points_value' => [
                'description' => __( 'Monetary value of points to earn.', 'beltoft-loyalty-rewards' ),
                'type'        => 'number',
                'readonly'    => true,
            ],
            'signup_url' => [
                'description' => __( 'Sign-up page URL for guests.', 'beltoft-loyalty-rewards' ),
                'type'        => 'string',
                'readonly'    => true,
            ],
        ];
    }

    /**
     * Handle apply/remove points via the Store API update callback.
     * Called when JS dispatches applyExtensionCartUpdate().
     */
    public static function handle_update_callback( $data ) {
        $action = isset( $data['action'] ) ? sanitize_text_field( $data['action'] ) : '';

        if ( Options::get( 'redeem_enabled' ) !== '1' ) {
            return;
        }

        if ( $action === 'apply_points' ) {
            $user_id = get_current_user_id();
            if ( ! $user_id ) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                    'wclr_not_logged_in',
                    esc_html__( 'You must be logged in to redeem points.', 'beltoft-loyalty-rewards' ),
                    401
                );
            }

            if ( ! WC()->cart || ! WC()->session ) {
                return;
            }

            $points = isset( $data['points'] ) ? absint( $data['points'] ) : 0;
            $result = RedeemHooks::apply_points_to_cart( $user_id, $points );

            if ( is_wp_error( $result ) ) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                    'wclr_' . sanitize_key( $result->get_error_code() ),
                    esc_html( $result->get_error_message() ),
                    400
                );
            }

        } elseif ( $action === 'remove_points' ) {
            RedeemHooks::remove_points_from_cart();
        }
    }

    /**
     * Register REST routes for block checkout apply/remove (legacy).
     */
    public static function register_routes() {
        register_rest_route( 'beltoft-loyalty-rewards/v1', '/redeem', [
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'rest_apply_points' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args'                => [
                    'points' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ] );

        register_rest_route( 'beltoft-loyalty-rewards/v1', '/redeem', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'rest_remove_points' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ],
        ] );
    }

    /**
     * REST: Apply points.
     */
    public static function rest_apply_points( $request ) {
        if ( Options::get( 'redeem_enabled' ) !== '1' ) {
            return new \WP_Error( 'wclr_redeem_disabled', __( 'Points redemption is not enabled.', 'beltoft-loyalty-rewards' ), [ 'status' => 403 ] );
        }

        if ( ! WC()->cart || ! WC()->session ) {
            return new \WP_Error( 'wclr_no_cart', __( 'Cart not available.', 'beltoft-loyalty-rewards' ), [ 'status' => 400 ] );
        }

        $result = RedeemHooks::apply_points_to_cart( get_current_user_id(), $request->get_param( 'points' ) );

        if ( is_wp_error( $result ) ) {
            $result->add_data( [ 'status' => 400 ] );
            return $result;
        }

        return rest_ensure_response( [
            'success'  => true,
            'points'   => $result,
            'discount' => Calculator::discount_for_points( $result ),
        ] );
    }

    /**
     * REST: Remove points.
     */
    public static function rest_remove_points() {
        if ( Options::get( 'redeem_enabled' ) !== '1' ) {
            return new \WP_Error( 'wclr_redeem_disabled', __( 'Points redemption is not enabled.', 'beltoft-loyalty-rewards' ), [ 'status' => 403 ] );
        }

        RedeemHooks::remove_points_from_cart();

        return rest_ensure_response( [ 'success' => true ] );
    }
}
