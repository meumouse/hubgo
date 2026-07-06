<?php

namespace MeuMouse\Hubgo\Admin\Settings;

use MeuMouse\Hubgo\Admin\Default_Options;

defined('ABSPATH') || exit;

/**
 * Settings persistence + type-based sanitization.
 *
 * All settings live in a single option (`hubgo_settings`). Values are read with
 * defaults merged at read time, and written through type-aware sanitizers derived
 * from the schema field definitions.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Admin\Settings
 * @author MeuMouse.com
 */
class Repository {

    /**
     * Option name.
     *
     * @var string
     */
    const OPTION_NAME = 'hubgo_settings';


    /**
     * Get all settings (stored values merged over defaults).
     *
     * @since 3.0.0
     * @return array
     */
    public static function get_settings() {
        $stored = get_option( self::OPTION_NAME, array() );
        $stored = is_array( $stored ) ? $stored : array();

        return wp_parse_args( $stored, Default_Options::get_defaults() );
    }


    /**
     * Persist incoming settings after sanitization.
     *
     * @since 3.0.0
     * @param array $incoming Raw settings map from the client.
     * @return array Sanitized settings that were saved.
     */
    public static function save_settings( $incoming ) {
        $incoming = is_array( $incoming ) ? $incoming : array();
        $definitions = Registry::get_field_definitions();
        $current = self::get_settings();
        $sanitized = $current;

        foreach ( $definitions as $key => $definition ) {
            $type = isset( $definition['type'] ) ? (string) $definition['type'] : 'text';

            // Toggles: absent means "off".
            if ( 'toggle' === $type ) {
                $value = isset( $incoming[ $key ] ) ? $incoming[ $key ] : 'no';
                $sanitized[ $key ] = self::sanitize_toggle( $value );
                continue;
            }

            if ( ! array_key_exists( $key, $incoming ) ) {
                continue;
            }

            $sanitized[ $key ] = self::sanitize_value( $type, $incoming[ $key ] );
        }

        update_option( self::OPTION_NAME, $sanitized );

        return $sanitized;
    }


    /**
     * Sanitize a value according to its field type.
     *
     * @since 3.0.0
     * @param string $type Field type.
     * @param mixed $value Raw value.
     * @return mixed
     */
    private static function sanitize_value( $type, $value ) {
        switch ( $type ) {
            case 'toggle':
                return self::sanitize_toggle( $value );
            case 'color':
                $color = sanitize_hex_color( (string) $value );
                return $color ? $color : '';
            case 'textarea':
                return sanitize_textarea_field( (string) $value );
            case 'select':
            case 'text':
            default:
                return sanitize_text_field( (string) $value );
        }
    }


    /**
     * Normalize a toggle value to 'yes' | 'no'.
     *
     * @since 3.0.0
     * @param mixed $value Raw value.
     * @return string
     */
    private static function sanitize_toggle( $value ) {
        return in_array( $value, array( 'yes', true, 'true', 1, '1' ), true ) ? 'yes' : 'no';
    }
}
