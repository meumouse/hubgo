(function ($) {
    "use strict";

	/**
	 * Hide toast on click button or after 5 seconds
	 * 
	 * @since 1.0.0
	 */
	jQuery( function($) {
		$('.hide-toast').click( function() {
			$('.update-notice-spm-wp').fadeOut('fast');
		});

		setTimeout( function() {
			$('.update-notice-spm-wp').fadeOut('fast');
		}, 3000);
	});


	/**
	 * Save options in AJAX
	 * 
	 * @since 1.2.0
	 */
	jQuery( function($) {
		let settingsForm = $('form[name="hubgo-shipping-management-wc"]');
		let originalValues = settingsForm.serialize();
		var notificationDelay;
	
		settingsForm.on('change', function() {
			if (settingsForm.serialize() != originalValues) {
				ajax_save_options(); // send option serialized on change
			}
		});
	
		function ajax_save_options() {
			$.ajax({
				url: hubgo_admin_params.ajax_url,
				type: 'POST',
				data: {
					action: 'hubgo_ajax_save_options',
					form_data: settingsForm.serialize(),
				},
				success: function(response) {
					try {
						var responseData = JSON.parse(response); // Parse the JSON response

						if (responseData.status === 'success') {
							originalValues = settingsForm.serialize();
							$('.update-notice-spm-wp').addClass('active');
							
							if (notificationDelay) {
								clearTimeout(notificationDelay);
							}
				
							notificationDelay = setTimeout( function() {
								$('.update-notice-spm-wp').fadeOut('fast', function() {
									$(this).removeClass('active').css('display', '');
								});
							}, 3000);
						}
					} catch (error) {
						console.log(error);
					}
				}
			});
		}
	});

})(jQuery);