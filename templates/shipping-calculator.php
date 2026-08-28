<?php
/**
 * Storefront shipping calculator mount node.
 *
 * Overridable at woocommerce/shipping-calculator.php in a theme.
 *
 * The node is intentionally empty: the Vue app in app/src/storefront takes it
 * over on DOM ready and reads its configuration from the data attribute. The
 * `hubgo-shipping-calculator` class is the hook the CSS custom properties are
 * declared on — both Views\Calculator_Styles and the Elementor widget target
 * it, so renaming it here detaches the widget from every style control.
 *
 * @package HubGo
 * @since 3.0.0
 * @version 3.0.0
 * @var array $config Instance configuration.
 */

defined('ABSPATH') || exit;

?>
<div
    class="hubgo-shipping-calculator"
    data-hubgo-calculator="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
></div>
