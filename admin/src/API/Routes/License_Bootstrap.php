<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\License;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * GET /hubgo/v1/license — bootstrap payload for the license SPA.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class License_Bootstrap extends Abstract_Route {

    protected $route = '/license';
    protected $methods = 'GET';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        return $this->success_response( array(
            'license' => License::get_payload(),
        ) );
    }
}
