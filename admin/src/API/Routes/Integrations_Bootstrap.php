<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Admin\Settings\Registry;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * GET /hubgo/v1/integrations — bootstrap payload for the integrations SPA.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Integrations_Bootstrap extends Abstract_Route {

    protected $route = '/integrations';
    protected $methods = 'GET';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        return rest_ensure_response( Registry::get_integrations_bootstrap_data() );
    }
}
