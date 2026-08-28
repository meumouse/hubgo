<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\Address\Address_Provider;
use MeuMouse\Hubgo\Core\Address\Google_Maps_Provider;

defined('ABSPATH') || exit;

/**
 * Google Maps integration — the address layer of the shipping calculator.
 *
 * Unlike every other card, this one depends on no plugin: the dependency is an
 * API key the store owner creates on Google Cloud. So there is nothing to
 * install, `is_plugin` stays false, and the card explains what to enable
 * instead of offering a one-click button.
 *
 * Switching it on gives the calculator two things:
 *
 * - **Find my postcode.** A free-text address search below the postcode field,
 *   for the shopper who does not know their CEP.
 * - **Name the destination.** Typing a postcode resolves the street it belongs
 *   to, so the quote reads "to Rua Leonor Dugonski (postcode 83407-280)"
 *   instead of echoing the digits back.
 *
 * The two are billed by Google separately — Places API (New) for the first,
 * Geocoding API for the second — so each gets its own toggle. A store that only
 * wants one is not made to pay for both.
 *
 * The wiring runs the other way round from what the file layout suggests:
 * `Core\Address` publishes the `Hubgo/Core/Address/Provider` filter and knows
 * nothing about Google, and this class is what answers it. That is what keeps
 * an install with the card off from ever reaching an external service, and lets
 * a third party register a different provider without touching Core.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Google_Maps extends Integrations_Base {

    /**
     * Card slug.
     *
     * @since 3.0.0
     * @var string
     */
    const CARD_SLUG = 'google_maps';

    /**
     * Option key toggling the integration.
     *
     * @since 3.0.0
     * @var string
     */
    const SETTING_KEY = 'enable_google_maps_integration';

    /**
     * Option key holding the API key.
     *
     * @since 3.0.0
     * @var string
     */
    const API_KEY_SETTING = Google_Maps_Provider::API_KEY_SETTING;

    /**
     * Option key toggling the "I do not know my postcode" finder.
     *
     * @since 3.0.0
     * @var string
     */
    const FINDER_SETTING_KEY = 'google_maps_enable_finder';

    /**
     * Option key toggling the postcode to address lookup.
     *
     * @since 3.0.0
     * @var string
     */
    const LOOKUP_SETTING_KEY = 'google_maps_enable_address_lookup';

    /**
     * Google Cloud console URL where the key and the APIs are managed.
     *
     * @since 3.0.0
     * @var string
     */
    const CONSOLE_URL = 'https://console.cloud.google.com/google/maps-apis/credentials';


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        // Always first: the card has to be listed even when the toggle is off
        // or the key is missing, or the user can never enable it.
        $this->register_integration_card( 40 );

        if ( ! self::is_enabled() || ! self::is_configured() ) {
            return;
        }

        add_filter( 'Hubgo/Core/Address/Provider', array( $this, 'register_provider' ) );
        add_filter( 'Hubgo/Core/Address/Finder_Enabled', array( $this, 'filter_finder_enabled' ), 10, 2 );
        add_filter( 'Hubgo/Core/Address/Lookup_Enabled', array( $this, 'filter_lookup_enabled' ), 10, 2 );
    }


    /**
     * Whether the integration is switched on.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === Settings::get_setting( self::SETTING_KEY, 'no' );
    }


    /**
     * Whether an API key is in place.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_configured() {
        return '' !== Google_Maps_Provider::get_api_key();
    }


    /**
     * Answer the address provider filter with the Google Maps provider.
     *
     * A provider registered by something else wins: this class is the bundled
     * default, not an override.
     *
     * @since 3.0.0
     * @param Address_Provider|null $provider Provider registered so far.
     * @return Address_Provider
     */
    public function register_provider( $provider ) {
        if ( $provider instanceof Address_Provider ) {
            return $provider;
        }

        return new Google_Maps_Provider();
    }


    /**
     * Gate the address finder on its own toggle.
     *
     * @since 3.0.0
     * @param bool $enabled Whether the finder is available.
     * @param Address_Provider $provider Active provider.
     * @return bool
     */
    public function filter_finder_enabled( $enabled, $provider ) {
        if ( ! $provider instanceof Google_Maps_Provider ) {
            return $enabled;
        }

        return 'yes' === Settings::get_setting( self::FINDER_SETTING_KEY, 'yes' );
    }


    /**
     * Gate the postcode lookup on its own toggle.
     *
     * @since 3.0.0
     * @param bool $enabled Whether the lookup runs.
     * @param Address_Provider $provider Active provider.
     * @return bool
     */
    public function filter_lookup_enabled( $enabled, $provider ) {
        if ( ! $provider instanceof Google_Maps_Provider ) {
            return $enabled;
        }

        return 'yes' === Settings::get_setting( self::LOOKUP_SETTING_KEY, 'yes' );
    }


    /**
     * Register the card on the Integrations screen.
     *
     * @since 3.0.0
     * @param array $integrations Current catalog.
     * @return array
     */
    public function add_integration_item( $integrations ) {
        $integrations[ self::CARD_SLUG ] = array(
            'title'       => __( 'Google Maps', 'hubgo' ),
            'description' => __( 'Let shoppers find their postcode by typing the address, and name the destination street on the shipping quote.', 'hubgo' ),
            'icon'        => $this->get_icon_svg(),
            'author'      => 'Google',
            'author_url'  => 'https://mapsplatform.google.com/',
            'category'    => 'others',
            'setting_key' => self::SETTING_KEY,
            'is_plugin'   => false,
            'doc_url'     => 'https://developers.google.com/maps/documentation/places/web-service/op-overview',
            'settings'    => array(
                self::field_password(
                    self::API_KEY_SETTING,
                    __( 'Google API key', 'hubgo' ),
                    __( 'The key stays on the server and is never sent to the browser.', 'hubgo' ),
                    array( 'placeholder' => 'AIza...' )
                ),
                // Both spell their default out rather than letting the field
                // type infer one: an inferred toggle defaults to 'no', and a
                // card advertising a default that contradicts
                // Default_Options is a bug waiting for the first reader of
                // the card's `defaults` block.
                self::field_toggle(
                    self::FINDER_SETTING_KEY,
                    __( 'Find postcode by address', 'hubgo' ),
                    __( 'Shows a link below the postcode field that opens a free-text address search. Uses the Places API.', 'hubgo' ),
                    array( 'default' => 'yes' )
                ),
                self::field_toggle(
                    self::LOOKUP_SETTING_KEY,
                    __( 'Show the destination address', 'hubgo' ),
                    __( 'Names the street the informed postcode belongs to on the quote and in the delivery options window. Uses the Geocoding API.', 'hubgo' ),
                    array( 'default' => 'yes' )
                ),
            ),
            'modal'       => array(
                'title'       => __( 'Google Maps', 'hubgo' ),
                'description' => __( 'Addresses for the shipping calculator, served by your own Google Cloud project.', 'hubgo' ),
                'size'        => 'medium',
                'blocks'      => $this->get_modal_blocks(),
            ),
        );

        return $integrations;
    }


    /**
     * Content blocks explaining the setup.
     *
     * The "no key yet" notice is built here rather than in the frontend because
     * this card declares no plugin dependency, so the generic
     * `disabled_message` the Integrations screen computes never covers it — an
     * enabled card with an empty key would otherwise look fully configured and
     * silently do nothing.
     *
     * @since 3.0.0
     * @return array<int,array<string,mixed>>
     */
    private function get_modal_blocks() {
        $blocks = array();

        if ( self::is_enabled() && ! self::is_configured() ) {
            $blocks[] = self::modal_notice_block(
                __( 'The address features stay off until an API key is saved below.', 'hubgo' ),
                'warning'
            );
        }

        // An HTML block rather than a notice: this one carries a link, and the
        // notice block renders as plain text. Escaped here because the frontend
        // paints HTML blocks with v-html.
        $blocks[] = self::modal_html_block( sprintf(
            '<p>%s</p>',
            sprintf(
                /* translators: %s: link to the Google Cloud credentials page. */
                esc_html__( 'Create a key at %s and enable two APIs on the same project: "Places API (New)" for the address search, and "Geocoding API" for the destination address shown on the quote.', 'hubgo' ),
                '<a href="' . esc_url( self::CONSOLE_URL ) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html__( 'Google Cloud Console', 'hubgo' )
                    . '</a>'
            )
        ) );

        $blocks[] = self::modal_notice_block(
            __( 'Both APIs are billed by Google to your own account, so restrict the key to your server. HubGo keeps the cost flat by caching each resolved postcode for a month and capping how many new ones it looks up per day.', 'hubgo' ),
            'info'
        );

        return $blocks;
    }


    /**
     * Inline brand icon for the card.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_icon_svg() {
        return self::get_brand_svg( 'google-maps-logo.svg', __( 'Google Maps', 'hubgo' ) );
    }
}
