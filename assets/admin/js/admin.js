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

        deleteTracking: function() {
            var trackingId = $( this ).attr( 'rel' );
            var $item = $( '#tracking-item-' + trackingId );
            var nonce = hubgoTrackingItems.getNonce( 'delete' );

            hubgoTrackingItems.setLoading( $item, true );

            var data = {
                action: 'hubgo_tracking_delete_item',
                order_id: hubgoTrackingItems.getOrderId(),
                tracking_id: trackingId,
                security: nonce,
                _ajax_nonce: nonce,
                nonce: nonce
            };

            $.post( hubgoOrderTrackingParams.ajax_url, data )
                .done( function( response ) {
                    if ( response && response !== '-1' ) {
                        $item.remove();
                    }
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