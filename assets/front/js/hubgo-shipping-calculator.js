jQuery(document).ready( function($) {
	'use strict'

	$( function() {
		$('#hubgo-shipping-calc-button').on('click', function() {
			let requestButton = $(this);
			let originalText = requestButton.text();
			let btnWidth = requestButton.width();
			let btnHeight = requestButton.height();
			
			// stay original width and height
			requestButton.width(btnWidth);
			requestButton.height(btnHeight);

			if( $('#hubgo-postcode').val().length < 3 ) {
				$('#hubgo-postcode').focus();
				return;
			}

			$('#hubgo-response').html('');
			var detected_variation = detect_product_variation();

			if( !detected_variation ) {
				$('#hubgo-response').fadeOut('fast', function() {
					$(this).html('<div class="woocommerce-message woocommerce-error">' + hubgo_params.without_selected_variation_message + '</div>').fadeIn('fast');
				});
			} else {
				requestButton.html('<span class="hubgo-button-loader"></span>');

				$.ajax( {
					type : 'post',
					url : hubgo_params.ajax_url + '?action=hubgo_ajax_postcode',
					data : {
						product : detected_variation,
						qty : ( $('.quantity input.qty').length ? $('.quantity input.qty').val() : 1 ),
						postcode : $('#hubgo-postcode').val(),
						nonce : hubgo_params.nonce,
					},
					success: function(response) {
						requestButton.html(originalText);

						$('#hubgo-response').fadeOut('fast',function() {
							$(this).html(response).fadeIn('fast');
						});
					}
				});
			}

		});

		$('form.cart, #hubgo-postcode').on('keypress', function(e) {
		 	var keyCode = e.keyCode || e.which;

			if (keyCode === 13) { 
				$('#hubgo-shipping-calc-button').click();
		    	e.preventDefault();

		    	return false;
		  	}
		});

	});

	/**
	 * Apply mask on input postcode, disable button if is empty and save postcode on Cookies
	 * 
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	$( function() {
		// Load postcode saved in cookies if it exists
		var savedCep = getCookie('savedCep');
		const postcode_input = $('#hubgo-postcode');
		var auto_calculator = hubgo_params.auto_shipping;

		if (savedCep) {
		  setFormattedCep(savedCep);
		}
	  
		$(postcode_input).on('input', function() {
		  let value = $(this).val().replace(/\D/g, '');
		  let formattedValue = '';
	  
		  if (value.length > 5) {
			formattedValue = value.substring(0, 5) + '-' + value.substring(5, 8);
		  } else {
			formattedValue = value;
		  }
	  
		  $(this).val(formattedValue);

	  
		  setCookie('savedCep', value, 30); // storage for 30 days
		});
	  
		$(window).on('load', function() {
			if ( postcode_input.val() !== '' && auto_calculator === 'yes' ) {
				$('#hubgo-shipping-calc-button').click();
			}
		});
	  
		function setCookie(name, value, days) {
		  var expires = '';

		  if (days) {
			var date = new Date();

			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = '; expires=' + date.toUTCString();
		  }

		  document.cookie = name + '=' + (value || '') + expires + '; path=/';
		}
	  
		function getCookie(name) {
		  var nameEQ = name + '=';
		  var ca = document.cookie.split(';');

		  for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) === ' ') c = c.substring(1, c.length);
			if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
		  }
		  
		  return null;
		}
	  
		function setFormattedCep(postcode) {
		  var formattedCep = postcode.replace(/^(\d{5})(\d{3})$/, '$1-$2');
		  $('#hubgo-postcode').val(formattedCep);
		}
	});

	/**
	 * Detect product variation in WooCommerce
	 * 
	 * @since 1.0.0
	 * @returns bool
	 * @package MeuMouse.com
	 */
	function detect_product_variation() {
		let variationId = jQuery('input[name=variation_id]').val();
		let addToCartValue = jQuery('*[name=add-to-cart]').val();
	
		if (variationId && variationId > 0) {
			return variationId;
		} else if (addToCartValue && addToCartValue > 0) {
			return addToCartValue;
		} else {
			return false;
		}
	}
	
});