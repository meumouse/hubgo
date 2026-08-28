<?php

namespace MeuMouse\Hubgo\Integrations;

defined('ABSPATH') || exit;

/**
 * Recovers the data Frenet receives from its API but never publishes.
 *
 * The Frenet Shipping Gateway plugin asks the quote endpoint for a full service
 * description — delivery time, carrier, service code — and then throws most of
 * it away: the rate it hands to WooCommerce carries a single meta entry
 * (`FRENET_ID`), and the delivery forecast survives only as text appended to the
 * rate label. HubGo needs the number, not the sentence: `Core\Delivery_Estimate`
 * turns business days into the promised date the storefront advertises, and
 * `Core\Delivery_Promise` stores it on the order.
 *
 * Reading the raw API response is what makes that possible without forking a
 * third-party plugin. The alternative — parsing "(Delivery in 3 working days)"
 * out of the label — only works in the languages Frenet ships and only while the
 * "Estimated delivery" option is on, so it is kept as a fallback for stores
 * running Frenet in SOAP mode (no token), whose traffic never passes through the
 * HTTP API.
 *
 * The captured map lives for one request only. It is keyed by service code,
 * which is the one identifier that also reaches the rate (as `FRENET_ID`).
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Frenet_Quote_Listener {

    /**
     * Host serving the Frenet quote API.
     *
     * @since 3.0.0
     * @var string
     */
    const API_HOST = 'frenet.com.br';

    /**
     * Path of the quote endpoint the gateway posts to.
     *
     * @since 3.0.0
     * @var string
     */
    const QUOTE_PATH = '/shipping/quote';

    /**
     * Service data captured during this request, keyed by service code.
     *
     * @since 3.0.0
     * @var array<string,array<string,mixed>>
     */
    private static $services = array();

    /**
     * Whether the response filter is already registered.
     *
     * @since 3.0.0
     * @var bool
     */
    private static $listening = false;


    /**
     * Start watching HTTP responses for Frenet quotes.
     *
     * Registered once per request and left in place: the callback bails on the
     * URL before touching the body, so every unrelated HTTP call costs two
     * `strpos()` and nothing else.
     *
     * @since 3.0.0
     * @return void
     */
    public static function listen() {
        if ( self::$listening ) {
            return;
        }

        add_filter( 'http_response', array( __CLASS__, 'capture_response' ), 10, 3 );

        self::$listening = true;
    }


    /**
     * Capture the service list out of a Frenet quote response.
     *
     * Never alters the response — it is a read-only tap on the HTTP layer.
     *
     * @since 3.0.0
     * @param array|\WP_Error $response HTTP response.
     * @param array $args Request arguments.
     * @param string $url Requested URL.
     * @return array|\WP_Error The untouched response.
     */
    public static function capture_response( $response, $args, $url ) {
        $url = (string) $url;

        if ( false === strpos( $url, self::QUOTE_PATH ) || false === strpos( $url, self::API_HOST ) ) {
            return $response;
        }

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $payload = json_decode( (string) wp_remote_retrieve_body( $response ) );

        // "ShippingSevicesArray" is Frenet's own spelling in the API contract,
        // not a typo on this side — the gateway reads the same key.
        if ( ! is_object( $payload ) || ! isset( $payload->ShippingSevicesArray ) ) {
            return $response;
        }

        foreach ( (array) $payload->ShippingSevicesArray as $service ) {
            if ( ! is_object( $service ) || empty( $service->ServiceCode ) ) {
                continue;
            }

            self::$services[ (string) $service->ServiceCode ] = array(
                'delivery_time' => self::parse_delivery_time( $service ),
                'carrier'       => isset( $service->Carrier ) ? sanitize_text_field( (string) $service->Carrier ) : '',
                'description'   => isset( $service->ServiceDescription ) ? sanitize_text_field( (string) $service->ServiceDescription ) : '',
            );
        }

        return $response;
    }


    /**
     * Data captured for a service code.
     *
     * @since 3.0.0
     * @param string $service_code Frenet service code.
     * @return array<string,mixed> Empty array when the code was not captured.
     */
    public static function get( $service_code ) {
        $service_code = (string) $service_code;

        return isset( self::$services[ $service_code ] ) ? self::$services[ $service_code ] : array();
    }


    /**
     * Read the delivery forecast off a service entry.
     *
     * Frenet answers with a string ("3"), and an unavailable service reports
     * zero rather than omitting the field — which would otherwise be read as
     * "same-day delivery" and promise the shopper a date the carrier never
     * offered.
     *
     * @since 3.0.0
     * @param object $service Service entry from the API response.
     * @return int|null Business days, or null when the service did not report one.
     */
    private static function parse_delivery_time( $service ) {
        if ( ! isset( $service->DeliveryTime ) || ! is_numeric( $service->DeliveryTime ) ) {
            return null;
        }

        $days = (int) $service->DeliveryTime;

        return $days > 0 ? $days : null;
    }
}
