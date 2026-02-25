<?php

namespace MeuMouse\Hubgo\Admin\Views\Settings\Components;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Textarea
 *
 * Renders textarea fields
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Components
 * @author MeuMouse.com
 */
class Textarea {

    /**
     * Render a textarea
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
        $default = Settings::get_default_value( $name, '' );
        $value = Settings::get_setting( $name, $default );

        $args = wp_parse_args( $args, array(
            'class' => 'form-control',
            'placeholder' => '',
            'rows' => 3,
        ));

        $content = sprintf(
            '<textarea id="%s" name="%s" class="%s" rows="%s" placeholder="%s">%s</textarea>',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $args['class'] ),
            esc_attr( $args['rows'] ),
            esc_attr( $args['placeholder'] ),
            esc_textarea( $value )
        );

        Fields::wrapper( $label, $description, $content );
    }
}