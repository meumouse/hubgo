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
 * @author MeuMouse.com
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
     * @param int $order_id Order ID.
     * @return array
     */
    public function get_items( $order_id ) {
        $items = get_post_meta( $order_id, self::META_KEY, true );

        if ( ! is_array( $items ) ) {
            $items = array();
        }

        foreach ( $items as &$item ) {
            if ( empty( $item['provider'] ) && ! empty( $item['carrier'] ) ) {
                $item['provider'] = sanitize_text_field( $item['carrier'] );
            }

            if ( empty( $item['tracking_id'] ) ) {
                $item['tracking_id'] = uniqid( 'hubgo_', true );
            }
        }

        return apply_filters( 'Hubgo/Tracking/Get_Items', $items, $order_id );
    }


    /**
     * Add tracking item to order
     *
     * @since 2.1.0
     * @param int   $order_id Order ID.
     * @param array $data Tracking data.
     * @return array
     */
    public function add_item( $order_id, $data ) {
        $items = $this->get_items( $order_id );
        $provider = isset( $data['provider'] ) ? $data['provider'] : ( $data['carrier'] ?? '' );

        $item = array(
            'tracking_id'     => uniqid( 'hubgo_', true ),
            'tracking_number' => sanitize_text_field( $data['tracking_number'] ),
            'provider'        => sanitize_text_field( $provider ),
            'custom_provider' => sanitize_text_field( $data['custom_provider'] ?? '' ),
            'custom_url'      => esc_url_raw( $data['custom_url'] ?? '' ),
            'ship_date'       => sanitize_text_field( $data['ship_date'] ?? '' ),
        );

        $items[] = $item;

        update_post_meta( $order_id, self::META_KEY, $items );

        return $item;
    }


    /**
     * Delete item.
     *
     * @since 2.1.0
     * @param int    $order_id
     * @param string $tracking_id
     * @return int|bool Meta ID if the key didn’t exist, true on successful update, false on failure or if the value passed to the function is the same as the one that is already in the database.
     */
    public function delete_item( $order_id, $tracking_id ) {
        $items = $this->get_items( $order_id );

        foreach ( $items as $key => $item ) {
            if ( isset( $item['tracking_id'] ) && $item['tracking_id'] === $tracking_id ) {
                unset( $items[ $key ] );
            }
        }

        return update_post_meta( $order_id, self::META_KEY, array_values( $items ) );
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
        $items = $this->get_items( $order_id );

        /**
         * Fired hook when order status is updated to 'shipped-order'
         *
         * @since 2.1.0
         * @param int $order_id | Order ID
         * @param array $items | Post meta data of shipping details
         */
        do_action( 'Hubgo/Tracking/Order_Shipped', $order_id, $items );
    }
}
