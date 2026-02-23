<?php

namespace LoyaltyRewards\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around WC_Logger for loyalty plugin debug logging.
 *
 * - debug(): gated behind the debug_logging option (high-volume tracing)
 * - info():  always logs (notable events: cron runs, bulk operations)
 * - error(): always logs (failures, unexpected states)
 *
 * Logs appear in WooCommerce > Status > Logs as wclr-*.log
 */
class Logger {

    const SOURCE = 'wclr';

    /**
     * Debug-level log (only when debug_logging is enabled).
     */
    public static function debug( string $message ): void {
        if ( Options::get( 'debug_logging' ) !== '1' ) {
            return;
        }
        self::log( 'debug', $message );
    }

    /**
     * Info-level log (always logs).
     */
    public static function info( string $message ): void {
        self::log( 'info', $message );
    }

    /**
     * Error-level log (always logs).
     */
    public static function error( string $message ): void {
        self::log( 'error', $message );
    }

    /**
     * Write to WooCommerce log.
     */
    private static function log( string $level, string $message ): void {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }
        wc_get_logger()->log( $level, $message, [ 'source' => self::SOURCE ] );
    }
}
