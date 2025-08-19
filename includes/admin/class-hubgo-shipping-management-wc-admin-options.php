<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class Hubgo_Shipping_Management_Admin_Options extends Hubgo_Shipping_Management_Init {

  /**
   * Hubgo_Shipping_Management_Admin constructor.
   *
   * @since 1.0.0
   * @access public
   */
  public function __construct() {
    parent::__construct();

    add_action( 'admin_menu', array( $this, 'hubgo_shipping_management_wc_admin_menu' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'hubgo_shipping_management_wc_admin_scripts' ) );
    add_action( 'wp_ajax_hubgo_ajax_save_options', array( $this, 'hubgo_ajax_save_options_callback' ) );
    add_action( 'wp_ajax_nopriv_hubgo_ajax_save_options', array( $this, 'hubgo_ajax_save_options_callback' ) );
  }

  /**
   * Function for create submenu in settings
   * 
   * @since 1.0.0
   * @access public
   * @return array
   */
  public function hubgo_shipping_management_wc_admin_menu() {
    add_submenu_page(
      'woocommerce', // parent page slug
      esc_html__( 'HubGo - Gerenciamento de Frete para WooCommerce', 'hubgo-shipping-management-wc'), // page title
      esc_html__( 'HubGo', 'hubgo-shipping-management-wc'), // submenu title
      'manage_woocommerce', // user capabilities
      'hubgo-shipping-management-wc', // page slug
      array( $this, 'hubgo_shipping_management_wc_settings_page' ) // public function for print content page
    );
  }


  /**
   * Plugin general setting page and save options
   * 
   * @since 1.0.0
   * @access public
   */
  public function hubgo_shipping_management_wc_settings_page() {
    include_once HUBGO_SHIPPING_MANAGEMENT_INC_DIR . 'admin/settings.php';
  }


  /**
   * Enqueue admin scripts in page settings only
   * 
   * @since 1.0.0
   * @access public
   * @return void
   */
  public function hubgo_shipping_management_wc_admin_scripts() {
    $url = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
    
    if ( false !== strpos( $url, 'admin.php?page=hubgo-shipping-management-wc' ) ) {
      wp_enqueue_script( 'hubgo-shipping-management-wc-admin-scripts', HUBGO_SHIPPING_MANAGEMENT_ASSETS . 'admin/js/hubgo-shipping-management-wc-admin-scripts.js', array('jquery'), HUBGO_SHIPPING_MANAGEMENT_VERSION );
      wp_enqueue_style( 'hubgo-shipping-management-wc-admin-styles', HUBGO_SHIPPING_MANAGEMENT_ASSETS . 'admin/css/hubgo-shipping-management-wc-admin-styles.css', array(), HUBGO_SHIPPING_MANAGEMENT_VERSION );
    
      wp_localize_script( 'hubgo-shipping-management-wc-admin-scripts', 'hubgo_admin_params', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
      ));
    }
  }


  /**
   * Save options in AJAX
   * 
   * @since 1.2.0
   * @return void
   * @package MeuMouse.com
   */
  public function hubgo_ajax_save_options_callback() {
    if ( isset( $_POST['form_data'] ) ) {
        // Convert serialized data into an array
        parse_str( $_POST['form_data'], $form_data );

        $options = get_option( 'hubgo-shipping-management-wc-setting' );
        $options['enable_shipping_calculator'] = isset( $form_data['enable_shipping_calculator'] ) ? 'yes' : 'no';
        $options['enable_auto_shipping_calculator'] = isset( $form_data['enable_auto_shipping_calculator'] ) ? 'yes' : 'no';

        // Merge the form data with the default options
        $updated_options = wp_parse_args( $form_data, $options );

        // Save the updated options
        update_option( 'hubgo-shipping-management-wc-setting', $updated_options );

        $response = array(
          'status' => 'success',
          'options' => $updated_options,
        );

        echo wp_json_encode( $response ); // Send JSON response
    }

    wp_die();
  }

}

new Hubgo_Shipping_Management_Admin_Options();