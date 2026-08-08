<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\License;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/license/activate — activate a license key for this site.
 *
 * A refused key answers with `status: error` and the server's reason while
 * still returning the persisted license payload, so the screen can render the
 * new (invalid) state without a second request.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class License_Activate extends Abstract_Route {

    protected $route = '/license/activate';
    protected $methods = 'POST';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $key = trim( (string) $request->get_param( 'license_key' ) );

        if ( '' === $key ) {
            return $this->error_response( esc_html__( 'Informe a chave de licença.', 'hubgo' ) );
        }

        $status = License::activate( $key );

        if ( is_wp_error( $status ) ) {
            return $this->error_response( $status->get_error_message() );
        }

        $payload = License::get_payload();

        if ( ! $status->is_valid() ) {
            $message = $status->message();

            return $this->error_response(
                '' !== $message ? $message : esc_html__( 'Não foi possível ativar esta licença.', 'hubgo' ),
                400,
                array( 'license' => $payload )
            );
        }

        return $this->success_response( array(
            'message' => esc_html__( 'Licença ativada com sucesso!', 'hubgo' ),
            'license' => $payload,
        ) );
    }
}
