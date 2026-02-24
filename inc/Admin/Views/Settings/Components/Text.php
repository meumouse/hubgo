<?php

namespace MeuMouse\Hubgo\Admin\Views\Settings\Components;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Text
 *
 * Renders text input fields
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Components
 * @author MeuMouse.com
 */
class Text {

    /**
     * Render a text input
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
        $value = Settings::get_setting( $name, '' );
        
        $args = wp_parse_args( $args, array(
            'class' => 'form-control input-control-wd-20',
            'type' => 'text',
            'placeholder' => '',
        ));

        $content = sprintf(
            '<input type="%s" id="%s" name="%s" class="%s" value="%s" placeholder="%s" />',
            esc_attr( $args['type'] ),
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $args['class'] ),
            esc_attr( $value ),
            esc_attr( $args['placeholder'] )
        );

        Fields::wrapper( $label, $description, $content );
    }
}