/*
File: donate_form.js
Author: Steve Avery
Date: September
Provides client ajax functions for the donation form.
*/

var sdf = {};

var sdf_new = sdf_new || {};

sdf_new.validation = (function($) {

	var self = this;

	// jquery way to check if dom element exists
	$.fn.exists = function() { return this.length > 0 }

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
		error_container: {},
		saved: [], // save array of elements to revive
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
			new_input.placeholder = 'Custom amount';
			new_input.required = true;
			new_input.type = 'text';
			new_input.id = input_id;
			new_input.setAttribute('data-regex-name', 'custom_amount');
			new_input = $(new_input);

			// TODO: This code is not DRY (see setup_form_change_validate above).
			// Maybe do something about this?
			new_input.change(function (e) {
				self.validate(self.elems.form);
			});

			el.after(new_input);
			new_input.focus();
		}
	}

	self.setup_copy_info = function() {
		// TODO: this is really hacky and could use some refinement.
		var input = $('#copy-personal-info');
		input.change(function(e) {
			var first = self.elems.form.find('#first-name').val(),
				last = self.elems.form.find('#last-name').val(),
				zip = self.elems.form.find('#zip').val();
			if ($(this).prop('checked') === true) {
				$('#cc-name').val(first + ' ' + last);
				$('#cc-zip').val(zip);
			} else {
				$('#cc-name').val('');
				$('#cc-zip').val('');
			}
		});
	}

	self.destroy_associated_input = function(el) {
		// TODO: I probably should remove the element. DOM stuff is expensive.
		// Maybe just convert this stuff to shows and hides? I am hesitant to do
		// shows and hides because, again, I don't know what the backend is
		// expecting. A hidden form element will still get submitted, a destroyed
		// one will not.
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

		if (!self.elems.error_container.exists()) {
			throw { message: 'ErrorContainerNonExistent', from: 'attach_validation_to_form' }
		}

		self.setup_spinner();
		self.setup_amount_clicks();
		self.setup_submit_event();
		self.setup_goto_error();
		self.setup_copy_info();
		self.setup_close_errors_button();

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

				e.preventDefault();

				customs.removeClass('selected');
				labels.removeClass('selected');
				els.prop('checked', false);

				// TODO: Really we should just have one input for the amount that gets
				// populated by all of the label onclicks. This also works for the
				// "custom" amount field. We could just use 2 inputs, one for annual
				// and one for monthly, each targeted by their respected set of values.
				// This way, when the user clicks "custom amount" it will be prefilled
				// with the amount already selected, and instead of the destroying the
				// element when we click a preset value, we just hide the input form
				// element and populate it with the new data. But, it works as is and I
				// don't want to screw with the input arrangements because that would
				// entail touching the backend stuff to deal with newly arranged
				// inputs.

				customs.each(function(idx) {
					self.destroy_associated_input($(this));
				});

				// I don't think we need this because a label with the 'for' attribute
				// will automatically mark its associated input as checked.
				// On second thought, what if this function kills that action? Maybe I
				// should keep this enabled.
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
				//console.log("Validation succeeded.");
				self.elems.form.submit();
			} else { // show errors
				//console.log('Validation failed.');
				self.show_errors();
				self.enable_submit();
				self.hide_loading();
			}
		});
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

				if (el.val().trim() !== '') {
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
		self.elems.form = $('#' + self.opts.ids.form);
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
	sdf_new.validation.init({
		ids: {
			form: 'sdf_form',
			custom: '',
		},
	});
});
