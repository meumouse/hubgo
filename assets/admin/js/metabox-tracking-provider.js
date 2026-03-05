(function($){
    'use strict';

    var hubgoTrackingProvider = {
        init: function() {
            $( '.custom_tracking_link_field, .custom_tracking_provider_field' ).hide();

            $( '#hubgo_custom_tracking_link, #hubgo_tracking_number, #hubgo_tracking_provider' )
                .on( 'change keyup', this.updateTrackingLink )
                .trigger( 'change' );
        },

        updateTrackingLink: function() {
            var tracking = $( '#hubgo_tracking_number' ).val();
            var provider = $( '#hubgo_tracking_provider' ).val();
            var providers = hubgoTrackingProviderParams.providers || {};
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
        }
    };

    hubgoTrackingProvider.init();

})(jQuery);
