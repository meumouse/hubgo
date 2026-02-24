<?php

namespace MeuMouse\Hubgo\Admin\Views\Settings\Components;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Color
 *
 * Renders color picker fields
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Components
 * @author MeuMouse.com
 */
class Color {

    /**
     * Render a color picker
     *
     * @since 2.0.0
     * @param string $id Field ID
     * @param string $name Field name
     * @param string $label Field label
     * @param string $description Field description
     * @param array $args Additional arguments
     * @return void
     */
    public static function render( $id, $name, $label, $description = '', $args = array() ) {
        $value = Settings::get_setting( $name, '#008aff' );
        
        $args = wp_parse_args( $args, array(
            'class' => 'form-control-color',
        ));

        $content = sprintf(
            '<input type="color" id="%s" name="%s" class="%s" value="%s" />',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $args['class'] ),
            esc_attr( $value )
        );

        Fields::wrapper( $label, $description, $content );
    }
}