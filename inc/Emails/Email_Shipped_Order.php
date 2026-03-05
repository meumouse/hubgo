<?php

namespace MeuMouse\Hubgo\Emails;

use WC_Email;

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
        $this->description = __( 'Enviado quando o pedido e marcado como enviado.', 'hubgo' );
        $this->customer_email = true;

        $this->template_html = 'emails/hubgo-shipped-order.php';
        $this->template_plain = 'emails/plain/hubgo-shipped-order.php';
        $this->template_base = HUBGO_PATH . 'templates/';

        add_action( 'Hubgo/Tracking/Order_Shipped', array( $this, 'trigger' ) );

        parent::__construct();

        $this->subject = $this->get_option( 'subject', $this->get_default_subject() );
        $this->heading = $this->get_option( 'heading', $this->get_default_heading() );
    }


    /**
     * Get default email subject.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_default_subject() {
        return __( 'Seu pedido foi enviado', 'hubgo' );
    }


    /**
     * Get default email heading.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_default_heading() {
        return __( 'Pedido enviado', 'hubgo' );
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

        if ( ! $this->object ) {
            return;
        }

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
     * Get email content HTML.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $this,
            ),
            '',
            $this->template_base
        );
    }


    /**
     * Get email content plain text.
     *
     * @since 2.1.0
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text' => true,
                'email' => $this,
            ),
            '',
            $this->template_base
        );
    }
}
