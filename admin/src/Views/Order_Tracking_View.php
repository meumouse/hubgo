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
        $prepared = array();

        // Carrier name, tracking URL and date all come from Tracking_Manager so
        // the account page, the e-mails, the order screen and the Joinotify
        // notification show exactly the same values. These used to be resolved
        // here independently, and the copies had drifted: the label preferred
        // `custom_provider` while the URL was always looked up from `provider`,
        // so a custom carrier rendered one name with another carrier's link.
        foreach ( $this->tracking->get_items_for_display( $order->get_id() ) as $item ) {
            $tracking_number = isset( $item['tracking_number'] ) ? (string) $item['tracking_number'] : '';

            if ( '' === $tracking_number ) {
                continue;
            }

            $prepared[] = array(
                'provider'        => $item['carrier_name'],
                'tracking_number' => $tracking_number,
                'url'             => $item['tracking_link'],
                'ship_date'       => $item['ship_date_label'],
            );
        }

        return $prepared;
    }
}
