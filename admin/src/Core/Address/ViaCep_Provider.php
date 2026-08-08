<?php

namespace MeuMouse\Hubgo\Core\Address;

use WP_Error;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Address lookup backed by ViaCEP.
 *
 * The default provider: it needs no credential and no billing account, so the
 * "I do not know my postcode" flow works on every install out of the box.
 *
 * ViaCEP has no free-text endpoint — its reverse search is keyed on state,
 * city and street — hence MODE_STRUCTURED. In exchange it returns the postcode
 * inline with each match, so picking a suggestion never costs a second request.
 *
 * Results are cached because the street/postcode mapping is effectively static
 * and shoppers on the same street would otherwise each hit the service.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core\Address
 * @author MeuMouse.com
 */
class ViaCep_Provider extends Address_Provider {

    /**
     * Provider identifier.
     *
     * @since 3.0.0
     * @var string
     */
    const ID = 'viacep';

    /**
     * Reverse search endpoint template: state / city / street.
     *
     * @since 3.0.0
     * @var string
     */
    const SEARCH_URL = 'https://viacep.com.br/ws/%1$s/%2$s/%3$s/json/';

    /**
     * Transient prefix for cached searches.
     *
     * @since 3.0.0
     * @var string
     */
    const CACHE_PREFIX = 'hubgo_viacep_';

    /**
     * Cache lifetime for a search, in seconds.
     *
     * @since 3.0.0
     * @var int
     */
    const CACHE_TTL = DAY_IN_SECONDS;

    /**
     * Minimum characters ViaCEP requires for the city and street terms.
     *
     * @since 3.0.0
     * @var int
     */
    const MIN_TERM_LENGTH = 3;

    /**
     * Upper bound on returned suggestions.
     *
     * @since 3.0.0
     * @var int
     */
    const MAX_RESULTS = 25;


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
        return self::MODE_STRUCTURED;
    }


    /**
     * @inheritDoc
     */
    public function is_configured() {
        return true;
    }


    /**
     * @inheritDoc
     */
    public function get_label() {
        return esc_html__( 'ViaCEP (free)', 'hubgo' );
    }


    /**
     * @inheritDoc
     */
    public function search( $args ) {
        $uf     = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) ( $args['uf'] ?? '' ) ), 0, 2 ) );
        $city   = trim( (string) ( $args['city'] ?? '' ) );
        $street = trim( (string) ( $args['street'] ?? '' ) );

        if ( 2 !== strlen( $uf ) ) {
            return new WP_Error( 'hubgo_address_invalid_uf', esc_html__( 'Select the state.', 'hubgo' ) );
        }

        if ( mb_strlen( $city ) < self::MIN_TERM_LENGTH || mb_strlen( $street ) < self::MIN_TERM_LENGTH ) {
            return new WP_Error(
                'hubgo_address_short_query',
                esc_html__( 'Enter at least 3 letters of the city and of the street.', 'hubgo' )
            );
        }

        $cache_key = self::CACHE_PREFIX . md5( $uf . '|' . mb_strtolower( $city ) . '|' . mb_strtolower( $street ) );
        $cached    = get_transient( $cache_key );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $url = sprintf(
            self::SEARCH_URL,
            rawurlencode( $uf ),
            rawurlencode( $city ),
            rawurlencode( $street )
        );

        $body = $this->get_json( $url );

        if ( is_wp_error( $body ) ) {
            return $body;
        }

        // ViaCEP answers `{"erro": true}` (an object, not a list) when the
        // combination matches nothing. That is an empty result, not a failure.
        if ( ! isset( $body[0] ) ) {
            set_transient( $cache_key, array(), self::CACHE_TTL );

            return array();
        }

        $suggestions = $this->map_suggestions( $body );

        set_transient( $cache_key, $suggestions, self::CACHE_TTL );

        return $suggestions;
    }


    /**
     * @inheritDoc
     *
     * The search already carried the postcode, so the id IS the postcode and
     * no network call is needed.
     */
    public function resolve( $args ) {
        $postcode = self::normalize_postcode( (string) ( $args['id'] ?? '' ) );

        if ( '' === $postcode ) {
            return new WP_Error( 'hubgo_address_not_found', esc_html__( 'Could not resolve the postcode for that address.', 'hubgo' ) );
        }

        return $postcode;
    }


    /**
     * Map ViaCEP rows to normalized suggestions.
     *
     * @since 3.0.0
     * @param array $rows Decoded ViaCEP response.
     * @return array<int,array<string,string>>
     */
    private function map_suggestions( $rows ) {
        $suggestions = array();

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $postcode = self::normalize_postcode( $row['cep'] ?? '' );

            if ( '' === $postcode ) {
                continue;
            }

            $secondary = array_filter( array(
                (string) ( $row['bairro'] ?? '' ),
                (string) ( $row['localidade'] ?? '' ),
                (string) ( $row['uf'] ?? '' ),
            ) );

            // The postcode doubles as the identifier: resolve() is a no-op.
            $suggestions[] = $this->suggestion(
                $postcode,
                (string) ( $row['logradouro'] ?? '' ),
                implode( ', ', $secondary ),
                $postcode
            );

            if ( count( $suggestions ) >= self::MAX_RESULTS ) {
                break;
            }
        }

        return $suggestions;
    }
}
