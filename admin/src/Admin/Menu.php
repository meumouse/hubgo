<?php

namespace MeuMouse\Hubgo\Admin;

defined('ABSPATH') || exit;

/**
 * Registers the HubGo top-level admin menu and its Vue SPA subpages.
 *
 * Each page body is intentionally minimal: it only renders the mount node and a
 * skeleton loader. All data flows through the hubgo/v1 REST API.
 *
 * The parent slug is the settings page slug (not a dedicated "hubgo" slug) so
 * every link published before 3.0.0 — the MDS plugin row action, bookmarks and
 * the WooCommerce submenu entry — still lands on a valid screen.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Admin
 * @author MeuMouse.com
 */
class Menu {

    /**
     * Settings page slug. Doubles as the top-level menu slug.
     *
     * @var string
     */
    const PAGE_SLUG = 'hubgo-settings';

    /**
     * Integrations page slug.
     *
     * @since 3.0.0
     * @var string
     */
    const INTEGRATIONS_PAGE_SLUG = 'hubgo-integrations';

    /**
     * License page slug.
     *
     * @since 3.0.0
     * @var string
     */
    const LICENSE_PAGE_SLUG = 'hubgo-license';

    /**
     * Parent menu slug.
     *
     * Kept pointing at the top-level HubGo menu so third parties (and the MDS
     * SDK) can attach their own subpages to it.
     *
     * @var string
     */
    const PARENT_MENU_SLUG = self::PAGE_SLUG;


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }


    /**
     * Register the HubGo top-level menu and its subpages.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @return void
     */
    public function register_menu() {
        $capability = self::get_capability();

        add_menu_page(
            esc_html__( 'HubGo - Gerenciamento de Frete', 'hubgo' ),
            esc_html__( 'HubGo', 'hubgo' ),
            $capability,
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' ),
            self::get_menu_icon(),
            58
        );

        // Repeating the parent slug renames the auto-generated first submenu
        // entry (which would otherwise read "HubGo") without duplicating it.
        add_submenu_page(
            self::PAGE_SLUG,
            esc_html__( 'HubGo - Configurações', 'hubgo' ),
            esc_html__( 'Configurações', 'hubgo' ),
            $capability,
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            self::PAGE_SLUG,
            esc_html__( 'HubGo - Integrações', 'hubgo' ),
            esc_html__( 'Integrações', 'hubgo' ),
            $capability,
            self::INTEGRATIONS_PAGE_SLUG,
            array( $this, 'render_integrations_page' )
        );

        add_submenu_page(
            self::PAGE_SLUG,
            esc_html__( 'HubGo - Licença', 'hubgo' ),
            esc_html__( 'Licença', 'hubgo' ),
            $capability,
            self::LICENSE_PAGE_SLUG,
            array( $this, 'render_license_page' )
        );

        do_action( 'Hubgo/Admin/Menu/Registered', self::PAGE_SLUG, $capability );
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
     * Every admin page slug this class registers.
     *
     * @since 3.0.0
     * @return array<int,string>
     */
    public static function get_page_slugs() {
        return array(
            self::PAGE_SLUG,
            self::INTEGRATIONS_PAGE_SLUG,
            self::LICENSE_PAGE_SLUG,
        );
    }


    /**
     * Admin URL of one of the HubGo pages.
     *
     * @since 3.0.0
     * @param string $slug Page slug. Defaults to the settings page.
     * @return string
     */
    public static function get_page_url( $slug = self::PAGE_SLUG ) {
        return admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
    }


    /**
     * Render the settings SPA mount point.
     *
     * @since 3.0.0
     * @return void
     */
    public function render_settings_page() {
        $this->render_app( 'hubgo-settings-app', 'hubgo-settings-page' );
    }


    /**
     * Render the integrations SPA mount point.
     *
     * @since 3.0.0
     * @return void
     */
    public function render_integrations_page() {
        $this->render_app( 'hubgo-integrations-app', 'hubgo-integrations-page' );
    }


    /**
     * Render the license SPA mount point.
     *
     * @since 3.0.0
     * @return void
     */
    public function render_license_page() {
        $this->render_app( 'hubgo-license-app', 'hubgo-license-page' );
    }


    /**
     * Print the shared SPA shell: a mount node plus skeleton placeholders.
     *
     * @since 3.0.0
     * @param string $mount_id Mount node id the Vite entry looks for.
     * @param string $page_class Extra class identifying the screen.
     * @return void
     */
    private function render_app( $mount_id, $page_class ) {
        if ( ! current_user_can( self::get_capability() ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'hubgo' ) );
        }
        ?>
        <div class="wrap <?php echo esc_attr( $page_class ); ?> hubgo-app">
            <div id="<?php echo esc_attr( $mount_id ); ?>" class="hubgo-settings-app">
                <div class="hubgo-skeleton-content" style="width: 950px; max-width: 100%; height: 100px;"></div>
                <div class="hubgo-skeleton-content" style="width: 680px; max-width: 100%; height: 65px; margin-top: 2rem;"></div>
                <div class="hubgo-skeleton-content" style="width: 100%; height: 550px; margin-top: 2rem;"></div>
            </div>
        </div>
        <?php
    }


    /**
     * Base64 data URI used as the top-level menu icon.
     *
     * A shipping glyph rather than the HubGo wordmark: WordPress renders menu
     * icons at 20x20, where the wide logotype becomes illegible. `currentColor`
     * lets the admin colour scheme tint it like every core icon.
     *
     * @since 3.0.0
     * @return string
     */
    private static function get_menu_icon() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">'
            . '<path d="M20.7 8.3a1 1 0 0 0-.7-.3h-3V6a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h1.2a3 3 0 0 0 5.6 0h4.4a3 3 0 0 0 5.6 0H22a1 1 0 0 0 1-1v-4a1 1 0 0 0-.3-.7l-2-3zM7 18a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm10 0a1 1 0 1 1 0-2 1 1 0 0 1 0 2zm4-4h-4v-4h2.5l1.5 2.3V14z"/>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }
}
