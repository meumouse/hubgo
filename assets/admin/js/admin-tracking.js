(function($){
    'use strict';

    $(document).on('click', '.hubgo-add-tracking', function(){
        const data = {
            action: 'hubgo_add_tracking',
            order_id: $('#post_ID').val(),
            tracking_number: $('#hubgo_tracking_number').val(),
            carrier: $('#hubgo_tracking_carrier').val(),
            ship_date: $('#hubgo_tracking_date').val(),
            nonce: hubgo_tracking_params.nonce
        };

        $.post(ajaxurl, data, function(response){
            if(response.success){
                location.reload();
            }
        });
    });

})(jQuery);