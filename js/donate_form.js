/*
File: donate_form.js
Author: Steve Avery
Date: September 
Provides client ajax functions for the donation form.
*/

(function($) {
	'use strict';

	// http://stackoverflow.com/questions/1184624/convert-form-data-to-js-object-with-jquery/1186309#1186309
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
	};

	function hasPlaceholderSupport() {
		var input = document.createElement('input');
		return ('placeholder' in input);
	}

	function copyPersonalInfo() {
		// copy fields and gray out
		if($('.js-copy-personal-info').attr('checked') == 'checked') {

			$('#js-cc-fields input').each(function(k, v) {
				$(v).val($('#' + $(v).attr('name').substr(3)).val()).removeClass('field-error');
			});

			$('#cc-name').val($('#first-name').val() + ' ' + $('#last-name').val());
			$('#cc-country').val($('#country').val());

			$('#js-cc-fields').fadeTo('fast', 0.5, function() {
				$('#js-cc-fields input').each(function(k, v) {
					$(v).prop('readonly', true);
				});
				$('#cc-country').prop('disabled', true);
			});

		} else {

			$('#js-cc-fields').fadeTo('fast', 1, function() {
				$('#js-cc-fields input').each(function(k, v) {
					$(v).prop('readonly', false);
				});

				$('#cc-country').prop('disabled', false);
			});

		}
	}

	function futureDate() {
		// Validate CC date.
		var inputyear = parseInt($('#cc-exp-year').val()),
			inputmo = parseInt($('#cc-exp-mo').val());

		if(inputyear < 30) {
			inputyear += 2000;
		}

		var date = new Date(inputyear, inputmo),
			now = new Date();

		now.setHours(0, 0, 0, 0);

		if(now.getTime() - date.getTime() > 0) {
			// This could be improved by comparing the years,
			// and if the input year is the same as this year,
			// then only highlighting the month as incorrect.
			// Using custom error to not interfere with h5Validate.
			$('#cc-exp-mo, #cc-exp-year').addClass('field-custom-error');
		} else {
			$('#cc-exp-mo, #cc-exp-year').removeClass('field-custom-error');
		}
	}

	function hearAboutChange() {
		var select = $('#hearabout')[0];
		if($(select.options[select.selectedIndex]).hasClass('js-select-extra')) {
			$('#js-select-extra-name').html(select.options[select.selectedIndex].value + ':');
			$('#js-select-extra-input').show();
		} else {
			$('#hearabout-extra').val('');
			$('#js-select-extra-input').hide();
		}
	}

	function clearCustomAmounts() {
		if(!$(this).hasClass('js-custom-amount')) {
			$('.js-custom-amount').each(function(k, v) {
				$(v).val('');
			});
		}
	}

	function doSubmit() {
		var form = $('#sdf_form form')[0];

		$('body').append('<div id="loading"></div>');
		spinner.spin(document.getElementById('loading'));

		if(form.checkValidity()) {
			var cardData = {
				number: $('#cc-number').val(),
				cvc: $('#cc-cvc').val(),
				exp_month: $('#cc-exp-mo').val(),
				exp_year: $('#cc-exp-year').val(),
				name: $('#cc-name').val(),
				address_line1: $('#cc-address1').val(),
				address_line2: $('#cc-address2').val(),
				address_city: $('#cc-city').val(),
				address_state: $('#cc-state').val(),
				address_zip: $('#cc-zip').val(),
				address_country: $('#cc-country').val()
			};
			Stripe.card.createToken(cardData, stripeResponseHandler);
			$('a#js-form-submit').addClass('disabled').parent().find('img').prop('src', '/img/button-grey-tip.png');
		} else {
			spinner.stop();
			// iterate through the inputs to mark those that aren't valid.
			$('#sdf_form input:invalid').each(function(k, v) {
				$(v).addClass('field-error');
			});
		}
	}

	function clear_notification(timeout) {
		timeout = timeout || 10000;
		setTimeout(function() {
			$('.alert').first('p').fadeTo('fast', 0, function() {
				$(this).animate({height: 0}, 'fast').remove();
			});
			redirect();
		}, timeout);
	}

	function clear_loading() {
		spinner.stop();
		$('#loading').remove();
	}

	function redirect() {
		window.location.href = window.location.protocol + '//'
			+ window.location.hostname + '/donation-confirmation/';
	}

	function stripeResponseHandler(status, response) {
		var data = {};
		response = (typeof response !== 'undefined') ? response : {};
		if(response.error) {
			$('.alert').append('<p class="error">' + response.error.message + '</p>');
			clear_notification();
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
				clear_loading();
				data = JSON.parse(data);
				$('.alert').append('<p class="'	+ data.type + '">' + data.message + '</p>').show();
				document.getElementsByClassName('alert')[0].scrollIntoView();
				clear_notification();
			});
		}
	}

	function input_focus() {
		// This part of the code handles when all the inputs are empty.
		// It switches the radio button.

		var clicked = this,
			all_empty = true;

		$('.js-custom-amount').each(function(k, v) {
			if($(v).val().length) {
				all_empty = false;
			}
		});

		if(all_empty) {
			// this will select the radio if you focus on a custom input.
			$(clicked).prevAll('.js-custom-amount-click').prop('checked', 'checked');
		}
	}

	function input_keydown() {
		// This part of the code handles when one of the inputs has content.
		// It allows you to tab through the other input without clearing
		// the one that you just interacted with.
		// Can be improved by allowing shift-tabs.

		var keyCode = event.keyCode || event.which;

		if(keyCode != 9) {
			// if it's not a tab, then we change the selected radio and dump the inputs.
			if(!$(this).val().length) {

				$(this).prevAll('.js-custom-amount-click').prop('checked', 'checked');
				var dont_clear = this; // keep the just entered value.

				$('.js-custom-amount').each(function(k, v) {
					if(v != dont_clear) {
						$(v).val('');
					}
				});
			}
		}
	}

	function input_click() {
		// This part of the code allows you to click focus an input
		// assuming that clicks take precedence.

		if($(this).val().length) {
			// The click event is clearing the content.
			// I would like to just select it instead.
			$(this).select();
			return;
		} else {
			$('.js-custom-amount').each(function(k, v) {
				$(v).val('');
			});

			$(this).prevAll('.js-custom-amount-click').prop('checked', 'checked');
		}
	}

	function placeholder_pf() {
		var inputs = document.getElementsByTagName('input');
		for(var i = 0, count = inputs.length; i < count; i++) {
			if(inputs[i].getAttribute('placeholder')) {
				inputs[i].style.cssText = 'color:#939393;'
				inputs[i].value = inputs[i].getAttribute('placeholder');
				inputs[i].onclick = function(){
					if(this.value == this.getAttribute('placeholder')) {
						this.value = '';
						this.style.cssText = 'color:#000;font-style:normal;'
					}
				}
				inputs[i].onblur = function(){
					if(this.value == ''){
						this.value = this.getAttribute('placeholder');
						this.style.cssText = 'color:#939393;'
					}
				}
			}
		}
	}

	var opts = {
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
	spinner = new Spinner(opts);

	// One function to bind them.

	$(document).ready(function() {

		if(!hasPlaceholderSupport()) {

		}

		$('.js-custom-amount-click').click(function() {
			$(this).nextAll('.js-custom-amount').focus();
		});

		$('.js-custom-amount').focus(input_focus)
			.click(input_click)
			.keydown(input_keydown);

		$('.amount').click(clearCustomAmounts);

		$('#hearabout').change(hearAboutChange);

		$('.js-copy-personal-info').click(copyPersonalInfo);

		$('a#js-form-submit.disabled').on('click', function() {
			return false;
		});

		$('#sdf_form form').h5Validate({
			errorClass: 'field-error'
		});

		$('#cc-exp-year').on('focusout', futureDate);

		$('#js-form-submit').click(doSubmit);

	});
}(jQuery));
