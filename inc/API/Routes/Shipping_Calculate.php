<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Shipping_Calculator_Service;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/shipping/calculate — public storefront shipping quote.
 *
 * Public endpoint (guests can calculate shipping). Returns normalized rate rows
 * as JSON; the storefront renders the table client-side.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Shipping_Calculate extends Abstract_Route {

    protected $route = '/shipping/calculate';
    protected $methods = 'POST';

    protected $args = array(
        'product'      => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint' ),
        'variation_id' => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint' ),
        'postcode'     => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
        'qty'          => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint' ),
    );


    /**
     * Public access — shipping calculation is a read-only quote.
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
        $service = new Shipping_Calculator_Service();

        $rates = $service->calculate(
            absint( $request->get_param( 'product' ) ),
            absint( $request->get_param( 'variation_id' ) ),
            (string) $request->get_param( 'postcode' ),
            absint( $request->get_param( 'qty' ) ) ?: 1
        );

        return $this->success_response( array(
            'rates' => $rates,
        ) );
    }
}
