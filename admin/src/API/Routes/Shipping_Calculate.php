<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Shipping_Calculator_Service;
use MeuMouse\Hubgo\Core\Shipping_Preference;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/shipping/calculate — public storefront shipping quote.
 *
 * Public endpoint (guests can calculate shipping). Returns normalized rate rows
 * as JSON; the storefront renders them client-side.
 *
 * The response carries three blocks: `rates` (the rows), `free_shipping` (the
 * threshold badge context) and `context` (destination + the shopper's preferred
 * rate id, resolved against the rates actually available here so the storefront
 * never highlights a method this zone does not offer).
 *
 * @since 3.0.0
 * @version 3.0.0
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
        'country'      => array( 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
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
     *
     * @version 3.0.0
     */
    public function handle( WP_REST_Request $request ) {
        $service = new Shipping_Calculator_Service();

        $rates = $service->calculate(
            absint( $request->get_param( 'product' ) ),
            absint( $request->get_param( 'variation_id' ) ),
            (string) $request->get_param( 'postcode' ),
            absint( $request->get_param( 'qty' ) ) ?: 1,
            (string) $request->get_param( 'country' )
        );

        $error = $service->get_last_error();

        // An unusable postcode is a client mistake, not an empty result. Telling
        // them apart is what keeps the storefront from silently showing
        // "no delivery available" for a typo.
        if ( 'invalid_postcode' === $error ) {
            return $this->error_response(
                __( 'Invalid postcode. Check the number you entered and try again.', 'hubgo' ),
                400
            );
        }

        $context = $service->get_context();
        $context['preferred_id'] = Shipping_Preference::match_preferred_rate( wp_list_pluck( $rates, 'id' ) );

        $data = array(
            'rates'         => $rates,
            'free_shipping' => $service->get_free_shipping_context(),
            'context'       => $context,
        );

        // Surface the matched zone while the store owner is debugging shipping,
        // so zone priority can be confirmed without touching the database.
        if ( 'yes' === get_option( 'woocommerce_shipping_debug_mode', 'no' ) ) {
            $data['zone']       = $service->get_matched_zone();
            $data['error_code'] = $error;
        }

        return $this->success_response( $data );
    }
}
