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
            return hubgoOrderTrackingParams.order_id || $( '#post_ID' ).val() || 0;
        },

        saveForm: function() {
            var trackingNumber = $( '#hubgo_tracking_number' ).val();

            if ( ! trackingNumber ) {
                return false;
            }

            var data = {
                action: 'hubgo_tracking_save_item',
                order_id: hubgoTrackingItems.getOrderId(),
                tracking_provider: $( '#hubgo_tracking_provider' ).val(),
                custom_tracking_provider: $( '#hubgo_custom_tracking_provider' ).val(),
                custom_tracking_link: $( '#hubgo_custom_tracking_link' ).val(),
                tracking_number: trackingNumber,
                date_shipped: $( '#hubgo_date_shipped' ).val(),
                security: $( '#hubgo_tracking_create_nonce' ).val()
            };

            $.post( hubgoOrderTrackingParams.ajax_url, data, function( response ) {
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
            });

            return false;
        },

        showForm: function() {
            $( '#hubgo-shipment-tracking-form' ).show();
            $( '#hubgo-order-tracking button.button-show-form' ).hide();
        },

        deleteTracking: function() {
            var trackingId = $( this ).attr( 'rel' );
            var data = {
                action: 'hubgo_tracking_delete_item',
                order_id: hubgoTrackingItems.getOrderId(),
                tracking_id: trackingId,
                security: $( '#hubgo_tracking_delete_nonce' ).val()
            };

            $.post( hubgoOrderTrackingParams.ajax_url, data, function( response ) {
                if ( response && response !== '-1' ) {
                    $( '#tracking-item-' + trackingId ).remove();
                }
            });

            return false;
        },

        refreshItems: function() {
            var data = {
                action: 'hubgo_tracking_get_items',
                order_id: hubgoTrackingItems.getOrderId(),
                security: $( '#hubgo_tracking_get_nonce' ).val()
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
