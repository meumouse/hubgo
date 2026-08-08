<?php

namespace MeuMouse\Hubgo\Integrations\Widgets;

use MeuMouse\Hubgo\Core\Assets;
use MeuMouse\Hubgo\Views\Calculator_Styles;
use MeuMouse\Hubgo\Views\Shipping_Calculator;

use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor_Plugin;
use Elementor\Widget_Base;

defined('ABSPATH') || exit;

/**
 * Elementor widget rendering the HubGo shipping calculator.
 *
 * Content controls override the values {@see Shipping_Calculator::get_config()}
 * derives from the settings; style controls write CSS custom properties scoped
 * to `{{WRAPPER}}`.
 *
 * Every style selector is built by {@see self::token_selector()} from
 * {@see Calculator_Styles::get_token_map()}, so a control and its counterpart on
 * the Aparência tab are guaranteed to drive the same property. Adding a style
 * control means adding one entry to that map and one control here — never
 * hardcoding a property name in two places.
 *
 * @since 3.1.0
 * @package MeuMouse\Hubgo\Integrations\Widgets
 * @author MeuMouse.com
 */
class Shipping_Calculator_Widget extends Widget_Base {

    /**
     * Element the custom properties are scoped to, relative to the wrapper.
     *
     * @since 3.1.0
     * @var string
     */
    const ROOT_SELECTOR = '{{WRAPPER}} .hubgo-shipping-calculator';


    /**
     * Widget name, used by the Elementor frontend hook the Vue app listens to.
     *
     * @since 3.1.0
     * @return string
     */
    public function get_name() {
        return 'hubgo_shipping_calculator';
    }


    /**
     * Widget title.
     *
     * @since 3.1.0
     * @return string
     */
    public function get_title() {
        return esc_html__( 'Calculadora de frete', 'hubgo' );
    }


    /**
     * Widget icon.
     *
     * @since 3.1.0
     * @return string
     */
    public function get_icon() {
        return 'eicon-shipping';
    }


    /**
     * Widget categories.
     *
     * @since 3.1.0
     * @return array<int,string>
     */
    public function get_categories() {
        return array( 'hubgo' );
    }


    /**
     * Editor search keywords.
     *
     * @since 3.1.0
     * @return array<int,string>
     */
    public function get_keywords() {
        return array( 'frete', 'shipping', 'cep', 'entrega', 'hubgo', 'correios' );
    }


    /**
     * Scripts the widget needs on the frontend.
     *
     * @since 3.1.0
     * @return array<int,string>
     */
    public function get_script_depends() {
        return array( Assets::FRONT_SCRIPT_HANDLE );
    }


    /**
     * Register content and style controls.
     *
     * @since 3.1.0
     * @return void
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_text_controls();
        $this->register_surface_controls();
        $this->register_typography_controls();
        $this->register_badge_controls();
        $this->register_input_controls();
        $this->register_button_controls();
        $this->register_option_controls();
        $this->register_modal_controls();
    }


    /**
     * Content: what the calculator quotes and which parts are visible.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_content_controls() {
        $this->start_controls_section( 'content_section', array(
            'label' => esc_html__( 'Calculadora', 'hubgo' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'product_source', array(
            'label'   => esc_html__( 'Produto', 'hubgo' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'current',
            'options' => array(
                'current' => esc_html__( 'Produto da página atual', 'hubgo' ),
                'manual'  => esc_html__( 'Produto específico', 'hubgo' ),
            ),
        ) );

        $this->add_control( 'product_id', array(
            'label'       => esc_html__( 'ID do produto', 'hubgo' ),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 1,
            'condition'   => array( 'product_source' => 'manual' ),
            'description' => esc_html__( 'Use quando a calculadora estiver fora de uma página de produto.', 'hubgo' ),
        ) );

        $this->add_control( 'quantity', array(
            'label'   => esc_html__( 'Quantidade base', 'hubgo' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'default' => 1,
        ) );

        $this->add_control( 'display', array(
            'label'   => esc_html__( 'Formato das opções', 'hubgo' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '',
            'options' => array(
                ''      => esc_html__( 'Herdar das configurações', 'hubgo' ),
                'list'  => esc_html__( 'Lista', 'hubgo' ),
                'table' => esc_html__( 'Tabela', 'hubgo' ),
            ),
        ) );

        $this->add_control( 'features_heading', array(
            'label'     => esc_html__( 'Elementos exibidos', 'hubgo' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $features = array(
            'show_badge'      => esc_html__( 'Selo de frete grátis', 'hubgo' ),
            'show_finder'     => esc_html__( 'Link "Não sei meu CEP"', 'hubgo' ),
            'show_options'    => esc_html__( 'Janela de mais opções', 'hubgo' ),
            'show_preference' => esc_html__( 'Escolha do método preferido', 'hubgo' ),
            'auto_calculate'  => esc_html__( 'Calcular automaticamente', 'hubgo' ),
        );

        foreach ( $features as $key => $label ) {
            $this->add_control( $key, array(
                'label'        => $label,
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Sim', 'hubgo' ),
                'label_off'    => esc_html__( 'Não', 'hubgo' ),
                'return_value' => 'yes',
                'default'      => 'auto_calculate' === $key ? '' : 'yes',
            ) );
        }

        $this->end_controls_section();
    }


    /**
     * Content: per-instance text overrides.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_text_controls() {
        $this->start_controls_section( 'texts_section', array(
            'label' => esc_html__( 'Textos', 'hubgo' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ) );

        $this->add_control( 'texts_notice', array(
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => '<div class="elementor-control-field-description">'
                . esc_html__( 'Deixe vazio para usar o texto definido em HubGo → Configurações → Textos.', 'hubgo' )
                . '</div>',
        ) );

        $texts = array(
            'text_title'        => esc_html__( 'Título do card', 'hubgo' ),
            'text_info'         => esc_html__( 'Texto de informação', 'hubgo' ),
            'text_placeholder'  => esc_html__( 'Placeholder do CEP', 'hubgo' ),
            'text_button'       => esc_html__( 'Texto do botão', 'hubgo' ),
            'text_more_options' => esc_html__( 'Link de mais opções', 'hubgo' ),
            'text_finder_link'  => esc_html__( 'Link "Não sei meu CEP"', 'hubgo' ),
        );

        foreach ( $texts as $key => $label ) {
            $this->add_control( $key, array(
                'label'       => $label,
                'type'        => Controls_Manager::TEXT,
                'label_block' => true,
                'default'     => '',
            ) );
        }

        $this->add_control( 'text_note', array(
            'label'       => esc_html__( 'Nota de rodapé', 'hubgo' ),
            'type'        => Controls_Manager::TEXTAREA,
            'rows'        => 3,
            'default'     => '',
        ) );

        $this->end_controls_section();
    }


    /**
     * Style: the card container.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_surface_controls() {
        $this->start_controls_section( 'style_surface', array(
            'label' => esc_html__( 'Container', 'hubgo' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_color_control( 'style_surface_bg', esc_html__( 'Cor de fundo', 'hubgo' ), 'calc_surface_bg' );
        $this->add_color_control( 'style_surface_border', esc_html__( 'Cor da borda', 'hubgo' ), 'calc_surface_border' );
        $this->add_slider_control( 'style_surface_radius', esc_html__( 'Arredondamento', 'hubgo' ), 'calc_surface_radius', 0, 40 );
        $this->add_slider_control( 'style_surface_padding', esc_html__( 'Espaçamento interno', 'hubgo' ), 'calc_surface_padding', 0, 64 );

        $this->end_controls_section();
    }


    /**
     * Style: colours and type scale.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_typography_controls() {
        $this->start_controls_section( 'style_typography', array(
            'label' => esc_html__( 'Cores e tipografia', 'hubgo' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'style_font_family', array(
            'label'     => esc_html__( 'Fonte', 'hubgo' ),
            'type'      => Controls_Manager::FONT,
            'default'   => '',
            'selectors' => array(
                self::ROOT_SELECTOR => '--hubgo-calc-font-family: "{{VALUE}}", sans-serif;',
            ),
        ) );

        $this->add_color_control( 'style_accent', esc_html__( 'Cor principal', 'hubgo' ), 'primary_main_color' );
        $this->add_color_control( 'style_text', esc_html__( 'Cor do texto', 'hubgo' ), 'calc_text_color' );
        $this->add_color_control( 'style_muted', esc_html__( 'Cor do texto secundário', 'hubgo' ), 'calc_muted_color' );
        $this->add_slider_control( 'style_font_size', esc_html__( 'Tamanho da fonte', 'hubgo' ), 'calc_font_size', 10, 28 );

        $this->end_controls_section();
    }


    /**
     * Style: free shipping badge.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_badge_controls() {
        $this->start_controls_section( 'style_badge', array(
            'label'     => esc_html__( 'Selo de frete grátis', 'hubgo' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_badge' => 'yes' ),
        ) );

        $this->add_color_control( 'style_badge_bg', esc_html__( 'Cor de fundo', 'hubgo' ), 'calc_badge_bg' );
        $this->add_color_control( 'style_badge_text', esc_html__( 'Cor do texto', 'hubgo' ), 'calc_badge_text_color' );

        $this->end_controls_section();
    }


    /**
     * Style: postcode field.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_input_controls() {
        $this->start_controls_section( 'style_input', array(
            'label' => esc_html__( 'Campo de CEP', 'hubgo' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_color_control( 'style_input_bg', esc_html__( 'Cor de fundo', 'hubgo' ), 'calc_input_bg' );
        $this->add_color_control( 'style_input_border', esc_html__( 'Cor da borda', 'hubgo' ), 'calc_input_border' );
        $this->add_slider_control( 'style_input_radius', esc_html__( 'Arredondamento', 'hubgo' ), 'calc_input_radius', 0, 40 );
        $this->add_slider_control( 'style_input_height', esc_html__( 'Altura', 'hubgo' ), 'calc_input_height', 32, 80 );

        $this->end_controls_section();
    }


    /**
     * Style: submit button, with a hover tab.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_button_controls() {
        $this->start_controls_section( 'style_button', array(
            'label' => esc_html__( 'Botão calcular', 'hubgo' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_color_control( 'style_button_bg', esc_html__( 'Cor de fundo', 'hubgo' ), 'calc_button_bg' );
        $this->add_color_control( 'style_button_hover_bg', esc_html__( 'Cor de fundo (hover)', 'hubgo' ), 'calc_button_hover_bg' );
        $this->add_color_control( 'style_button_text', esc_html__( 'Cor do texto', 'hubgo' ), 'calc_button_text_color' );
        $this->add_slider_control( 'style_button_radius', esc_html__( 'Arredondamento', 'hubgo' ), 'calc_button_radius', 0, 40 );

        $this->end_controls_section();
    }


    /**
     * Style: delivery option rows.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_option_controls() {
        $this->start_controls_section( 'style_options', array(
            'label'     => esc_html__( 'Opções de entrega', 'hubgo' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_options' => 'yes' ),
        ) );

        $this->add_color_control( 'style_option_border', esc_html__( 'Cor da borda', 'hubgo' ), 'calc_option_border' );
        $this->add_color_control( 'style_option_selected_bg', esc_html__( 'Fundo da opção escolhida', 'hubgo' ), 'calc_option_selected_bg' );
        $this->add_slider_control( 'style_option_radius', esc_html__( 'Arredondamento', 'hubgo' ), 'calc_option_radius', 0, 40 );

        $this->end_controls_section();
    }


    /**
     * Style: modal shell.
     *
     * @since 3.1.0
     * @return void
     */
    private function register_modal_controls() {
        $this->start_controls_section( 'style_modal', array(
            'label'     => esc_html__( 'Janela de detalhes', 'hubgo' ),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => array( 'show_options' => 'yes' ),
        ) );

        $this->add_control( 'style_modal_notice', array(
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => '<div class="elementor-control-field-description">'
                . esc_html__( 'A janela é aberta fora do widget, mas herda estes valores automaticamente.', 'hubgo' )
                . '</div>',
        ) );

        $this->add_color_control( 'style_modal_bg', esc_html__( 'Cor de fundo', 'hubgo' ), 'calc_modal_bg' );
        $this->add_slider_control( 'style_modal_radius', esc_html__( 'Arredondamento', 'hubgo' ), 'calc_modal_radius', 0, 40 );

        // Elementor's colour control emits rgba(), so the overlay is editable
        // here even though the settings panel — whose picker speaks hex — has
        // no field for it.
        $this->add_color_control( 'style_modal_overlay', esc_html__( 'Cor do fundo escurecido', 'hubgo' ), 'calc_modal_overlay' );
        $this->add_slider_control( 'style_modal_blur', esc_html__( 'Desfoque do fundo', 'hubgo' ), 'calc_modal_blur', 0, 24 );

        $this->end_controls_section();
    }


    /**
     * Register a colour control bound to a calculator token.
     *
     * @since 3.1.0
     * @param string $control_id Elementor control id.
     * @param string $label Control label.
     * @param string $setting_key Setting key whose token this control drives.
     * @return void
     */
    private function add_color_control( $control_id, $label, $setting_key ) {
        $selector = $this->token_selector( $setting_key, '{{VALUE}}' );

        if ( empty( $selector ) ) {
            return;
        }

        $this->add_control( $control_id, array(
            'label'     => $label,
            'type'      => Controls_Manager::COLOR,
            'selectors' => $selector,
        ) );
    }


    /**
     * Register a slider control bound to a calculator token.
     *
     * @since 3.1.0
     * @param string $control_id Elementor control id.
     * @param string $label Control label.
     * @param string $setting_key Setting key whose token this control drives.
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     * @return void
     */
    private function add_slider_control( $control_id, $label, $setting_key, $min, $max ) {
        $selector = $this->token_selector( $setting_key, '{{SIZE}}{{UNIT}}' );

        if ( empty( $selector ) ) {
            return;
        }

        $this->add_responsive_control( $control_id, array(
            'label'      => $label,
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array(
                'px' => array( 'min' => $min, 'max' => $max, 'step' => 1 ),
            ),
            'selectors'  => $selector,
        ) );
    }


    /**
     * Build the `selectors` array for a setting's CSS custom property.
     *
     * @since 3.1.0
     * @param string $setting_key Setting key.
     * @param string $value_placeholder Elementor value placeholder.
     * @return array<string,string> Empty when the key drives no property.
     */
    private function token_selector( $setting_key, $value_placeholder ) {
        $token = Calculator_Styles::get_token( $setting_key );

        if ( '' === $token ) {
            return array();
        }

        return array(
            self::ROOT_SELECTOR => $token . ': ' . $value_placeholder . ';',
        );
    }


    /**
     * Render the mount node with this instance's overrides.
     *
     * @since 3.1.0
     * @return void
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        if ( ! Shipping_Calculator::is_enabled() ) {
            $this->render_editor_hint( esc_html__( 'A calculadora de frete está desativada nas configurações do HubGo.', 'hubgo' ) );

            return;
        }

        $overrides = array(
            'quantity' => max( 1, absint( $settings['quantity'] ?? 1 ) ),
            'features' => array(
                'badge'      => 'yes' === ( $settings['show_badge'] ?? 'yes' ),
                'finder'     => 'yes' === ( $settings['show_finder'] ?? 'yes' ),
                'options'    => 'yes' === ( $settings['show_options'] ?? 'yes' ),
                'auto'       => 'yes' === ( $settings['auto_calculate'] ?? '' ),
            ),
            'texts'    => $this->collect_text_overrides( $settings ),
        );

        // The preference toggle can only turn the feature OFF for this widget:
        // switching it on while the site-wide setting is off would write a
        // cookie nothing reads back at the checkout.
        if ( 'yes' !== ( $settings['show_preference'] ?? 'yes' ) ) {
            $overrides['features']['preference'] = false;
        }

        if ( 'manual' === ( $settings['product_source'] ?? 'current' ) && ! empty( $settings['product_id'] ) ) {
            $overrides['product'] = absint( $settings['product_id'] );
        }

        if ( ! empty( $settings['display'] ) ) {
            $overrides['display'] = sanitize_key( $settings['display'] );
        }

        $config = Shipping_Calculator::get_config( $overrides );

        // In the editor there is no product context, so the widget would render
        // an input that can never return a quote. Say so instead.
        if ( empty( $config['product'] ) && $this->is_edit_mode() ) {
            $this->render_editor_hint( esc_html__( 'Nenhum produto encontrado. Escolha "Produto específico" para pré-visualizar aqui.', 'hubgo' ) );

            return;
        }

        Shipping_Calculator::render_mount_node( $overrides );
    }


    /**
     * Collect the non-empty text overrides from the widget settings.
     *
     * An empty control means "inherit", so blank values must not reach the
     * config — they would blank out the setting-level text instead.
     *
     * @since 3.1.0
     * @param array $settings Widget settings.
     * @return array<string,string>
     */
    private function collect_text_overrides( $settings ) {
        $map = array(
            'text_title'        => 'title',
            'text_info'         => 'info',
            'text_placeholder'  => 'placeholder',
            'text_button'       => 'button',
            'text_more_options' => 'moreOptions',
            'text_finder_link'  => 'finderLink',
            'text_note'         => 'note',
        );

        $texts = array();

        foreach ( $map as $control => $config_key ) {
            $value = isset( $settings[ $control ] ) ? trim( (string) $settings[ $control ] ) : '';

            if ( '' !== $value ) {
                $texts[ $config_key ] = sanitize_text_field( $value );
            }
        }

        return $texts;
    }


    /**
     * Whether Elementor is currently rendering inside the editor.
     *
     * @since 3.1.0
     * @return bool
     */
    private function is_edit_mode() {
        return class_exists( Elementor_Plugin::class )
            && isset( Elementor_Plugin::$instance->editor )
            && Elementor_Plugin::$instance->editor->is_edit_mode();
    }


    /**
     * Print an editor-only explanation in place of the widget.
     *
     * @since 3.1.0
     * @param string $message Message to show.
     * @return void
     */
    private function render_editor_hint( $message ) {
        if ( ! $this->is_edit_mode() ) {
            return;
        }

        printf(
            '<div class="elementor-alert elementor-alert-info">%s</div>',
            esc_html( $message )
        );
    }
}
