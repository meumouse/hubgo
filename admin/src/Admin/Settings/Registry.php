<?php

namespace MeuMouse\Hubgo\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Schema-driven settings registry.
 *
 * Builds the settings schema (sections -> cards -> fields) consumed by the Vue
 * SPA, exposes a flat field-definition map used for sanitization, and assembles
 * the REST bootstrap payload. Everything is filterable for extensibility.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Admin\Settings
 * @author MeuMouse.com
 */
class Registry {

    /**
     * Build the full settings schema.
     *
     * @since 3.0.0
     * @return array
     */
    public static function get_schema() {
        $schema = array(
            array(
                'id'    => 'general',
                'title' => esc_html__( 'Geral', 'hubgo' ),
                'icon'  => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M7.5 14.5c-1.58 0-2.903 1.06-3.337 2.5H2v2h2.163c.434 1.44 1.757 2.5 3.337 2.5s2.903-1.06 3.337-2.5H22v-2H10.837c-.434-1.44-1.757-2.5-3.337-2.5zm9-11c-1.58 0-2.903 1.06-3.337 2.5H2v2h11.163c.434 1.44 1.757 2.5 3.337 2.5s2.903-1.06 3.337-2.5H22v-2h-2.163c-.434-1.44-1.757-2.5-3.337-2.5z"></path></svg>',
                'cards' => array(
                    array(
                        'id'          => 'general-features',
                        'title'       => esc_html__( 'Funcionalidades', 'hubgo' ),
                        'description' => esc_html__( 'Ative ou desative os módulos do HubGo.', 'hubgo' ),
                        'fields'      => array(
                            self::toggle( 'enable_shipping_calculator', esc_html__( 'Calculadora de frete', 'hubgo' ), esc_html__( 'Exibe a calculadora de frete na página do produto.', 'hubgo' ) ),
                            self::toggle( 'enable_auto_shipping_calculator', esc_html__( 'Cálculo automático', 'hubgo' ), esc_html__( 'Calcula o frete automaticamente com o CEP salvo do cliente.', 'hubgo' ) ),
                            self::toggle( 'enable_order_shipped_status', esc_html__( 'Status "Pedido enviado"', 'hubgo' ), esc_html__( 'Adiciona o status de pedido enviado ao WooCommerce.', 'hubgo' ) ),
                            self::toggle( 'enable_order_tracking_admin_ui', esc_html__( 'Rastreio no pedido (admin)', 'hubgo' ), esc_html__( 'Exibe o metabox de código de rastreio na tela do pedido.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'general-calculator',
                        'title'       => esc_html__( 'Calculadora', 'hubgo' ),
                        'description' => esc_html__( 'Posição e textos da calculadora de frete.', 'hubgo' ),
                        'fields'      => array(
                            self::select( 'hook_display_shipping_calculator', esc_html__( 'Posição de exibição', 'hubgo' ), esc_html__( 'Onde exibir a calculadora na página do produto.', 'hubgo' ), array(
                                array( 'value' => 'after_cart', 'label' => esc_html__( 'Após o botão de compra', 'hubgo' ) ),
                                array( 'value' => 'before_cart', 'label' => esc_html__( 'Antes do botão de compra', 'hubgo' ) ),
                                array( 'value' => 'meta_end', 'label' => esc_html__( 'Após os metadados do produto', 'hubgo' ) ),
                                array( 'value' => 'shortcode', 'label' => esc_html__( 'Somente via shortcode', 'hubgo' ) ),
                            ) ),
                            self::text( 'text_info_before_input_shipping_calc', esc_html__( 'Texto de informação', 'hubgo' ), esc_html__( 'Texto exibido acima do campo de CEP.', 'hubgo' ) ),
                            self::text( 'text_button_shipping_calc', esc_html__( 'Texto do botão', 'hubgo' ), esc_html__( 'Rótulo do botão de calcular.', 'hubgo' ) ),
                            self::text( 'text_placeholder_input_shipping_calc', esc_html__( 'Placeholder do campo', 'hubgo' ), esc_html__( 'Texto de exemplo dentro do campo de CEP.', 'hubgo' ) ),
                        ),
                    ),
                ),
            ),
            array(
                'id'    => 'appearance',
                'title' => esc_html__( 'Aparência', 'hubgo' ),
                'icon'  => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16z"></path><circle cx="7.5" cy="12" r="1.5"></circle><circle cx="12" cy="7.5" r="1.5"></circle><circle cx="16.5" cy="12" r="1.5"></circle></svg>',
                'cards' => array(
                    array(
                        'id'          => 'appearance-colors',
                        'title'       => esc_html__( 'Cores e textos', 'hubgo' ),
                        'description' => esc_html__( 'Personalize a aparência da tabela de fretes.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'primary_main_color', esc_html__( 'Cor principal', 'hubgo' ), esc_html__( 'Cor de destaque usada na interface do frete.', 'hubgo' ) ),
                            self::text( 'text_header_ship', esc_html__( 'Cabeçalho "Entrega"', 'hubgo' ), esc_html__( 'Título da coluna de método de entrega.', 'hubgo' ) ),
                            self::text( 'text_header_value', esc_html__( 'Cabeçalho "Valor"', 'hubgo' ), esc_html__( 'Título da coluna de valor.', 'hubgo' ) ),
                            self::textarea( 'note_text_bottom_shipping_calc', esc_html__( 'Nota de rodapé', 'hubgo' ), esc_html__( 'Observação exibida abaixo da tabela de fretes.', 'hubgo' ) ),
                        ),
                    ),
                ),
            ),
        );

        return apply_filters( 'Hubgo/Admin/Settings/Schema', $schema );
    }


    /**
     * Flat map of field key => definition (used for sanitization).
     *
     * @since 3.0.0
     * @return array
     */
    public static function get_field_definitions() {
        $definitions = array();

        foreach ( self::get_schema() as $section ) {
            foreach ( ( $section['cards'] ?? array() ) as $card ) {
                foreach ( ( $card['fields'] ?? array() ) as $field ) {
                    if ( ! empty( $field['key'] ) ) {
                        $definitions[ $field['key'] ] = $field;
                    }
                }
            }
        }

        return apply_filters( 'Hubgo/Admin/Settings/Field_Definitions', $definitions );
    }


    /**
     * Build the REST bootstrap payload for the SPA.
     *
     * @since 3.0.0
     * @return array
     */
    public static function get_bootstrap_data() {
        $data = array(
            'version'  => defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '',
            'settings' => Repository::get_settings(),
            'schema'   => self::get_schema(),
            'rest'     => array(
                'root'  => esc_url_raw( rest_url( 'hubgo/v1' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ),
        );

        return apply_filters( 'Hubgo/Admin/Settings/Bootstrap_Data', $data );
    }


    /**
     * Field helpers.
     */
    private static function toggle( $key, $label, $description = '' ) {
        return array( 'key' => $key, 'type' => 'toggle', 'label' => $label, 'description' => $description );
    }

    private static function text( $key, $label, $description = '' ) {
        return array( 'key' => $key, 'type' => 'text', 'label' => $label, 'description' => $description );
    }

    private static function textarea( $key, $label, $description = '' ) {
        return array( 'key' => $key, 'type' => 'textarea', 'label' => $label, 'description' => $description, 'rows' => 3 );
    }

    private static function color( $key, $label, $description = '' ) {
        return array( 'key' => $key, 'type' => 'color', 'label' => $label, 'description' => $description );
    }

    private static function select( $key, $label, $description, $options ) {
        return array( 'key' => $key, 'type' => 'select', 'label' => $label, 'description' => $description, 'options' => $options );
    }
}
