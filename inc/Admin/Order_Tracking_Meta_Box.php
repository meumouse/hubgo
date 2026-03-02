<?php

namespace MeuMouse\Hubgo\Admin;

use MeuMouse\Hubgo\Core\Tracking_Manager;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Order_Tracking_Meta_Box
 *
 * Adds tracking management meta box to WooCommerce order admin screen.
 *
 * @since 2.1.0
 * @package MeuMouse\Hubgo\Admin
 */
class Order_Tracking_Meta_Box {

    /**
     * Tracking manager instance
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

        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
        add_action( 'save_post_shop_order', array( $this, 'save_tracking_data' ) );
    }


    /**
     * Register meta box
     *
     * @since 2.1.0
     *
     * @return void
     */
    public function register_meta_box() {
        add_meta_box(
            'hubgo_order_tracking',
            __( 'HubGo Tracking', 'hubgo' ),
            array( $this, 'render_meta_box' ),
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Render meta box
     *
     * @since 2.1.0
     *
     * @param \WP_Post $post Order post.
     * @return void
     */
    public function render_meta_box( $post ) {
        $items = $this->tracking->get_tracking_items( $post->ID );

        wp_nonce_field( 'hubgo_save_tracking', 'hubgo_tracking_nonce' ); ?>

        <div id="hubgo-tracking-wrapper">
            <?php foreach ( $items as $index => $item ) : ?>
                <p>
                    <strong><?php echo esc_html( $item['tracking_number'] ); ?></strong><br>
                    <?php echo esc_html( $item['carrier'] ); ?>
                </p>
            <?php endforeach; ?>

            <hr>

            <p>
                <input type="text" name="hubgo_tracking_number" placeholder="Tracking number" style="width:100%;" />
            </p>
            <p>
                <input type="text" name="hubgo_tracking_carrier" placeholder="Carrier (ex: Correios)" style="width:100%;" />
            </p>
            <p>
                <input type="url" name="hubgo_tracking_url" placeholder="Tracking URL (optional)" style="width:100%;" />
            </p>
        </div>
        <?php
    }


    /**
     * Save tracking data
     *
     * @since 2.1.0
     *
     * @param int $post_id Order ID.
     * @return void
     */
    public function save_tracking_data( $post_id ) {
        if ( ! isset( $_POST['hubgo_tracking_nonce'] ) || ! wp_verify_nonce( $_POST['hubgo_tracking_nonce'], 'hubgo_save_tracking' ) ) {
            return;
        }

        if ( ! empty( $_POST['hubgo_tracking_number'] ) ) {
            $this->tracking->add_tracking_item( $post_id, array(
                'tracking_number' => $_POST['hubgo_tracking_number'],
                'carrier'         => $_POST['hubgo_tracking_carrier'] ?? '',
                'custom_url'      => $_POST['hubgo_tracking_url'] ?? '',
            ) );
        }
    }
}