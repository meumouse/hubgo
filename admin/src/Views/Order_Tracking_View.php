<?php

namespace MeuMouse\Hubgo\Views;

use MeuMouse\Hubgo\Core\Providers_Registry;
use MeuMouse\Hubgo\Core\Tracking_Manager;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Order_Tracking_View
 *
 * Displays tracking information on frontend and emails.
 *
 * @since 2.1.0
 * @package MeuMouse\Hubgo\Views
 * @author MeuMouse.com
 */
class Order_Tracking_View {

    /**
     * Tracking manager
     *
     * @since 2.1.0
     * @var Tracking_Manager
     */
    protected $tracking;

    /**
     * Constructor
     *
     * @since 2.1.0
     *
     * @param Tracking_Manager $tracking Tracking manager instance.
     */
    public function __construct( Tracking_Manager $tracking ) {
        $this->tracking = $tracking;

        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_tracking_myaccount' ) );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'render_tracking_email' ), 10, 4 );
    }


    /**
     * Render tracking info at My Account order details.
     *
     * @since 2.1.0
     *
     * @param \WC_Order|int $order Order object or ID.
     * @return void
     */
    public function render_tracking_myaccount( $order ) {
        $order = $this->get_order_object( $order );

        if ( ! $order ) {
            return;
        }

        $items = $this->get_template_items( $order );

        if ( empty( $items ) ) {
            return;
        }

        wc_get_template(
            'myaccount/hubgo-tracking-info.php',
            array(
                'order' => $order,
                'items' => $items,
            ),
            '',
            HUBGO_PATH . 'templates/'
        );
    }


    /**
     * Render tracking info in WooCommerce emails.
     *
     * @since 2.1.0
     *
     * @param \WC_Order|int $order Order object or ID.
     * @param bool          $sent_to_admin If email is for admin.
     * @param bool          $plain_text If email is plain text.
     * @return void
     */
    public function render_tracking_email( $order, $sent_to_admin = false, $plain_text = false ) {
        $order = $this->get_order_object( $order );

        if ( ! $order || $sent_to_admin ) {
            return;
        }

        $items = $this->get_template_items( $order );

        if ( empty( $items ) ) {
            return;
        }

        $template = $plain_text
            ? 'email/plain/hubgo-tracking-info.php'
            : 'email/hubgo-tracking-info.php';

        wc_get_template(
            $template,
            array(
                'order' => $order,
                'items' => $items,
            ),
            '',
            HUBGO_PATH . 'templates/'
        );
    }


    /**
     * Resolve order object.
     *
     * @since 2.1.0
     *
     * @param \WC_Order|int $order Order object or ID.
     * @return \WC_Order|null
     */
    protected function get_order_object( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        return $order instanceof \WC_Order ? $order : null;
    }


    /**
     * Build sanitized tracking items for templates.
     *
     * @since 2.1.0
     *
     * @param \WC_Order $order Order object.
     * @return array
     */
    protected function get_template_items( \WC_Order $order ) {
        $items = $this->tracking->get_items( $order->get_id() );
        $prepared = array();

        foreach ( $items as $item ) {
            $provider = ! empty( $item['custom_provider'] )
                ? (string) $item['custom_provider']
                : ( $item['provider'] ?? ( $item['carrier'] ?? '' ) );
            $tracking_number = isset( $item['tracking_number'] ) ? (string) $item['tracking_number'] : '';

            if ( '' === $tracking_number ) {
                continue;
            }

            $url = ! empty( $item['custom_url'] )
                ? (string) $item['custom_url']
                : Providers_Registry::get_tracking_url(
                    $item['provider'] ?? ( $item['carrier'] ?? '' ),
                    $tracking_number,
                    '',
                    $this->get_order_country( $order ),
                    $order->get_id()
                );

            $prepared[] = array(
                'provider' => $provider,
                'tracking_number' => $tracking_number,
                'url' => $url,
                'ship_date' => $this->format_ship_date( $item['ship_date'] ?? '' ),
            );
        }

        return $prepared;
    }


    /**
     * Get order country code fallback.
     *
     * @since 2.1.0
     * @param \WC_Order $order Order object.
     * @return string
     */
    protected function get_order_country( \WC_Order $order ) {
        $country = $order->get_shipping_country();

        if ( empty( $country ) ) {
            $country = $order->get_billing_country();
        }

        return $country ? $country : 'Brazil';
    }


    /**
     * Format shipment date for presentation.
     *
     * @since 2.1.0
     * @param string $ship_date Raw shipment date.
     * @return string
     */
    protected function format_ship_date( $ship_date ) {
        if ( empty( $ship_date ) ) {
            return '';
        }

        $timestamp = strtotime( $ship_date );

        if ( ! $timestamp ) {
            return (string) $ship_date;
        }

        return wp_date( get_option( 'date_format' ), $timestamp );
    }
}
