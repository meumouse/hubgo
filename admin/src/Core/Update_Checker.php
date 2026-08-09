<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Core\License;

use MeuMouse\MDS\SDK\Support\Cache;
use MeuMouse\MDS\SDK\Updates\AbstractUpdater;

use Exception;
use WP_Error;
use stdClass;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * "Check for updates" link on the WordPress plugins list.
 *
 * Same pattern the Plugin Update Checker library publishes: a link in the HubGo
 * row that re-runs the update check on demand, instead of waiting for the
 * twice-daily scan WordPress performs. The link answers over
 * `POST hubgo/v1/updates/check` and reports the result next to itself, so the
 * plugins screen is never reloaded; the href stays a real, nonced URL so the
 * check still runs when the script does not (JS disabled, blocked asset).
 *
 * The check itself is a forced pass through the MDS SDK: its 12h update cache
 * and the rollback version list are dropped, the license is re-validated
 * (updates are a licensed benefit) and the `update_plugins` transient is
 * re-saved, which replays `pre_set_site_transient_update_plugins` — the filter
 * where the SDK's PluginUpdater injects HubGo's update data.
 *
 * @since 3.1.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Update_Checker {

    /**
     * Query argument that triggers the no-JavaScript fallback check.
     *
     * @since 3.1.0
     * @var string
     */
    const QUERY_ARG = 'hubgo-check-updates';

    /**
     * Nonce action guarding the fallback check.
     *
     * @since 3.1.0
     * @var string
     */
    const NONCE_ACTION = 'hubgo_check_updates';


    /**
     * Constructor: wire the plugins-list link and its fallback handler.
     *
     * @since 3.1.0
     */
    public function __construct() {
        add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_link' ), 10, 2 );
        add_action( 'admin_init', array( $this, 'maybe_handle_fallback' ) );
        add_action( 'admin_notices', array( $this, 'render_notice' ) );
    }


    /**
     * Append the "Check for updates" link to the HubGo plugin row.
     *
     * The status node ships in the same meta item as the link: `plugin_row_meta`
     * items are joined with a separator, and a separate item would put a stray
     * pipe on screen while the status is still empty.
     *
     * @since 3.1.0
     * @param array $meta Row meta items.
     * @param string $file Plugin basename of the row being rendered.
     * @return array
     */
    public function add_row_meta_link( $meta, $file ) {
        if ( self::get_plugin_file() !== $file || ! self::can_check() ) {
            return $meta;
        }

        $meta[] = sprintf(
            '<a href="%1$s" class="hubgo-check-updates" data-hubgo-check-updates="1">%2$s</a>' .
            '<span class="hubgo-check-updates-status" role="status" aria-live="polite"></span>',
            esc_url( self::get_fallback_url() ),
            esc_html__( 'Check for updates', 'hubgo' )
        );

        return $meta;
    }


    /**
     * Run the check server-side when the link is followed without JavaScript.
     *
     * @since 3.1.0
     * @return void
     */
    public function maybe_handle_fallback() {
        if ( empty( $_GET[ self::QUERY_ARG ] ) || ! self::can_check() ) {
            return;
        }

        check_admin_referer( self::NONCE_ACTION );

        $result = self::check();

        if ( is_wp_error( $result ) ) {
            $notice = array(
                'type'    => 'error',
                'message' => $result->get_error_message(),
            );
        } else {
            $notice = array(
                'type'    => ! empty( $result['update_available'] ) ? 'warning' : 'success',
                'message' => $result['message'],
            );
        }

        set_transient( self::notice_key(), $notice, MINUTE_IN_SECONDS );

        wp_safe_redirect( remove_query_arg( array( self::QUERY_ARG, '_wpnonce' ) ) );
        exit;
    }


    /**
     * Render the notice left behind by the fallback check.
     *
     * Scoped to the plugins list, which is where the fallback redirects back
     * to: the transient carries an expiry and is therefore not autoloaded, so
     * reading it on every admin screen would cost a query per page load.
     *
     * @since 3.1.0
     * @return void
     */
    public function render_notice() {
        global $pagenow;

        if ( 'plugins.php' !== $pagenow ) {
            return;
        }

        $notice = get_transient( self::notice_key() );

        if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
            return;
        }

        delete_transient( self::notice_key() );

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p><strong>%2$s:</strong> %3$s</p></div>',
            esc_attr( isset( $notice['type'] ) ? $notice['type'] : 'info' ),
            esc_html__( 'HubGo', 'hubgo' ),
            esc_html( $notice['message'] )
        );
    }


    /**
     * Force a fresh update check and return the resulting state.
     *
     * @since 3.1.0
     * @return array<string,mixed>|\WP_Error Payload as described in {@see self::get_payload()}.
     */
    public static function check() {
        $integration = License::get_integration();

        if ( ! $integration ) {
            return new WP_Error( 'hubgo_updates_unavailable', __( 'The update service is not available.', 'hubgo' ) );
        }

        // The SDK holds the update payload for 12h and the rollback version list
        // beside it. Both have to go, or a "check now" would answer from cache —
        // this is the same pair the SDK's own check-now action clears.
        $cache = new Cache( $integration->product() );
        $cache->delete( AbstractUpdater::CACHE_UPDATE );
        $integration->rollback()->clear_cache();

        // Updates are gated by the license, and a refused check is cached with a
        // short negative TTL, so the key is re-validated first: a license
        // renewed minutes ago has to be visible to this very request.
        try {
            $integration->license()->validate();
        } catch ( Exception $e ) {
            // Deliberately ignored: the persisted status still gates the check
            // below, and a transport hiccup here must not hide an update that
            // the transient pass can still find.
        }

        self::refresh_update_transient();

        return self::get_payload();
    }


    /**
     * Current update state, read from the transient WordPress already holds.
     *
     * Shape: `update_available`, `current_version`, `new_version`, `is_licensed`,
     * `update_url`, `message`.
     *
     * @since 3.1.0
     * @return array<string,mixed>
     */
    public static function get_payload() {
        $new_version = self::get_new_version();
        $update_available = '' !== $new_version;
        $is_licensed = License::is_active();

        if ( $update_available ) {
            /* translators: %s: version number. */
            $message = sprintf( __( 'Version %s is available.', 'hubgo' ), $new_version );
        } elseif ( ! $is_licensed ) {
            $message = __( 'Activate your license to receive updates.', 'hubgo' );
        } else {
            $message = __( 'HubGo is up to date.', 'hubgo' );
        }

        return apply_filters( 'Hubgo/Core/Update_Checker/Payload', array(
            'update_available' => $update_available,
            'current_version'  => defined('HUBGO_VERSION') ? HUBGO_VERSION : '',
            'new_version'      => $new_version,
            'is_licensed'      => $is_licensed,
            'update_url'       => $update_available ? self::get_update_url() : '',
            'message'          => $message,
        ) );
    }


    /**
     * Whether the current user may run the check.
     *
     * `update_plugins` rather than the HubGo settings capability: a shop manager
     * who may edit every setting still cannot update a plugin, and the answer
     * would be useless to them.
     *
     * @since 3.1.0
     * @return bool
     */
    public static function can_check() {
        return current_user_can('update_plugins') && License::is_configured();
    }


    /**
     * Re-save the plugin update transient so the SDK re-injects HubGo's data.
     *
     * `set_site_transient()` runs `pre_set_site_transient_update_plugins`, which
     * is where the SDK's PluginUpdater answers — with its cache just dropped,
     * that pass really talks to MDS. `last_checked` is deliberately left alone:
     * bumping it would tell core every other plugin had just been checked too,
     * postponing its own scan by up to 12 hours. Re-saving is also why
     * `wp_update_plugins()` is not used here — that would re-scan the whole
     * plugin list against wordpress.org for a check the user asked about one
     * plugin.
     *
     * @since 3.1.0
     * @return void
     */
    private static function refresh_update_transient() {
        $updates = get_site_transient('update_plugins');

        if ( ! is_object( $updates ) ) {
            $updates = new stdClass();
        }

        set_site_transient( 'update_plugins', $updates );
    }


    /**
     * Version WordPress currently advertises for HubGo, when newer than the
     * installed one.
     *
     * @since 3.1.0
     * @return string Version number, or an empty string when up to date.
     */
    private static function get_new_version() {
        $updates = get_site_transient('update_plugins');
        $file = self::get_plugin_file();

        if ( ! is_object( $updates ) || empty( $updates->response[ $file ] ) ) {
            return '';
        }

        $update = (object) $updates->response[ $file ];
        $new_version = isset( $update->new_version ) ? (string) $update->new_version : '';
        $current = defined('HUBGO_VERSION') ? HUBGO_VERSION : '';

        if ( '' === $new_version || '' === $current ) {
            return '';
        }

        return version_compare( $new_version, $current, '>' ) ? $new_version : '';
    }


    /**
     * Nonced URL of the core one-click update for HubGo.
     *
     * @since 3.1.0
     * @return string
     */
    private static function get_update_url() {
        $file = self::get_plugin_file();

        if ( '' === $file || ! current_user_can( 'update_plugins' ) ) {
            return '';
        }

        return wp_nonce_url(
            self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ),
            'upgrade-plugin_' . $file
        );
    }


    /**
     * Nonced URL that runs the check without JavaScript.
     *
     * @since 3.1.0
     * @return string
     */
    private static function get_fallback_url() {
        return wp_nonce_url(
            add_query_arg( self::QUERY_ARG, '1', self_admin_url('plugins.php') ),
            self::NONCE_ACTION
        );
    }


    /**
     * Transient key holding the fallback notice for the current user.
     *
     * @since 3.1.0
     * @return string
     */
    private static function notice_key() {
        return 'hubgo_update_check_notice_' . get_current_user_id();
    }


    /**
     * Plugin basename ("hubgo/hubgo.php").
     *
     * @since 3.1.0
     * @return string
     */
    private static function get_plugin_file() {
        if ( defined('HUBGO_BASENAME') ) {
            return HUBGO_BASENAME;
        }

        return defined('HUBGO_FILE') ? plugin_basename( HUBGO_FILE ) : '';
    }
}
