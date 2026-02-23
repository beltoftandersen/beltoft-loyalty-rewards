<?php

namespace LoyaltyRewards\Admin;

use LoyaltyRewards\Core\PointsService;

defined( 'ABSPATH' ) || exit;

class UserProfile {

    public static function init() {
        add_action( 'show_user_profile', [ __CLASS__, 'render_fields' ] );
        add_action( 'edit_user_profile', [ __CLASS__, 'render_fields' ] );
        add_action( 'personal_options_update', [ __CLASS__, 'save_fields' ] );
        add_action( 'edit_user_profile_update', [ __CLASS__, 'save_fields' ] );
    }

    /**
     * Render loyalty points section on user profile.
     *
     * @param \WP_User $user
     */
    public static function render_fields( $user ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $balance  = PointsService::get_balance( $user->ID );
        $earned   = (int) get_user_meta( $user->ID, '_wclr_lifetime_earned', true );
        $spent    = (int) get_user_meta( $user->ID, '_wclr_lifetime_spent', true );
        $ledger_url = admin_url( 'admin.php?page=wclr-loyalty&tab=ledger&user_id=' . $user->ID );
        ?>
        <h2><?php esc_html_e( 'Loyalty Points', 'beltoft-loyalty-rewards' ); ?></h2>
        <table class="form-table wclr-user-profile">
            <tr>
                <th><?php esc_html_e( 'Current Balance', 'beltoft-loyalty-rewards' ); ?></th>
                <td>
                    <strong><?php echo esc_html( number_format_i18n( $balance ) ); ?></strong>
                    <a href="<?php echo esc_url( $ledger_url ); ?>" class="button button-small" style="margin-left:8px;">
                        <?php esc_html_e( 'View Ledger', 'beltoft-loyalty-rewards' ); ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Lifetime Earned', 'beltoft-loyalty-rewards' ); ?></th>
                <td><?php echo esc_html( number_format_i18n( $earned ) ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Lifetime Spent', 'beltoft-loyalty-rewards' ); ?></th>
                <td><?php echo esc_html( number_format_i18n( $spent ) ); ?></td>
            </tr>
            <tr>
                <th>
                    <label for="wclr_adjust_points"><?php esc_html_e( 'Adjust Points', 'beltoft-loyalty-rewards' ); ?></label>
                </th>
                <td>
                    <input type="number" id="wclr_adjust_points" name="wclr_adjust_points" value="" step="1" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Positive to add, negative to deduct.', 'beltoft-loyalty-rewards' ); ?></p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="wclr_adjust_reason"><?php esc_html_e( 'Reason', 'beltoft-loyalty-rewards' ); ?></label>
                </th>
                <td>
                    <input type="text" id="wclr_adjust_reason" name="wclr_adjust_reason" value="" class="regular-text" />
                    <?php wp_nonce_field( 'wclr_adjust_points_' . $user->ID, 'wclr_adjust_nonce' ); ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Handle admin points adjustment.
     *
     * @param int $user_id
     */
    public static function save_fields( $user_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! isset( $_POST['wclr_adjust_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wclr_adjust_nonce'] ) ), 'wclr_adjust_points_' . $user_id ) ) {
            return;
        }

        $points = isset( $_POST['wclr_adjust_points'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['wclr_adjust_points'] ) ) : 0;
        if ( $points === 0 ) {
            return;
        }

        $reason = isset( $_POST['wclr_adjust_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['wclr_adjust_reason'] ) ) : '';

        PointsService::admin_adjust( $user_id, $points, $reason );
    }
}
