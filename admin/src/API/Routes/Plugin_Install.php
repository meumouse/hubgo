<?php

namespace MeuMouse\Hubgo\API\Routes;

use MeuMouse\Hubgo\API\Abstract_Route;
use MeuMouse\Hubgo\Admin\Settings\Registry;
use MeuMouse\Hubgo\Core\Plugin_Installer;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * POST /hubgo/v1/plugins/install — install and activate an integration's plugin.
 *
 * The request only names an integration slug: the package URL comes from the
 * server-side catalog, never from the client. That keeps the endpoint from
 * doubling as an arbitrary "install this zip" API.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\API\Routes
 */
class Plugin_Install extends Abstract_Route {

    protected $route = '/plugins/install';
    protected $methods = 'POST';


    /**
     * Installing code is a stricter operation than editing settings.
     *
     * @since 3.0.0
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permission( WP_REST_Request $request ) {
        return parent::permission( $request ) && current_user_can( 'install_plugins' );
    }


    /**
     * @inheritDoc
     */
    public function handle( WP_REST_Request $request ) {
        $slug = sanitize_key( (string) $request->get_param( 'slug' ) );

        if ( '' === $slug ) {
            return $this->error_response( esc_html__( 'Tell us which integration should be installed.', 'hubgo' ) );
        }

        $card = $this->find_card( $slug );

        if ( null === $card ) {
            return $this->error_response( esc_html__( 'Integration not found.', 'hubgo' ), 404 );
        }

        $plugin_file = (string) ( $card['install']['plugin_slug'] ?? '' );
        $package_url = (string) ( $card['install']['download_url'] ?? '' );

        if ( '' === $plugin_file || '' === $package_url ) {
            return $this->error_response( esc_html__( 'This integration cannot be installed from the dashboard yet.', 'hubgo' ) );
        }

        $installed = Plugin_Installer::install_and_activate( $plugin_file, $package_url );

        if ( is_wp_error( $installed ) ) {
            return $this->error_response( $installed->get_error_message() );
        }

        return $this->success_response( array(
            'message'    => esc_html__( 'Plugin installed and activated successfully.', 'hubgo' ),
            'cards'      => Registry::get_integration_cards(),
        ) );
    }


    /**
     * Look an integration card up by slug.
     *
     * @since 3.0.0
     * @param string $slug Integration slug.
     * @return array|null
     */
    private function find_card( $slug ) {
        foreach ( Registry::get_integration_cards() as $card ) {
            if ( isset( $card['slug'] ) && $slug === $card['slug'] ) {
                return $card;
            }
        }

        return null;
    }
}
