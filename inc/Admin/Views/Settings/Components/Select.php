<?php

namespace MeuMouse\Hubgo\Admin\Views\Settings\Components;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Select
 *
 * Renders select dropdown fields
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Components
 * @author MeuMouse.com
 */
class Select {

    /**
     * Render a select dropdown
     *
     * @since 2.0.0
     * @param string $id Field ID
     * @param string $name Field name
     * @param string $label Field label
     * @param array $options Select options
     * @param string $description Field description
     * @param array $args Additional arguments
     * @return void
     */
    public static function render( $id, $name, $label, $options, $description = '', $args = array() ) {
        $value = Settings::get_setting( $name );
        
        $args = wp_parse_args( $args, array(
            'class' => 'form-select',
        ));

        $options_html = '';
        foreach ( $options as $option_value => $option_label ) {
            $selected = selected( $value === $option_value, true, false );
            $options_html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $option_value ),
                $selected,
                esc_html( $option_label )
            );
        }

        $content = sprintf(
            '<select id="%s" name="%s" class="%s">%s</select>',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $args['class'] ),
            $options_html
        );

        Fields::wrapper( $label, $description, $content );
    }
}