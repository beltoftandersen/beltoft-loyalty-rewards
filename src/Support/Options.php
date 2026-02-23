<?php

namespace LoyaltyRewards\Support;

defined( 'ABSPATH' ) || exit;

class Options {

    const OPTION = 'wclr_options';

    /** @var array|null Static cache for the current request. */
    private static $cache = null;

    /**
     * Full defaults array for every setting.
     */
    public static function defaults() {
        return [
            // General
            'enabled'              => '1',

            // Earning
            'earn_rate'            => '1',      // points per currency unit
            'award_on_status'      => 'completed', // completed | processing
            'exclude_tax'          => '1',
            'exclude_shipping'     => '1',
            'rounding'             => 'floor',  // floor | ceil | round

            // Redemption
            'redeem_enabled'       => '1',
            'redeem_rate_points'   => '100',    // this many points …
            'redeem_rate_currency' => '1',      // … equal this much currency
            'redeem_min_points'    => '100',    // minimum points to redeem
            'redeem_max_percent'   => '100',    // max % of cart total

            // Display
            'show_myaccount_tab'   => '1',
            'show_product_message' => '1',
            'redeem_show_on_cart'     => '1',
            'redeem_show_on_checkout' => '1',
            'signup_url'           => '/my-account/',

            // Expiry (Phase 3)
            'expiry_enabled'       => '0',
            'expiry_days'          => '365',

            // Advanced
            'cleanup_on_uninstall' => '0',
            'debug_logging'        => '0',
        ];
    }

    /**
     * Get option value(s). Returns single key or full merged array.
     */
    public static function get( $key = null ) {
        if ( null === self::$cache ) {
            self::$cache = wp_parse_args(
                get_option( self::OPTION, [] ),
                self::defaults()
            );
        }

        if ( null === $key ) {
            return self::$cache;
        }

        return self::$cache[ $key ] ?? null;
    }

    /**
     * Get the absolute sign-up page URL.
     */
    public static function get_signup_url(): string {
        $url = self::get( 'signup_url' ) ?: '/my-account/';
        if ( strpos( $url, 'http' ) !== 0 ) {
            $url = home_url( $url );
        }
        return $url;
    }

    /**
     * Sanitize callback for register_setting.
     */
    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : [];
        $out   = [];
        $prev  = self::get();

        $fields = [
            // key => type
            'enabled'              => 'bool',
            'earn_rate'            => 'float',
            'award_on_status'      => 'select',
            'exclude_tax'          => 'bool',
            'exclude_shipping'     => 'bool',
            'rounding'             => 'select',
            'redeem_enabled'       => 'bool',
            'redeem_rate_points'   => 'int',
            'redeem_rate_currency' => 'float',
            'redeem_min_points'    => 'int',
            'redeem_max_percent'   => 'int',
            'show_myaccount_tab'      => 'bool',
            'show_product_message'    => 'bool',
            'redeem_show_on_cart'     => 'bool',
            'redeem_show_on_checkout' => 'bool',
            'signup_url'           => 'url',
            'expiry_enabled'       => 'bool',
            'expiry_days'          => 'int',
            'cleanup_on_uninstall' => 'bool',
            'debug_logging'        => 'bool',
        ];

        $selects = [
            'award_on_status' => [ 'completed', 'processing' ],
            'rounding'        => [ 'floor', 'ceil', 'round' ],
        ];

        foreach ( $fields as $field => $type ) {
            $has = array_key_exists( $field, $input );
            $val = $has ? $input[ $field ] : '';

            switch ( $type ) {
                case 'bool':
                    $out[ $field ] = ( $has && in_array( $val, [ '1', 'on', 'yes', true ], true ) ) ? '1' : '0';
                    break;

                case 'int':
                    $out[ $field ] = $has && is_numeric( $val ) ? (string) absint( $val ) : $prev[ $field ];
                    break;

                case 'float':
                    $out[ $field ] = $has && is_numeric( $val ) ? (string) abs( (float) $val ) : $prev[ $field ];
                    break;

                case 'select':
                    $allowed       = $selects[ $field ] ?? [];
                    $out[ $field ] = ( $has && in_array( $val, $allowed, true ) ) ? $val : $prev[ $field ];
                    break;

                case 'url':
                    $out[ $field ] = $has && $val !== '' ? esc_url_raw( $val ) : $prev[ $field ];
                    break;

                default:
                    $out[ $field ] = sanitize_text_field( $val );
            }
        }

        return $out;
    }
}
