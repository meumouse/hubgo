<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Delivery_Promise;
use MeuMouse\Hubgo\Core\Tracking_Manager;

use MeuMouse\Joinotify\Integrations\Woocommerce;

defined('ABSPATH') || exit;

/**
 * Joinotify v2 integration for HubGo.
 *
 * Uses ONLY the public functional API introduced in Joinotify v2
 * (joinotify_register_integration / joinotify_register_trigger /
 * joinotify_register_placeholders / joinotify_dispatch_trigger). The legacy v1
 * hook surface (Builder/Get_All_Triggers, Builder/Triggers(_Content),
 * Placeholders_List, Workflow_Processor::process_workflows) is no longer used.
 *
 * Tracking data is always resolved through {@see Tracking_Manager} so the
 * carrier name and tracking link a notification carries are byte-for-byte the
 * ones the order screen and the customer account page show. The delivery tokens
 * follow the same rule against {@see Delivery_Promise}: a message quotes the
 * date the shopper was actually promised at the checkout, never a fresh quote
 * that may have drifted since.
 *
 * @since 2.1.0
 * @version 3.1.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Joinotify extends Integrations_Base {

    /**
     * Integration slug / trigger context.
     *
     * @var string
     */
    const SLUG = 'hubgo';

    /**
     * Card slug on the HubGo Integrations screen.
     *
     * @since 3.0.0
     * @var string
     */
    const CARD_SLUG = 'joinotify';

    /**
     * Trigger identifiers.
     */
    const TRIGGER_ORDER_SHIPPED    = 'Hubgo/Tracking/Order_Shipped';
    const TRIGGER_ITEM_SAVED       = 'Hubgo/Tracking/Item_Saved';
    const TRIGGER_DELIVERY_OVERDUE = 'Hubgo/Delivery/Overdue';

    /**
     * Option key toggling the integration in Joinotify's settings.
     *
     * @var string
     */
    const SETTING_KEY = 'enable_hubgo_integration';

    /**
     * Option key toggling the integration on the HubGo side.
     *
     * @since 3.0.0
     * @var string
     */
    const HUBGO_SETTING_KEY = 'enable_joinotify_integration';

    /**
     * Plugin basename of the host plugin.
     *
     * @since 3.0.0
     * @var string
     */
    const PLUGIN_FILE = 'joinotify/joinotify.php';

    /**
     * Tracking manager used to normalize items.
     *
     * @since 3.0.0
     * @var Tracking_Manager|null
     */
    protected $tracking = null;


    /**
     * Constructor.
     *
     * @since 3.0.0
     * @version 3.0.0
     */
    public function __construct() {
        // The card is registered unconditionally: the Integrations screen must
        // list the integration even when Joinotify is absent or switched off,
        // otherwise there is no way for the user to discover or enable it.
        $this->register_integration_card( 10 );

        if ( ! $this->is_supported() || ! $this->is_enabled() ) {
            return;
        }

        $this->register();

        // Runtime dispatch listeners (HubGo -> Joinotify).
        add_action( self::TRIGGER_ORDER_SHIPPED, array( $this, 'handle_order_shipped' ), 10, 2 );
        add_action( self::TRIGGER_ITEM_SAVED, array( $this, 'handle_tracking_saved' ), 10, 3 );
        add_action( self::TRIGGER_DELIVERY_OVERDUE, array( $this, 'handle_delivery_overdue' ), 10, 2 );

        // Give the builder's trigger cards the HubGo brand icon instead of the
        // generic fallback used for unregistered contexts.
        add_filter( 'Joinotify/Builder/Trigger_Context_Icons', array( $this, 'register_context_icon' ) );
    }


    /**
     * Register the HubGo card on the Integrations screen.
     *
     * Note this is the HubGo-side switch. Joinotify keeps its own toggle for
     * the HubGo card inside its Applications tab (see self::SETTING_KEY); both
     * must be on for notifications to flow, which the modal spells out.
     *
     * @since 3.0.0
     * @param array $integrations Current catalog.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations[ self::CARD_SLUG ] = array(
            'title'            => __( 'Joinotify', 'hubgo' ),
            'description'      => __( 'Send automatic WhatsApp messages when an order is shipped, a tracking code is saved or a delivery is running late.', 'hubgo' ),
            'icon'             => $this->get_card_icon_svg(),
            'author'           => 'MeuMouse.com',
            'author_url'       => 'https://meumouse.com',
            'category'         => 'notifications',
            'setting_key'      => self::HUBGO_SETTING_KEY,
            'is_plugin'        => true,
            'plugin_active'    => array( self::PLUGIN_FILE ),
            'requires_license' => true,
            'doc_url'          => 'https://ajuda.meumouse.com/docs/joinotify/overview',
            'install'          => array(
                'plugin_slug' => self::PLUGIN_FILE,
                'label'       => __( 'Discover Joinotify', 'hubgo' ),
            ),
            'modal'            => array(
                'title'       => __( 'Joinotify', 'hubgo' ),
                'description' => __( 'Logistics triggers available in the Joinotify flow builder.', 'hubgo' ),
                'size'        => 'medium',
                'blocks'      => array(
                    self::modal_notice_block(
                        __( 'Besides this switch, the "HubGo" card must also be enabled on the Joinotify Applications tab.', 'hubgo' ),
                        'info'
                    ),
                ),
            ),
        );

        return $integrations;
    }


    /**
     * Whether the HubGo-side toggle is on.
     *
     * @since 3.0.0
     * @return bool
     */
    private function is_enabled() {
        return 'yes' === Settings::get_setting( self::HUBGO_SETTING_KEY, 'yes' );
    }


    /**
     * Version guard: only run against Joinotify v2+ with the functional API.
     *
     * @since 3.0.0
     * @return bool
     */
    private function is_supported() {
        if ( ! function_exists( 'joinotify_register_integration' )
            || ! function_exists( 'joinotify_register_trigger' )
            || ! function_exists( 'joinotify_dispatch_trigger' ) ) {
            return false;
        }

        if ( defined( 'JOINOTIFY_VERSION' ) && version_compare( JOINOTIFY_VERSION, '2.0', '<' ) ) {
            return false;
        }

        return true;
    }


    /**
     * Lazily resolve the tracking manager.
     *
     * @since 3.0.0
     * @return Tracking_Manager
     */
    protected function tracking() {
        if ( null === $this->tracking ) {
            $this->tracking = new Tracking_Manager();
        }

        return $this->tracking;
    }


    /**
     * Register the integration card, triggers and placeholders.
     *
     * @since 3.0.0
     * @return void
     */
    public function register() {
        joinotify_register_integration( array(
            'slug'        => self::SLUG,
            'title'       => __( 'HubGo', 'hubgo' ),
            'description' => __( 'Send automatic WhatsApp messages from logistics events such as order shipped and tracking code saved, connecting HubGo to Joinotify.', 'hubgo' ),
            'icon'        => $this->get_icon_svg(),
            'category'    => 'ecommerce',
            'setting_key' => self::SETTING_KEY,
            'defaults'    => array(
                self::SETTING_KEY => 'no',
            ),
        ) );

        joinotify_register_trigger( self::SLUG, array(
            'data_trigger'     => self::TRIGGER_ORDER_SHIPPED,
            'title'            => __( 'Order shipped', 'hubgo' ),
            'description'      => __( 'Fired when the order status changes to Order shipped.', 'hubgo' ),
            'require_settings' => false,
            'icon'             => $this->get_icon_svg(),
        ) );

        joinotify_register_trigger( self::SLUG, array(
            'data_trigger'     => self::TRIGGER_ITEM_SAVED,
            'title'            => __( 'When a tracking code is saved on the order', 'hubgo' ),
            'description'      => __( 'Fired when a tracking code is saved on the order.', 'hubgo' ),
            'require_settings' => false,
            'icon'             => $this->get_icon_svg(),
        ) );

        joinotify_register_trigger( self::SLUG, array(
            'data_trigger'     => self::TRIGGER_DELIVERY_OVERDUE,
            'title'            => __( 'Delivery is late', 'hubgo' ),
            'description'      => __( 'Fired once a day for each shipped order whose promised delivery date has passed.', 'hubgo' ),
            'require_settings' => false,
            'icon'             => $this->get_icon_svg(),
        ) );

        if ( function_exists( 'joinotify_register_placeholders' ) ) {
            joinotify_register_placeholders( self::SLUG, $this->get_placeholders() );
        }
    }


    /**
     * Map the HubGo trigger context to the brand icon.
     *
     * @since 3.0.0
     * @param array $icons Context slug => icon slug/markup.
     * @return array
     */
    public function register_context_icon( $icons ) {
        if ( ! is_array( $icons ) ) {
            $icons = array();
        }

        $icons[ self::SLUG ] = $this->get_icon_svg();

        return $icons;
    }


    /**
     * Build the placeholder map with runtime (production) resolvers.
     *
     * Every 'production' value is a callable( array $payload ): string — Joinotify
     * resolves it at send time. Sandbox values are static previews for the builder.
     *
     * Both triggers are listed on every token: since Joinotify 2.1 the `triggers`
     * list is enforced at SEND time (the runtime payload carries the fired trigger
     * slug), so a token missing the fired trigger is left unresolved in the
     * message. The slugs must match the registered `data_trigger` values exactly.
     *
     * @since 3.0.0
     * @return array
     */
    private function get_placeholders() {
        $triggers = array( self::TRIGGER_ORDER_SHIPPED, self::TRIGGER_ITEM_SAVED, self::TRIGGER_DELIVERY_OVERDUE );

        $order_from = function( $payload ) {
            $order_id = isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0;

            return ( $order_id && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order_id ) : null;
        };

        $tracking_from = function( $payload, $key ) {
            $data = isset( $payload['tracking_data'] ) && is_array( $payload['tracking_data'] ) ? $payload['tracking_data'] : array();

            return isset( $data[ $key ] ) ? (string) $data[ $key ] : '';
        };

        // The delivery promise is read off the order rather than the payload, so
        // the tokens resolve the same on every trigger — including the two that
        // predate it and know nothing about a delivery date.
        $promise_from = function( $payload, $key ) {
            $order_id = isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0;
            $promise = $order_id ? Delivery_Promise::get( $order_id ) : array();

            return isset( $promise[ $key ] ) ? (string) $promise[ $key ] : '';
        };

        return array(
            '{{ hubgo_carrier_name }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Carrier name', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Express Delivery', 'hubgo' ),
                    'production' => function( $payload ) use ( $tracking_from, $promise_from ) {
                        $carrier = $tracking_from( $payload, 'carrier_name' );

                        // No carrier typed on the tracking code yet: fall back to
                        // the one quoted at the checkout, which is the same name
                        // the customer saw on the product page.
                        return '' !== $carrier ? $carrier : $promise_from( $payload, 'carrier' );
                    },
                ),
            ),
            '{{ hubgo_delivery_date }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Delivery date promised at the checkout', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => wp_date( get_option('date_format') ),
                    'production' => function( $payload ) use ( $promise_from ) {
                        return $promise_from( $payload, 'date_label' );
                    },
                ),
            ),
            '{{ hubgo_delivery_days }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Business days promised at the checkout', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '5',
                    'production' => function( $payload ) use ( $promise_from ) {
                        $days = $promise_from( $payload, 'days' );

                        return '0' !== $days ? $days : '';
                    },
                ),
            ),
            '{{ hubgo_shipping_method }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Shipping method chosen at the checkout', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Express Delivery', 'hubgo' ),
                    'production' => function( $payload ) use ( $promise_from ) {
                        return $promise_from( $payload, 'method' );
                    },
                ),
            ),
            '{{ hubgo_tracking_link }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Tracking link', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => 'https://carrier.example/tracking/BR1234567890',
                    'production' => function( $payload ) use ( $tracking_from ) {
                        return $tracking_from( $payload, 'tracking_link' );
                    },
                ),
            ),
            '{{ hubgo_tracking_code }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Tracking code', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => 'BR1234567890',
                    'production' => function( $payload ) use ( $tracking_from ) {
                        return $tracking_from( $payload, 'tracking_code' );
                    },
                ),
            ),
            '{{ hubgo_shipping_date }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Shipping date', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => wp_date( get_option( 'date_format' ) ),
                    'production' => function( $payload ) use ( $tracking_from ) {
                        $date = $tracking_from( $payload, 'shipping_date' );
                        $timestamp = $date ? strtotime( $date ) : false;

                        return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $date;
                    },
                ),
            ),
            '{{ hubgo_tracking_count }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Number of tracking codes registered on the order', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '2',
                    'production' => function( $payload ) {
                        $items = isset( $payload['tracking_items'] ) && is_array( $payload['tracking_items'] ) ? $payload['tracking_items'] : array();

                        return (string) count( $items );
                    },
                ),
            ),
            '{{ hubgo_tracking_list }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Every tracking code on the order, one per line (carrier, code and link). It can also be used as the source of a loop action.', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => "Express Delivery - BR1234567890 - https://carrier.example/tracking/BR1234567890\nStandard Post - JD9876543210 - https://carrier.example/tracking/JD9876543210",
                    'production' => function( $payload ) {
                        return $this->format_tracking_list( $payload );
                    },
                ),
            ),
            '{{ wc_billing_first_name }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Customer billing first name (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'John', 'hubgo' ),
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return $order ? $order->get_billing_first_name() : '';
                    },
                ),
            ),
            '{{ wc_billing_last_name }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Customer billing last name (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Doe', 'hubgo' ),
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return $order ? $order->get_billing_last_name() : '';
                    },
                ),
            ),
            '{{ wc_billing_email }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Customer billing e-mail (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => 'user@example.com',
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return $order ? $order->get_billing_email() : '';
                    },
                ),
            ),
            '{{ wc_billing_phone }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Order billing phone (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '+55 11 91234-5678',
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return $order ? $order->get_billing_phone() : '';
                    },
                ),
            ),
            '{{ wc_shipping_phone }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Order shipping phone (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '+55 41 91234-5678',
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        // get_shipping_phone() only exists from WooCommerce 5.6.
                        if ( ! $order || ! method_exists( $order, 'get_shipping_phone' ) ) {
                            return '';
                        }

                        return $order->get_shipping_phone();
                    },
                ),
            ),
            '{{ wc_order_number }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Order number (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '12345',
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return $order ? $order->get_order_number() : '';
                    },
                ),
            ),
            '{{ wc_order_status }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Order status (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Order shipped', 'hubgo' ),
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return ( $order && function_exists( 'wc_get_order_status_name' ) ) ? wc_get_order_status_name( $order->get_status() ) : '';
                    },
                ),
            ),
            '{{ wc_order_total }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Order total (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => function_exists( 'joinotify_format_plain_text' ) && function_exists( 'wc_price' ) ? joinotify_format_plain_text( wc_price( 150 ) ) : '150.00',
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        if ( ! $order || ! function_exists( 'joinotify_format_plain_text' ) || ! function_exists( 'wc_price' ) ) {
                            return '';
                        }

                        return joinotify_format_plain_text( wc_price( $order->get_total() ) );
                    },
                ),
            ),
            '{{ wc_billing_full_address }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Customer full billing address (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( '123 Flower Street - Curitiba/PR - Brazil (postcode: 80000-000)', 'hubgo' ),
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return ( $order && class_exists( Woocommerce::class ) ) ? Woocommerce::get_full_address( $order, 'billing' ) : '';
                    },
                ),
            ),
            '{{ wc_shipping_full_address }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Customer full shipping address (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( '450 Daisy Street - Curitiba/PR - Brazil (postcode: 80000-100)', 'hubgo' ),
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return ( $order && class_exists( Woocommerce::class ) ) ? Woocommerce::get_full_address( $order, 'shipping' ) : '';
                    },
                ),
            ),
            '{{ wc_purchased_items }}' => array(
                'triggers'    => $triggers,
                'description' => __( 'Products and quantities purchased on the order, one per line', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => "1x - Men's cotton t-shirt (sample product)\n1x - UV protection sunglasses (sample product)",
                    'production' => function( $payload ) use ( $order_from ) {
                        $order = $order_from( $payload );

                        return ( $order && class_exists( Woocommerce::class ) ) ? Woocommerce::get_purchased_items( $order ) : '';
                    },
                ),
            ),
        );
    }


    /**
     * Handle order shipped -> dispatch Joinotify trigger.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @param array $items Tracking items.
     * @return void
     */
    public function handle_order_shipped( $order_id, $items ) {
        $items = is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();

        // The most recently added item is the one the shipping notification is
        // about; the full list still travels on the payload for the aggregate
        // tokens and for loop actions.
        $latest = ! empty( $items ) ? end( $items ) : array();

        $this->dispatch(
            self::TRIGGER_ORDER_SHIPPED,
            absint( $order_id ),
            is_array( $latest ) ? $latest : array(),
            $items,
            __( 'Order shipped', 'hubgo' )
        );
    }


    /**
     * Handle tracking saved -> dispatch Joinotify trigger.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @param array $item Saved tracking item.
     * @param array $all_items All items.
     * @return void
     */
    public function handle_tracking_saved( $order_id, $item, $all_items = array() ) {
        $all_items = is_array( $all_items ) ? array_values( array_filter( $all_items, 'is_array' ) ) : array();

        $this->dispatch(
            self::TRIGGER_ITEM_SAVED,
            absint( $order_id ),
            is_array( $item ) ? $item : array(),
            $all_items,
            __( 'Tracking code saved on the order', 'hubgo' )
        );
    }


    /**
     * Handle a late delivery -> dispatch Joinotify trigger.
     *
     * The order's tracking codes travel on the payload as well, so a "your
     * parcel is running late" message can still carry the tracking link the
     * customer needs to check it themselves.
     *
     * @since 3.1.0
     * @param int $order_id Order ID.
     * @param array $promise Delivery promise stored on the order.
     * @return void
     */
    public function handle_delivery_overdue( $order_id, $promise = array() ) {
        $order_id = absint( $order_id );
        $items = $order_id ? $this->tracking()->get_items( $order_id ) : array();
        $items = is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();
        $latest = ! empty( $items ) ? end( $items ) : array();

        $this->dispatch(
            self::TRIGGER_DELIVERY_OVERDUE,
            $order_id,
            is_array( $latest ) ? $latest : array(),
            $items,
            __( 'Delivery is late', 'hubgo' ),
            array( 'delivery_promise' => is_array( $promise ) ? $promise : array() )
        );
    }


    /**
     * Dispatch a HubGo trigger to Joinotify workflows.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @param string $hook Trigger identifier.
     * @param int $order_id Order ID.
     * @param array $tracking_item Primary tracking item.
     * @param array $all_items Every tracking item on the order.
     * @param string $description Human description.
     * @param array $extra Extra payload entries merged into the dispatch.
     * @return void
     */
    protected function dispatch( $hook, $order_id, $tracking_item, $all_items, $description, $extra = array() ) {
        if ( ! function_exists( 'joinotify_dispatch_trigger' ) ) {
            return;
        }

        if ( 'yes' !== $this->get_setting( self::SETTING_KEY ) ) {
            return;
        }

        if ( ! $order_id ) {
            return;
        }

        $payload = array_merge( is_array( $extra ) ? $extra : array(), array(
            'order_id'       => $order_id,
            'tracking_data'  => $this->build_tracking_data( $order_id, $tracking_item ),
            'tracking_items' => $this->build_tracking_items( $order_id, $all_items ),
            'description'    => $description,
        ) );

        /**
         * Filter the HubGo payload before dispatching to Joinotify.
         *
         * @since 3.0.0
         * @param array $payload Dispatch payload.
         * @param string $hook Trigger identifier.
         */
        $payload = apply_filters( 'Hubgo/Integrations/Joinotify/Payload', $payload, $hook );

        joinotify_dispatch_trigger( $hook, self::SLUG, $payload );
    }


    /**
     * Read a Joinotify setting value (v2 helper) with a safe fallback.
     *
     * Joinotify's Admin::get_setting() returns false for a key that has not been
     * persisted yet, so the value is cast before comparison.
     *
     * @since 3.0.0
     * @param string $key Setting key.
     * @return string
     */
    private function get_setting( $key ) {
        if ( ! function_exists( 'joinotify_get_setting' ) ) {
            return 'no';
        }

        $value = joinotify_get_setting( $key );

        return is_scalar( $value ) ? (string) $value : 'no';
    }


    /**
     * Build normalized tracking data for the payload/placeholders.
     *
     * Carrier label and tracking link come from {@see Tracking_Manager} so the
     * notification always matches what the order screen and the customer account
     * page display, including the per-provider URL templates and the custom URL
     * override.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @param array $item Tracking item.
     * @return array
     */
    protected function build_tracking_data( $order_id, $item ) {
        $empty = array(
            'carrier_name'  => '',
            'tracking_link' => '',
            'tracking_code' => '',
            'shipping_date' => '',
        );

        if ( ! is_array( $item ) || empty( $item ) ) {
            return $empty;
        }

        $tracking = $this->tracking();
        $tracking_code = isset( $item['tracking_number'] ) ? (string) $item['tracking_number'] : '';

        // get_carrier_name() (not get_provider_label()) because a message must
        // render an empty token for an unset carrier, never the UI's
        // "Carrier not set" placeholder copy.
        return array(
            'carrier_name'  => sanitize_text_field( $tracking->get_carrier_name( $item ) ),
            'tracking_link' => esc_url_raw( $tracking->get_display_tracking_link( $order_id, $item ) ),
            'tracking_code' => sanitize_text_field( $tracking_code ),
            'shipping_date' => isset( $item['ship_date'] ) ? sanitize_text_field( (string) $item['ship_date'] ) : '',
        );
    }


    /**
     * Normalize every tracking item on the order for the payload.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @param array $items Raw tracking items.
     * @return array<int,array<string,string>>
     */
    protected function build_tracking_items( $order_id, $items ) {
        if ( ! is_array( $items ) || empty( $items ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) || empty( $item['tracking_number'] ) ) {
                continue;
            }

            $normalized[] = $this->build_tracking_data( $order_id, $item );
        }

        return $normalized;
    }


    /**
     * Render every tracking item as one line of "carrier - code - link".
     *
     * The newline-delimited shape is what Joinotify's loop action expects from a
     * "placeholder list" source, so a workflow can send one message per tracking
     * code by looping over {{ hubgo_tracking_list }}.
     *
     * @since 3.0.0
     * @param array $payload Runtime payload.
     * @return string
     */
    protected function format_tracking_list( $payload ) {
        $items = isset( $payload['tracking_items'] ) && is_array( $payload['tracking_items'] ) ? $payload['tracking_items'] : array();
        $lines = array();

        foreach ( $items as $item ) {
            $parts = array_filter( array(
                isset( $item['carrier_name'] ) ? $item['carrier_name'] : '',
                isset( $item['tracking_code'] ) ? $item['tracking_code'] : '',
                isset( $item['tracking_link'] ) ? $item['tracking_link'] : '',
            ), function( $value ) {
                return '' !== trim( (string) $value );
            } );

            if ( ! empty( $parts ) ) {
                $lines[] = implode( ' - ', $parts );
            }
        }

        /**
         * Filter the rendered tracking list.
         *
         * @since 3.0.0
         * @param string $list Newline-delimited list.
         * @param array $items Normalized tracking items.
         * @param array $payload Runtime payload.
         */
        return apply_filters( 'Hubgo/Integrations/Joinotify/Tracking_List', implode( "\n", $lines ), $items, $payload );
    }


    /**
     * Joinotify brand mark used on the HubGo Integrations card.
     *
     * The card represents the *other* product, so it carries the Joinotify
     * logo — unlike self::get_icon_svg(), which brands HubGo's own triggers
     * inside the Joinotify builder.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_card_icon_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 703 882.5" role="img" aria-label="Joinotify">'
            . '<path d="M908.66,248V666a126.5,126.5,0,0,1-207.21,97.41l-16.7-16.7L434.08,496.07l-62-62a47.19,47.19,0,0,0-72,30.86V843.36a47.52,47.52,0,0,0,69.57,35.22l19.3-19.3,56-56,81.19-81.19,10.44-10.44a47.65,47.65,0,0,1,67.63,65.05l-13,13L428.84,952.12l-9.59,9.59a128,128,0,0,1-213.59-95.18V413.17a124.52,124.52,0,0,1,199.78-82.54l22.13,22.13L674.45,599.64l46.22,46.22,17,17a47.8,47.8,0,0,0,71-31.44V270.19a48.19,48.19,0,0,0-75-40.05L720.43,243.4l-68.09,68.09L575.7,388.13a48.39,48.39,0,0,1-67.43-67.93L680,148.46A136,136,0,0,1,908.66,248Z" transform="translate(-205.66 -112.03)" fill="#22c55e"/>'
            . '</svg>';
    }


    /**
     * HubGo icon SVG for the trigger cards inside the Joinotify builder.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_icon_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 272.84 152.99" role="img" aria-label="HubGo"><g transform="translate(-363.58 -216.05)"><g fill="#008aff"><path d="M601.94,295.67c1.75,4.52,6.86,0,6.56-3.29l1.78-39.26-16.7,9.05Z"/><path d="M630.77,217.59c-8.62,1.84-17.75,2.72-24.54,9.12-13.16,10.11-36.44,30.92-54.15,39.8-55,25.09-115.12,40.9-172.42,38.09-20.59-1.47-21.89,30.28-1.11,30.42,44.74-3.17,90.28-13.37,130.27-30.66,27.77-11.46,51.52-28.55,77.3-42.73,11.29-6.59,35.93-16.07,43.66-27.39C633.38,229.34,642.18,220,630.77,217.59Z"/><path d="M552.62,221.23l27.84,21,14.94-11.94-37.19-13.79C555.26,214.92,549.15,217.87,552.62,221.23Z"/></g><g fill="#232323"><path d="M445.26,242.32c.23-16.14-25.11-16.14-24.88,0v54.27c7.51-.78,15.8-1.94,24.88-3.6Z"/><path d="M420.38,356.84c-.22,16.13,25.11,16.14,24.88,0V332.36q-12.22,2.77-24.88,4.86Z"/><path d="M533.72,267.22v-24.9c-.07-16.27-24.83-16.27-24.9,0v33.95C516.73,273.58,525.33,270.54,533.72,267.22Z"/><path d="M508.82,356.84c.07,16.27,24.83,16.26,24.9,0V300.43q-12.12,6.29-24.9,11.7Z"/></g></g></svg>';
    }
}
