<?php

namespace MeuMouse\Hubgo\Views;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Custom_Colors
 *
 * Injects the legacy `--hubgo-primary-color` variable based on user settings.
 *
 * @deprecated 3.0.0 Superseded by {@see Calculator_Styles}, which drives the
 * storefront calculator through the `--hubgo-calc-*` token set. The class stays
 * because `Hubgo/Core/Custom_Colors/Css_Rules` and `Hubgo/Core/Custom_Colors/Primary_Color`
 * are published hooks, and because sites may have custom CSS keyed on the
 * variable — but it no longer ships rules of its own: the selectors it used to
 * target (`#hubgo-postcode`, `#hubgo-shipping-calc-button`) belonged to the
 * pre-3.0.0 markup and match nothing now.
 *
 * @since 2.0.0
 * @version 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Custom_Colors {

    /**
     * CSS variable name for primary color
     *
     * @since 2.0.0
     * @var string
     */
    const PRIMARY_COLOR_VAR = '--hubgo-primary-color';

    /**
     * Default primary color fallback
     *
     * @since 2.0.0
     * @var string
     */
    const DEFAULT_PRIMARY_COLOR = '#008aff';

    /**
     * Settings key for primary color
     *
     * @since 2.0.0
     * @var string
     */
    const PRIMARY_COLOR_SETTING_KEY = 'primary_main_color';


    /**
     * Constructor
     *
     * @since 2.0.0
     */
    public function __construct() {
        $this->init_hooks();
    }


    /**
     * Initialize WordPress hooks
     *
     * @since 2.0.0
     * @return void
     */
    private function init_hooks() {
        add_action( 'wp_head', array( $this, 'render_custom_styles' ) );
    }


    /**
     * Render custom styles in wp_head
     *
     * @since 2.0.0
     * @return void
     */
    public function render_custom_styles() {
        $primary_color = $this->get_primary_color();
        
        if ( empty( $primary_color ) ) {
            return;
        }

        $this->output_styles( $primary_color );
    }


    /**
     * Get primary color from settings
     *
     * @since 2.0.0
     * @return string
     */
    private function get_primary_color() {
        $primary_color = Settings::get_setting( self::PRIMARY_COLOR_SETTING_KEY );
        
        if ( empty( $primary_color ) ) {
            return '';
        }

        /**
         * Filter primary color
         *
         * Allows modification of the primary color value
         *
         * @since 2.0.0
         * @param string $primary_color Primary color hex value
         */
        return apply_filters( 'Hubgo/Core/Custom_Colors/Primary_Color', $primary_color );
    }


    /**
     * Output CSS styles
     *
     * @since 2.0.0
     * @param string $primary_color Primary color hex value
     * @return void
     */
    private function output_styles( $primary_color ) {
        // Validate color format
        if ( ! $this->is_valid_hex_color( $primary_color ) ) {
            $primary_color = self::DEFAULT_PRIMARY_COLOR;
        }

        $css_variables = $this->get_css_variables( $primary_color );
        $css_rules = $this->get_css_rules();
        
        ?>
        <style type="text/css" id="hubgo-custom-colors">
            <?php echo $this->minify_css( $css_variables ); ?>
            <?php echo $this->minify_css( $css_rules ); ?>
        </style>
        <?php
    }


    /**
     * Get CSS variables
     *
     * @since 2.0.0
     * @param string $primary_color Primary color value
     * @return string
     */
    private function get_css_variables( $primary_color ) {
        return sprintf(
            ':root { %s: %s; }',
            self::PRIMARY_COLOR_VAR,
            esc_attr( $primary_color )
        );
    }


    /**
     * Get CSS rules
     *
     * Empty since 3.0.0: every selector this used to emit belonged to the
     * server-rendered calculator that the Vue storefront replaced, so the rules
     * matched nothing while still being printed on every page. The filter runs
     * on an empty array so third parties that hooked it keep working.
     *
     * @since 2.0.0
     * @version 3.0.0
     * @return string
     */
    private function get_css_rules() {
        $rules = array();

        /**
         * Filter CSS rules
         *
         * Allows adding or modifying CSS rules
         *
         * @since 2.0.0
         * @param array $rules Associative array of CSS rules
         */
        $rules = apply_filters( 'Hubgo/Core/Custom_Colors/Css_Rules', $rules );

        return $this->build_css_rules( $rules );
    }


    /**
     * Build CSS rules from array
     *
     * @since 2.0.0
     * @param array $rules Associative array of CSS rules
     * @return string
     */
    private function build_css_rules( $rules ) {
        $css = '';

        foreach ( $rules as $selector => $properties ) {
            $css .= $selector . ' { ';
            
            foreach ( $properties as $property => $value ) {
                $css .= $property . ': ' . $value . '; ';
            }
            
            $css .= '} ';
        }

        return $css;
    }


    /**
     * Validate hex color
     *
     * @since 2.0.0
     * @param string $color Color to validate
     * @return bool
     */
    private function is_valid_hex_color( $color ) {
        // Remove # if present
        $color = ltrim( $color, '#' );
        
        // Check if it's a valid hex color (3 or 6 characters)
        return ctype_xdigit( $color ) && in_array( strlen( $color ), array( 3, 6 ), true );
    }


    /**
     * Minify CSS output
     *
     * Removes unnecessary whitespace and comments
     *
     * @since 2.0.0
     * @param string $css Raw CSS
     * @return string
     */
    private function minify_css( $css ) {
        // Remove comments
        $css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
        
        // Remove whitespace
        $css = str_replace( array( "\r\n", "\r", "\n", "\t", '  ', '    ', '    ' ), '', $css );
        
        return $css;
    }


    /**
     * Get inline styles for specific element
     *
     * @since 2.0.0
     * @param string $element Element selector
     * @return string
     */
    public static function get_element_styles( $element ) {
        $primary_color = Settings::get_setting( self::PRIMARY_COLOR_SETTING_KEY );
        
        if ( empty( $primary_color ) ) {
            $primary_color = self::DEFAULT_PRIMARY_COLOR;
        }

        $styles = array(
            'button' => 'background-color: ' . esc_attr( $primary_color ) . ';',
            'header' => 'color: ' . esc_attr( $primary_color ) . ';',
            'link'   => 'color: ' . esc_attr( $primary_color ) . ';',
        );

        return isset( $styles[ $element ] ) ? $styles[ $element ] : '';
    }
}