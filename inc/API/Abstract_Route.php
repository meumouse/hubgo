<?php

namespace MeuMouse\Hubgo\API;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Base class for HubGo REST endpoints (hubgo/v1).
 *
 * Mirrors the Joinotify REST route pattern: each subclass declares its route,
 * methods, args and permission, and registers itself on rest_api_init.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API
 * @author MeuMouse.com
 */
abstract class Abstract_Route {

    /**
     * REST namespace.
     *
     * @var string
     */
    protected $namespace = 'hubgo/v1';

    /**
     * REST route path, including the leading slash.
     *
     * @var string
     */
    protected $route = '';

    /**
     * Allowed HTTP method(s).
     *
     * @var string
     */
    protected $methods = 'GET';

    /**
     * REST argument schema.
     *
     * @var array
     */
    protected $args = array();


    /**
     * Register the route when WordPress initializes REST endpoints.
     *
     * @since 3.0.0
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_route' ) );
    }


    /**
     * Register the route with the WordPress REST API.
     *
     * @since 3.0.0
     * @return void
     */
    public function register_route() {
        register_rest_route( $this->namespace, $this->route, array(
            'methods'             => $this->methods,
            'callback'            => array( $this, 'handle' ),
            'permission_callback' => array( $this, 'permission' ),
            'args'                => $this->args,
        ) );
    }


    /**
     * Default permission check (admin/shop manager).
     *
     * @since 3.0.0
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permission( WP_REST_Request $request ) {
        return current_user_can( 'manage_woocommerce' );
    }


    /**
     * Handle the REST request.
     *
     * @since 3.0.0
     * @param WP_REST_Request $request REST request instance.
     * @return mixed
     */
    abstract public function handle( WP_REST_Request $request );


    /**
     * Build a standardised success REST response.
     *
     * @since 3.0.0
     * @param array $data Additional key/value pairs.
     * @return \WP_REST_Response
     */
    protected function success_response( array $data = array() ) {
        return rest_ensure_response( array_merge( array( 'status' => 'success' ), $data ) );
    }


    /**
     * Build a standardised error REST response.
     *
     * @since 3.0.0
     * @param string $message Human-readable error description.
     * @param int    $code    HTTP status code.
     * @param array  $data    Optional additional key/value pairs.
     * @return \WP_REST_Response
     */
    protected function error_response( $message, $code = 400, array $data = array() ) {
        $response = rest_ensure_response( array_merge( array(
            'status'  => 'error',
            'message' => $message,
        ), $data ) );

        $response->set_status( $code );

        return $response;
    }
}
