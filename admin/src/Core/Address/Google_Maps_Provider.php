<?php

namespace MeuMouse\Hubgo\Core\Address;

use MeuMouse\Hubgo\Admin\Settings;

use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Address provider backed by Google Maps Platform.
 *
 * Registered by {@see \MeuMouse\Hubgo\Integrations\Google_Maps} once the card is
 * enabled and a key is in place. Two APIs are involved, and both have to be
 * turned on for the project the key belongs to:
 *
 * - **Places API (New)** — the free-text autocomplete the "I do not know my
 *   postcode" finder is built on, plus the Place Details call that turns the
 *   picked suggestion into a postcode.
 * - **Geocoding API** — the reverse direction: the street a postcode sits on,
 *   which is what lets the calculator say "to Rua X" instead of only echoing
 *   the digits back.
 *
 * Google bills autocomplete per *session*, not per keystroke, as long as every
 * request in one search carries the same session token and the session is
 * closed by a Place Details call. The storefront mints that token, sends it on
 * every request and rotates it after resolving, which is what keeps a search
 * costing one lookup instead of one per typed character.
 *
 * The key lives in the settings and never leaves the server — the storefront
 * only ever sees `hubgo/v1/address/*`.
 *
 * The legacy `maps.googleapis.com/maps/api/place` endpoints are not used:
 * Google has them on a deprecation path. Geocoding has no "new" counterpart and
 * is called on its documented endpoint.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core\Address
 * @author MeuMouse.com
 */
class Google_Maps_Provider extends Address_Provider {

    /**
     * Provider identifier.
     *
     * @since 3.0.0
     * @var string
     */
    const ID = 'google_maps';

    /**
     * Setting holding the API key.
     *
     * @since 3.0.0
     * @var string
     */
    const API_KEY_SETTING = 'google_maps_api_key';

    /**
     * Autocomplete endpoint.
     *
     * @since 3.0.0
     * @var string
     */
    const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    /**
     * Place details endpoint template.
     *
     * @since 3.0.0
     * @var string
     */
    const DETAILS_URL = 'https://places.googleapis.com/v1/places/%s';

    /**
     * Geocoding endpoint.
     *
     * @since 3.0.0
     * @var string
     */
    const GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Transient prefix for cached place resolutions.
     *
     * @since 3.0.0
     * @var string
     */
    const CACHE_PREFIX = 'hubgo_places_';

    /**
     * Cache lifetime for a resolved place, in seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const CACHE_TTL = WEEK_IN_SECONDS;

    /**
     * Minimum characters before a query is worth billing.
     *
     * @since 3.0.0
     * @var int
     */
    const MIN_QUERY_LENGTH = 3;


    /**
     * @inheritDoc
     */
    public function get_id() {
        return self::ID;
    }


    /**
     * @inheritDoc
     */
    public function get_mode() {
        return self::MODE_FREETEXT;
    }


    /**
     * @inheritDoc
     */
    public function is_configured() {
        return '' !== self::get_api_key();
    }


    /**
     * @inheritDoc
     */
    public function get_label() {
        return __( 'Google Maps', 'hubgo' );
    }


    /**
     * @inheritDoc
     */
    public function search( $args ) {
        $query = trim( (string) ( $args['q'] ?? '' ) );

        if ( mb_strlen( $query ) < self::MIN_QUERY_LENGTH ) {
            return array();
        }

        if ( ! $this->is_configured() ) {
            return new WP_Error( 'hubgo_address_unconfigured', __( 'Address lookup is not configured.', 'hubgo' ) );
        }

        $payload = array(
            'input'               => $query,
            'includedRegionCodes' => array( $this->get_region_code() ),
            'languageCode'        => $this->get_language_code(),
        );

        $session = $this->normalize_session( $args['session'] ?? '' );

        if ( '' !== $session ) {
            $payload['sessionToken'] = $session;
        }

        $body = $this->post_json( self::AUTOCOMPLETE_URL, $payload, array(
            'X-Goog-Api-Key'   => self::get_api_key(),
            'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.structuredFormat',
        ) );

        if ( is_wp_error( $body ) ) {
            return $body;
        }

        return $this->map_suggestions( $body['suggestions'] ?? array() );
    }


    /**
     * @inheritDoc
     */
    public function resolve( $args ) {
        $place_id = $this->normalize_place_id( (string) ( $args['id'] ?? '' ) );

        if ( '' === $place_id ) {
            return new WP_Error( 'hubgo_address_invalid_place', __( 'Invalid address. Select one of the suggestions.', 'hubgo' ) );
        }

        if ( ! $this->is_configured() ) {
            return new WP_Error( 'hubgo_address_unconfigured', __( 'Address lookup is not configured.', 'hubgo' ) );
        }

        $cache_key = self::CACHE_PREFIX . md5( $place_id );
        $cached    = get_transient( $cache_key );

        if ( is_string( $cached ) && '' !== $cached ) {
            return $cached;
        }

        $url     = sprintf( self::DETAILS_URL, rawurlencode( $place_id ) );
        $session = $this->normalize_session( $args['session'] ?? '' );

        if ( '' !== $session ) {
            $url = add_query_arg( 'sessionToken', rawurlencode( $session ), $url );
        }

        $body = $this->get_json( $url, array(
            'X-Goog-Api-Key'   => self::get_api_key(),
            'X-Goog-FieldMask' => 'addressComponents',
        ) );

        if ( is_wp_error( $body ) ) {
            return $body;
        }

        $postcode = $this->extract_postcode( $body['addressComponents'] ?? array() );

        if ( '' === $postcode ) {
            return new WP_Error(
                'hubgo_address_no_postcode',
                __( 'That address has no exact postcode. Try including the street and the number.', 'hubgo' )
            );
        }

        set_transient( $cache_key, $postcode, self::CACHE_TTL );

        return $postcode;
    }


    /**
     * @inheritDoc
     *
     * Filtering on `components` rather than passing the postcode as free-text
     * `address` is what keeps the answer trustworthy: a bare number can match a
     * street number, a building or a business, while the component filter can
     * only ever return the postal area itself.
     */
    public function lookup_postcode( $postcode, $country ) {
        $postcode = self::normalize_postcode( $postcode );
        $country  = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $country ), 0, 2 ) );

        if ( '' === $postcode ) {
            return new WP_Error( 'hubgo_address_invalid_postcode', __( 'Invalid postcode.', 'hubgo' ) );
        }

        if ( ! $this->is_configured() ) {
            return new WP_Error( 'hubgo_address_unconfigured', __( 'Address lookup is not configured.', 'hubgo' ) );
        }

        $components = 'postal_code:' . $postcode;

        if ( 2 === strlen( $country ) ) {
            $components .= '|country:' . $country;
        }

        $url = add_query_arg( array(
            'components' => rawurlencode( $components ),
            'language'   => rawurlencode( $this->get_language_code() ),
            'key'        => rawurlencode( self::get_api_key() ),
        ), self::GEOCODE_URL );

        // Inline timeout: this runs while a shopper waits on a shipping quote.
        $body = $this->get_json( $url, array(), self::INLINE_TIMEOUT );

        if ( is_wp_error( $body ) ) {
            return $body;
        }

        // Geocoding answers 200 for its own failures and reports them in the
        // body, so the HTTP status alone never tells us whether this worked.
        $status = strtoupper( (string) ( $body['status'] ?? '' ) );

        if ( 'ZERO_RESULTS' === $status ) {
            return self::get_empty_address();
        }

        if ( 'OK' !== $status ) {
            return new WP_Error( 'hubgo_address_geocode', __( 'Could not resolve that postcode right now.', 'hubgo' ) );
        }

        $result = isset( $body['results'][0] ) && is_array( $body['results'][0] ) ? $body['results'][0] : array();

        return $this->map_address_components( $result['address_components'] ?? array() );
    }


    /**
     * Map Places predictions to normalized suggestions.
     *
     * @since 3.0.0
     * @param array $predictions Raw `suggestions` array.
     * @return array<int,array<string,string>>
     */
    private function map_suggestions( $predictions ) {
        $suggestions = array();

        foreach ( (array) $predictions as $prediction ) {
            $place = $prediction['placePrediction'] ?? array();

            if ( empty( $place['placeId'] ) ) {
                continue;
            }

            $format = $place['structuredFormat'] ?? array();

            // Google returns an empty postcode here on purpose: it is only
            // available through Place Details, which is what closes the
            // billing session.
            $suggestions[] = $this->suggestion(
                (string) $place['placeId'],
                (string) ( $format['mainText']['text'] ?? '' ),
                (string) ( $format['secondaryText']['text'] ?? '' )
            );
        }

        return $suggestions;
    }


    /**
     * Turn a Geocoding component list into a normalized address.
     *
     * The component types are tried in order of how specific they are. `city`
     * is the awkward one in Brazil: Google files the municipality under
     * `administrative_area_level_2`, while `locality` is often the district a
     * shopper would not recognize as their city — so the former wins when both
     * are present.
     *
     * @since 3.0.0
     * @param array $components Raw `address_components` array.
     * @return array<string,string>
     */
    private function map_address_components( $components ) {
        $map = array(
            'street'       => array( 'route' ),
            'neighborhood' => array( 'sublocality_level_1', 'sublocality', 'neighborhood' ),
            'city'         => array( 'administrative_area_level_2', 'locality', 'postal_town' ),
        );

        $parts = array( 'street' => '', 'neighborhood' => '', 'city' => '', 'state' => '' );

        foreach ( $map as $key => $types ) {
            foreach ( $types as $type ) {
                $value = $this->find_component( $components, $type );

                if ( '' !== $value ) {
                    $parts[ $key ] = $value;
                    break;
                }
            }
        }

        // The state is the only part read from `short_name`: WooCommerce zones
        // and every carrier speak in two-letter codes, not in "Paraná".
        $parts['state'] = $this->find_component( $components, 'administrative_area_level_1', true );

        return $this->address( $parts );
    }


    /**
     * Read one component out of a Geocoding component list.
     *
     * @since 3.0.0
     * @param array $components Raw `address_components` array.
     * @param string $type Component type to look for.
     * @param bool $short Whether to prefer `short_name` over `long_name`.
     * @return string
     */
    private function find_component( $components, $type, $short = false ) {
        foreach ( (array) $components as $component ) {
            $types = isset( $component['types'] ) ? (array) $component['types'] : array();

            if ( ! in_array( $type, $types, true ) ) {
                continue;
            }

            $value = $short
                ? ( $component['short_name'] ?? $component['long_name'] ?? '' )
                : ( $component['long_name'] ?? $component['short_name'] ?? '' );

            if ( '' !== (string) $value ) {
                return (string) $value;
            }
        }

        return '';
    }


    /**
     * Pull the postal code out of a Place Details address component list.
     *
     * @since 3.0.0
     * @param array $components Raw `addressComponents` array.
     * @return string
     */
    private function extract_postcode( $components ) {
        foreach ( (array) $components as $component ) {
            $types = isset( $component['types'] ) ? (array) $component['types'] : array();

            if ( ! in_array( 'postal_code', $types, true ) ) {
                continue;
            }

            $postcode = self::normalize_postcode( (string) ( $component['longText'] ?? $component['shortText'] ?? '' ) );

            if ( '' !== $postcode ) {
                return $postcode;
            }
        }

        return '';
    }


    /**
     * Configured Google API key.
     *
     * Static so the integration can report the card as configured without
     * instantiating a provider it may never use.
     *
     * @since 3.0.0
     * @return string
     */
    public static function get_api_key() {
        return trim( (string) Settings::get_setting( self::API_KEY_SETTING, '' ) );
    }


    /**
     * Region the autocomplete is restricted to.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_region_code() {
        $country = function_exists( 'WC' ) && WC() ? (string) WC()->countries->get_base_country() : 'BR';
        $country = '' !== $country ? $country : 'BR';

        /**
         * Filters the region code the address autocomplete is restricted to.
         *
         * @since 3.0.0
         * @param string $country Two letter region code.
         */
        return strtolower( apply_filters( 'Hubgo/Core/Address/Region_Code', $country ) );
    }


    /**
     * Language the results should come back in.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_language_code() {
        $locale = str_replace( '_', '-', (string) determine_locale() );

        return '' !== $locale ? $locale : 'pt-BR';
    }


    /**
     * Constrain a client-supplied session token to a safe shape.
     *
     * The token reaches Google verbatim, so it is limited to the character set
     * a UUID uses rather than forwarded as-is.
     *
     * @since 3.0.0
     * @param string $session Raw token.
     * @return string
     */
    private function normalize_session( $session ) {
        $session = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) $session );

        return substr( (string) $session, 0, 64 );
    }


    /**
     * Constrain a client-supplied place id to a safe shape.
     *
     * @since 3.0.0
     * @param string $place_id Raw place id.
     * @return string
     */
    private function normalize_place_id( $place_id ) {
        $place_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $place_id );

        return substr( (string) $place_id, 0, 255 );
    }
}
