<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Tracking_Manager;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * DELETE /hubgo/v1/tracking/{id}?order_id= — remove a tracking item.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Tracking_Delete extends Abstract_Route {

    protected $route = '/tracking/(?P<id>[A-Za-z0-9_.\-]+)';
    protected $methods = 'DELETE';

    protected $args = array(
        'order_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
    );


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $order_id = absint( $request->get_param( 'order_id' ) );
        $tracking_id = sanitize_text_field( (string) $request->get_param( 'id' ) );

        if ( ! $order_id || '' === $tracking_id ) {
            return $this->error_response( __( 'Invalid request.', 'hubgo' ), 400 );
        }

        $manager = new Tracking_Manager();
        $manager->delete_item( $order_id, $tracking_id );

        return $this->success_response( array(
            'order_id' => $order_id,
            'items'    => $manager->get_items_for_display( $order_id ),
        ) );
    }
}
