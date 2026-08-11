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
     * @version 3.1.0
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
            // Storefront copy. Wrapped in __() so a store that never edits these
            // fields still reads them in the site language; the moment the
            // merchant saves the settings screen the literal value is persisted
            // and the translation stops applying, which is the expected trade.
            'text_info_before_input_shipping_calc' => __( 'Check the delivery time and price', 'hubgo' ),
            'text_button_shipping_calc'            => __( 'Calculate', 'hubgo' ),
            'text_header_ship'                     => __( 'Delivery', 'hubgo' ),
            'text_header_value'                    => __( 'Price', 'hubgo' ),
            'note_text_bottom_shipping_calc'       => __( '*This result is only an estimate for this product. The final amount is the one calculated for the whole cart.', 'hubgo' ),
            'text_placeholder_input_shipping_calc' => __( 'Enter your postcode', 'hubgo' ),

            // Storefront calculator (3.0.0). Empty strings hide the element they
            // belong to, so a store that wants a leaner card just clears them.
            'text_calculator_title'                => __( 'Calculate shipping and delivery time', 'hubgo' ),
            'text_free_shipping_badge'             => __( 'Free shipping over %s', 'hubgo' ),
            'text_free_shipping_active'            => __( 'Free shipping on this product', 'hubgo' ),
            'text_more_options'                    => __( 'More details and delivery methods', 'hubgo' ),
            'text_cep_finder_link'                 => __( 'I do not know my postcode', 'hubgo' ),
            'text_preference_hint'                 => __( 'It will be pre-selected at the checkout', 'hubgo' ),
            'text_preference_saved'                => __( 'Option saved as your preference', 'hubgo' ),
            'text_clear_preference'                => __( 'Clear preference', 'hubgo' ),

            // Delivery forecast and free-shipping badge. An empty threshold
            // means "read it from the shipping zone" — see Free_Shipping_Context.
            'shipping_handling_days'               => '0',
            'free_shipping_threshold'              => '',

            // Preferred shipping method carried into the checkout.
            'enable_shipping_preference'           => 'yes',
            'shipping_preference_apply_postcode'   => 'yes',
            'shipping_preference_fallback'         => 'same_method',
            'shipping_preference_ttl'              => '30',

            // Calculator appearance. Every style key defaults to an empty string
            // on purpose: empty means "use the built-in value", which keeps the
            // CSS custom properties defined in exactly one place (the storefront
            // stylesheet) instead of duplicating them here where they would
            // silently drift. Only primary_main_color predates 3.0.0 and keeps
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
            'calc_badge_radius'                    => '',
            'calc_muted_bg'                        => '',
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

            // Maintenance ("About" tab). enable_auto_updates is read by
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
            // Same story as Frenet below: the Melhor Envio plugin prints its own
            // simulator on the product page — see Integrations\Melhor_Envio.
            'melhor_envio_disable_simulator'       => 'yes',
            'melhor_envio_tracking_url'            => '',
            'enable_elementor_integration'         => 'no',
            'enable_frenet_integration'            => 'no',
            'frenet_sync_tracking'                 => 'yes',
            // The Frenet plugin prints its own simulator on the product page,
            // which duplicates HubGo's calculator. Only applied while HubGo's
            // calculator is on — see Integrations\Frenet.
            'frenet_disable_simulator'             => 'yes',
            'frenet_tracking_url'                  => '',
            'enable_shipment_tracking_integration' => 'no',
            'shipment_tracking_show_items'         => 'yes',
            // Google Maps. Both sub-toggles default to "yes" so switching the
            // card on delivers what the card advertises; they exist to turn one
            // of the two billed APIs back off, not to be found before the
            // feature works at all.
            'enable_google_maps_integration'       => 'no',
            'google_maps_api_key'                  => '',
            'google_maps_enable_finder'            => 'yes',
            'google_maps_enable_address_lookup'    => 'yes',
        ));
    }
}
