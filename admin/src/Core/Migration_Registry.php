<?php

namespace MeuMouse\Hubgo\Core;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Registry of the data migrations HubGo can run.
 *
 * Mirrors {@see \MeuMouse\Hubgo\Integrations\Integration_Registry}: third
 * parties append their own {@see Abstract_Migration} subclass to the
 * `Hubgo/Migrations/Registered` filter and get the REST route, the batching and
 * the Integrations-screen progress bar for free.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Migration_Registry {

    /**
     * Instantiated migrations, keyed by ID.
     *
     * @since 3.0.0
     * @var array<string,Abstract_Migration>|null
     */
    protected static $migrations = null;


    /**
     * All registered migrations, keyed by ID.
     *
     * @since 3.0.0
     * @return array<string,Abstract_Migration>
     */
    public static function get_migrations() {
        if ( null !== self::$migrations ) {
            return self::$migrations;
        }

        /**
         * Filter the migration classes HubGo instantiates.
         *
         * @since 3.0.0
         * @param array<int,string> $classes | Fully qualified class names.
         */
        $classes = apply_filters( 'Hubgo/Migrations/Registered', array(
            Woo_Shipment_Tracking_Migration::class,
        ) );

        self::$migrations = array();

        foreach ( (array) $classes as $class ) {
            if ( ! is_string( $class ) || ! class_exists( $class ) ) {
                continue;
            }

            $migration = new $class();

            if ( ! $migration instanceof Abstract_Migration ) {
                continue;
            }

            self::$migrations[ $migration->get_id() ] = $migration;
        }

        return self::$migrations;
    }


    /**
     * Look a migration up by ID.
     *
     * @since 3.0.0
     * @param string $id | Migration ID.
     * @return Abstract_Migration|null
     */
    public static function get( $id ) {
        $migrations = self::get_migrations();
        $id = sanitize_key( (string) $id );

        return $migrations[ $id ] ?? null;
    }


    /**
     * Status payload of every registered migration.
     *
     * @since 3.0.0
     * @return array<string,array<string,mixed>>
     */
    public static function get_status_list() {
        $statuses = array();

        foreach ( self::get_migrations() as $id => $migration ) {
            $statuses[ $id ] = $migration->get_status();
        }

        return $statuses;
    }
}
