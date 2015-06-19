/*
File: donate_form.js
Author: Steve Avery
Date: September
Provides client ajax functions for the donation form.
*/

var sdf = {};

sdf_new = (function($) {

	var self = this;

	// jquery way to check if dom element exists
	$.fn.exists = function() { return this.length > 0 }

	self.opts = {
		class_prefix: 'sdf',
		ids: {
			form: 'sdf_form',
			submit: 'js-form-submit',
		},
		regex: {
			birth_month: /^(0?[1-9]|1[012])/,
			birth_year: /^(19|20)\d{2}$/,
			cc_expiry_month: /^(0?[1-9]|1[0-2])$/,
			cc_expiry_year: /^\d{2}/,
			cc_zipcode: /^\d{5}(-\d{4})?$/,
			credit_card: /\d{14,16}/,
			custom_amount: /^[$]?\d+([.]\d{2})?$/,
			cvc: /[\d]{3,4}/,
			phone: /^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/,
			state: /[a-zA-Z]{2}/,
			zipcode: /^\d{5}(-\d{4})?$/,
		},
	};

	self.elems = {
		form: {},
		submit: {},
		loading: {},
		saved: [], // save array of elements to revive
	};

	self.spinner = {
		obj: {},
		opts: {
			lines: 9, // The number of lines to draw
			length: 13, // The length of each line
			width: 7, // The line thickness
			radius: 26, // The radius of the inner circle
			corners: 0.8, // Corner roundness (0..1)
			rotate: 30, // The rotation offset
			direction: 1, // 1: clockwise, -1: counterclockwise
			color: '#FFF', // #rgb or #rrggbb or array of colors
			speed: 0.7, // Rounds per second
			trail: 76, // Afterglow percentage
			shadow: false, // Whether to render a shadow
			hwaccel: true, // Whether to use hardware acceleration
			className: 'spinner', // The CSS class to assign to the spinner
			zIndex: 2e9, // The z-index (defaults to 2000000000)
			top: 'auto', // Top position relative to parent in px
			left: 'auto' // Left position relative to parent in px
		},
	};

	self.show_error = function(e) {
		console.log("Error: " + e.message + ". From: " + e.from);
	}

	self.amount_validate = function() {
		return false;
	}

	self.activate_custom_amount = function(el) {
		var input_id = el.attr('for');
		if (typeof self.elems.saved[input_id] !== 'undefined') {
			el.after(self.elems.saved[input_id]);
			$(self.elems.saved[input_id]).focus();
		} else {
			var new_input = document.createElement('input');
			new_input.name = $(el).attr('for');
			new_input.class = self.class_prefix + '-custom_amount amount';
			new_input.placeholder = 'custom amount';
			new_input.required = true;
			new_input.type = 'text';
			new_input.id = input_id;
			el.after(new_input);
			$(new_input).focus();
		}
	}

	self.destroy_associated_input = function(el) {

		// TODO: I probably should remove the element. DOM stuff is expensive.
		// Maybe just convert this stuff to shows and hides?

		var input_id = el.attr('for');
		var to_remove = $('#' + input_id);
		if (to_remove.exists()) {
			self.elems.saved[input_id] = to_remove;
			to_remove.remove();
		} // else nothing to remove
	}

	self.attach_validation_to_form = function() {

		if (!self.elems.form.exists()) {
			throw { message: 'FormNonExistent', from: 'attach_validation_to_form' }
		}

		if (!self.elems.submit.exists()) {
			throw { message: 'SubmitNonExistent', from: 'attach_validation_to_form' }
		}

		// TODO: I should probably add an 'on change' event for any elements that
		// are already invalid and changed. If they enter something valid I should
		// immeditately remove the error.

		// TODO: Need to populate OR remove inputs when the "Copy billing
		// information from above?" is checked.

		self.activate_amount_clicks();
		self.activate_submit_click();

	}

	self.activate_amount_clicks = function() {

		var els = $('#' + self.opts.ids.form + ' .amount');
		var labels = $('#' + self.opts.ids.form + ' .amount-label');
		var customs = $('#' + self.opts.ids.form + ' .custom-amount-label');
// TODO: There's a checkbox below the amount radio boxes saying "No thanks,
		// I only want to make a one-time gift of the amount above.". Should I hide
		// all of the monthly gift boxes if this is checked?

		// onclick for regular buttons
		$.each(labels, function(idx) {
			var el = $(this);
			el.on('click', function(e) {

				e.preventDefault();

				customs.removeClass('selected');
				labels.removeClass('selected');
				els.prop('checked', false);

				customs.each(function(idx) {
					self.destroy_associated_input($(this));
				});

				// I don't think we need this because a label with the 'for' attribute
				// will automatically mark its associated input as checked.
				// input.name = label.for
				//$('#' + self.opts.ids.form + ' #' + el.attr('for'))
				//.prop('checked', true);

				el.addClass('selected');

			});
		});

		// treat the custom labels differently
		$.each(customs, function(idx) {
			var el = $(this);
			el.on('click', function(e) {
				e.preventDefault();

				// update labels and elements with selected and checked properties
				customs.removeClass('selected');
				labels.removeClass('selected');
				els.prop('checked', false);

				$.each(customs, function(idx) {
					self.destroy_associated_input($(this));
				});
				self.activate_custom_amount(el);

				el.addClass('selected');

			});
		});
	}

	self.activate_submit_click = function() {
		self.elems.submit.on('click', function(e) {
			e.preventDefault();

			self.show_loading();
			self.disable_submit();

			setTimeout(function() { // XXX: remove this. It's just for testing.

			if (self.validates()) { // submit form
				console.log("Validation succeeded.");
				//self.elems.form.submit();
			} else { // show errors

				// TODO: Make sure to set correct errors here and updated all previous
				// errors. Or maybe this just happens in validate()?

				self.enable_submit();
				self.hide_loading();
				console.log('Validation failed.');
			}
			}, 1000);
		});
	}

	self.disable_submit = function() {
		// TODO: disable click event.
		self.elems.submit
			.addClass('disabled')
			.parent()
			.find('img')
			.prop('src', '/wp-content/plugins/sdf/img/button-gray-tip-transparent.png')
			.click(false);
	}

	self.enable_submit = function() {
		// TODO: re-add onclick function.
		self.elems.submit
			.removeClass('disabled')
			.parent()
			.find('img')
			.prop('src', '/img/button-dark-tip.png');
	}

	self.create_spinner_loading_element = function() {
		self.elems.loading = document.createElement('div');
		self.elems.loading.setAttribute('id', 'loading');
		self.elems.loading = $(self.elems.loading);
		self.elems.loading.hide();
		$('body').append(self.elems.loading);
	}

	self.activate_spinner = function() {
		self.create_spinner_loading_element();
		self.spinner.obj = new Spinner(self.spinner.opts);
	}

	self.hide_loading = function() {
		self.spinner.obj.stop();
		self.elems.loading.hide();
	}

	self.show_loading = function() {

		// TODO: This actually isn't working for some reason. Need to figure out
		// why. Is it a style issue? Configuration?

		self.spinner.obj.spin();
		self.elems.loading.show();
	}

	self.validates = function() {
		return self.validate(self.elems.form);
	}

	self.validate = function(form_el) {

		var is_valid = true,
			items_to_validate = form_el.find('[required]');

		$.each(items_to_validate, function(idx) {
			var el = $(this),
				regex_data =  el.attr('data-regex-name');
			if (typeof regex_data !== 'undefined') { // check regex

				if (el.val().match(self.opts.regex[regex_data])) {
					el.removeClass('invalid');
					//$('#invalid-' + $(el).attr('id')).hide();
				} else {
					el.addClass('invalid');
					//$('#invalid-' + $(el).attr('id')).show();
					is_valid = false;
				}

			} else { // just check if blank

				if (el.val() !== '') {
					el.removeClass('invalid');
					//$('#invalid-' + $(el).attr('id')).hide();
				} else {
					el.addClass('invalid');
					//$('#invalid-' + $(el).attr('id')).show();
					is_valid = false;
				}

			}
		});

		return is_valid;

	}

	self.init = function(args) {
		$.extend(true, self.opts, args); // order matters

		self.elems.submit = $('#' + self.opts.ids.submit);
		self.elems.form = $('#' + self.opts.ids.form);

		self.activate_spinner();

		try {
			self.attach_validation_to_form();
		} catch (e) {
			self.show_error(e);
		}

	}

	return {
		init: init
	}

})(jQuery);

jQuery(document).ready(function() {
	sdf_new.init({
		ids: {
			form: 'sdf_form',
			custom: '',
		},
	});
});

//(function($) {
	//'use strict';

	//// http://stackoverflow.com/questions/1184624/convert-form-data-to-js-object-with-jquery/1186309#1186309
	//$.fn.serializeObject = function() {
		//var o = {};
		//var a = this.serializeArray();
		//$.each(a, function() {
			//if (o[this.name] !== undefined) {
				//if (!o[this.name].push) {
					//o[this.name] = [o[this.name]];
				//}
				//o[this.name].push(this.value || '');
			//} else {
				//o[this.name] = this.value || '';
			//}
		//});
		//return o;
	//};

	//$.extend($.expr[':'], {
		//invalid : function(elem, index, match){
			//var invalids = document.querySelectorAll(':invalid'),
				//result = false,
				//len = invalids.length;

			//if (len) {
				//for (var i=0; i<len; i++) {
					//if (elem === invalids[i]) {
						//result = true;
						//break;
					//}
				//}
			//}
			//return result;
		//}
	//});

	//var opts = {
		//lines: 9, // The number of lines to draw
		//length: 13, // The length of each line
		//width: 7, // The line thickness
		//radius: 26, // The radius of the inner circle
		//corners: 0.8, // Corner roundness (0..1)
		//rotate: 30, // The rotation offset
		//direction: 1, // 1: clockwise, -1: counterclockwise
		//color: '#FFF', // #rgb or #rrggbb or array of colors
		//speed: 0.7, // Rounds per second
		//trail: 76, // Afterglow percentage
		//shadow: false, // Whether to render a shadow
		//hwaccel: true, // Whether to use hardware acceleration
		//className: 'spinner', // The CSS class to assign to the spinner
		//zIndex: 2e9, // The z-index (defaults to 2000000000)
		//top: 'auto', // Top position relative to parent in px
		//left: 'auto' // Left position relative to parent in px
	//},
	//spinner = new Spinner(opts);

	//// preload the image
	//sdf.preload = function() {
		//var img = document.createElement('image');
		//img.src = '/wp-content/plugins/sdf/img/button-gray-tip-transparent.png';
	//}

	//sdf.stateBlur = function() {
		//$('#state').val($('#state').val().toUpperCase());
	//}

	//sdf.copyPersonalInfo = function() {
		//// copy fields and gray out
		//if($('.js-copy-personal-info').prop('checked')) {

			//$('#js-cc-fields input').each(function(k, v) {
				//$(v).val($('#' + $(v).attr('name').substr(3)).val()).removeClass('field-error');
			//});

			//$('#js-cc-fields .h5-error-msg').each(function(k, v) {
				//$(v).hide();
				//// its imperfect, but it will smooth the problem of big error messages in the way
				//// can be improved by re-validating the transferred fields
				//// and leaving the ones that are still bad not read only
			//});

			//$('#cc-name').val($('#first-name').val() + ' ' + $('#last-name').val());

			//$('#js-cc-fields').fadeTo('fast', 0.5, function() {
				//$('#js-cc-fields input').each(function(k, v) {
					//$(v).prop('readonly', true);
				//});
			//});

		//} else {

			//$('#js-cc-fields').fadeTo('fast', 1, function() {
				//$('#js-cc-fields input').each(function(k, v) {
					//$(v).prop('readonly', false);
				//});
			//});

		//}
	//}

	//sdf.cc_fields_gray = function() {
		//// when we go to another page and come back, make sure that if the
		//// box is checked, the fields are gray, and read-only-ize them
		//if($('.js-copy-personal-info').prop('checked')) {
			//$('#js-cc-fields').addClass('faded');

			//$('#js-cc-fields input').each(function(k, v) {
				//$(v).prop('readonly', true);
			//});
		//}
	//}

	//sdf.futureDate = function() {
		//// Validate CC date.
		//var inputyear = parseInt($('#cc-exp-year').val()),
			//inputmo = parseInt($('#cc-exp-mo').val());

		//if(inputyear < 30) {
			//inputyear += 2000;
		//}

		//var date = new Date(inputyear, inputmo),
			//now = new Date();

		//now.setHours(0, 0, 0, 0);

		//if(now.getTime() - date.getTime() > 0) {
			//// This could be improved by comparing the years,
			//// and if the input year is the same as this year,
			//// then only highlighting the month as incorrect.
			//// Using custom error to not interfere with h5Validate.
			//$('#cc-exp-mo, #cc-exp-year').addClass('field-custom-error');
		//} else {
			//$('#cc-exp-mo, #cc-exp-year').removeClass('field-custom-error');
		//}
	//}

	//sdf.hearAboutChange = function() {
		//var select = $('#hearabout')[0];
		//if($(select.options[select.selectedIndex]).hasClass('js-select-extra')) {
			//$('#js-select-extra-name').html(select.options[select.selectedIndex].value + ':');
			//$('#js-select-extra-input').show();
		//} else {
			//$('#hearabout-extra').val('');
			//$('#js-select-extra-input').hide();
		//}
	//}

	//sdf.doSubmit = function() {
		//var form = $('#sdf_form form')[0];

		//if(form.checkValidity()) {

			//$('body').append('<div id="loading"></div>');
			//spinner.spin(document.getElementById('loading'));

			//var cardData = {
				//number: $('#cc-number').val(),
				//cvc: $('#cc-cvc').val(),
				//exp_month: $('#cc-exp-mo').val(),
				//exp_year: $('#cc-exp-year').val(),
				//name: $('#cc-name').val(),
				//address_zip: $('#cc-zip').val(),
			//};

			//Stripe.card.createToken(cardData, sdf.stripeResponseHandler);

			//$('a#js-form-submit').addClass('disabled')
				//.parent().find('img').prop('src', '/wp-content/plugins/sdf/img/button-gray-tip-transparent.png');

		//} else {
			//// iterate through the inputs to mark those that aren't valid.
			//$('#sdf_form input:invalid').each(function(k, v) {
				//$(v).addClass('field-error');
			//});
		//}
	//}

	//sdf.clear_notification = function(timeout) {
		//if(timeout === 0) {
			//$('.alert p').first().remove();
		//} else {
			//timeout = timeout || 5000;
			//setTimeout(function() {
				//$('.alert p').first().fadeTo('fast', 0, function() {
					//$(this).animate({height: 0}, 'fast').remove();
				//});
			//}, timeout);
		//}

	//}

	//sdf.clear_loading = function() {
		//spinner.stop();
		//$('#loading').remove();
	//}

	//sdf.redirect = function() {
		//window.location.href = window.location.protocol + '//'
			//+ window.location.hostname + '/donation-confirmation/';
	//}

	//sdf.stripeResponseHandler = function(status, response) {
		//var data = {};
		//response = (typeof response !== 'undefined') ? response : {};
		//if(response.error) {
			//sdf.clear_notification(0);
			//$('.alert').append('<p class="error">' + response.error.message + '</p>');
			//document.getElementsByClassName('alert')[0].scrollIntoView();
			//sdf.clear_loading();
			//// re enable submissions
			//sdf.re_enable();
		//} else {

			//$('#stripe-token').val(response.id);
			//data = $('#sdf_form form').serializeObject();

			//// remove the card data before sending to our server.
			//$.each(data, function(k, v) {
				//if(k.substring(0, 3) === 'cc-') {
					//delete data[k];
				//}
			//});

			//$.post(ajaxurl, {
				//action: 'sdf_parse',
				//data: data,
			//}, function(data) {
				//sdf.clear_loading();
				//// clear existing notifications.
				//sdf.clear_notification(0);

				//data = JSON.parse(data);
				//$('.alert').append('<p class="'	+ data.type + '">' + data.message + '</p>').show();
				//document.getElementsByClassName('alert')[0].scrollIntoView();

				//if(data.type == 'error') {
					//// could we figure out what element to highlight in error? that would be good.
					//// don't clear error for now.
					//// re enable submit
					//sdf.re_enable();
				//} else {
					//setTimeout(sdf.redirect, 2500);
				//}
			//});
		//}
	//}

	//sdf.re_enable = function() {
		//$('a#js-form-submit')
			//.removeClass('disabled')
			//.parent()
			//.find('img')
			//.prop('src', '/img/button-dark-tip.png');
	//}

	//sdf.custom_amount_create = function(event) {
		//// replace the label contents with the input value.
		//// set focus in the input element

		//// okay since the radio change function is called AFTER this one
		//// when a custom is to be created
		//// the problem is that radio change in turn calls the destroy function
		//// which insta kills the custom.
		//// fix is to make sure that other customs are gone before radio change starts

		//event = event || {};
		//var ele = event.target;

		//if($(ele).is('#js-custom-input, #amount-text')
			//|| $.contains(ele, document.getElementById('js-custom-input'))) {
			//// means you have clicked on an empty input!
			//return true;
		//} else {
			//sdf.custom_amount_remove();
		//}

		//var	input = document.createElement('input');
		//input.name = $(ele).attr('for');
		//input.placeholder = 'Custom amount';
		//input.pattern = '^[$]?\\d+([.]\\d{2})?$';
		//input.required = true;
		//input.type = 'text';
		//input.id = 'js-custom-input';
		//input.dataset.h5Errorid = 'invalid-' + input.name;


		//// can't chain these because otherwise focus gets called too early
		//$(ele).html(input).addClass('custom-label-input');
		//// dunno why i gotta wait
		//setTimeout(function() {
			//$(input).focus();
		//}, 0);

		//// $(input).parent('label').prev('input.amount').prop('checked', 'checked');

		//// so, when this is called, the next click on the other custom-able label
		//// isn't called, radio change goes first and says, okay destroy other, set this to active
		//// but doesn't give a shot to create new.
		////$('#sdf_form').off('click', '.custom-label', sdf.custom_amount_create);

	//}

	//sdf.custom_amount_remove = function() {
		//// should they change selection
		//// replace the input in the label with the placeholder text of that input

		//// this is not a guaranteed test for custom. the input
		//var input = $('#js-custom-input');
		//if(input.length) {
			//var label_text = input.prop('placeholder');
			//input.parent('label').empty()
				//.removeClass('custom-label-active custom-label-input').text(label_text);

			//// hide error message
			//$('#invalid-' + input.attr('name')).hide();
		//}

		//// should re hook the create here.
		////$('.custom-label').click(sdf.custom_amount_create);
		//// don't bind multiple times!
		//$('#sdf_form').off('click', '.custom-label')
			//.on('click', '.custom-label', sdf.custom_amount_create);
	//}

	//sdf.custom_amount_blur = function() {
		//// when the input loses focus
		//// make the label just have the content of the amount
		//// from the input and a dollar sign in front, to match the other label styles

		//var input = $('#js-custom-input'),
			//amount_text = input.val(),
			//label = input.parent('label');

		//if(!amount_text.length) {

			//// look to see if the checked radio button doesn't correspond to this element.
			//// if it does, then remove and radio change
			//// also, if I clicked on something else, we have to switch.
			//// label.prev('input.amount').is(':checked') &&
			//// it has to be this element / and it can't be any other label
			//var hasMyClass = $(sdf.clicked).hasClass('selected'),
				//isNotALabel = !($(sdf.clicked).is('label.button-look'));
			//if(hasMyClass || isNotALabel) {
				//// this means we have blurred but not to another amount
				//// we DONT want to remove.
				//return false;
			//} else {
				//// we can destroy the element
				//// sdf.custom_amount_remove();
				//// reset the selected class, so that one of them is clicked
				//// sdf.radio_change();
			//}
		//}

		//// if the input state is invalid, return false
		//if(input.is(':invalid')) {
			//// a perfect moment for custom validity
			//// $('#js-custom-input').focus();
			//return false;
		//}

		//// we test again here, just to be able to toggle this block.
		//if(amount_text.length) {
			//// should also make sure amount text doesn't have a dollar sign in it
			//if(amount_text.substr(0,1) !== '$') {
				//amount_text = '$' + amount_text;
			//}

			//// MONEY FORMAT!!!!!!!
			//if(amount_text.indexOf('.') != -1) {
				//// found a period. make sure that there are two decimal points.
				//var parts = amount_text.split('.');
				//if(parts[1].length == 0) {
					//parts[1] += '00';
				//} else if(parts[1].length == 1) {
					//parts[1] += '0';
				//}
				//amount_text = parts[0] + '.' + parts[1];
			//}

			//input.prop('type', 'hidden');
			//// don't append so many!
			//if($('#amount-text').length) {
				//$('#amount-text').text(amount_text);
			//} else {
				//label.append('<span id="amount-text">' + amount_text + '</span>');
			//}
			////label.off('click', '.custom-label', sdf.custom_amount_create).addClass('custom-label-active');
			//label.removeClass('custom-label-input').addClass('custom-label-active');
		//}

	//}

	//sdf.custom_amount_focus = function() {
		//// make it into a input again to edit the number

		//$('#amount-text').remove();
		//// so apparently there can be two somehow...
		//if($('#amount-text').length) {
			//$('#amount-text').remove();
		//}

		//$('#js-custom-input').parent('label').addClass('custom-label-input');

		//$('#js-custom-input').prop('type', 'text');
		//setTimeout(function() {
			//$('#js-custom-input').focus();
		//}, 0);
	//}

	//sdf.radio_change = function(event) {
		//// need to check here so that we don't clear a just created input.
		//var clearem = false,
			//event = event || {};

		//if(!('target' in event)) {
			//event.target = sdf.clicked;
		//}

		//// problem:
		//// going from custom to custom, the old input is destoryed, and a new one is created,
		//// then this is called. soo, in that case, we want to know if event.target is also a custom!

		//// looks like the target is actually the input ELEMENT itself...
		//var label = $(event.target).next('label'),
			//previousIsCustom = $('.selected').hasClass('custom-label');
		//if(previousIsCustom) {
			//var targetIsntCustom = !(label.hasClass('custom-label'));
			//// targetWasntOldSelected = (event.target !== document.getElementsByClassName('selected')[0]);
			//if(targetIsntCustom) {
				//clearem = true;
			//}
		//}

		//if(clearem) {
			//sdf.custom_amount_remove();
		//}


		//// insurance against unbound events
		//// should have been called!
		//if($(event.target).hasClass('custom-label')) {
			//sdf.custom_amount_create(event);
		//}

		//$('label.button-look').removeClass('selected');

		//$('.amount:checked').next('label').addClass('selected');
	//}

	//sdf.custom_keys = function(event) {
		//var key = event.which || event.keyCode;

		//if(key == 13) {
			//sdf.custom_amount_blur();
		//} /*else if (key == 9) {
			//return false;
		//}*/
	//}

	//// in order to be able to see what blurred me
	//$(document).mousedown(function(e) {
		//sdf.clicked = e.target;
	//});

	//// One function to bind them.

	//$(document).ready(function() {

		//sdf.cc_fields_gray();

		//sdf.preload();

		//$('#sdf_form').on('blur', '#js-custom-input', sdf.custom_amount_blur)
			//.on('click', '.custom-label-active', sdf.custom_amount_focus)
			//.on('click', '.custom-label', sdf.custom_amount_create)
			//.on('keydown', '#js-custom-input', sdf.custom_keys);

		//$('.amount').change(sdf.radio_change);

		//$('#hearabout').change(sdf.hearAboutChange);

		//$('#state').blur(sdf.stateBlur);

		//$('#copy-personal-info').click(sdf.copyPersonalInfo);

		//$('a#js-form-submit.disabled').on('click', function() {
			//return false;
		//});

		//// Names shouldn't contain dashes (-) because I think h5 explodes class on -
		//$.h5Validate.addPatterns({
			//birthmonth: /^(0?[1-9]|1[012])/,
			//birthyear: /^(19|20)\d{2}$/,
			//creditcard: /\d{14,16}/,
			//cc_expiry_mo: /^(0?[1-9]|1[012])$/,
			//cc_expiry_year: /^(1[0-9])|20[\d]{2}/,
			//cc_zipcode: /^\d{5}(-\d{4})?$/,
			//cvc: /[\d]{3,4}/,
			//phone: /^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/,
			//state: /[a-zA-Z]{2}/,
			//zipcode: /^\d{5}(-\d{4})?$/,
		//});

		//$('#sdf_form form').h5Validate({
			//errorClass: 'field-error',
			//focusout: false // don't validate on blur
		//});

		//$('#cc-exp-year').on('focusout', sdf.futureDate);

		//$('#js-form-submit').click(sdf.doSubmit);

	//});
//}(jQuery));


