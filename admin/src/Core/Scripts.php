<?php

namespace MeuMouse\Hubgo\Core;

defined('ABSPATH') || exit;

/**
 * Resolve Vite manifest assets for HubGo admin pages.
 *
 * Mirrors the Joinotify Scripts resolver: reads app/dist/.vite/manifest.json and
 * builds public URLs + cache-busting versions for the built Vue entries.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Core
 * @author MeuMouse.com
 */
class Scripts {

    /**
     * Relative path to the Vite manifest.
     *
     * @var string
     */
    const MANIFEST_PATH = 'app/dist/.vite/manifest.json';

    /**
     * Relative URL path to the Vite build output.
     *
     * @var string
     */
    const DIST_URL_PATH = 'app/dist/';


    /**
     * Get all assets for a Vite entry source.
     *
     * @since 3.0.0
     * @param string $entry_source Relative entry source, e.g. src/entries/settings.js.
     * @return array<string,mixed>
     */
    public static function get_entry_assets( $entry_source ) {
        $manifest = self::get_manifest();
        $entry = self::get_manifest_entry( $entry_source, $manifest );

        if ( empty( $entry ) ) {
            return array();
        }

        $styles = self::collect_css_assets( $entry, $manifest );

        return array(
            'script'  => ! empty( $entry['file'] ) ? self::get_asset_url( $entry['file'] ) : '',
            'styles'  => $styles,
            'imports' => ! empty( $entry['imports'] ) && is_array( $entry['imports'] ) ? $entry['imports'] : array(),
            'version' => ! empty( $entry['file'] ) ? self::get_asset_version( $entry['file'] ) : null,
        );
    }


    /**
     * Resolve a cache-busting version for a built Vite asset.
     *
     * The Vite entry script and its stylesheet keep fixed file names
     * (e.g. `settings/app.js`, `styles/*.css`), so without a version query
     * browsers serve a stale bundle after every rebuild. Use the file
     * modification time so each rebuild produces a fresh URL.
     *
     * @since 3.0.0
     * @param string $relative_path Manifest asset path, relative to the dist root.
     * @return string|null
     */
    public static function get_asset_version( $relative_path ) {
        if ( empty( $relative_path ) || ! is_string( $relative_path ) ) {
            return null;
        }

        $absolute_path = trailingslashit( HUBGO_PATH ) . self::DIST_URL_PATH . ltrim( $relative_path, '/' );

        if ( ! file_exists( $absolute_path ) ) {
            return null;
        }

        $modified_time = filemtime( $absolute_path );

        return $modified_time ? (string) $modified_time : null;
    }


    /**
     * Get the manifest entry for a source file.
     *
     * @since 3.0.0
     * @param string $entry_source Relative entry source.
     * @param array<string,mixed>|null $manifest Optional decoded manifest.
     * @return array<string,mixed>
     */
    private static function get_manifest_entry( $entry_source, $manifest = null ) {
        if ( empty( $entry_source ) || ! is_string( $entry_source ) ) {
            return array();
        }

        if ( null === $manifest ) {
            $manifest = self::get_manifest();
        }

        if ( empty( $manifest ) ) {
            return array();
        }

        $normalized_source = ltrim( str_replace( '\\', '/', $entry_source ), '/' );

        if ( isset( $manifest[ $normalized_source ] ) && is_array( $manifest[ $normalized_source ] ) ) {
            return $manifest[ $normalized_source ];
        }

        foreach ( $manifest as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            if ( isset( $entry['src'] ) && $normalized_source === ltrim( str_replace( '\\', '/', $entry['src'] ), '/' ) ) {
                return $entry;
            }
        }

        return array();
    }


    /**
     * Collect CSS assets from an entry and its imported chunks.
     *
     * @since 3.0.0
     * @param array<string,mixed> $entry Manifest entry.
     * @param array<string,mixed> $manifest Manifest data.
     * @param array<string,bool> $visited Internal recursion guard.
     * @return array<int,string>
     */
    private static function collect_css_assets( $entry, $manifest, &$visited = array() ) {
        $styles = array();

        if ( empty( $entry ) || ! is_array( $entry ) ) {
            return $styles;
        }

        if ( ! empty( $entry['file'] ) ) {
            $visited_key = $entry['file'];

            if ( isset( $visited[ $visited_key ] ) ) {
                return $styles;
            }

            $visited[ $visited_key ] = true;
        }

        if ( ! empty( $entry['css'] ) && is_array( $entry['css'] ) ) {
            foreach ( $entry['css'] as $css_file ) {
                $styles[] = self::get_asset_url( $css_file );
            }
        }

        if ( empty( $entry['imports'] ) || ! is_array( $entry['imports'] ) ) {
            return array_values( array_unique( $styles ) );
        }

        foreach ( $entry['imports'] as $import_key ) {
            if ( empty( $manifest[ $import_key ] ) || ! is_array( $manifest[ $import_key ] ) ) {
                continue;
            }

            $styles = array_merge( $styles, self::collect_css_assets( $manifest[ $import_key ], $manifest, $visited ) );
        }

        return array_values( array_unique( $styles ) );
    }


    /**
     * Decode the manifest once and cache it.
     *
     * @since 3.0.0
     * @return array<string,mixed>
     */
    private static function get_manifest() {
        static $manifest = null;

        if ( null !== $manifest ) {
            return $manifest;
        }

        $manifest = array();
        $manifest_path = self::get_manifest_path();

        if ( ! file_exists( $manifest_path ) || ! is_readable( $manifest_path ) ) {
            return $manifest;
        }

        $manifest_content = file_get_contents( $manifest_path );

        if ( false === $manifest_content || '' === trim( $manifest_content ) ) {
            return $manifest;
        }

        $decoded_manifest = json_decode( $manifest_content, true );

        if ( is_array( $decoded_manifest ) ) {
            $manifest = $decoded_manifest;
        }

        return $manifest;
    }


    /**
     * Get the absolute manifest file path.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_manifest_path() {
        return trailingslashit( HUBGO_PATH ) . self::MANIFEST_PATH;
    }


    /**
     * Build a public URL for a manifest asset.
     *
     * @since 3.0.0
     * @param string $relative_path Manifest asset path.
     * @return string
     */
    private static function get_asset_url( $relative_path ) {
        return trailingslashit( HUBGO_URL ) . self::DIST_URL_PATH . ltrim( $relative_path, '/' );
    }
}
