<?php

namespace MeuMouse\Hubgo\Admin;

defined('ABSPATH') || exit;

/**
 * Environment snapshot rendered by the "About" tab.
 *
 * Read-only and cheap: every value comes from constants or already-loaded
 * globals, so the settings bootstrap can include it without an extra round trip.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Admin
 * @author MeuMouse.com
 */
class System_Status {

    /**
     * Build the status rows.
     *
     * Each row is array( 'id', 'label', 'value', 'tone' ), where `tone` is one
     * of neutral|success|warning|danger and drives the badge colour.
     *
     * @since 3.0.0
     * @return array<int,array<string,string>>
     */
    public static function get_status() {
        global $wp_version;

        $rows = array(
            self::row( 'plugin_version', __( 'HubGo version', 'hubgo' ), defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '—' ),
            self::row( 'wp_version', __( 'WordPress', 'hubgo' ), (string) $wp_version ),
            self::row( 'php_version', __( 'PHP', 'hubgo' ), PHP_VERSION, version_compare( PHP_VERSION, '7.4', '>=' ) ? 'success' : 'danger' ),
            self::row(
                'wc_version',
                __( 'WooCommerce', 'hubgo' ),
                defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not detected', 'hubgo' ),
                defined( 'WC_VERSION' ) ? 'success' : 'danger'
            ),
            self::row(
                'hpos',
                __( 'Order tables (HPOS)', 'hubgo' ),
                self::is_hpos_enabled() ? __( 'Enabled', 'hubgo' ) : __( 'Disabled', 'hubgo' )
            ),
            self::row(
                'ssl',
                __( 'HTTPS', 'hubgo' ),
                is_ssl() ? __( 'Active', 'hubgo' ) : __( 'Inactive', 'hubgo' ),
                is_ssl() ? 'success' : 'warning'
            ),
            self::row(
                'wp_debug',
                __( 'WP_DEBUG', 'hubgo' ),
                ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? __( 'Enabled', 'hubgo' ) : __( 'Disabled', 'hubgo' )
            ),
            self::row( 'memory_limit', __( 'Memory limit', 'hubgo' ), (string) ini_get( 'memory_limit' ) ),
            self::row( 'max_execution_time', __( 'Max execution time', 'hubgo' ), (string) ini_get( 'max_execution_time' ) . 's' ),
            self::row( 'locale', __( 'Site language', 'hubgo' ), function_exists( 'determine_locale' ) ? determine_locale() : get_locale() ),
        );

        return apply_filters( 'Hubgo/Admin/System_Status', $rows );
    }


    /**
     * Whether WooCommerce is running on custom order tables.
     *
     * @since 3.0.0
     * @return bool
     */
    private static function is_hpos_enabled() {
        if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
            return false;
        }

        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }


    /**
     * Build one status row.
     *
     * @since 3.0.0
     * @param string $id Row identifier.
     * @param string $label Human-readable label.
     * @param string $value Displayed value.
     * @param string $tone Badge tone.
     * @return array<string,string>
     */
    private static function row( $id, $label, $value, $tone = 'neutral' ) {
        return array(
            'id'    => $id,
            'label' => $label,
            'value' => (string) $value,
            'tone'  => $tone,
        );
    }
}
