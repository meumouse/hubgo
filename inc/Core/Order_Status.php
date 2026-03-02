<?php

namespace MeuMouse\Hubgo\Core;

defined('ABSPATH') || exit;

/**
 * Class Order_Status
 *
 * Registers custom WooCommerce order status "shipped-order".
 *
 * @since 2.1.0
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
        add_action( 'init', array( $this, 'register_status' ) );
        add_filter( 'wc_order_statuses', array( $this, 'add_status_to_list' ) );
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
}