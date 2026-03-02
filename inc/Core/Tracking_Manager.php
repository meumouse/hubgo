<?php

namespace MeuMouse\Hubgo\Core;

use WC_Order;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Tracking_Manager
 *
 * Handles tracking items storage and retrieval for WooCommerce orders.
 *
 * @since 2.1.0
 * @package MeuMouse\Hubgo\Core
 */
class Tracking_Manager {

    /**
     * Order meta key for tracking items
     *
     * @since 2.1.0
     * @var string
     */
    const META_KEY = '_hubgo_tracking_items';

    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        add_action( 'woocommerce_order_status_shipped-order', array( $this, 'trigger_shipped_event' ) );
    }

    
    /**
     * Get tracking items from order
     *
     * @since 2.1.0
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function get_tracking_items( $order_id ) {
        $items = get_post_meta( $order_id, self::META_KEY, true );

        if ( ! is_array( $items ) ) {
            $items = array();
        }

        return apply_filters( 'Hubgo/Tracking/Get_Tracking_Items', $items, $order_id );
    }


    /**
     * Add tracking item to order
     *
     * @since 2.1.0
     *
     * @param int   $order_id Order ID.
     * @param array $data Tracking data.
     * @return void
     */
    public function add_tracking_item( $order_id, $data ) {
        $items = $this->get_tracking_items( $order_id );

        $items[] = apply_filters( 'Hubgo/Tracking/Add_Tracking_Item_Data', array(
            'tracking_number' => sanitize_text_field( $data['tracking_number'] ?? '' ),
            'carrier'         => sanitize_text_field( $data['carrier'] ?? '' ),
            'custom_url'      => esc_url_raw( $data['custom_url'] ?? '' ),
            'date_added'      => current_time( 'mysql' ),
        ), $order_id );

        update_post_meta( $order_id, self::META_KEY, $items );
    }


    /**
     * Delete tracking item
     *
     * @since 2.1.0
     *
     * @param int $order_id Order ID.
     * @param int $index Item index.
     * @return void
     */
    public function delete_tracking_item( $order_id, $index ) {
        $items = $this->get_tracking_items( $order_id );

        if ( isset( $items[ $index ] ) ) {
            unset( $items[ $index ] );
            update_post_meta( $order_id, self::META_KEY, array_values( $items ) );
        }
    }


    /**
     * Trigger shipped event
     *
     * @since 2.1.0
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function trigger_shipped_event( $order_id ) {
        do_action( 'Hubgo/Tracking/Order_Shipped', $order_id );
    }
}