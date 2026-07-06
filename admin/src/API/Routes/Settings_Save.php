<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Admin\Settings\Repository;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/settings — persist settings.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Settings_Save extends Abstract_Route {

    protected $route = '/settings';
    protected $methods = 'POST';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        $settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : array();

        $saved = Repository::save_settings( $settings );

        return $this->success_response( array(
            'message'  => __( 'Configurações salvas com sucesso!', 'hubgo' ),
            'settings' => $saved,
        ) );
    }
}
