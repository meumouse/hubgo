<?php
/**
 * Shipped order email (plain text).
 *
 * @package Hubgo
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . wp_strip_all_tags( $email_heading ) . " =\n\n";
echo wp_strip_all_tags( __( 'Seu pedido foi enviado e ja esta a caminho.', 'hubgo' ) ) . "\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
