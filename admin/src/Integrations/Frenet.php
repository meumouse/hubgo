<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Frenet integration, backed by the official "Frenet Shipping Gateway" plugin.
 *
 * Frenet is distributed on wordpress.org, so the card ships a package URL and
 * the Integrations screen can install and activate it in one click through
 * {@see \MeuMouse\Hubgo\Core\Plugin_Installer}.
 *
 * The integration itself is free: it only teaches HubGo how to read the
 * tracking data Frenet writes on the order, so the tracking screen, the
 * customer account view and the shipped e-mail all show the same information.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Frenet extends Integrations_Base {

    /**
     * Card slug.
     *
     * @since 3.0.0
     * @var string
     */
    const CARD_SLUG = 'frenet';

    /**
     * Option key toggling the integration.
     *
     * @since 3.0.0
     * @var string
     */
    const SETTING_KEY = 'enable_frenet_integration';

    /**
     * Plugin basename of the official plugin.
     *
     * @since 3.0.0
     * @var string
     */
    const PLUGIN_FILE = 'woo-shipping-gateway/woo-shipping-gateway.php';

    /**
     * Package URL served by wordpress.org.
     *
     * @since 3.0.0
     * @var string
     */
    const PACKAGE_URL = 'https://downloads.wordpress.org/plugin/woo-shipping-gateway.zip';

    /**
     * Tracking URL template. %1$s receives the url-encoded tracking code, the
     * same placeholder contract every entry in Providers_Registry uses.
     *
     * @since 3.0.0
     * @var string
     */
    const DEFAULT_TRACKING_URL = 'https://painel.frenet.com.br/tracking/%1$s';


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        $this->register_integration_card( 30 );

        if ( ! self::is_available() || ! self::is_enabled() ) {
            return;
        }

        add_filter( 'Hubgo/Tracking/Get_Providers', array( $this, 'register_provider' ) );
    }


    /**
     * Register the card on the Integrations screen.
     *
     * @since 3.0.0
     * @param array $integrations Current catalog.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations[ self::CARD_SLUG ] = array(
            'title'         => esc_html__( 'Frenet', 'hubgo' ),
            'description'   => esc_html__( 'Use as cotações da Frenet no cálculo de frete e leve o código de rastreio para o painel de pedidos do HubGo.', 'hubgo' ),
            'icon'          => $this->get_icon_svg(),
            'category'      => 'shipping',
            'setting_key'   => self::SETTING_KEY,
            'is_plugin'     => true,
            'plugin_active' => array( self::PLUGIN_FILE ),
            'doc_url'       => 'https://wordpress.org/plugins/woo-shipping-gateway/',
            'install'       => array(
                'plugin_slug'  => self::PLUGIN_FILE,
                'download_url' => self::PACKAGE_URL,
                'label'        => esc_html__( 'Instalar Frenet', 'hubgo' ),
            ),
            'settings'      => array(
                self::field_toggle(
                    'frenet_sync_tracking',
                    esc_html__( 'Importar rastreio', 'hubgo' ),
                    esc_html__( 'Lê o código de rastreio gravado pela Frenet no pedido e o exibe no rastreio do HubGo.', 'hubgo' )
                ),
                self::field_text(
                    'frenet_tracking_url',
                    esc_html__( 'URL de rastreio', 'hubgo' ),
                    esc_html__( 'Endereço usado no link de rastreio. Use %1$s onde o código deve entrar.', 'hubgo' ),
                    array( 'placeholder' => self::DEFAULT_TRACKING_URL )
                ),
            ),
            'modal'         => array(
                'title'       => esc_html__( 'Frenet', 'hubgo' ),
                'description' => esc_html__( 'Como o HubGo lê os dados de envio gerados pela Frenet.', 'hubgo' ),
                'size'        => 'medium',
            ),
        );

        return $integrations;
    }


    /**
     * Add Frenet to the Brazilian tracking providers list.
     *
     * The group keys are internal identifiers translated for display by
     * Providers_Registry::get_country_label(), so 'Brazil' is used verbatim.
     *
     * @since 3.0.0
     * @param array $providers Current provider groups.
     * @return array
     */
    public function register_provider( $providers ) {
        if ( ! is_array( $providers ) ) {
            $providers = array();
        }

        $url = (string) Settings::get_setting( 'frenet_tracking_url', '' );
        $providers['Brazil']['Frenet'] = '' !== trim( $url ) ? $url : self::DEFAULT_TRACKING_URL;

        return $providers;
    }


    /**
     * Whether the official plugin is installed and active.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_available() {
        return self::is_plugin_dependency_active( array( self::PLUGIN_FILE ), true );
    }


    /**
     * Whether the integration toggle is on.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === Settings::get_setting( self::SETTING_KEY, 'no' );
    }


    /**
     * Frenet brand mark.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_icon_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="Frenet">'
            . '<rect x="2" y="2" width="60" height="60" rx="14" fill="#2f3192"/>'
            . '<path d="M24.5 44V20h16.8v4.6H29.7v5.1h10.1v4.6H29.7V44z" fill="#ffffff"/>'
            . '<path d="M17 33.6h6.2v3.1H17zm0 6.2h6.2v3.1H17z" fill="#00b3e3"/>'
            . '</svg>';
    }
}
