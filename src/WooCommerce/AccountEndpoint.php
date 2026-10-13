<?php

namespace LoyaltyRewards\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "My Points" My Account endpoint through WooCommerce's query vars,
 * so its URL slug can be changed under WooCommerce › Settings › Advanced ›
 * Account endpoints. The internal key stays 'loyalty-points'.
 */
class AccountEndpoint {

    const KEY    = 'loyalty-points';
    const OPTION = 'woocommerce_myaccount_loyalty_points_endpoint';

    /**
     * Register hooks. Runs in every context (admin included) so rewrite rules
     * rebuilt on an admin request still contain the endpoint.
     */
    public static function init() {
        add_filter( 'woocommerce_get_query_vars', [ __CLASS__, 'query_vars' ] );
        add_filter( 'woocommerce_get_settings_advanced', [ __CLASS__, 'settings' ], 10, 2 );
        add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION, [ __CLASS__, 'sanitize' ] );
        add_action( 'add_option_' . self::OPTION, [ __CLASS__, 'queue_flush' ] );
        add_action( 'update_option_' . self::OPTION, [ __CLASS__, 'queue_flush' ] );
        add_action( 'delete_option_' . self::OPTION, [ __CLASS__, 'queue_flush' ] );
    }

    /**
     * The configured URL slug, or the default when unset or empty.
     */
    public static function slug( $value = null ) {
        if ( null === $value ) {
            $value = get_option( self::OPTION, self::KEY );
        }
        $slug = sanitize_title( (string) $value );
        return '' !== $slug ? $slug : self::KEY;
    }

    /**
     * Map the endpoint key to its URL slug.
     */
    public static function query_vars( $vars ) {
        $vars[ self::KEY ] = self::slug();
        return $vars;
    }

    /**
     * Add the slug field to WooCommerce › Settings › Advanced › Account endpoints.
     */
    public static function settings( $settings, $section_id = '' ) {
        if ( '' !== $section_id || ! is_array( $settings ) ) {
            return $settings;
        }

        $field = [
            'title'    => __( 'My Points', 'beltoft-loyalty-rewards' ),
            'desc'     => __( 'Endpoint for the "My account &rarr; My Points" page.', 'beltoft-loyalty-rewards' ),
            'id'       => self::OPTION,
            'type'     => 'text',
            'default'  => self::KEY,
            'desc_tip' => true,
        ];

        foreach ( $settings as $index => $setting ) {
            if ( isset( $setting['type'], $setting['id'] ) && 'sectionend' === $setting['type'] && 'account_endpoint_options' === $setting['id'] ) {
                array_splice( $settings, $index, 0, [ $field ] );
                break;
            }
        }

        return $settings;
    }

    /**
     * Store a clean slug; an empty field falls back to the default.
     */
    public static function sanitize( $value ) {
        return self::slug( $value );
    }

    /**
     * Rebuild rewrite rules on the next request, once the endpoint is registered
     * with its current slug (WooCommerce flushes when this flag is set).
     */
    public static function queue_flush() {
        update_option( 'woocommerce_queue_flush_rewrite_rules', 'yes' );
    }
}
