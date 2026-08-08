<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Melhor Envio integration (MeuMouse.com edition).
 *
 * The companion plugin has not shipped yet, so this class exists to make the
 * card discoverable and to hold the wiring that will light up the moment the
 * plugin lands: nothing here needs a code change to go live.
 *
 * Three constants can be overridden from wp-config.php (or the matching
 * filters) so the card can be pointed at the plugin as soon as it is published,
 * including during a beta:
 *
 *     define( 'HUBGO_MELHOR_ENVIO_PLUGIN_FILE', 'melhor-envio-meumouse/melhor-envio-meumouse.php' );
 *     define( 'HUBGO_MELHOR_ENVIO_PACKAGE_URL', 'https://cloud.meumouse.com/.../melhor-envio.zip' );
 *
 * While `get_package_url()` is empty the card renders as "em breve" and the
 * install button stays hidden; the toggle unlocks once the plugin is active.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Melhor_Envio extends Integrations_Base {

    /**
     * Card slug.
     *
     * @since 3.0.0
     * @var string
     */
    const CARD_SLUG = 'melhor_envio';

    /**
     * Option key toggling the integration.
     *
     * @since 3.0.0
     * @var string
     */
    const SETTING_KEY = 'enable_melhor_envio_integration';

    /**
     * Default plugin basename of the companion plugin.
     *
     * @since 3.0.0
     * @var string
     */
    const PLUGIN_FILE = 'melhor-envio-meumouse/melhor-envio-meumouse.php';


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        $this->register_integration_card( 20 );

        if ( ! self::is_available() || ! self::is_enabled() ) {
            return;
        }

        /**
         * Runtime bridge with the Melhor Envio plugin.
         *
         * Fired once both plugins are present and the integration is on, so the
         * companion plugin can hook tracking synchronisation and label events
         * without HubGo having to know its internals ahead of time.
         *
         * @since 3.0.0
         * @param Melhor_Envio $integration Integration instance.
         */
        do_action( 'Hubgo/Integrations/Melhor_Envio/Booted', $this );
    }


    /**
     * Register the card on the Integrations screen.
     *
     * @since 3.0.0
     * @param array $integrations Current catalog.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $package_url = self::get_package_url();

        $integrations[ self::CARD_SLUG ] = array(
            'title'            => esc_html__( 'Melhor Envio', 'hubgo' ),
            'description'      => esc_html__( 'Cotação, etiquetas e rastreio do Melhor Envio integrados ao HubGo, com o plugin oficial da MeuMouse.com.', 'hubgo' ),
            'icon'             => $this->get_icon_svg(),
            'category'         => 'shipping',
            'setting_key'      => self::SETTING_KEY,
            'is_plugin'        => true,
            'plugin_active'    => array( self::get_plugin_file() ),
            'requires_license' => true,
            // No package URL yet means the plugin has not been published: the
            // card advertises the integration instead of offering an install.
            'coming_soon'      => '' === $package_url,
            'doc_url'          => 'https://ajuda.meumouse.com/docs/hubgo/overview',
            'install'          => array(
                'plugin_slug'  => self::get_plugin_file(),
                'download_url' => $package_url,
                'label'        => esc_html__( 'Instalar Melhor Envio', 'hubgo' ),
            ),
            'settings'         => array(
                self::field_toggle(
                    'melhor_envio_sync_tracking',
                    esc_html__( 'Sincronizar rastreio', 'hubgo' ),
                    esc_html__( 'Importa automaticamente o código de rastreio gerado no Melhor Envio para o pedido.', 'hubgo' )
                ),
                self::field_toggle(
                    'melhor_envio_mark_as_shipped',
                    esc_html__( 'Marcar como enviado', 'hubgo' ),
                    esc_html__( 'Altera o pedido para o status "Pedido enviado" assim que a etiqueta é postada.', 'hubgo' )
                ),
            ),
            'modal'            => array(
                'title'       => esc_html__( 'Melhor Envio', 'hubgo' ),
                'description' => esc_html__( 'Comportamento da sincronização entre o Melhor Envio e o rastreio de pedidos do HubGo.', 'hubgo' ),
                'size'        => 'medium',
            ),
        );

        return $integrations;
    }


    /**
     * Whether the companion plugin is installed and active.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_available() {
        return self::is_plugin_dependency_active( array( self::get_plugin_file() ), true );
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
     * Plugin basename of the companion plugin.
     *
     * @since 3.0.0
     * @return string
     */
    public static function get_plugin_file() {
        $plugin_file = defined( 'HUBGO_MELHOR_ENVIO_PLUGIN_FILE' )
            ? (string) HUBGO_MELHOR_ENVIO_PLUGIN_FILE
            : self::PLUGIN_FILE;

        return (string) apply_filters( 'Hubgo/Integrations/Melhor_Envio/Plugin_File', $plugin_file );
    }


    /**
     * Download URL of the installable package, or an empty string when the
     * plugin has not been published yet.
     *
     * @since 3.0.0
     * @return string
     */
    public static function get_package_url() {
        $package_url = defined( 'HUBGO_MELHOR_ENVIO_PACKAGE_URL' )
            ? (string) HUBGO_MELHOR_ENVIO_PACKAGE_URL
            : '';

        return esc_url_raw( (string) apply_filters( 'Hubgo/Integrations/Melhor_Envio/Package_Url', $package_url ) );
    }


    /**
     * Melhor Envio brand mark.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_icon_svg() {
        return self::get_brand_svg( 'melhor-envio-logo.svg', __( 'Melhor Envio', 'hubgo' ) );
    }
}
