<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Tracking_Manager;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * GET /hubgo/v1/tracking?order_id= — list tracking items for an order.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Tracking_Get extends Abstract_Route {

    protected $route = '/tracking';
    protected $methods = 'GET';

    protected $args = array(
        'order_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
    );


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $order_id = absint( $request->get_param( 'order_id' ) );

        if ( ! $order_id ) {
            return $this->error_response( __( 'Invalid order.', 'hubgo' ), 400 );
        }

        $manager = new Tracking_Manager();

        return $this->success_response( array(
            'order_id' => $order_id,
            'items'    => $manager->get_items_for_display( $order_id ),
        ) );
    }
}
