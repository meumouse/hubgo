<?php

namespace MeuMouse\Hubgo\Core;

use MeuMouse\Hubgo\Core\Delivery_Promise;
use MeuMouse\Hubgo\Core\Order_Status;

use WC_Abstract_Order;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Watches shipped orders whose promised delivery date has passed.
 *
 * The promise stored by {@see Delivery_Promise} is only worth keeping if someone
 * notices when it breaks. A daily pass flags every shipped order past its date
 * and fires `Hubgo/Delivery/Overdue` once per order — the Joinotify integration
 * turns that into a WhatsApp message, and any other listener can do its own
 * thing with it.
 *
 * Two properties keep the pass safe on a large store: it is batched, so a
 * backlog is drained over several days instead of in one request that times
 * out; and it stamps the order before firing, so an order can never be
 * announced twice even if the same batch is processed again.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Delivery_Watcher {

    /**
     * Cron hook running the daily pass.
     *
     * @since 3.0.0
     * @var string
     */
    const CRON_HOOK = 'hubgo_check_delivery_promises';

    /**
     * Order meta stamped once an order has been reported as overdue.
     *
     * @since 3.0.0
     * @var string
     */
    const NOTIFIED_META = '_hubgo_delivery_overdue_notified';

    /**
     * Orders processed per pass.
     *
     * @since 3.0.0
     * @var int
     */
    const BATCH_SIZE = 50;


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        add_action( self::CRON_HOOK, array( $this, 'run' ) );

        $this->maybe_schedule();
    }


    /**
     * Make sure the daily pass is scheduled.
     *
     * @since 3.0.0
     * @return void
     */
    private function maybe_schedule() {
        if ( wp_next_scheduled( self::CRON_HOOK ) ) {
            return;
        }

        // An hour out rather than immediately: an activation (or an update)
        // should not spend its first request walking the order table.
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
    }


    /**
     * Drop the scheduled pass.
     *
     * Called from the plugin deactivation hook so a disabled plugin leaves no
     * orphan cron entry behind.
     *
     * @since 3.0.0
     * @return void
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }


    /**
     * Report every shipped order past its promised delivery date.
     *
     * @since 3.0.0
     * @return int Number of orders reported.
     */
    public function run() {
        /**
         * Filters whether the overdue delivery pass runs at all.
         *
         * @since 3.0.0
         * @param bool $enabled Whether to run the pass.
         */
        if ( ! apply_filters( 'Hubgo/Delivery/Overdue_Enabled', true ) ) {
            return 0;
        }

        if ( ! function_exists( 'wc_get_orders' ) ) {
            return 0;
        }

        $grace = self::get_grace_days();
        $orders = $this->get_overdue_orders( $grace );
        $reported = 0;

        foreach ( $orders as $order ) {
            if ( ! $order instanceof WC_Abstract_Order ) {
                continue;
            }

            // The cutoff below is a coarse date filter; this is the check that
            // actually honours the grace period and the site timezone.
            if ( ! Delivery_Promise::is_overdue( $order, $grace ) ) {
                continue;
            }

            // Stamp before dispatching: a listener that fatals must not leave
            // the order eligible for a second notification on the next pass.
            $order->update_meta_data( self::NOTIFIED_META, current_time('mysql') );
            $order->save();

            $reported++;

            /**
             * Fired once for a shipped order whose promised delivery date passed.
             *
             * @since 3.0.0
             * @param int $order_id Order ID.
             * @param array<string,mixed> $promise Promise stored on the order.
             */
            do_action( 'Hubgo/Delivery/Overdue', $order->get_id(), Delivery_Promise::get( $order ) );
        }

        return $reported;
    }


    /**
     * Query the next batch of candidate orders.
     *
     * The date comparison stays a plain string compare: the promise is stored as
     * `Y-m-d`, which sorts lexicographically, so no CAST is needed and the query
     * behaves identically under HPOS and the legacy post storage.
     *
     * @since 3.0.0
     * @param int $grace_days Days of tolerance beyond the promise.
     * @return array<int,WC_Abstract_Order>
     */
    private function get_overdue_orders( $grace_days ) {
        $cutoff = current_datetime()
            ->setTime( 0, 0, 0 )
            ->modify( '-' . max( 0, (int) $grace_days ) . ' days' )
            ->format('Y-m-d');

        $args = array(
            'limit'      => self::BATCH_SIZE,
            'status'     => Order_Status::STATUS,
            'return'     => 'objects',
            'orderby'    => 'date',
            'order'      => 'ASC',
            'meta_query' => array(
                array(
                    'key'     => Delivery_Promise::META_DATE,
                    'value'   => $cutoff,
                    'compare' => '<',
                ),
                array(
                    'key'     => self::NOTIFIED_META,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );

        /**
         * Filters the query used to find overdue deliveries.
         *
         * @since 3.0.0
         * @param array $args Arguments passed to wc_get_orders().
         * @param string $cutoff Promise date the query compares against (Y-m-d).
         */
        $args = apply_filters( 'Hubgo/Delivery/Overdue_Query', $args, $cutoff );

        $orders = wc_get_orders( $args );

        return is_array( $orders ) ? $orders : array();
    }


    /**
     * Days of tolerance before an order counts as late.
     *
     * One day by default: a parcel delivered on the promised date is frequently
     * only scanned the morning after, and a notification that arrives before the
     * customer has checked their door creates the very support ticket it was
     * meant to prevent.
     *
     * @since 3.0.0
     * @return int
     */
    public static function get_grace_days() {
        /**
         * Filters the grace period applied before reporting a late delivery.
         *
         * @since 3.0.0
         * @param int $days Days of tolerance beyond the promised date.
         */
        return max( 0, (int) apply_filters( 'Hubgo/Delivery/Overdue_Grace_Days', 1 ) );
    }
}
