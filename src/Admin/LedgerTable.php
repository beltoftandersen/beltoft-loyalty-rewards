<?php

namespace LoyaltyRewards\Admin;

use LoyaltyRewards\Core\LedgerRepository;

defined( 'ABSPATH' ) || exit;

// Ensure WP_List_Table is available (this file is loaded on-demand, not via autoloader).
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class LedgerTable extends \WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => 'ledger_entry',
            'plural'   => 'ledger_entries',
            'ajax'     => false,
        ] );
    }

    public function get_columns() {
        return [
            'user_id'       => __( 'User', 'beltoft-loyalty-rewards' ),
            'order_id'      => __( 'Order', 'beltoft-loyalty-rewards' ),
            'points_delta'  => __( 'Points', 'beltoft-loyalty-rewards' ),
            'balance_after' => __( 'Balance After', 'beltoft-loyalty-rewards' ),
            'type'          => __( 'Type', 'beltoft-loyalty-rewards' ),
            'description'   => __( 'Description', 'beltoft-loyalty-rewards' ),
            'created_at'    => __( 'Date', 'beltoft-loyalty-rewards' ),
        ];
    }

    public function get_sortable_columns() {
        return [
            'created_at'   => [ 'created_at', true ],
            'points_delta' => [ 'points_delta', false ],
            'user_id'      => [ 'user_id', false ],
        ];
    }

    public function prepare_items() {
        $per_page = 20;
        $current  = $this->get_pagenum();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list table filters.
        $args = [
            'per_page' => $per_page,
            'offset'   => ( $current - 1 ) * $per_page,
            'orderby'  => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at',
            'order'    => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC',
        ];

        // Filter by type.
        if ( ! empty( $_GET['type'] ) ) {
            $args['type'] = sanitize_text_field( wp_unslash( $_GET['type'] ) );
        }

        // Filter by user ID (search).
        if ( ! empty( $_GET['s'] ) ) {
            $args['user_id'] = absint( $_GET['s'] );
        }

        // URL-param filter.
        if ( ! empty( $_GET['user_id'] ) ) {
            $args['user_id'] = absint( $_GET['user_id'] );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $this->items = LedgerRepository::get_all_paginated( $args );
        $total       = LedgerRepository::count_all( $args );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
        ] );

        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'user_id':
                $user = get_userdata( $item->user_id );
                $name = $user ? esc_html( $user->display_name ) : '#' . intval( $item->user_id );
                $url  = admin_url( 'user-edit.php?user_id=' . intval( $item->user_id ) );
                return '<a href="' . esc_url( $url ) . '">' . $name . '</a>';

            case 'order_id':
                if ( ! $item->order_id ) {
                    return '&mdash;';
                }
                $order = wc_get_order( $item->order_id );
                if ( ! $order ) {
                    return '#' . esc_html( $item->order_id );
                }
                return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $item->order_id ) . '</a>';

            case 'points_delta':
                $class = $item->points_delta >= 0 ? 'wclr-positive' : 'wclr-negative';
                $sign  = $item->points_delta >= 0 ? '+' : '';
                return '<span class="' . esc_attr( $class ) . '">' . esc_html( $sign . number_format_i18n( $item->points_delta ) ) . '</span>';

            case 'balance_after':
                return esc_html( number_format_i18n( $item->balance_after ) );

            case 'type':
                return '<span class="wclr-type wclr-type--' . esc_attr( $item->type ) . '">'
                       . esc_html( LedgerRepository::type_label( $item->type ) )
                       . '</span>';

            case 'description':
                return esc_html( $item->description );

            case 'created_at':
                return esc_html( date_i18n( 'Y-m-d H:i', strtotime( $item->created_at ) ) );

            default:
                return '';
        }
    }

    /**
     * Extra filter controls above the table.
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter display.
        $current_type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
        $types = apply_filters( 'wclr_ledger_type_filter_options', [
            ''                => __( 'All Types', 'beltoft-loyalty-rewards' ),
            'earn'            => __( 'Earned', 'beltoft-loyalty-rewards' ),
            'redeem'          => __( 'Redeemed', 'beltoft-loyalty-rewards' ),
            'admin_add'       => __( 'Admin Add', 'beltoft-loyalty-rewards' ),
            'admin_deduct'    => __( 'Admin Deduct', 'beltoft-loyalty-rewards' ),
            'refund_reversal' => __( 'Refund/Cancel', 'beltoft-loyalty-rewards' ),
            'expire'          => __( 'Expired', 'beltoft-loyalty-rewards' ),
        ] );
        ?>
        <div class="alignleft actions">
            <select name="type">
                <?php foreach ( $types as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_type, $val ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php submit_button( __( 'Filter', 'beltoft-loyalty-rewards' ), '', 'filter_action', false ); ?>
        </div>
        <?php
    }

    public function no_items() {
        esc_html_e( 'No ledger entries found.', 'beltoft-loyalty-rewards' );
    }
}
