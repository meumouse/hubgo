/**
 * HubGo Frontend Module
 *
 * Handles shipping calculator functionality on the frontend
 *
 * @since 2.0.0
 * @package HubGo
 * @author MeuMouse.com
 */

( function($) {
    'use strict';

    /**
     * HubGo Frontend Object
     *
     * @since 2.0.0
     */
    const HubgoFront = {

        /**
         * DOM elements cache
         * 
         * @since 2.0.0
         */
        elements: {
            $calcContainer: null,
            $calcButton: null,
            $postcodeInput: null,
            $responseContainer: null,
        },

        /**
         * Configuration
         * 
         * @since 2.0.0
         */
        config: {
            minPostcodeLength: 8,
            debounceDelay: 500,
            loadingClass: 'loading',
            spinnerClass: 'spinner',
            buttonTitleClass: '.hubgo-shipping-calc-button-title',
            productInputSelector: 'input[name="product_id"]',
            quantitySelector: 'input[name="quantity"]',
        },

        /**
         * Initialize the module
         *
         * @since 2.0.0
         * @return {void}
         */
        init: function() {
            this.cacheDom();
            this.bindEvents();
        },

        /**
         * Cache DOM elements for better performance
         *
         * @since 2.0.0
         * @return {void}
         */
        cacheDom: function() {
            this.elements = {
                $calcContainer: $('#hubgo-shipping-calc'),
                $calcButton: $('#hubgo-shipping-calc-button'),
                $postcodeInput: $('#hubgo-postcode'),
                $responseContainer: $('#hubgo-response'),
            };
        },

        /**
         * Bind event listeners
         *
         * @since 2.0.0
         * @return {void}
         */
        bindEvents: function() {
            // Click event for calculate button
            this.elements.$calcButton.on(
                'click',
                this.handleCalculateClick.bind(this)
            );
            
            // Auto calculation if enabled
            this.initAutoCalculation();
        },

        /**
         * Initialize auto calculation feature
         *
         * @since 2.0.0
         * @return {void}
         */
        initAutoCalculation: function() {
            if ( ! this.isAutoShippingEnabled() ) {
                return;
            }

            this.elements.$postcodeInput.on(
                'keyup',
                this.debounce(
                    this.handleCalculateClick.bind(this),
                    this.config.debounceDelay
                )
            );
        },

        /**
         * Check if auto shipping is enabled
         *
         * @since 2.0.0
         * @return {boolean}
         */
        isAutoShippingEnabled: function() {
            return typeof hubgo_params !== 'undefined' 
                && hubgo_params.auto_shipping === 'yes';
        },

        /**
         * Handle calculate button click
         *
         * @since 2.0.0
         * @param {Event} event Click event
         * @return {void}
         */
        handleCalculateClick: function(event) {
            if ( event ) {
                event.preventDefault();
            }

            const postcode = this.getPostcodeValue();
            const productId = this.getProductId();
            const quantity = this.getQuantity();

            if ( ! this.validateInputs( postcode, productId ) ) {
                return;
            }

            this.calculateShipping( postcode, productId, quantity );
        },

        /**
         * Get postcode input value
         *
         * @since 2.0.0
         * @return {string}
         */
        getPostcodeValue: function() {
            return this.elements.$postcodeInput.val() || '';
        },

        /**
         * Get product ID from various sources
         *
         * @since 2.0.0
         * @return {string}
         */
        getProductId: function() {
            const $productInput = $(this.config.productInputSelector);
            
            return $productInput.val() || this.getQueryParam('product_id') || '';
        },

        /**
         * Get quantity value
         *
         * @since 2.0.0
         * @return {number}
         */
        getQuantity: function() {
            const $qtyInput = $(this.config.quantitySelector);
            
            return parseInt( $qtyInput.val() ) || 1;
        },

        /**
         * Validate inputs before shipping calculation
         *
         * @since 2.0.0
         * @param {string} postcode
         * @param {string} productId
         * @return {boolean}
         */
        validateInputs: function( postcode, productId ) {
            // Check if postcode exists and has minimum length
            if ( ! postcode || postcode.length < this.config.minPostcodeLength ) {
                return false;
            }

            // Check if product ID exists
            if ( ! productId ) {
                return false;
            }

            return true;
        },

        /**
         * Calculate shipping via AJAX
         *
         * @since 2.0.0
         * @param {string} postcode
         * @param {string} productId
         * @param {number} quantity
         * @return {void}
         */
        calculateShipping: function( postcode, productId, quantity ) {
            const self = this;

            this.setLoading( true );

            $.ajax({
                url: hubgo_front_params.ajax_url,
                type: 'POST',
                dataType: 'html',
                data: {
                    action: 'hubgo_ajax_postcode',
                    nonce: hubgo_params.nonce,
                    product: productId,
                    postcode: postcode,
                    qty: quantity,
                },
                success: function( response ) {
                    self.handleSuccess( response );
                },
                error: function( jqXHR, textStatus, errorThrown ) {
                    self.handleError( textStatus, errorThrown );
                },
                complete: function() {
                    self.setLoading( false );
                },
            });
        },

        /**
         * Handle successful AJAX response
         *
         * @since 2.0.0
         * @param {string} response
         * @return {void}
         */
        handleSuccess: function( response ) {
            this.elements.$responseContainer.html( response );
            
            /**
             * Trigger event after shipping calculation
             *
             * @since 2.0.0
             */
            $(document).trigger( 'hubgo:shipping_calculated', [ response ] );
        },

        /**
         * Handle AJAX error
         *
         * @since 2.0.0
         * @param {string} textStatus
         * @param {string} errorThrown
         * @return {void}
         */
        handleError: function( textStatus, errorThrown ) {
            // Log error for debugging
            if ( window.console && console.error ) {
                console.error( 'HubGo Shipping Error:', textStatus, errorThrown );
            }

            // Show user-friendly error message
            this.elements.$responseContainer.html(
                '<div class="woocommerce-message woocommerce-error">' +
                hubgo_front_params.error_message +
                '</div>'
            );

            /**
             * Trigger event on shipping calculation error
             *
             * @since 2.0.0
             */
            $(document).trigger( 'hubgo:shipping_error', [ textStatus, errorThrown ] );
        },

        /**
         * Set loading state
         *
         * @since 2.0.0
         * @param {boolean} isLoading
         * @return {void}
         */
        setLoading: function( isLoading ) {
            const $button = this.elements.$calcButton;
            const $title = $button.find( this.config.buttonTitleClass );

            if ( isLoading ) {
                $button
                    .addClass( this.config.loadingClass )
                    .prop( 'disabled', true );
                
                $title.hide();
                
                this.addSpinner( $button );
            } else {
                $button
                    .removeClass( this.config.loadingClass )
                    .prop( 'disabled', false );
                
                $title.show();
                
                this.removeSpinner( $button );
            }
        },

        /**
         * Add loading spinner to button
         *
         * @since 2.0.0
         * @param {jQuery} $button
         * @return {void}
         */
        addSpinner: function( $button ) {
            if ( $button.find( '.' + this.config.spinnerClass ).length === 0 ) {
                $button.append( '<span class="' + this.config.spinnerClass + '"></span>' );
            }
        },

        /**
         * Remove loading spinner from button
         *
         * @since 2.0.0
         * @param {jQuery} $button
         * @return {void}
         */
        removeSpinner: function( $button ) {
            $button.find( '.' + this.config.spinnerClass ).remove();
        },

        /**
         * Get query parameter from URL
         *
         * @since 2.0.0
         * @param {string} name Parameter name
         * @return {string|null}
         */
        getQueryParam: function( name ) {
            const results = new RegExp( '[\?&]' + name + '=([^&#]*)' )
                .exec( window.location.href );
            
            return results ? results[1] : null;
        },

        /**
         * Debounce function to limit execution rate
         *
         * @since 2.0.0
         * @param {Function} func Function to debounce
         * @param {number} wait Milliseconds to wait
         * @return {Function}
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
     * Initialize on document ready
     *
     * @since 2.0.0
     */
    $(document).ready(function() {
        if ( $('#hubgo-shipping-calc').length > 0 ) {
            HubgoFront.init();
        }
    });

    /**
     * Make HubgoFront available globally
     *
     * @since 2.0.0
     */
    window.HubgoFront = HubgoFront;

})(jQuery);