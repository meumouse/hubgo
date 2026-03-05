<?php
/**
 * Email tracking info (plain text).
 *
 * @package Hubgo
 */

defined( 'ABSPATH' ) || exit;

echo "\n";
echo "=================================\n";
echo wp_strip_all_tags( __( 'Informacoes de rastreio', 'hubgo' ) ) . "\n";
echo "=================================\n\n";

foreach ( $items as $item ) {
    echo wp_strip_all_tags( __( 'Transportadora:', 'hubgo' ) ) . ' ' . wp_strip_all_tags( $item['provider'] ) . "\n";
    echo wp_strip_all_tags( __( 'Codigo:', 'hubgo' ) ) . ' ' . wp_strip_all_tags( $item['tracking_number'] ) . "\n";

    if ( ! empty( $item['ship_date'] ) ) {
        echo wp_strip_all_tags( __( 'Data de envio:', 'hubgo' ) ) . ' ' . wp_strip_all_tags( $item['ship_date'] ) . "\n";
    }

    if ( ! empty( $item['url'] ) ) {
        echo wp_strip_all_tags( __( 'Link de rastreio:', 'hubgo' ) ) . ' ' . esc_url_raw( $item['url'] ) . "\n";
    }

    echo "\n";
}
