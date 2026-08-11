<?php

namespace MeuMouse\Hubgo\API;

use MeuMouse\Hubgo\API\Routes\Settings_Bootstrap;
use MeuMouse\Hubgo\API\Routes\Settings_Save;
use MeuMouse\Hubgo\API\Routes\Settings_Reset;
use MeuMouse\Hubgo\API\Routes\Integrations_Bootstrap;
use MeuMouse\Hubgo\API\Routes\Plugin_Install;
use MeuMouse\Hubgo\API\Routes\Migration_Run;
use MeuMouse\Hubgo\API\Routes\License_Bootstrap;
use MeuMouse\Hubgo\API\Routes\License_Activate;
use MeuMouse\Hubgo\API\Routes\License_Deactivate;
use MeuMouse\Hubgo\API\Routes\License_Sync;
use MeuMouse\Hubgo\API\Routes\Update_Check;
use MeuMouse\Hubgo\API\Routes\Shipping_Calculate;
use MeuMouse\Hubgo\API\Routes\Address_Autocomplete;
use MeuMouse\Hubgo\API\Routes\Address_Resolve;
use MeuMouse\Hubgo\API\Routes\Providers;
use MeuMouse\Hubgo\API\Routes\Tracking_Get;
use MeuMouse\Hubgo\API\Routes\Tracking_Create;
use MeuMouse\Hubgo\API\Routes\Tracking_Delete;

defined('ABSPATH') || exit;

/**
 * Instantiates every hubgo/v1 REST route.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API
 * @author MeuMouse.com
 */
class Rest_Controller {

    /**
     * Constructor: register all routes.
     *
     * @since 3.0.0
     */
    public function __construct() {
        $routes = apply_filters( 'Hubgo/API/Routes', array(
            Settings_Bootstrap::class,
            Settings_Save::class,
            Settings_Reset::class,
            Integrations_Bootstrap::class,
            Plugin_Install::class,
            Migration_Run::class,
            License_Bootstrap::class,
            License_Activate::class,
            License_Deactivate::class,
            License_Sync::class,
            Update_Check::class,
            Shipping_Calculate::class,
            Address_Autocomplete::class,
            Address_Resolve::class,
            Providers::class,
            Tracking_Get::class,
            Tracking_Create::class,
            Tracking_Delete::class,
        ) );

        foreach ( $routes as $route_class ) {
            if ( class_exists( $route_class ) ) {
                new $route_class();
            }
        }
    }
}
