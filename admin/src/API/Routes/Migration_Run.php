<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Admin\Settings\Registry;
use MeuMouse\Hubgo\Core\Migration_Registry;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/migrations/run — process one batch of a data migration.
 *
 * The request only names a registered migration ID; how many orders that
 * migration touches and where it resumes from is decided server-side. The
 * client keeps calling the route until the returned status reports `completed`,
 * which is what keeps a large store from timing out in a single request.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Migration_Run extends Abstract_Route {

    protected $route = '/migrations/run';
    protected $methods = 'POST';


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $params = is_array( $params ) ? $params : $request->get_params();

        $id = sanitize_key( (string) ( $params['id'] ?? '' ) );

        if ( '' === $id ) {
            return $this->error_response( __( 'Tell us which migration should run.', 'hubgo' ) );
        }

        $migration = Migration_Registry::get( $id );

        if ( null === $migration ) {
            return $this->error_response( __( 'Migration not found.', 'hubgo' ), 404 );
        }

        $status = $migration->run_batch( isset( $params['limit'] ) ? (int) $params['limit'] : 0 );

        if ( is_wp_error( $status ) ) {
            return $this->error_response( $status->get_error_message() );
        }

        return $this->success_response( array(
            'migration' => $status,
            'message'   => $this->get_progress_message( $status ),
            // The catalog carries the migration block, so the screen can repaint
            // the card with the refreshed counters once the run finishes.
            'cards'     => ! empty( $status['completed'] ) ? Registry::get_integration_cards() : array(),
        ) );
    }


    /**
     * Human-readable summary of a migration run.
     *
     * @since 3.0.0
     * @param array $status Migration status payload.
     * @return string
     */
    private function get_progress_message( $status ) {
        if ( empty( $status['completed'] ) ) {
            /* translators: 1: processed orders, 2: total orders. */
            return sprintf(
                __( 'Migrating orders... %1$d of %2$d.', 'hubgo' ),
                (int) $status['processed'],
                (int) $status['total']
            );
        }

        /* translators: 1: number of tracking codes, 2: number of orders. */
        return sprintf(
            __( 'Migration completed: %1$d tracking code(s) across %2$d order(s).', 'hubgo' ),
            (int) $status['imported_records'],
            (int) $status['migrated_orders']
        );
    }
}
