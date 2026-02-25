<?php

/**
 * Advanced Settings Tab
 *
 * @since 2.0.0
 * @package MeuMouse\Hubgo\Admin\Views\Settings\Tabs
 * @author MeuMouse.com
 */

use MeuMouse\Hubgo\Admin\Views\Settings\Components\Toggle;
use MeuMouse\Hubgo\Admin\Views\Settings\Components\Select;

// Exit if accessed directly.
defined('ABSPATH') || exit; ?>

<div id="advanced" class="hubgo-settings-tab" style="display: none;">
    <table class="form-table">
        <?php
        Toggle::render(
            'enable_auto_updates',
            'enable_auto_updates',
            __( 'Ativar atualizações automáticas', 'hubgo' ),
            __( 'Ative esta opção para receber atualizações automáticas do plugin.', 'hubgo' )
        );

        Select::render(
            'shipping_methods_display',
            'shipping_methods_display',
            __( 'Exibição dos métodos de frete', 'hubgo' ),
            array(
                'table' => __( 'Tabela', 'hubgo' ),
                'list' => __( 'Lista', 'hubgo' ),
                'dropdown' => __( 'Dropdown', 'hubgo' ),
            ),
            __( 'Escolha como os métodos de frete serão exibidos.', 'hubgo' )
        );
        ?>
    </table>
</div>