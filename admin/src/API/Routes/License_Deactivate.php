<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\License;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/license/deactivate — release this site's activation.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class License_Deactivate extends Abstract_Route {

    protected $route = '/license/deactivate';
    protected $methods = 'POST';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $result = License::deactivate_license();

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message() );
        }

        return $this->success_response( array(
            'message' => esc_html__( 'Licença desativada neste site.', 'hubgo' ),
            'license' => License::get_payload(),
        ) );
    }
}
