<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Core\Tracking_Manager;
use MeuMouse\Hubgo\Core\Providers_Registry;
use MeuMouse\Joinotify\Integrations\Integrations_Base;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Joinotify integration for HubGo triggers and placeholders.
 *
 * @since 2.1.1
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Joinotify extends Integrations_Base {

    /**
     * Tracking manager instance.
     *
     * @since 2.1.1
     * @var Tracking_Manager
     */
    protected $tracking_manager;


    /**
     * Constructor.
     *
     * @since 2.1.1
     * @param Tracking_Manager $tracking_manager
     * @return void
     */
    public function __construct( Tracking_Manager $tracking_manager ) {
        $this->tracking_manager = $tracking_manager;

        add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_triggers' ), 10, 1 );
        add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), 60 );
        add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );
        add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 20, 2 );

        add_action( 'Hubgo/Tracking/Order_Shipped', array( $this, 'handle_order_shipped' ), 10, 2 );
        add_action( 'Hubgo/Tracking/Item_Saved', array( $this, 'handle_tracking_saved' ), 10, 3 );
    }


    /**
     * Register HubGo triggers in Joinotify.
     *
     * @since 2.1.1
     * @param array $triggers Existing triggers.
     * @return array
     */
    public function add_triggers( $triggers ) {
        $triggers['hubgo'] = array(
            array(
                'data_trigger'     => 'hubgo_order_sent',
                'title'            => esc_html__( 'Pedido enviado', 'hubgo' ),
                'description'      => esc_html__( 'Disparado quando o pedido entra no status enviado.', 'hubgo' ),
                'require_settings' => false,
            ),
            array(
                'data_trigger'     => 'hubgo_tracking_saved',
                'title'            => esc_html__( 'Ao salvar um rastreio no pedido', 'hubgo' ),
                'description'      => esc_html__( 'Disparado ao salvar um codigo de rastreio no pedido.', 'hubgo' ),
                'require_settings' => false,
            ),
        );

        return $triggers;
    }


    /**
     * Add HubGo tab in Joinotify trigger builder.
     *
     * @since 2.1.1
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'hubgo';
        $integration_name = esc_html__( 'HubGo', 'hubgo' );
        $icon_svg = '<span class="joinotify-tab-icon hubgo-text" style="font-weight:700;letter-spacing:.3px;">HG</span>';

        $this->render_integration_trigger_tab( $integration_slug, $integration_name, $icon_svg );
    }


    /**
     * Render HubGo trigger content in Joinotify builder.
     *
     * @since 2.1.1
     * @return void
     */
    public function add_triggers_content() {
        $this->render_integration_trigger_content( 'hubgo' );
    }


    /**
     * Register HubGo placeholders.
     *
     * @since 2.1.1
     * @param array $placeholders Existing placeholders.
     * @param array $payload Trigger payload.
     * @return array
     */
    public function add_placeholders( $placeholders, $payload ) {
        $trigger_names = array(
            'hubgo_order_sent',
            'hubgo_tracking_saved',
        );

        $tracking_data = isset( $payload['tracking_data'] ) && is_array( $payload['tracking_data'] )
            ? $payload['tracking_data']
            : array();

        $carrier_name = isset( $tracking_data['carrier_name'] ) ? $tracking_data['carrier_name'] : '';
        $tracking_link = isset( $tracking_data['tracking_link'] ) ? $tracking_data['tracking_link'] : '';
        $tracking_code = isset( $tracking_data['tracking_code'] ) ? $tracking_data['tracking_code'] : '';
        $shipping_date = isset( $tracking_data['shipping_date'] ) ? $tracking_data['shipping_date'] : '';

        $placeholders['hubgo'] = array(
            '{{ hubgo_carrier_name }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Nome da transportadora', 'hubgo' ),
                'replacement' => array(
                    'production' => $carrier_name,
                    'sandbox'    => esc_html__( 'Correios', 'hubgo' ),
                ),
            ),
            '{{ hubgo_tracking_link }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Link de rastreio', 'hubgo' ),
                'replacement' => array(
                    'production' => $tracking_link,
                    'sandbox'    => 'https://transportadora.exemplo/rastreio/BR1234567890',
                ),
            ),
            '{{ hubgo_tracking_code }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Codigo de rastreio', 'hubgo' ),
                'replacement' => array(
                    'production' => $tracking_code,
                    'sandbox'    => 'BR1234567890',
                ),
            ),
            '{{ hubgo_shipping_date }}' => array(
                'triggers' => $trigger_names,
                'description' => esc_html__( 'Data do envio', 'hubgo' ),
                'replacement' => array(
                    'production' => $shipping_date,
                    'sandbox'    => '2026-03-05',
                ),
            ),
        );

        return $placeholders;
    }


    /**
     * Handle order shipped action.
     *
     * @since 2.1.1
     * @param int $order_id Order ID.
     * @param array $items Tracking items.
     * @return void
     */
    public function handle_order_shipped( $order_id, $items ) {
        $tracking_item = is_array( $items ) && ! empty( $items ) ? end( $items ) : array();

        $this->process_trigger( 'hubgo_order_sent', absint( $order_id ), $tracking_item, __( 'Pedido enviado', 'hubgo' ) );
    }


    /**
     * Handle tracking saved action.
     *
     * @since 2.1.1
     * @param int $order_id Order ID.
     * @param array $item Saved tracking item.
     * @param array $all_items All tracking items.
     * @return void
     */
    public function handle_tracking_saved( $order_id, $item, $all_items ) {
        $this->process_trigger( 'hubgo_tracking_saved', absint( $order_id ), $item, __( 'Rastreio salvo no pedido', 'hubgo' ) );
    }


    /**
     * Process Joinotify workflows for HubGo triggers.
     *
     * @since 2.1.1
     * @param string $hook Trigger hook.
     * @param int $order_id Order ID.
     * @param array $tracking_item Tracking item.
     * @param string $description Event description.
     * @return void
     */
    protected function process_trigger( $hook, $order_id, $tracking_item, $description ) {
        if ( ! class_exists( 'MeuMouse\\Joinotify\\Core\\Workflow_Processor' ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $tracking_data = $this->build_tracking_data( $order, is_array( $tracking_item ) ? $tracking_item : array() );

        $payload = array(
            'type'          => 'trigger',
            'hook'          => $hook,
            'integration'   => 'hubgo',
            'order_id'      => $order_id,
            'order_number'  => $order->get_order_number(),
            'order_status'  => $order->get_status(),
            'tracking_data' => $tracking_data,
            'description'   => $description,
            'timestamp'     => current_time( 'mysql' ),
        );

        \MeuMouse\Joinotify\Core\Workflow_Processor::process_workflows(
            apply_filters( 'Joinotify/Process_Workflows/HubGo', $payload )
        );

        do_action( 'joinotify_' . $hook, $payload );
    }


    /**
     * Build normalized tracking data for payload/placeholders.
     *
     * @since 2.1.1
     * @param \WC_Order $order WooCommerce order.
     * @param array $item Tracking item.
     * @return array
     */
    protected function build_tracking_data( $order, $item ) {
        $provider = '';

        if ( ! empty( $item['custom_provider'] ) ) {
            $provider = (string) $item['custom_provider'];
        } elseif ( ! empty( $item['provider'] ) ) {
            $provider = (string) $item['provider'];
        } elseif ( ! empty( $item['carrier'] ) ) {
            $provider = (string) $item['carrier'];
        }

        $tracking_code = isset( $item['tracking_number'] ) ? (string) $item['tracking_number'] : '';
        $tracking_link = isset( $item['custom_url'] ) ? (string) $item['custom_url'] : '';

        if ( empty( $tracking_link ) && ! empty( $provider ) && ! empty( $tracking_code ) ) {
            $country = $order->get_shipping_country();
            if ( empty( $country ) ) {
                $country = $order->get_billing_country();
            }
            if ( empty( $country ) ) {
                $country = 'Brazil';
            }

            $tracking_link = Providers_Registry::get_tracking_url(
                $provider,
                $tracking_code,
                '',
                $country,
                $order->get_id()
            );
        }

        return array(
            'carrier_name'  => sanitize_text_field( $provider ),
            'tracking_link' => esc_url_raw( $tracking_link ),
            'tracking_code' => sanitize_text_field( $tracking_code ),
            'shipping_date' => isset( $item['ship_date'] ) ? sanitize_text_field( (string) $item['ship_date'] ) : '',
        );
    }
}
