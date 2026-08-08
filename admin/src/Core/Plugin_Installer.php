<?php

namespace MeuMouse\Hubgo\Core;

use Plugin_Upgrader;
use WP_Ajax_Upgrader_Skin;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * One-click installation of the plugins HubGo integrates with.
 *
 * Backs the `POST /hubgo/v1/plugins/install` route used by the Integrations
 * screen: an integration card that declares an `install` descriptor gets an
 * "install plugin" button instead of the plain "dependency missing" notice.
 *
 * Download URLs are checked against an allowlist of hosts. Without it the route
 * would install arbitrary remote code for anyone holding the capability — the
 * card data itself is filterable, so it is not a trusted input.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Plugin_Installer {

    /**
     * Hosts allowed to serve installable packages.
     *
     * @since 3.0.0
     * @return array<int,string>
     */
    public static function get_allowed_hosts() {
        return apply_filters( 'Hubgo/Core/Plugin_Installer/Allowed_Hosts', array(
            'downloads.wordpress.org',
            'meumouse.com',
            'www.meumouse.com',
            'cloud.meumouse.com',
            'packages.meumouse.com',
        ) );
    }


    /**
     * Whether a package URL may be installed.
     *
     * @since 3.0.0
     * @param string $url Package URL.
     * @return bool
     */
    public static function is_allowed_package( $url ) {
        $host = wp_parse_url( (string) $url, PHP_URL_HOST );
        $scheme = wp_parse_url( (string) $url, PHP_URL_SCHEME );

        if ( empty( $host ) || 'https' !== $scheme ) {
            return false;
        }

        return in_array( strtolower( $host ), array_map( 'strtolower', self::get_allowed_hosts() ), true );
    }


    /**
     * Whether a plugin is present on disk (active or not).
     *
     * @since 3.0.0
     * @param string $plugin_file Plugin basename, e.g. "woo-shipping-gateway/woo-shipping-gateway.php".
     * @return bool
     */
    public static function is_installed( $plugin_file ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        return ! empty( $plugins[ $plugin_file ] );
    }


    /**
     * Install (or update) a package and activate the resulting plugin.
     *
     * @since 3.0.0
     * @param string $plugin_file Plugin basename to activate afterwards.
     * @param string $package_url HTTPS URL of the .zip package.
     * @return true|WP_Error
     */
    public static function install_and_activate( $plugin_file, $package_url ) {
        if ( ! current_user_can( 'install_plugins' ) ) {
            return new WP_Error( 'hubgo_install_forbidden', __( 'You do not have permission to install plugins.', 'hubgo' ) );
        }

        $plugin_file = plugin_basename( (string) $plugin_file );

        if ( '' === $plugin_file ) {
            return new WP_Error( 'hubgo_install_invalid_plugin', __( 'The requested plugin is invalid.', 'hubgo' ) );
        }

        if ( ! self::is_allowed_package( $package_url ) ) {
            return new WP_Error( 'hubgo_install_blocked_source', __( 'The package source is not allowed.', 'hubgo' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        wp_cache_flush();

        $upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );

        // The upgrader prints progress markup; the REST response must stay JSON.
        ob_start();

        $result = self::is_installed( $plugin_file )
            ? $upgrader->upgrade( $plugin_file )
            : $upgrader->install( $package_url );

        ob_end_clean();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( false === $result || null === $result ) {
            return new WP_Error( 'hubgo_install_failed', __( 'Could not install the plugin. Try installing it manually.', 'hubgo' ) );
        }

        if ( ! self::is_installed( $plugin_file ) ) {
            return new WP_Error( 'hubgo_install_missing_file', __( 'The plugin was downloaded, but its main file was not found.', 'hubgo' ) );
        }

        $activated = activate_plugin( $plugin_file );

        if ( is_wp_error( $activated ) ) {
            return $activated;
        }

        return true;
    }
}
