(function($){
    'use strict';

    var hubgoTrackingItems = {
        init: function() {
            $( '#hubgo-order-tracking' )
                .on( 'click', 'a.delete-tracking', this.deleteTracking )
                .on( 'click', 'button.button-show-form', this.showForm )
                .on( 'click', 'button.button-save-form', this.saveForm );
        },

        getOrderId: function() {
            return $( '#hubgo-order-tracking-inner' ).data( 'order-id' )
                || hubgoOrderTrackingParams.order_id
                || ( typeof woocommerce_admin_meta_boxes !== 'undefined' ? woocommerce_admin_meta_boxes.post_id : 0 )
                || $( '#post_ID' ).val()
                || 0;
        },

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

        getI18n: function( key, fallback ) {
            if ( hubgoOrderTrackingParams
                && hubgoOrderTrackingParams.i18n
                && hubgoOrderTrackingParams.i18n[ key ] ) {
                return hubgoOrderTrackingParams.i18n[ key ];
            }

            return fallback || '';
        },

        setLoading: function( $el, status ) {
            if ( status ) {
                $el.addClass( 'hubgo-is-loading' );
                $el.find( 'button, input, select, a.delete-tracking' ).prop( 'disabled', true );
            } else {
                $el.removeClass( 'hubgo-is-loading' );
                $el.find( 'button, input, select, a.delete-tracking' ).prop( 'disabled', false );
            }
        },

        saveForm: function() {
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

            return false;
        },

        showForm: function() {
            $( '#hubgo-shipment-tracking-form' ).show();
            $( '#hubgo-order-tracking button.button-show-form' ).hide();
        },

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

    hubgoTrackingItems.init();
    window.hubgo_tracking_refresh = hubgoTrackingItems.refreshItems;

})(jQuery);

