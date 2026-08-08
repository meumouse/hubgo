<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Menu;
use MeuMouse\Hubgo\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Class Assets
 *
 * Enqueues all CSS/JS for the plugin. The admin settings page loads the Vite/Vue
 * SPA (resolved from the manifest); the storefront calculator and order tracking
 * metabox talk to the hubgo/v1 REST API (no admin-ajax).
 *
 * @since 2.0.0
 * @version 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Assets {

    const FRONT_SCRIPT_HANDLE = 'hubgo-front';
    const FRONT_STYLE_HANDLE  = 'hubgo-front-style';

    // Kept as published constants for third parties. The authoritative
    // page -> entry/handle mapping is get_admin_pages(); these mirror the
    // settings row of that map.
    const SETTINGS_HANDLE     = 'hubgo-settings-app';
    const SETTINGS_ENTRY      = 'src/entries/settings.js';
    const SETTINGS_PAGE_ID    = 'hubgo-settings';


    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 3 );
        add_filter( 'load_script_translation_file', array( $this, 'resolve_script_translation_file' ), 10, 3 );
    }


    /**
     * Admin page slug => Vite entry, script handle and DOM mount node.
     *
     * One entry per admin screen: adding a page means adding a row here, a
     * Rollup input in `app/vite.config.js` and a `src/entries/*.js` file.
     *
     * @since 3.0.0
     * @return array<string,array<string,string>>
     */
    public static function get_admin_pages() {
        return apply_filters( 'Hubgo/Core/Assets/Admin_Pages', array(
            Menu::PAGE_SLUG => array(
                'entry'    => 'src/entries/settings.js',
                'handle'   => 'hubgo-settings-app',
                'page'     => 'settings',
                'endpoint' => 'settings',
            ),
            Menu::INTEGRATIONS_PAGE_SLUG => array(
                'entry'    => 'src/entries/integrations.js',
                'handle'   => 'hubgo-integrations-app',
                'page'     => 'integrations',
                'endpoint' => 'integrations',
            ),
            Menu::LICENSE_PAGE_SLUG => array(
                'entry'    => 'src/entries/license.js',
                'handle'   => 'hubgo-license-app',
                'page'     => 'license',
                'endpoint' => 'license',
            ),
        ) );
    }


    /**
     * REST root for the hubgo/v1 namespace.
     *
     * @since 3.0.0
     * @return string
     */
    private function rest_root() {
        return esc_url_raw( rest_url( 'hubgo/v1' ) );
    }


    /**
     * Enqueue frontend (storefront) assets.
     *
     * @since 2.0.0
     * @version 3.0.0
     * @return void
     */
    public function enqueue_frontend_assets() {
        $version = $this->get_asset_version();

        wp_enqueue_style(
            self::FRONT_STYLE_HANDLE,
            $this->get_asset_url( 'front/css/hubgo-front.css' ),
            array(),
            $version
        );

        wp_enqueue_script(
            self::FRONT_SCRIPT_HANDLE,
            $this->get_asset_url( 'front/js/hubgo-front.js' ),
            array(),
            $version,
            true
        );

        $front_params = array(
            'rest_url'           => $this->rest_root(),
            'nonce'              => wp_create_nonce( 'wp_rest' ),
            'auto_shipping'      => Settings::get_setting( 'enable_auto_shipping_calculator', 'no' ),
            'current_product_id' => $this->get_current_product_id(),
            'i18n'               => array(
                'header_shipping' => Settings::get_setting( 'text_header_ship' ),
                'header_value'    => Settings::get_setting( 'text_header_value' ),
                'bottom_note'     => Settings::get_setting( 'note_text_bottom_shipping_calc' ),
                'no_shipping'     => __( 'Nenhuma forma de entrega disponível.', 'hubgo' ),
                'error'           => __( 'Erro ao calcular o frete. Tente novamente.', 'hubgo' ),
            ),
        );

        wp_localize_script(
            self::FRONT_SCRIPT_HANDLE,
            'hubgo_front_params',
            apply_filters( 'Hubgo/Core/Assets/FrontParams', $front_params )
        );
    }


    /**
     * Enqueue admin assets.
     *
     * @since 2.0.0
     * @version 3.0.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        $page = $this->get_current_admin_page();

        if ( '' !== $page ) {
            $this->enqueue_admin_app( $page );
        }

        if ( $this->is_order_screen() ) {
            $this->enqueue_order_tracking_assets();
        }
    }


    /**
     * Enqueue one of the Vue admin SPAs from the Vite manifest.
     *
     * @since 3.0.0
     * @param string $page Admin page slug present in {@see self::get_admin_pages()}.
     * @return void
     */
    private function enqueue_admin_app( $page ) {
        $pages = self::get_admin_pages();

        if ( empty( $pages[ $page ] ) ) {
            return;
        }

        $definition = $pages[ $page ];
        $assets = Scripts::get_entry_assets( $definition['entry'] );

        if ( empty( $assets ) || empty( $assets['script'] ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__( 'HubGo: build de interface não encontrado. Execute "npm run build" no plugin.', 'hubgo' );
                echo '</p></div>';
            } );

            return;
        }

        $handle = $definition['handle'];
        $version = ! empty( $assets['version'] ) ? $assets['version'] : $this->get_asset_version();

        if ( ! empty( $assets['styles'] ) && is_array( $assets['styles'] ) ) {
            foreach ( $assets['styles'] as $index => $style_url ) {
                wp_enqueue_style( $handle . '-' . $index, $style_url, array(), $version );
            }
        }

        wp_enqueue_script( $handle, $assets['script'], array( 'wp-i18n' ), $version, true );
        wp_set_script_translations( $handle, 'hubgo', trailingslashit( HUBGO_PATH ) . 'languages' );

        wp_localize_script( $handle, 'hubgoBootstrapConfig', array(
            'restUrl'   => $this->rest_root(),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'page'      => $definition['page'],
            'endpoint'  => $definition['endpoint'],
            'version'   => defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '',
            // Brand assets are resolved at runtime so the logo also loads on
            // sites installed in a subdirectory or served from a custom domain.
            'assetsUrl' => defined( 'HUBGO_ASSETS' ) ? esc_url_raw( HUBGO_ASSETS ) : '',
            'pages'     => array(
                'settings'     => Menu::get_page_url( Menu::PAGE_SLUG ),
                'integrations' => Menu::get_page_url( Menu::INTEGRATIONS_PAGE_SLUG ),
                'license'      => Menu::get_page_url( Menu::LICENSE_PAGE_SLUG ),
            ),
        ) );
    }


    /**
     * Resolve the HubGo admin page currently being rendered.
     *
     * @since 3.0.0
     * @return string Page slug, or an empty string outside HubGo screens.
     */
    private function get_current_admin_page() {
        if ( ! is_admin() || empty( $_GET['page'] ) ) {
            return '';
        }

        $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

        return isset( self::get_admin_pages()[ $page ] ) ? $page : '';
    }


    /**
     * Inject type="module" on the Vue entry scripts so browsers load them as ESM.
     *
     * WordPress hands this filter the whole printed block for the handle — the
     * main `<script>` tag PLUS the inline `wp.i18n.setLocaleData` block emitted by
     * `wp_set_script_translations()`. Match only this handle's own tag (by its
     * `id`) so those sibling scripts survive untouched.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @param string $tag Script tag HTML (may include translation/inline scripts).
     * @param string $handle Script handle.
     * @param string $src Script source URL.
     * @return string
     */
    public function add_module_type_attribute( $tag, $handle, $src ) {
        $tag = is_scalar( $tag ) ? (string) $tag : '';
        $handles = wp_list_pluck( self::get_admin_pages(), 'handle' );

        if ( ! in_array( $handle, $handles, true ) ) {
            return $tag;
        }

        $pattern = '#<script\b(?![^>]*\stype=)([^>]*\sid=(["\'])'
            . preg_quote( $handle, '#' ) . '-js\2[^>]*)>#';

        $result = preg_replace( $pattern, '<script type="module"$1>', $tag, 1 );

        return null === $result ? $tag : $result;
    }


    /**
     * Point WordPress at the handle-named JSON translation files.
     *
     * Core resolves script translations as "{domain}-{locale}-{md5(src)}.json",
     * but the languages pipeline emits "{domain}-{locale}-{handle}.json". Vite
     * hashes the entry paths, so a stable md5-based name cannot be generated
     * ahead of the build; without this remap wp.i18n keeps the original strings
     * and every Vue screen renders untranslated.
     *
     * @since 3.0.0
     * @param string|false $file Translation file path resolved by core.
     * @param string $handle Script handle being translated.
     * @param string $domain Text domain.
     * @return string|false
     */
    public function resolve_script_translation_file( $file, $handle, $domain ) {
        if ( 'hubgo' !== $domain ) {
            return $file;
        }

        $languages = trailingslashit( HUBGO_PATH ) . 'languages/';
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        $candidate = $languages . "hubgo-{$locale}-{$handle}.json";

        if ( is_readable( $candidate ) ) {
            return $candidate;
        }

        // Each per-handle JSON the pipeline emits is a full copy of the same
        // translation table, so any HubGo screen can be served the base file.
        // A new admin page therefore gets its translations without touching the
        // languages pipeline.
        $base = $languages . "hubgo-{$locale}-hubgo.json";

        return is_readable( $base ) ? $base : $file;
    }


    /**
     * Enqueue order tracking metabox assets.
     *
     * @since 2.1.0
     * @version 3.0.0
     * @return void
     */
    private function enqueue_order_tracking_assets() {
        $version = $this->get_asset_version();

        wp_enqueue_style(
            'hubgo-order-tracking-admin',
            $this->get_asset_url( 'admin/css/tracking-metabox.css' ),
            array(),
            $version
        );

        wp_enqueue_script(
            'hubgo-order-tracking-provider',
            $this->get_asset_url( 'admin/js/tracking-metabox.js' ),
            array(),
            $version,
            true
        );

        wp_localize_script( 'hubgo-order-tracking-provider', 'hubgoOrderTrackingParams', array(
            'rest_url'  => $this->rest_root(),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'order_id'  => $this->get_order_id_from_request(),
            'providers' => $this->get_tracking_providers_for_script(),
            'i18n'      => array(
                'confirm_delete' => __( 'Tem certeza que deseja remover este rastreio?', 'hubgo' ),
                'delete_error'   => __( 'Não foi possível remover o rastreio. Tente novamente.', 'hubgo' ),
                'save_error'     => __( 'Não foi possível salvar o rastreio. Tente novamente.', 'hubgo' ),
            ),
        ) );
    }


    /**
     * Get current product ID on single product pages.
     *
     * @since 2.1.1
     * @return int
     */
    private function get_current_product_id() {
        $product_id = 0;

        if ( function_exists( 'is_product' ) && is_product() ) {
            $product_id = absint( get_queried_object_id() );
        }

        if ( ! $product_id && isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ) {
            $product_id = absint( $GLOBALS['product']->get_id() );
        }

        return $product_id;
    }


    /**
     * Get providers map for tracking preview script.
     *
     * @since 2.1.0
     * @return array
     */
    private function get_tracking_providers_for_script() {
        $provider_array = array();

        foreach ( Providers_Registry::get_providers() as $providers ) {
            foreach ( $providers as $provider => $format ) {
                if ( ! empty( $format ) ) {
                    $provider_array[ $provider ] = rawurlencode( $format );
                }
            }
        }

        return $provider_array;
    }


    /**
     * Get order ID from request (HPOS + classic).
     *
     * @since 2.1.0
     * @return int
     */
    private function get_order_id_from_request() {
        if ( isset( $_GET['id'] ) ) {
            return absint( $_GET['id'] );
        }

        if ( isset( $_GET['post'] ) ) {
            return absint( $_GET['post'] );
        }

        return 0;
    }


    /**
     * Check if current screen is an order screen.
     *
     * @since 2.1.0
     * @return bool
     */
    private function is_order_screen() {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();

        if ( ! $screen || empty( $screen->id ) ) {
            return false;
        }

        $screens = array( 'shop_order' );

        if ( function_exists( 'wc_get_page_screen_id' ) ) {
            $screens[] = wc_get_page_screen_id( 'shop-order' );
        }

        return in_array( $screen->id, array_filter( array_unique( $screens ) ), true );
    }


    /**
     * Get asset URL with fallback.
     *
     * @since 2.0.0
     * @param string $path Asset path relative to the assets directory.
     * @return string
     */
    private function get_asset_url( $path ) {
        $assets_base = defined( 'HUBGO_ASSETS' ) ? HUBGO_ASSETS : '';

        if ( empty( $assets_base ) ) {
            return '';
        }

        return trailingslashit( $assets_base ) . ltrim( $path, '/' );
    }


    /**
     * Get asset version with fallback.
     *
     * @since 2.0.0
     * @return string
     */
    private function get_asset_version() {
        return defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '1.0.0';
    }
}
