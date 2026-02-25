<?php

/**
 * Appearance Settings Tab
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Tabs
 * @author MeuMouse.com
 */

use MeuMouse\Hubgo\Admin\Views\Settings\Components\Color;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Text;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Textarea;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="appearance" class="hubgo-settings-tab" style="display: none;">
    <table class="form-table">
        <?php
        Color::render(
            'primary_main_color',
            'primary_main_color',
            __( 'Cor principal', 'hubgo' ),
            __( 'Selecione a cor principal para botões e outros estilos.', 'hubgo' )
        );

        Text::render(
            'text_header_ship',
            'text_header_ship',
            __( 'Texto do cabeçalho das formas de entrega', 'hubgo' ),
            __( 'Deixe em branco para não exibir.', 'hubgo' ),
            array(
                'placeholder' => __( 'Entrega', 'hubgo' ),
            )
        );

        Text::render(
            'text_header_value',
            'text_header_value',
            __( 'Texto do cabeçalho do valor das formas de entrega', 'hubgo' ),
            __( 'Deixe em branco para não exibir.', 'hubgo' ),
            array(
                'placeholder' => __( 'Valor', 'hubgo' ),
            )
        );

        Textarea::render(
            'note_text_bottom_shipping_calc',
            'note_text_bottom_shipping_calc',
            __( 'Texto de observação inferior das opções de frete', 'hubgo' ),
            __( 'Deixe em branco para não exibir.', 'hubgo' ),
            array(
                'placeholder' => __( '*Este resultado é apenas uma estimativa...', 'hubgo' ),
                'class' => 'form-control',
                'rows' => 4,
            )
        );
        ?>
    </table>
</div>