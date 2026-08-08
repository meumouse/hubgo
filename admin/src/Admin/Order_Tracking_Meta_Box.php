<?php

namespace MeuMouse\Hubgo\Admin;

use MeuMouse\Hubgo\Admin\Settings;

use MeuMouse\Hubgo\Core\Providers_Registry;
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
 * @author MeuMouse.com
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
     * Plugin version
     * 
     * @since 2.1.0
     * @return string
     */
    protected $version = HUBGO_VERSION;

    /**
     * Order IDs already handled in this request, keyed by ID.
     *
     * Guards against the duplicate save hooks documented on
     * {@see Order_Tracking_Meta_Box::save_tracking_data()}.
     *
     * @since 3.0.0
     * @var array<int,bool>
     */
    protected $saved_orders = array();

    /**
     * Constructor
     *
     * @since 2.1.0
     * @param Tracking_Manager $tracking Tracking manager instance.
     * @return void
     */
    public function __construct( Tracking_Manager $tracking ) {
        $this->tracking = $tracking;


        if ( 'yes' !== Settings::get_setting( 'enable_order_tracking_admin_ui', Settings::get_default_value( 'enable_order_tracking_admin_ui', 'yes' ) ) ) {
            return;
        }
        add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
        add_action( 'save_post_shop_order', array( $this, 'save_tracking_data' ) );
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_tracking_data' ) );
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_orders_list_column' ), 20 );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_shop_order_list_column' ), 20, 2 );
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_orders_list_column' ), 20 );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_wc_orders_list_column' ), 20, 2 );
    }


    /**
     * Register meta box
     *
     * @since 2.1.0
     * @return void
     */
    public function register_meta_box() {
        foreach ( $this->get_order_screen_ids() as $screen_id ) {
            add_meta_box(
                'hubgo-order-tracking',
                __( 'Tracking - HubGo', 'hubgo' ),
                array( $this, 'render_meta_box' ),
                $screen_id,
                'side',
                'high'
            );
        }
    }




    /**
     * Render meta box.
     *
     * @since 2.1.0
     * @param mixed $post_or_order | Post or order object.
     * @return void
     */
    public function render_meta_box( $post_or_order ) {
        $order_id = $this->get_order_id_from_post_or_order( $post_or_order );

        if ( $order_id <= 0 ) {
            echo '<p>' . esc_html__( 'Order not found.', 'hubgo' ) . '</p>';

            return;
        }

        echo '<div id="hubgo-order-tracking-inner" data-order-id="' . esc_attr( $order_id ) . '">';
        echo '<div id="hubgo-tracking-items">';

            foreach ( $this->tracking->get_items( $order_id ) as $item ) {
                $this->render_tracking_item( $order_id, $item );
            }

            echo '</div>';

            echo '<button type="button" class="button button-show-form">' . esc_html__( 'Add tracking code', 'hubgo' ) . '</button>';

            echo '<div id="hubgo-shipment-tracking-form">';

            echo '<p class="form-field tracking_provider_field">';
                echo '<label for="hubgo_tracking_provider">' . esc_html__( 'Carrier:', 'hubgo' ) . '</label>';
                    echo '<select id="hubgo_tracking_provider" name="hubgo_tracking_provider" style="width:100%;">';
                        echo '<option value="">' . esc_html__( 'Custom carrier', 'hubgo' ) . '</option>';

                        foreach ( Providers_Registry::get_providers() as $provider_group => $providers ) {
                            echo '<optgroup label="' . esc_attr( Providers_Registry::get_country_label( $provider_group ) ) . '">';
                                foreach ( $providers as $provider => $format ) {
                                    if ( empty( $format ) ) {
                                        continue;
                                    }

                                    echo '<option value="' . esc_attr( $provider ) . '">' . esc_html( $provider ) . '</option>';
                                }
                            echo '</optgroup>';
                        }
                    echo '</select>';
                echo '</p>';

                echo '<p class="form-field custom_tracking_provider_field">';
                echo '<label for="hubgo_custom_tracking_provider">' . esc_html__( 'Carrier:', 'hubgo' ) . '</label>';

                echo '<input type="text" id="hubgo_custom_tracking_provider" name="hubgo_custom_tracking_provider" />';
            echo '</p>';

            echo '<p class="form-field">';
                echo '<label for="hubgo_tracking_number">' . esc_html__( 'Tracking code:', 'hubgo' ) . '</label>';
                echo '<input type="text" id="hubgo_tracking_number" name="hubgo_tracking_number" />';
            echo '</p>';

            echo '<p class="form-field custom_tracking_link_field">';
                echo '<label for="hubgo_custom_tracking_link">' . esc_html__( 'Tracking link:', 'hubgo' ) . '</label>';
                echo '<input type="url" id="hubgo_custom_tracking_link" name="hubgo_custom_tracking_link" placeholder="https://" />';
            echo '</p>';

            echo '<p class="form-field">';
                echo '<label for="hubgo_date_shipped">' . esc_html__( 'Shipping date:', 'hubgo' ) . '</label>';
                echo '<input type="date" id="hubgo_date_shipped" name="hubgo_date_shipped" value="' . esc_attr( gmdate( 'Y-m-d' ) ) . '" />';
            echo '</p>';

            echo '<button type="button" class="button button-primary button-save-form">' . esc_html__( 'Save tracking', 'hubgo' ) . '</button>';
            echo '<p class="preview_tracking_link">' . esc_html__( 'Preview:', 'hubgo' ) . ' <a href="" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Click here to track your parcel', 'hubgo' ) . '</a></p>';

        echo '</div>';

        wp_nonce_field( 'hubgo_save_tracking', 'hubgo_tracking_nonce' );

        echo '</div>';
    }


    /**
     * Save fallback tracking data on post save.
     *
     * On the classic (post-based) order screen WordPress fires
     * `save_post_shop_order` AND WooCommerce fires
     * `woocommerce_process_shop_order_meta` for the same request. Both are wired
     * so the handler also runs under HPOS, where only the WooCommerce hook
     * exists — but without the guard below a classic save stored the tracking
     * code twice and dispatched the Joinotify "tracking saved" trigger twice,
     * sending the customer two identical notifications.
     *
     * @since 2.1.0
     * @version 3.0.0
     * @param int $post_id Order ID.
     * @return void
     */
    public function save_tracking_data( $post_id ) {
        $post_id = absint( $post_id );

        if ( ! $post_id || isset( $this->saved_orders[ $post_id ] ) ) {
            return;
        }

        if ( ! isset( $_POST['hubgo_tracking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hubgo_tracking_nonce'] ) ), 'hubgo_save_tracking' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( empty( $_POST['hubgo_tracking_number'] ) ) {
            return;
        }

        $provider = isset( $_POST['hubgo_tracking_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['hubgo_tracking_provider'] ) ) : '';
        $custom_provider = isset( $_POST['hubgo_custom_tracking_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['hubgo_custom_tracking_provider'] ) ) : '';

        // Mark before storing: add_item() fires Hubgo/Tracking/Item_Saved, and a
        // listener could re-enter the order save.
        $this->saved_orders[ $post_id ] = true;

        $this->tracking->add_item(
            $post_id,
            array(
                'tracking_number' => sanitize_text_field( wp_unslash( $_POST['hubgo_tracking_number'] ) ),
                'provider'        => $provider,
                'custom_provider' => $custom_provider,
                'custom_url'      => isset( $_POST['hubgo_custom_tracking_link'] ) ? esc_url_raw( wp_unslash( $_POST['hubgo_custom_tracking_link'] ) ) : '',
                'ship_date'       => isset( $_POST['hubgo_date_shipped'] ) ? sanitize_text_field( wp_unslash( $_POST['hubgo_date_shipped'] ) ) : '',
            )
        );
    }


    /**
     * Render a single tracking item for metabox.
     *
     * @since 2.1.0
     * @param int   $order_id | Order ID.
     * @param array $item | Tracking item.
     * @return void
     */
    protected function render_tracking_item( $order_id, $item ) {
        $tracking_id = isset( $item['tracking_id'] ) ? $item['tracking_id'] : '';
        $provider = $this->tracking->get_provider_label( $item );
        $tracking_number = isset( $item['tracking_number'] ) ? $item['tracking_number'] : '';
        $tracking_link = $this->get_tracking_link( $order_id, $item );
        $ship_date = $this->tracking->get_date_label( $item );

        if ( empty( $tracking_id ) ) :
            return;
        endif; ?>
        
        <div class="tracking-item" id="tracking-item-<?php echo esc_attr( $tracking_id ); ?>">
            <p class="tracking-content">
                <strong><?php echo esc_html( $provider ); ?></strong>

                <?php if ( ! empty( $tracking_link ) ) : ?>
                    - <a href="<?php echo esc_url( $tracking_link ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Track', 'hubgo' ); ?></a>
                <?php endif; ?>

                <br>
                <em><?php echo esc_html( $tracking_number ); ?></em>
            </p>

            <p class="meta">
                <?php echo esc_html( $ship_date ); ?>

                <?php if ( ! empty( $item['read_only'] ) ) : ?>
                    <?php // Injected by an integration from another plugin's data: there is
                          // nothing in HubGo's own meta to remove, so offering the action
                          // would only produce a "tracking not found" error. ?>
                    <span class="tracking-origin"><?php echo esc_html( $item['source_label'] ?? __( 'Read only', 'hubgo' ) ); ?></span>
                <?php else : ?>
                    <a href="#" class="delete-tracking" rel="<?php echo esc_attr( $tracking_id ); ?>"><?php esc_html_e( 'Remove', 'hubgo' ); ?></a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }




    /**
     * Add tracking column to admin orders list.
     *
     * @since 2.1.0
     * @param array $columns Existing columns.
     * @return array
     */
    public function add_orders_list_column( $columns ) {
        $columns['hubgo_tracking'] = __( 'Tracking', 'hubgo' );

        return $columns;
    }


    /**
     * Render tracking column in classic orders table.
     *
     * @since 2.1.0
     * @param string $column_name Column key.
     * @param int    $post_id Order ID.
     * @return void
     */
    public function render_shop_order_list_column( $column_name, $post_id ) {
        if ( 'hubgo_tracking' !== $column_name ) {
            return;
        }

        echo wp_kses_post( $this->get_orders_list_tracking_column_content( absint( $post_id ) ) );
    }


    /**
     * Render tracking column in HPOS orders table.
     *
     * @since 2.1.0
     * @param string       $column_name Column key.
     * @param int|WC_Order $order Order object or ID.
     * @return void
     */
    public function render_wc_orders_list_column( $column_name, $order ) {
        if ( 'hubgo_tracking' !== $column_name ) {
            return;
        }

        $order_id = is_object( $order ) && method_exists( $order, 'get_id' ) ? absint( $order->get_id() ) : absint( $order );

        echo wp_kses_post( $this->get_orders_list_tracking_column_content( $order_id ) );
    }


    /**
     * Build tracking column content for order list.
     *
     * @since 2.1.0
     * @param int $order_id Order ID.
     * @return string
     */
    protected function get_orders_list_tracking_column_content( $order_id ) {
        if ( $order_id <= 0 ) {
            return '&ndash;';
        }

        $items = array_reverse( $this->tracking->get_items( $order_id ) );

        if ( empty( $items ) ) {
            return '&ndash;';
        }

        $output = '';

        foreach ( $items as $item ) {
            $provider = $this->tracking->get_provider_label( $item );
            $tracking_number = isset( $item['tracking_number'] ) ? (string) $item['tracking_number'] : '';
            $tracking_link = $this->get_tracking_link( $order_id, $item );

            if ( '' === $tracking_number ) {
                continue;
            }

            $line = esc_html( sprintf( '%1$s: %2$s', $provider, $tracking_number ) );

            if ( ! empty( $tracking_link ) ) {
                $line = sprintf(
                    '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
                    esc_url( $tracking_link ),
                    $line
                );
            }

            $output .= '<div class="hubgo-tracking-item-summary">' . $line . '</div>';
        }

        return '' !== $output ? $output : '&ndash;';
    }


    /**
     * Get tracking link from item.
     *
     * Thin proxy over {@see Tracking_Manager::get_display_tracking_link()} so the
     * metabox, the orders list, the account page and the Joinotify notification
     * all resolve the same URL.
     *
     * @since 2.1.0
     * @version 3.0.0
     * @param int   $order_id Order ID.
     * @param array $item Tracking item.
     * @return string
     */
    protected function get_tracking_link( $order_id, $item ) {
        return $this->tracking->get_display_tracking_link( $order_id, $item );
    }


    /**
     * Get order screens where metabox should be available.
     *
     * @since 2.1.0
     * @return array
     */
    protected function get_order_screen_ids() {
        $screens = array('shop_order');

        if ( function_exists('wc_get_page_screen_id') ) {
            $screens[] = wc_get_page_screen_id( 'shop-order' );
        }

        return array_filter( array_unique( $screens ) );
    }




    /**
     * Get order ID from request.
     *
     * @since 2.1.0
     * @return int
     */
    protected function get_order_id_from_request() {
        if ( isset( $_GET['id'] ) ) {
            return absint( $_GET['id'] );
        }

        if ( isset( $_GET['post'] ) ) {
            return absint( $_GET['post'] );
        }

        return 0;
    }


    /**
     * Get order ID from post or order object.
     *
     * @since 2.1.0
     * @param mixed $post_or_order_object WP_Post|WC_Order.
     * @return int
     */
    protected function get_order_id_from_post_or_order( $post_or_order_object ) {
        if ( is_object( $post_or_order_object ) && method_exists( $post_or_order_object, 'get_id' ) ) {
            return absint( $post_or_order_object->get_id() );
        }

        if ( isset( $post_or_order_object->ID ) ) {
            return absint( $post_or_order_object->ID );
        }

        return $this->get_order_id_from_request();
    }
}

