<?php

namespace MeuMouse\Hubgo\API;

use MeuMouse\Hubgo\API\Routes\Settings_Bootstrap;
use MeuMouse\Hubgo\API\Routes\Settings_Save;
use MeuMouse\Hubgo\API\Routes\Shipping_Calculate;
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
            Shipping_Calculate::class,
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
