<?php

namespace MeuMouse\Hubgo\API;

use MeuMouse\Hubgo\Core\Tracking_Manager;
use MeuMouse\Hubgo\Core\Providers_Registry;

use WP_REST_Controller;

defined('ABSPATH') || exit;

/**
 * Class Tracking_REST_Controller
 *
 * REST API for tracking insertion.
 *
 * @since 2.1.0
 * @package MeuMouse\Hubgo\API
 * @author MeuMouse.com
 */
class Tracking_REST_Controller extends WP_REST_Controller {

    /**
     * Constructor
     *
     * @since 2.1.0
     * @return void
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
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_tracking' ),
                'permission_callback' => '__return_true',
            ),
        ));

        register_rest_route( $this->namespace, '/providers', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_providers' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'country' => array(
                        'description'       => __( 'Country/region name to filter providers (e.g. Brazil).', 'hubgo' ),
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));
    }


    /**
     * Create tracking entry
     *
     * @since 2.1.0
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function create_tracking( $request ) {
        $order_id = absint( $request['order_id'] );
        $tracking = new Tracking_Manager();

        $provider = ! empty( $request['provider'] ) ? $request['provider'] : $request['carrier'];

        $tracking->add_item( $order_id, array(
            'tracking_number' => $request['tracking_number'],
            'provider'        => $provider,
            'custom_provider' => $request['custom_provider'],
            'custom_url'      => $request['custom_url'],
            'ship_date'       => $request['ship_date'],
        ));

        // Fire shipped order trigger.
        $tracking->trigger_shipped_event( $order_id );

        return rest_ensure_response( array(
            'success' => true,
        ));
    }


    /**
     * Get registered shipping providers.
     *
     * Optional query params:
     * - country: Filter by country/region key.
     *
     * @since 2.1.0
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function get_providers( $request ) {
        $providers = Providers_Registry::get_providers();
        $country = trim( (string) $request->get_param( 'country' ) );

        if ( empty( $country ) ) {
            return rest_ensure_response( array(
                'success'   => true,
                'providers' => $providers,
            ));
        }

        foreach ( $providers as $group => $items ) {
            if ( 0 === strcasecmp( (string) $group, $country ) ) {
                return rest_ensure_response( array(
                    'success'   => true,
                    'country'   => $group,
                    'providers' => array( $group => $items ),
                ));
            }
        }

        return rest_ensure_response( array(
            'success'   => true,
            'country'   => $country,
            'providers' => array(),
        ));
    }
}
