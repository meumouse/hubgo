<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Change colors on front-end
 *
 * @package MeuMouse.com
 * @since 1.0.0
 */

class Hubgo_Shipping_Management_Custom_Colors extends Hubgo_Shipping_Management_Init {

  public function __construct() {
    parent::__construct();

    add_action( 'wp_head', array( $this, 'hubgo_custom_colors' ) );
  }

  /**
   * Custom color primary
   * 
   * @return string
   * @since 1.0.0
   */
  public function hubgo_custom_colors() {
    $primary_color = Hubgo_Shipping_Management_Init::get_setting( 'primary_main_color' );
    $hover_color = $this->generate_rgba_color($primary_color, 80);
  
    $css = '#hubgo-postcode:focus {';
      $css .= 'border-color:'. $primary_color .' !important;';
    $css .= '}';

    $css .= '#hubgo-shipping-calc-button {';
      $css .= 'background-color:'. $primary_color .' !important;';
      $css .= 'border-color:'. $primary_color .' !important;';
    $css .= '}';

    $css .= '#hubgo-shipping-calc-button:hover {';
      $css .= 'background-color:'. $hover_color .' !important;';
      $css .= 'border-color:'. $hover_color .' !important;';
    $css .= '}';

    $css .= '.hubgo-postcode-search:hover {';
      $css .= 'color:'. $primary_color .';';
    $css .= '}';

    $css .= '#hubgo-response table .hubgo-shipping-header th {';
      $css .= 'background-color:'. $primary_color .' !important;';
    $css .= '}';

    ?>
    <style type="text/css">
      <?php echo $css; ?>
    </style> <?php
  }


  /**
   * Generate RGBA color from primary color
   * 
   * @since 1.0.0
   * @return string
   * @package MeuMouse.com
   */
  public function generate_rgba_color($color, $opacity) {
    // removes the "#" character if present 
    $color = str_replace("#", "", $color);

    // gets the RGB decimal value of each color component
    $red = hexdec(substr($color, 0, 2));
    $green = hexdec(substr($color, 2, 2));
    $blue = hexdec(substr($color, 4, 2));
    $opacity = $opacity / 100;

    // generates RGBA color based on foreground color and opacity
    $rgba_color = "rgba($red, $green, $blue, $opacity)";

    return $rgba_color;
  }

}

new Hubgo_Shipping_Management_Custom_Colors();