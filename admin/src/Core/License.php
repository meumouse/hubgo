<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Admin\Menu;
use MeuMouse\Hubgo\Admin\Settings;

use MeuMouse\MDS\SDK\SDK;
use MeuMouse\MDS\SDK\Integration;
use MeuMouse\MDS\SDK\Config\Features;
use MeuMouse\MDS\SDK\License\LicenseStatus;

use Exception;
use InvalidArgumentException;
use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Licensing and update integration with the Modular Distribution Service (MDS).
 *
 * Registers HubGo with the MDS PHP SDK, which takes over the signed update
 * check and — where the site activates a key — the license screen and the
 * daily heartbeat. It replaces the legacy MeuMouse\Hubgo\API\Updater class,
 * which polled a static JSON file on packages.meumouse.com.
 *
 * The two are separable, and HubGo currently ships with only the first: see
 * ENABLED. Updates do not depend on activation, so the product is registered
 * either way and the switch decides which SDK preset it registers under.
 *
 * Requires meumouse/mds-php-sdk ^1.3 (installed by Composer into admin/vendor):
 *
 * - 1.1 sends `product_slug` on activate/deactivate, so a single bundle key can
 *   license several products, and keeps every extra field the validation
 *   endpoint returns — read them via get_data(), get_bundle(), get_plan_name()
 *   and get_renew_url().
 * - 1.2 turns every module into a named feature. HubGo switches `notices` and
 *   `admin_menu` off below, because it renders the license state on its own
 *   screen (see register_product()).
 * - 1.3 splits "may this site be told about new versions" from "may it install
 *   them" and lets the server waive either one for a specific license. Anything
 *   about updates must therefore ask allows_updates() / allows_downloads(), not
 *   is_active(): a customer waived past the gate has a lapsed license and is
 *   still entitled to the update, and gating on validity would hide it from
 *   them before MDS ever got the chance to honour its own waiver.
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
     * Master switch for license activation.
     *
     * It governs the key, not the product: while this is false HubGo is still
     * registered with MDS and still checks for updates — in the SDK's
     * `updates_only` mode, which sends the check without a license key and
     * lets MDS answer on the product's own gates. What goes away is everything
     * that asks for a key: the License admin screen and its Vue bundle, the
     * hubgo/v1 license routes, the SDK's license heartbeat, rollback and the
     * license notices. is_active() answers true, so nothing gates itself
     * behind a key either.
     *
     * Nothing is removed — flip this to true, or define the constant below in
     * wp-config.php, and the whole activation flow comes back untouched:
     *
     *     define( 'HUBGO_LICENSE_ENABLED', true );
     *
     * MDS has to agree with the choice: with no key on the wire,
     * `/v2/update-check` only answers for a product whose update gate is open
     * on the server.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @var bool
     */
    const ENABLED = false;


    /**
     * Whether license enforcement is switched on.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_enabled() {
        if ( defined('HUBGO_LICENSE_ENABLED') ) {
            return (bool) HUBGO_LICENSE_ENABLED;
        }

        return self::ENABLED;
    }


    /**
     * Hook the product registration onto the SDK bootstrap.
     *
     * Must run before `plugins_loaded` (the SDK loader elects the newest
     * embedded copy at priority -100 and fires `mds_sdk_loaded` there), so this
     * is called from Plugin::init() while plugin files are still being loaded.
     *
     * Registration is unconditional: updates are served whether or not the site
     * activates a key, and it is register_product() that decides how much of
     * the SDK comes with them.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return void
     */
    public static function boot() {
        add_action( 'mds_sdk_loaded', array( __CLASS__, 'register_product' ) );
    }


    /**
     * Register HubGo with the MDS SDK.
     *
     * Runs in both worlds. With activation on, the whole stack is registered
     * minus the SDK's own screen and notices, which HubGo replaces. With it
     * off, the SDK's `updates_only` preset is used: no key is stored or sent,
     * no heartbeat is scheduled, no license interface exists — and the signed
     * update check still runs, which is the point of registering at all.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return void
     */
    public static function register_product() {
        if ( ! class_exists( SDK::class ) || null !== self::$integration ) {
            return;
        }

        if ( ! self::is_configured() ) {
            self::log( 'MDS credentials are missing: update checks are disabled.' );

            return;
        }

        $config = array(
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
        );

        if ( self::is_enabled() ) {
            $config['mode'] = Features::MODE_FULL;
            $config['features'] = array(
                // The SDK would nag on the dashboard, plugins and updates
                // screens about a license HubGo already reports on its own
                // screen — the same reason `settings_parent` is null. Off
                // by flag since SDK 1.2; before that it took unhooking the
                // callback from `admin_notices` after the fact.
                Features::NOTICES    => false,
                // Redundant while `settings_parent` is null, and stated so
                // that setting a parent one day does not silently bring the
                // SDK's own submenu back beside HubGo's.
                Features::ADMIN_MENU => false,
            );
        } else {
            // No key is stored, so none is sent: the SDK omits `license_key`
            // from the check rather than sending it empty, which is how MDS
            // tells "product served without a licence" from "key missing".
            // The response is still verified against the ed25519 public key —
            // no preset and no filter can switch that off.
            $config['mode'] = Features::MODE_UPDATES_ONLY;
        }

        try {
            self::$integration = SDK::register( $config );
        } catch ( InvalidArgumentException $e ) {
            self::log( 'MDS registration failed: ' . $e->getMessage() );

            return;
        }

        // Only while there is a screen to link to. Safe to wire now that
        // get_license_url() resolves to the HubGo license subpage instead of
        // the SDK's own (no longer registered) screen.
        if ( self::is_enabled() ) {
            add_filter( 'plugin_action_links_' . self::get_plugin_file(), array( __CLASS__, 'plugin_action_links' ) );
        }

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
     * Do NOT use it to gate anything about updates — see allows_updates() and
     * allows_downloads(), which answer that question and can differ from this
     * one.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_active() {
        // Every premium feature is free while licensing is switched off.
        if ( ! self::is_enabled() ) {
            return true;
        }

        $integration = self::get_integration();

        return $integration ? $integration->is_licensed() : false;
    }


    /**
     * Whether MDS still tells this site about new versions.
     *
     * Usually the same answer as is_active(), and where the two differ this is
     * the one the update interface must follow: MDS can waive the update gate
     * for a single license — how a customer who bought before HubGo required a
     * key keeps updating — and that waiver only exists on the server. Falls
     * back to the license's own validity when the server has not answered yet
     * (no heartbeat, or an API older than the gates).
     *
     * Requires MDS PHP SDK 1.3+.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function allows_updates() {
        if ( ! self::is_enabled() ) {
            return true;
        }

        $integration = self::get_integration();

        return $integration ? $integration->allows_updates() : false;
    }


    /**
     * Whether MDS still hands this site the package itself.
     *
     * Separate from allows_updates() because a product can announce a release
     * to every site and give the ZIP only to licensed ones: the update shows up
     * on the plugins list with no "Update now", and rollback is refused for the
     * same reason — installing a version is a download.
     *
     * Requires MDS PHP SDK 1.3+.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function allows_downloads() {
        if ( ! self::is_enabled() ) {
            return true;
        }

        $integration = self::get_integration();

        return $integration ? $integration->allows_downloads() : false;
    }


    /**
     * Last persisted license status. Never performs a network call.
     *
     * @since 3.0.0
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
     * @since 3.0.0
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
     * @since 3.0.0
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
     * @since 3.0.0
     * @return array|null
     */
    public static function get_bundle() {
        $bundle = self::get_data('bundle');

        return is_array( $bundle ) ? $bundle : null;
    }


    /**
     * Whether the license comes from a bundle (e.g. "Clube M").
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_bundle() {
        return null !== self::get_bundle();
    }


    /**
     * Human-readable plan name, falling back to the plan slug.
     *
     * @since 3.0.0
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
     * @since 3.0.0
     * @return string|null
     */
    public static function get_expires_at() {
        $status = self::get_status();

        return $status ? $status->expires_at() : null;
    }


    /**
     * Where to send a customer whose license lapsed.
     *
     * @since 3.0.0
     * @return string
     */
    public static function get_renew_url() {
        $url = self::get_data( 'renew_url', '' );

        return is_string( $url ) ? $url : '';
    }


    /**
     * Last message returned by the server (including the refusal reason).
     *
     * @since 3.0.0
     * @return string
     */
    public static function get_message() {
        $status = self::get_status();

        return $status ? $status->message() : '';
    }


    /**
     * URL of the license screen, or an empty string when there is none.
     *
     * The subpage is only registered while activation is on, so with it off
     * this must not hand out a link to a screen WordPress would answer with
     * "Sorry, you are not allowed to access this page".
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return string
     */
    public static function get_license_url() {
        return self::is_enabled() ? Menu::get_page_url( Menu::LICENSE_PAGE_SLUG ) : '';
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
        // The integration exists even with activation switched off (it is what
        // checks for updates), so the switch has to be asked here rather than
        // inferred from its absence.
        if ( ! self::is_enabled() ) {
            return self::unavailable_error();
        }

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
        // The integration exists even with activation switched off (it is what
        // checks for updates), so the switch has to be asked here rather than
        // inferred from its absence.
        if ( ! self::is_enabled() ) {
            return self::unavailable_error();
        }

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
        // The integration exists even with activation switched off (it is what
        // checks for updates), so the switch has to be asked here rather than
        // inferred from its absence.
        if ( ! self::is_enabled() ) {
            return self::unavailable_error();
        }

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
            'enabled'    => self::is_enabled(),
            'configured' => self::is_configured(),
            'is_active'  => self::is_active(),
            'status'     => $status ? $status->status() : ( self::is_enabled() ? 'unknown' : 'disabled' ),
            'message'    => self::get_message(),
            'has_key'    => '' !== self::get_key(),
            'plan_name'  => self::get_plan_name(),
            'url'        => self::get_license_url(),
            // Both gates travel with the summary so a screen never has to infer
            // "is this site still being served?" from `is_active`: a waived
            // license is invalid and served, and the two answers can also part
            // ways with each other (announced release, withheld package).
            'allows_updates'   => self::allows_updates(),
            'allows_downloads' => self::allows_downloads(),
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
     * The download gate has the last word: MDS can announce a release to this
     * site and keep the package, in which case cron would wake up to install
     * something it was never given and log the failure on every pass.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @param bool $update Whether to enable auto-update.
     * @param object $item Plugin update object.
     * @return bool
     */
    public static function enable_auto_update( $update, $item ) {
        if ( ! isset( $item->plugin ) || $item->plugin !== self::get_plugin_file() ) {
            return $update;
        }

        if ( ! self::allows_downloads() ) {
            return false;
        }

        return 'yes' === Settings::get_setting('enable_auto_updates');
    }


    /**
     * Whether both MDS credentials are present.
     *
     * Credentials only, deliberately: the product is registered — and updates
     * are checked — whether or not the site activates a key, so this must not
     * answer for the license switch. Anything that talks to MDS (the "Check
     * for updates" link) hangs off this.
     *
     * @since 3.0.0
     * @version 3.1.0
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
