<?php

namespace MeuMouse\Hubgo\Core\Address;

use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Base class for the "I do not know my postcode" address lookup providers.
 *
 * A provider answers two questions: which addresses match what the shopper
 * typed, and which postcode belongs to the one they picked. How it collects the
 * query differs — some services take free text, others need the state, city and
 * street separately — so each provider declares its `mode` and the storefront
 * modal renders the matching form. That keeps a single Vue component serving
 * every provider.
 *
 * Providers never run in the browser: whatever credential they need stays on
 * the server and the storefront only ever talks to `hubgo/v1/address/*`.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core\Address
 * @author MeuMouse.com
 */
abstract class Address_Provider {

    /**
     * Provider takes a single free-text query.
     *
     * @since 3.0.0
     * @var string
     */
    const MODE_FREETEXT = 'freetext';

    /**
     * Provider needs the state, city and street as separate fields.
     *
     * @since 3.0.0
     * @var string
     */
    const MODE_STRUCTURED = 'structured';

    /**
     * Outbound request timeout, in seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const TIMEOUT = 8;


    /**
     * Provider identifier, as stored in the settings.
     *
     * @since 3.0.0
     * @return string
     */
    abstract public function get_id();


    /**
     * How the storefront should collect the query.
     *
     * @since 3.0.0
     * @return string One of MODE_FREETEXT|MODE_STRUCTURED.
     */
    abstract public function get_mode();


    /**
     * Whether the provider holds everything it needs to answer.
     *
     * @since 3.0.0
     * @return bool
     */
    abstract public function is_configured();


    /**
     * Search addresses matching the shopper's input.
     *
     * @since 3.0.0
     * @param array $args Sanitized query parts: q, uf, city, street, session.
     * @return array<int,array<string,string>>|WP_Error Suggestions or an error.
     */
    abstract public function search( $args );


    /**
     * Resolve the postcode of a previously suggested address.
     *
     * @since 3.0.0
     * @param array $args Sanitized args: id, session.
     * @return string|WP_Error Eight-digit postcode, or an error.
     */
    abstract public function resolve( $args );


    /**
     * Human-readable label shown on the settings screen.
     *
     * @since 3.0.0
     * @return string
     */
    public function get_label() {
        return $this->get_id();
    }


    /**
     * Build a normalized suggestion row.
     *
     * `postcode` is filled by providers that already know it at search time —
     * the storefront then skips the resolve round-trip entirely.
     *
     * @since 3.0.0
     * @param string $id Opaque identifier the provider can resolve later.
     * @param string $primary Main line (street, number).
     * @param string $secondary Supporting line (neighbourhood, city, state).
     * @param string $postcode Postcode, when already known.
     * @return array<string,string>
     */
    protected function suggestion( $id, $primary, $secondary = '', $postcode = '' ) {
        return array(
            'id'        => (string) $id,
            'primary'   => sanitize_text_field( (string) $primary ),
            'secondary' => sanitize_text_field( (string) $secondary ),
            'postcode'  => self::normalize_postcode( $postcode ),
        );
    }


    /**
     * Reduce a postcode to its eight digits, or an empty string.
     *
     * @since 3.0.0
     * @param string $postcode Raw postcode.
     * @return string
     */
    public static function normalize_postcode( $postcode ) {
        $digits = preg_replace( '/\D/', '', (string) $postcode );

        return ( 8 === strlen( (string) $digits ) ) ? $digits : '';
    }


    /**
     * Perform a GET request and decode its JSON body.
     *
     * @since 3.0.0
     * @param string $url Absolute URL.
     * @param array $headers Extra request headers.
     * @return array|WP_Error Decoded body, or an error.
     */
    protected function get_json( $url, $headers = array() ) {
        $response = wp_remote_get( $url, array(
            'timeout' => self::TIMEOUT,
            'headers' => array_merge( array( 'Accept' => 'application/json' ), $headers ),
        ) );

        return $this->decode_response( $response );
    }


    /**
     * Perform a POST request with a JSON body and decode the JSON response.
     *
     * @since 3.0.0
     * @param string $url Absolute URL.
     * @param array $body Payload to encode.
     * @param array $headers Extra request headers.
     * @return array|WP_Error Decoded body, or an error.
     */
    protected function post_json( $url, $body, $headers = array() ) {
        $response = wp_remote_post( $url, array(
            'timeout' => self::TIMEOUT,
            'headers' => array_merge( array(
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ), $headers ),
            'body'    => wp_json_encode( $body ),
        ) );

        return $this->decode_response( $response );
    }


    /**
     * Turn an HTTP response into a decoded array or a WP_Error.
     *
     * Upstream error messages are deliberately not forwarded to the storefront:
     * they can echo the API key back, and they are written for developers.
     *
     * @since 3.0.0
     * @param array|WP_Error $response Response from the HTTP API.
     * @return array|WP_Error
     */
    private function decode_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'hubgo_address_transport', __( 'Could not search addresses right now. Please try again.', 'hubgo' ) );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code > 299 ) {
            return new WP_Error( 'hubgo_address_http', __( 'Could not search addresses right now. Please try again.', 'hubgo' ) );
        }

        return is_array( $body ) ? $body : array();
    }
}
