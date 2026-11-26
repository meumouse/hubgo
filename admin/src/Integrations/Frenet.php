<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Delivery_Promise;
use MeuMouse\Hubgo\Core\Tracking_Manager;
use MeuMouse\Hubgo\Views\Shipping_Calculator;

use WC_Order;
use WC_Shipping_Rate;

defined('ABSPATH') || exit;

/**
 * Frenet integration, backed by the official "Frenet Shipping Gateway" plugin.
 *
 * Frenet is distributed on wordpress.org, so the card ships a package URL and
 * the Integrations screen can install and activate it in one click through
 * {@see \MeuMouse\Hubgo\Core\Plugin_Installer}.
 *
 * The integration is free and lives entirely on this side: the Frenet plugin is
 * third-party code that HubGo itself installs from wordpress.org, so patching it
 * would be undone by its next update. Everything below therefore reaches the
 * gateway through hooks and public API only:
 *
 * - **Quote value.** `WC_Frenet::frenet_calculate()` reads the invoice value off
 *   `WC()->cart` unless its public `quoteByProduct` flag is set. HubGo's
 *   calculator quotes a product with an empty cart, so Frenet was being asked to
 *   price a R$ 0,00 shipment — which silently drops the services priced by
 *   declared value. The flag is flipped for HubGo packages only.
 * - **Delivery forecast.** The gateway keeps Frenet's `DeliveryTime` out of the
 *   rate meta and folds it into the label text instead, so
 *   {@see \MeuMouse\Hubgo\Core\Delivery_Estimate} found nothing and the
 *   storefront lost its promised date. It is recovered from the API response by
 *   {@see Frenet_Quote_Listener} and written back as standard rate meta.
 * - **Carrier.** Frenet answers with the carrier actually moving the parcel
 *   (Correios, Jadlog, Loggi). It reaches the order through the rate meta, which
 *   is what lets a notification name the carrier instead of the reseller.
 * - **Duplicate calculator.** The gateway prints its own shipping simulator on
 *   the product page. With HubGo's calculator on, the page rendered two.
 * - **Tracking code.** The gateway stores no tracking code at all. The Frenet
 *   platform reports it by adding an order note to the store ("Seu código de
 *   rastreio é o AE176599145BR"), so {@see Frenet::import_note_code()} reads the
 *   code out of that note and saves it as a HubGo tracking item.
 *
 * @since 3.0.0
 * @version 3.2.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Frenet extends Integrations_Base {

    /**
     * Card slug.
     *
     * @since 3.0.0
     * @var string
     */
    const CARD_SLUG = 'frenet';

    /**
     * Option key toggling the integration.
     *
     * @since 3.0.0
     * @var string
     */
    const SETTING_KEY = 'enable_frenet_integration';

    /**
     * Plugin basename of the official plugin.
     *
     * @since 3.0.0
     * @var string
     */
    const PLUGIN_FILE = 'woo-shipping-gateway/woo-shipping-gateway.php';

    /**
     * WooCommerce shipping method id registered by the gateway.
     *
     * @since 3.0.0
     * @var string
     */
    const METHOD_ID = 'frenet';

    /**
     * Provider name this integration publishes to the tracking registry.
     *
     * @since 3.0.0
     * @var string
     */
    const PROVIDER_NAME = 'Frenet';

    /**
     * Rate meta key the gateway writes the prefixed service code under.
     *
     * @since 3.0.0
     * @var string
     */
    const SERVICE_META_KEY = 'FRENET_ID';

    /**
     * Package URL served by wordpress.org.
     *
     * @since 3.0.0
     * @var string
     */
    const PACKAGE_URL = 'https://downloads.wordpress.org/plugin/woo-shipping-gateway.zip';

    /**
     * Tracking URL template. %1$s receives the url-encoded tracking code, the
     * same placeholder contract every entry in Providers_Registry uses, and
     * %2$s the carrier segment Frenet's public tracking page routes on.
     *
     * The previous address — `painel.frenet.com.br/tracking/<code>` — is gone:
     * it answers with the panel's login screen now, so every store still on the
     * default was handing customers a dead link. The page that replaced it
     * lives on its own host and takes the carrier in the path; without that
     * segment it answers 404, which is why this template needs a second
     * placeholder. {@see Frenet::add_carrier_code()} fills it.
     *
     * @since 3.0.0
     * @version 3.1.1
     * @var string
     */
    const DEFAULT_TRACKING_URL = 'https://rastreio.frenet.com.br/%2$s/%1$s';

    /**
     * Carrier segment used when the quoted carrier matches nothing known.
     *
     * Safe as a guess rather than merely common: Frenet resolves the shipment
     * from the code alone, so a segment naming the wrong carrier still renders
     * the right history. It only has to be a carrier Frenet publishes — an
     * absent segment is what turns the page into a 404.
     *
     * @since 3.1.1
     * @var string
     */
    const DEFAULT_CARRIER_CODE = 'COR';

    /**
     * Previous `quoteByProduct` value, keyed by method instance.
     *
     * @since 3.0.0
     * @var array<int,bool>
     */
    private $quote_mode_backup = array();

    /**
     * Per-request cache of "was this order shipped with Frenet", by order ID.
     *
     * `Hubgo/Tracking/Get_Items` runs on every surface that lists tracking
     * codes, several times per screen; resolving the order's shipping lines
     * once per order keeps that cheap.
     *
     * @since 3.0.0
     * @var array<int,bool>
     */
    private $frenet_orders = array();

    /**
     * Orders whose note is being imported right now, keyed by order ID.
     *
     * Saving the tracking item saves the order, and whatever listens to
     * `Hubgo/Tracking/Item_Saved` may add a note of its own; neither may start a
     * second import of the same order.
     *
     * @since 3.2.0
     * @var array<int,bool>
     */
    private static $importing = array();


    /**
     * Constructor.
     *
     * @since 3.0.0
     * @version 3.2.0
     */
    public function __construct() {
        $this->register_integration_card( 30 );

        if ( ! self::is_available() || ! self::is_enabled() ) {
            return;
        }

        add_filter( 'Hubgo/Tracking/Get_Providers', array( $this, 'register_provider' ) );

        // Feeds the %2$s of the template registered just above. The two are
        // added together deliberately: a template carrying a placeholder that
        // nothing fills is an ArgumentCountError out of vsprintf(), not merely
        // a broken link.
        add_filter( 'Hubgo/Tracking/Provider_Url_Values', array( $this, 'add_carrier_code' ), 10, 5 );

        Frenet_Quote_Listener::listen();

        add_action( 'woocommerce_before_get_rates_for_package', array( $this, 'before_get_rates' ), 10, 2 );
        add_action( 'woocommerce_after_get_rates_for_package', array( $this, 'after_get_rates' ), 10, 2 );

        // Priority 20 so the rates are already assembled (and any zone-level
        // filtering has run) before the forecast is attached to them.
        add_filter( 'woocommerce_package_rates', array( $this, 'decorate_rates' ), 20, 2 );

        if ( self::syncs_tracking() ) {
            add_filter( 'Hubgo/Tracking/Get_Items', array( $this, 'fill_tracking_provider' ), 10, 2 );

            // The order note is the only place the Frenet platform puts the
            // code, so it is the only signal there is to import it from.
            add_action( 'woocommerce_order_note_added', array( $this, 'import_note_code' ), 10, 2 );
        }

        if ( self::disables_native_simulator() ) {
            add_action( 'wp', array( $this, 'unhook_native_simulator' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_native_simulator_assets' ), 100 );
        }
    }


    /**
     * Register the card on the Integrations screen.
     *
     * @since 3.0.0
     * @version 3.2.0
     * @param array $integrations Current catalog.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations[ self::CARD_SLUG ] = array(
            'title'         => __( 'Frenet', 'hubgo' ),
            'description'   => __( 'Use Frenet quotes in the shipping calculation and bring the tracking code into the HubGo order screen.', 'hubgo' ),
            'icon'          => $this->get_icon_svg(),
            'author'        => 'Frenet',
            'author_url'    => 'https://www.frenet.com.br',
            'category'      => 'shipping',
            'setting_key'   => self::SETTING_KEY,
            'is_plugin'     => true,
            'plugin_active' => array( self::PLUGIN_FILE ),
            'doc_url'       => 'https://wordpress.org/plugins/woo-shipping-gateway/',
            'install'       => array(
                'plugin_slug'  => self::PLUGIN_FILE,
                'download_url' => self::PACKAGE_URL,
                'label'        => __( 'Install Frenet', 'hubgo' ),
            ),
            'settings'      => array(
                self::field_toggle(
                    'frenet_sync_tracking',
                    __( 'Import tracking', 'hubgo' ),
                    __( 'On orders shipped with Frenet, saves the tracking code Frenet adds as an order note and fills in the carrier Frenet quoted.', 'hubgo' )
                ),
                self::field_toggle(
                    'frenet_disable_simulator',
                    __( 'Hide the Frenet simulator', 'hubgo' ),
                    __( 'Removes the shipping simulator the Frenet plugin prints on the product page, so only the HubGo calculator is shown.', 'hubgo' )
                ),
                self::field_text(
                    'frenet_tracking_url',
                    __( 'Tracking URL', 'hubgo' ),
                    __( 'Address used in the tracking link. Use %1$s where the code should go, and %2$s for the carrier.', 'hubgo' ),
                    array( 'placeholder' => self::DEFAULT_TRACKING_URL )
                ),
            ),
            'modal'         => array(
                'title'       => __( 'Frenet', 'hubgo' ),
                'description' => __( 'How HubGo reads the shipping data generated by Frenet.', 'hubgo' ),
                'size'        => 'medium',
                'blocks'      => array(
                    self::modal_notice_block(
                        __( 'HubGo reads the delivery time and the carrier straight from the Frenet quote, so the product page can promise a delivery date. Fill in the "Token" field of the Frenet shipping method for the most accurate result.', 'hubgo' ),
                        'info'
                    ),
                ),
            ),
        );

        return $integrations;
    }


    /**
     * Add Frenet to the Brazilian tracking providers list.
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

        $url = (string) Settings::get_setting( 'frenet_tracking_url', '' );
        $providers['Brazil'][ self::PROVIDER_NAME ] = '' !== trim( $url ) ? $url : self::DEFAULT_TRACKING_URL;

        return $providers;
    }


    /**
     * Supply the carrier segment the Frenet tracking URL is built from.
     *
     * Registered on the registry's own placeholder-values filter rather than
     * resolved inside the template, because the template is a plain string a
     * shop owner may overwrite in the settings — a store that kept the default
     * gets the carrier routed for it, a store that typed its own address keeps
     * full control of the shape and simply never references %2$s.
     *
     * The carrier is read from the checkout promise and not from the tracking
     * item: this filter is handed an order, not an item, and the promise is the
     * same source {@see Frenet::fill_tracking_provider()} labels the item with,
     * so the link and the label can never disagree.
     *
     * @since 3.1.1
     * @param array $values Placeholder values indexed by sprintf position.
     * @param string $provider Provider name.
     * @param string $tracking_number Tracking number.
     * @param string $country Country/region key.
     * @param int $order_id Order ID.
     * @return array
     */
    public function add_carrier_code( $values, $provider, $tracking_number, $country, $order_id ) {
        if ( ! is_array( $values ) || self::PROVIDER_NAME !== (string) $provider ) {
            return $values;
        }

        $values[2] = rawurlencode( self::resolve_carrier_code( $order_id ) );

        // The registry flattens this map with array_values(), so the keys have
        // to be in sprintf order before it does — another filter may well have
        // appended a position of its own ahead of this one.
        ksort( $values );

        return $values;
    }


    /**
     * Resolve the carrier segment for an order.
     *
     * @since 3.1.1
     * @param int $order_id Order ID.
     * @return string Carrier code, never empty.
     */
    public static function resolve_carrier_code( $order_id ) {
        $carrier = self::normalize_carrier( Delivery_Promise::get_carrier( $order_id ) );

        if ( '' === $carrier ) {
            return self::DEFAULT_CARRIER_CODE;
        }

        $codes = self::get_carrier_codes();

        if ( isset( $codes[ $carrier ] ) ) {
            return $codes[ $carrier ];
        }

        // Frenet names a carrier more fully than the catalog does ("Jadlog
        // Package", "Correios Mini Envios"), so an exact miss is still worth a
        // prefix pass before giving up. Longest key first, so "jtexpress" is
        // never shadowed by a shorter entry that happens to also match.
        $keys = array_keys( $codes );

        usort( $keys, function( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        });

        foreach ( $keys as $key ) {
            if ( false !== strpos( $carrier, $key ) ) {
                return $codes[ $key ];
            }
        }

        return self::DEFAULT_CARRIER_CODE;
    }


    /**
     * Carrier name to Frenet tracking segment.
     *
     * Keys are normalized by {@see Frenet::normalize_carrier()}, so they carry
     * no spaces, accents or punctuation. The list is the one Frenet publishes
     * on its public tracking page; a carrier it adds later only needs an entry
     * here, since the URL shape itself does not change.
     *
     * Only carriers Frenet routes on belong here. A posting point such as
     * Drops is named in the same catalog but is not a tracking segment — its
     * code lands on the "rastreio não encontrado" page, which is worse than
     * falling back to {@see Frenet::DEFAULT_CARRIER_CODE}.
     *
     * @since 3.1.1
     * @return array<string,string>
     */
    public static function get_carrier_codes() {
        $codes = array(
            'correios'      => 'COR',
            'jadlog'        => 'JAD',
            'loggi'         => 'LOG',
            'carriers'      => 'CARR',
            'totalexpress'  => 'TOT',
            'anjun'         => 'AJN',
            'imile'         => 'IMI',
            'jtexpress'     => 'JTE',
            'jt'            => 'JTE',
            'optimuscargas' => 'OPT',
            'optimus'       => 'OPT',
            'jamef'         => 'JAM',
        );

        /**
         * Filter the carrier name to Frenet tracking segment map.
         *
         * @since 3.1.1
         * @param array $codes Normalized carrier name => Frenet carrier code.
         */
        return apply_filters( 'Hubgo/Integrations/Frenet/Carrier_Codes', $codes );
    }


    /**
     * Reduce a carrier name to a comparable key.
     *
     * @since 3.1.1
     * @param string $carrier Carrier name as quoted by Frenet.
     * @return string Lowercase, accent-free, alphanumeric-only name.
     */
    private static function normalize_carrier( $carrier ) {
        $carrier = (string) $carrier;

        if ( '' === trim( $carrier ) ) {
            return '';
        }

        // remove_accents() keeps "Correios Expresso" and "Côrreios" comparable
        // without each needing its own entry in the map.
        $carrier = remove_accents( $carrier );
        $carrier = strtolower( $carrier );

        return (string) preg_replace( '/[^a-z0-9]/', '', $carrier );
    }


    /**
     * Ask Frenet to price the package instead of the cart.
     *
     * `WC_Frenet::$quoteByProduct` is public precisely so the gateway's own
     * product-page simulator can set it; HubGo's calculator is in exactly the
     * same position — quoting one product line with no cart behind it — and uses
     * the same switch. Scoped to packages this plugin built, so the cart and the
     * checkout keep quoting the whole order as before.
     *
     * @since 3.0.0
     * @param array $package Shipping package.
     * @param \WC_Shipping_Method $method Shipping method about to quote.
     * @return void
     */
    public function before_get_rates( $package, $method ) {
        if ( empty( $package['hubgo_calculator'] ) || ! $this->is_frenet_method( $method ) ) {
            return;
        }

        if ( ! property_exists( $method, 'quoteByProduct' ) ) {
            return;
        }

        $this->quote_mode_backup[ spl_object_id( $method ) ] = (bool) $method->quoteByProduct;

        $method->quoteByProduct = true;
    }


    /**
     * Restore the gateway's quoting mode after the package was priced.
     *
     * WooCommerce reuses one method instance for every package in a request, so
     * leaving the flag on would make an unrelated cart calculation later in the
     * same request price only its last line.
     *
     * @since 3.0.0
     * @param array $package Shipping package.
     * @param \WC_Shipping_Method $method Shipping method that quoted.
     * @return void
     */
    public function after_get_rates( $package, $method ) {
        if ( ! $this->is_frenet_method( $method ) ) {
            return;
        }

        $key = spl_object_id( $method );

        if ( ! array_key_exists( $key, $this->quote_mode_backup ) ) {
            return;
        }

        $method->quoteByProduct = $this->quote_mode_backup[ $key ];

        unset( $this->quote_mode_backup[ $key ] );
    }


    /**
     * Attach the delivery forecast and the carrier to every Frenet rate.
     *
     * Written under `delivery_time` and `carrier` — the conventional keys, not
     * Frenet-specific ones — because WooCommerce copies rate meta onto the order
     * line item, and both {@see \MeuMouse\Hubgo\Core\Delivery_Estimate} and
     * {@see Delivery_Promise} then read the data without knowing which carrier
     * produced it.
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

        foreach ( $rates as $rate ) {
            if ( ! $rate instanceof WC_Shipping_Rate || self::METHOD_ID !== $rate->get_method_id() ) {
                continue;
            }

            $meta = (array) $rate->get_meta_data();
            $service = Frenet_Quote_Listener::get( $this->get_service_code( $meta ) );

            $days = isset( $service['delivery_time'] ) ? $service['delivery_time'] : null;

            // SOAP mode (a gateway configured without a token) never goes
            // through the HTTP API, so the label is the only place left that
            // still carries the number.
            if ( null === $days ) {
                $days = $this->parse_days_from_label( $rate->get_label() );
            }

            if ( null !== $days && ! isset( $meta['delivery_time'] ) ) {
                $rate->add_meta_data( 'delivery_time', (int) $days );
            }

            if ( ! empty( $service['carrier'] ) && ! isset( $meta['carrier'] ) ) {
                $rate->add_meta_data( 'carrier', $service['carrier'] );
            }
        }

        return $rates;
    }


    /**
     * Name the carrier on tracking codes added to orders shipped with Frenet.
     *
     * Runs on the *display* filter, never on a write path: HubGo's own meta
     * keeps holding exactly what the shop owner typed, and turning the
     * integration off restores the original reading everywhere at once.
     *
     * The provider is set to Frenet so the link resolves through the registry
     * template, while `custom_provider` carries the carrier that actually moves
     * the parcel — that is the pair {@see \MeuMouse\Hubgo\Core\Tracking_Manager}
     * reads to build a link and a label respectively.
     *
     * @since 3.0.0
     * @param array $items Tracking items.
     * @param int $order_id Order ID.
     * @return array
     */
    public function fill_tracking_provider( $items, $order_id ) {
        if ( ! is_array( $items ) || empty( $items ) || ! $this->order_used_frenet( $order_id ) ) {
            return $items;
        }

        $carrier = Delivery_Promise::get_carrier( $order_id );

        foreach ( $items as &$item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            // Anything the shop owner filled in wins, always.
            if ( ! empty( $item['provider'] ) || ! empty( $item['carrier'] ) || ! empty( $item['custom_provider'] ) ) {
                continue;
            }

            $item['provider'] = self::PROVIDER_NAME;

            if ( '' !== $carrier ) {
                $item['custom_provider'] = $carrier;
            }
        }

        unset( $item );

        return $items;
    }


    /**
     * Save the tracking code the Frenet platform reports in an order note.
     *
     * The gateway plugin writes no tracking meta and fires no hook: when a label
     * is posted, the Frenet platform updates the order through the WooCommerce
     * REST API and adds a customer note carrying the code. That note is the only
     * place the code reaches the store, so without this the order had a code the
     * customer could read and HubGo could not, and every tracking token resolved
     * empty.
     *
     * Goes through {@see Tracking_Manager::add_item()} rather than the import path
     * used by migrations, for the reason {@see Melhor_Envio::import_code()} does:
     * this is a shipment happening now, so the e-mails and the Joinotify "tracking
     * code saved" automation must fire exactly as they do for a code typed by hand.
     *
     * Limited to orders shipped with Frenet, which is what the "Import tracking"
     * card promises and what makes reading a code out of free text safe enough.
     *
     * @since 3.2.0
     * @param int $note_id Order note (comment) ID.
     * @param WC_Order $order Order the note was added to.
     * @return void
     */
    public function import_note_code( $note_id, $order ) {
        if ( ! $order instanceof WC_Order ) {
            return;
        }

        $order_id = $order->get_id();

        if ( ! $order_id || isset( self::$importing[ $order_id ] ) || ! $this->order_used_frenet( $order_id ) ) {
            return;
        }

        $note = get_comment( $note_id );
        $code = $note ? self::parse_tracking_code( $note->comment_content ) : '';

        if ( '' === $code ) {
            return;
        }

        self::$importing[ $order_id ] = true;

        $this->import_code( $order_id, $code );

        unset( self::$importing[ $order_id ] );
    }


    /**
     * Read a tracking code out of an order note.
     *
     * The patterns anchor on the sentence around the code, not on the code's own
     * shape: Frenet moves Correios codes (AE176599145BR) as well as purely numeric
     * Jadlog and Loggi ones, so no single code format covers every carrier. A
     * match must still contain a digit, which is what keeps a sentence such as
     * "o código de rastreio estará disponível" from yielding the word "estará".
     *
     * @since 3.2.0
     * @param string $note Note content, HTML allowed.
     * @return string The code, or an empty string when the note carries none.
     */
    public static function parse_tracking_code( $note ) {
        $text = html_entity_decode( wp_strip_all_tags( (string) $note ), ENT_QUOTES, 'UTF-8' );
        $text = (string) preg_replace( '/\s+/u', ' ', $text );

        if ( '' === trim( $text ) ) {
            return '';
        }

        $code = '(?=[A-Za-z\-]*\d)([A-Za-z0-9][A-Za-z0-9\-]{4,39})';

        $patterns = array(
            // "Seu código de rastreio é o AE176599145BR", "Código de rastreamento: 123".
            '/c[óo]digo\s+de\s+rastr(?:eio|eamento)\s*(?:[ée]\s+)?(?:o\s+)?:?\s*' . $code . '/iu',
            // "Your tracking code is AE176599145BR", "Tracking number: 123".
            '/tracking\s+(?:code|number)\s*(?:is\s+)?:?\s*' . $code . '/iu',
        );

        /**
         * Filter the patterns a tracking code is read from in a Frenet order note.
         *
         * Each one is a full PCRE pattern whose first capture group is the code.
         *
         * @since 3.2.0
         * @param array $patterns PCRE patterns.
         * @param string $text Note text, tags stripped.
         */
        $patterns = apply_filters( 'Hubgo/Integrations/Frenet/Tracking_Note_Patterns', $patterns, $text );

        foreach ( (array) $patterns as $pattern ) {
            if ( is_string( $pattern ) && '' !== $pattern && preg_match( $pattern, $text, $matches ) && ! empty( $matches[1] ) ) {
                return trim( (string) $matches[1], '-' );
            }
        }

        return '';
    }


    /**
     * Save a tracking code as a HubGo tracking item, unless the order has it.
     *
     * Items bridged read-only by another integration are ignored when looking
     * for a duplicate: they are display copies, not HubGo's own records.
     *
     * @since 3.2.0
     * @param int $order_id Order ID.
     * @param string $code Tracking code.
     * @return bool Whether an item was added.
     */
    private function import_code( $order_id, $code ) {
        $manager = new Tracking_Manager();

        foreach ( $manager->get_items( $order_id ) as $item ) {
            if ( ! is_array( $item ) || ! empty( $item['read_only'] ) || empty( $item['tracking_number'] ) ) {
                continue;
            }

            if ( 0 === strcasecmp( trim( (string) $item['tracking_number'] ), $code ) ) {
                return false;
            }
        }

        $manager->add_item( $order_id, array(
            'tracking_number' => $code,
            'provider'        => self::PROVIDER_NAME,
            // Frenet resolves the link; the carrier quoted at the checkout is
            // the name the customer actually needs to read.
            'custom_provider' => Delivery_Promise::get_carrier( $order_id ),
            'ship_date'       => current_time('Y-m-d'),
        ) );

        return true;
    }


    /**
     * Remove the shipping simulator the Frenet plugin renders on product pages.
     *
     * The gateway registers a static callback, so it unhooks cleanly by name.
     * Deferred to `wp` because the gateway boots on `plugins_loaded` and the
     * action would otherwise be added back after this ran.
     *
     * @since 3.0.0
     * @return void
     */
    public function unhook_native_simulator() {
        if ( ! class_exists('WC_Frenet_Shipping_Simulator') ) {
            return;
        }

        remove_action( 'woocommerce_single_product_summary', array( 'WC_Frenet_Shipping_Simulator', 'simulator' ), 40 );
    }


    /**
     * Drop the assets the hidden simulator would otherwise still load.
     *
     * @since 3.0.0
     * @return void
     */
    public function dequeue_native_simulator_assets() {
        wp_dequeue_script('shipping-simulator');
        wp_dequeue_style('shipping-simulator');
    }


    /**
     * Whether a shipping method instance is the Frenet gateway.
     *
     * @since 3.0.0
     * @param mixed $method Shipping method instance.
     * @return bool
     */
    private function is_frenet_method( $method ) {
        return is_object( $method ) && isset( $method->id ) && self::METHOD_ID === $method->id;
    }


    /**
     * Read the Frenet service code out of a rate's meta.
     *
     * The gateway stores it prefixed (`FRENET_04014`); the API answers with the
     * bare code, which is what the captured map is keyed by.
     *
     * @since 3.0.0
     * @param array $meta Rate meta data.
     * @return string Empty string when the rate carries no service code.
     */
    private function get_service_code( $meta ) {
        if ( empty( $meta[ self::SERVICE_META_KEY ] ) || ! is_scalar( $meta[ self::SERVICE_META_KEY ] ) ) {
            return '';
        }

        $code = (string) $meta[ self::SERVICE_META_KEY ];

        return 0 === strpos( $code, 'FRENET_' ) ? substr( $code, 7 ) : $code;
    }


    /**
     * Recover the forecast from the text the gateway appended to a rate label.
     *
     * Only the trailing parenthesis is read — the gateway builds the label as
     * "<service> (Delivery in N working days)" — so a service whose own name
     * contains a number ("Sedex 10") is never mistaken for a forecast.
     *
     * @since 3.0.0
     * @param string $label Rate label.
     * @return int|null Business days, or null when the label carried none.
     */
    private function parse_days_from_label( $label ) {
        if ( ! preg_match( '/\(([^()]*)\)\s*$/u', (string) $label, $matches ) ) {
            return null;
        }

        if ( ! preg_match( '/\d+/', $matches[1], $digits ) ) {
            return null;
        }

        $days = (int) $digits[0];

        return $days > 0 ? $days : null;
    }


    /**
     * Whether an order was shipped through the Frenet gateway.
     *
     * @since 3.0.0
     * @param int $order_id Order ID.
     * @return bool
     */
    private function order_used_frenet( $order_id ) {
        $order_id = absint( $order_id );

        if ( ! $order_id || ! function_exists('wc_get_order') ) {
            return false;
        }

        if ( isset( $this->frenet_orders[ $order_id ] ) ) {
            return $this->frenet_orders[ $order_id ];
        }

        $order = wc_get_order( $order_id );
        $used = false;

        if ( $order ) {
            foreach ( $order->get_items('shipping') as $item ) {
                if ( self::METHOD_ID === $item->get_method_id() ) {
                    $used = true;
                    break;
                }
            }
        }

        $this->frenet_orders[ $order_id ] = $used;

        return $used;
    }


    /**
     * Whether the official plugin is installed and active.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_available() {
        return self::is_plugin_dependency_active( array( self::PLUGIN_FILE ), true );
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
     * Whether the tracking carrier should be filled in from the Frenet quote.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function syncs_tracking() {
        return 'yes' === Settings::get_setting( 'frenet_sync_tracking', 'yes' );
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
        if ( 'yes' !== Settings::get_setting( 'frenet_disable_simulator', 'yes' ) ) {
            return false;
        }

        return Shipping_Calculator::is_enabled();
    }


    /**
     * Frenet brand mark.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_icon_svg() {
        return self::get_brand_svg( 'frenet-logo.svg', __( 'Frenet', 'hubgo' ) );
    }
}
