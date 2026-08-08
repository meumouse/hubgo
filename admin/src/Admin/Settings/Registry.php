<?php

namespace MeuMouse\Hubgo\Admin\Settings;

use MeuMouse\Hubgo\Admin\System_Status;
use MeuMouse\Hubgo\Core\License;
use MeuMouse\Hubgo\Integrations\Integrations_Base;

defined('ABSPATH') || exit;

/**
 * Schema-driven settings registry.
 *
 * Builds the settings schema (sections -> cards -> fields) consumed by the Vue
 * SPA, assembles the integrations catalog rendered by the Integrations screen,
 * exposes a flat field-definition map used for sanitization, and packs the REST
 * bootstrap payloads. Everything is filterable for extensibility.
 *
 * A card either declares `fields` (rendered by the shared field registry) or a
 * `component` name (rendered by a page-local Vue component). Integration cards
 * live outside the schema but reuse the very same field definitions, so their
 * values are sanitized and persisted by the same repository.
 *
 * @since 3.0.0
 * @version 3.0.0
 * @package MeuMouse\Hubgo\Admin\Settings
 * @author MeuMouse.com
 */
class Registry {

    /**
     * Build the full settings schema.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @return array
     */
    public static function get_schema() {
        $schema = array(
            array(
                'id'          => 'general',
                'title'       => esc_html__( 'Geral', 'hubgo' ),
                'description' => esc_html__( 'Ative os módulos do HubGo e defina onde a calculadora aparece.', 'hubgo' ),
                'icon'        => 'slider-alt',
                'layout'      => 'fields',
                'cards'       => array(
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
                        'description' => esc_html__( 'Onde e como a calculadora de frete é exibida.', 'hubgo' ),
                        'fields'      => array(
                            self::select( 'hook_display_shipping_calculator', esc_html__( 'Posição de exibição', 'hubgo' ), esc_html__( 'Onde exibir a calculadora na página do produto.', 'hubgo' ), array(
                                array( 'value' => 'after_cart', 'label' => esc_html__( 'Após o botão de compra', 'hubgo' ) ),
                                array( 'value' => 'before_cart', 'label' => esc_html__( 'Antes do botão de compra', 'hubgo' ) ),
                                array( 'value' => 'meta_end', 'label' => esc_html__( 'Após os metadados do produto', 'hubgo' ) ),
                                array( 'value' => 'shortcode', 'label' => esc_html__( 'Somente via shortcode', 'hubgo' ) ),
                            ) ),
                            self::number( 'shipping_handling_days', esc_html__( 'Dias de manuseio', 'hubgo' ), esc_html__( 'Dias úteis somados ao prazo da transportadora antes de mostrar a data de entrega.', 'hubgo' ), array(
                                'min'  => 0,
                                'max'  => 60,
                                'step' => 1,
                                'unit' => esc_html__( 'dias', 'hubgo' ),
                            ) ),
                            self::number( 'free_shipping_threshold', esc_html__( 'Valor para frete grátis', 'hubgo' ), esc_html__( 'Deixe vazio para ler automaticamente da zona de entrega do WooCommerce.', 'hubgo' ), array(
                                'min'         => 0,
                                'step'        => 0.01,
                                'placeholder' => esc_html__( 'Automático', 'hubgo' ),
                            ) ),
                        ),
                    ),
                    array(
                        'id'          => 'general-preference',
                        'title'       => esc_html__( 'Método preferido', 'hubgo' ),
                        'description' => esc_html__( 'Leva a opção de entrega escolhida pelo cliente na página do produto para o checkout.', 'hubgo' ),
                        'fields'      => array(
                            self::toggle( 'enable_shipping_preference', esc_html__( 'Salvar método preferido', 'hubgo' ), esc_html__( 'Pré-seleciona no checkout a opção que o cliente escolheu na calculadora.', 'hubgo' ) ),
                            self::toggle( 'shipping_preference_apply_postcode', esc_html__( 'Aplicar o CEP no checkout', 'hubgo' ), esc_html__( 'Preenche o CEP de entrega quando o cliente ainda não informou um endereço.', 'hubgo' ) ),
                            self::select( 'shipping_preference_fallback', esc_html__( 'Quando a opção não existir', 'hubgo' ), esc_html__( 'O que fazer se o método preferido não estiver disponível para o endereço do checkout.', 'hubgo' ), array(
                                array( 'value' => 'same_method', 'label' => esc_html__( 'Usar a mesma transportadora, se houver', 'hubgo' ) ),
                                array( 'value' => 'exact', 'label' => esc_html__( 'Deixar o WooCommerce escolher', 'hubgo' ) ),
                            ) ),
                            self::number( 'shipping_preference_ttl', esc_html__( 'Validade da preferência', 'hubgo' ), esc_html__( 'Por quantos dias a escolha do cliente é lembrada no navegador dele.', 'hubgo' ), array(
                                'min'  => 1,
                                'max'  => 365,
                                'step' => 1,
                                'unit' => esc_html__( 'dias', 'hubgo' ),
                            ) ),
                        ),
                    ),
                    array(
                        'id'          => 'general-address',
                        'title'       => esc_html__( 'Busca de endereço', 'hubgo' ),
                        'description' => esc_html__( 'Alimenta o "Não sei meu CEP" exibido abaixo do campo de CEP.', 'hubgo' ),
                        'fields'      => array(
                            self::select( 'address_lookup_provider', esc_html__( 'Serviço de busca', 'hubgo' ), esc_html__( 'O ViaCEP é gratuito e não exige cadastro. O Google Places aceita busca por texto livre, mas consome a cota da sua chave.', 'hubgo' ), array(
                                array( 'value' => 'viacep', 'label' => esc_html__( 'ViaCEP (gratuito)', 'hubgo' ) ),
                                array( 'value' => 'google', 'label' => esc_html__( 'Google Places (requer chave de API)', 'hubgo' ) ),
                                array( 'value' => 'off', 'label' => esc_html__( 'Desativado', 'hubgo' ) ),
                            ) ),
                            self::password( 'google_places_api_key', esc_html__( 'Chave da API do Google', 'hubgo' ), esc_html__( 'Requer a "Places API (New)" ativada no projeto. A chave fica no servidor e nunca é enviada ao navegador.', 'hubgo' ), array(
                                'placeholder' => 'AIza...',
                            ) ),
                        ),
                    ),
                ),
            ),
            array(
                'id'          => 'appearance',
                'title'       => esc_html__( 'Aparência', 'hubgo' ),
                'description' => esc_html__( 'Cores e formato da tabela de fretes exibida na loja.', 'hubgo' ),
                'icon'        => 'palette',
                'layout'      => 'fields',
                'cards'       => array(
                    array(
                        'id'          => 'appearance-colors',
                        'title'       => esc_html__( 'Cores e tipografia', 'hubgo' ),
                        'description' => esc_html__( 'Deixe um campo vazio para manter o padrão do HubGo.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'primary_main_color', esc_html__( 'Cor principal', 'hubgo' ), esc_html__( 'Cor de destaque usada em links, ícones e na opção selecionada.', 'hubgo' ), array( 'default' => '#008aff' ) ),
                            self::color( 'calc_text_color', esc_html__( 'Cor do texto', 'hubgo' ), esc_html__( 'Cor dos títulos e dos valores.', 'hubgo' ), array( 'default' => '#102033' ) ),
                            self::color( 'calc_muted_color', esc_html__( 'Cor do texto secundário', 'hubgo' ), esc_html__( 'Cor das descrições e observações.', 'hubgo' ), array( 'default' => '#6b7280' ) ),
                            self::number( 'calc_font_size', esc_html__( 'Tamanho da fonte', 'hubgo' ), esc_html__( 'Tamanho base do texto da calculadora.', 'hubgo' ), array(
                                'min'         => 10,
                                'max'         => 24,
                                'step'        => 1,
                                'unit'        => 'px',
                                'placeholder' => '14',
                            ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-surface',
                        'title'       => esc_html__( 'Container', 'hubgo' ),
                        'description' => esc_html__( 'Caixa que envolve toda a calculadora.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_surface_bg', esc_html__( 'Cor de fundo', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::color( 'calc_surface_border', esc_html__( 'Cor da borda', 'hubgo' ), '', array( 'default' => '#e5e8ec' ) ),
                            self::range( 'calc_surface_radius', esc_html__( 'Arredondamento', 'hubgo' ), '', array( 'max' => 32 ) ),
                            self::range( 'calc_surface_padding', esc_html__( 'Espaçamento interno', 'hubgo' ), '', array( 'max' => 48 ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-badge',
                        'title'       => esc_html__( 'Selo de frete grátis', 'hubgo' ),
                        'description' => esc_html__( 'Etiqueta exibida no topo do card.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_badge_bg', esc_html__( 'Cor de fundo', 'hubgo' ), '', array( 'default' => '#059669' ) ),
                            self::color( 'calc_badge_text_color', esc_html__( 'Cor do texto', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-input',
                        'title'       => esc_html__( 'Campo de CEP', 'hubgo' ),
                        'description' => esc_html__( 'Entrada onde o cliente digita o CEP.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_input_bg', esc_html__( 'Cor de fundo', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::color( 'calc_input_border', esc_html__( 'Cor da borda', 'hubgo' ), '', array( 'default' => '#d5dae1' ) ),
                            self::range( 'calc_input_radius', esc_html__( 'Arredondamento', 'hubgo' ), '', array( 'max' => 32 ) ),
                            self::range( 'calc_input_height', esc_html__( 'Altura', 'hubgo' ), '', array( 'min' => 32, 'max' => 72 ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-button',
                        'title'       => esc_html__( 'Botão calcular', 'hubgo' ),
                        'description' => esc_html__( 'Deixe vazio para usar a cor principal.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_button_bg', esc_html__( 'Cor de fundo', 'hubgo' ), '', array( 'default' => '#008aff' ) ),
                            self::color( 'calc_button_hover_bg', esc_html__( 'Cor de fundo (hover)', 'hubgo' ), '', array( 'default' => '#0069c2' ) ),
                            self::color( 'calc_button_text_color', esc_html__( 'Cor do texto', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::range( 'calc_button_radius', esc_html__( 'Arredondamento', 'hubgo' ), '', array( 'max' => 32 ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-options',
                        'title'       => esc_html__( 'Opções de entrega', 'hubgo' ),
                        'description' => esc_html__( 'Lista de métodos exibida na janela de detalhes.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_option_border', esc_html__( 'Cor da borda', 'hubgo' ), '', array( 'default' => '#e5e8ec' ) ),
                            self::color( 'calc_option_selected_bg', esc_html__( 'Fundo da opção escolhida', 'hubgo' ), '', array( 'default' => '#f4f9ff' ) ),
                            self::range( 'calc_option_radius', esc_html__( 'Arredondamento', 'hubgo' ), '', array( 'max' => 32 ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-modal',
                        'title'       => esc_html__( 'Janela de detalhes', 'hubgo' ),
                        'description' => esc_html__( 'Modal aberto por "Mais detalhes e formas de entrega".', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_modal_bg', esc_html__( 'Cor de fundo', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::range( 'calc_modal_radius', esc_html__( 'Arredondamento', 'hubgo' ), '', array( 'max' => 32 ) ),
                            self::range( 'calc_modal_blur', esc_html__( 'Desfoque do fundo', 'hubgo' ), esc_html__( 'Quanto a página atrás da janela é desfocada. Zero desativa o efeito.', 'hubgo' ), array( 'max' => 24, 'default' => 8 ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-layout',
                        'title'       => esc_html__( 'Layout', 'hubgo' ),
                        'description' => esc_html__( 'Como os métodos de entrega são apresentados ao cliente.', 'hubgo' ),
                        'fields'      => array(
                            self::select( 'shipping_methods_display', esc_html__( 'Exibição dos métodos', 'hubgo' ), esc_html__( 'Formato usado para listar os métodos de entrega retornados.', 'hubgo' ), array(
                                array( 'value' => 'table', 'label' => esc_html__( 'Tabela', 'hubgo' ) ),
                                array( 'value' => 'list', 'label' => esc_html__( 'Lista', 'hubgo' ) ),
                            ) ),
                        ),
                    ),
                ),
            ),
            array(
                'id'          => 'texts',
                'title'       => esc_html__( 'Textos', 'hubgo' ),
                'description' => esc_html__( 'Todos os textos exibidos pela calculadora e pela tabela de fretes.', 'hubgo' ),
                'icon'        => 'align-left',
                'layout'      => 'fields',
                'cards'       => array(
                    array(
                        'id'          => 'texts-calculator',
                        'title'       => esc_html__( 'Calculadora', 'hubgo' ),
                        'description' => esc_html__( 'Textos do formulário de consulta de frete.', 'hubgo' ),
                        'fields'      => array(
                            self::text( 'text_calculator_title', esc_html__( 'Título do card', 'hubgo' ), esc_html__( 'Deixe vazio para ocultar o título.', 'hubgo' ) ),
                            self::text( 'text_info_before_input_shipping_calc', esc_html__( 'Texto de informação', 'hubgo' ), esc_html__( 'Texto exibido acima do campo de CEP.', 'hubgo' ) ),
                            self::text( 'text_placeholder_input_shipping_calc', esc_html__( 'Placeholder do campo', 'hubgo' ), esc_html__( 'Texto de exemplo dentro do campo de CEP.', 'hubgo' ) ),
                            self::text( 'text_button_shipping_calc', esc_html__( 'Texto do botão', 'hubgo' ), esc_html__( 'Rótulo do botão de calcular.', 'hubgo' ) ),
                            self::text( 'text_cep_finder_link', esc_html__( 'Link "Não sei meu CEP"', 'hubgo' ), esc_html__( 'Deixe vazio para ocultar a busca de endereço.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'texts-results',
                        'title'       => esc_html__( 'Resultado do frete', 'hubgo' ),
                        'description' => esc_html__( 'Cabeçalhos e observações exibidos no resultado da consulta.', 'hubgo' ),
                        'fields'      => array(
                            self::text( 'text_header_ship', esc_html__( 'Cabeçalho "Entrega"', 'hubgo' ), esc_html__( 'Título da coluna de método de entrega.', 'hubgo' ) ),
                            self::text( 'text_header_value', esc_html__( 'Cabeçalho "Valor"', 'hubgo' ), esc_html__( 'Título da coluna de valor.', 'hubgo' ) ),
                            self::text( 'text_more_options', esc_html__( 'Link de mais opções', 'hubgo' ), esc_html__( 'Abre a janela com todas as formas de entrega.', 'hubgo' ) ),
                            self::textarea( 'note_text_bottom_shipping_calc', esc_html__( 'Nota de rodapé', 'hubgo' ), esc_html__( 'Observação exibida abaixo do resultado.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'texts-free-shipping',
                        'title'       => esc_html__( 'Frete grátis', 'hubgo' ),
                        'description' => esc_html__( 'Selo exibido no topo do card. Deixe ambos vazios para ocultá-lo.', 'hubgo' ),
                        'fields'      => array(
                            self::text( 'text_free_shipping_badge', esc_html__( 'Selo antes de atingir', 'hubgo' ), esc_html__( 'Use %s onde o valor mínimo deve aparecer.', 'hubgo' ) ),
                            self::text( 'text_free_shipping_active', esc_html__( 'Selo ao atingir', 'hubgo' ), esc_html__( 'Exibido quando o produto já garante frete grátis.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'texts-preference',
                        'title'       => esc_html__( 'Método preferido', 'hubgo' ),
                        'description' => esc_html__( 'Textos da escolha que é levada ao checkout.', 'hubgo' ),
                        'fields'      => array(
                            self::text( 'text_preference_hint', esc_html__( 'Aviso na lista de opções', 'hubgo' ), esc_html__( 'Exibido abaixo das formas de entrega.', 'hubgo' ) ),
                            self::text( 'text_preference_saved', esc_html__( 'Confirmação da escolha', 'hubgo' ), esc_html__( 'Exibido depois que o cliente escolhe uma opção.', 'hubgo' ) ),
                            self::text( 'text_clear_preference', esc_html__( 'Link para remover', 'hubgo' ), esc_html__( 'Deixe vazio para não permitir remover a preferência.', 'hubgo' ) ),
                        ),
                    ),
                ),
            ),
            array(
                'id'          => 'about',
                'title'       => esc_html__( 'Sobre', 'hubgo' ),
                'description' => esc_html__( 'Manutenção, atualizações e informações do ambiente.', 'hubgo' ),
                'icon'        => 'info-circle',
                'layout'      => 'mixed',
                'cards'       => array(
                    array(
                        'id'          => 'about-maintenance',
                        'title'       => esc_html__( 'Manutenção e preferências', 'hubgo' ),
                        'description' => esc_html__( 'Comportamento operacional do plugin.', 'hubgo' ),
                        'fields'      => array(
                            self::toggle( 'enable_auto_updates', esc_html__( 'Atualizações automáticas', 'hubgo' ), esc_html__( 'Permite que o HubGo se atualize sozinho sempre que possível.', 'hubgo' ) ),
                            self::toggle( 'enable_update_notice', esc_html__( 'Avisos de atualização', 'hubgo' ), esc_html__( 'Exibe notificações quando uma nova versão está disponível.', 'hubgo' ) ),
                            self::toggle( 'enable_debug_mode', esc_html__( 'Modo de depuração', 'hubgo' ), esc_html__( 'Registra detalhes adicionais de erros e processos no log do WordPress.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'about-system',
                        'title'       => esc_html__( 'Status do sistema', 'hubgo' ),
                        'description' => esc_html__( 'Visão rápida do ambiente WordPress, PHP e WooCommerce.', 'hubgo' ),
                        'component'   => 'system-status',
                    ),
                    array(
                        'id'          => 'about-danger',
                        'title'       => esc_html__( 'Zona de perigo', 'hubgo' ),
                        'description' => esc_html__( 'Ações irreversíveis sobre a configuração do plugin.', 'hubgo' ),
                        'component'   => 'danger-zone',
                    ),
                ),
            ),
        );

        return apply_filters( 'Hubgo/Admin/Settings/Schema', $schema );
    }


    /**
     * Flat map of field key => definition (used for sanitization).
     *
     * Integration fields are merged in so the single `POST /settings` route
     * persists both the settings screen and the integrations screen. Skipping
     * them here would silently drop every integration value on save.
     *
     * @since 3.0.0
     * @version 3.0.0
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

        foreach ( Integrations_Base::get_integration_items() as $item ) {
            if ( ! empty( $item['setting_key'] ) ) {
                $definitions[ $item['setting_key'] ] = self::toggle( $item['setting_key'], $item['title'] );
            }

            foreach ( ( $item['settings'] ?? array() ) as $field ) {
                if ( ! empty( $field['key'] ) ) {
                    $definitions[ $field['key'] ] = $field;
                }
            }
        }

        return apply_filters( 'Hubgo/Admin/Settings/Field_Definitions', $definitions );
    }


    /**
     * Build the REST bootstrap payload for the settings SPA.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @return array
     */
    public static function get_bootstrap_data() {
        $data = array(
            'version'  => defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '',
            'settings' => Repository::get_settings(),
            'schema'   => self::get_schema(),
            'system'   => System_Status::get_status(),
            'license'  => License::get_summary(),
            'rest'     => array(
                'root'  => esc_url_raw( rest_url( 'hubgo/v1' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ),
        );

        return apply_filters( 'Hubgo/Admin/Settings/Bootstrap_Data', $data );
    }


    /**
     * Build the REST bootstrap payload for the integrations SPA.
     *
     * @since 3.0.0
     * @return array
     */
    public static function get_integrations_bootstrap_data() {
        $data = array(
            'version'    => defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '',
            'settings'   => Repository::get_settings(),
            'cards'      => self::get_integration_cards(),
            'categories' => self::get_integration_categories(),
            'license'    => License::get_summary(),
            'can_install' => current_user_can( 'install_plugins' ),
        );

        return apply_filters( 'Hubgo/Admin/Integrations/Bootstrap_Data', $data );
    }


    /**
     * Build the integration cards consumed by the Integrations screen.
     *
     * Every runtime-dependent flag (is the plugin active, is the license valid,
     * can this card be installed) is resolved here so the Vue side stays a pure
     * renderer.
     *
     * @since 3.0.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_cards() {
        $settings = Repository::get_settings();
        $licensed = License::is_active();
        $can_install = current_user_can( 'install_plugins' );
        $cards = array();

        foreach ( Integrations_Base::get_integration_items() as $slug => $item ) {
            $requires_plugin = ! empty( $item['is_plugin'] );
            $plugin_active = Integrations_Base::is_plugin_dependency_active( $item['plugin_active'], $requires_plugin );
            $setting_key = (string) $item['setting_key'];
            $locked = ! empty( $item['requires_license'] ) && ! $licensed;
            $installable = $requires_plugin && ! $plugin_active && ! empty( $item['install']['download_url'] );

            $card = array_merge( $item, array(
                'slug'             => $slug,
                'enabled'          => '' !== $setting_key && 'yes' === ( $settings[ $setting_key ] ?? 'no' ),
                'requires_plugin'  => $requires_plugin,
                'plugin_active'    => $plugin_active,
                'is_locked'        => $locked,
                'can_install'      => $installable && $can_install,
                // A card is configurable when it has fields OR modal blocks:
                // an integration may have nothing to tune and still need to
                // explain something (Joinotify's dual-toggle notice does).
                'has_settings'     => ! empty( $item['settings'] ) || ! empty( $item['modal']['blocks'] ),
                'disabled_message' => self::get_integration_disabled_message( $item, $requires_plugin, $plugin_active, $locked ),
            ) );

            $cards[] = $card;
        }

        return apply_filters( 'Hubgo/Admin/Integrations/Cards', $cards );
    }


    /**
     * Normalized, priority-ordered category catalog for the frontend.
     *
     * @since 3.0.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_categories() {
        $normalized = array();

        foreach ( (array) Integrations_Base::get_integration_categories() as $category ) {
            if ( ! is_array( $category ) || empty( $category['id'] ) ) {
                continue;
            }

            $id = sanitize_key( (string) $category['id'] );

            if ( '' === $id ) {
                continue;
            }

            $normalized[ $id ] = array(
                'id'       => $id,
                'label'    => isset( $category['label'] ) ? (string) $category['label'] : ucfirst( str_replace( '_', ' ', $id ) ),
                'icon'     => isset( $category['icon'] ) ? (string) $category['icon'] : '',
                'priority' => isset( $category['priority'] ) ? (int) $category['priority'] : 0,
            );
        }

        $normalized = array_values( $normalized );

        usort( $normalized, static function( $a, $b ) {
            return $a['priority'] <=> $b['priority'];
        } );

        return $normalized;
    }


    /**
     * Reason a card's toggle is unavailable, or an empty string when it is not.
     *
     * @since 3.0.0
     * @param array $item Normalized card.
     * @param bool $requires_plugin Whether the card declares a plugin dependency.
     * @param bool $plugin_active Whether that dependency is satisfied.
     * @param bool $locked Whether the card needs a license the site does not hold.
     * @return string
     */
    private static function get_integration_disabled_message( $item, $requires_plugin, $plugin_active, $locked ) {
        if ( ! empty( $item['coming_soon'] ) ) {
            return esc_html__( 'Esta integração estará disponível em breve.', 'hubgo' );
        }

        if ( $requires_plugin && ! $plugin_active ) {
            return esc_html__( 'O plugin correspondente precisa estar instalado e ativo para usar esta integração.', 'hubgo' );
        }

        if ( $locked ) {
            return esc_html__( 'Ative sua licença do HubGo para usar esta integração.', 'hubgo' );
        }

        return '';
    }


    /**
     * Field helpers.
     */
    private static function toggle( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'toggle', 'label' => $label, 'description' => $description ), $extra );
    }

    private static function text( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'text', 'label' => $label, 'description' => $description ), $extra );
    }

    private static function textarea( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'textarea', 'label' => $label, 'description' => $description, 'rows' => 3 ), $extra );
    }

    private static function color( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'color', 'label' => $label, 'description' => $description ), $extra );
    }

    private static function select( $key, $label, $description, $options, $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'select', 'label' => $label, 'description' => $description, 'options' => $options ), $extra );
    }


    /**
     * Numeric field. `min`/`max` are enforced again on save by the repository.
     *
     * @since 3.1.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra attributes (min, max, step, unit, placeholder).
     * @return array<string,mixed>
     */
    private static function number( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'number', 'label' => $label, 'description' => $description ), $extra );
    }


    /**
     * Slider field, for style values that read better as a continuum.
     *
     * @since 3.1.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra attributes (min, max, step, unit).
     * @return array<string,mixed>
     */
    private static function range( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array(
            'key'         => $key,
            'type'        => 'range',
            'label'       => $label,
            'description' => $description,
            'min'         => 0,
            'max'         => 40,
            'step'        => 1,
            'unit'        => 'px',
        ), $extra );
    }


    /**
     * Masked field for credentials.
     *
     * @since 3.1.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra attributes.
     * @return array<string,mixed>
     */
    private static function password( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'password', 'label' => $label, 'description' => $description ), $extra );
    }
}
