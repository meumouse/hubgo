<?php

namespace MeuMouse\Hubgo\Views;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Emit the storefront calculator's CSS custom properties from the settings.
 *
 * The calculator is styled exclusively through custom properties, which is what
 * lets two independent editors drive the same component without either knowing
 * about the other. The cascade resolves the precedence on its own:
 *
 *   1. Fallbacks hardcoded in the Vue stylesheet — always present, so the widget
 *      is never unstyled.
 *   2. This class, printed on `.hubgo-shipping-calculator` in `wp_head` — the
 *      store-wide defaults set on the Aparência tab.
 *   3. Elementor, printed on `{{WRAPPER}} .hubgo-shipping-calculator` — higher
 *      specificity, so a widget's own controls win for that instance only.
 *
 * {@see self::get_token_map()} is the single source of truth for which setting
 * feeds which property. The Elementor widget builds its selectors from the very
 * same map, so adding a control anywhere means editing one array.
 *
 * Only non-empty settings are emitted. An empty value means "keep the built-in
 * value", which is why the style defaults in {@see \MeuMouse\Hubgo\Admin\Default_Options}
 * are empty strings rather than copies of the stylesheet's numbers.
 *
 * @since 3.1.0
 * @package MeuMouse\Hubgo\Views
 * @author MeuMouse.com
 */
class Calculator_Styles {

    /**
     * Root class every calculator instance carries.
     *
     * @since 3.1.0
     * @var string
     */
    const ROOT_CLASS = 'hubgo-shipping-calculator';

    /**
     * Id of the printed style tag.
     *
     * @since 3.1.0
     * @var string
     */
    const STYLE_ID = 'hubgo-calculator-styles';


    /**
     * Constructor.
     *
     * @since 3.1.0
     */
    public function __construct() {
        // Priority 20 keeps this after Views\Custom_Colors, whose legacy block
        // targets the pre-3.1.0 markup and must not win over these tokens.
        add_action( 'wp_head', array( $this, 'render' ), 20 );
    }


    /**
     * Setting key => CSS custom property (and the unit its value takes).
     *
     * @since 3.1.0
     * @return array<string,array<string,string>>
     */
    public static function get_token_map() {
        $map = array(
            // Surface.
            'calc_surface_bg'         => array( 'token' => '--hubgo-calc-surface-bg', 'unit' => '' ),
            'calc_surface_border'     => array( 'token' => '--hubgo-calc-surface-border', 'unit' => '' ),
            'calc_surface_radius'     => array( 'token' => '--hubgo-calc-radius', 'unit' => 'px' ),
            'calc_surface_padding'    => array( 'token' => '--hubgo-calc-surface-padding', 'unit' => 'px' ),

            // Typography and accent.
            'primary_main_color'      => array( 'token' => '--hubgo-calc-accent', 'unit' => '' ),
            'calc_text_color'         => array( 'token' => '--hubgo-calc-text', 'unit' => '' ),
            'calc_muted_color'        => array( 'token' => '--hubgo-calc-text-muted', 'unit' => '' ),
            'calc_font_size'          => array( 'token' => '--hubgo-calc-body-size', 'unit' => 'px' ),

            // Free shipping badge.
            'calc_badge_bg'           => array( 'token' => '--hubgo-calc-badge-bg', 'unit' => '' ),
            'calc_badge_text_color'   => array( 'token' => '--hubgo-calc-badge-text', 'unit' => '' ),

            // Postcode field.
            'calc_input_bg'           => array( 'token' => '--hubgo-calc-input-bg', 'unit' => '' ),
            'calc_input_border'       => array( 'token' => '--hubgo-calc-input-border', 'unit' => '' ),
            'calc_input_radius'       => array( 'token' => '--hubgo-calc-input-radius', 'unit' => 'px' ),
            'calc_input_height'       => array( 'token' => '--hubgo-calc-input-height', 'unit' => 'px' ),

            // Submit button.
            'calc_button_bg'          => array( 'token' => '--hubgo-calc-button-bg', 'unit' => '' ),
            'calc_button_hover_bg'    => array( 'token' => '--hubgo-calc-button-hover-bg', 'unit' => '' ),
            'calc_button_text_color'  => array( 'token' => '--hubgo-calc-button-text', 'unit' => '' ),
            'calc_button_radius'      => array( 'token' => '--hubgo-calc-button-radius', 'unit' => 'px' ),

            // Delivery option rows.
            'calc_option_border'      => array( 'token' => '--hubgo-calc-option-border', 'unit' => '' ),
            'calc_option_radius'      => array( 'token' => '--hubgo-calc-option-radius', 'unit' => 'px' ),
            'calc_option_selected_bg' => array( 'token' => '--hubgo-calc-option-selected-bg', 'unit' => '' ),

            // Modal shell. The overlay is not exposed on the settings panel:
            // it needs an alpha channel and the panel's colour field speaks hex.
            // Elementor's colour control does emit rgba(), and a filter can set
            // it, so the token stays here for both.
            'calc_modal_bg'           => array( 'token' => '--hubgo-calc-modal-bg', 'unit' => '' ),
            'calc_modal_radius'       => array( 'token' => '--hubgo-calc-modal-radius', 'unit' => 'px' ),
            'calc_modal_overlay'      => array( 'token' => '--hubgo-calc-modal-overlay', 'unit' => '' ),
            'calc_modal_blur'         => array( 'token' => '--hubgo-calc-modal-blur', 'unit' => 'px' ),
        );

        /**
         * Filters the setting -> CSS custom property map of the calculator.
         *
         * Both the settings panel and the Elementor widget build their output
         * from this map, so an entry added here is editable from both.
         *
         * @since 3.1.0
         * @param array<string,array<string,string>> $map Setting key => token/unit.
         */
        return apply_filters( 'Hubgo/Views/Calculator_Styles/Token_Map', $map );
    }


    /**
     * CSS custom property a setting key drives.
     *
     * The Elementor widget builds its `selectors` from this, so a control and
     * the matching panel field always write to the same property.
     *
     * @since 3.1.0
     * @param string $key Setting key.
     * @return string Empty string when the key drives no property.
     */
    public static function get_token( $key ) {
        $map = self::get_token_map();

        return isset( $map[ $key ]['token'] ) ? (string) $map[ $key ]['token'] : '';
    }


    /**
     * Print the custom property block.
     *
     * @since 3.1.0
     * @return void
     */
    public function render() {
        $declarations = self::build_declarations( Settings::get_all() );

        if ( empty( $declarations ) ) {
            return;
        }

        printf(
            '<style id="%1$s">.%2$s{%3$s}</style>' . "\n",
            esc_attr( self::STYLE_ID ),
            esc_attr( self::ROOT_CLASS ),
            esc_html( implode( '', $declarations ) )
        );
    }


    /**
     * Build the `--token:value;` declarations for a settings map.
     *
     * @since 3.1.0
     * @param array $settings Settings map.
     * @return array<int,string>
     */
    public static function build_declarations( $settings ) {
        $settings     = is_array( $settings ) ? $settings : array();
        $declarations = array();

        foreach ( self::get_token_map() as $key => $definition ) {
            $value = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
            $value = self::sanitize_value( $value, isset( $definition['unit'] ) ? (string) $definition['unit'] : '' );

            if ( '' === $value ) {
                continue;
            }

            $declarations[] = $definition['token'] . ':' . $value . ';';
        }

        return $declarations;
    }


    /**
     * Coerce a setting into a value that is safe to drop into a style block.
     *
     * Values reach this from the options table, so they are already sanitized on
     * write — but a style block is the one place where a stray `}` or `<` turns
     * a bad value into markup, so lengths and character sets are checked again.
     *
     * @since 3.1.0
     * @param mixed $value Raw setting value.
     * @param string $unit Unit to append to a numeric value.
     * @return string Empty string when the value cannot be used.
     */
    private static function sanitize_value( $value, $unit ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = trim( (string) $value );

        if ( '' === $value ) {
            return '';
        }

        if ( '' !== $unit ) {
            return is_numeric( $value ) ? ( (string) (float) $value ) . $unit : '';
        }

        $color = sanitize_hex_color( $value );

        if ( $color ) {
            return $color;
        }

        // Keep the door open for rgba()/hsl() values arriving from a filter,
        // while refusing anything that could terminate the declaration.
        if ( preg_match( '/^(?:rgba?|hsla?)\([0-9a-z%.,\s\/]+\)$/i', $value ) ) {
            return $value;
        }

        return '';
    }
}
