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
     * Get tracking items from order.
     *
     * Reads through the WooCommerce CRUD layer so the items resolve under both
     * storage engines. With HPOS enabled (and post/table synchronisation off)
     * order meta no longer lives in wp_postmeta, so the previous get_post_meta()
     * read returned nothing — the tracking metabox rendered empty and the
     * Joinotify "order shipped" notification went out with a blank tracking code
     * and link. Legacy post meta is still read as a fallback for orders stored
     * before a migration.
     *
     * @since 2.1.0
     * @version 3.0.0
     * @param int $order_id | Order ID.
     * @return array
     */
    public function get_items( $order_id ) {
        return apply_filters( 'Hubgo/Tracking/Get_Items', $this->read_items( $order_id ), $order_id );
    }


    /**
     * Read the stored tracking items without running the read filter.
     *
     * Every write path has to start from what is actually persisted:
     * `Hubgo/Tracking/Get_Items` lets an integration inject items owned by
     * another plugin (the Shipment Tracking bridge does), and feeding a
     * filtered list back into {@see self::persist_items()} would silently
     * copy that foreign data into HubGo's own meta on the next save.
     *
     * @since 3.0.0
     * @param int $order_id | Order ID.
     * @return array
     */
    private function read_items( $order_id ) {
        $order = $this->get_order( $order_id );
        $items = $order ? $order->get_meta( self::META_KEY, true ) : array();

        if ( ! is_array( $items ) || empty( $items ) ) {
            $legacy = get_post_meta( $order_id, self::META_KEY, true );

            if ( is_array( $legacy ) && ! empty( $legacy ) ) {
                $items = $legacy;
            }
        }

        if ( ! is_array( $items ) ) {
            $items = array();
        }

        foreach ( $items as &$item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            if ( empty( $item['provider'] ) && ! empty( $item['carrier'] ) ) {
                $item['provider'] = sanitize_text_field( $item['carrier'] );
            }

            if ( empty( $item['tracking_id'] ) ) {
                $item['tracking_id'] = uniqid( 'hubgo_', true );
            }
        }

        unset( $item );

        return array_values( array_filter( $items, 'is_array' ) );
    }


    /**
     * Resolve the order object for an ID.
     *
     * @since 3.0.0
     * @param int $order_id | Order ID.
     * @return \WC_Order|\WC_Order_Refund|false
     */
    private function get_order( $order_id ) {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return false;
        }

        return wc_get_order( absint( $order_id ) );
    }


    /**
     * Persist the tracking item list on the order (HPOS-safe).
     *
     * @since 3.0.0
     * @param int $order_id | Order ID.
     * @param array $items | Tracking item list.
     * @return bool Whether the list was persisted.
     */
    private function persist_items( $order_id, $items ) {
        $items = array_values( $items );
        $order = $this->get_order( $order_id );

        if ( ! $order ) {
            // No CRUD object available (order deleted mid-request): fall back to
            // post meta rather than silently dropping the data.
            return (bool) update_post_meta( $order_id, self::META_KEY, $items );
        }

        $order->update_meta_data( self::META_KEY, $items );
        $order->save();

        return true;
    }


    /**
     * Add tracking item to order
     *
     * @since 2.1.0
     * @version 3.0.0
     * @param int $order_id | Order ID.
     * @param array $data | Tracking data.
     * @return array
     */
    public function add_item( $order_id, $data ) {
        $items = $this->read_items( $order_id );
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

        $this->persist_items( $order_id, $items );

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
     * @version 3.0.0
     * @param int $order_id | Order ID
     * @param string $tracking_id | Tracking ID
     * @return int|bool Meta ID if the key did not exist, true on successful update, false on failure or if the value passed to the function is the same as the one that is already in the database.
     */
    public function delete_item( $order_id, $tracking_id ) {
        $items = $this->read_items( $order_id );
        $removed = false;

        foreach ( $items as $key => $item ) {
            if ( isset( $item['tracking_id'] ) && $item['tracking_id'] === $tracking_id ) {
                unset( $items[ $key ] );
                $removed = true;
            }
        }

        // Nothing matched: report failure instead of persisting an identical list
        // (update_post_meta returns false for an unchanged value, which used to
        // make a successful no-op look like a storage error).
        if ( ! $removed ) {
            return false;
        }

        $updated = $this->persist_items( $order_id, $items );

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
     * Import tracking items produced by another plugin.
     *
     * Used by the migration layer, which converts a third-party item list into
     * the HubGo shape. Unlike {@see self::add_item()} this deliberately does
     * **not** fire `Hubgo/Tracking/Item_Saved`: that hook drives the Joinotify
     * "tracking code added" automation, and replaying it over a store's whole
     * order history would send one WhatsApp message per historical shipment.
     *
     * Items whose `source_tracking_id` (or tracking number) is already present
     * are skipped, so a migration can be interrupted and re-run safely.
     *
     * @since 3.0.0
     * @param int $order_id | Order ID.
     * @param array $items | Items to import, already in the HubGo item shape.
     * @param string $source | Identifier of the plugin the items came from.
     * @return int Number of items appended.
     */
    public function import_items( $order_id, $items, $source = '' ) {
        if ( ! is_array( $items ) || empty( $items ) ) {
            return 0;
        }

        $current = $this->read_items( $order_id );
        $known = array();

        foreach ( $current as $item ) {
            $known = array_merge( $known, $this->get_item_fingerprints( $item ) );
        }

        $imported = array();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || empty( $item['tracking_number'] ) ) {
                continue;
            }

            $item = $this->sanitize_imported_item( $item, $source );
            $fingerprints = $this->get_item_fingerprints( $item );

            if ( array_intersect( $fingerprints, $known ) ) {
                continue;
            }

            $known = array_merge( $known, $fingerprints );
            $imported[] = $item;
        }

        if ( empty( $imported ) ) {
            return 0;
        }

        $this->persist_items( $order_id, array_merge( $current, $imported ) );

        /**
         * Fired after third-party tracking items are imported into an order.
         *
         * @since 3.0.0
         * @param int $order_id | Order ID.
         * @param array $imported | Items that were appended.
         * @param string $source | Identifier of the source plugin.
         */
        do_action( 'Hubgo/Tracking/Items_Imported', $order_id, $imported, $source );

        return count( $imported );
    }


    /**
     * Normalize one imported item to the stored HubGo shape.
     *
     * @since 3.0.0
     * @param array $item | Raw imported item.
     * @param string $source | Identifier of the source plugin.
     * @return array
     */
    private function sanitize_imported_item( $item, $source ) {
        return array(
            'tracking_id'        => uniqid( 'hubgo_', true ),
            'tracking_number'    => sanitize_text_field( $item['tracking_number'] ),
            'provider'           => sanitize_text_field( $item['provider'] ?? '' ),
            'custom_provider'    => sanitize_text_field( $item['custom_provider'] ?? '' ),
            'custom_url'         => esc_url_raw( $item['custom_url'] ?? '' ),
            'ship_date'          => sanitize_text_field( $item['ship_date'] ?? '' ),
            'source'             => sanitize_text_field( '' !== (string) $source ? $source : ( $item['source'] ?? '' ) ),
            'source_tracking_id' => sanitize_text_field( $item['source_tracking_id'] ?? '' ),
        );
    }


    /**
     * Build the values used to tell two tracking items apart on import.
     *
     * Both forms are emitted so an import is skipped either way: the source ID
     * catches a re-run of the same migration, and the carrier + number pair
     * catches a code the shop manager had already typed into HubGo by hand.
     *
     * @since 3.0.0
     * @param array $item | Tracking item.
     * @return array<int,string>
     */
    private function get_item_fingerprints( $item ) {
        if ( ! is_array( $item ) ) {
            return array();
        }

        $fingerprints = array(
            'number:' . strtolower( trim( (string) ( $item['tracking_number'] ?? '' ) ) )
                . '|' . strtolower( trim( $this->get_carrier_name( $item ) ) ),
        );

        if ( ! empty( $item['source_tracking_id'] ) ) {
            $fingerprints[] = 'source:' . (string) $item['source_tracking_id'];
        }

        return $fingerprints;
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
            $item['carrier_name']      = $this->get_carrier_name( $item );
            $item['provider_label']    = $this->get_provider_label( $item );
            $item['tracking_link']     = $this->get_display_tracking_link( $order_id, $item );
            $item['ship_date_label']   = $this->format_ship_date( $item['ship_date'] ?? '' );
            $item['date_label']        = $this->get_date_label( $item );
        }

        unset( $item );

        return $items;
    }


    /**
     * Resolve the carrier name stored on an item, without any display fallback.
     *
     * Use this when the value is interpolated into text the customer reads (a
     * WhatsApp message, an e-mail): an unset carrier must render as an empty
     * string, never as the "not defined" placeholder copy.
     *
     * @since 3.0.0
     * @param array $item Tracking item.
     * @return string
     */
    public function get_carrier_name( $item ) {
        if ( ! is_array( $item ) ) {
            return '';
        }

        foreach ( array( 'custom_provider', 'provider', 'carrier' ) as $key ) {
            if ( ! empty( $item[ $key ] ) ) {
                return (string) $item[ $key ];
            }
        }

        return '';
    }


    /**
     * Resolve a display provider label from an item.
     *
     * Same resolution as {@see Tracking_Manager::get_carrier_name()} plus the
     * placeholder copy used by UI surfaces. Public so every consumer — metabox,
     * orders list, account page, e-mails and integrations — shows one label.
     *
     * @since 3.0.0
     * @param array $item Tracking item.
     * @return string
     */
    public function get_provider_label( $item ) {
        $carrier = $this->get_carrier_name( $item );

        return '' !== $carrier ? $carrier : __( 'Transportadora não definida', 'hubgo' );
    }


    /**
     * Resolve a tracking link for display.
     *
     * Public so integrations (e.g. the Joinotify placeholders) build the same
     * link the admin and storefront show, instead of duplicating the rules.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @param array $item Tracking item.
     * @return string
     */
    public function get_display_tracking_link( $order_id, $item ) {
        if ( ! is_array( $item ) ) {
            return '';
        }

        // An explicit URL always wins, whatever carrier is selected.
        if ( ! empty( $item['custom_url'] ) ) {
            return esc_url_raw( $item['custom_url'] );
        }

        // The registry is keyed by the catalog carrier, so a free-typed
        // custom_provider has no URL template and must not be looked up.
        $provider = ! empty( $item['provider'] ) ? (string) $item['provider'] : (string) ( $item['carrier'] ?? '' );

        if ( '' === $provider || empty( $item['tracking_number'] ) ) {
            return '';
        }

        return (string) Providers_Registry::get_tracking_url(
            $provider,
            $item['tracking_number'],
            '',
            $this->get_order_country( $order_id ),
            $order_id
        );
    }


    /**
     * Resolve the country used to pick a carrier's URL template.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @return string
     */
    public function get_order_country( $order_id ) {
        $order = $this->get_order( $order_id );

        if ( ! $order ) {
            return 'Brazil';
        }

        $country = $order->get_shipping_country();

        if ( empty( $country ) ) {
            $country = $order->get_billing_country();
        }

        return ! empty( $country ) ? $country : 'Brazil';
    }


    /**
     * Format a raw ship date for presentation (no surrounding copy).
     *
     * @since 3.0.0
     * @param string $ship_date Raw stored date.
     * @return string Formatted date, or an empty string when unset.
     */
    public function format_ship_date( $ship_date ) {
        $ship_date = (string) $ship_date;

        if ( '' === $ship_date ) {
            return '';
        }

        $timestamp = strtotime( $ship_date );

        return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $ship_date;
    }


    /**
     * Build a human-readable ship date label.
     *
     * @since 3.0.0
     * @param array $item Tracking item.
     * @return string
     */
    public function get_date_label( $item ) {
        $formatted = $this->format_ship_date( is_array( $item ) && isset( $item['ship_date'] ) ? $item['ship_date'] : '' );

        if ( '' === $formatted ) {
            return __( 'Sem data de envio', 'hubgo' );
        }

        /* translators: %s: formatted shipping date. */
        return sprintf( __( 'Enviado em %s', 'hubgo' ), $formatted );
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

