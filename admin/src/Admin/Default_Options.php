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

            // Storefront calculator (3.1.0). Empty strings hide the element they
            // belong to, so a store that wants a leaner card just clears them.
            'text_calculator_title'                => 'Calcular frete e prazo',
            'text_free_shipping_badge'             => 'Frete grátis acima de %s',
            'text_free_shipping_active'            => 'Frete grátis neste produto',
            'text_more_options'                    => 'Mais detalhes e formas de entrega',
            'text_cep_finder_link'                 => 'Não sei meu CEP',
            'text_preference_hint'                 => 'Será pré-selecionada no checkout',
            'text_preference_saved'                => 'Opção salva como sua preferência',
            'text_clear_preference'                => 'Remover preferência',

            // Delivery forecast and free-shipping badge. An empty threshold
            // means "read it from the shipping zone" — see Free_Shipping_Context.
            'shipping_handling_days'               => '0',
            'free_shipping_threshold'              => '',

            // Address lookup ("Não sei meu CEP"). ViaCEP needs no credential, so
            // the feature works on a fresh install without any setup.
            'address_lookup_provider'              => 'viacep',
            'google_places_api_key'                => '',

            // Preferred shipping method carried into the checkout.
            'enable_shipping_preference'           => 'yes',
            'shipping_preference_apply_postcode'   => 'yes',
            'shipping_preference_fallback'         => 'same_method',
            'shipping_preference_ttl'              => '30',

            // Calculator appearance. Every style key defaults to an empty string
            // on purpose: empty means "use the built-in value", which keeps the
            // CSS custom properties defined in exactly one place (the storefront
            // stylesheet) instead of duplicating them here where they would
            // silently drift. Only primary_main_color predates 3.1.0 and keeps
            // its concrete default.
            'calc_surface_bg'                      => '',
            'calc_surface_border'                  => '',
            'calc_surface_radius'                  => '',
            'calc_surface_padding'                 => '',
            'calc_text_color'                      => '',
            'calc_muted_color'                     => '',
            'calc_font_size'                       => '',
            'calc_badge_bg'                        => '',
            'calc_badge_text_color'                => '',
            'calc_input_bg'                        => '',
            'calc_input_border'                    => '',
            'calc_input_radius'                    => '',
            'calc_input_height'                    => '',
            'calc_button_bg'                       => '',
            'calc_button_hover_bg'                 => '',
            'calc_button_text_color'               => '',
            'calc_button_radius'                   => '',
            'calc_option_border'                   => '',
            'calc_option_radius'                   => '',
            'calc_option_selected_bg'              => '',
            'calc_modal_bg'                        => '',
            'calc_modal_radius'                    => '',
            'calc_modal_overlay'                   => '',
            'calc_modal_blur'                      => '',

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
            'enable_elementor_integration'         => 'no',
            'enable_frenet_integration'            => 'no',
            'frenet_sync_tracking'                 => 'yes',
            'frenet_tracking_url'                  => '',
        ));
    }
}
