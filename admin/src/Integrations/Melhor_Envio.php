<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Delivery_Promise;
use MeuMouse\Hubgo\Core\Order_Status;
use MeuMouse\Hubgo\Core\Tracking_Manager;
use MeuMouse\Hubgo\Views\Shipping_Calculator;

use MelhorEnvio\Factory\ProductServiceFactory;
use MelhorEnvio\Services\Products\ProductsService;

use WC_Product;
use WC_Shipping_Rate;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Melhor Envio integration, backed by the official "Melhor Envio" plugin.
 *
 * The plugin is distributed on wordpress.org, so the card ships a package URL
 * and the Integrations screen can install and activate it in one click through
 * {@see \MeuMouse\Hubgo\Core\Plugin_Installer}. There is no MeuMouse add-on to
 * wait for and no license to buy: the card is free, like Frenet's, because what
 * it does is keep two plugins the store already runs from contradicting each
 * other.
 *
 * Everything below reaches the gateway through hooks and public API only —
 * patching a plugin HubGo installs would be erased by its next update:
 *
 * - **Quote payload.** `CalculateShippingMethodService::calculateShipping()`
 *   prices the shopper's cart unless the package announces itself as a product
 *   page quote (`product_page_calculation`), in which case it reads a
 *   pre-normalized product payload from `contents[…]['formatted_data']`. HubGo's
 *   calculator quotes one product line with no cart behind it and shipped
 *   neither key, so the gateway either priced the wrong basket (cart not empty)
 *   or dereferenced a missing key and brought the request down (cart empty).
 *   {@see self::prepare_package()} fills both, using the gateway's own factory.
 *   This one runs for every store with the gateway active — a crash guard is
 *   not something a toggle should have to be found and switched on to get.
 * - **Carrier.** Melhor Envio is a reseller: the parcel is moved by Correios,
 *   Jadlog, Loggi or Azul, and the gateway already knows which — it just
 *   publishes it under `company`. It is copied to the conventional `carrier`
 *   key so a notification names the carrier instead of the reseller.
 * - **Tracking.** The code generated when the label is paid lands in the
 *   gateway's own order meta and nowhere else. It is imported into HubGo's
 *   tracking as it appears, which is what makes the e-mails, the customer
 *   account and the Joinotify automation see the shipment.
 * - **Duplicate calculator.** The gateway prints its own shipping simulator on
 *   the product page. With HubGo's calculator on, the page rendered two.
 * - **Bundles and composites.** The gateway refuses to quote them outside the
 *   cart — its own simulator prints "a cotação do frete desse produto deve ser
 *   feita no carrinho" — because the payload it builds for a single product
 *   carries the kit's own (usually empty) dimensions and none of its parts.
 *   Quoting one anyway is not a smaller answer, it is a wrong price, so those
 *   rates are dropped from HubGo's calculator instead.
 *   {@see self::has_composition_product()}
 * - **Duplicated forecast.** The gateway bakes the delivery window into the
 *   rate label ("Correios Pac (3 a 5 dias úteis)") on top of publishing it as
 *   meta. HubGo's calculator already turns that meta into a promised date, and
 *   the two disagree as soon as the store sets a handling time, so the
 *   parenthetical is stripped from the label — in HubGo's own packages only.
 *
 * The delivery forecast needs no bridge: the gateway writes it as rate meta
 * under `delivery_time` ("(3 a 5 dias úteis)"), which is one of the keys
 * {@see \MeuMouse\Hubgo\Core\Delivery_Estimate} already reads, upper bound and
 * all.
 *
 * @since 3.0.0
 * @version 3.1.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Melhor_Envio extends Integrations_Base {

    /**
     * Card slug.
     *
     * @since 3.0.0
     * @var string
     */
    const CARD_SLUG = 'melhor_envio';

    /**
     * Option key toggling the integration.
     *
     * @since 3.0.0
     * @var string
     */
    const SETTING_KEY = 'enable_melhor_envio_integration';

    /**
     * Plugin basename of the official plugin.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @var string
     */
    const PLUGIN_FILE = 'melhor-envio-cotacao/melhor-envio-beta.php';

    /**
     * Every basename the official plugin is known to ship under.
     *
     * The wordpress.org slug is `melhor-envio-cotacao`; a store that installed
     * the package by hand often ends up with the shorter folder name.
     *
     * @since 3.0.0
     * @var array<int,string>
     */
    const PLUGIN_FILES = array(
        'melhor-envio-cotacao/melhor-envio-beta.php',
        'melhor-envio/melhor-envio-beta.php',
    );

    /**
     * Package URL served by wordpress.org.
     *
     * @since 3.0.0
     * @var string
     */
    const PACKAGE_URL = 'https://downloads.wordpress.org/plugin/melhor-envio-cotacao.zip';

    /**
     * Prefix shared by every shipping method id the gateway registers
     * (`melhorenvio_correios_pac`, `melhorenvio_jadlog_package`, …).
     *
     * @since 3.0.0
     * @var string
     */
    const METHOD_PREFIX = 'melhorenvio_';

    /**
     * Provider name this integration publishes to the tracking registry.
     *
     * @since 3.0.0
     * @var string
     */
    const PROVIDER_NAME = 'Melhor Envio';

    /**
     * Order meta the gateway stores the tracking code under.
     *
     * @since 3.0.0
     * @var string
     */
    const SOURCE_META_KEY = 'melhorenvio_tracking';

    /**
     * Order meta flagging that the shipped status was already applied, so a
     * shop manager who moves the order back is not overruled on the next save.
     *
     * @since 3.0.0
     * @var string
     */
    const SHIPPED_META_KEY = '_hubgo_melhor_envio_shipped_at';

    /**
     * Source identifier stored on the tracking items this integration imports.
     *
     * @since 3.0.0
     * @var string
     */
    const SOURCE_ID = 'melhor_envio';

    /**
     * Tracking URL template. %1$s receives the url-encoded tracking code, the
     * same placeholder contract every entry in Providers_Registry uses.
     *
     * @since 3.0.0
     * @var string
     */
    const DEFAULT_TRACKING_URL = 'https://melhorrastreio.com.br/rastreio/%1$s';

    /**
     * Orders currently being synchronised, by ID.
     *
     * The sync runs on `woocommerce_update_order` and both of its steps save
     * the order again; without this the handler would re-enter itself.
     *
     * @since 3.0.0
     * @var array<int,bool>
     */
    private static $syncing = array();


    /**
     * Constructor.
     *
     * @since 3.0.0
     * @version 3.1.0
     */
    public function __construct() {
        $this->register_integration_card( 20 );

        if ( ! self::is_available() ) {
            return;
        }

        // Registered before the toggle and the license are consulted, unlike
        // everything below it: an unprepared package does not degrade the quote,
        // it takes the calculator request down with it (see prepare_package()).
        // A store that never enabled this integration still has HubGo's own
        // calculator to keep working.
        add_filter( 'Hubgo/Shipping_Calculator/Package', array( $this, 'prepare_package' ) );

        if ( ! self::is_enabled() ) {
            return;
        }

        add_filter( 'Hubgo/Tracking/Get_Providers', array( $this, 'register_provider' ) );

        // Priority 20 so the rates are already assembled (and any zone-level
        // filtering has run) before the carrier is attached to them.
        add_filter( 'woocommerce_package_rates', array( $this, 'decorate_rates' ), 20, 2 );

        // Both toggles are fed by the same order save, but they are independent
        // choices on the card: a store that only wants the status moved must
        // not have to import the code as well to get it.
        if ( self::syncs_tracking() || self::marks_as_shipped() ) {
            add_action( 'woocommerce_update_order', array( $this, 'sync_order' ), 20 );
        }

        if ( self::syncs_tracking() ) {
            add_filter( 'Hubgo/Tracking/Get_Items', array( $this, 'merge_source_items' ), 10, 2 );
        }

        if ( self::disables_native_simulator() ) {
            add_action( 'wp', array( $this, 'unhook_native_simulator' ), 20 );
            add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_native_simulator_assets' ), 100 );
        }

        /**
         * Runtime bridge with the Melhor Envio plugin.
         *
         * Fired once both plugins are present and the integration is on, so a
         * third party can hook the same events without repeating the dependency
         * checks.
         *
         * @since 3.0.0
         * @param Melhor_Envio $integration Integration instance.
         */
        do_action( 'Hubgo/Integrations/Melhor_Envio/Booted', $this );
    }


    /**
     * Register the card on the Integrations screen.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @param array $integrations Current catalog.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $package_url = self::get_package_url();

        $integrations[ self::CARD_SLUG ] = array(
            'title'            => __( 'Melhor Envio', 'hubgo' ),
            'description'      => __( 'Use Melhor Envio quotes in the shipping calculation and bring the tracking code generated on the label into the order.', 'hubgo' ),
            'icon'             => $this->get_icon_svg(),
            'author'           => 'Melhor Envio',
            'author_url'       => 'https://melhorenvio.com.br',
            'category'         => 'shipping',
            'setting_key'      => self::SETTING_KEY,
            'is_plugin'        => true,
            'plugin_active'    => self::get_plugin_files(),
            'coming_soon'      => '' === $package_url,
            'doc_url'          => 'https://wordpress.org/plugins/melhor-envio-cotacao/',
            'install'          => array(
                'plugin_slug'  => self::get_plugin_file(),
                'download_url' => $package_url,
                'label'        => __( 'Install Melhor Envio', 'hubgo' ),
            ),
            'settings'         => array(
                self::field_toggle(
                    'melhor_envio_sync_tracking',
                    __( 'Import tracking', 'hubgo' ),
                    __( 'Brings the tracking code generated in Melhor Envio into the order as soon as the label is paid.', 'hubgo' )
                ),
                self::field_toggle(
                    'melhor_envio_mark_as_shipped',
                    __( 'Mark as shipped', 'hubgo' ),
                    __( 'Moves the order to the "Order shipped" status the first time a Melhor Envio tracking code reaches it.', 'hubgo' )
                ),
                self::field_toggle(
                    'melhor_envio_disable_simulator',
                    __( 'Hide the Melhor Envio simulator', 'hubgo' ),
                    __( 'Removes the shipping simulator the Melhor Envio plugin prints on the product page, so only the HubGo calculator is shown.', 'hubgo' )
                ),
                self::field_text(
                    'melhor_envio_tracking_url',
                    __( 'Tracking URL', 'hubgo' ),
                    __( 'Address used in the tracking link. Use %1$s where the code should go.', 'hubgo' ),
                    array( 'placeholder' => self::DEFAULT_TRACKING_URL )
                ),
            ),
            'modal'            => array(
                'title'       => __( 'Melhor Envio', 'hubgo' ),
                'description' => __( 'How HubGo reads the shipping data generated by Melhor Envio.', 'hubgo' ),
                'size'        => 'medium',
                'blocks'      => array(
                    self::modal_notice_block(
                        __( 'HubGo quotes Melhor Envio for the product being viewed instead of the shopper\'s cart, and reads the delivery time and the carrier straight from the quote, so the product page can promise a delivery date.', 'hubgo' ),
                        'info'
                    ),
                    self::modal_notice_block(
                        __( 'Importing tracking depends on Melhor Envio having synchronized the label: the code appears on the order once the shipment is paid and the plugin has fetched its data.', 'hubgo' ),
                        'warning'
                    ),
                ),
            ),
        );

        return $integrations;
    }


    /**
     * Add Melhor Envio to the Brazilian tracking providers list.
     *
     * The group keys are internal identifiers translated for display by
     * Providers_Registry::get_country_label(), so 'Brazil' is used verbatim.
     *
     * @since 3.0.0
     * @param array $providers Current provider groups.
     * @return array
     */
    public function register_provider( $providers ) {
        if ( ! is_array( $providers ) ) {
            $providers = array();
        }

        $url = (string) Settings::get_setting( 'melhor_envio_tracking_url', '' );
        $providers['Brazil'][ self::PROVIDER_NAME ] = '' !== trim( $url ) ? $url : self::DEFAULT_TRACKING_URL;

        return $providers;
    }


    /**
     * Teach the gateway to price the product being viewed, not the cart.
     *
     * `CalculateShippingMethodService::calculateShipping()` only skips the cart
     * when the package carries `product_page_calculation`, and then expects each
     * line to bring its own normalized payload under `formatted_data` — the
     * shape the gateway's own product-page calculator builds. Both are produced
     * here through `ProductServiceFactory`, so bundles and composites are
     * normalized exactly as the gateway would normalize them.
     *
     * The flag is only set once every line was converted: a package that is
     * half-converted would make the gateway read a key that is not there, which
     * is the failure this whole method exists to prevent.
     *
     * Bundles and composites are converted like anything else — the point here
     * is that the gateway survives the request. Their quote is unusable for a
     * different reason and is discarded later, in
     * {@see self::has_composition_product()}.
     *
     * @since 3.0.0
     * @param array $package Shipping package built by the calculator.
     * @return array
     */
    public function prepare_package( $package ) {
        if ( ! is_array( $package ) || empty( $package['hubgo_calculator'] ) || empty( $package['contents'] ) ) {
            return $package;
        }

        if ( ! class_exists( ProductServiceFactory::class ) ) {
            return $package;
        }

        $converted = true;

        foreach ( $package['contents'] as $key => $content ) {
            if ( isset( $content['formatted_data'] ) ) {
                continue;
            }

            $product = isset( $content['data'] ) ? $content['data'] : null;

            if ( ! $product instanceof WC_Product ) {
                $converted = false;
                continue;
            }

            $quantity = max( 1, absint( isset( $content['quantity'] ) ? $content['quantity'] : 1 ) );

            try {
                // get_id() is the variation ID when a variation is being
                // quoted, which is what carries the dimensions and the price.
                $formatted = ProductServiceFactory::fromProduct( $product )->getProduct( $product->get_id(), $quantity );
            } catch ( Throwable $error ) {
                $formatted = null;
            }

            if ( empty( $formatted ) ) {
                $converted = false;
                continue;
            }

            $package['contents'][ $key ]['formatted_data'] = $formatted;
        }

        if ( $converted ) {
            $package['product_page_calculation'] = true;
        }

        return $package;
    }


    /**
     * Attach the carrier to every Melhor Envio rate, and clean up what the
     * gateway's rates carry that HubGo's calculator renders on its own.
     *
     * The carrier is written under `carrier` — the conventional key, not a
     * Melhor Envio one — because WooCommerce copies rate meta onto the order
     * line item, and {@see Delivery_Promise} then reads the data without knowing
     * which gateway produced it. That part applies everywhere, checkout
     * included.
     *
     * The other two only touch packages HubGo built: a quote for a kit is
     * dropped rather than shown wrong, and the delivery window the gateway
     * repeats inside the label is removed because the calculator prints the
     * promised date next to it.
     *
     * @since 3.0.0
     * @param array $rates Rates keyed by rate ID.
     * @param array $package Shipping package.
     * @return array
     */
    public function decorate_rates( $rates, $package ) {
        if ( ! is_array( $rates ) ) {
            return $rates;
        }

        $is_calculator = is_array( $package ) && ! empty( $package['hubgo_calculator'] );
        $drop = $is_calculator && self::has_composition_product( $package );

        foreach ( $rates as $rate_id => $rate ) {
            if ( ! $rate instanceof WC_Shipping_Rate || ! self::is_melhor_envio_method( $rate->get_method_id() ) ) {
                continue;
            }

            if ( $drop ) {
                unset( $rates[ $rate_id ] );
                continue;
            }

            $meta = (array) $rate->get_meta_data();

            if ( ! empty( $meta['company'] ) && is_scalar( $meta['company'] ) && ! isset( $meta['carrier'] ) ) {
                $rate->add_meta_data( 'carrier', sanitize_text_field( (string) $meta['company'] ) );
            }

            if ( $is_calculator ) {
                $rate->set_label( self::strip_delivery_window( $rate->get_label() ) );
            }
        }

        return $rates;
    }


    /**
     * Whether a package carries a product the gateway only quotes from the cart.
     *
     * Bundles (WPC Product Bundles) and composites (WPC Composite Products) are
     * normalized by the gateway from the *parent* product alone when they are
     * quoted outside the cart, because the parts only exist as cart lines. The
     * gateway answers that by refusing the quote; HubGo drops the rate for the
     * same reason.
     *
     * @since 3.0.0
     * @param array $package Shipping package.
     * @return bool
     */
    private static function has_composition_product( $package ) {
        if ( empty( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
            return false;
        }

        $found = false;

        foreach ( $package['contents'] as $content ) {
            $product = isset( $content['data'] ) ? $content['data'] : null;

            if ( $product instanceof WC_Product && self::is_composition_product( $product ) ) {
                $found = true;
                break;
            }
        }

        /**
         * Filters whether Melhor Envio rates are dropped from a calculator quote.
         *
         * A store that gave its kits real dimensions — and therefore gets a
         * usable quote out of the gateway — can return false to keep them.
         *
         * @since 3.0.0
         * @param bool $found Whether the package carries a bundle or a composite.
         * @param array $package Shipping package.
         */
        return (bool) apply_filters( 'Hubgo/Integrations/Melhor_Envio/Skip_Compositions', $found, $package );
    }


    /**
     * Whether a product is a bundle or a composite.
     *
     * Asked of the gateway itself when it is new enough to answer, so a type
     * added on its side is recognized without a HubGo release. The fallback
     * repeats the four types it knows about today.
     *
     * @since 3.0.0
     * @param WC_Product $product Product being quoted.
     * @return bool
     */
    private static function is_composition_product( $product ) {
        if ( class_exists( ProductsService::class ) && method_exists( ProductsService::class, 'hasProductComposition' ) ) {
            return (bool) ProductsService::hasProductComposition( $product );
        }

        $types = array( 'woosb', 'product-woosb', 'composite', 'product-composite' );

        return in_array( (string) $product->get_type(), $types, true );
    }


    /**
     * Remove the delivery window the gateway appends to a rate label.
     *
     * `MelhorEnvio\Helpers\TimeHelper::label()` produces " (3 a 5 dias úteis)",
     * " (1 dia útil)" or " (*)" and the gateway concatenates it onto the method
     * title, on top of publishing the same value as `delivery_time` meta. HubGo
     * reads the meta, adds the store's handling time and prints a calendar date,
     * so leaving the parenthetical in place shows the shopper two different
     * answers to the same question.
     *
     * Only the trailing group is matched, so a method a store deliberately named
     * "Sedex (até 10kg)" keeps its title.
     *
     * @since 3.0.0
     * @param string $label Rate label.
     * @return string
     */
    private static function strip_delivery_window( $label ) {
        $stripped = preg_replace( '/\s*\((?:\*|[^()]*\bdias?\b[^()]*)\)\s*$/ui', '', (string) $label );

        // preg_replace() answers null on a bad subject (broken UTF-8), and an
        // empty label would leave the row unreadable.
        return ( is_string( $stripped ) && '' !== trim( $stripped ) ) ? $stripped : (string) $label;
    }


    /**
     * Bring a freshly generated tracking code into HubGo.
     *
     * Runs on the order save the gateway itself performs when it writes the
     * code ({@see \MelhorEnvio\Services\TrackingService::addTrackingWcOrder()}),
     * which is the only signal it publishes — the plugin fires no actions of its
     * own. Both steps below save the order again, hence the re-entrancy guard.
     *
     * The code is what signals the shipment, so it is read even when importing
     * is off — "Mark as shipped" alone is a valid choice on the card.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @return void
     */
    public function sync_order( $order_id ) {
        $order_id = absint( $order_id );

        if ( ! $order_id || isset( self::$syncing[ $order_id ] ) || ! function_exists('wc_get_order') ) {
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $code = self::get_source_code( $order );

        if ( '' === $code ) {
            return;
        }

        self::$syncing[ $order_id ] = true;

        if ( self::syncs_tracking() ) {
            $this->import_code( $order, $code );
        }

        $this->maybe_mark_as_shipped( $order );

        unset( self::$syncing[ $order_id ] );
    }


    /**
     * Show codes the gateway generated before the integration was switched on.
     *
     * The importer only sees orders that are saved again after this integration
     * booted; every shipment that predates it lives on in the gateway's meta and
     * would otherwise be invisible on HubGo's surfaces. Those are bridged
     * read-only — HubGo has nothing of its own to delete — and skipped as soon
     * as the same code exists as a real HubGo item.
     *
     * @since 3.0.0
     * @param array $items Tracking items resolved by HubGo.
     * @param int $order_id Order ID.
     * @return array
     */
    public function merge_source_items( $items, $order_id ) {
        $items = is_array( $items ) ? $items : array();
        $order = function_exists('wc_get_order') ? wc_get_order( absint( $order_id ) ) : false;

        if ( ! $order ) {
            return $items;
        }

        $code = self::get_source_code( $order );

        if ( '' === $code || self::has_tracking_number( $items, $code ) ) {
            return $items;
        }

        $carrier = Delivery_Promise::get_carrier( $order );

        $items[] = array(
            'tracking_id'     => 'melhorenvio_' . md5( $code ),
            'tracking_number' => $code,
            'provider'        => self::PROVIDER_NAME,
            'custom_provider' => $carrier,
            'custom_url'      => '',
            'ship_date'       => '',
            'read_only'       => true,
            'source'          => self::SOURCE_ID,
            'source_label'    => self::PROVIDER_NAME,
        );

        return $items;
    }


    /**
     * Remove the shipping simulator the Melhor Envio plugin renders on product
     * pages.
     *
     * The gateway hooks an instance it keeps no reference to, on a hook the shop
     * owner picks in its own settings, so there is no callback to name in a
     * `remove_action()`. The registered callbacks are matched by class instead,
     * which is stable across the gateway's releases as long as the controller
     * keeps its name.
     *
     * Deferred to `wp` because the gateway boots on `plugins_loaded` and the
     * actions would otherwise be added back after this ran. Removing
     * `isProductSingle` is enough to keep the markup out: the callback printing
     * it is only hooked from inside that one.
     *
     * @since 3.0.0
     * @return void
     */
    public function unhook_native_simulator() {
        $controller = 'MelhorEnvio\Controllers\ShowCalculatorProductPage';

        if ( is_admin() || ! class_exists( $controller ) ) {
            return;
        }

        self::remove_instance_callbacks( self::get_native_simulator_hook(), $controller );
        self::remove_instance_callbacks( 'wp_enqueue_scripts', $controller );
    }


    /**
     * Drop the assets the hidden simulator would otherwise still load.
     *
     * The gateway registers them under generic handles ("calculator",
     * "produto"), so each one is only dropped after its source is confirmed to
     * live inside the Melhor Envio plugin — a theme owning the same handle is
     * left alone.
     *
     * @since 3.0.0
     * @return void
     */
    public function dequeue_native_simulator_assets() {
        foreach ( array( 'produto', 'produto-variacao', 'calculator', 'calculator-script' ) as $handle ) {
            if ( self::is_native_asset( wp_scripts(), $handle ) ) {
                wp_dequeue_script( $handle );
            }
        }

        if ( self::is_native_asset( wp_styles(), 'calculator-style' ) ) {
            wp_dequeue_style('calculator-style');
        }
    }


    /**
     * Whether a registered asset is served from the Melhor Envio plugin.
     *
     * @since 3.0.0
     * @param \WP_Dependencies $dependencies Script or style registry.
     * @param string $handle Asset handle.
     * @return bool
     */
    private static function is_native_asset( $dependencies, $handle ) {
        if ( ! isset( $dependencies->registered[ $handle ]->src ) ) {
            return false;
        }

        $src = (string) $dependencies->registered[ $handle ]->src;

        return defined('MELHORENVIO_ASSETS') && 0 === strpos( $src, (string) MELHORENVIO_ASSETS );
    }


    /**
     * Import the gateway's tracking code as a HubGo tracking item.
     *
     * Goes through {@see Tracking_Manager::add_item()} rather than the import
     * path used by migrations: this is a shipment that is happening now, so the
     * e-mails and the Joinotify automation are meant to fire exactly as they do
     * for a code typed by hand. Items already bridged for display are ignored
     * when looking for a duplicate — they are this same code, unimported.
     *
     * @since 3.0.0
     * @param \WC_Order $order Order carrying the code.
     * @param string $code Tracking code.
     * @return bool Whether an item was added.
     */
    private function import_code( $order, $code ) {
        $manager = new Tracking_Manager();
        $owned = array();

        foreach ( $manager->get_items( $order->get_id() ) as $item ) {
            if ( is_array( $item ) && empty( $item['read_only'] ) ) {
                $owned[] = $item;
            }
        }

        if ( self::has_tracking_number( $owned, $code ) ) {
            return false;
        }

        $manager->add_item( $order->get_id(), array(
            'tracking_number' => $code,
            'provider'        => self::PROVIDER_NAME,
            // The reseller resolves the link; the carrier quoted at the checkout
            // is the name the customer actually needs to read.
            'custom_provider' => Delivery_Promise::get_carrier( $order ),
        ) );

        return true;
    }


    /**
     * Move the order to "Order shipped" the first time a code reaches it.
     *
     * Applied once per order and recorded in meta, so a shop manager who moves
     * the order somewhere else afterwards is never overruled by a later save.
     * Orders that were cancelled, refunded or failed are left alone: a label
     * bought before the cancellation must not resurrect them.
     *
     * @since 3.0.0
     * @param \WC_Order $order Order to transition.
     * @return bool Whether the status was changed.
     */
    private function maybe_mark_as_shipped( $order ) {
        if ( ! self::marks_as_shipped() || ! self::shipped_status_available() ) {
            return false;
        }

        if ( '' !== (string) $order->get_meta( self::SHIPPED_META_KEY, true ) ) {
            return false;
        }

        // get_status() answers without the `wc-` prefix, which is the form
        // Order_Status::LEGACY_STATUS holds.
        $skipped = array( 'cancelled', 'refunded', 'failed', 'trash', Order_Status::LEGACY_STATUS );

        if ( in_array( $order->get_status(), $skipped, true ) ) {
            return false;
        }

        $order->update_meta_data( self::SHIPPED_META_KEY, current_time('mysql') );

        // update_status() saves the order, meta included.
        $order->update_status(
            Order_Status::STATUS,
            __( 'Tracking code generated in Melhor Envio.', 'hubgo' ),
            true
        );

        return true;
    }


    /**
     * Tracking code the gateway stored on an order.
     *
     * @since 3.0.0
     * @param \WC_Order $order Order to read.
     * @return string Empty string when the order carries none.
     */
    private static function get_source_code( $order ) {
        $code = $order->get_meta( self::SOURCE_META_KEY, true );

        return is_scalar( $code ) ? trim( (string) $code ) : '';
    }


    /**
     * Whether a tracking list already carries a code.
     *
     * @since 3.0.0
     * @param array $items Tracking items.
     * @param string $code Tracking code.
     * @return bool
     */
    private static function has_tracking_number( $items, $code ) {
        $code = strtolower( trim( (string) $code ) );

        foreach ( (array) $items as $item ) {
            if ( ! is_array( $item ) || empty( $item['tracking_number'] ) ) {
                continue;
            }

            if ( strtolower( trim( (string) $item['tracking_number'] ) ) === $code ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Hook the gateway prints its product-page simulator on.
     *
     * Read from the gateway's own option so a store that moved the simulator
     * (its settings screen offers a few positions) still gets it removed. The
     * fallback repeats the gateway's default.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_native_simulator_hook() {
        $hook = get_option( 'melhor_envio_option_where_show_calculator' );

        return ( is_string( $hook ) && '' !== $hook ) ? $hook : 'woocommerce_before_add_to_cart_button';
    }


    /**
     * Unhook every callback a class instance registered on a hook.
     *
     * `remove_action()` needs the very object that was hooked, and the gateway
     * keeps no reference to its controller. Matching on the class is the only
     * external way left to reach those callbacks.
     *
     * @since 3.0.0
     * @param string $hook Hook name.
     * @param string $class Fully qualified class name.
     * @return void
     */
    private static function remove_instance_callbacks( $hook, $class ) {
        global $wp_filter;

        if ( empty( $wp_filter[ $hook ] ) || ! isset( $wp_filter[ $hook ]->callbacks ) ) {
            return;
        }

        // Iterating a copy: remove_action() rewrites the live structure.
        foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
            foreach ( $callbacks as $callback ) {
                if ( ! isset( $callback['function'] ) || ! is_array( $callback['function'] ) ) {
                    continue;
                }

                if ( ! is_object( $callback['function'][0] ) || ! $callback['function'][0] instanceof $class ) {
                    continue;
                }

                remove_action( $hook, $callback['function'], $priority );
            }
        }
    }


    /**
     * Whether a shipping method id belongs to the gateway.
     *
     * @since 3.0.0
     * @param string $method_id Shipping method id.
     * @return bool
     */
    private static function is_melhor_envio_method( $method_id ) {
        return 0 === strpos( (string) $method_id, self::METHOD_PREFIX );
    }


    /**
     * Whether the official plugin is installed and active.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_available() {
        return self::is_plugin_dependency_active( self::get_plugin_files(), true );
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
     * Whether the tracking code should be imported from the gateway.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function syncs_tracking() {
        return 'yes' === Settings::get_setting( 'melhor_envio_sync_tracking', 'yes' );
    }


    /**
     * Whether an imported code moves the order to "Order shipped".
     *
     * @since 3.0.0
     * @return bool
     */
    public static function marks_as_shipped() {
        return 'yes' === Settings::get_setting( 'melhor_envio_mark_as_shipped', 'no' );
    }


    /**
     * Whether the gateway's own product-page simulator should be hidden.
     *
     * Gated on HubGo's calculator being switched on: hiding the only calculator
     * a product page has would be a regression, not a fix.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function disables_native_simulator() {
        if ( 'yes' !== Settings::get_setting( 'melhor_envio_disable_simulator', 'yes' ) ) {
            return false;
        }

        return Shipping_Calculator::is_enabled();
    }


    /**
     * Whether the "Order shipped" status is registered on this store.
     *
     * Moving an order to a status WooCommerce does not know about silently
     * lands it on "pending", so the transition is skipped instead.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function shipped_status_available() {
        return 'yes' === Settings::get_setting(
            'enable_order_shipped_status',
            Settings::get_default_value( 'enable_order_shipped_status', 'yes' )
        );
    }


    /**
     * Plugin basenames the dependency is satisfied by.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return array<int,string>
     */
    public static function get_plugin_files() {
        $plugin_files = defined('HUBGO_MELHOR_ENVIO_PLUGIN_FILE')
            ? array( (string) HUBGO_MELHOR_ENVIO_PLUGIN_FILE )
            : self::PLUGIN_FILES;

        /**
         * Filters the basenames the Melhor Envio dependency accepts.
         *
         * @since 3.0.0
         * @param array<int,string> $plugin_files Plugin basenames.
         */
        $plugin_files = (array) apply_filters( 'Hubgo/Integrations/Melhor_Envio/Plugin_Files', $plugin_files );

        return array_values( array_filter( array_map( 'strval', $plugin_files ) ) );
    }


    /**
     * Basename the one-click install writes to.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return string
     */
    public static function get_plugin_file() {
        $plugin_files = self::get_plugin_files();
        $plugin_file = ! empty( $plugin_files ) ? $plugin_files[0] : self::PLUGIN_FILE;

        return (string) apply_filters( 'Hubgo/Integrations/Melhor_Envio/Plugin_File', $plugin_file );
    }


    /**
     * Download URL of the installable package.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return string
     */
    public static function get_package_url() {
        $package_url = defined('HUBGO_MELHOR_ENVIO_PACKAGE_URL')
            ? (string) HUBGO_MELHOR_ENVIO_PACKAGE_URL
            : self::PACKAGE_URL;

        return esc_url_raw( (string) apply_filters( 'Hubgo/Integrations/Melhor_Envio/Package_Url', $package_url ) );
    }


    /**
     * Melhor Envio brand mark.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_icon_svg() {
        return self::get_brand_svg( 'melhor-envio-logo.svg', __( 'Melhor Envio', 'hubgo' ) );
    }
}
