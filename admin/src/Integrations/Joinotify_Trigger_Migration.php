<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Joinotify\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * One-time repair of Joinotify workflows built against HubGo 3.0.x.
 *
 * Until 3.1.0 the `data_trigger` HubGo registered was the HubGo hook name itself
 * (`Hubgo/Tracking/Item_Saved`). Joinotify runs that slug through `sanitize_key()`
 * when the builder creates a workflow from it, so what reached the database was
 * `hubgotrackingitem_saved` while HubGo went on dispatching the original. The two
 * never matched again: the workflow never ran, the builder offered none of the
 * HubGo placeholders and the condition node had no conditions to offer.
 *
 * 3.1.0 fixes the slugs. This class fixes the workflows already saved with the old
 * ones, rewriting the stored trigger to its canonical slug so a flow drawn before
 * the upgrade starts working without being redrawn.
 *
 * **Why this does not extend {@see \MeuMouse\Hubgo\Core\Abstract_Migration}.** That
 * base class models the other kind of migration this plugin performs: a
 * user-driven, batched import of another plugin's data *into* HubGo, paged over
 * orders through `POST hubgo/v1/migrations/run` and surfaced as a progress bar on
 * the Integrations screen. This is neither — it is a silent, one-shot correction
 * of HubGo's own past output, over a handful of posts, with nothing for the user
 * to start or watch.
 *
 * Three rules hold it inside its lane:
 *
 * - It only ever rewrites a trigger node whose context is HubGo's and whose slug
 *   is one this class itself emitted. Anything else is left untouched.
 * - It is idempotent by construction: a canonical slug is not in the legacy map,
 *   so a second pass over a repaired workflow matches nothing. The option is a
 *   short-circuit, not the correctness guarantee.
 * - It fires no Joinotify hook and touches no other field, so repairing a
 *   workflow can never send a message.
 *
 * @since 3.1.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Joinotify_Trigger_Migration {

    /**
     * Option marking the repair as done.
     *
     * @since 3.1.0
     * @var string
     */
    const OPTION = 'hubgo_joinotify_trigger_migration';

    /**
     * Post type holding Joinotify workflows.
     *
     * @since 3.1.0
     * @var string
     */
    const POST_TYPE = 'joinotify-workflow';

    /**
     * Meta key Joinotify indexes a workflow's trigger under.
     *
     * @since 3.1.0
     * @var string
     */
    const INDEX_META = '_joinotify_trigger_hook';

    /**
     * Legacy slug => canonical slug.
     *
     * @since 3.1.0
     * @var array<string,string>
     */
    protected $map = array();


    /**
     * Constructor.
     *
     * @since 3.1.0
     * @param array<string,string> $map Legacy slug => canonical trigger slug.
     */
    public function __construct( $map ) {
        $this->map = is_array( $map ) ? $map : array();
    }


    /**
     * Build the legacy => canonical map from a trigger/hook table.
     *
     * Both shapes a 3.0.x workflow may hold are covered: the hook name verbatim
     * (what HubGo registered) and its `sanitize_key()` form (what the builder
     * actually stored). A hook that already sanitizes to its own trigger slug
     * contributes nothing, so the map can never rewrite a canonical value.
     *
     * @since 3.1.0
     * @param array<string,string> $trigger_hooks Trigger slug => HubGo hook name.
     * @return array<string,string>
     */
    public static function build_map( $trigger_hooks ) {
        $map = array();

        foreach ( (array) $trigger_hooks as $trigger => $hook ) {
            $trigger = (string) $trigger;
            $hook = (string) $hook;

            if ( '' === $trigger || '' === $hook ) {
                continue;
            }

            foreach ( array( $hook, sanitize_key( $hook ) ) as $legacy ) {
                if ( '' !== $legacy && $legacy !== $trigger ) {
                    $map[ $legacy ] = $trigger;
                }
            }
        }

        return $map;
    }


    /**
     * Whether the repair still has to run.
     *
     * @since 3.1.0
     * @return bool
     */
    public function is_pending() {
        return ! empty( $this->map ) && 'done' !== get_option( self::OPTION, '' );
    }


    /**
     * Run the repair once, then mark it done.
     *
     * The marker is written even when nothing matched — on a site that never had a
     * 3.0.x workflow there is nothing to find on the next request either.
     *
     * @since 3.1.0
     * @return int Number of workflows repaired.
     */
    public function run() {
        if ( ! $this->is_pending() ) {
            return 0;
        }

        // Claim the run before doing the work, so two concurrent requests cannot
        // both walk the posts. The rewrite is idempotent, so the worst case of a
        // lost race is a workflow repaired on the next upgrade instead of now.
        update_option( self::OPTION, 'done', false );

        $repaired = 0;

        foreach ( $this->get_candidates() as $post_id ) {
            $repaired += $this->repair( $post_id ) ? 1 : 0;
        }

        /**
         * Fires after the 3.0.x Joinotify workflow triggers have been repaired.
         *
         * @since 3.1.0
         * @param int $repaired Number of workflows rewritten.
         * @param array<string,string> $map Legacy slug => canonical trigger slug.
         */
        do_action( 'Hubgo/Integrations/Joinotify/Triggers_Migrated', $repaired, $this->map );

        return $repaired;
    }


    /**
     * Workflow IDs worth inspecting.
     *
     * Every status, not just `publish`: a draft is exactly the workflow a store is
     * still building and the one most likely to carry a stale slug.
     *
     * @since 3.1.0
     * @return array<int,int>
     */
    protected function get_candidates() {
        if ( ! post_type_exists( self::POST_TYPE ) ) {
            return array();
        }

        $posts = get_posts( array(
            'post_type'        => self::POST_TYPE,
            'post_status'      => 'any',
            'numberposts'      => -1,
            'fields'           => 'ids',
            'suppress_filters' => false,
            'no_found_rows'    => true,
        ) );

        return is_array( $posts ) ? array_map( 'absint', $posts ) : array();
    }


    /**
     * Rewrite one workflow's trigger node, if it holds a legacy HubGo slug.
     *
     * @since 3.1.0
     * @param int $post_id Workflow post ID.
     * @return bool Whether the workflow was rewritten.
     */
    protected function repair( $post_id ) {
        if ( ! function_exists( 'joinotify_get_workflow_content' ) || ! class_exists( Helpers::class ) ) {
            return false;
        }

        $content = joinotify_get_workflow_content( $post_id );

        if ( ! is_array( $content ) || empty( $content ) ) {
            return false;
        }

        $canonical = '';

        foreach ( $content as $index => $node ) {
            if ( ! is_array( $node ) || ! isset( $node['type'] ) || 'trigger' !== $node['type'] ) {
                continue;
            }

            $data = isset( $node['data'] ) && is_array( $node['data'] ) ? $node['data'] : array();
            $context = isset( $data['context'] ) ? (string) $data['context'] : '';
            $trigger = isset( $data['trigger'] ) ? (string) $data['trigger'] : '';

            // Another integration's workflow, or a slug this class never emitted.
            if ( Joinotify::SLUG !== $context || ! isset( $this->map[ $trigger ] ) ) {
                continue;
            }

            $canonical = $this->map[ $trigger ];
            $content[ $index ]['data']['trigger'] = $canonical;
        }

        if ( '' === $canonical ) {
            return false;
        }

        Helpers::update_workflow_content_meta( $post_id, $content );

        // Keep Joinotify's queryable index in step with the content it mirrors:
        // `Workflow_Processor::get_workflows_by_hook()` reads this meta first and
        // only falls back to a content LIKE when it is absent, so a stale value
        // here would keep the workflow invisible to the dispatch it now matches.
        update_post_meta( $post_id, self::INDEX_META, $canonical );

        return true;
    }
}
