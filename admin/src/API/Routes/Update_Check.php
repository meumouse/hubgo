<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Core\Update_Checker;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/updates/check — force a fresh update check against MDS.
 *
 * Backs the "Check for updates" link on the plugins list, which runs it without
 * leaving the screen.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Update_Check extends Abstract_Route {

    protected $route = '/updates/check';
    protected $methods = 'POST';


    /**
     * Checking (and then installing) an update is an `update_plugins` matter,
     * not a WooCommerce one, so this route does not use the shared settings
     * capability.
     *
     * @since 3.0.0
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permission( WP_REST_Request $request ) {
        return Update_Checker::can_check();
    }


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $result = Update_Checker::check();

        if ( is_wp_error( $result ) ) {
            return $this->error_response( $result->get_error_message() );
        }

        return $this->success_response( $result );
    }
}
