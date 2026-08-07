<?php

namespace MeuMouse\Hubgo\Admin;

defined('ABSPATH') || exit;

/**
 * Registers the HubGo settings admin page (Vue SPA mount point).
 *
 * The page body is intentionally minimal: it only renders the mount node and a
 * skeleton loader. All data flows through the hubgo/v1 REST API.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Admin
 * @author MeuMouse.com
 */
class Menu {

    /**
     * Settings page slug.
     *
     * @var string
     */
    const PAGE_SLUG = 'hubgo-settings';

    /**
     * Parent menu slug.
     *
     * @var string
     */
    const PARENT_MENU_SLUG = 'woocommerce';


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }


    /**
     * Register the settings submenu under WooCommerce.
     *
     * @since 3.0.0
     * @return void
     */
    public function register_menu() {
        add_submenu_page(
            $this->get_parent_menu_slug(),
            esc_html__( 'HubGo - Gerenciamento de Frete', 'hubgo' ),
            esc_html__( 'HubGo', 'hubgo' ),
            self::get_capability(),
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }


    /**
     * Get the capability required to manage HubGo settings.
     *
     * @since 3.0.0
     * @return string
     */
    public static function get_capability() {
        return apply_filters( 'Hubgo/Admin/Settings_Capability', 'manage_woocommerce' );
    }


    /**
     * Render the SPA mount point.
     *
     * @since 3.0.0
     * @return void
     */
    public function render_page() {
        if ( ! current_user_can( self::get_capability() ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'hubgo' ) );
        }
        ?>
        <div class="wrap hubgo-settings-page hubgo-app">
            <div id="hubgo-settings-app" class="hubgo-settings-app">
                <div class="hubgo-skeleton-content" style="width: 950px; max-width: 100%; height: 100px;"></div>
                <div class="hubgo-skeleton-content" style="width: 680px; max-width: 100%; height: 65px; margin-top: 2rem;"></div>
                <div class="hubgo-skeleton-content" style="width: 100%; height: 550px; margin-top: 2rem;"></div>
            </div>
        </div>
        <?php
    }


    /**
     * Resolve the parent menu slug (WooCommerce when available).
     *
     * @since 3.0.0
     * @return string
     */
    private function get_parent_menu_slug() {
        global $menu;

        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && self::PARENT_MENU_SLUG === $item[2] ) {
                    return self::PARENT_MENU_SLUG;
                }
            }
        }

        return 'options-general.php';
    }
}
