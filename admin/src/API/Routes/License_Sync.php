<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\License;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/license/sync — re-validate the stored key against MDS.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class License_Sync extends Abstract_Route {

    protected $route = '/license/sync';
    protected $methods = 'POST';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $status = License::sync();

        if ( is_wp_error( $status ) ) {
            return $this->error_response( $status->get_error_message() );
        }

        $payload = License::get_payload();

        if ( ! $status->is_valid() ) {
            $message = $status->message();

            return $this->error_response(
                '' !== $message ? $message : esc_html__( 'The license is not valid for this site.', 'hubgo' ),
                400,
                array( 'license' => $payload )
            );
        }

        return $this->success_response( array(
            'message' => esc_html__( 'License synchronized successfully!', 'hubgo' ),
            'license' => $payload,
        ) );
    }
}
