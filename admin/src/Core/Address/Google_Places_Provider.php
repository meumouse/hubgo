<?php

namespace MeuMouse\Hubgo\Core\Address;

use MeuMouse\Hubgo\Admin\Settings;

use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Address lookup backed by the Google Places API (New).
 *
 * Opt-in provider: it delivers the free-text autocomplete shoppers expect, at
 * the cost of a billable Google Cloud key. The key lives in the settings and
 * never leaves the server — the storefront only ever sees `hubgo/v1/address/*`.
 *
 * Google bills autocomplete per *session*, not per keystroke, as long as every
 * request in one search carries the same session token and the session is
 * closed by a Place Details call. The storefront mints that token, sends it on
 * every request and rotates it after resolving, which is what keeps a search
 * costing one lookup instead of one per typed character.
 *
 * Requires the "Places API (New)" to be enabled on the key. The legacy
 * `maps.googleapis.com/maps/api/place` endpoints are not used: Google has them
 * on a deprecation path.
 *
 * @since 3.1.0
 * @package MeuMouse\Hubgo\Core\Address
 * @author MeuMouse.com
 */
class Google_Places_Provider extends Address_Provider {

    /**
     * Provider identifier.
     *
     * @since 3.1.0
     * @var string
     */
    const ID = 'google';

    /**
     * Autocomplete endpoint.
     *
     * @since 3.1.0
     * @var string
     */
    const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    /**
     * Place details endpoint template.
     *
     * @since 3.1.0
     * @var string
     */
    const DETAILS_URL = 'https://places.googleapis.com/v1/places/%s';

    /**
     * Transient prefix for cached place resolutions.
     *
     * @since 3.1.0
     * @var string
     */
    const CACHE_PREFIX = 'hubgo_places_';

    /**
     * Cache lifetime for a resolved place, in seconds.
     *
     * @since 3.1.0
     * @var int
     */
    const CACHE_TTL = WEEK_IN_SECONDS;

    /**
     * Minimum characters before a query is worth billing.
     *
     * @since 3.1.0
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
        return '' !== $this->get_api_key();
    }


    /**
     * @inheritDoc
     */
    public function get_label() {
        return esc_html__( 'Google Places (requer chave de API)', 'hubgo' );
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
            return new WP_Error( 'hubgo_address_unconfigured', esc_html__( 'A busca de endereços não está configurada.', 'hubgo' ) );
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
            'X-Goog-Api-Key'    => $this->get_api_key(),
            'X-Goog-FieldMask'  => 'suggestions.placePrediction.placeId,suggestions.placePrediction.structuredFormat',
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
            return new WP_Error( 'hubgo_address_invalid_place', esc_html__( 'Endereço inválido. Selecione uma das sugestões.', 'hubgo' ) );
        }

        if ( ! $this->is_configured() ) {
            return new WP_Error( 'hubgo_address_unconfigured', esc_html__( 'A busca de endereços não está configurada.', 'hubgo' ) );
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
            'X-Goog-Api-Key'   => $this->get_api_key(),
            'X-Goog-FieldMask' => 'addressComponents',
        ) );

        if ( is_wp_error( $body ) ) {
            return $body;
        }

        $postcode = $this->extract_postcode( $body['addressComponents'] ?? array() );

        if ( '' === $postcode ) {
            return new WP_Error(
                'hubgo_address_no_postcode',
                esc_html__( 'Esse endereço não tem um CEP exato. Tente incluir o número e a rua.', 'hubgo' )
            );
        }

        set_transient( $cache_key, $postcode, self::CACHE_TTL );

        return $postcode;
    }


    /**
     * Map Places predictions to normalized suggestions.
     *
     * @since 3.1.0
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
     * Pull the postal code out of a Place Details address component list.
     *
     * @since 3.1.0
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
     * @since 3.1.0
     * @return string
     */
    private function get_api_key() {
        return trim( (string) Settings::get_setting( 'google_places_api_key', '' ) );
    }


    /**
     * Region the autocomplete is restricted to.
     *
     * @since 3.1.0
     * @return string
     */
    private function get_region_code() {
        $country = function_exists( 'WC' ) && WC() ? (string) WC()->countries->get_base_country() : 'BR';
        $country = '' !== $country ? $country : 'BR';

        /**
         * Filters the region code the address autocomplete is restricted to.
         *
         * @since 3.1.0
         * @param string $country Two letter region code.
         */
        return strtolower( apply_filters( 'Hubgo/Core/Address_Lookup/Region_Code', $country ) );
    }


    /**
     * Language the suggestions should come back in.
     *
     * @since 3.1.0
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
     * @since 3.1.0
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
     * @since 3.1.0
     * @param string $place_id Raw place id.
     * @return string
     */
    private function normalize_place_id( $place_id ) {
        $place_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $place_id );

        return substr( (string) $place_id, 0, 255 );
    }
}
