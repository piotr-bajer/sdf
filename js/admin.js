/*
admin.js
*/
(function($) {
	$.post(ajaxurl, {
		action: 'sdf_stripe_default_plans_create'	
	});
}(jQuery));