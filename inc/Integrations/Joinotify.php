<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Core\Providers_Registry;

use MeuMouse\Joinotify\Integrations\Integrations_Base;
use MeuMouse\Joinotify\Core\Workflow_Processor;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Joinotify integration for HubGo triggers and placeholders.
 *
 * @since 2.1.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Joinotify extends Integrations_Base {

    /**
     * Constructor.
     *
     * @since 2.1.0
     * @return void
     */
    public function __construct() {
        add_filter( 'Joinotify/Settings/Tabs/Integrations', array( $this, 'add_integration_item' ), 70, 1 );

        add_filter( 'Joinotify/Builder/Get_All_Triggers', array( $this, 'add_triggers' ), 10, 1 );
        add_action( 'Joinotify/Builder/Triggers', array( $this, 'add_triggers_tab' ), 60 );
        add_action( 'Joinotify/Builder/Triggers_Content', array( $this, 'add_triggers_content' ) );
        add_filter( 'Joinotify/Builder/Placeholders_List', array( $this, 'add_placeholders' ), 20, 2 );

        add_action( 'Hubgo/Tracking/Order_Shipped', array( $this, 'handle_order_shipped' ), 10, 2 );
        add_action( 'Hubgo/Tracking/Item_Saved', array( $this, 'handle_tracking_saved' ), 10, 3 );
    }


    /**
     * Provide integration information for Joinotify settings.
     *
     * @since 2.1.0
     * @param array $integrations | Current integrations array.
     * @return array Modified integrations array including Bling.
     */
    public function add_integration_item( $integrations ) {
        $integrations['bling'] = array(
            'title'         => esc_html__( 'HubGo', 'hubgo' ),
            'description'   => esc_html__( 'Dispare mensagens automáticas no WhatsApp com eventos de logística, como pedido enviado e código de rastreio, integrando o HubGo ao Joinotify.', 'hubgo' ),
            'icon'          => '<svg id="hubgo_logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 272.84 152.99"><defs><style>.hubgo-1{fill:#008aff;}.hubgo-2{fill:#232323;}</style></defs><g id="Icon"><g id="Icon-2" data-name="Icon"><g id="Airplane"><path class="hubgo-1" d="M601.94,295.67c1.75,4.52,6.86,0,6.56-3.29l1.78-39.26-16.7,9.05Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-1" d="M630.77,217.59c-8.62,1.84-17.75,2.72-24.54,9.12-13.16,10.11-36.44,30.92-54.15,39.8-55,25.09-115.12,40.9-172.42,38.09-20.59-1.47-21.89,30.28-1.11,30.42,44.74-3.17,90.28-13.37,130.27-30.66,27.77-11.46,51.52-28.55,77.3-42.73,11.29-6.59,35.93-16.07,43.66-27.39C633.38,229.34,642.18,220,630.77,217.59Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-1" d="M552.62,221.23l27.84,21,14.94-11.94-37.19-13.79C555.26,214.92,549.15,217.87,552.62,221.23Z" transform="translate(-363.58 -216.05)"/></g><g id="H"><path class="hubgo-2" d="M445.26,242.32c.23-16.14-25.11-16.14-24.88,0v54.27c7.51-.78,15.8-1.94,24.88-3.6Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-2" d="M420.38,356.84c-.22,16.13,25.11,16.14,24.88,0V332.36q-12.22,2.77-24.88,4.86Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-2" d="M533.72,267.22v-24.9c-.07-16.27-24.83-16.27-24.9,0v33.95C516.73,273.58,525.33,270.54,533.72,267.22Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-2" d="M508.82,356.84c.07,16.27,24.83,16.26,24.9,0V300.43q-12.12,6.29-24.9,11.7Z" transform="translate(-363.58 -216.05)"/></g></g></g></svg>',
            'setting_key'   => 'enable_hubgo_integration',
            'action_hook'   => 'Joinotify/Settings/Tabs/Integrations/Hubgo',
            'is_plugin'     => true,
            'plugin_active' => array('hubgo/hubgo.php'),
        );

        return $integrations;
    }


    /**
     * Register HubGo triggers in Joinotify.
     *
     * @since 2.1.0
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
     * @since 2.1.0
     * @return void
     */
    public function add_triggers_tab() {
        $integration_slug = 'hubgo';
        $integration_name = esc_html__( 'HubGo', 'hubgo' );
        $icon_svg = '<svg class="joinotify-tab-icon hubgo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 272.84 152.99"><defs><style>.hubgo-1{fill:#008aff;}.hubgo-2{fill:#232323;}</style></defs><g id="Icon"><g id="Icon-2" data-name="Icon"><g id="Airplane"><path class="hubgo-1" d="M601.94,295.67c1.75,4.52,6.86,0,6.56-3.29l1.78-39.26-16.7,9.05Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-1" d="M630.77,217.59c-8.62,1.84-17.75,2.72-24.54,9.12-13.16,10.11-36.44,30.92-54.15,39.8-55,25.09-115.12,40.9-172.42,38.09-20.59-1.47-21.89,30.28-1.11,30.42,44.74-3.17,90.28-13.37,130.27-30.66,27.77-11.46,51.52-28.55,77.3-42.73,11.29-6.59,35.93-16.07,43.66-27.39C633.38,229.34,642.18,220,630.77,217.59Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-1" d="M552.62,221.23l27.84,21,14.94-11.94-37.19-13.79C555.26,214.92,549.15,217.87,552.62,221.23Z" transform="translate(-363.58 -216.05)"/></g><g id="H"><path class="hubgo-2" d="M445.26,242.32c.23-16.14-25.11-16.14-24.88,0v54.27c7.51-.78,15.8-1.94,24.88-3.6Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-2" d="M420.38,356.84c-.22,16.13,25.11,16.14,24.88,0V332.36q-12.22,2.77-24.88,4.86Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-2" d="M533.72,267.22v-24.9c-.07-16.27-24.83-16.27-24.9,0v33.95C516.73,273.58,525.33,270.54,533.72,267.22Z" transform="translate(-363.58 -216.05)"/><path class="hubgo-2" d="M508.82,356.84c.07,16.27,24.83,16.26,24.9,0V300.43q-12.12,6.29-24.9,11.7Z" transform="translate(-363.58 -216.05)"/></g></g></g></svg>';

        $this->render_integration_trigger_tab( $integration_slug, $integration_name, $icon_svg );
    }


    /**
     * Render HubGo trigger content in Joinotify builder.
     *
     * @since 2.1.0
     * @return void
     */
    public function add_triggers_content() {
        $this->render_integration_trigger_content( 'hubgo' );
    }


    /**
     * Register HubGo placeholders.
     *
     * @since 2.1.0
     * @param array $placeholders | Existing placeholders.
     * @param array $payload | Trigger payload.
     * @return array
     */
    public function add_placeholders( $placeholders, $payload ) {
        $trigger_names = array(
            'hubgo_order_sent',
            'hubgo_tracking_saved',
        );

        $tracking_data = isset( $payload['tracking_data'] ) && is_array( $payload['tracking_data'] ) ? $payload['tracking_data'] : array();
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
     * @since 2.1.0
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
     * @since 2.1.0
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
     * @since 2.1.0
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

        Workflow_Processor::process_workflows(
            apply_filters( 'Joinotify/Process_Workflows/HubGo', $payload )
        );

        do_action( 'joinotify_' . $hook, $payload );
    }


    /**
     * Build normalized tracking data for payload/placeholders.
     *
     * @since 2.1.0
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
