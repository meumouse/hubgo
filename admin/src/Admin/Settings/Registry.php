<?php

namespace MeuMouse\Hubgo\Admin\Settings;

use MeuMouse\Hubgo\Admin\System_Status;
use MeuMouse\Hubgo\Core\License;
use MeuMouse\Hubgo\Integrations\Integrations_Base;

defined('ABSPATH') || exit;

/**
 * Schema-driven settings registry.
 *
 * Builds the settings schema (sections -> cards -> fields) consumed by the Vue
 * SPA, assembles the integrations catalog rendered by the Integrations screen,
 * exposes a flat field-definition map used for sanitization, and packs the REST
 * bootstrap payloads. Everything is filterable for extensibility.
 *
 * A card either declares `fields` (rendered by the shared field registry) or a
 * `component` name (rendered by a page-local Vue component). Integration cards
 * live outside the schema but reuse the very same field definitions, so their
 * values are sanitized and persisted by the same repository.
 *
 * @since 3.0.0
 * @version 3.0.0
 * @package MeuMouse\Hubgo\Admin\Settings
 * @author MeuMouse.com
 */
class Registry {

    /**
     * Build the full settings schema.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return array
     */
    public static function get_schema() {
        $schema = array(
            array(
                'id'          => 'general',
                'title'       => __( 'General', 'hubgo' ),
                'description' => __( 'Enable the HubGo modules and choose where the calculator shows up.', 'hubgo' ),
                'icon'        => 'slider-alt',
                'layout'      => 'fields',
                'cards'       => array(
                    array(
                        'id'          => 'general-features',
                        'title'       => __( 'Features', 'hubgo' ),
                        'description' => __( 'Turn the HubGo modules on or off.', 'hubgo' ),
                        'fields'      => array(
                            self::toggle( 'enable_shipping_calculator', __( 'Shipping calculator', 'hubgo' ), __( 'Displays the shipping calculator on the product page.', 'hubgo' ) ),
                            self::toggle( 'enable_auto_shipping_calculator', __( 'Automatic calculation', 'hubgo' ), __( 'Calculates shipping automatically using the customer saved postcode.', 'hubgo' ) ),
                            self::toggle( 'enable_order_shipped_status', __( '"Order shipped" status', 'hubgo' ), __( 'Adds the order shipped status to WooCommerce.', 'hubgo' ) ),
                            self::toggle( 'enable_order_tracking_admin_ui', __( 'Order tracking (admin)', 'hubgo' ), __( 'Displays the tracking code metabox on the order screen.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'general-calculator',
                        'title'       => __( 'Calculator', 'hubgo' ),
                        'description' => __( 'Where and how the shipping calculator is displayed.', 'hubgo' ),
                        'fields'      => array(
                            self::select( 'hook_display_shipping_calculator', __( 'Display position', 'hubgo' ), __( 'Where to display the calculator on the product page. The last two options place nothing automatically, so the calculator only shows where you insert it.', 'hubgo' ), array(
                                array( 'value' => 'after_cart', 'label' => __( 'After the add to cart button', 'hubgo' ) ),
                                array( 'value' => 'before_cart', 'label' => __( 'Before the add to cart button', 'hubgo' ) ),
                                array( 'value' => 'meta_end', 'label' => __( 'After the product meta', 'hubgo' ) ),
                                array( 'value' => 'elementor', 'label' => __( 'Elementor widget only', 'hubgo' ) ),
                                array( 'value' => 'shortcode', 'label' => __( 'Shortcode only', 'hubgo' ) ),
                            ) ),
                            self::number( 'shipping_handling_days', __( 'Handling days', 'hubgo' ), __( 'Business days added to the carrier estimate before showing the delivery date.', 'hubgo' ), array(
                                'min'  => 0,
                                'max'  => 60,
                                'step' => 1,
                                'unit' => __( 'days', 'hubgo' ),
                            ) ),
                            self::number( 'free_shipping_threshold', __( 'Free shipping amount', 'hubgo' ), __( 'Leave empty to read it automatically from the WooCommerce shipping zone.', 'hubgo' ), array(
                                'min'         => 0,
                                'step'        => 0.01,
                                'placeholder' => __( 'Automatic', 'hubgo' ),
                            ) ),
                        ),
                    ),
                    array(
                        'id'          => 'general-preference',
                        'title'       => __( 'Preferred method', 'hubgo' ),
                        'description' => __( 'Carries the delivery option the customer picked on the product page into the checkout.', 'hubgo' ),
                        'fields'      => array(
                            self::toggle( 'enable_shipping_preference', __( 'Save preferred method', 'hubgo' ), __( 'Pre-selects at the checkout the option the customer picked in the calculator.', 'hubgo' ) ),
                            self::toggle( 'shipping_preference_apply_postcode', __( 'Apply the postcode at the checkout', 'hubgo' ), __( 'Fills in the shipping postcode when the customer has not entered an address yet.', 'hubgo' ) ),
                            self::select( 'shipping_preference_fallback', __( 'When the option is unavailable', 'hubgo' ), __( 'What to do when the preferred method is not available for the checkout address.', 'hubgo' ), array(
                                array( 'value' => 'same_method', 'label' => __( 'Use the same carrier, if available', 'hubgo' ) ),
                                array( 'value' => 'exact', 'label' => __( 'Let WooCommerce choose', 'hubgo' ) ),
                            ) ),
                            self::number( 'shipping_preference_ttl', __( 'Preference lifetime', 'hubgo' ), __( 'How many days the customer choice is remembered in their browser.', 'hubgo' ), array(
                                'min'  => 1,
                                'max'  => 365,
                                'step' => 1,
                                'unit' => __( 'days', 'hubgo' ),
                            ) ),
                        ),
                    ),
                ),
            ),
            array(
                'id'          => 'appearance',
                'title'       => __( 'Appearance', 'hubgo' ),
                'description' => __( 'Colors and layout of the shipping table displayed in the store.', 'hubgo' ),
                'icon'        => 'palette',
                'layout'      => 'fields',
                'cards'       => array(
                    array(
                        'id'          => 'appearance-colors',
                        'title'       => __( 'Colors and typography', 'hubgo' ),
                        'description' => __( 'Leave a field empty to keep the HubGo default.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'primary_main_color', __( 'Primary color', 'hubgo' ), __( 'Accent color used on links, icons and the selected option.', 'hubgo' ), array( 'default' => '#008aff' ) ),
                            self::color( 'calc_text_color', __( 'Text color', 'hubgo' ), __( 'Color of the titles and the amounts.', 'hubgo' ), array( 'default' => '#102033' ) ),
                            self::color( 'calc_muted_color', __( 'Secondary text color', 'hubgo' ), __( 'Color of the descriptions and notes.', 'hubgo' ), array( 'default' => '#6b7280' ) ),
                            self::dimension( 'calc_font_size', __( 'Font size', 'hubgo' ), __( 'Base text size of the calculator.', 'hubgo' ), array(
                                'max'         => 72,
                                'placeholder' => '14',
                            ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-surface',
                        'title'       => __( 'Container', 'hubgo' ),
                        'description' => __( 'Box that wraps the whole calculator.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_surface_bg', __( 'Background color', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::color( 'calc_surface_border', __( 'Border color', 'hubgo' ), '', array( 'default' => '#e5e8ec' ) ),
                            self::dimension( 'calc_surface_radius', __( 'Corner radius', 'hubgo' ), '', array( 'max' => 100, 'placeholder' => '12' ) ),
                            self::dimension( 'calc_surface_padding', __( 'Inner spacing', 'hubgo' ), '', array( 'max' => 200, 'placeholder' => '20' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-badge',
                        'title'       => __( 'Free shipping badge', 'hubgo' ),
                        'description' => __( 'Label displayed at the top of the card.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_badge_bg', __( 'Background color', 'hubgo' ), '', array( 'default' => '#059669' ) ),
                            self::color( 'calc_badge_text_color', __( 'Text color', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::dimension( 'calc_badge_radius', __( 'Corner radius', 'hubgo' ), __( 'Use a high value for a pill-shaped badge.', 'hubgo' ), array( 'max' => 100, 'placeholder' => '6' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-input',
                        'title'       => __( 'Postcode field', 'hubgo' ),
                        'description' => __( 'Input where the customer types the postcode.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_input_bg', __( 'Background color', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::color( 'calc_input_border', __( 'Border color', 'hubgo' ), '', array( 'default' => '#d5dae1' ) ),
                            self::dimension( 'calc_input_radius', __( 'Corner radius', 'hubgo' ), '', array( 'max' => 100, 'placeholder' => '10' ) ),
                            self::dimension( 'calc_input_height', __( 'Height', 'hubgo' ), '', array( 'max' => 200, 'placeholder' => '48' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-button',
                        'title'       => __( 'Calculate button', 'hubgo' ),
                        'description' => __( 'Leave empty to use the primary color.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_button_bg', __( 'Background color', 'hubgo' ), '', array( 'default' => '#008aff' ) ),
                            self::color( 'calc_button_hover_bg', __( 'Background color (hover)', 'hubgo' ), '', array( 'default' => '#0069c2' ) ),
                            self::color( 'calc_button_text_color', __( 'Text color', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::dimension( 'calc_button_radius', __( 'Corner radius', 'hubgo' ), '', array( 'max' => 100, 'placeholder' => '10' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-options',
                        'title'       => __( 'Delivery options', 'hubgo' ),
                        'description' => __( 'List of methods displayed in the details window.', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_option_border', __( 'Border color', 'hubgo' ), '', array( 'default' => '#e5e8ec' ) ),
                            self::color( 'calc_option_selected_bg', __( 'Selected option background', 'hubgo' ), '', array( 'default' => '#f4f9ff' ) ),
                            self::dimension( 'calc_option_radius', __( 'Corner radius', 'hubgo' ), '', array( 'max' => 100, 'placeholder' => '10' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-modal',
                        'title'       => __( 'Details window', 'hubgo' ),
                        'description' => __( 'Modal opened by "More details and delivery methods".', 'hubgo' ),
                        'fields'      => array(
                            self::color( 'calc_modal_bg', __( 'Background color', 'hubgo' ), '', array( 'default' => '#ffffff' ) ),
                            self::color( 'calc_muted_bg', __( 'Address box background', 'hubgo' ), __( 'Neutral fill behind the postcode shown at the top of the window.', 'hubgo' ), array( 'default' => '#f7f8f9' ) ),
                            self::dimension( 'calc_modal_radius', __( 'Corner radius', 'hubgo' ), '', array( 'max' => 100, 'placeholder' => '14' ) ),
                            self::dimension( 'calc_modal_blur', __( 'Backdrop blur', 'hubgo' ), __( 'How much the page behind the window is blurred. Zero disables the effect.', 'hubgo' ), array(
                                'max'         => 100,
                                'placeholder' => '8',
                                // A percentage is not a valid blur() radius.
                                'units'       => array( 'rem', 'em', 'px' ),
                            ) ),
                        ),
                    ),
                    array(
                        'id'          => 'appearance-layout',
                        'title'       => __( 'Layout', 'hubgo' ),
                        'description' => __( 'How the delivery methods are presented to the customer.', 'hubgo' ),
                        'fields'      => array(
                            self::select( 'shipping_methods_display', __( 'Methods display', 'hubgo' ), __( 'Format used to list the returned delivery methods.', 'hubgo' ), array(
                                array( 'value' => 'table', 'label' => __( 'Table', 'hubgo' ) ),
                                array( 'value' => 'list', 'label' => __( 'List', 'hubgo' ) ),
                            ) ),
                        ),
                    ),
                ),
            ),
            array(
                'id'          => 'texts',
                'title'       => __( 'Texts', 'hubgo' ),
                'description' => __( 'Every text displayed by the calculator and by the shipping table.', 'hubgo' ),
                'icon'        => 'align-left',
                'layout'      => 'fields',
                'cards'       => array(
                    array(
                        'id'          => 'texts-calculator',
                        'title'       => __( 'Calculator', 'hubgo' ),
                        'description' => __( 'Texts of the shipping lookup form.', 'hubgo' ),
                        'fields'      => array(
                            self::text( 'text_calculator_title', __( 'Card title', 'hubgo' ), __( 'Leave empty to hide the title.', 'hubgo' ) ),
                            self::text( 'text_info_before_input_shipping_calc', __( 'Information text', 'hubgo' ), __( 'Text displayed above the postcode field.', 'hubgo' ) ),
                            self::text( 'text_placeholder_input_shipping_calc', __( 'Field placeholder', 'hubgo' ), __( 'Example text inside the postcode field.', 'hubgo' ) ),
                            self::text( 'text_button_shipping_calc', __( 'Button text', 'hubgo' ), __( 'Label of the calculate button.', 'hubgo' ) ),
                            self::text( 'text_cep_finder_link', __( '"I do not know my postcode" link', 'hubgo' ), __( 'Only shown while the Google Maps integration is active. Leave empty to hide the link.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'texts-results',
                        'title'       => __( 'Shipping result', 'hubgo' ),
                        'description' => __( 'Headers and notes displayed in the lookup result.', 'hubgo' ),
                        'fields'      => array(
                            self::text( 'text_header_ship', __( '"Delivery" header', 'hubgo' ), __( 'Title of the delivery method column.', 'hubgo' ) ),
                            self::text( 'text_header_value', __( '"Price" header', 'hubgo' ), __( 'Title of the price column.', 'hubgo' ) ),
                            self::text( 'text_more_options', __( 'More options link', 'hubgo' ), __( 'Opens the window with every delivery method.', 'hubgo' ) ),
                            self::textarea( 'note_text_bottom_shipping_calc', __( 'Footer note', 'hubgo' ), __( 'Note displayed below the result.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'texts-free-shipping',
                        'title'       => __( 'Free shipping', 'hubgo' ),
                        'description' => __( 'Badge displayed at the top of the card. Leave both empty to hide it.', 'hubgo' ),
                        'fields'      => array(
                            self::text( 'text_free_shipping_badge', __( 'Badge before the threshold', 'hubgo' ), __( 'Use %s where the minimum amount should appear.', 'hubgo' ) ),
                            self::text( 'text_free_shipping_active', __( 'Badge once reached', 'hubgo' ), __( 'Displayed when the product already qualifies for free shipping.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'texts-preference',
                        'title'       => __( 'Preferred method', 'hubgo' ),
                        'description' => __( 'Texts of the choice carried into the checkout.', 'hubgo' ),
                        'fields'      => array(
                            self::text( 'text_preference_hint', __( 'Hint on the options list', 'hubgo' ), __( 'Displayed below the delivery methods.', 'hubgo' ) ),
                            self::text( 'text_preference_saved', __( 'Choice confirmation', 'hubgo' ), __( 'Displayed after the customer picks an option.', 'hubgo' ) ),
                            self::text( 'text_clear_preference', __( 'Clear link', 'hubgo' ), __( 'Leave empty to not allow clearing the preference.', 'hubgo' ) ),
                        ),
                    ),
                ),
            ),
            array(
                'id'          => 'about',
                'title'       => __( 'About', 'hubgo' ),
                'description' => __( 'Maintenance, updates and environment information.', 'hubgo' ),
                'icon'        => 'info-circle',
                'layout'      => 'mixed',
                'cards'       => array(
                    array(
                        'id'          => 'about-maintenance',
                        'title'       => __( 'Maintenance and preferences', 'hubgo' ),
                        'description' => __( 'Operational behaviour of the plugin.', 'hubgo' ),
                        'fields'      => array(
                            self::toggle( 'enable_auto_updates', __( 'Automatic updates', 'hubgo' ), __( 'Lets HubGo update itself whenever possible.', 'hubgo' ) ),
                            self::toggle( 'enable_update_notice', __( 'Update notices', 'hubgo' ), __( 'Displays notifications when a new version is available.', 'hubgo' ) ),
                            self::toggle( 'enable_debug_mode', __( 'Debug mode', 'hubgo' ), __( 'Records extra error and process details in the WordPress log.', 'hubgo' ) ),
                        ),
                    ),
                    array(
                        'id'          => 'about-system',
                        'title'       => __( 'System status', 'hubgo' ),
                        'description' => __( 'Quick view of the WordPress, PHP and WooCommerce environment.', 'hubgo' ),
                        'component'   => 'system-status',
                    ),
                    array(
                        'id'          => 'about-danger',
                        'title'       => __( 'Danger zone', 'hubgo' ),
                        'description' => __( 'Irreversible actions on the plugin configuration.', 'hubgo' ),
                        'component'   => 'danger-zone',
                    ),
                ),
            ),
        );

        return apply_filters( 'Hubgo/Admin/Settings/Schema', $schema );
    }


    /**
     * Flat map of field key => definition (used for sanitization).
     *
     * Integration fields are merged in so the single `POST /settings` route
     * persists both the settings screen and the integrations screen. Skipping
     * them here would silently drop every integration value on save.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @return array
     */
    public static function get_field_definitions() {
        $definitions = array();

        foreach ( self::get_schema() as $section ) {
            foreach ( ( $section['cards'] ?? array() ) as $card ) {
                foreach ( ( $card['fields'] ?? array() ) as $field ) {
                    if ( ! empty( $field['key'] ) ) {
                        $definitions[ $field['key'] ] = $field;
                    }
                }
            }
        }

        foreach ( Integrations_Base::get_integration_items() as $item ) {
            if ( ! empty( $item['setting_key'] ) ) {
                $definitions[ $item['setting_key'] ] = self::toggle( $item['setting_key'], $item['title'] );
            }

            foreach ( ( $item['settings'] ?? array() ) as $field ) {
                if ( ! empty( $field['key'] ) ) {
                    $definitions[ $field['key'] ] = $field;
                }
            }
        }

        return apply_filters( 'Hubgo/Admin/Settings/Field_Definitions', $definitions );
    }


    /**
     * Build the REST bootstrap payload for the settings SPA.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @return array
     */
    public static function get_bootstrap_data() {
        $data = array(
            'version'  => defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '',
            'settings' => Repository::get_settings(),
            'schema'   => self::get_schema(),
            'system'   => System_Status::get_status(),
            'license'  => License::get_summary(),
            'rest'     => array(
                'root'  => esc_url_raw( rest_url( 'hubgo/v1' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ),
        );

        return apply_filters( 'Hubgo/Admin/Settings/Bootstrap_Data', $data );
    }


    /**
     * Build the REST bootstrap payload for the integrations SPA.
     *
     * @since 3.0.0
     * @return array
     */
    public static function get_integrations_bootstrap_data() {
        $data = array(
            'version'    => defined( 'HUBGO_VERSION' ) ? HUBGO_VERSION : '',
            'settings'   => Repository::get_settings(),
            'cards'      => self::get_integration_cards(),
            'categories' => self::get_integration_categories(),
            'license'    => License::get_summary(),
            'can_install' => current_user_can( 'install_plugins' ),
        );

        return apply_filters( 'Hubgo/Admin/Integrations/Bootstrap_Data', $data );
    }


    /**
     * Build the integration cards consumed by the Integrations screen.
     *
     * Every runtime-dependent flag (is the plugin active, can this card be
     * installed) is resolved here so the Vue side stays a pure renderer.
     *
     * @since 3.0.0
     * @version 3.1.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_cards() {
        $settings = Repository::get_settings();
        $can_install = current_user_can( 'install_plugins' );
        $cards = array();

        foreach ( Integrations_Base::get_integration_items() as $slug => $item ) {
            $requires_plugin = ! empty( $item['is_plugin'] );
            $plugin_active = Integrations_Base::is_plugin_dependency_active( $item['plugin_active'], $requires_plugin );
            $setting_key = (string) $item['setting_key'];
            $installable = $requires_plugin && ! $plugin_active && ! empty( $item['install']['download_url'] );

            $author = array(
                'name' => (string) ( $item['author'] ?? '' ),
                'url'  => (string) ( $item['author_url'] ?? '' ),
            );

            // A card that names no vendor borrows the one from its dependency's
            // plugin header, so third-party integrations get credited without
            // having to declare anything.
            if ( '' === $author['name'] && $requires_plugin ) {
                $author = Integrations_Base::get_plugin_author( $item['plugin_active'] );
            }

            $card = array_merge( $item, array(
                'slug'             => $slug,
                'author'           => $author['name'],
                'author_url'       => $author['url'],
                'enabled'          => '' !== $setting_key && 'yes' === ( $settings[ $setting_key ] ?? 'no' ),
                'requires_plugin'  => $requires_plugin,
                'plugin_active'    => $plugin_active,
                'can_install'      => $installable && $can_install,
                // A card is configurable when it has fields OR modal blocks:
                // an integration may have nothing to tune and still need to
                // explain something (Joinotify's dual-toggle notice does).
                'has_settings'     => ! empty( $item['settings'] ) || ! empty( $item['modal']['blocks'] ),
                'disabled_message' => self::get_integration_disabled_message( $item, $requires_plugin, $plugin_active ),
            ) );

            $cards[] = $card;
        }

        return apply_filters( 'Hubgo/Admin/Integrations/Cards', $cards );
    }


    /**
     * Normalized, priority-ordered category catalog for the frontend.
     *
     * @since 3.0.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_categories() {
        $normalized = array();

        foreach ( (array) Integrations_Base::get_integration_categories() as $category ) {
            if ( ! is_array( $category ) || empty( $category['id'] ) ) {
                continue;
            }

            $id = sanitize_key( (string) $category['id'] );

            if ( '' === $id ) {
                continue;
            }

            $normalized[ $id ] = array(
                'id'       => $id,
                'label'    => isset( $category['label'] ) ? (string) $category['label'] : ucfirst( str_replace( '_', ' ', $id ) ),
                'icon'     => isset( $category['icon'] ) ? (string) $category['icon'] : '',
                'priority' => isset( $category['priority'] ) ? (int) $category['priority'] : 0,
            );
        }

        $normalized = array_values( $normalized );

        usort( $normalized, static function( $a, $b ) {
            return $a['priority'] <=> $b['priority'];
        } );

        return $normalized;
    }


    /**
     * Reason a card's toggle is unavailable, or an empty string when it is not.
     *
     * @since 3.0.0
     * @param array $item Normalized card.
     * @param bool $requires_plugin Whether the card declares a plugin dependency.
     * @param bool $plugin_active Whether that dependency is satisfied.
     * @return string
     */
    private static function get_integration_disabled_message( $item, $requires_plugin, $plugin_active ) {
        if ( ! empty( $item['coming_soon'] ) ) {
            return __( 'This integration will be available soon.', 'hubgo' );
        }

        if ( $requires_plugin && ! $plugin_active ) {
            return __( 'The matching plugin must be installed and active to use this integration.', 'hubgo' );
        }

        return '';
    }


    /**
     * Field helpers.
     */
    private static function toggle( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'toggle', 'label' => $label, 'description' => $description ), $extra );
    }

    private static function text( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'text', 'label' => $label, 'description' => $description ), $extra );
    }

    private static function textarea( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'textarea', 'label' => $label, 'description' => $description, 'rows' => 3 ), $extra );
    }

    private static function color( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'color', 'label' => $label, 'description' => $description ), $extra );
    }

    private static function select( $key, $label, $description, $options, $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'select', 'label' => $label, 'description' => $description, 'options' => $options ), $extra );
    }


    /**
     * Numeric field. `min`/`max` are enforced again on save by the repository.
     *
     * @since 3.0.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra attributes (min, max, step, unit, placeholder).
     * @return array<string,mixed>
     */
    private static function number( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'number', 'label' => $label, 'description' => $description ), $extra );
    }


    /**
     * Slider field, for style values that read better as a continuum.
     *
     * The calculator's lengths moved to {@see self::dimension()} in 3.0.1, but
     * `range` stays a registered field type — the schema is filterable and the
     * field registry is public, so an integration may still build one.
     *
     * @since 3.0.0
     * @version 3.0.1
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra attributes (min, max, step, unit).
     * @return array<string,mixed>
     */
    private static function range( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array(
            'key'         => $key,
            'type'        => 'range',
            'label'       => $label,
            'description' => $description,
            'min'         => 0,
            'max'         => 40,
            'step'        => 1,
            'unit'        => 'px',
        ), $extra );
    }


    /**
     * CSS length field: an amount plus a unit picker.
     *
     * The stored value is the CSS value itself ("12px", "1.5rem"), so the token
     * map can drop it straight into a custom property. A value saved before the
     * unit picker existed is a bare number and keeps working: {@see
     * \MeuMouse\Hubgo\Views\Calculator_Styles} appends the unit declared there.
     *
     * @since 3.0.1
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra attributes (min, max, step, unit, units, placeholder).
     * @return array<string,mixed>
     */
    private static function dimension( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array(
            'key'         => $key,
            'type'        => 'dimension',
            'label'       => $label,
            'description' => $description,
            'min'         => 0,
            'max'         => 200,
            // Unit a bare number is read with — px, matching both the values
            // stored before 3.0.1 and the stylesheet's built-in defaults.
            'unit'        => 'px',
            'units'       => array( 'rem', 'em', 'px', '%' ),
        ), $extra );
    }


    /**
     * Masked field for credentials.
     *
     * @since 3.0.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra attributes.
     * @return array<string,mixed>
     */
    private static function password( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array( 'key' => $key, 'type' => 'password', 'label' => $label, 'description' => $description ), $extra );
    }
}
