/**
 * HubGo tracking metabox scripts.
 *
 * @version 2.1.0
 * @since 2.1.0
 * @param {jQueryStatic} $ jQuery instance.
 * @return {void}
 */
(function($){
    'use strict';

    var hubgoTrackingProvider = {
        /**
         * Initialize tracking provider listeners.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {void} _ Unused.
         * @return {void}
         */
        init: function() {
            $( '.custom_tracking_link_field, .custom_tracking_provider_field' ).hide();

            $( '#hubgo_custom_tracking_link, #hubgo_tracking_number, #hubgo_tracking_provider' )
                .on( 'change keyup', this.updateTrackingLink )
                .trigger( 'change' );
        },

        /**
         * Build and render tracking preview URL.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {Event} event Trigger event.
         * @return {void}
         */
        updateTrackingLink: function( event ) {
            var tracking = $( '#hubgo_tracking_number' ).val();
            var provider = $( '#hubgo_tracking_provider' ).val();
            var providers = ( typeof hubgoTrackingProviderParams !== 'undefined' && hubgoTrackingProviderParams.providers )
                ? hubgoTrackingProviderParams.providers
                : {};
            var postcode = $( '#_shipping_postcode' ).val();
            var country = $( '#_shipping_country' ).val();
            var link = '';

            if ( ! postcode || ! postcode.length ) {
                postcode = $( '#_billing_postcode' ).val();
            }

            postcode = encodeURIComponent( postcode || '' );
            country = encodeURIComponent( country || '' );

            if ( provider && providers[ provider ] ) {
                link = providers[ provider ];
                link = link.replace( '%251%24s', tracking || '' );
                link = link.replace( '%252%24s', postcode );
                link = link.replace( '%253%24s', country );
                link = decodeURIComponent( link );

                $( '.custom_tracking_link_field, .custom_tracking_provider_field' ).hide();
            } else {
                $( '.custom_tracking_link_field, .custom_tracking_provider_field' ).show();
                link = $( '#hubgo_custom_tracking_link' ).val();
            }

            if ( link ) {
                $( '.preview_tracking_link a' ).attr( 'href', link );
                $( '.preview_tracking_link' ).show();
            } else {
                $( '.preview_tracking_link' ).hide();
            }

            void event;
        }
    };

    var hubgoTrackingItems = {
        /**
         * Initialize metabox action listeners.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {void} _ Unused.
         * @return {void}
         */
        init: function() {
            $( '#hubgo-order-tracking' )
                .on( 'click', 'a.delete-tracking', this.deleteTracking )
                .on( 'click', 'button.button-show-form', this.showForm )
                .on( 'click', 'button.button-save-form', this.saveForm );
        },

        /**
         * Resolve current order ID from admin contexts.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {void} _ Unused.
         * @return {number} Current order id.
         */
        getOrderId: function() {
            return $( '#hubgo-order-tracking-inner' ).data( 'order-id' )
                || hubgoOrderTrackingParams.order_id
                || ( typeof woocommerce_admin_meta_boxes !== 'undefined' ? woocommerce_admin_meta_boxes.post_id : 0 )
                || $( '#post_ID' ).val()
                || 0;
        },

        /**
         * Resolve nonce by operation type.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {string} type Nonce type: create|delete|get.
         * @return {string} Nonce value.
         */
        getNonce: function( type ) {
            var localized = hubgoOrderTrackingParams.nonces && hubgoOrderTrackingParams.nonces[ type ]
                ? hubgoOrderTrackingParams.nonces[ type ]
                : '';

            if ( localized ) {
                return localized;
            }

            if ( type === 'create' ) {
                return $( '#hubgo_tracking_create_nonce' ).val() || '';
            }

            if ( type === 'delete' ) {
                return $( '#hubgo_tracking_delete_nonce' ).val() || '';
            }

            return $( '#hubgo_tracking_get_nonce' ).val() || '';
        },

        /**
         * Get translated text with fallback.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {string} key I18n key.
         * @param {string} fallback Fallback text.
         * @return {string} Translation string.
         */
        getI18n: function( key, fallback ) {
            if ( hubgoOrderTrackingParams
                && hubgoOrderTrackingParams.i18n
                && hubgoOrderTrackingParams.i18n[ key ] ) {
                return hubgoOrderTrackingParams.i18n[ key ];
            }

            return fallback || '';
        },

        /**
         * Toggle loading state for a container.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {jQuery} $el Target container.
         * @param {boolean} status Loading status.
         * @return {void}
         */
        setLoading: function( $el, status ) {
            if ( status ) {
                $el.addClass( 'hubgo-is-loading' );
                $el.find( 'button, input, select, a.delete-tracking' ).prop( 'disabled', true );
            } else {
                $el.removeClass( 'hubgo-is-loading' );
                $el.find( 'button, input, select, a.delete-tracking' ).prop( 'disabled', false );
            }
        },

        /**
         * Save tracking item through AJAX.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {Event} event Click event.
         * @return {boolean} Always false to prevent default behavior.
         */
        saveForm: function( event ) {
            var trackingNumber = $( '#hubgo_tracking_number' ).val();
            var $form = $( '#hubgo-shipment-tracking-form' );

            if ( ! trackingNumber ) {
                return false;
            }

            hubgoTrackingItems.setLoading( $form, true );

            var nonce = hubgoTrackingItems.getNonce( 'create' );
            var data = {
                action: 'hubgo_tracking_save_item',
                order_id: hubgoTrackingItems.getOrderId(),
                tracking_provider: $( '#hubgo_tracking_provider' ).val(),
                custom_tracking_provider: $( '#hubgo_custom_tracking_provider' ).val(),
                custom_tracking_link: $( '#hubgo_custom_tracking_link' ).val(),
                tracking_number: trackingNumber,
                date_shipped: $( '#hubgo_date_shipped' ).val(),
                security: nonce,
                _ajax_nonce: nonce,
                nonce: nonce
            };

            $.post( hubgoOrderTrackingParams.ajax_url, data )
                .done( function( response ) {
                    if ( response && response !== '-1' ) {
                        $( '#hubgo-shipment-tracking-form' ).hide();
                        $( '#hubgo-order-tracking #hubgo-tracking-items' ).append( response );
                        $( '#hubgo-order-tracking button.button-show-form' ).show();
                        $( '#hubgo_tracking_provider' ).val( '' ).trigger( 'change' );
                        $( '#hubgo_custom_tracking_provider' ).val( '' );
                        $( '#hubgo_custom_tracking_link' ).val( '' );
                        $( '#hubgo_tracking_number' ).val( '' );
                        $( '.preview_tracking_link' ).hide();
                    }
                })
                .always( function() {
                    hubgoTrackingItems.setLoading( $form, false );
                } );

            void event;
            return false;
        },

        /**
         * Show tracking form in metabox.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {Event} event Click event.
         * @return {void}
         */
        showForm: function( event ) {
            $( '#hubgo-shipment-tracking-form' ).show();
            $( '#hubgo-order-tracking button.button-show-form' ).hide();
            void event;
        },

        /**
         * Delete tracking item through AJAX.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {Event} event Click event.
         * @return {boolean} False when action is interrupted or completed.
         */
        deleteTracking: function( event ) {
            if ( event ) {
                event.preventDefault();
            }

            var confirmMessage = hubgoTrackingItems.getI18n(
                'confirm_delete',
                'Are you sure you want to remove this tracking item?'
            );

            if ( ! window.confirm( confirmMessage ) ) {
                return false;
            }

            var trackingId = $( this ).attr( 'rel' );
            var $item = $( this ).closest( '.tracking-item' );

            if ( ! $item.length ) {
                $item = $( '[id="tracking-item-' + trackingId + '"]' );
            }
            var nonce = hubgoTrackingItems.getNonce( 'delete' )
                || $( '#hubgo_tracking_delete_nonce' ).val()
                || ( typeof hubgo_tracking_params !== 'undefined' ? hubgo_tracking_params.nonce : '' )
                || '';
            var legacyNonce = $( '#hubgo_tracking_nonce' ).val() || '';
            var ajaxUrl = ( hubgoOrderTrackingParams && hubgoOrderTrackingParams.ajax_url )
                ? hubgoOrderTrackingParams.ajax_url
                : ( typeof ajaxurl !== 'undefined' ? ajaxurl : '' );

            if ( ! nonce && ! legacyNonce ) {
                window.alert( hubgoTrackingItems.getI18n( 'missing_nonce', 'Could not validate the request. Please reload the page.' ) );
                return false;
            }

            hubgoTrackingItems.setLoading( $item, true );

            var data = {
                action: 'hubgo_tracking_delete_item',
                order_id: hubgoTrackingItems.getOrderId(),
                tracking_id: trackingId,
                security: nonce,
                _ajax_nonce: nonce,
                nonce: nonce,
                hubgo_tracking_nonce: legacyNonce
            };

            $.post( ajaxUrl, data )
                .done( function( response ) {
                    if ( response && response.success ) {
                        $item.stop( true, true ).slideUp( 220, function() {
                            $( this ).remove();
                        } );
                        return;
                    }

                    window.alert(
                        ( response && response.data && response.data.message )
                            ? response.data.message
                            : hubgoTrackingItems.getI18n( 'delete_error', 'Could not remove tracking item. Please try again.' )
                    );
                })
                .fail( function() {
                    window.alert( hubgoTrackingItems.getI18n( 'delete_error', 'Could not remove tracking item. Please try again.' ) );
                })
                .always( function() {
                    hubgoTrackingItems.setLoading( $item, false );
                } );

            return false;
        },

        /**
         * Refresh tracking item list from backend.
         *
         * @version 2.1.0
         * @since 2.1.0
         * @param {void} _ Unused.
         * @return {void}
         */
        refreshItems: function() {
            var nonce = hubgoTrackingItems.getNonce( 'get' );
            var data = {
                action: 'hubgo_tracking_get_items',
                order_id: hubgoTrackingItems.getOrderId(),
                security: nonce,
                _ajax_nonce: nonce,
                nonce: nonce
            };

            $.post( hubgoOrderTrackingParams.ajax_url, data, function( response ) {
                if ( response && response !== '-1' ) {
                    $( '#hubgo-order-tracking #hubgo-tracking-items' ).html( response );
                }
            });
        }
    };

    /**
     * Initialize metabox modules and expose refresh callback.
     *
     * @version 2.1.0
     * @since 2.1.0
     * @param {void} _ Unused.
     * @return {void}
     */
    hubgoTrackingProvider.init();
    hubgoTrackingItems.init();
    window.hubgo_tracking_refresh = hubgoTrackingItems.refreshItems;

})(jQuery);
