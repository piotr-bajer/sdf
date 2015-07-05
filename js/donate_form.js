/*
File: donate_form.js
Author: Steve Avery, Alex Luecke
Date: September
Provides client ajax functions for the donation form.
*/

var sdf = sdf || {};

sdf.validation = (function($) {

	var self = this;

	// jquery way to check if dom element exists
	$.fn.exists = function() { return this.length > 0 }

	// convert form data to javascript object
	$.fn.serializeObject = function() {
		var o = {};
		var a = this.serializeArray();
		$.each(a, function() {
			if (o[this.name] !== undefined) {
				if (!o[this.name].push) {
					o[this.name] = [o[this.name]];
				}
				o[this.name].push(this.value || '');
			} else {
				o[this.name] = this.value || '';
			}
		});
		return o;
	}

	self.opts = {
		class_prefix: 'sdf',
		ids: {
			form: 'sdf_form',
			error_container: 'sdf_error_container',
			submit: 'js-form-submit',
		},
		regex: {
			birth_month: /^(0?[1-9]|1[012])/,
			birth_year: /^(19|20)\d{2}$/,
			cc_expiry_month: /^(0?[1-9]|1[0-2])$/,
			cc_expiry_year: /^\d{2}/,
			credit_card: /\d{14,16}/,
			custom_amount: /^[$]?\d+([.]\d{2})?$/,
			cvc: /[\d]{3,4}/,
			phone: /^[+]?([0-9]*[\.\s\-\(\)]|[0-9]+){3,24}$/,
			state: /[a-zA-Z]{2}/,
		},
	};

	self.elems = {
		form: {},
		submit: {},
		loading: {},
		error_container: {},
	};

	self.spinner = {
		obj: {},
		opts: {
			lines: 9, // The number of lines to draw
			length: 13, // The length of each line
			width: 8, // The line thickness
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

	self.log_error = function(e) {
		console.log("Error: " + e.message + ". From: " + e.from);
	}

	self.attach_validation_to_form = function() {

		if (!self.elems.form.exists()) {
			throw { message: 'FormNonExistent', from: 'attach_validation_to_form' }
		}

		if (!self.elems.submit.exists()) {
			throw { message: 'SubmitNonExistent', from: 'attach_validation_to_form' }
		}

		if (!self.elems.error_container.exists()) {
			throw { message: 'ErrorContainerNonExistent', from: 'attach_validation_to_form' }
		}

		self.setup_spinner();
		self.setup_amount_clicks();
		self.setup_submit_event();
		self.setup_goto_error();
		self.setup_close_errors_button();
		$('#hearabout').change(self.hearabout_change);

	}

	self.setup_close_errors_button = function() {
		var el = document.createElement('div')
		el.setAttribute('class', 'close-button');
		el = $(el);
		el.text('Hide');
		self.elems.error_container.append(el);
		el.on('click', function(e) {
			self.elems.error_container.hide();
		});
	}

	self.setup_form_change_validate = function() {
		self.elems.form.find('input').change(function (e) {
			self.validate(self.elems.form);
		});
	}

	self.setup_amount_clicks = function() {

		var els = $('#' + self.opts.ids.form + ' .amount');
		var labels = $('#' + self.opts.ids.form + ' .amount-label');
		var customs = $('#' + self.opts.ids.form + ' .custom-amount-label');

		// onclick for regular buttons
		$.each(labels, function(idx) {
			var el = $(this);
			el.on('click', function(e) {

				// e.preventDefault();

				customs.removeClass('selected');
				labels.removeClass('selected');

				// Hide all custom form inputs when any non-custom field is clicked.
				self.hide_amount_inputs();

				// Get the inner text of the link targeting the custom input and set
				// the value of the input to the inner text value of the link.
				$('#' + el.attr("data-target-id"), self.elems.form)
					.attr('value', self.to_int(el.text()))
					.attr('required', '');

				$('#amount-to-use', self.elems.form)
					.attr('value', el.attr('data-target-id'));

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

				self.hide_amount_inputs();

				$('#' + el.attr('data-target-id'), self.elems.form )
					.css('display', 'inline-block')
					.attr('required', '')
					.focus();

				$('#amount-to-use', self.elems.form)
					.attr('value', el.attr('data-target-id'));

				el.addClass('selected');

			});
		});
	}

	self.hide_amount_inputs = function() {
		$('.amount-input', self.elems.form).each(function(idx) {
			var el = $(this);
			el.removeAttr('required')
				.removeClass('invalid')
				.hide();
			$('#invalid-' + el.attr('name')).hide();
		});
	}

	self.to_int = function(str) {
		return parseInt(str.replace(/^[^0-9]/g, ''));
	}

	self.setup_goto_error = function() {
		var error_items = self.elems.error_container.find('.sdf-error-msg');
		$.each(error_items, function(idx) {
			var el = $(this);
			el.on('click', function(e) {
				e.preventDefault();
				var goto_el_id = el.attr('id').replace('invalid-', '');
				self.goto_element($('#' + goto_el_id));
			});
		});
	}

	self.goto_element = function(el) {
		if (!el.exists()) {
			throw { message: "GotoElementNonExistent", from: "goto_element" }
		}
		$(window).scrollTop($(el).focus().position().top);
	}

	self.setup_submit_event = function() {
		var on_change_initialized = false;
		self.elems.submit.on('click', function(e) {
			e.preventDefault();

			// So we don't setup on changes everytime we submit the form. Maybe this
			// isn't so bad but I am going to leave this for now. Techincally
			// speaking, we introduce a branch, right? Hopefully we are doing predict
			// not taken branch prediction. I am just kidding, I am not actually
			// trying to make this extremely performant, I just wanted to write a nice
			// little comment here that sent my brain on a thought voyage.
			if (!on_change_initialized) self.setup_form_change_validate();

			self.show_loading();
			self.disable_submit();

			if (self.validates()) { // submit form
				var cardData = {
					name:        $('#cc-name').val(),
					number:      $('#cc-number').val(),
					cvc:         $('#cc-cvc').val(),
					exp_month:   $('#cc-exp-mo').val(),
					exp_year:    $('#cc-exp-year').val(),
					address_zip: $('#cc-zip').val()
				}

				Stripe.card.createToken(cardData, self.stripe_response_handler);

				// self.elems.form.submit();
			} else { // show errors
				//console.log('Validation failed.');
				self.show_errors();
				self.enable_submit();
				self.hide_loading();
			}
		});
	}

	self.alert_handler = function(type, msg) {
		var alert_el = document.getElementsByClassName('alert')[0];

		while(alert_el.firstChild) {
			alert_el.removeChild(alert_el.firstChild);
		}

		$('.alert').append(
			'<p class="' + type + '">'
				+ msg
			+ '</p>');

		alert_el.scrollIntoView();
	} 

	self.stripe_response_handler = function(status, response) {
		var data = {};
		response = (typeof response !== 'undefined') ? response : {};

		if(response.error) {

			self.alert_handler('error', response.error.message);
			self.enable_submit();
			self.hide_loading();

		} else {
			$('#stripe-token').val(response.id);
			data = $('#sdf_form form').serializeObject();

			// remove the card data before sending to our server.
			$.each(data, function(k, v) {
				if(k.substring(0, 3) === 'cc-') {
					delete data[k];
				}
			});

			$.post(ajaxurl, {
				action: 'sdf_parse',
				data: data,
			}, function(data) {
				self.hide_loading();

				data = JSON.parse(data);

				self.alert_handler(data.type, data.message);

				if(data.type == 'error') {
					// don't clear error for now.
					self.enable_submit();

				} else {
					// send to confirmation page
					setTimeout(function() {
						window.location.href = window.location.protocol + '//'
							+ window.location.hostname + '/donation-confirmation/';
					}, 2500);
				}
			});
		}
	}


	self.hearabout_change = function() {
		var select = $('#hearabout')[0];
		if($(select.options[select.selectedIndex]).hasClass('js-select-extra')) {
			$('#js-select-extra-name').html(
				select.options[select.selectedIndex].value + ':');
			$('#js-select-extra-input').show();
		} else {
			$('#hearabout-extra').val('');
			$('#js-select-extra-input').hide();
		}
	}


	self.show_errors = function() {
		var invalid_items = self.elems.form.find('.invalid');
		if (invalid_items.exists()) {
			self.elems.error_container.show();
			$.each(invalid_items, function(idx) {
				$('#invalid-' + $(this).attr('name')).show();
			});
		} else {
			self.elems.error_container.hide();
		}
	}

	self.has_errors = function() {
		return self.elems.form.find('.invalid').exists();
	}

	self.disable_submit = function() {
		self.elems.submit
			.addClass('disabled')
			.parent()
			.find('img')
			.prop('src', '/wp-content/plugins/sdf/img/button-gray-tip-transparent.png')
			.click(false);
	}

	self.enable_submit = function() {
		self.elems.submit
			.removeClass('disabled')
			.parent()
			.find('img')
			.prop('src', '/img/button-dark-tip.png');
	}

	self.setup_spinner = function() {
		self.create_spinner_loading_element();
		self.spinner.obj = new Spinner(self.spinner.opts).spin();
		self.elems.loading.append(self.spinner.obj.el);
	}

	self.create_spinner_loading_element = function() {
		self.elems.loading = document.createElement('div');
		self.elems.loading.setAttribute('id', 'sdf_loading');
		self.elems.loading = $(self.elems.loading);
		self.elems.loading.hide();
		$('body').append(self.elems.loading);
	}

	self.hide_loading = function() {
		self.elems.loading.hide();
	}

	self.show_loading = function() {
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
					$('#invalid-' + $(el).attr('id')).hide();
				} else {
					el.addClass('invalid');
					$('#invalid-' + $(el).attr('id')).show();
					is_valid = false;
				}
			} else { // just check if blank
				// IE doesn't support "".trim(). Fantastic.
				if ($.trim(el.val()) !== '') {
					el.removeClass('invalid');
					$('#invalid-' + $(el).attr('id')).hide();
				} else {
					el.addClass('invalid');
					$('#invalid-' + $(el).attr('id')).show();
					is_valid = false;
				}
			}
		});

		if (!self.has_errors()) {
			self.elems.error_container.hide();
		}

		return is_valid;

	}

	self.init = function(args) {
		$.extend(true, self.opts, args); // order matters
		self.elems.submit = $('#' + self.opts.ids.submit);
		self.elems.form = $('#' + self.opts.ids.form + ' form');
		self.elems.error_container = $('#' + self.opts.ids.error_container);

		try {
			self.attach_validation_to_form();
		} catch (e) {
			self.log_error(e);
		}
	}

	return { init:init }

})(jQuery);

jQuery(document).ready(function() {
	sdf.validation.init({
		ids: {
			form: 'sdf_form',
			custom: '',
		},
	});
});
