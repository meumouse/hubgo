<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main class init
 * 
 * @version 1.0.0
 * @version 1.3.0
 * @package MeuMouse.com
 */
class Hubgo_Shipping_Management_Init {
  
  public function __construct() {

    add_action( 'plugins_loaded', array( $this, 'hubgo_set_default_options' ), 999 );
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_scripts' ) );

    // load shipping calculator in single product page
    if ( self::get_setting('enable_shipping_calculator') === 'yes' ) {
      include_once HUBGO_SHIPPING_MANAGEMENT_INC_DIR . 'classes/class-hubgo-shipping-management-wc-shipping-calculator.php';
    }
  }


  /**
   * Set default options
   * 
   * @since 1.0.0
   * @version 1.3.0
   * @return array
   */
  public function set_default_data_options() {
    $options = array(
      'enable_shipping_calculator' => 'yes',
      'enable_auto_shipping_calculator' => 'yes',
      'primary_main_color' => '#008aff',
      'hook_display_shipping_calculator' => 'after_cart',
      'text_info_before_input_shipping_calc' => 'Consultar prazo e valor da entrega',
      'text_button_shipping_calc' => 'Calcular',
      'text_header_ship' => 'Entrega',
      'text_header_value' => 'Valor',
      'note_text_bottom_shipping_calc' => '*Este resultado é apenas uma estimativa para este produto. O valor final considerado, deverá ser o total do carrinho.',
      'text_placeholder_input_shipping_calc' => 'Informe seu CEP',
    );

    return $options;
  }

  
  /**
   * Gets the items from the array and inserts them into the option if it is empty,
   * or adds new items with default value to the option
   * 
   * @since 1.0.0
   * @version 1.3.0
   * @return void
   */
  public function hubgo_set_default_options() {
    $get_options = $this->set_default_data_options();
    $default_options = get_option('hubgo-shipping-management-wc-setting', array());

    if ( empty( $default_options ) ) {
      $options = $get_options;
      update_option('hubgo-shipping-management-wc-setting', $options);
    } else {
        $options = $default_options;

        foreach ( $get_options as $key => $value ) {
            if ( !isset( $options[$key] ) ) {
                $options[$key] = $value;
            }
        }

        update_option('hubgo-shipping-management-wc-setting', $options);
    }
  }    


  /**
   * Checks if the option exists and returns the indicated array item
   * 
   * @since 1.0.0
   * @version 1.3.0
   * @param $key | Array key
   * @return mixed | string or false
   */
  public static function get_setting( $key ) {
    $default_options = get_option('hubgo-shipping-management-wc-setting', array());

    // check if array key exists and return key
    if ( isset( $default_options[$key] ) ) {
        return $default_options[$key];
    }

    return false;
  }


  /**
   * Load scripts in front-end
   * 
   * @since 1.0.0
   * @version 1.3.0
   * @return void
   */
  public function enqueue_front_scripts() {
    wp_enqueue_script( 'hubgo-shipping-management-wc-front-scripts', HUBGO_SHIPPING_MANAGEMENT_ASSETS . 'front/js/hubgo-shipping-management-wc-front-scripts.js', array('jquery'), HUBGO_SHIPPING_MANAGEMENT_VERSION );
  }

}

new Hubgo_Shipping_Management_Init();