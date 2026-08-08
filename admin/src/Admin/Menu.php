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
            esc_html__( 'HubGo - Shipping Management', 'hubgo' ),
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
            esc_html__( 'HubGo - Settings', 'hubgo' ),
            esc_html__( 'Settings', 'hubgo' ),
            $capability,
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            self::PAGE_SLUG,
            esc_html__( 'HubGo - Integrations', 'hubgo' ),
            esc_html__( 'Integrations', 'hubgo' ),
            $capability,
            self::INTEGRATIONS_PAGE_SLUG,
            array( $this, 'render_integrations_page' )
        );

        add_submenu_page(
            self::PAGE_SLUG,
            esc_html__( 'HubGo - License', 'hubgo' ),
            esc_html__( 'License', 'hubgo' ),
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
            wp_die( esc_html__( 'You do not have permission to access this page.', 'hubgo' ) );
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
     * The HubGo brand mark, geometry taken verbatim from
     * assets/brand/logo-hubgo-primary.svg — keep the two in sync when the logo
     * changes.
     *
     * It is inlined rather than read from disk so the menu never depends on a
     * filesystem hit, and it is drawn in a single colour on purpose: WordPress
     * paints an SVG menu icon as a CSS background-image and only varies its
     * opacity, so it never recolours the artwork. The #232323 half of the
     * primary logo would therefore disappear against the dark admin menu
     * (#1d2327). Brand blue reads on both the dark schemes and the Light one.
     *
     * @since 3.0.0
     * @version 3.0.0
     * @return string
     */
    private static function get_menu_icon() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 272.84 152.99" role="img" aria-label="HubGo">'
            . '<g transform="translate(-363.58 -216.05)" fill="#008aff">'
            . '<path d="M601.94,295.67c1.75,4.52,6.86,0,6.56-3.29l1.78-39.26-16.7,9.05Z"/>'
            . '<path d="M630.77,217.59c-8.62,1.84-17.75,2.72-24.54,9.12-13.16,10.11-36.44,30.92-54.15,39.8-55,25.09-115.12,40.9-172.42,38.09-20.59-1.47-21.89,30.28-1.11,30.42,44.74-3.17,90.28-13.37,130.27-30.66,27.77-11.46,51.52-28.55,77.3-42.73,11.29-6.59,35.93-16.07,43.66-27.39C633.38,229.34,642.18,220,630.77,217.59Z"/>'
            . '<path d="M552.62,221.23l27.84,21,14.94-11.94-37.19-13.79C555.26,214.92,549.15,217.87,552.62,221.23Z"/>'
            . '<path d="M445.26,242.32c.23-16.14-25.11-16.14-24.88,0v54.27c7.51-.78,15.8-1.94,24.88-3.6Z"/>'
            . '<path d="M420.38,356.84c-.22,16.13,25.11,16.14,24.88,0V332.36q-12.22,2.77-24.88,4.86Z"/>'
            . '<path d="M533.72,267.22v-24.9c-.07-16.27-24.83-16.27-24.9,0v33.95C516.73,273.58,525.33,270.54,533.72,267.22Z"/>'
            . '<path d="M508.82,356.84c.07,16.27,24.83,16.26,24.9,0V300.43q-12.12,6.29-24.9,11.7Z"/>'
            . '</g>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }
}
