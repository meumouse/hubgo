<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Core\Delivery_Estimate;

use WC_Abstract_Order;
use WC_Order_Item_Shipping;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Persists the delivery date promised at the checkout onto the order.
 *
 * Up to 3.0.0 the delivery forecast lived only in the calculator response: the
 * storefront advertised "Get it by August 14" and the moment the shopper paid,
 * that promise was gone. Nothing downstream — the order screen, a WhatsApp
 * notification, a late-delivery check — could tell what had been promised, so
 * every consumer would have had to re-quote the carrier and hope the answer had
 * not changed in the meantime.
 *
 * WooCommerce copies a rate's meta onto the shipping line item when the order is
 * created ({@see \WC_Order_Item_Shipping::set_shipping_rate()}), so the carrier
 * forecast is already on the order — under whichever key the carrier chose.
 * This class reads it through the same key list the calculator uses, adds the
 * store's handling time exactly once, and stores the resolved calendar date.
 *
 * The capture is idempotent: an order that already carries a promise is left
 * alone, so a re-entrant checkout hook never re-dates a shipment.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Delivery_Promise {

    /**
     * Order meta: promised delivery date, as Y-m-d.
     *
     * @since 3.0.0
     * @var string
     */
    const META_DATE = '_hubgo_delivery_date';

    /**
     * Order meta: business days behind the promise, handling time included.
     *
     * @since 3.0.0
     * @var string
     */
    const META_DAYS = '_hubgo_delivery_days';

    /**
     * Order meta: carrier that will actually move the parcel.
     *
     * Distinct from the shipping method title: a Frenet or Melhor Envio rate is
     * fulfilled by Correios, Jadlog or Loggi, and that is the name a customer
     * needs to hear.
     *
     * @since 3.0.0
     * @var string
     */
    const META_CARRIER = '_hubgo_delivery_carrier';

    /**
     * Order meta: shipping method title chosen at the checkout.
     *
     * @since 3.0.0
     * @var string
     */
    const META_METHOD = '_hubgo_delivery_method';

    /**
     * Rate meta keys that may carry the carrier name, in priority order.
     *
     * @since 3.0.0
     * @var array<int,string>
     */
    const CARRIER_META_KEYS = array( 'carrier', '_carrier', 'carrier_name', 'transportadora' );


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        // Both checkouts are covered: the classic one and the Store API used by
        // the block checkout, which never runs WC_Checkout::process_checkout().
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'capture_from_checkout' ), 20, 3 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'capture_from_store_api' ), 20, 1 );
    }


    /**
     * Capture the promise after the classic checkout created the order.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @param array $posted_data Checkout payload (unused).
     * @param mixed $order Order object, when WooCommerce passed one.
     * @return void
     */
    public function capture_from_checkout( $order_id, $posted_data = array(), $order = null ) {
        if ( ! $order instanceof WC_Abstract_Order ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $order_id ) ) : false;
        }

        $this->capture( $order );
    }


    /**
     * Capture the promise after the Store API created the order.
     *
     * @since 3.0.0
     * @param mixed $order Order object.
     * @return void
     */
    public function capture_from_store_api( $order ) {
        $this->capture( $order );
    }


    /**
     * Resolve and store the promise for an order.
     *
     * @since 3.0.0
     * @param mixed $order Order object.
     * @return array<string,mixed> The stored promise, empty when there was none.
     */
    public function capture( $order ) {
        if ( ! $order instanceof WC_Abstract_Order ) {
            return array();
        }

        // Already promised: never move a date a customer may have been told.
        if ( '' !== (string) $order->get_meta( self::META_DATE, true ) ) {
            return self::get( $order );
        }

        $promise = $this->build_from_order( $order );

        if ( empty( $promise['date'] ) ) {
            return array();
        }

        $order->update_meta_data( self::META_DATE, $promise['date'] );
        $order->update_meta_data( self::META_DAYS, (string) $promise['days'] );
        $order->update_meta_data( self::META_CARRIER, $promise['carrier'] );
        $order->update_meta_data( self::META_METHOD, $promise['method'] );
        $order->save();

        /**
         * Fired once a delivery promise has been stored on an order.
         *
         * @since 3.0.0
         * @param int $order_id Order ID.
         * @param array<string,mixed> $promise Stored promise.
         */
        do_action( 'Hubgo/Delivery/Promise_Saved', $order->get_id(), $promise );

        return $promise;
    }


    /**
     * Build the promise from the order's shipping lines.
     *
     * The first line carrying a forecast wins. A split shipment quotes each
     * package separately, and the earliest promise is the one that would be
     * broken first — but announcing it would be a lie for the rest of the order,
     * so the order of the lines (the order the packages were quoted in) decides.
     *
     * @since 3.0.0
     * @param WC_Abstract_Order $order Order to read.
     * @return array<string,mixed> Empty array when no line carried a forecast.
     */
    private function build_from_order( $order ) {
        foreach ( $order->get_items('shipping') as $item ) {
            if ( ! $item instanceof WC_Order_Item_Shipping ) {
                continue;
            }

            $meta = $this->get_item_meta_map( $item );
            $days = Delivery_Estimate::get_days_from_meta( $meta );

            /**
             * Filters the carrier forecast read from an order shipping line.
             *
             * Lets an integration resolve a forecast the carrier did not write
             * onto the rate — the same escape hatch
             * `Hubgo/Shipping_Calculator/Delivery_Days` offers at quote time.
             *
             * @since 3.0.0
             * @param int|null $days Business days read from the line meta.
             * @param WC_Order_Item_Shipping $item Shipping line item.
             * @param WC_Abstract_Order $order Order being processed.
             */
            $days = apply_filters( 'Hubgo/Delivery/Promise_Days', $days, $item, $order );

            if ( null === $days ) {
                continue;
            }

            // Delivery_Estimate::build() adds the store handling time, so the
            // raw carrier number is what must go in — the same input the
            // storefront estimate was computed from.
            $estimate = Delivery_Estimate::build( (int) $days );

            if ( empty( $estimate['date'] ) ) {
                continue;
            }

            return array(
                'days'       => (int) $estimate['days'],
                'date'       => (string) $estimate['date'],
                'date_label' => (string) $estimate['date_label'],
                'headline'   => (string) $estimate['headline'],
                'carrier'    => $this->get_item_carrier( $meta ),
                'method'     => sanitize_text_field( (string) $item->get_method_title() ),
            );
        }

        return array();
    }


    /**
     * Flatten a shipping line's meta into a key => value map.
     *
     * @since 3.0.0
     * @param WC_Order_Item_Shipping $item Shipping line item.
     * @return array<string,mixed>
     */
    private function get_item_meta_map( $item ) {
        $map = array();

        foreach ( $item->get_meta_data() as $meta ) {
            $data = $meta->get_data();

            if ( ! isset( $data['key'] ) || ! is_scalar( $data['value'] ) ) {
                continue;
            }

            $map[ (string) $data['key'] ] = $data['value'];
        }

        return $map;
    }


    /**
     * Read the carrier name out of a shipping line's meta.
     *
     * @since 3.0.0
     * @param array<string,mixed> $meta Line meta map.
     * @return string Empty string when the rate carried no carrier.
     */
    private function get_item_carrier( $meta ) {
        /**
         * Filters the meta keys scanned for the carrier behind a rate.
         *
         * @since 3.0.0
         * @param array<int,string> $keys Meta keys in priority order.
         */
        $keys = apply_filters( 'Hubgo/Delivery/Carrier_Meta_Keys', self::CARRIER_META_KEYS );

        foreach ( (array) $keys as $key ) {
            if ( empty( $meta[ $key ] ) || ! is_scalar( $meta[ $key ] ) ) {
                continue;
            }

            return sanitize_text_field( (string) $meta[ $key ] );
        }

        return '';
    }


    /**
     * Read the promise stored on an order.
     *
     * @since 3.0.0
     * @param mixed $order Order object or ID.
     * @return array<string,mixed> Empty array when the order carries no promise.
     */
    public static function get( $order ) {
        $order = self::resolve_order( $order );

        if ( ! $order ) {
            return array();
        }

        $date = (string) $order->get_meta( self::META_DATE, true );

        if ( '' === $date ) {
            return array();
        }

        $timestamp = strtotime( $date );

        return array(
            'days'       => (int) $order->get_meta( self::META_DAYS, true ),
            'date'       => $date,
            'date_label' => $timestamp ? wp_date( get_option('date_format'), $timestamp ) : $date,
            'carrier'    => (string) $order->get_meta( self::META_CARRIER, true ),
            'method'     => (string) $order->get_meta( self::META_METHOD, true ),
            'timestamp'  => $timestamp ? (int) $timestamp : 0,
        );
    }


    /**
     * Carrier promised for an order.
     *
     * @since 3.0.0
     * @param mixed $order Order object or ID.
     * @return string
     */
    public static function get_carrier( $order ) {
        $promise = self::get( $order );

        return isset( $promise['carrier'] ) ? (string) $promise['carrier'] : '';
    }


    /**
     * Whether an order's promised delivery date has already passed.
     *
     * @since 3.0.0
     * @param mixed $order Order object or ID.
     * @param int $grace_days Days of tolerance beyond the promise.
     * @return bool
     */
    public static function is_overdue( $order, $grace_days = 0 ) {
        $promise = self::get( $order );

        if ( empty( $promise['timestamp'] ) ) {
            return false;
        }

        $deadline = $promise['timestamp'] + ( max( 0, (int) $grace_days ) * DAY_IN_SECONDS );

        return current_datetime()->setTime( 0, 0, 0 )->getTimestamp() > $deadline;
    }


    /**
     * Resolve an order object from an ID or object.
     *
     * @since 3.0.0
     * @param mixed $order Order object or ID.
     * @return WC_Abstract_Order|false
     */
    private static function resolve_order( $order ) {
        if ( $order instanceof WC_Abstract_Order ) {
            return $order;
        }

        if ( ! function_exists( 'wc_get_order' ) ) {
            return false;
        }

        return wc_get_order( absint( $order ) );
    }
}
