<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Address\Address_Service;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * GET /hubgo/v1/address/autocomplete — suggest addresses for the CEP finder.
 *
 * Public endpoint (guests use the calculator), rate limited per visitor because
 * the active provider is spending the store owner's Google quota.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 * @author MeuMouse.com
 */
class Address_Autocomplete extends Abstract_Route {

    protected $route = '/address/autocomplete';
    protected $methods = 'GET';

    protected $args = array(
        'q'       => array( 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
        'session' => array( 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
    );

    /**
     * Longest query term accepted, in characters.
     *
     * A postal address never needs more, and capping it keeps a padded query
     * from being forwarded to a metered upstream.
     *
     * @since 3.0.0
     * @var int
     */
    const MAX_TERM_LENGTH = 120;


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

        $suggestions = $provider->search( array(
            'q'       => $this->clamp( $request->get_param('q') ),
            'session' => (string) $request->get_param('session'),
        ) );

        if ( is_wp_error( $suggestions ) ) {
            return $this->error_response( $suggestions->get_error_message(), 400 );
        }

        return $this->success_response( array(
            'mode'        => $provider->get_mode(),
            'suggestions' => array_values( (array) $suggestions ),
        ) );
    }


    /**
     * Trim a query term to the accepted length.
     *
     * @since 3.0.0
     * @param mixed $value Raw parameter.
     * @return string
     */
    private function clamp( $value ) {
        return mb_substr( trim( (string) $value ), 0, self::MAX_TERM_LENGTH );
    }
}
