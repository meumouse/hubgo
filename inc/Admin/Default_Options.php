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
     * @return array Default options
     */
    public static function get_defaults() {
        return apply_filters( 'Hubgo/Admin/Default_Options', array(
            'enable_shipping_calculator'           => 'yes',
            'enable_auto_shipping_calculator'      => 'yes',
            'primary_main_color'                   => '#008aff',
            'hook_display_shipping_calculator'     => 'after_cart',
            'text_info_before_input_shipping_calc' => 'Consultar prazo e valor da entrega',
            'text_button_shipping_calc'            => 'Calcular',
            'text_header_ship'                     => 'Entrega',
            'text_header_value'                    => 'Valor',
            'note_text_bottom_shipping_calc'       => '*Este resultado é apenas uma estimativa para este produto. O valor final considerado, deverá ser o total do carrinho.',
            'text_placeholder_input_shipping_calc' => 'Informe seu CEP',
        ));
    }
}
