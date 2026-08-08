<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Migration_Registry;
use MeuMouse\Hubgo\Core\Woo_Shipment_Tracking_Migration;

defined('ABSPATH') || exit;

/**
 * WooCommerce Shipment Tracking integration and migration path.
 *
 * The card does two jobs, in the order a store actually needs them:
 *
 * 1. **Continuidade** — while the other plugin is still installed, HubGo reads
 *    its `_wc_shipment_tracking_items` meta and shows those shipments on every
 *    HubGo surface (metabox, conta do cliente, e-mails, Joinotify). They are
 *    flagged read-only because the records still belong to the other plugin.
 * 2. **Migração** — the modal runs {@see Woo_Shipment_Tracking_Migration} in
 *    batches, copying the data into HubGo's own meta. Migrated orders stop
 *    being bridged, so nothing is ever shown twice.
 *
 * Unlike the other integrations this card declares no plugin dependency: the
 * data outlives the plugin, and a store that already deactivated Shipment
 * Tracking must still be able to migrate. To avoid advertising a migration
 * nobody needs, the card is only registered when the plugin is active or when
 * it left something behind — see {@see self::is_relevant()}.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Woo_Shipment_Tracking extends Integrations_Base {

    /**
     * Card slug, matching the migration ID.
     *
     * @since 3.0.0
     * @var string
     */
    const CARD_SLUG = 'woo_shipment_tracking';

    /**
     * Option key toggling the integration.
     *
     * @since 3.0.0
     * @var string
     */
    const SETTING_KEY = 'enable_shipment_tracking_integration';

    /**
     * Option key toggling the read-only bridge.
     *
     * @since 3.0.0
     * @var string
     */
    const SYNC_SETTING_KEY = 'shipment_tracking_show_items';

    /**
     * Plugin basenames the extension ships under.
     *
     * @since 3.0.0
     * @var array<int,string>
     */
    const PLUGIN_FILES = array(
        'woocommerce-shipment-tracking/woocommerce-shipment-tracking.php',
        'shipment-tracking/shipment-tracking.php',
    );

    /**
     * Option Shipment Tracking writes on install and never removes, used as a
     * cheap "this store had the plugin" signal before touching the database.
     *
     * @since 3.0.0
     * @var string
     */
    const SOURCE_VERSION_OPTION = 'woocommerce_shipment_tracking_version';


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        $this->register_integration_card( 40 );

        if ( ! self::is_enabled() || ! self::is_bridge_enabled() ) {
            return;
        }

        add_filter( 'Hubgo/Tracking/Get_Items', array( $this, 'merge_source_items' ), 10, 2 );
    }


    /**
     * Register the card on the Integrations screen.
     *
     * @since 3.0.0
     * @param array $integrations Current catalog.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        if ( ! self::is_relevant() ) {
            return $integrations;
        }

        $integrations[ self::CARD_SLUG ] = array(
            'title'       => esc_html__( 'WooCommerce Shipment Tracking', 'hubgo' ),
            'description' => esc_html__( 'Exibe os rastreios do Shipment Tracking no HubGo e migra os dados dos pedidos para o rastreio nativo.', 'hubgo' ),
            'icon'        => $this->get_icon_svg(),
            'category'    => 'shipping',
            'setting_key' => self::SETTING_KEY,
            // No plugin dependency on purpose: the migration has to stay
            // available after Shipment Tracking is deactivated.
            'is_plugin'   => false,
            'doc_url'     => 'https://ajuda.meumouse.com/docs/hubgo/overview',
            'settings'    => array(
                self::field_toggle(
                    self::SYNC_SETTING_KEY,
                    esc_html__( 'Exibir rastreios do Shipment Tracking', 'hubgo' ),
                    esc_html__( 'Mostra os códigos gravados pelo outro plugin nas telas do HubGo, em modo somente leitura, enquanto a migração não é executada.', 'hubgo' )
                ),
            ),
            'modal'       => array(
                'title'       => esc_html__( 'WooCommerce Shipment Tracking', 'hubgo' ),
                'description' => esc_html__( 'Traga os códigos de rastreio já cadastrados no Shipment Tracking para o HubGo.', 'hubgo' ),
                'size'        => 'medium',
                'blocks'      => $this->get_modal_blocks(),
            ),
        );

        return $integrations;
    }


    /**
     * Content blocks rendered above the card's fields.
     *
     * @since 3.0.0
     * @return array<int,array<string,mixed>>
     */
    protected function get_modal_blocks() {
        $blocks = array(
            self::modal_notice_block(
                esc_html__( 'A migração copia os dados: nada é apagado do Shipment Tracking. Depois de migrar e conferir os pedidos, você pode desativar o outro plugin com segurança.', 'hubgo' ),
                'info'
            ),
        );

        $migration = Migration_Registry::get( Woo_Shipment_Tracking_Migration::MIGRATION_ID );

        if ( null !== $migration ) {
            $blocks[] = self::modal_migration_block( $migration->get_status() );
        }

        return $blocks;
    }


    /**
     * Show the shipments Shipment Tracking still owns on HubGo's surfaces.
     *
     * Only orders that were not migrated are bridged, so a migrated order never
     * lists the same code twice. The injected items carry `read_only` because
     * they live in the other plugin's meta: HubGo can render them, but it has
     * nothing of its own to delete.
     *
     * @since 3.0.0
     * @param array $items Tracking items resolved by HubGo.
     * @param int $order_id Order ID.
     * @return array
     */
    public function merge_source_items( $items, $order_id ) {
        $items = is_array( $items ) ? $items : array();
        $order = function_exists( 'wc_get_order' ) ? wc_get_order( absint( $order_id ) ) : false;

        if ( ! $order || Woo_Shipment_Tracking_Migration::is_order_migrated( $order ) ) {
            return $items;
        }

        $source_items = Woo_Shipment_Tracking_Migration::convert_items(
            $order->get_meta( Woo_Shipment_Tracking_Migration::SOURCE_META_KEY, true )
        );

        if ( empty( $source_items ) ) {
            return $items;
        }

        $known = array();

        foreach ( $items as $item ) {
            if ( is_array( $item ) && ! empty( $item['tracking_number'] ) ) {
                $known[] = strtolower( trim( (string) $item['tracking_number'] ) );
            }
        }

        foreach ( $source_items as $source_item ) {
            $number = strtolower( trim( (string) $source_item['tracking_number'] ) );

            if ( in_array( $number, $known, true ) ) {
                continue;
            }

            $known[] = $number;

            $source_item['tracking_id'] = 'wcst_' . md5( $source_item['source_tracking_id'] . $number );
            $source_item['read_only'] = true;
            $source_item['source_label'] = esc_html__( 'Shipment Tracking', 'hubgo' );

            $items[] = $source_item;
        }

        return $items;
    }


    /**
     * Whether the card has any reason to be listed on this store.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_relevant() {
        if ( self::is_plugin_active() ) {
            return true;
        }

        // The plugin is gone, but it may have left order data behind. The
        // option lookup is autoloaded and free; the migration count is not, so
        // it only runs for stores that once had the plugin installed.
        if ( false === get_option( self::SOURCE_VERSION_OPTION, false ) ) {
            return false;
        }

        $migration = Migration_Registry::get( Woo_Shipment_Tracking_Migration::MIGRATION_ID );

        return null !== $migration && $migration->is_available();
    }


    /**
     * Whether the Shipment Tracking plugin is installed and active.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_plugin_active() {
        return self::is_plugin_dependency_active( self::PLUGIN_FILES, true );
    }


    /**
     * Whether the integration toggle is on.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === Settings::get_setting( self::SETTING_KEY, 'no' );
    }


    /**
     * Whether the read-only bridge is on.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_bridge_enabled() {
        return 'yes' === Settings::get_setting( self::SYNC_SETTING_KEY, 'yes' );
    }


    /**
     * WooCommerce brand mark.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_icon_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="WooCommerce Shipment Tracking">'
            . '<rect x="2" y="2" width="60" height="60" rx="14" fill="#7f54b3"/>'
            . '<path d="M14 38V26a2 2 0 0 1 2-2h18a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H16a2 2 0 0 1-2-2z" fill="#ffffff"/>'
            . '<path d="M36 30h6.6a2 2 0 0 1 1.7.95L48 37v3h-12z" fill="#ffffff" opacity=".7"/>'
            . '<circle cx="22" cy="42" r="4" fill="#ffffff"/>'
            . '<circle cx="42" cy="42" r="4" fill="#ffffff"/>'
            . '</svg>';
    }
}
