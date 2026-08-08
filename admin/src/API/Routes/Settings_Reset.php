<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Admin\Settings\Repository;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/settings/reset — restore every setting to its default.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Settings_Reset extends Abstract_Route {

    protected $route = '/settings/reset';
    protected $methods = 'POST';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        return $this->success_response( array(
            'message'  => __( 'Settings restored to their default values.', 'hubgo' ),
            'settings' => Repository::reset_settings(),
        ) );
    }
}
