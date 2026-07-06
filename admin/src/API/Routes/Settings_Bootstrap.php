<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Admin\Settings\Registry;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * GET /hubgo/v1/settings — bootstrap payload for the settings SPA.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Settings_Bootstrap extends Abstract_Route {

    protected $route = '/settings';
    protected $methods = 'GET';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        return rest_ensure_response( Registry::get_bootstrap_data() );
    }
}
