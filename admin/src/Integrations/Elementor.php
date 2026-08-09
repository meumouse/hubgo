<?php

namespace MeuMouse\Hubgo\Integrations;

use MeuMouse\Hubgo\Admin\Menu;
use MeuMouse\Hubgo\Admin\Settings;
use MeuMouse\Hubgo\Core\License;
use MeuMouse\Hubgo\Integrations\Widgets\Shipping_Calculator_Widget;

defined('ABSPATH') || exit;

/**
 * Elementor integration: the shipping calculator as a drag-and-drop widget.
 *
 * The widget renders the very same mount node the product-page hook and the
 * shortcode do, so all three share one Vue app and one style contract. What it
 * adds is per-instance styling: every control writes a `--hubgo-calc-*` custom
 * property scoped to `{{WRAPPER}}`, which outranks the store-wide block printed
 * by {@see \MeuMouse\Hubgo\Views\Calculator_Styles} purely on specificity.
 *
 * Licensing: the widget is only registered while the site holds an active
 * licence. That is the behaviour the product asked for, and it has a sharp edge
 * worth knowing about — when a licence lapses, Elementor silently drops the
 * element from any page already using it, because an unregistered widget type
 * simply does not render. {@see self::render_license_notice()} warns in the
 * admin, and the product-page position keeps working regardless, but pages
 * built around the widget do lose the block until the licence is renewed.
 *
 * @since 3.0.0
 * @package MeuMouse\Hubgo\Integrations
 * @author MeuMouse.com
 */
class Elementor extends Integrations_Base {

    /**
     * Card slug.
     *
     * @since 3.0.0
     * @var string
     */
    const CARD_SLUG = 'elementor';

    /**
     * Option key toggling the integration.
     *
     * @since 3.0.0
     * @var string
     */
    const SETTING_KEY = 'enable_elementor_integration';

    /**
     * Plugin basename of Elementor.
     *
     * @since 3.0.0
     * @var string
     */
    const PLUGIN_FILE = 'elementor/elementor.php';

    /**
     * Package URL served by wordpress.org.
     *
     * @since 3.0.0
     * @var string
     */
    const PACKAGE_URL = 'https://downloads.wordpress.org/plugin/elementor.zip';

    /**
     * Elementor widget category the calculator is filed under.
     *
     * @since 3.0.0
     * @var string
     */
    const CATEGORY = 'hubgo';


    /**
     * Constructor.
     *
     * @since 3.0.0
     */
    public function __construct() {
        // Always first: the card has to be listed even when Elementor is
        // missing or the toggle is off, or the user can never enable/install it.
        $this->register_integration_card( 40 );

        if ( ! self::is_available() || ! self::is_enabled() ) {
            return;
        }

        // Warn before the widget disappears from the editor, not after.
        add_action( 'admin_notices', array( $this, 'render_license_notice' ) );

        if ( ! License::is_active() ) {
            return;
        }

        add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
        add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
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
            'title'            => __( 'Elementor', 'hubgo' ),
            'description'      => __( 'Adds the shipping calculator as an Elementor widget, with its own color, spacing and typography controls.', 'hubgo' ),
            'icon'             => $this->get_icon_svg(),
            'author'           => 'Elementor.com',
            'author_url'       => 'https://elementor.com',
            'category'         => 'ecommerce',
            'setting_key'      => self::SETTING_KEY,
            'is_plugin'        => true,
            'plugin_active'    => array( self::PLUGIN_FILE, 'elementor-pro/elementor-pro.php' ),
            'requires_license' => true,
            'doc_url'          => 'https://ajuda.meumouse.com/docs/hubgo/overview',
            'install'          => array(
                'plugin_slug'  => self::PLUGIN_FILE,
                'download_url' => self::PACKAGE_URL,
                'label'        => __( 'Install Elementor', 'hubgo' ),
            ),
            'modal'            => array(
                'title'       => __( 'Elementor', 'hubgo' ),
                'description' => __( 'How to use the shipping calculator inside Elementor.', 'hubgo' ),
                'size'        => 'medium',
                'blocks'      => array(
                    self::modal_html_block(
                        '<p>' . esc_html__( 'With the integration enabled, drag the "Shipping calculator" widget (HubGo category) onto any page. Styles set on the widget apply to that instance only and take precedence over the Appearance tab.', 'hubgo' ) . '</p>'
                        . '<p>' . esc_html__( 'To place the calculator through the widget alone, set the display position to "Elementor widget only" in Settings → General — otherwise product pages also show the automatic one.', 'hubgo' ) . '</p>'
                    ),
                    self::modal_notice_block(
                        __( 'The widget requires an active license. Without one it stops being registered and disappears from the pages already using it — the calculator displayed automatically on the product page keeps working.', 'hubgo' ),
                        'warning'
                    ),
                ),
            ),
        );

        return $integrations;
    }


    /**
     * Register the HubGo widget category.
     *
     * @since 3.0.0
     * @param \Elementor\Elements_Manager $manager Elementor elements manager.
     * @return void
     */
    public function register_category( $manager ) {
        $manager->add_category( self::CATEGORY, array(
            'title' => __( 'HubGo', 'hubgo' ),
            'icon'  => 'eicon-shipping',
        ) );
    }


    /**
     * Register the calculator widget.
     *
     * @since 3.0.0
     * @param \Elementor\Widgets_Manager $manager Elementor widgets manager.
     * @return void
     */
    public function register_widgets( $manager ) {
        if ( ! class_exists( Shipping_Calculator_Widget::class ) ) {
            return;
        }

        $manager->register( new Shipping_Calculator_Widget() );
    }


    /**
     * Warn on the Elementor and HubGo screens when the licence is not active.
     *
     * @since 3.0.0
     * @return void
     */
    public function render_license_notice() {
        if ( License::is_active() || ! current_user_can( Menu::get_capability() ) ) {
            return;
        }

        if ( ! $this->is_relevant_admin_screen() ) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
            esc_html__( 'HubGo', 'hubgo' ),
            esc_html__( 'The Elementor shipping calculator widget requires an active license and is unavailable. Pages already using the widget stop displaying it until the license is activated.', 'hubgo' ),
            esc_url( Menu::get_page_url( Menu::LICENSE_PAGE_SLUG ) ),
            esc_html__( 'Activate license', 'hubgo' )
        );
    }


    /**
     * Whether the current admin screen is one where the notice is useful.
     *
     * @since 3.0.0
     * @return bool
     */
    private function is_relevant_admin_screen() {
        if ( ! function_exists( 'get_current_screen' ) ) {
            return false;
        }

        $screen = get_current_screen();

        if ( ! $screen || empty( $screen->id ) ) {
            return false;
        }

        // Elementor's own screens, plus the HubGo integrations page where the
        // card advertises the feature.
        return ( false !== strpos( $screen->id, 'elementor' ) )
            || ( false !== strpos( $screen->id, Menu::INTEGRATIONS_PAGE_SLUG ) );
    }


    /**
     * Whether Elementor is installed and active.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_available() {
        return self::is_plugin_dependency_active( array( self::PLUGIN_FILE, 'elementor-pro/elementor-pro.php' ), true );
    }


    /**
     * Whether the integration toggle is on.
     *
     * @since 3.0.0
     * @return bool
     */
    public static function is_enabled() {
        return 'yes' === Settings::get_setting( self::SETTING_KEY, 'no' );
    }


    /**
     * Elementor brand mark.
     *
     * @since 3.0.0
     * @return string
     */
    private function get_icon_svg() {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" role="img" aria-label="Elementor">'
            . '<rect x="2" y="2" width="60" height="60" rx="14" fill="#92003b"/>'
            . '<path d="M20 19h6v26h-6zm10 0h14v6H30zm0 10h14v6H30zm0 10h14v6H30z" fill="#ffffff"/>'
            . '</svg>';
    }
}
