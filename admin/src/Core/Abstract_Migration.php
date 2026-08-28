<?php

namespace MeuMouse\Hubgo\Core;

use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Base class for one-shot data migrations from another plugin into HubGo.
 *
 * A migration knows three things: how many orders hold the source data, how to
 * page through them and how to convert a single order. Everything else —
 * batching, progress bookkeeping and the payload the Integrations screen
 * renders — lives here, so a new migration is one small subclass.
 *
 * Progress is stored in a single option keyed by migration ID and is only a
 * *resume pointer*: the real idempotency guarantee belongs to the subclass,
 * which must be able to run over an already-migrated order without
 * duplicating anything. Losing the option therefore costs time, never data.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
abstract class Abstract_Migration {

    /**
     * Option holding the progress state of every migration.
     *
     * @since 3.0.0
     * @var string
     */
    const STATE_OPTION = 'hubgo_migrations_state';

    /**
     * Default number of orders processed per request.
     *
     * @since 3.0.0
     * @var int
     */
    const DEFAULT_BATCH_SIZE = 20;

    /**
     * Largest batch a client may ask for, so one request cannot time out the
     * whole screen on a store with heavy order meta.
     *
     * @since 3.0.0
     * @var int
     */
    const MAX_BATCH_SIZE = 100;


    /**
     * Unique migration identifier.
     *
     * @since 3.0.0
     * @return string
     */
    abstract public function get_id();


    /**
     * Human-readable migration title.
     *
     * @since 3.0.0
     * @return string
     */
    abstract public function get_title();


    /**
     * Whether the source data can be reached right now.
     *
     * @since 3.0.0
     * @return bool
     */
    abstract public function is_available();


    /**
     * Total number of orders holding source data.
     *
     * @since 3.0.0
     * @return int
     */
    abstract public function count_source_orders();


    /**
     * Page through the orders holding source data.
     *
     * @since 3.0.0
     * @param int $limit | Maximum number of IDs to return.
     * @param int $offset | Number of orders to skip.
     * @return array<int,int> Order IDs.
     */
    abstract public function get_source_order_ids( $limit, $offset );


    /**
     * Convert a single order.
     *
     * @since 3.0.0
     * @param int $order_id | Order ID.
     * @return int Number of records imported for this order (0 when skipped).
     */
    abstract public function migrate_order( $order_id );


    /**
     * Short description rendered next to the progress bar.
     *
     * @since 3.0.0
     * @return string
     */
    public function get_description() {
        return '';
    }


    /**
     * Run one batch and return the refreshed status.
     *
     * @since 3.0.0
     * @param int $limit | Requested batch size.
     * @return array<string,mixed>|WP_Error
     */
    public function run_batch( $limit = self::DEFAULT_BATCH_SIZE ) {
        if ( ! $this->is_available() ) {
            return new WP_Error(
                'hubgo_migration_unavailable',
                __( 'The source data for this migration is not available.', 'hubgo' )
            );
        }

        $limit = $this->clamp_batch_size( $limit );
        $state = $this->get_state();
        $order_ids = $this->get_source_order_ids( $limit, (int) $state['offset'] );
        $imported = 0;
        $migrated_orders = 0;

        foreach ( $order_ids as $order_id ) {
            $records = (int) $this->migrate_order( (int) $order_id );

            if ( $records > 0 ) {
                ++$migrated_orders;
                $imported += $records;
            }
        }

        $state['offset'] += count( $order_ids );
        $state['migrated_orders'] += $migrated_orders;
        $state['imported_records'] += $imported;

        if ( '' === $state['started_at'] ) {
            $state['started_at'] = current_time( 'mysql' );
        }

        // A short page means the source list is exhausted. The total is checked
        // as well because a batch can come back full and still be the last one.
        if ( count( $order_ids ) < $limit || $state['offset'] >= $this->count_source_orders() ) {
            $state['completed_at'] = current_time( 'mysql' );
        }

        $this->update_state( $state );

        /**
         * Fired after a migration batch is processed.
         *
         * @since 3.0.0
         * @param string $id | Migration ID.
         * @param array $state | Persisted progress state.
         * @param array $order_ids | Orders processed in this batch.
         */
        do_action( 'Hubgo/Migrations/Batch_Processed', $this->get_id(), $state, $order_ids );

        return $this->get_status();
    }


    /**
     * Status payload consumed by the Integrations screen.
     *
     * @since 3.0.0
     * @return array<string,mixed>
     */
    public function get_status() {
        $available = $this->is_available();
        $state = $this->get_state();
        $total = $available ? $this->count_source_orders() : 0;
        $processed = min( (int) $state['offset'], $total );

        return array(
            'id'               => $this->get_id(),
            'title'            => $this->get_title(),
            'description'      => $this->get_description(),
            'available'        => $available,
            'total'            => $total,
            'processed'        => $processed,
            'pending'          => max( 0, $total - $processed ),
            'migrated_orders'  => (int) $state['migrated_orders'],
            'imported_records' => (int) $state['imported_records'],
            'started_at'       => (string) $state['started_at'],
            'completed_at'     => (string) $state['completed_at'],
            'completed'        => '' !== (string) $state['completed_at'] && $processed >= $total,
            'batch_size'       => self::DEFAULT_BATCH_SIZE,
        );
    }


    /**
     * Forget the progress pointer so the next run starts from the first order.
     *
     * @since 3.0.0
     * @return void
     */
    public function reset_state() {
        $states = $this->get_all_states();

        unset( $states[ $this->get_id() ] );

        update_option( self::STATE_OPTION, $states, false );
    }


    /**
     * Progress state of this migration, with every key filled.
     *
     * @since 3.0.0
     * @return array<string,mixed>
     */
    protected function get_state() {
        $states = $this->get_all_states();
        $state = isset( $states[ $this->get_id() ] ) && is_array( $states[ $this->get_id() ] )
            ? $states[ $this->get_id() ]
            : array();

        return wp_parse_args( $state, array(
            'offset'           => 0,
            'migrated_orders'  => 0,
            'imported_records' => 0,
            'started_at'       => '',
            'completed_at'     => '',
        ) );
    }


    /**
     * Persist the progress state of this migration.
     *
     * @since 3.0.0
     * @param array $state | New state.
     * @return void
     */
    protected function update_state( $state ) {
        $states = $this->get_all_states();
        $states[ $this->get_id() ] = $state;

        // Not autoloaded: this option is only read on the Integrations screen.
        update_option( self::STATE_OPTION, $states, false );
    }


    /**
     * Every migration state stored in the option.
     *
     * @since 3.0.0
     * @return array<string,array<string,mixed>>
     */
    protected function get_all_states() {
        $states = get_option( self::STATE_OPTION, array() );

        return is_array( $states ) ? $states : array();
    }


    /**
     * Keep a client-supplied batch size inside the supported range.
     *
     * @since 3.0.0
     * @param int $limit | Requested batch size.
     * @return int
     */
    protected function clamp_batch_size( $limit ) {
        $limit = (int) $limit;

        if ( $limit < 1 ) {
            $limit = self::DEFAULT_BATCH_SIZE;
        }

        return min( $limit, self::MAX_BATCH_SIZE );
    }
}
