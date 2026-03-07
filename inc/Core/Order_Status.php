<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Class Order_Status
 *
 * Registers custom WooCommerce order status "shipped-order".
 *
 * @since 2.1.0
 * @version 2.1.1
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Order_Status {

    /**
     * Status slug
     *
     * @since 2.1.0
     * @var string
     */
    const STATUS = 'wc-shipped-order';

    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        if ( 'yes' !== Settings::get_setting( 'enable_order_shipped_status', Settings::get_default_value( 'enable_order_shipped_status', 'yes' ) ) ) {
            return;
        }

        add_action( 'init', array( $this, 'register_status' ) );
        add_filter( 'wc_order_statuses', array( $this, 'add_status_to_list' ) );
        add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_bulk_action' ) );
        add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_bulk_action' ) );
        add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_action' ), 10, 3 );
        add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_action' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'render_bulk_action_notice' ) );
    }


    /**
     * Register custom status
     *
     * @since 2.1.0
     * @return void
     */
    public function register_status() {
        register_post_status( self::STATUS, array(
            'label'                     => __( 'Pedido enviado', 'hubgo' ),
            'public'                    => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list'    => true,
            'label_count'               => _n_noop(
                'Pedido enviado (%s)',
                'Pedidos enviados (%s)',
                'hubgo'
            ),
        ));
    }


    /**
     * Add status to WooCommerce dropdown
     *
     * @since 2.1.0
     *
     * @param array $statuses Order statuses.
     * @return array
     */
    public function add_status_to_list( $statuses ) {
        $new_statuses = array();

        foreach ( $statuses as $key => $label ) {
            $new_statuses[ $key ] = $label;

            if ( 'wc-processing' === $key ) {
                $new_statuses[ self::STATUS ] = __( 'Pedido enviado', 'hubgo' );
            }
        }

        return $new_statuses;
    }


    /**
     * Add custom bulk action for orders list.
     *
     * @since 2.1.1
     * @param array $actions | Bulk actions.
     * @return array
     */
    public function add_bulk_action( $actions ) {
        $new_actions = array();

        foreach ( $actions as $action_key => $action_label ) {
            $new_actions[ $action_key ] = $action_label;

            if ( 'mark_processing' === $action_key ) {
                $new_actions['mark_shipped-order'] = __( 'Mudar status para Pedido enviado', 'hubgo' );
            }
        }

        if ( ! isset( $new_actions['mark_shipped-order'] ) ) {
            $new_actions['mark_shipped-order'] = __( 'Mudar status para Pedido enviado', 'hubgo' );
        }

        return $new_actions;
    }


    /**
     * Handle custom bulk action.
     *
     * @since 2.1.1
     * @param string $redirect_to Redirect URL.
     * @param string $action Action slug.
     * @param array  $order_ids Selected order IDs.
     * @return string
     */
    public function handle_bulk_action( $redirect_to, $action, $order_ids ) {
        if ( 'mark_shipped-order' !== $action ) {
            return $redirect_to;
        }

        $updated = 0;

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                continue;
            }

            $order->update_status( 'shipped-order', __( 'Status alterado em massa para Pedido enviado.', 'hubgo' ), true );
            $updated++;
        }

        return add_query_arg( 'hubgo_mark_shipped_order', $updated, $redirect_to );
    }


    /**
     * Render admin notice for bulk action.
     *
     * @since 2.1.1
     * @return void
     */
    public function render_bulk_action_notice() {
        if ( ! isset( $_REQUEST['hubgo_mark_shipped_order'] ) ) {
            return;
        }

        $count = absint( wp_unslash( $_REQUEST['hubgo_mark_shipped_order'] ) );

        if ( $count < 1 ) {
            return;
        }
        ?>
        <div class="updated notice is-dismissible">
            <p>
                <?php
                echo esc_html( sprintf(
                    /* translators: %s: number of orders updated. */
                    _n( '%s pedido alterado para "Pedido enviado".', '%s pedidos alterados para "Pedido enviado".', $count, 'hubgo' ),
                    number_format_i18n( $count )
                ) );
                ?>
            </p>
        </div>
        <?php
    }
}

