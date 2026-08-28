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

            $sanitized[ $key ] = self::sanitize_value( $type, $incoming[ $key ], $definition );
        }

        update_option( self::OPTION_NAME, $sanitized );

        return $sanitized;
    }


    /**
     * Restore every setting to its default value.
     *
     * The option is deleted rather than overwritten with the defaults so a later
     * change to Default_Options reaches sites that were reset.
     *
     * @since 3.0.0
     * @return array The defaults now in effect.
     */
    public static function reset_settings() {
        delete_option( self::OPTION_NAME );

        do_action( 'Hubgo/Admin/Settings/Reset' );

        return self::get_settings();
    }


    /**
     * Sanitize a value according to its field type.
     *
     * @since 3.0.0
     * @version 3.0.1
     * @param string $type Field type.
     * @param mixed $value Raw value.
     * @param array $definition Field definition, for type-specific constraints.
     * @return mixed
     */
    private static function sanitize_value( $type, $value, $definition = array() ) {
        switch ( $type ) {
            case 'toggle':
                return self::sanitize_toggle( $value );
            case 'color':
                // An empty colour is meaningful for the calculator style keys:
                // it means "keep the built-in value", so it must survive the
                // round-trip instead of being coerced to a default.
                if ( '' === trim( (string) $value ) ) {
                    return '';
                }

                $color = sanitize_hex_color( (string) $value );
                return $color ? $color : '';
            case 'number':
            case 'range':
                return self::sanitize_number( $value, $definition );
            case 'dimension':
                return self::sanitize_dimension( $value, $definition );
            case 'textarea':
                return sanitize_textarea_field( (string) $value );
            case 'password':
            case 'select':
            case 'text':
            default:
                return sanitize_text_field( (string) $value );
        }
    }


    /**
     * Normalize a numeric value and clamp it to the field's declared bounds.
     *
     * Stored as a string so an unset value stays distinguishable from a real
     * zero — several calculator style keys treat "" as "use the default".
     *
     * @since 3.0.0
     * @param mixed $value Raw value.
     * @param array $definition Field definition (min, max).
     * @return string
     */
    private static function sanitize_number( $value, $definition ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = trim( (string) $value );

        if ( '' === $value || ! is_numeric( $value ) ) {
            return '';
        }

        $number = (float) $value;

        if ( isset( $definition['min'] ) && is_numeric( $definition['min'] ) ) {
            $number = max( (float) $definition['min'], $number );
        }

        if ( isset( $definition['max'] ) && is_numeric( $definition['max'] ) ) {
            $number = min( (float) $definition['max'], $number );
        }

        // Keep whole numbers whole: "12" reads better than "12.0" both in the
        // option table and in the CSS custom properties built from it.
        return ( floor( $number ) === $number ) ? (string) (int) $number : (string) $number;
    }


    /**
     * Normalize a CSS length: an amount, optionally followed by a unit.
     *
     * The unit is kept on the value because that is what the calculator's custom
     * properties consume. A bare number is accepted and stored as-is — that is
     * how every length was stored before 3.0.1, and the token map still knows
     * which unit to append to it.
     *
     * Anything the field did not offer is refused rather than coerced: an
     * unexpected unit reaching a style block is how a setting turns into markup.
     *
     * @since 3.0.0
     * @param mixed $value Raw value.
     * @param array $definition Field definition (min, max, units).
     * @return string Empty string when the value cannot be used.
     */
    private static function sanitize_dimension( $value, $definition ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = strtolower( trim( (string) $value ) );

        if ( '' === $value ) {
            return '';
        }

        if ( ! preg_match( '/^(-?\d*\.?\d+)\s*(rem|em|px|%)?$/', $value, $matches ) ) {
            return '';
        }

        $unit  = isset( $matches[2] ) ? $matches[2] : '';
        $units = isset( $definition['units'] ) && is_array( $definition['units'] ) ? $definition['units'] : array( 'rem', 'em', 'px', '%' );

        if ( '' !== $unit && ! in_array( $unit, $units, true ) ) {
            return '';
        }

        $number = self::sanitize_number( $matches[1], $definition );

        return '' === $number ? '' : $number . $unit;
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
