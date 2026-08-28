<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Address\Address_Service;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * GET /hubgo/v1/address/resolve — turn a suggestion into a postcode.
 *
 * Second half of the CEP finder flow. Providers that already knew the postcode
 * at search time answer without a network call; the ones that did not (Google)
 * spend their Place Details lookup here, which is also what closes the billing
 * session opened by the autocomplete requests.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 * @author MeuMouse.com
 */
class Address_Resolve extends Abstract_Route {

    protected $route = '/address/resolve';
    protected $methods = 'GET';

    protected $args = array(
        'id'      => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
        'session' => array( 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
    );


    /**
     * Public access — the CEP finder must work for guests.
     *
     * @inheritDoc
     */
    public function permission( WP_REST_Request $request ) {
        return true;
    }


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $provider = Address_Service::is_finder_enabled() ? Address_Service::get_provider() : null;

        if ( ! $provider ) {
            return $this->error_response( __( 'Address lookup is disabled.', 'hubgo' ), 404 );
        }

        if ( ! Address_Service::consume_rate_limit() ) {
            return $this->error_response(
                __( 'Too many lookups in a short time. Wait a moment and try again.', 'hubgo' ),
                429
            );
        }

        $postcode = $provider->resolve( array(
            'id'      => (string) $request->get_param('id'),
            'session' => (string) $request->get_param('session'),
        ) );

        if ( is_wp_error( $postcode ) ) {
            return $this->error_response( $postcode->get_error_message(), 400 );
        }

        return $this->success_response( array(
            'postcode' => (string) $postcode,
        ) );
    }
}
