<?php

namespace MeuMouse\Hubgo\Admin;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Default_Options
 *
 * Centralizes all default option values used throughout the plugin
 *
 * @since 2.0.0
 * @version 2.1.0
 * @package MeuMouse\Hubgo\Admin
 * @author MeuMouse.com
 */
class Default_Options {

    /**
     * Get default options array
     *
     * Returns an array of all default plugin settings.
     * Can be filtered via 'Hubgo/Admin/Default_Options' filter.
     *
     * @since 2.0.0
     * @version 3.0.0
     * @return array Default options
     */
    public static function get_defaults() {
        return apply_filters( 'Hubgo/Admin/Default_Options', array(
            'enable_shipping_calculator'           => 'yes',
            'enable_auto_shipping_calculator'      => 'no',
            'enable_order_shipped_status'          => 'yes',
            'enable_order_tracking_admin_ui'       => 'yes',
            'primary_main_color'                   => '#008aff',
            'hook_display_shipping_calculator'     => 'after_cart',
            'shipping_methods_display'             => 'table',
            'text_info_before_input_shipping_calc' => 'Consultar prazo e valor da entrega',
            'text_button_shipping_calc'            => 'Calcular',
            'text_header_ship'                     => 'Entrega',
            'text_header_value'                    => 'Valor',
            'note_text_bottom_shipping_calc'       => '*Este resultado é apenas uma estimativa para este produto. O valor final considerado, deverá ser o total do carrinho.',
            'text_placeholder_input_shipping_calc' => 'Informe seu CEP',

            // Maintenance ("Sobre" tab). enable_auto_updates is read by
            // Core\License::enable_auto_update() and had no default before 3.0.0.
            'enable_auto_updates'                  => 'no',
            'enable_update_notice'                 => 'yes',
            'enable_debug_mode'                    => 'no',

            // Integrations. Joinotify defaults to "yes" because HubGo registered
            // its triggers unconditionally before the Integrations screen
            // existed — flipping it off by default would silently break flows
            // already running on updated sites.
            'enable_joinotify_integration'         => 'yes',
            'enable_melhor_envio_integration'      => 'no',
            'melhor_envio_sync_tracking'           => 'yes',
            'melhor_envio_mark_as_shipped'         => 'no',
            'enable_frenet_integration'            => 'no',
            'frenet_sync_tracking'                 => 'yes',
            'frenet_tracking_url'                  => '',
        ));
    }
}
