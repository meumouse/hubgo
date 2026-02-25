<?php

namespace MeuMouse\Hubgo\Admin\Views\Settings\Components;

use MeuMouse\Hubgo\Admin\Settings;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Class Toggle
 *
 * Renders toggle switch fields
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Components
 * @author MeuMouse.com
 */
class Toggle {

    /**
     * Render a toggle switch
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
        $value = Settings::get_setting( $name, Settings::get_default_value( $name ) );
        $checked = checked( $value === 'yes', true, false );
        
        $args = wp_parse_args( $args, array(
            'class' => 'toggle-switch',
        ));

        $content = sprintf(
            '<div class="form-check form-switch">
                <input type="checkbox" class="%s" id="%s" name="%s" value="yes" %s />
            </div>',
            esc_attr( $args['class'] ),
            esc_attr( $id ),
            esc_attr( $name ),
            $checked
        );

        Fields::wrapper( $label, $description, $content );
    }
}