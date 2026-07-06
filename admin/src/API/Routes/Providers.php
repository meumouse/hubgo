<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Providers_Registry;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * GET /hubgo/v1/providers — registered shipping providers (optional country filter).
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Providers extends Abstract_Route {

    protected $route = '/providers';
    protected $methods = 'GET';

    protected $args = array(
        'country' => array(
            'type'              => 'string',
            'required'          => false,
            'sanitize_callback' => 'sanitize_text_field',
        ),
    );


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $providers = Providers_Registry::get_providers();
        $country = trim( (string) $request->get_param( 'country' ) );

        if ( '' === $country ) {
            return $this->success_response( array( 'providers' => $providers ) );
        }

        foreach ( $providers as $group => $items ) {
            if ( 0 === strcasecmp( (string) $group, $country ) ) {
                return $this->success_response( array(
                    'country'   => $group,
                    'providers' => array( $group => $items ),
                ) );
            }
        }

        return $this->success_response( array(
            'country'   => $country,
            'providers' => array(),
        ) );
    }
}
