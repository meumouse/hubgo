<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Menu;
use MeuMouse\Hubgo\Admin\Settings;

use MeuMouse\MDS\SDK\SDK;
use MeuMouse\MDS\SDK\Integration;
use MeuMouse\MDS\SDK\License\LicenseStatus;

use InvalidArgumentException;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Licensing and update integration with the Modular Distribution Service (MDS).
 *
 * Registers HubGo with the MDS PHP SDK, which takes over update checks
 * (signed, license-gated), the license activation screen and the daily license
 * heartbeat. It replaces the legacy MeuMouse\Hubgo\API\Updater class, which
 * polled a static JSON file on packages.meumouse.com.
 *
 * Requires meumouse/mds-php-sdk ^1.1 (installed by Composer into admin/vendor).
 * From 1.1 the SDK sends `product_slug` on activate/deactivate, so a single
 * bundle key can license several products, and keeps every extra field the
 * validation endpoint returns — read them via get_data(), get_bundle(),
 * get_plan_name() and get_renew_url().
 *
 * Credentials are compiled in as class constants so the shipped plugin works
 * out of the box, and every one of them can be overridden by a constant in
 * wp-config.php (handy for staging against a different MDS instance):
 *
 *     define( 'HUBGO_MDS_API_URL', 'https://staging.meumouse.com' );
 *     define( 'HUBGO_MDS_API_KEY', 'mds_test_xxx' );
 *     define( 'HUBGO_MDS_PUBLIC_KEY', 'BASE64_ED25519_PUBLIC_KEY' );
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
final class License {

    /**
     * Product slug, must match the product slug registered on MDS.
     *
     * @since 3.0.0
     * @var string
     */
    const PRODUCT_SLUG = 'hubgo';

    /**
     * MDS API host.
     *
     * @since 3.0.0
     * @var string
     */
    const API_BASE_URL = 'https://cloud.meumouse.com';

    /**
     * Public, low-privilege product API key issued by MDS.
     *
     * Scopes: updates:check, licenses:activate, licenses:deactivate. It is meant
     * to be readable inside the distributed plugin; it grants nothing beyond
     * those three operations.
     *
     * @since 3.0.0
     * @var string
     */
    const API_KEY = 'mds_fc0509fbf15485d2c829db343c5c2e0fffdbf7c5b1d6a78e88485940713e3914';

    /**
     * Base64 ed25519 public key used to verify every signed MDS response.
     *
     * Must match the MDS_SIGNING_PUBLIC_KEY configured on the API. Responses
     * that are unsigned or fail verification are discarded by the SDK.
     *
     * @since 3.0.0
     * @var string
     */
    const PUBLIC_KEY = 'fLpjcbSx1ccEDAYjf0BheQDhn9W+iBYaJAxT+eQ0Mac=';

    /**
     * The registered SDK integration, or null when unavailable.
     *
     * @since 3.0.0
     * @var Integration|null
     */
    private static $integration = null;


    /**
     * Hook the product registration onto the SDK bootstrap.
     *
     * Must run before `plugins_loaded` (the SDK loader elects the newest
     * embedded copy at priority -100 and fires `mds_sdk_loaded` there), so this
     * is called from Plugin::init() while plugin files are still being loaded.
     *
     * @since 3.0.0
     * @return void
     */
    public static function boot() {
        add_action( 'mds_sdk_loaded', array( __CLASS__, 'register_product' ) );
    }


    /**
     * Register HubGo with the MDS SDK.
     *
     * @since 3.0.0
     * @return void
     */
    public static function register_product() {
        if ( ! class_exists( SDK::class ) || null !== self::$integration ) {
            return;
        }

        if ( ! self::is_configured() ) {
            self::log( 'MDS credentials are missing: updates and licensing are disabled.' );

            return;
        }

        try {
            self::$integration = SDK::register( array(
                'product_slug'    => self::PRODUCT_SLUG,
                'type'            => 'plugin',
                'file'            => self::get_plugin_file(),
                'current_version' => defined('HUBGO_VERSION') ? HUBGO_VERSION : '',
                'api_base_url'    => self::get_api_base_url(),
                'api_key'         => self::get_api_key(),
                'public_key'      => self::get_public_key(),
                'item_name'       => 'HubGo',
                'text_domain'     => 'hubgo',
                'settings_parent' => self::get_settings_parent(),
            ) );
        } catch ( InvalidArgumentException $e ) {
            self::log( 'MDS registration failed: ' . $e->getMessage() );

            return;
        }

        // The SDK submenu label is only available in its own text domain; relabel
        // it so the entry reads in the plugin language.
        add_action( 'admin_menu', array( __CLASS__, 'localize_submenu' ), 11 );

        // Link to the license screen from the plugins list.
        add_filter( 'plugin_action_links_' . self::get_plugin_file(), array( __CLASS__, 'plugin_action_links' ) );

        // Opt this plugin into WordPress background updates when enabled.
        add_filter( 'auto_update_plugin', array( __CLASS__, 'enable_auto_update' ), 10, 2 );
    }


    /**
     * Tear down the license heartbeat on plugin deactivation.
     *
     * @since 3.0.0
     * @return void
     */
    public static function deactivate() {
        $integration = self::get_integration();

        if ( $integration ) {
            $integration->shutdown();
        }
    }


    /**
     * Get the registered SDK integration.
     *
     * @since 3.0.0
     * @return Integration|null
     */
    public static function get_integration() {
        if ( null === self::$integration && class_exists( SDK::class ) ) {
            self::$integration = SDK::get( self::PRODUCT_SLUG );
        }

        return self::$integration;
    }


    /**
     * Whether the site holds a valid, active HubGo license.
     *
     * Use this to gate premium behaviour:
     *
     *     if ( License::is_active() ) { ... }
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_active() {
        $integration = self::get_integration();

        return $integration ? $integration->is_licensed() : false;
    }


    /**
     * Last persisted license status. Never performs a network call.
     *
     * @since 3.0.1
     * @return LicenseStatus|null
     */
    public static function get_status() {
        $integration = self::get_integration();

        return $integration ? $integration->license()->status() : null;
    }


    /**
     * Read one of the fields the validation endpoint returns beyond the ones
     * LicenseStatus models: plan, plan_name, renew_url, support_expires_at,
     * max_activations, used_activations, reason, activation_status, product
     * and bundle.
     *
     * Requires MDS PHP SDK 1.1+; the extras persist with the status, so they
     * stay readable during a grace-period outage.
     *
     * @since 3.0.1
     * @param string $key Field name as returned by the API.
     * @param mixed $default Value when the field is absent.
     * @return mixed
     */
    public static function get_data( $key, $default = null ) {
        $status = self::get_status();

        return $status ? $status->get( $key, $default ) : $default;
    }


    /**
     * Every server-supplied field beyond the modelled ones.
     *
     * @since 3.0.1
     * @return array
     */
    public static function get_extra() {
        $status = self::get_status();

        return $status ? $status->extra() : array();
    }


    /**
     * The bundle that granted this license, when the key is a bundle key.
     *
     * Shape: array( 'id', 'name', 'slug', 'products' => array( array( 'id',
     * 'name', 'slug' ) ) ). A bundle seat is shared by every product it covers,
     * so deactivating HubGo does not release the sibling products.
     *
     * @since 3.0.1
     * @return array|null
     */
    public static function get_bundle() {
        $bundle = self::get_data('bundle');

        return is_array( $bundle ) ? $bundle : null;
    }


    /**
     * Whether the license comes from a bundle (e.g. "Clube M").
     *
     * @since 3.0.1
     * @return bool
     */
    public static function is_bundle() {
        return null !== self::get_bundle();
    }


    /**
     * Human-readable plan name, falling back to the plan slug.
     *
     * @since 3.0.1
     * @return string
     */
    public static function get_plan_name() {
        $name = self::get_data('plan_name');

        if ( ! is_string( $name ) || '' === $name ) {
            $name = self::get_data( 'plan', '' );
        }

        return is_string( $name ) ? $name : '';
    }


    /**
     * License expiry as an ISO-8601 string, or null for a lifetime license.
     *
     * @since 3.0.1
     * @return string|null
     */
    public static function get_expires_at() {
        $status = self::get_status();

        return $status ? $status->expires_at() : null;
    }


    /**
     * Where to send a customer whose license lapsed.
     *
     * @since 3.0.1
     * @return string
     */
    public static function get_renew_url() {
        $url = self::get_data( 'renew_url', '' );

        return is_string( $url ) ? $url : '';
    }


    /**
     * Last message returned by the server (including the refusal reason).
     *
     * @since 3.0.1
     * @return string
     */
    public static function get_message() {
        $status = self::get_status();

        return $status ? $status->message() : '';
    }


    /**
     * URL of the license screen.
     *
     * @since 3.0.0
     * @return string
     */
    public static function get_license_url() {
        $integration = self::get_integration();

        return $integration ? $integration->settings()->settings_url() : admin_url('plugins.php');
    }


    /**
     * Render the license panel inside another screen.
     *
     * @since 3.0.0
     * @return void
     */
    public static function render_panel() {
        $integration = self::get_integration();

        if ( $integration ) {
            $integration->settings()->render();
        }
    }


    /**
     * Render the available versions / rollback list inside another screen.
     *
     * @since 3.0.0
     * @return void
     */
    public static function render_rollback() {
        $integration = self::get_integration();

        if ( $integration ) {
            $integration->rollback_page()->render();
        }
    }


    /**
     * Translate the SDK submenu entry into the plugin language.
     *
     * @since 3.0.0
     * @return void
     */
    public static function localize_submenu() {
        global $submenu;

        $integration = self::get_integration();

        if ( ! $integration ) {
            return;
        }

        $parent = self::get_settings_parent();
        $slug = $integration->settings()->page_slug();

        if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
            return;
        }

        foreach ( $submenu[ $parent ] as $index => $item ) {
            if ( isset( $item[2] ) && $slug === $item[2] ) {
                $submenu[ $parent ][ $index ][0] = esc_html__( 'HubGo - Licença', 'hubgo' );
                $submenu[ $parent ][ $index ][3] = esc_html__( 'HubGo - Licença', 'hubgo' );

                break;
            }
        }
    }


    /**
     * Add a license link to the plugin row.
     *
     * @since 3.0.0
     * @param array $links Existing plugin links.
     * @return array
     */
    public static function plugin_action_links( $links ) {
        $license_link = '<a href="' . esc_url( self::get_license_url() ) . '">' .
            esc_html__( 'Licença', 'hubgo' ) .
        '</a>';

        array_unshift( $links, $license_link );

        return $links;
    }


    /**
     * Enable WordPress background updates for HubGo when the setting is on.
     *
     * @since 3.0.0
     * @param bool $update Whether to enable auto-update.
     * @param object $item Plugin update object.
     * @return bool
     */
    public static function enable_auto_update( $update, $item ) {
        if ( ! isset( $item->plugin ) || $item->plugin !== self::get_plugin_file() ) {
            return $update;
        }

        return 'yes' === Settings::get_setting('enable_auto_updates');
    }


    /**
     * Whether both MDS credentials are present.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_configured() {
        return '' !== self::get_api_key() && '' !== self::get_public_key();
    }


    /**
     * Plugin basename ("hubgo/hubgo.php") as MDS knows it.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_plugin_file() {
        if ( defined('HUBGO_BASENAME') ) {
            return HUBGO_BASENAME;
        }

        return defined('HUBGO_FILE') ? plugin_basename( HUBGO_FILE ) : '';
    }


    /**
     * Parent menu slug the license screen is attached to.
     *
     * Mirrors MeuMouse\Hubgo\Admin\Menu: WooCommerce when present, Settings
     * otherwise. Resolved on `mds_sdk_loaded` (every plugin file is loaded by
     * then), so the WooCommerce class check is reliable.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_settings_parent() {
        return class_exists('WooCommerce') ? Menu::PARENT_MENU_SLUG : 'options-general.php';
    }


    /**
     * MDS API base URL.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_api_base_url() {
        return defined('HUBGO_MDS_API_URL') ? (string) HUBGO_MDS_API_URL : self::API_BASE_URL;
    }


    /**
     * Product API key.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_api_key() {
        return defined('HUBGO_MDS_API_KEY') ? (string) HUBGO_MDS_API_KEY : self::API_KEY;
    }


    /**
     * Response-signing public key.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_public_key() {
        return defined('HUBGO_MDS_PUBLIC_KEY') ? (string) HUBGO_MDS_PUBLIC_KEY : self::PUBLIC_KEY;
    }


    /**
     * Log an integration problem when debugging is on.
     *
     * @since 3.0.0
     * @param string $message Message to log.
     * @return void
     */
    private static function log( $message ) {
        if ( defined('HUBGO_DEBUG_MODE') && HUBGO_DEBUG_MODE ) {
            error_log( 'HubGo: ' . $message );
        }
    }
}
