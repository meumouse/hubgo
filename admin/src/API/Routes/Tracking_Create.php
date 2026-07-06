<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Tracking_Manager;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/tracking — add a tracking item to an order.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Tracking_Create extends Abstract_Route {

    protected $route = '/tracking';
    protected $methods = 'POST';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : $request->get_params();

        $order_id = isset( $params['order_id'] ) ? absint( $params['order_id'] ) : 0;

        if ( ! $order_id ) {
            return $this->error_response( __( 'Pedido inválido.', 'hubgo' ), 400 );
        }

        if ( empty( $params['tracking_number'] ) ) {
            return $this->error_response( __( 'Informe o código de rastreio.', 'hubgo' ), 400 );
        }

        $manager = new Tracking_Manager();

        $provider = ! empty( $params['provider'] ) ? $params['provider'] : ( $params['carrier'] ?? '' );

        $item = $manager->add_item( $order_id, array(
            'tracking_number' => $params['tracking_number'],
            'provider'        => $provider,
            'custom_provider' => $params['custom_provider'] ?? '',
            'custom_url'      => $params['custom_url'] ?? '',
            'ship_date'       => $params['ship_date'] ?? '',
        ) );

        return $this->success_response( array(
            'item'  => $item,
            'items' => $manager->get_items_for_display( $order_id ),
        ) );
    }
}
