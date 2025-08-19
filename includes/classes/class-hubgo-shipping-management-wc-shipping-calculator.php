<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class for handle requests in shipping calculator
 * 
 * @since 1.0.0
 * @package MeuMouse.com
 */
class Hubgo_Shipping_Management_Shipping_Calculator {

    public function __construct() {
        add_action( 'wp_ajax_hubgo_ajax_postcode', array( $this,'hubgo_ajax_postcode' ) );
		add_action( 'wp_ajax_nopriv_hubgo_ajax_postcode', array( $this,'hubgo_ajax_postcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// display form shipping calc on page product
		if ( Hubgo_Shipping_Management_Init::get_setting( 'hook_display_shipping_calculator' ) === 'after_cart' ) {
			add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'hubgo_form_shipping_calc' ), 10 );
		} elseif ( Hubgo_Shipping_Management_Init::get_setting( 'hook_display_shipping_calculator' ) === 'before_cart' ) {
			add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'hubgo_form_shipping_calc' ), 10 );
		} elseif ( Hubgo_Shipping_Management_Init::get_setting( 'hook_display_shipping_calculator' ) === 'meta_end' ) {
			add_action( 'woocommerce_product_meta_end', array( $this, 'hubgo_form_shipping_calc' ), 10 );
		} else {
			add_shortcode( 'hubgo_shipping_calculator', array( $this, 'create_form_shipping_calc_shortcode' ) );
		}

		$this->update_default_customer_address();
	}


	/**
	 * Enqueue scripts on front-end
	 * 
	 * @since 1.0.0
	 * @return void
	 * @package MeuMouse.com
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'hubgo-shipping-calculator-styles', HUBGO_SHIPPING_MANAGEMENT_ASSETS . 'front/css/hubgo-shipping-calculator-styles.css', array(), HUBGO_SHIPPING_MANAGEMENT_VERSION );
		wp_enqueue_script( 'hubgo-shipping-calculator-scripts', HUBGO_SHIPPING_MANAGEMENT_ASSETS . 'front/js/hubgo-shipping-calculator.js', array('jquery'), HUBGO_SHIPPING_MANAGEMENT_VERSION );
	
		wp_localize_script( 'hubgo-shipping-calculator-scripts', 'hubgo_params', array(
		  'ajax_url' => admin_url( 'admin-ajax.php' ),
		  'nonce' => wp_create_nonce( 'hubgo-shipping-calc-nonce' ),
		  'without_selected_variation_message' => __( 'Por favor, escolha uma opção antes de calcular.', 'hubgo-shipping-management-wc' ),
		  'auto_shipping' => Hubgo_Shipping_Management_Init::get_setting('enable_auto_shipping_calculator'),
		) );
	}


	/**
	 * Call AJAX from front-end form
	 * 
	 * @since 1.0.0
	 * @return void
	 * @package MeuMouse.com
	 */
	public function hubgo_ajax_postcode() {
		check_ajax_referer( 'hubgo-shipping-calc-nonce', 'nonce' );

		$data = $_POST;
		$shipping_response = $this->get_product_shipping_estimate( $data );
		
		if ( is_array( $shipping_response ) && ! empty( $shipping_response ) ) {
			echo '<table cellspacing="0"  class="hubgo-table-shipping-methods">
				<tbody>';
			
			// if is different empty display header table
			if ( !empty( Hubgo_Shipping_Management_Init::get_setting( 'text_header_ship' ) ) || !empty( Hubgo_Shipping_Management_Init::get_setting( 'text_header_value' ) ) ) {
				echo '<tr class="hubgo-shipping-header">
				<th>'. Hubgo_Shipping_Management_Init::get_setting( 'text_header_ship' ) .'</th>
				<th>'. Hubgo_Shipping_Management_Init::get_setting( 'text_header_value' ) .'</th>
				</tr>';
			}

			foreach ( $shipping_response as $key => $shipping ) {
				echo '<tr class="hubgo-shipping-method">	
						<td>'. $shipping->label .'</td>
						<td>'. wc_price( $shipping->cost ) .'</td>
					</tr>';
				}

			if ( !empty( Hubgo_Shipping_Management_Init::get_setting( 'note_text_bottom_shipping_calc' ) ) ) {
				echo '<tr class="hubgo-shipping-bottom"><td colspan="2">';
					echo '<span>'. Hubgo_Shipping_Management_Init::get_setting( 'note_text_bottom_shipping_calc' ) .'</span>';
				echo '</td></tr>';
			}

			echo '</tbody> </table>';
		} else {
			echo '<div class="woocommerce-message woocommerce-error">' . esc_html__('Nenhuma forma de entrega disponível.', 'hubgo-shipping-management-wc') . '</div>';
		}

		wp_die();
	}


	/**
	 * Get shipping estimate date
	 * 
	 * @since 1.0.0
	 * @param array $request | Post data
	 * @return string
	 */
	public function get_product_shipping_estimate( array $request ) {
	    $product = wc_get_product( sanitize_text_field( $request['product'] ) );
	    
	    if ( !$product->needs_shipping() || get_option('woocommerce_calc_shipping') == 'no' ) {
			return __('Não foi possível calcular a entrega deste produto', 'hubgo-shipping-management-wc');
		}
	        
	    if ( !$product->is_in_stock() ) {
			return __('Não foi possível calcular a entrega deste produto, pois o mesmo não está disponível.', 'hubgo-shipping-management-wc');
		}

	    if ( !WC_Validation::is_postcode( $request['postcode'], WC()->customer->get_shipping_country() ) ) {
			return __('Por favor, insira um CEP válido.', 'hubgo-shipping-management-wc');
		}

	    $products = [$product];

	    if ( WC()->customer->get_shipping_country() ) {
	        $destination = [
	            'country' => WC()->customer->get_shipping_country(),
	            'state' => WC()->customer->get_shipping_state(),
	            'postcode' => sanitize_text_field( $request['postcode'] ),
	            'city' => WC()->customer->get_shipping_city(),
	            'address' => WC()->customer->get_shipping_address(),
	            'address_2' => WC()->customer->get_shipping_address_2(),
	        ];
	    } else {
	        $destination = wc_get_customer_default_location();
	    }

	    $package = [
	        'destination' => $destination,
	        'applied_coupons' => WC()->cart->applied_coupons,
	        'user' => ['ID' => get_current_user_id()],
	    ];

	    foreach ( $products as $data ) {
	        $cartId = WC()->cart->generate_cart_id($data->id, $product->is_type('variable') ? $data->variation_id : 0);
	        $price = $data->get_price_excluding_tax();
	        $tax = $data->get_price_including_tax() - $price;

	        $package['contents'] = [
	            $cartId => [
	                'product_id' => $data->id,
	                'data' => $data,
	                'quantity' => sanitize_text_field( $request['qty'] ),
	                'line_total' => $price,
	                'line_tax' => $tax,
	                'line_subtotal' => $price,
	                'line_subtotal_tax' => $tax,
	                'contents_cost' => $price,
	            ]
	        ];

	        if ( class_exists('WC_Correios_Webservice') ) {
				add_filter( 'woocommerce_correios_shipping_args', function( $array, $this_id, $this_instance_id, $this_package ) use( $price ){
					$option_id = 'woocommerce_'.$this_id.'_'.$this_instance_id.'_settings';
					$settings = get_option( $option_id );

					if ( 'yes' == $settings['declare_value'] ) {
						$array['nVlValorDeclarado'] = $price;
					}

					return $array;
				
				}, 10, 4 ); 
			}
		    
			$methods = WC_Shipping::instance()->load_shipping_methods($package);

	        foreach ( $methods as $key => $method ) {
	        	if ( "free_shipping" == $method->id && 'yes' == $method->enabled ) {
	        		$GLOBALS['method'] = $method;
	        		$has_coupon = $has_met_min_amount = false;

	        		if ( in_array( $method->requires, array( 'coupon', 'either', 'both' ) ) ) {

			            if ( $coupons = WC()->cart->get_coupons() ) {
			                foreach ( $coupons as $code => $coupon ) {
			                    if ( $coupon->is_valid() && $coupon->get_free_shipping() ) {
			                        $has_coupon = true;
			                        break;
			                    }
			                }
			            }
			        }

			        if ( in_array( $method->requires, array( 'min_amount', 'either', 'both' ) ) ) {
			            $_total = $price * $request['qty'];

			            if ( $_total >= $method->min_amount ) {
			                $has_met_min_amount = true;
			            }
			        }

			        switch ( $method->requires ) {
			            case 'min_amount' :
			                $is_available = $has_met_min_amount;
			                break;
			            case 'coupon' :
			                $is_available = $has_coupon;
			                break;
			            case 'both' :
			                $is_available = $has_met_min_amount && $has_coupon;
			                break;
			            case 'either' :
			                $is_available = $has_met_min_amount || $has_coupon;
			                break;
			            default :
			                $is_available = false;
			                break;
			        }

	        		break;
	        	}
	        }

			$rates = array();

	        if ( $is_available ) {
	        	$rates[] = (object) [
	        		'cost' => 0,
	        		'label' => $method->method_title
	        	];
	        }

	        $packageRates = WC_Shipping::instance()->calculate_shipping_for_package( $package );

			foreach ( $packageRates['rates'] as $rate ) {
				$meta = $rate->get_meta_data();
			
				if ( isset( $meta['_delivery_forecast'] ) ) {
					$delivery_forecast = $meta['_delivery_forecast'];
					$translated_delivery_forecast = sprintf( __('(Entrega em %s dias úteis)', 'hubgo-shipping-management-wc'), $delivery_forecast );
					$rate->set_label( $rate->get_label() . ' ' . $translated_delivery_forecast );
				}
			
				$rates[] = $rate;
			}

	        if ( isset( $rates ) ) {
				WC()->customer->set_shipping_postcode( $request['postcode'] );
				WC()->customer->set_billing_postcode( $request['postcode'] );
			}
	    }

		if ( isset( $rates ) ) {
			return $rates;
		} else {
			return "<div class='woocommerce-message woocommerce-error'>" . esc_html__('Nenhuma forma de entrega disponível.', 'hubgo-shipping-management-wc') . "</div>";
		}		
	}


	/**
	 * Update default address
	 * 
	 * @since 1.0.0
	 * @return void
	 * @package MeuMouse.com
	 */
	public function update_default_customer_address() {
		$address  = get_option('woocommerce_default_customer_address');

		if ( empty( $address ) ) {
			update_option( 'woocommerce_default_customer_address', 'geolocation' );
		}	
	}


	/**
	 * Form shipping calculator
	 * 
	 * @since 1.0.0
	 * @return void
	 * @package MeuMouse.com
	 */
	public function hubgo_form_shipping_calc() {
		echo '<div id="hubgo-shipping-calc">
		<span class="hubgo-info-shipping-calc">'. Hubgo_Shipping_Management_Init::get_setting( 'text_info_before_input_shipping_calc' ) .'</span>
		
		<div class="hubgo-form-group">
			<input id="hubgo-postcode" placeholder="'. Hubgo_Shipping_Management_Init::get_setting( 'text_placeholder_input_shipping_calc' ) .'" name="hubgo-postcode">
			<button id="hubgo-shipping-calc-button">
				<span class="hubgo-shipping-calc-button-title">'. Hubgo_Shipping_Management_Init::get_setting( 'text_button_shipping_calc' ) .'</span>
			</button>
		</div>
		
		<a class="hubgo-postcode-search" href="https://buscacepinter.correios.com.br/app/endereco/" target="_blank">'. esc_html__( 'Não sei meu CEP', 'hubgo-shipping-management-wc' ) .'</a>
		<div id="hubgo-response"></div>
		</div>';
	}


	/**
	 * Create form shipping calc shortcode
	 * 
	 * @since 1.0.0
	 * @return void
	 * @package MeuMouse.com
	 */
	public function create_form_shipping_calc_shortcode() {
		// check if page is single product
		if ( !is_product() ) {
			return;
		}

		ob_start();

		$this->hubgo_form_shipping_calc();

		return ob_get_clean();
	}
}

new Hubgo_Shipping_Management_Shipping_Calculator();