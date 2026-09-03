<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Delivery_Promise;
use MeuMouse\Hubgo\Core\Providers_Registry;
use MeuMouse\Hubgo\Core\Tracking_Manager;

use MeuMouse\Joinotify\Integrations\Woocommerce;

defined('ABSPATH') || exit;

/**
 * Joinotify v2 integration for HubGo.
 *
 * Uses ONLY the public functional API introduced in Joinotify v2
 * (joinotify_register_integration / joinotify_register_trigger /
 * joinotify_register_placeholders / joinotify_register_conditions /
 * joinotify_dispatch_trigger). The legacy v1 hook surface
 * (Builder/Get_All_Triggers, Builder/Triggers(_Content), Placeholders_List,
 * Workflow_Processor::process_workflows) is no longer used. Verified against
 * Joinotify 2.3.4.
 *
 * Tracking data is always resolved through {@see Tracking_Manager} so the
 * carrier name and tracking link a notification carries are byte-for-byte the
 * ones the order screen and the customer account page show. The delivery tokens
 * follow the same rule against {@see Delivery_Promise}: a message quotes the
 * date the shopper was actually promised at the checkout, never a fresh quote
 * that may have drifted since.
 *
 * Every order-scoped hook HubGo fires is exposed as a trigger — shipped, tracking
 * code saved, tracking code removed, delivery promised and delivery late — except
 * `Hubgo/Tracking/Items_Imported`, which replays a store's whole shipment history
 * during a migration and would send one message per historical order.
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
     * HubGo action hooks this integration listens to.
     *
     * Every order-scoped hook HubGo fires is here, except
     * `Hubgo/Tracking/Items_Imported`: that one replays a store's whole shipment
     * history during a migration and would send one message per historical order.
     *
     * @since 3.1.0
     */
    const HOOK_ORDER_SHIPPED     = 'Hubgo/Tracking/Order_Shipped';
    const HOOK_ITEM_SAVED        = 'Hubgo/Tracking/Item_Saved';
    const HOOK_ITEM_DELETED      = 'Hubgo/Tracking/Deleted_Item';
    const HOOK_DELIVERY_PROMISED = 'Hubgo/Delivery/Promise_Saved';
    const HOOK_DELIVERY_OVERDUE  = 'Hubgo/Delivery/Overdue';

    /**
     * Trigger identifiers registered with Joinotify.
     *
     * These are deliberately NOT the HubGo hook names above. Joinotify runs a
     * trigger slug through `sanitize_key()` on the way in — when the builder
     * creates a workflow from a trigger (`Registry::create_workflow_from_trigger()`,
     * `Rest\Builder_Create`) and again in `Api\Extensions::register_conditions()`.
     * `sanitize_key()` lowercases and drops every character outside `a-z0-9_-`, so
     * `Hubgo/Tracking/Item_Saved` was stored as `hubgotrackingitem_saved`: it no
     * longer equalled the slug HubGo dispatched, and the strict comparison in
     * `Workflow_Processor::matches_trigger()` meant no HubGo workflow ever ran and
     * `Placeholders::get_placeholders_list()` never matched a HubGo token, so the
     * builder listed none of them.
     *
     * A slug that survives `sanitize_key()` unchanged is therefore the contract,
     * not a style choice. The hook a developer listens to from PHP stays the
     * `HOOK_*` constant above; {@see self::get_trigger_hook()} maps one onto the
     * other.
     *
     * @since 3.1.0
     */
    const TRIGGER_ORDER_SHIPPED     = 'hubgo_order_shipped';
    const TRIGGER_ITEM_SAVED        = 'hubgo_tracking_saved';
    const TRIGGER_ITEM_DELETED      = 'hubgo_tracking_deleted';
    const TRIGGER_DELIVERY_PROMISED = 'hubgo_delivery_promised';
    const TRIGGER_DELIVERY_OVERDUE  = 'hubgo_delivery_overdue';

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
     * Package URL served by wordpress.org.
     *
     * Joinotify is distributed on the official directory, so the card can be
     * installed and activated in one click from the Integrations screen through
     * {@see \MeuMouse\Hubgo\Core\Plugin_Installer} instead of sending the user
     * off to the plugin search screen.
     *
     * @since 3.1.0
     * @var string
     */
    const PACKAGE_URL = 'https://downloads.wordpress.org/plugin/joinotify.zip';

    /**
     * Lowest Joinotify version carrying the functional registration API this
     * integration is written against.
     *
     * @since 3.1.0
     * @var string
     */
    const MIN_VERSION = '2.0';

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
     * @version 3.1.0
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
        add_action( self::HOOK_ORDER_SHIPPED, array( $this, 'handle_order_shipped' ), 10, 2 );
        add_action( self::HOOK_ITEM_SAVED, array( $this, 'handle_tracking_saved' ), 10, 3 );
        add_action( self::HOOK_ITEM_DELETED, array( $this, 'handle_tracking_deleted' ), 10, 2 );
        add_action( self::HOOK_DELIVERY_PROMISED, array( $this, 'handle_delivery_promised' ), 10, 2 );
        add_action( self::HOOK_DELIVERY_OVERDUE, array( $this, 'handle_delivery_overdue' ), 10, 2 );

        // Value inputs the builder renders for the HubGo-only conditions, and the
        // carrier catalog behind the "Carrier" select.
        add_filter( 'Joinotify/Builder/Condition_Value_Types', array( $this, 'register_condition_value_types' ) );
        add_filter( 'Joinotify/Builder/Condition_Options', array( $this, 'register_condition_options' ) );

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
     * @version 3.1.0
     * @param array $integrations Current catalog.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations[ self::CARD_SLUG ] = array(
            'title'            => __( 'Joinotify', 'hubgo' ),
            'description'      => __( 'Send automatic WhatsApp messages from the logistics events: order shipped, tracking code saved or removed, delivery date promised at the checkout and delivery running late.', 'hubgo' ),
            'icon'             => $this->get_card_icon_svg(),
            'author'           => 'MeuMouse.com',
            'author_url'       => 'https://meumouse.com',
            'category'         => 'notifications',
            'setting_key'      => self::HUBGO_SETTING_KEY,
            'is_plugin'        => true,
            'plugin_active'    => array( self::PLUGIN_FILE ),
            'doc_url'          => 'https://ajuda.meumouse.com/docs/joinotify/overview',
            'install'          => array(
                'plugin_slug'  => self::PLUGIN_FILE,
                'download_url' => self::PACKAGE_URL,
                'label'        => __( 'Install Joinotify', 'hubgo' ),
            ),
            'modal'            => array(
                'title'       => __( 'Joinotify', 'hubgo' ),
                'description' => __( 'Logistics triggers, placeholders and conditions available in the Joinotify flow builder.', 'hubgo' ),
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
     * The function checks are the real gate — a host that ships the API under a
     * version this constant does not know about still works. `MIN_VERSION` only
     * rules out a v1 install that happens to autoload a same-named helper.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return bool
     */
    private function is_supported() {
        $required = array(
            'joinotify_register_integration',
            'joinotify_register_trigger',
            'joinotify_dispatch_trigger',
        );

        foreach ( $required as $function ) {
            if ( ! function_exists( $function ) ) {
                return false;
            }
        }

        if ( defined( 'JOINOTIFY_VERSION' ) && version_compare( JOINOTIFY_VERSION, self::MIN_VERSION, '<' ) ) {
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
     * Every trigger slug this integration registers.
     *
     * @since 3.1.0
     * @return array<int,string>
     */
    private function get_trigger_slugs() {
        return array(
            self::TRIGGER_ORDER_SHIPPED,
            self::TRIGGER_ITEM_SAVED,
            self::TRIGGER_ITEM_DELETED,
            self::TRIGGER_DELIVERY_PROMISED,
            self::TRIGGER_DELIVERY_OVERDUE,
        );
    }


    /**
     * Trigger slugs whose payload carries tracking data.
     *
     * `Hubgo/Delivery/Promise_Saved` fires while the order is being created, when
     * nothing has shipped yet, so the tracking tokens are deliberately not offered
     * there: the builder should not list a token that can only ever resolve empty.
     *
     * @since 3.1.0
     * @return array<int,string>
     */
    private function get_tracking_trigger_slugs() {
        return array(
            self::TRIGGER_ORDER_SHIPPED,
            self::TRIGGER_ITEM_SAVED,
            self::TRIGGER_ITEM_DELETED,
            self::TRIGGER_DELIVERY_OVERDUE,
        );
    }


    /**
     * The HubGo action hook behind each registered trigger slug.
     *
     * @since 3.1.0
     * @return array<string,string> Trigger slug => HubGo hook name.
     */
    private function get_trigger_hooks() {
        return array(
            self::TRIGGER_ORDER_SHIPPED     => self::HOOK_ORDER_SHIPPED,
            self::TRIGGER_ITEM_SAVED        => self::HOOK_ITEM_SAVED,
            self::TRIGGER_ITEM_DELETED      => self::HOOK_ITEM_DELETED,
            self::TRIGGER_DELIVERY_PROMISED => self::HOOK_DELIVERY_PROMISED,
            self::TRIGGER_DELIVERY_OVERDUE  => self::HOOK_DELIVERY_OVERDUE,
        );
    }


    /**
     * The HubGo action hook a trigger slug stands for.
     *
     * @since 3.1.0
     * @param string $trigger Registered trigger slug.
     * @return string Empty string for an unknown slug.
     */
    public function get_trigger_hook( $trigger ) {
        $hooks = $this->get_trigger_hooks();

        return isset( $hooks[ $trigger ] ) ? $hooks[ $trigger ] : '';
    }




    /**
     * Register the integration card, triggers and placeholders.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return void
     */
    public function register() {
        joinotify_register_integration( array(
            'slug'        => self::SLUG,
            'title'       => __( 'HubGo', 'hubgo' ),
            'description' => __( 'Send automatic WhatsApp messages from logistics events — order shipped, tracking code saved or removed, delivery date promised and delivery running late — connecting HubGo to Joinotify.', 'hubgo' ),
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
            'data_trigger'     => self::TRIGGER_ITEM_DELETED,
            'title'            => __( 'When a tracking code is removed from the order', 'hubgo' ),
            'description'      => __( 'Fired when a tracking code is deleted from the order. Meant for internal alerts to the shipping team rather than for the customer.', 'hubgo' ),
            'require_settings' => false,
            'icon'             => $this->get_icon_svg(),
        ) );

        joinotify_register_trigger( self::SLUG, array(
            'data_trigger'     => self::TRIGGER_DELIVERY_PROMISED,
            'title'            => __( 'Delivery date promised at the checkout', 'hubgo' ),
            'description'      => __( 'Fired when the order is placed and HubGo stores the delivery date quoted at the checkout. Use it to confirm the estimate right after the purchase.', 'hubgo' ),
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

        $this->register_conditions();
    }


    /**
     * Register the conditions a HubGo workflow can branch on.
     *
     * Two families. The WooCommerce ones are already resolvable by Joinotify's own
     * engine — its `get_compare_value()` builds its context from `order_id`, which
     * every HubGo payload carries — they were simply never *offered* for a HubGo
     * trigger, so a condition node under one of them showed "no condition available
     * for this action". The HubGo ones (carrier, tracking code, promised days, days
     * late) need a resolver of their own, registered right below.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_conditions() {
        if ( ! function_exists( 'joinotify_register_conditions' ) ) {
            return;
        }

        $conditions = array(
            'hubgo_carrier' => array(
                'title'       => __( 'Carrier', 'hubgo' ),
                'description' => __( 'The carrier on the tracking code, falling back to the one quoted at the checkout.', 'hubgo' ),
            ),
            'hubgo_tracking_code' => array(
                'title'       => __( 'Tracking code', 'hubgo' ),
                'description' => __( 'The tracking code the event is about.', 'hubgo' ),
            ),
            'hubgo_tracking_count' => array(
                'title'       => __( 'Number of tracking codes', 'hubgo' ),
                'description' => __( 'How many tracking codes the order carries.', 'hubgo' ),
            ),
            'hubgo_delivery_days' => array(
                'title'       => __( 'Business days promised', 'hubgo' ),
                'description' => __( 'The delivery estimate, in business days, quoted at the checkout.', 'hubgo' ),
            ),
            'hubgo_days_late' => array(
                'title'       => __( 'Days late', 'hubgo' ),
                'description' => __( 'How many days have passed since the promised delivery date.', 'hubgo' ),
            ),
            'hubgo_shipping_method' => array(
                'title'       => __( 'Shipping method (HubGo)', 'hubgo' ),
                'description' => __( 'The shipping method chosen at the checkout, as HubGo stored it on the order.', 'hubgo' ),
            ),
            // Resolved by Joinotify's own engine, from the order on the payload.
            'order_status' => array(
                'title'       => __( 'Order status', 'hubgo' ),
                'description' => __( 'The current WooCommerce order status.', 'hubgo' ),
            ),
            'order_total' => array(
                'title'       => __( 'Order total', 'hubgo' ),
                'description' => __( 'The WooCommerce order total.', 'hubgo' ),
            ),
            'order_paid' => array(
                'title'       => __( 'Order is paid', 'hubgo' ),
                'description' => __( 'Whether the WooCommerce order has been paid.', 'hubgo' ),
            ),
            'products_purchased' => array(
                'title'       => __( 'Products purchased', 'hubgo' ),
                'description' => __( 'The products on the WooCommerce order.', 'hubgo' ),
            ),
            'payment_method' => array(
                'title'       => __( 'Payment method', 'hubgo' ),
                'description' => __( 'The payment gateway used on the order.', 'hubgo' ),
            ),
            'shipping_method' => array(
                'title'       => __( 'Shipping method', 'hubgo' ),
                'description' => __( 'The WooCommerce shipping method on the order.', 'hubgo' ),
            ),
            'customer_email' => array(
                'title'       => __( 'Customer e-mail', 'hubgo' ),
                'description' => __( 'The billing e-mail on the order.', 'hubgo' ),
            ),
        );

        // `joinotify_register_conditions()` sanitize_key()s the slug it is given,
        // which is why every trigger slug is already lowercase/underscore: the key
        // it registers under has to be the one the builder looks the trigger up by.
        foreach ( $this->get_trigger_slugs() as $trigger ) {
            joinotify_register_conditions( $trigger, $conditions );
        }

        if ( ! function_exists( 'joinotify_register_condition_operators' ) || ! function_exists( 'joinotify_register_condition_value' ) ) {
            return;
        }

        // A condition with no registered operator is dropped from the builder
        // catalog, so these are what make the HubGo keys above selectable.
        $operators = array(
            'hubgo_carrier'         => array( 'is', 'is_not', 'contains', 'not_contain', 'empty', 'not_empty' ),
            'hubgo_tracking_code'   => array( 'is', 'is_not', 'contains', 'not_contain', 'empty', 'not_empty' ),
            'hubgo_tracking_count'  => array( 'is', 'is_not', 'bigger_than', 'less_than' ),
            'hubgo_delivery_days'   => array( 'is', 'is_not', 'bigger_than', 'less_than' ),
            'hubgo_days_late'       => array( 'is', 'is_not', 'bigger_than', 'less_than' ),
            'hubgo_shipping_method' => array( 'is', 'is_not', 'contains', 'not_contain', 'empty', 'not_empty' ),
        );

        foreach ( $operators as $condition => $allowed ) {
            joinotify_register_condition_operators( $condition, $allowed );
        }

        $resolvers = array(
            'hubgo_carrier' => function( $value_map, $type, $payload ) {
                $carrier = $this->tracking_value( $payload, 'carrier_name' );

                return '' !== $carrier ? $carrier : $this->promise_value( $payload, 'carrier' );
            },
            'hubgo_tracking_code' => function( $value_map, $type, $payload ) {
                return $this->tracking_value( $payload, 'tracking_code' );
            },
            'hubgo_tracking_count' => function( $value_map, $type, $payload ) {
                return count( $this->payload_items( $payload ) );
            },
            'hubgo_delivery_days' => function( $value_map, $type, $payload ) {
                return (int) $this->promise_value( $payload, 'days' );
            },
            'hubgo_days_late' => function( $value_map, $type, $payload ) {
                return $this->get_days_late( $payload );
            },
            'hubgo_shipping_method' => function( $value_map, $type, $payload ) {
                return $this->promise_value( $payload, 'method' );
            },
        );

        foreach ( $resolvers as $condition => $callback ) {
            joinotify_register_condition_value( $condition, $callback );
        }
    }


    /**
     * Declare how the builder renders the value input of each HubGo-only condition.
     *
     * Joinotify falls back to a free-text field for an unknown condition key; the
     * numeric ones want a number input instead.
     *
     * @since 3.1.0
     * @param array $types Condition key => {type, requires?}.
     * @return array
     */
    public function register_condition_value_types( $types ) {
        if ( ! is_array( $types ) ) {
            $types = array();
        }

        $types['hubgo_tracking_count'] = array( 'type' => 'number' );
        $types['hubgo_delivery_days'] = array( 'type' => 'number' );
        $types['hubgo_days_late'] = array( 'type' => 'number' );

        return $types;
    }


    /**
     * Feed the carrier condition with HubGo's own carrier catalog.
     *
     * @since 3.1.0
     * @param array $options Condition key => list of {label, value}.
     * @return array
     */
    public function register_condition_options( $options ) {
        if ( ! is_array( $options ) ) {
            $options = array();
        }

        if ( ! class_exists( Providers_Registry::class ) ) {
            return $options;
        }

        $carriers = array();

        foreach ( Providers_Registry::get_providers() as $group ) {
            foreach ( array_keys( (array) $group ) as $carrier ) {
                $carrier = (string) $carrier;

                // The registry groups carriers by country and the same carrier
                // serves more than one group, while the stored value is the
                // carrier key itself — so list each one once.
                if ( '' === $carrier || isset( $carriers[ $carrier ] ) ) {
                    continue;
                }

                $carriers[ $carrier ] = array(
                    'label' => $carrier,
                    'value' => $carrier,
                );
            }
        }

        ksort( $carriers );

        $options['hubgo_carrier'] = array_values( $carriers );

        return $options;
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
     * Resolve the WooCommerce order behind a runtime payload.
     *
     * @since 3.1.0
     * @param array $payload Runtime payload.
     * @return \WC_Order|null
     */
    protected function payload_order( $payload ) {
        $order_id = isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0;

        if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
            return null;
        }

        $order = wc_get_order( $order_id );

        return $order instanceof \WC_Order ? $order : null;
    }


    /**
     * Read one field of the primary tracking item on a runtime payload.
     *
     * @since 3.1.0
     * @param array $payload Runtime payload.
     * @param string $key Key of the normalized tracking item.
     * @return string
     */
    protected function tracking_value( $payload, $key ) {
        $data = isset( $payload['tracking_data'] ) && is_array( $payload['tracking_data'] ) ? $payload['tracking_data'] : array();

        return isset( $data[ $key ] ) ? (string) $data[ $key ] : '';
    }


    /**
     * Every normalized tracking item on a runtime payload.
     *
     * @since 3.1.0
     * @param array $payload Runtime payload.
     * @return array<int,array<string,string>>
     */
    protected function payload_items( $payload ) {
        return isset( $payload['tracking_items'] ) && is_array( $payload['tracking_items'] ) ? $payload['tracking_items'] : array();
    }


    /**
     * Read one field of the delivery promise behind a runtime payload.
     *
     * The promise travels on the payload for the two delivery triggers and is read
     * back off the order for the tracking ones, which know nothing about it. Either
     * way a message quotes the date the shopper was actually promised at the
     * checkout, never a fresh quote that may have drifted since.
     *
     * The order is also consulted when the payload's promise lacks the requested
     * key: `Hubgo/Delivery/Promise_Saved` carries the promise as it was built, which
     * has no `timestamp` — that one is derived by {@see Delivery_Promise::get()}.
     *
     * @since 3.1.0
     * @param array $payload Runtime payload.
     * @param string $key Key of the promise array.
     * @return string
     */
    protected function promise_value( $payload, $key ) {
        $promise = isset( $payload['delivery_promise'] ) && is_array( $payload['delivery_promise'] ) ? $payload['delivery_promise'] : array();

        if ( ! isset( $promise[ $key ] ) ) {
            $order_id = isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0;
            $stored = $order_id ? Delivery_Promise::get( $order_id ) : array();
            $promise = is_array( $stored ) ? array_merge( $promise, $stored ) : $promise;
        }

        return isset( $promise[ $key ] ) ? (string) $promise[ $key ] : '';
    }


    /**
     * How many whole days have passed since the promised delivery date.
     *
     * @since 3.1.0
     * @param array $payload Runtime payload.
     * @return int Zero while the promise is still in the future (or unknown).
     */
    protected function get_days_late( $payload ) {
        $timestamp = (int) $this->promise_value( $payload, 'timestamp' );

        if ( $timestamp <= 0 ) {
            return 0;
        }

        $elapsed = current_time( 'timestamp' ) - $timestamp;

        return $elapsed > 0 ? (int) floor( $elapsed / DAY_IN_SECONDS ) : 0;
    }


    /**
     * Format a stored ship date for presentation.
     *
     * @since 3.1.0
     * @param string $date Raw stored date.
     * @return string
     */
    protected function format_date( $date ) {
        $date = (string) $date;
        $timestamp = '' !== $date ? strtotime( $date ) : false;

        return $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : $date;
    }


    /**
     * Build the placeholder map with runtime (production) resolvers.
     *
     * Every 'production' value is a callable( array $payload ): string — Joinotify
     * resolves it at send time. Sandbox values are static previews for the builder.
     *
     * The `triggers` list is what scopes a token in the builder, and it is enforced
     * at SEND time as well since Joinotify 2.1 (the runtime payload carries the
     * fired trigger slug), so a token missing the fired trigger is left unresolved
     * in the message. Which is why the list is scoped rather than blanket: the
     * tracking tokens are offered only on the triggers that actually carry tracking
     * data, and `{{ hubgo_days_late }}` only where a delivery is already late.
     *
     * Both sides compare against the slug the *workflow stored*, so these have to be
     * the registered `data_trigger` values verbatim — see the constant block for why
     * those are no longer the hook names.
     *
     * The WooCommerce tokens deliberately reuse the names of Joinotify's own
     * WooCommerce context, so someone who has written a workflow there does not
     * have to learn a second vocabulary to write one here.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return array
     */
    private function get_placeholders() {
        $all = $this->get_trigger_slugs();
        $tracking = $this->get_tracking_trigger_slugs();
        $overdue = array( self::TRIGGER_DELIVERY_OVERDUE );

        $placeholders = array(
            '{{ hubgo_carrier_name }}' => array(
                'triggers'    => $all,
                'description' => __( 'Carrier name', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Express Delivery', 'hubgo' ),
                    'production' => function( $payload ) {
                        $carrier = $this->tracking_value( $payload, 'carrier_name' );

                        // No carrier typed on the tracking code yet: fall back to
                        // the one quoted at the checkout, which is the same name
                        // the customer saw on the product page.
                        return '' !== $carrier ? $carrier : $this->promise_value( $payload, 'carrier' );
                    },
                ),
            ),
            '{{ hubgo_delivery_date }}' => array(
                'triggers'    => $all,
                'description' => __( 'Delivery date promised at the checkout', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => wp_date( get_option('date_format') ),
                    'production' => function( $payload ) {
                        return $this->promise_value( $payload, 'date_label' );
                    },
                ),
            ),
            '{{ hubgo_delivery_days }}' => array(
                'triggers'    => $all,
                'description' => __( 'Business days promised at the checkout', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '5',
                    'production' => function( $payload ) {
                        $days = $this->promise_value( $payload, 'days' );

                        return '0' !== $days ? $days : '';
                    },
                ),
            ),
            '{{ hubgo_shipping_method }}' => array(
                'triggers'    => $all,
                'description' => __( 'Shipping method chosen at the checkout', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Express Delivery', 'hubgo' ),
                    'production' => function( $payload ) {
                        return $this->promise_value( $payload, 'method' );
                    },
                ),
            ),
            '{{ hubgo_days_late }}' => array(
                'triggers'    => $overdue,
                'description' => __( 'Whole days elapsed since the promised delivery date', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '2',
                    'production' => function( $payload ) {
                        $days = $this->get_days_late( $payload );

                        return $days > 0 ? (string) $days : '';
                    },
                ),
            ),
            '{{ hubgo_tracking_link }}' => array(
                'triggers'    => $tracking,
                'description' => __( 'Tracking link', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => 'https://carrier.example/tracking/BR1234567890',
                    'production' => function( $payload ) {
                        return $this->tracking_value( $payload, 'tracking_link' );
                    },
                ),
            ),
            '{{ hubgo_tracking_code }}' => array(
                'triggers'    => $tracking,
                'description' => __( 'Tracking code', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => 'BR1234567890',
                    'production' => function( $payload ) {
                        return $this->tracking_value( $payload, 'tracking_code' );
                    },
                ),
            ),
            '{{ hubgo_shipping_date }}' => array(
                'triggers'    => $tracking,
                'description' => __( 'Shipping date', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => wp_date( get_option( 'date_format' ) ),
                    'production' => function( $payload ) {
                        return $this->format_date( $this->tracking_value( $payload, 'shipping_date' ) );
                    },
                ),
            ),
            '{{ hubgo_tracking_count }}' => array(
                'triggers'    => $tracking,
                'description' => __( 'Number of tracking codes registered on the order', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '2',
                    'production' => function( $payload ) {
                        return (string) count( $this->payload_items( $payload ) );
                    },
                ),
            ),
            '{{ hubgo_tracking_list }}' => array(
                'triggers'    => $tracking,
                'description' => __( 'Every tracking code on the order, one per line (carrier, code and link). It can also be used as the source of a loop action.', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => "Express Delivery - BR1234567890 - https://carrier.example/tracking/BR1234567890\nStandard Post - JD9876543210 - https://carrier.example/tracking/JD9876543210",
                    'production' => function( $payload ) {
                        return $this->format_tracking_list( $payload );
                    },
                ),
            ),
            '{{ hubgo_tracking_codes }}' => array(
                'triggers'    => $tracking,
                'description' => __( 'Every tracking code on the order, separated by commas', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => 'BR1234567890, JD9876543210',
                    'production' => function( $payload ) {
                        return implode( ', ', $this->collect_item_values( $payload, 'tracking_code' ) );
                    },
                ),
            ),
            '{{ hubgo_tracking_links }}' => array(
                'triggers'    => $tracking,
                'description' => __( 'Every tracking link on the order, one per line. It can also be used as the source of a loop action.', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => "https://carrier.example/tracking/BR1234567890\nhttps://carrier.example/tracking/JD9876543210",
                    'production' => function( $payload ) {
                        return implode( "\n", $this->collect_item_values( $payload, 'tracking_link' ) );
                    },
                ),
            ),
            '{{ hubgo_order_tracking_url }}' => array(
                'triggers'    => $all,
                'description' => __( "Link to the order page on the customer's account, where HubGo shows the tracking codes", 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => get_site_url() . '/my-account/view-order/12345/',
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $order->get_view_order_url() : '';
                    },
                ),
            ),
            '{{ wc_billing_first_name }}' => array(
                'triggers'    => $all,
                'description' => __( 'Customer billing first name (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'John', 'hubgo' ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $order->get_billing_first_name() : '';
                    },
                ),
            ),
            '{{ wc_billing_last_name }}' => array(
                'triggers'    => $all,
                'description' => __( 'Customer billing last name (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Doe', 'hubgo' ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $order->get_billing_last_name() : '';
                    },
                ),
            ),
            '{{ wc_billing_email }}' => array(
                'triggers'    => $all,
                'description' => __( 'Customer billing e-mail (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => 'user@example.com',
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $order->get_billing_email() : '';
                    },
                ),
            ),
            '{{ wc_billing_phone }}' => array(
                'triggers'    => $all,
                'description' => __( 'Order billing phone (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '+55 11 91234-5678',
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $order->get_billing_phone() : '';
                    },
                ),
            ),
            '{{ wc_shipping_phone }}' => array(
                'triggers'    => $all,
                'description' => __( 'Order shipping phone (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '+55 41 91234-5678',
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        // get_shipping_phone() only exists from WooCommerce 5.6.
                        if ( ! $order || ! method_exists( $order, 'get_shipping_phone' ) ) {
                            return '';
                        }

                        return $order->get_shipping_phone();
                    },
                ),
            ),
            '{{ wc_order_number }}' => array(
                'triggers'    => $all,
                'description' => __( 'Order number (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => '12345',
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $order->get_order_number() : '';
                    },
                ),
            ),
            '{{ wc_order_status }}' => array(
                'triggers'    => $all,
                'description' => __( 'Order status (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Order shipped', 'hubgo' ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return ( $order && function_exists( 'wc_get_order_status_name' ) ) ? wc_get_order_status_name( $order->get_status() ) : '';
                    },
                ),
            ),
            '{{ wc_order_date }}' => array(
                'triggers'    => $all,
                'description' => __( 'Date the order was placed (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => wp_date( get_option('date_format') ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );
                        $created = $order ? $order->get_date_created() : null;

                        return $created ? wp_date( get_option('date_format'), $created->getTimestamp() ) : '';
                    },
                ),
            ),
            '{{ wc_order_total }}' => array(
                'triggers'    => $all,
                'description' => __( 'Order total (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => $this->format_price( 150 ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $this->format_price( $order->get_total(), $order->get_currency() ) : '';
                    },
                ),
            ),
            '{{ wc_total_discount }}' => array(
                'triggers'    => $all,
                'description' => __( 'Total discount on the order (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => $this->format_price( 20 ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $this->format_price( $order->get_total_discount(), $order->get_currency() ) : '';
                    },
                ),
            ),
            '{{ wc_total_tax }}' => array(
                'triggers'    => $all,
                'description' => __( 'Total tax on the order (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => $this->format_price( 15 ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $this->format_price( $order->get_total_tax(), $order->get_currency() ) : '';
                    },
                ),
            ),
            '{{ wc_currency_symbol }}' => array(
                'triggers'    => $all,
                'description' => __( 'Order currency symbol (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => function_exists( 'get_woocommerce_currency_symbol' ) ? $this->plain_text( get_woocommerce_currency_symbol() ) : '',
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        if ( ! $order || ! function_exists( 'get_woocommerce_currency_symbol' ) ) {
                            return '';
                        }

                        return $this->plain_text( get_woocommerce_currency_symbol( $order->get_currency() ) );
                    },
                ),
            ),
            '{{ wc_payment_method_title }}' => array(
                'triggers'    => $all,
                'description' => __( 'Payment method used on the order (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( 'Credit card', 'hubgo' ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        if ( ! $order ) {
                            return '';
                        }

                        // The title stored on the order is the one the customer saw
                        // at the checkout; the gateway is only consulted when the
                        // order carries no title of its own (older orders).
                        $title = $order->get_payment_method_title();

                        if ( '' === $title && function_exists( 'WC' ) && WC()->payment_gateways() ) {
                            $gateways = WC()->payment_gateways()->payment_gateways();
                            $id = $order->get_payment_method();
                            $title = isset( $gateways[ $id ] ) ? $gateways[ $id ]->get_title() : '';
                        }

                        return $this->plain_text( $title );
                    },
                ),
            ),
            '{{ wc_coupon_codes }}' => array(
                'triggers'    => $all,
                'description' => __( 'Coupon codes used on the order, separated by commas (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => 'CUPOM10, FREESHIPPING',
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? implode( ', ', $order->get_coupon_codes() ) : '';
                    },
                ),
            ),
            '{{ wc_shipping_address }}' => array(
                'triggers'    => $all,
                'description' => __( 'Order shipping address as WooCommerce formats it', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( '450 Daisy Street, Curitiba, PR 80000-100, Brazil', 'hubgo' ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return $order ? $order->get_shipping_to_display() : '';
                    },
                ),
            ),
            '{{ wc_billing_full_address }}' => array(
                'triggers'    => $all,
                'description' => __( 'Customer full billing address (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( '123 Flower Street - Curitiba/PR - Brazil (postcode: 80000-000)', 'hubgo' ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return ( $order && class_exists( Woocommerce::class ) ) ? Woocommerce::get_full_address( $order, 'billing' ) : '';
                    },
                ),
            ),
            '{{ wc_shipping_full_address }}' => array(
                'triggers'    => $all,
                'description' => __( 'Customer full shipping address (WooCommerce)', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => __( '450 Daisy Street - Curitiba/PR - Brazil (postcode: 80000-100)', 'hubgo' ),
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return ( $order && class_exists( Woocommerce::class ) ) ? Woocommerce::get_full_address( $order, 'shipping' ) : '';
                    },
                ),
            ),
            '{{ wc_purchased_items }}' => array(
                'triggers'    => $all,
                'description' => __( 'Products and quantities purchased on the order, one per line', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => "1x - Men's cotton t-shirt (sample product)\n1x - UV protection sunglasses (sample product)",
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return ( $order && class_exists( Woocommerce::class ) ) ? Woocommerce::get_purchased_items( $order ) : '';
                    },
                ),
            ),
            '{{ wc_review_links }}' => array(
                'triggers'    => $all,
                'description' => __( 'Review link for each purchased product, one per line. Pair it with a delay to ask for a review after the delivery.', 'hubgo' ),
                'replacement' => array(
                    'sandbox'    => get_site_url() . "/product/sample-t-shirt/#reviews\n" . get_site_url() . '/product/sample-sunglasses/#reviews',
                    'production' => function( $payload ) {
                        $order = $this->payload_order( $payload );

                        return ( $order && class_exists( Woocommerce::class ) && method_exists( Woocommerce::class, 'get_review_links' ) ) ? Woocommerce::get_review_links( $order ) : '';
                    },
                ),
            ),
            // Resolved centrally by Joinotify (Placeholders::replace_placeholders())
            // from the order_id every HubGo payload carries. Listed here without a
            // replacement so the builder offers it, exactly as Joinotify's own
            // WooCommerce context does.
            '{{ wc_checkout_field=[FIELD_ID] }}' => array(
                'triggers'    => $all,
                'description' => __( 'Value of a specific checkout field on the order. Replace FIELD_ID with the field ID, for example: billing_email.', 'hubgo' ),
                'replacement' => array(),
            ),
        );

        /**
         * Filter the placeholders HubGo exposes to the Joinotify builder.
         *
         * @since 3.1.0
         * @param array $placeholders Map of '{{ token }}' => {triggers, description, replacement}.
         * @param array $triggers Every HubGo trigger slug.
         */
        return apply_filters( 'Hubgo/Integrations/Joinotify/Placeholders', $placeholders, $all );
    }


    /**
     * Collect one field of every tracking item on the payload, dropping the blanks.
     *
     * @since 3.1.0
     * @param array $payload Runtime payload.
     * @param string $key Key of the normalized tracking item.
     * @return array<int,string>
     */
    protected function collect_item_values( $payload, $key ) {
        $values = array();

        foreach ( $this->payload_items( $payload ) as $item ) {
            $value = is_array( $item ) && isset( $item[ $key ] ) ? trim( (string) $item[ $key ] ) : '';

            if ( '' !== $value ) {
                $values[] = $value;
            }
        }

        return $values;
    }


    /**
     * Format a monetary amount the way Joinotify renders it in a message.
     *
     * @since 3.1.0
     * @param float|string $value Amount.
     * @param string $currency Optional currency code.
     * @return string
     */
    protected function format_price( $value, $currency = '' ) {
        if ( function_exists( 'joinotify_format_price' ) ) {
            return joinotify_format_price( $value, $currency );
        }

        if ( ! function_exists( 'wc_price' ) ) {
            return is_scalar( $value ) ? (string) $value : '';
        }

        $args = '' !== $currency ? array( 'currency' => $currency ) : array();

        return $this->plain_text( wc_price( (float) $value, $args ) );
    }


    /**
     * Strip the HTML WooCommerce wraps around a value so it reads as plain text.
     *
     * @since 3.1.0
     * @param string $value Raw value.
     * @return string
     */
    protected function plain_text( $value ) {
        $value = is_scalar( $value ) ? (string) $value : '';

        if ( function_exists( 'joinotify_format_plain_text' ) ) {
            return joinotify_format_plain_text( $value );
        }

        return trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) );
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
     * Handle a tracking code being removed -> dispatch Joinotify trigger.
     *
     * `Hubgo/Tracking/Deleted_Item` reports the list that survived the deletion,
     * not the item that went away, so the "primary" tracking item is whatever is
     * left on the order — and nothing at all once the last code is removed. That
     * is the useful shape here: an internal alert wants to say what the order
     * still has.
     *
     * @since 3.1.0
     * @param int $order_id Order ID.
     * @param array $items Tracking items left on the order.
     * @return void
     */
    public function handle_tracking_deleted( $order_id, $items = array() ) {
        $items = is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : array();
        $latest = ! empty( $items ) ? end( $items ) : array();

        $this->dispatch(
            self::TRIGGER_ITEM_DELETED,
            absint( $order_id ),
            is_array( $latest ) ? $latest : array(),
            $items,
            __( 'Tracking code removed from the order', 'hubgo' )
        );
    }


    /**
     * Handle the delivery promise being stored -> dispatch Joinotify trigger.
     *
     * Fires while the order is being created, so nothing has shipped yet: the
     * payload carries the promise and no tracking data, which is exactly what the
     * trigger's placeholder scope expects.
     *
     * @since 3.1.0
     * @param int $order_id Order ID.
     * @param array $promise Delivery promise stored on the order.
     * @return void
     */
    public function handle_delivery_promised( $order_id, $promise = array() ) {
        $this->dispatch(
            self::TRIGGER_DELIVERY_PROMISED,
            absint( $order_id ),
            array(),
            array(),
            __( 'Delivery date promised at the checkout', 'hubgo' ),
            array( 'delivery_promise' => is_array( $promise ) ? $promise : array() )
        );
    }


    /**
     * Handle a late delivery -> dispatch Joinotify trigger.
     *
     * The order's tracking codes travel on the payload as well, so a "your
     * parcel is running late" message can still carry the tracking link the
     * customer needs to check it themselves.
     *
     * @since 3.0.0
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
     * @param string $trigger Registered trigger slug.
     * @param int $order_id Order ID.
     * @param array $tracking_item Primary tracking item.
     * @param array $all_items Every tracking item on the order.
     * @param string $description Human description.
     * @param array $extra Extra payload entries merged into the dispatch.
     * @return void
     */
    protected function dispatch( $trigger, $order_id, $tracking_item, $all_items, $description, $extra = array() ) {
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
            // The HubGo action hook behind the trigger, so a workflow (or a
            // third party reading the payload) can tell them apart now that the
            // trigger slug is no longer the hook name.
            'hubgo_hook'     => $this->get_trigger_hook( $trigger ),
        ) );

        /**
         * Filter the HubGo payload before dispatching to Joinotify.
         *
         * @since 3.0.0
         * @version 3.1.0
         * @param array $payload Dispatch payload.
         * @param string $trigger Registered trigger slug (since 3.1.0 no longer the hook name).
         */
        $payload = apply_filters( 'Hubgo/Integrations/Joinotify/Payload', $payload, $trigger );

        joinotify_dispatch_trigger( $trigger, self::SLUG, $payload );
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
