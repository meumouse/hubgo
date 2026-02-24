<?php

/**
 * General Settings Tab
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Tabs
 * @author MeuMouse.com
 */

use MeuMouse\Hubgo\Admin\Views\Settings\Components\Toggle;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Select;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Text;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="general" class="hubgo-settings-tab">
    <table class="form-table">
        <?php
        Toggle::render(
            'enable_shipping_calculator',
            'enable_shipping_calculator',
            __( 'Ativar calculadora de frete', 'hubgo' ),
            __( 'Ative esta opção para adicionar uma calculadora de frete na página de produto individual.', 'hubgo' )
        );

        Toggle::render(
            'enable_auto_shipping_calculator',
            'enable_auto_shipping_calculator',
            __( 'Ativar cálculo automático de frete', 'hubgo' ),
            __( 'Ative esta opção para que o frete seja calculado de forma automática.', 'hubgo' )
        );

        Select::render(
            'hook_display_shipping_calculator',
            'hook_display_shipping_calculator',
            __( 'Posição da calculadora de frete', 'hubgo' ),
            array(
                'after_cart' => __( 'Depois do carrinho (Padrão)', 'hubgo' ),
                'before_cart' => __( 'Antes do carrinho', 'hubgo' ),
                'meta_end' => __( 'Final das informações adicionais', 'hubgo' ),
                'shortcode' => __( 'Shortcode', 'hubgo' ),
            ),
            __( 'Selecione onde será exibido o cálculo de frete na página de produto individual. Shortcode disponível: [hubgo_shipping_calculator]', 'hubgo' )
        );

        Text::render(
            'text_info_before_input_shipping_calc',
            'text_info_before_input_shipping_calc',
            __( 'Texto informativo antes do campo de CEP', 'hubgo' ),
            __( 'Deixe em branco para não exibir.', 'hubgo' ),
            array(
                'placeholder' => __( 'Consultar prazo e valor da entrega', 'hubgo' ),
            )
        );

        Text::render(
            'text_button_shipping_calc',
            'text_button_shipping_calc',
            __( 'Texto do botão da calculadora de frete', 'hubgo' ),
            __( 'Deixe em branco para não exibir.', 'hubgo' ),
            array(
                'placeholder' => __( 'Calcular', 'hubgo' ),
            )
        );

        Text::render(
            'text_placeholder_input_shipping_calc',
            'text_placeholder_input_shipping_calc',
            __( 'Texto do espaço reservado do campo de CEP', 'hubgo' ),
            __( 'Deixe em branco para não exibir.', 'hubgo' ),
            array(
                'placeholder' => __( 'Informe seu CEP', 'hubgo' ),
            )
        );
        ?>
    </table>
</div>