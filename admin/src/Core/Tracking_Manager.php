<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Core\Providers_Registry;

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
     * @param int $order_id | Order ID.
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
     * @param int $order_id | Order ID.
     * @param array $data | Tracking data.
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

        /**
         * Fired when a tracking item is saved for an order.
         *
         * @since 2.1.0
         * @param int $order_id Order ID.
         * @param array $item Saved item.
         * @param array $items Full tracking list for order.
         */
        do_action( 'Hubgo/Tracking/Item_Saved', $order_id, $item, $items );

        return $item;
    }


    /**
     * Delete item.
     *
     * @since 2.1.0
     * @param int $order_id | Order ID
     * @param string $tracking_id | Tracking ID
     * @return int|bool Meta ID if the key did not exist, true on successful update, false on failure or if the value passed to the function is the same as the one that is already in the database.
     */
    public function delete_item( $order_id, $tracking_id ) {
        $items = $this->get_items( $order_id );

        foreach ( $items as $key => $item ) {
            if ( isset( $item['tracking_id'] ) && $item['tracking_id'] === $tracking_id ) {
                unset( $items[ $key ] );
            }
        }

        $updated = update_post_meta( $order_id, self::META_KEY, array_values( $items ) );

        if ( $updated ) {
            /**
             * Fired hook when delete tracking item from order
             *
             * @since 2.1.0
             * @param int $order_id | Order ID
             * @param array $items | Post meta data of shipping details
             */
            do_action( 'Hubgo/Tracking/Deleted_Item', $order_id, $items );
        }

        return $updated;
    }


    /**
     * Get tracking items enriched with display fields (for REST/JS rendering).
     *
     * Adds provider_label, tracking_link and date_label so the client can render
     * items without any server-side HTML.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @return array
     */
    public function get_items_for_display( $order_id ) {
        $items = $this->get_items( $order_id );

        foreach ( $items as &$item ) {
            $item['provider_label'] = $this->get_provider_label( $item );
            $item['tracking_link']  = $this->get_display_tracking_link( $order_id, $item );
            $item['date_label']     = $this->get_date_label( $item );
        }

        unset( $item );

        return $items;
    }


    /**
     * Resolve a display provider label from an item.
     *
     * @since 3.0.0
     * @param array $item Tracking item.
     * @return string
     */
    private function get_provider_label( $item ) {
        if ( ! empty( $item['custom_provider'] ) ) {
            return $item['custom_provider'];
        }

        if ( ! empty( $item['provider'] ) ) {
            return $item['provider'];
        }

        if ( ! empty( $item['carrier'] ) ) {
            return $item['carrier'];
        }

        return __( 'Transportadora não definida', 'hubgo' );
    }


    /**
     * Resolve a tracking link for display.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @param array $item Tracking item.
     * @return string
     */
    private function get_display_tracking_link( $order_id, $item ) {
        if ( ! empty( $item['custom_url'] ) ) {
            return esc_url_raw( $item['custom_url'] );
        }

        $provider = ! empty( $item['provider'] ) ? $item['provider'] : ( $item['carrier'] ?? '' );

        if ( empty( $provider ) || empty( $item['tracking_number'] ) ) {
            return '';
        }

        $order = wc_get_order( $order_id );
        $country = $order ? ( $order->get_shipping_country() ?: $order->get_billing_country() ) : '';
        $country = $country ?: 'Brazil';

        return Providers_Registry::get_tracking_url( $provider, $item['tracking_number'], '', $country, $order_id );
    }


    /**
     * Build a human-readable ship date label.
     *
     * @since 3.0.0
     * @param array $item Tracking item.
     * @return string
     */
    private function get_date_label( $item ) {
        if ( empty( $item['ship_date'] ) ) {
            return __( 'Sem data de envio', 'hubgo' );
        }

        $timestamp = strtotime( $item['ship_date'] );

        if ( ! $timestamp ) {
            return sprintf( __( 'Enviado em %s', 'hubgo' ), $item['ship_date'] );
        }

        return sprintf( __( 'Enviado em %s', 'hubgo' ), wp_date( get_option( 'date_format' ), $timestamp ) );
    }


    /**
     * Trigger shipped event
     *
     * @since 2.1.0
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

