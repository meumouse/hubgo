<?php

namespace MeuMouse\Hubgo\Views;

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

        $items = $this->tracking->get_tracking_items( $order->get_id() );

        if ( empty( $items ) ) {
            return;
        }

        echo '<section class="hubgo-tracking-section">';
        echo '<h2>' . esc_html__( 'Tracking Information', 'hubgo' ) . '</h2>';

        foreach ( $items as $item ) {

            $url = ! empty( $item['custom_url'] )
                ? $item['custom_url']
                : apply_filters(
                    'Hubgo/Tracking/default_tracking_url',
                    '',
                    $item
                );

            echo '<p>';
            echo '<strong>' . esc_html( $item['carrier'] ) . '</strong><br>';
            echo esc_html( $item['tracking_number'] );

            if ( $url ) {
                echo '<br><a href="' . esc_url( $url ) . '" target="_blank">' .
                     esc_html__( 'Track shipment', 'hubgo' ) .
                     '</a>';
            }

            echo '</p>';
        }

        echo '</section>';
    }
}