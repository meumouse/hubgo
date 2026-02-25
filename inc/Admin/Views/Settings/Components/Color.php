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
        $default = Settings::get_default_value( $name, '#008aff' );
        $value = Settings::get_setting( $name, $default );
        
        $args = wp_parse_args( $args, array(
            'class' => 'form-control-color',
        ));

        $content = sprintf(
            '<div class="color-container input-group">'
                . '<input type="color" id="%1$s" name="%2$s" class="%3$s" value="%4$s" />'
                . '<input type="text" class="get-color-selected form-control input-control-wd-10" value="%4$s" />'
                . '<button type="button" class="btn btn-outline-secondary btn-icon reset-color hubgo-tooltip" data-color="%5$s" data-text="%6$s">'
                    . '<svg class="icon-button" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12 16c1.671 0 3-1.331 3-3s-1.329-3-3-3-3 1.331-3 3 1.329 3 3 3z"></path><path d="M20.817 11.186a8.94 8.94 0 0 0-1.355-3.219 9.053 9.053 0 0 0-2.43-2.43 8.95 8.95 0 0 0-3.219-1.355 9.028 9.028 0 0 0-1.838-.18V2L8 5l3.975 3V6.002c.484-.002.968.044 1.435.14a6.961 6.961 0 0 1 2.502 1.053 7.005 7.005 0 0 1 1.892 1.892A6.967 6.967 0 0 1 19 13a7.032 7.032 0 0 1-.55 2.725 7.11 7.11 0 0 1-.644 1.188 7.2 7.2 0 0 1-.858 1.039 7.028 7.028 0 0 1-3.536 1.907 7.13 7.13 0 0 1-2.822 0 6.961 6.961 0 0 1-2.503-1.054 7.002 7.002 0 0 1-1.89-1.89A6.996 6.996 0 0 1 5 13H3a9.02 9.02 0 0 0 1.539 5.034 9.096 9.096 0 0 0 2.428 2.428A8.95 8.95 0 0 0 12 22a9.09 9.09 0 0 0 1.814-.183 9.014 9.014 0 0 0 3.218-1.355 8.886 8.886 0 0 0 1.331-1.099 9.228 9.228 0 0 0 1.1-1.332A8.952 8.952 0 0 0 21 13a9.09 9.09 0 0 0-.183-1.814z"></path></svg>'
                . '</button>'
            . '</div>',
            esc_attr( $id ),
            esc_attr( $name ),
            esc_attr( $args['class'] ),
            esc_attr( $value ),
            esc_attr( $default ),
            esc_attr__( 'Redefinir para cor padrão', 'hubgo' )
        );

        Fields::wrapper( $label, $description, $content );
    }
}