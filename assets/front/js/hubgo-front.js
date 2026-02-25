/**
 * HubGo Frontend Module
 *
 * Handles shipping calculator functionality on the frontend
 *
 * @since 2.0.0
 * @package HubGo
 * @author MeuMouse.com
 */

( function( $ ) {
	'use strict';

	const HubgoFront = {

		elements: {
			$calcContainer: null,
			$calcButton: null,
			$postcodeInput: null,
			$responseContainer: null,
			$formCart: null,
		},

		config: {
			cookieName: 'savedCep',
			cookieDays: 30,
			debounceDelay: 500,
			loadingHtml: '<span class="hubgo-button-loader"></span>',
		},

		/**
		 * Initialize module.
		 *
		 * Caches DOM elements, binds events and initializes postcode helpers
		 * (mask, cookie, and auto calculation on page load).
		 *
		 * @since 2.0.0
		 * @return {void}
		 */
		init: function() {
			this.cacheDom();
			this.bindEvents();
			this.initPostcodeMaskAndCookie();
			this.initAutoCalculationOnLoad();
		},

		/**
		 * Cache DOM elements used by the module.
		 *
		 * Stores jQuery references to avoid repeated DOM queries.
		 *
		 * @since 2.0.0
		 * @return {void}
		 */
		cacheDom: function() {
			this.elements.$calcContainer = $( '#hubgo-shipping-calc' );
			this.elements.$calcButton = $( '#hubgo-shipping-calc-button' );
			this.elements.$postcodeInput = $( '#hubgo-postcode' );
			this.elements.$responseContainer = $( '#hubgo-response' );
			this.elements.$formCart = $( 'form.cart' );
		},

		/**
		 * Bind frontend events for the shipping calculator.
		 *
		 * - Click on calculate button.
		 * - Press Enter on cart form or postcode input triggers calculation.
		 * - If auto shipping is enabled, triggers calculation on keyup with debounce.
		 *
		 * @since 2.0.0
		 * @return {void}
		 */
		bindEvents: function() {
			// Button click
			this.elements.$calcButton.on( 'click', this.handleCalculateClick.bind( this ) );

			// Trigger calc on Enter inside form cart or postcode input (same as original)
			this.elements.$formCart.add( this.elements.$postcodeInput ).on( 'keypress', function( e ) {
				const keyCode = e.keyCode || e.which;

				if ( keyCode === 13 ) {
					$( '#hubgo-shipping-calc-button' ).trigger( 'click' );
					e.preventDefault();
					return false;
				}
			} );

			// Auto calculator (keyup debounce) when enabled
			if ( this.isAutoShippingEnabled() ) {
				this.elements.$postcodeInput.on(
					'keyup',
					this.debounce( this.handleCalculateClick.bind( this ), this.config.debounceDelay )
				);
			}
		},

		/**
		 * Handle calculation request.
		 *
		 * Validates postcode, detects current product/variation, sets loading state,
		 * performs AJAX request and renders the response or an error message.
		 *
		 * @since 2.0.0
		 * @param {Event} [event] jQuery event object.
		 * @return {void}
		 */
		handleCalculateClick: function( event ) {
			if ( event ) {
				event.preventDefault();
			}

			const $button = this.elements.$calcButton;
			const originalHtml = $button.html();

			// Keep original button size (same behavior)
			const btnWidth = $button.width();
			const btnHeight = $button.height();
			$button.width( btnWidth );
			$button.height( btnHeight );

			// Basic postcode validation (same spirit as original)
			const postcodeFormatted = this.elements.$postcodeInput.val() || '';
			const postcodeRaw = postcodeFormatted.replace( /\D/g, '' );

			if ( postcodeRaw.length < 3 ) {
				this.elements.$postcodeInput.trigger( 'focus' );
				return;
			}

			// Clear response (same as original)
			this.elements.$responseContainer.html( '' );

			// Detect product / variation like original
			const detectedProduct = this.detectProductVariation();

			if ( ! detectedProduct ) {
				this.elements.$responseContainer.fadeOut( 'fast', function() {
					$( this )
						.html(
							'<div class="woocommerce-message woocommerce-error">' +
							( hubgo_params && hubgo_params.without_selected_variation_message ? hubgo_params.without_selected_variation_message : '' ) +
							'</div>'
						)
						.fadeIn( 'fast' );
				} );

				return;
			}

			// Loading state like original
			$button.html( this.config.loadingHtml );

			$.ajax( {
				type: 'post',
				url: ( hubgo_params && hubgo_params.ajax_url ) ? hubgo_params.ajax_url : ( hubgo_front_params ? hubgo_front_params.ajax_url : '' ),
				data: {
					action: 'hubgo_ajax_postcode',
					product: detectedProduct,
					qty: this.getQuantity(),
					postcode: postcodeFormatted, // original sent formatted value
					nonce: hubgo_params ? hubgo_params.nonce : '',
				},
				success: ( response ) => {
					$button.html( originalHtml );

					this.elements.$responseContainer.fadeOut( 'fast', function() {
						$( this ).html( response ).fadeIn( 'fast' );
					} );

					/**
					 * Triggered after successful shipping calculation.
					 *
					 * @since 2.0.0
					 * @event hubgo:shipping_calculated
					 */
					$( document ).trigger( 'hubgo:shipping_calculated', [ response ] );
				},
				error: ( jqXHR, textStatus, errorThrown ) => {
					$button.html( originalHtml );

					this.elements.$responseContainer.html(
						'<div class="woocommerce-message woocommerce-error">' +
						( hubgo_front_params && hubgo_front_params.error_message ? hubgo_front_params.error_message : 'Erro ao calcular frete.' ) +
						'</div>'
					);

					/**
					 * Triggered when shipping calculation fails.
					 *
					 * @since 2.0.0
					 * @event hubgo:shipping_error
					 */
					$( document ).trigger( 'hubgo:shipping_error', [ textStatus, errorThrown ] );
				},
			} );
		},

		/**
		 * Get product quantity from WooCommerce quantity field.
		 *
		 * Falls back to 1 when quantity input is not present or invalid.
		 *
		 * @since 2.0.0
		 * @return {number}
		 */
		getQuantity: function() {
			const $qty = $('.quantity input.qty');

			return $qty.length ? ( parseInt( $qty.val(), 10 ) || 1 ) : 1;
		},

		/**
		 * Detect selected product or variation ID in WooCommerce.
		 *
		 * Uses `variation_id` first (variable products). If not found, falls back to
		 * the `add-to-cart` field value (simple products).
		 *
		 * @since 2.0.0
		 * @return {string|boolean} Variation/Product ID when found, otherwise false.
		 */
		detectProductVariation: function() {
			const variationId = $( 'input[name="variation_id"]' ).val();
			const addToCartValue = $( '*[name="add-to-cart"]' ).val();

			if ( variationId && parseInt( variationId, 10 ) > 0 ) {
				return variationId;
			}

			if ( addToCartValue && parseInt( addToCartValue, 10 ) > 0 ) {
				return addToCartValue;
			}

			return false;
		},

		/**
		 * Initialize postcode input mask and persist value in cookie.
		 *
		 * - Loads saved postcode (cookie) and populates the input.
		 * - Applies CEP formatting (#####-###) while typing.
		 * - Saves raw digits-only CEP in a cookie for a defined period.
		 *
		 * @since 2.0.0
		 * @return {void}
		 */
		initPostcodeMaskAndCookie: function() {
			const savedCep = this.getCookie( this.config.cookieName );

			if ( savedCep ) {
				this.setFormattedCep( savedCep );
			}

			this.elements.$postcodeInput.on( 'input', ( e ) => {
				let value = $( e.currentTarget ).val().replace( /\D/g, '' );
				let formattedValue = '';

				if ( value.length > 5 ) {
					formattedValue = value.substring( 0, 5 ) + '-' + value.substring( 5, 8 );
				} else {
					formattedValue = value;
				}

				$( e.currentTarget ).val( formattedValue );

				// Save raw value
				this.setCookie( this.config.cookieName, value, this.config.cookieDays );
			} );
		},

		/**
		 * Auto calculate shipping on window load when enabled.
		 *
		 * If auto shipping is enabled and postcode input is not empty, triggers
		 * the calculate button click automatically after page is fully loaded.
		 *
		 * @since 2.0.0
		 * @return {void}
		 */
		initAutoCalculationOnLoad: function() {
			$( window ).on( 'load', () => {
				if ( this.isAutoShippingEnabled() && this.elements.$postcodeInput.val() !== '' ) {
					this.elements.$calcButton.trigger( 'click' );
				}
			} );
		},

		/**
		 * Check whether auto shipping calculation is enabled.
		 *
		 * Reads `hubgo_params.auto_shipping` expected to be localized by WordPress.
		 *
		 * @since 2.0.0
		 * @return {boolean}
		 */
		isAutoShippingEnabled: function() {
			return typeof hubgo_params !== 'undefined' && hubgo_params.auto_shipping === 'yes';
		},

		/**
		 * Set a cookie with optional expiration.
		 *
		 * @since 2.0.0
		 * @param {string} name Cookie name.
		 * @param {string} value Cookie value.
		 * @param {number} [days] Expiration in days.
		 * @return {void}
		 */
		setCookie: function( name, value, days ) {
			let expires = '';

			if ( days ) {
				const date = new Date();
				date.setTime( date.getTime() + ( days * 24 * 60 * 60 * 1000 ) );
				expires = '; expires=' + date.toUTCString();
			}

			document.cookie = name + '=' + ( value || '' ) + expires + '; path=/';
		},

		/**
		 * Get a cookie value by name.
		 *
		 * @since 2.0.0
		 * @param {string} name Cookie name.
		 * @return {string|null} Cookie value when found, otherwise null.
		 */
		getCookie: function( name ) {
			const nameEQ = name + '=';
			const ca = document.cookie.split( ';' );

			for ( let i = 0; i < ca.length; i++ ) {
				let c = ca[ i ];

				while ( c.charAt( 0 ) === ' ' ) {
					c = c.substring( 1, c.length );
				}

				if ( c.indexOf( nameEQ ) === 0 ) {
					return c.substring( nameEQ.length, c.length );
				}
			}

			return null;
		},

		/**
		 * Set the CEP input value using the formatted pattern (#####-###).
		 *
		 * Expects raw digits-only postcode and formats it before assigning.
		 *
		 * @since 2.0.0
		 * @param {string} postcode Raw postcode (digits only).
		 * @return {void}
		 */
		setFormattedCep: function( postcode ) {
			const formattedCep = ( postcode || '' ).replace( /^(\d{5})(\d{3})$/, '$1-$2' );
			this.elements.$postcodeInput.val( formattedCep );
		},

		/**
		 * Debounce helper to limit rapid-fire function calls.
		 *
		 * Useful for keyup handlers to avoid excessive AJAX requests.
		 *
		 * @since 2.0.0
		 * @param {Function} func Function to debounce.
		 * @param {number} wait Delay in milliseconds.
		 * @return {Function} Debounced function.
		 */
		debounce: function( func, wait ) {
			let timeout;

			return function() {
				const context = this;
				const args = arguments;

				clearTimeout( timeout );

				timeout = setTimeout( function() {
					func.apply( context, args );
				}, wait );
			};
		},
	};

    /**
     * Initialize HubgoFront when document is ready and the shipping calculator container exists.
     * Ensures that the module only initializes on pages where the shipping calculator is present.
     * 
     * @since 2.0.0
     */
	$( document ).ready( function() {
		if ( $('#hubgo-shipping-calc').length ) {
			HubgoFront.init();
		}
	} );

    /**
     * Export HubgoFront to global scope for potential external usage.
     *
     * This allows other scripts to access HubgoFront methods or properties if needed.
     *
     * @since 2.0.0
     */
	window.HubgoFront = HubgoFront;

} )( jQuery );