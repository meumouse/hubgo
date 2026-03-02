<?php
namespace MeuMouse\Hubgo\API;

use WP_REST_Controller;
use MeuMouse\Hubgo\Core\Tracking_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tracking_REST_Controller
 *
 * REST API for tracking insertion.
 *
 * @since 2.1.0
 */
class Tracking_REST_Controller extends WP_REST_Controller {

    /**
     * Constructor
     *
     * @since 2.1.0
     */
    public function __construct() {
        $this->namespace = 'hubgo/v1';
        $this->rest_base = 'tracking';

        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    
    /**
     * Register routes
     *
     * @since 2.1.0
     * @return void
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'create_tracking' ),
                'permission_callback' => '__return_true',
            ),
        ));
    }


    /**
     * Create tracking entry
     *
     * @since 2.1.0
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function create_tracking( $request ) {
        $order_id = absint( $request['order_id'] );

        $tracking = new Tracking_Manager();

        $tracking->add_item( $order_id, array(
            'tracking_number' => $request['tracking_number'],
            'carrier'         => $request['carrier'],
            'custom_url'      => $request['custom_url'],
        ) );

        return rest_ensure_response( array(
            'success' => true,
        ));
    }
}