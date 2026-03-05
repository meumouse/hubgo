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

        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_tracking' ) );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'render_tracking' ), 10, 1 );
    }


    /**
     * Render tracking info
     *
     * @since 2.1.0
     *
     * @param \WC_Order|int $order Order object or ID.
     * @return void
     */
    public function render_tracking( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order ) {
            return;
        }

        $items = $this->tracking->get_items( $order->get_id() );

        if ( empty( $items ) ) {
            return;
        }

        echo '<section class="hubgo-tracking-section">';
        echo '<h2>' . esc_html__( 'Tracking Information', 'hubgo' ) . '</h2>';

        foreach ( $items as $item ) {
            $provider = ! empty( $item['custom_provider'] )
                ? $item['custom_provider']
                : ( $item['provider'] ?? ( $item['carrier'] ?? '' ) );

            $tracking_number = $item['tracking_number'] ?? '';

            $url = ! empty( $item['custom_url'] )
                ? $item['custom_url']
                : Providers_Registry::get_tracking_url(
                    $item['provider'] ?? ( $item['carrier'] ?? '' ),
                    $tracking_number,
                    '',
                    $order->get_shipping_country() ? $order->get_shipping_country() : 'Brazil',
                    $order->get_id()
                );

            echo '<p>';
            echo '<strong>' . esc_html( $provider ) . '</strong><br>';
            echo esc_html( $tracking_number );

            if ( $url ) {
                echo '<br><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html__( 'Track shipment', 'hubgo' )
                    . '</a>';
            }

            echo '</p>';
        }

        echo '</section>';
    }
}
