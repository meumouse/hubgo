<?php

namespace MeuMouse\Hubgo\Integrations;

defined('ABSPATH') || exit;

/**
 * Base class and normalization layer for the Integrations screen catalog.
 *
 * Mirrors the Joinotify contract so an integration is declared as plain data —
 * a card with an icon, a category, a `setting_key` toggle and an optional set
 * of fields rendered inside a modal. The Vue screen renders whatever this
 * catalog returns, so adding an integration never means touching the frontend.
 *
 * An integration class extends this base, registers its card from the
 * constructor via {@see self::register_integration_card()} and only then gates
 * its runtime behaviour on the host plugin being available — the card must be
 * listed even when the dependency is missing, otherwise the user has no way to
 * discover (or install) it.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
abstract class Integrations_Base {

    /**
     * Register this integration's card on the catalog filter.
     *
     * @since 3.0.0
     * @param int $priority Filter priority, controlling the card order.
     * @return void
     */
    protected function register_integration_card( $priority = 10 ) {
        if ( method_exists( $this, 'add_integration_item' ) ) {
            add_filter( 'Hubgo/Integrations/Cards', array( $this, 'add_integration_item' ), $priority, 1 );
        }
    }


    /**
     * The full, normalized integration catalog.
     *
     * @since 3.0.0
     * @return array<string,array<string,mixed>>
     */
    public static function get_integration_items() {
        return self::normalize_integration_items( apply_filters( 'Hubgo/Integrations/Cards', array() ) );
    }


    /**
     * Catalog of integration categories, ordered by priority.
     *
     * @since 3.0.0
     * @return array<int,array<string,mixed>>
     */
    public static function get_integration_categories() {
        $categories = array(
            array(
                'id'       => 'shipping',
                'label'    => __( 'Shipping and carriers', 'hubgo' ),
                'icon'     => 'package',
                'priority' => 10,
            ),
            array(
                'id'       => 'notifications',
                'label'    => __( 'Notifications', 'hubgo' ),
                'icon'     => 'bell',
                'priority' => 20,
            ),
            array(
                'id'       => 'ecommerce',
                'label'    => __( 'E-commerce', 'hubgo' ),
                'icon'     => 'cart',
                'priority' => 30,
            ),
            array(
                'id'       => 'others',
                'label'    => __( 'Other', 'hubgo' ),
                'icon'     => 'grid',
                'priority' => 100,
            ),
        );

        /**
         * Filter the integration categories catalog.
         *
         * @since 3.0.0
         * @param array<int,array<string,mixed>> $categories Current categories (id/label/icon/priority).
         */
        return apply_filters( 'Hubgo/Integrations/Categories', $categories );
    }


    /**
     * Build a normalized card payload.
     *
     * Small, stable contract third parties can call instead of assembling the
     * internal array shape by hand.
     *
     * @since 3.0.0
     * @param string $slug Integration slug.
     * @param string $title Card title.
     * @param string $description Card description.
     * @param string $icon Inline SVG markup for the card icon.
     * @param array $args Extra card arguments.
     * @return array<string,mixed>
     */
    public static function build_integration_item( $slug, $title, $description, $icon, $args = array() ) {
        $item = wp_parse_args( $args, array(
            'title'       => $title,
            'description' => $description,
            'icon'        => $icon,
        ) );

        return self::normalize_integration_item( $slug, $item );
    }


    /**
     * Normalize a whole catalog, dropping malformed entries.
     *
     * @since 3.0.0
     * @param array<string,array<string,mixed>> $items Raw catalog.
     * @return array<string,array<string,mixed>>
     */
    public static function normalize_integration_items( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $items as $slug => $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $item_slug = is_string( $slug ) && '' !== $slug ? sanitize_key( $slug ) : '';

            if ( '' === $item_slug && ! empty( $item['slug'] ) ) {
                $item_slug = sanitize_key( (string) $item['slug'] );
            }

            if ( '' === $item_slug ) {
                continue;
            }

            $normalized[ $item_slug ] = self::normalize_integration_item( $item_slug, $item );
        }

        return $normalized;
    }


    /**
     * Normalize a single card, filling every key the frontend expects.
     *
     * @since 3.0.0
     * @param string $slug Integration slug.
     * @param array $item Raw card.
     * @return array<string,mixed>
     */
    public static function normalize_integration_item( $slug, $item ) {
        $slug = sanitize_key( $slug );
        $item = is_array( $item ) ? $item : array();

        $settings = isset( $item['settings'] ) && is_array( $item['settings'] )
            ? $item['settings']
            : ( isset( $item['fields'] ) && is_array( $item['fields'] ) ? $item['fields'] : array() );

        $normalized = wp_parse_args( $item, array(
            'slug'             => $slug,
            'title'            => '',
            'description'      => '',
            'icon'             => '',
            'author'           => '',
            'author_url'       => '',
            'category'         => 'others',
            'setting_key'      => '',
            'action_hook'      => self::get_integration_action_hook( $slug ),
            'is_plugin'        => false,
            'plugin_active'    => array(),
            'requires_license' => false,
            'coming_soon'      => false,
            'doc_url'          => '',
            'install'          => array(),
            'settings'         => $settings,
            'defaults'         => array(),
            'modal'            => array(),
        ) );

        $normalized['slug'] = $slug;
        $normalized['category'] = sanitize_key( (string) $normalized['category'] );

        if ( '' === $normalized['category'] ) {
            $normalized['category'] = 'others';
        }

        $normalized['setting_key'] = sanitize_key( (string) $normalized['setting_key'] );
        $normalized['author'] = sanitize_text_field( (string) $normalized['author'] );
        $normalized['author_url'] = esc_url_raw( (string) $normalized['author_url'] );
        $normalized['is_plugin'] = ! empty( $normalized['is_plugin'] );
        $normalized['requires_license'] = ! empty( $normalized['requires_license'] );
        $normalized['coming_soon'] = ! empty( $normalized['coming_soon'] );
        $normalized['plugin_active'] = is_array( $normalized['plugin_active'] )
            ? array_values( array_filter( array_map( 'strval', $normalized['plugin_active'] ) ) )
            : array();
        $normalized['settings'] = self::normalize_integration_settings( $normalized['settings'] );
        $normalized['defaults'] = self::normalize_integration_defaults( $normalized['defaults'], $normalized['settings'] );
        $normalized['install'] = self::normalize_integration_install( $normalized['install'] );
        $normalized['modal'] = self::normalize_integration_modal( $normalized['modal'], $normalized );

        /**
         * Filter a single normalized integration card.
         *
         * @since 3.0.0
         * @param array<string,mixed> $normalized Normalized card.
         * @param string $slug Integration slug.
         */
        return apply_filters( 'Hubgo/Integrations/Card', $normalized, $slug );
    }


    /**
     * Normalize the field definitions declared by a card.
     *
     * Field shapes match the settings schema, so the same Vue field registry
     * and the same {@see \MeuMouse\Hubgo\Admin\Settings\Repository} sanitizers
     * handle them.
     *
     * @since 3.0.0
     * @param array $settings Raw field definitions.
     * @return array<int,array<string,mixed>>
     */
    public static function normalize_integration_settings( $settings ) {
        if ( ! is_array( $settings ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $settings as $key => $setting ) {
            if ( ! is_array( $setting ) ) {
                continue;
            }

            if ( empty( $setting['key'] ) && is_string( $key ) ) {
                $setting['key'] = $key;
            }

            $setting = wp_parse_args( $setting, array(
                'key'         => '',
                'type'        => 'text',
                'label'       => '',
                'description' => '',
                'placeholder' => '',
                'options'     => array(),
                'rows'        => 3,
                'default'     => null,
            ) );

            $setting['key'] = sanitize_key( (string) $setting['key'] );

            if ( '' === $setting['key'] ) {
                continue;
            }

            $setting['type'] = sanitize_key( (string) $setting['type'] );
            $setting['options'] = is_array( $setting['options'] ) ? array_values( $setting['options'] ) : array();

            if ( null === $setting['default'] ) {
                $setting['default'] = self::infer_default_value( $setting );
            }

            $normalized[] = $setting;
        }

        return $normalized;
    }


    /**
     * Merge explicit defaults with the ones inferred from the field types.
     *
     * @since 3.0.0
     * @param array $defaults Explicit defaults.
     * @param array $settings Normalized field definitions.
     * @return array<string,mixed>
     */
    public static function normalize_integration_defaults( $defaults, $settings ) {
        $normalized = is_array( $defaults ) ? $defaults : array();

        foreach ( (array) $settings as $setting ) {
            $key = isset( $setting['key'] ) ? (string) $setting['key'] : '';

            if ( '' === $key || array_key_exists( $key, $normalized ) ) {
                continue;
            }

            $normalized[ $key ] = array_key_exists( 'default', $setting ) && null !== $setting['default']
                ? $setting['default']
                : self::infer_default_value( $setting );
        }

        return $normalized;
    }


    /**
     * Normalize the one-click install descriptor.
     *
     * An empty `download_url` means "not installable yet": the frontend then
     * shows the dependency notice instead of an install button. That is what
     * keeps a card useful for a plugin that has not shipped yet.
     *
     * @since 3.0.0
     * @param array $install Raw install descriptor.
     * @return array<string,string>
     */
    public static function normalize_integration_install( $install ) {
        $install = is_array( $install ) ? $install : array();

        $install = wp_parse_args( $install, array(
            'plugin_slug'  => '',
            'download_url' => '',
            'label'        => '',
        ) );

        $install['plugin_slug'] = sanitize_text_field( (string) $install['plugin_slug'] );
        $install['download_url'] = esc_url_raw( (string) $install['download_url'] );
        $install['label'] = (string) $install['label'];

        if ( '' === $install['label'] ) {
            $install['label'] = __( 'Install plugin', 'hubgo' );
        }

        return $install;
    }


    /**
     * Normalize the modal metadata of a card.
     *
     * @since 3.0.0
     * @param array $modal Raw modal metadata.
     * @param array $item Normalized card the modal belongs to.
     * @return array<string,mixed>
     */
    public static function normalize_integration_modal( $modal, $item = array() ) {
        $modal = is_array( $modal ) ? $modal : array();

        $modal = wp_parse_args( $modal, array(
            'title'        => isset( $item['title'] ) ? (string) $item['title'] : '',
            'description'  => isset( $item['description'] ) ? (string) $item['description'] : '',
            'button_label' => __( 'Configure', 'hubgo' ),
            'size'         => 'medium',
            'blocks'       => array(),
        ) );

        $modal['size'] = self::normalize_modal_size( $modal['size'] );
        $modal['blocks'] = self::normalize_modal_blocks( $modal['blocks'] );

        return $modal;
    }


    /**
     * Build an HTML content block for a modal.
     *
     * @since 3.0.0
     * @param string $html Trusted markup (escaped with wp_kses_post on output).
     * @param array $extra Extra block attributes.
     * @return array<string,mixed>
     */
    public static function modal_html_block( $html, $extra = array() ) {
        return array_merge( array(
            'type' => 'html',
            'html' => (string) $html,
        ), $extra );
    }


    /**
     * Build a notice block for a modal.
     *
     * @since 3.0.0
     * @param string $message Notice body.
     * @param string $tone One of info|success|warning|danger.
     * @return array<string,mixed>
     */
    public static function modal_notice_block( $message, $tone = 'info' ) {
        return array(
            'type'    => 'notice',
            'tone'    => in_array( $tone, array( 'info', 'success', 'warning', 'danger' ), true ) ? $tone : 'info',
            'message' => (string) $message,
        );
    }


    /**
     * Build a data-migration block for a modal.
     *
     * The block carries the whole migration status payload, so the frontend
     * renders the progress bar and drives `POST hubgo/v1/migrations/run`
     * without knowing which plugin is being migrated.
     *
     * @since 3.0.0
     * @param array $migration Status payload from a Core\Abstract_Migration.
     * @param array $extra Extra block attributes.
     * @return array<string,mixed>
     */
    public static function modal_migration_block( $migration, $extra = array() ) {
        $migration = is_array( $migration ) ? $migration : array();

        return array_merge( array(
            'type'      => 'migration',
            'key'       => 'migration-' . sanitize_key( (string) ( $migration['id'] ?? '' ) ),
            'migration' => $migration,
        ), $extra );
    }


    /**
     * Field helper: toggle.
     *
     * @since 3.0.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra field attributes.
     * @return array<string,mixed>
     */
    public static function field_toggle( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array(
            'key'         => $key,
            'type'        => 'toggle',
            'label'       => $label,
            'description' => $description,
        ), $extra );
    }


    /**
     * Field helper: single-line text.
     *
     * @since 3.0.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra field attributes.
     * @return array<string,mixed>
     */
    public static function field_text( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array(
            'key'         => $key,
            'type'        => 'text',
            'label'       => $label,
            'description' => $description,
        ), $extra );
    }


    /**
     * Field helper: multi-line text.
     *
     * @since 3.0.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $extra Extra field attributes.
     * @return array<string,mixed>
     */
    public static function field_textarea( $key, $label, $description = '', $extra = array() ) {
        return array_merge( array(
            'key'         => $key,
            'type'        => 'textarea',
            'label'       => $label,
            'description' => $description,
            'rows'        => 3,
        ), $extra );
    }


    /**
     * Field helper: select.
     *
     * @since 3.0.0
     * @param string $key Setting key.
     * @param string $label Field label.
     * @param string $description Field description.
     * @param array $options List of array( 'value' => ..., 'label' => ... ).
     * @param array $extra Extra field attributes.
     * @return array<string,mixed>
     */
    public static function field_select( $key, $label, $description, $options, $extra = array() ) {
        return array_merge( array(
            'key'         => $key,
            'type'        => 'select',
            'label'       => $label,
            'description' => $description,
            'options'     => $options,
        ), $extra );
    }


    /**
     * Action hook fired for an integration's own modal extensions.
     *
     * @since 3.0.0
     * @param string $slug Integration slug.
     * @return string
     */
    public static function get_integration_action_hook( $slug ) {
        $segments = array_map( 'ucfirst', explode( '_', sanitize_key( $slug ) ) );

        return 'Hubgo/Integrations/Card/' . implode( '_', $segments );
    }


    /**
     * Whether every plugin an integration depends on is active.
     *
     * @since 3.0.0
     * @param array $plugin_files Plugin basenames.
     * @param bool $requires_plugin Whether the card declares a dependency at all.
     * @return bool
     */
    public static function is_plugin_dependency_active( $plugin_files, $requires_plugin ) {
        if ( ! $requires_plugin ) {
            return true;
        }

        if ( ! is_array( $plugin_files ) || empty( $plugin_files ) ) {
            return false;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Any one of the listed basenames satisfies the dependency: a plugin can
        // ship under more than one folder name (free/pro, renamed forks).
        foreach ( $plugin_files as $plugin_file ) {
            if ( is_plugin_active( $plugin_file ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Vendor declared in the header of the plugin an integration depends on.
     *
     * The fallback for cards that do not declare an `author` of their own —
     * notably third-party integrations registered through
     * `Hubgo/Integrations/Registered`, which get the credit right for free.
     * Only installed plugins have a header to read, so a missing dependency
     * simply yields no vendor and the card renders without the line.
     *
     * @since 3.0.0
     * @param array $plugin_files Plugin basenames, in preference order.
     * @return array{name:string,url:string} Empty strings when unknown.
     */
    public static function get_plugin_author( $plugin_files ) {
        $author = array( 'name' => '', 'url' => '' );

        if ( ! is_array( $plugin_files ) || empty( $plugin_files ) ) {
            return $author;
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // get_plugins() caches its scan for the whole request, so listing every
        // card costs a single pass over the plugin headers.
        $installed = get_plugins();

        foreach ( $plugin_files as $plugin_file ) {
            $plugin_file = (string) $plugin_file;

            if ( empty( $installed[ $plugin_file ]['Author'] ) ) {
                continue;
            }

            // The header ships the author as linked markup ("<a href=…>Name</a>")
            // whenever Author URI is set: keep the name and the URL apart so the
            // card decides how to present them.
            $author['name'] = sanitize_text_field( wp_strip_all_tags( (string) $installed[ $plugin_file ]['Author'] ) );
            $author['url'] = esc_url_raw( (string) ( $installed[ $plugin_file ]['AuthorURI'] ?? '' ) );

            break;
        }

        return $author;
    }


    /**
     * Inline SVG shipped under `assets/admin/img/`.
     *
     * Card icons travel through the REST payload as markup (the frontend
     * detects them with `isMarkup()`), so a brand logo has to be inlined rather
     * than linked. Reading it from disk keeps the artwork in a real `.svg` file
     * the designer can replace, instead of a multi-kilobyte string pasted into
     * PHP; the result is cached per request so a screen listing several cards
     * still only touches each file once.
     *
     * @since 3.0.0
     * @param string $file SVG file name inside assets/admin/img/.
     * @param string $label Accessible name applied to the root <svg> element.
     * @return string Inline SVG markup, or an empty string when unavailable.
     */
    protected static function get_brand_svg( $file, $label = '' ) {
        static $cache = array();

        $file = basename( (string) $file );

        if ( ! isset( $cache[ $file ] ) ) {
            $path = trailingslashit( HUBGO_PATH ) . 'assets/admin/img/' . $file;

            $cache[ $file ] = ( '.svg' === strtolower( (string) substr( $file, -4 ) ) && is_readable( $path ) )
                ? trim( (string) file_get_contents( $path ) )
                : '';
        }

        $svg = $cache[ $file ];

        if ( '' === $svg || '' === $label || false !== strpos( $svg, 'aria-label' ) ) {
            return $svg;
        }

        // The exported artwork carries no accessible name: give it one without
        // asking every designer to hand-edit the file after each export.
        return preg_replace(
            '/^<svg\b/',
            '<svg role="img" aria-label="' . esc_attr( $label ) . '"',
            $svg,
            1
        );
    }


    /**
     * Default value implied by a field type.
     *
     * @since 3.0.0
     * @param array $field Normalized field definition.
     * @return mixed
     */
    protected static function infer_default_value( $field ) {
        $type = isset( $field['type'] ) ? (string) $field['type'] : 'text';

        if ( 'toggle' === $type ) {
            return 'no';
        }

        if ( 'select' === $type && ! empty( $field['options'][0]['value'] ) ) {
            return (string) $field['options'][0]['value'];
        }

        return '';
    }


    /**
     * Clamp a modal size to the supported keywords.
     *
     * @since 3.0.0
     * @param string $size Raw size.
     * @return string
     */
    protected static function normalize_modal_size( $size ) {
        $size = strtolower( trim( (string) $size ) );
        $allowed = array( 'small', 'medium', 'large', 'extra-large' );

        return in_array( $size, $allowed, true ) ? $size : 'medium';
    }


    /**
     * Normalize modal content blocks, accepting raw HTML strings as shorthand.
     *
     * @since 3.0.0
     * @param array $blocks Raw blocks.
     * @return array<int,array<string,mixed>>
     */
    protected static function normalize_modal_blocks( $blocks ) {
        if ( ! is_array( $blocks ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $blocks as $index => $block ) {
            if ( is_string( $block ) && '' !== trim( $block ) ) {
                $normalized[] = self::modal_html_block( $block, array( 'key' => 'html-' . $index ) );

                continue;
            }

            if ( ! is_array( $block ) || empty( $block['type'] ) ) {
                continue;
            }

            $block['type'] = sanitize_key( (string) $block['type'] );
            $block['key'] = ! empty( $block['key'] ) ? sanitize_key( (string) $block['key'] ) : $block['type'] . '-' . $index;

            $normalized[] = $block;
        }

        return $normalized;
    }
}
