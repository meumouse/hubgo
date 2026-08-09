<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Menu;
use MeuMouse\Hubgo\Admin\Settings;

use MeuMouse\MDS\SDK\SDK;
use MeuMouse\MDS\SDK\Integration;
use MeuMouse\MDS\SDK\Admin\Notices;
use MeuMouse\MDS\SDK\License\LicenseStatus;

use Exception;
use InvalidArgumentException;
use WP_Error;

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

        // Taken before the SDK boots so its own admin notice can be told apart
        // from the ones other MDS products already hooked.
        $notices_before = self::admin_notice_ids();

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
                // A null parent stops the SDK from registering its own submenu
                // and rendering its own form: HubGo ships a Vue license screen
                // (Admin\Menu::LICENSE_PAGE_SLUG) driven by the hubgo/v1 routes,
                // which call this same SDK license manager underneath. The
                // admin_post handlers the SDK registers stay wired, so a legacy
                // bookmark to them keeps working.
                'settings_parent' => null,
            ) );
        } catch ( InvalidArgumentException $e ) {
            self::log( 'MDS registration failed: ' . $e->getMessage() );

            return;
        }

        self::remove_sdk_license_notice( $notices_before );

        // Link to the license screen from the plugins list. Safe to wire now
        // that get_license_url() resolves to the HubGo license subpage instead
        // of the SDK's own (no longer registered) screen.
        add_filter( 'plugin_action_links_' . self::get_plugin_file(), array( __CLASS__, 'plugin_action_links' ) );

        // Opt this plugin into WordPress background updates when enabled.
        add_filter( 'auto_update_plugin', array( __CLASS__, 'enable_auto_update' ), 10, 2 );
    }


    /**
     * Map of callback ids currently hooked onto `admin_notices`, per priority.
     *
     * @since 3.0.0
     * @return array<int,array<string,bool>>
     */
    private static function admin_notice_ids() {
        global $wp_filter;

        if ( empty( $wp_filter['admin_notices'] ) ) {
            return array();
        }

        $ids = array();

        foreach ( $wp_filter['admin_notices']->callbacks as $priority => $callbacks ) {
            $ids[ $priority ] = array_fill_keys( array_keys( $callbacks ), true );
        }

        return $ids;
    }


    /**
     * Drop the license nag the SDK renders on the dashboard, plugins and
     * updates screens ("Enter your license key to enable updates and
     * support.", and its expired/invalid variants).
     *
     * HubGo already surfaces the license state on its own screen — the same
     * reason `settings_parent` is null above — so the extra notice only
     * duplicates it. The SDK keeps the Notices instance to itself, so the
     * callback is found on the hook: any Notices added while SDK::register()
     * ran is ours, which leaves the notices of other MDS products alone.
     *
     * @since 3.0.0
     * @param array<int,array<string,bool>> $before Callback ids seen before the SDK booted.
     * @return void
     */
    private static function remove_sdk_license_notice( $before ) {
        global $wp_filter;

        if ( empty( $wp_filter['admin_notices'] ) ) {
            return;
        }

        foreach ( $wp_filter['admin_notices']->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $id => $callback ) {
                if ( isset( $before[ $priority ][ $id ] ) ) {
                    continue;
                }

                $function = isset( $callback['function'] ) ? $callback['function'] : null;

                if ( is_array( $function ) && isset( $function[0] ) && $function[0] instanceof Notices ) {
                    remove_action( 'admin_notices', $function, $priority );
                }
            }
        }
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
     * @version 3.0.0
     * @return string
     */
    public static function get_license_url() {
        return Menu::get_page_url( Menu::LICENSE_PAGE_SLUG );
    }


    /**
     * Activate a license key against the MDS API.
     *
     * A refused key is NOT an error: the SDK persists an "invalid" status and
     * returns it, so the caller reads `is_valid()`/`message()` to tell the user
     * why. Only a transport failure produces a WP_Error.
     *
     * @since 3.0.0
     * @param string $key License key.
     * @return LicenseStatus|\WP_Error
     */
    public static function activate( $key ) {
        $integration = self::get_integration();

        if ( ! $integration ) {
            return self::unavailable_error();
        }

        try {
            return $integration->license()->activate( (string) $key );
        } catch ( Exception $e ) {
            return new WP_Error( 'hubgo_license_transport', __( 'Could not reach the license server. Please try again.', 'hubgo' ) );
        }
    }


    /**
     * Release this site's activation and forget the key locally.
     *
     * Best-effort by design: the SDK clears the local state even when the
     * server call fails, so the admin can always enter another key.
     *
     * @since 3.0.0
     * @return true|\WP_Error
     */
    public static function deactivate_license() {
        $integration = self::get_integration();

        if ( ! $integration ) {
            return self::unavailable_error();
        }

        $integration->license()->deactivate();

        return true;
    }


    /**
     * Re-validate the stored key against the API.
     *
     * @since 3.0.0
     * @return LicenseStatus|\WP_Error
     */
    public static function sync() {
        $integration = self::get_integration();

        if ( ! $integration ) {
            return self::unavailable_error();
        }

        try {
            return $integration->license()->validate();
        } catch ( Exception $e ) {
            return new WP_Error( 'hubgo_license_transport', __( 'Could not reach the license server. Please try again.', 'hubgo' ) );
        }
    }


    /**
     * The stored license key, if any.
     *
     * @since 3.0.0
     * @return string
     */
    public static function get_key() {
        $integration = self::get_integration();

        return $integration ? (string) $integration->license()->get_key() : '';
    }


    /**
     * Compact license summary shipped with every admin bootstrap payload.
     *
     * Never performs a network call: it reads the status the daily heartbeat
     * persisted, so opening any screen stays instant even when MDS is down.
     *
     * @since 3.0.0
     * @return array<string,mixed>
     */
    public static function get_summary() {
        $status = self::get_status();

        return array(
            'configured' => self::is_configured(),
            'is_active'  => self::is_active(),
            'status'     => $status ? $status->status() : 'unknown',
            'message'    => self::get_message(),
            'has_key'    => '' !== self::get_key(),
            'plan_name'  => self::get_plan_name(),
            'url'        => self::get_license_url(),
        );
    }


    /**
     * Full payload for the license screen.
     *
     * @since 3.0.0
     * @return array<string,mixed>
     */
    public static function get_payload() {
        $status = self::get_status();
        $bundle = self::get_bundle();

        return apply_filters( 'Hubgo/Core/License/Payload', array_merge( self::get_summary(), array(
            'version'           => defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '',
            'masked_key'        => self::mask_key( self::get_key() ),
            'domain'            => $status ? $status->domain() : '',
            'expires_at'        => self::get_expires_at(),
            'checked_at'        => $status ? $status->checked_at() : 0,
            'is_expired'        => $status ? $status->is_expired() : false,
            'is_signed'         => $status ? $status->is_signed() : false,
            'is_bundle'         => self::is_bundle(),
            'bundle'            => $bundle,
            'renew_url'         => self::get_renew_url(),
            'purchase_url'      => 'https://meumouse.com/plugins/hubgo/',
            'docs_url'          => 'https://ajuda.meumouse.com/docs/hubgo/overview',
            'max_activations'   => (int) self::get_data( 'max_activations', 0 ),
            'used_activations'  => (int) self::get_data( 'used_activations', 0 ),
            'support_expires_at' => (string) self::get_data( 'support_expires_at', '' ),
        ) ) );
    }


    /**
     * Mask a license key for display, keeping only its edges readable.
     *
     * @since 3.0.0
     * @param string $key Raw license key.
     * @return string
     */
    public static function mask_key( $key ) {
        $key = (string) $key;
        $length = strlen( $key );

        if ( $length <= 8 ) {
            return $key;
        }

        return substr( $key, 0, 4 ) . str_repeat( '•', 8 ) . substr( $key, -4 );
    }


    /**
     * Error returned when the SDK is missing or misconfigured.
     *
     * @since 3.0.0
     * @return \WP_Error
     */
    private static function unavailable_error() {
        return new WP_Error( 'hubgo_license_unavailable', __( 'The licensing service is not available.', 'hubgo' ) );
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
     * Add a license link to the plugin row.
     *
     * @since 3.0.0
     * @param array $links Existing plugin links.
     * @return array
     */
    public static function plugin_action_links( $links ) {
        $license_link = '<a href="' . esc_url( self::get_license_url() ) . '">' .
            esc_html__( 'License', 'hubgo' ) .
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
