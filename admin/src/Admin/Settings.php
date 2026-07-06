<?php

namespace MeuMouse\Hubgo\Admin;

use MeuMouse\Hubgo\Admin\Settings\Repository;

defined('ABSPATH') || exit;

/**
 * Class Settings
 *
 * Static accessor for plugin settings. The admin UI is now a Vue SPA (see
 * MeuMouse\Hubgo\Admin\Menu) and persistence lives in
 * MeuMouse\Hubgo\Admin\Settings\Repository. This class remains as a thin,
 * backward-compatible read/write facade used across the plugin.
 *
 * @since 2.0.0
 * @version 3.0.0
 * @package MeuMouse\Hubgo\Admin
 * @author MeuMouse.com
 */
class Settings {

    /**
     * Settings option name.
     *
     * @var string
     */
    const OPTION_NAME = 'hubgo_settings';


    /**
     * Get a specific setting value (defaults merged at read time).
     *
     * @since 2.0.0
     * @param string $key Setting key.
     * @param mixed $default Default value if setting not found.
     * @return mixed
     */
    public static function get_setting( $key, $default = false ) {
        $options = Repository::get_settings();

        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }


    /**
     * Get all settings.
     *
     * @since 3.0.0
     * @return array
     */
    public static function get_all() {
        return Repository::get_settings();
    }


    /**
     * Update a specific setting.
     *
     * @since 2.0.0
     * @param string $key Setting key.
     * @param mixed $value Setting value.
     * @return bool
     */
    public static function update_setting( $key, $value ) {
        $options = get_option( self::OPTION_NAME, array() );
        $options = is_array( $options ) ? $options : array();
        $options[ $key ] = $value;

        return update_option( self::OPTION_NAME, $options );
    }


    /**
     * Get default value for a setting key.
     *
     * @since 2.0.0
     * @param string $name Setting key.
     * @param mixed $fallback Fallback value.
     * @return mixed
     */
    public static function get_default_value( $name, $fallback = '' ) {
        $defaults = Default_Options::get_defaults();

        return isset( $defaults[ $name ] ) ? $defaults[ $name ] : $fallback;
    }
}
