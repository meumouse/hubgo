<?php

namespace MeuMouse\Hubgo\Emails;

use WC_Email;
use MeuMouse\Hubgo\Core\Tracking_Manager;

defined('ABSPATH') || exit;

/**
 * Class Email_Shipped_Order
 *
 * Custom email sent when order is marked as shipped.
 *
 * @since 2.1.0
 */
class Email_Shipped_Order extends WC_Email {

    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {

        $this->id = 'hubgo_shipped_order';
        $this->title = __( 'Pedido enviado', 'hubgo' );
        $this->description = __( 'Enviado quando o pedido é marcado como enviado.', 'hubgo' );
        $this->customer_email = true;

        add_action( 'Hubgo/Tracking/Order_Shipped', array( $this, 'trigger' ) );

        parent::__construct();
    }


    /**
     * Trigger email
     *
     * @since 2.1.0
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function trigger( $order_id ) {
        if ( ! $order_id ) {
            return;
        }

        $this->object = wc_get_order( $order_id );
        $this->recipient = $this->object->get_billing_email();

        if ( ! $this->is_enabled() || ! $this->recipient ) {
            return;
        }

        $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );
    }

    
    /**
     * Get email content HTML
     *
     * @since 2.1.0
     * @return string
     */
    public function get_content_html() {
        ob_start();

        wc_get_template(
            'emails/hubgo-shipped-order.php',
            array(
                'order' => $this->object,
            )
        );

        return ob_get_clean();
    }
}