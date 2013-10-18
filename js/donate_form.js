/*
File: donate_form.js
Author: Steve Avery
Date: September 
Provides client ajax functions for the donation form.
*/

//XXX one bug with the custom amount boxes.
// if you enter a number in one
// and then click the other
// you will lose the value in the first input
// but the radio button wont switch
// tabbing through works correctly.


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

	var callbacks = {
		single_success: function() {
			clear_loading();
			$('.alert').append('<p class="success">Thank you for your support! Expect an email with the details.</p>')
				.show();
			clear_notification();
		},
		subscribe_success: function() {
			clear_loading();
			$('.alert').append('<p class="success">Thank you for your support! You have signed up for a recurring donation. Expect an email with the details.</p>')
				.show();
			clear_notification();
		}
	},
	clear_loading = function() {
		$('#sdf_form').remove();
		$('body').animate({scrollTop: 0}, 300);
	},
	clear_notification = function(timeout) {
		timeout = timeout || 10000;
		setTimeout(function() {
			$('.alert').first('p').fadeTo('fast', 0, function() {
				$(this).animate({height: 0}, 'fast').remove();
			});
		}, timeout);
	},
	stripeResponseHandler = function(status, response) {
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
				data: data, // XXX money format remove on the custom amounts fields
			}, function(data) {
				callbacks[data]();
			});
		}
	},
	formValidity = function() {
		// XXX don't allow below a certain value in the custom amounts.
	};

	$(document).ready(function() {
		$('input').blur(function(event) {
			event.target.checkValidity(); // XXX
		});

		$('.js-custom-amount').focus(function() {
			// Also, when a custom amount has been entered, clear the custom amounts.
			// if none of them have values...
			// need to differentiate between clicks and tabbed focus.
			// event.which 9 is tab 1 is click
			var clicked = this,
				all_empty = true;
			$('.js-custom-amount').each(function(k, v) {
				if($(v).val().length) {
					all_empty = false;
				}
			});
			if(all_empty) { // XXX needs to work in firefox too, the behavior is okay but isn't exactly the same. focusing on an empty box when there is a full box will clear the other text box but won't move the radio selection.
				$(clicked).prev('.js-custom-amount-click').prop('checked', 'checked');
			}
		}).keydown(function(event) {
			// if it has a value, clear all others
			// if this key isn't a control key
			var keyCode = event.keyCode || event.which;
			if(keyCode != (9 || 13)) { // tab character
				if(!$(this).val().length) {
					$(this).prevAll('.js-custom-amount-click').prop('checked', 'checked');
					var dont_clear = this;
					$('.js-custom-amount').each(function(k, v) {
						if(v != dont_clear) {
							$(v).val('');
						}
					});
				}
			}
		});

		$('.amount').click(function() {
			if(!$(this).hasClass('.js-custom-amount')) {
				$('.js-custom-amount').each(function(k, v) {
					$(v).val('');
				});
			}
		});

		$('.js-custom-amount-click').click(function() {
			$(this).nextAll('.js-custom-amount').focus();
		});

		$('#hearabout').change(function() {
			var select = $('#hearabout')[0];
			if($(select.options[select.selectedIndex]).hasClass('js-select-extra')) {
				$('#js-select-extra-name').html(select.options[select.selectedIndex].value + ':');
				$('#js-select-extra-input').show();
			} else {
				$('#hearabout-extra').val('');
				$('#js-select-extra-input').hide();
			}
		});

		$('.js-copy-personal-info').click(function() {
			// copy fields and  gray out
			if($('.js-copy-personal-info').attr('checked') == 'checked') {
				$('#js-cc-fields input').each(function(k, v) {
					$(v).val($('#' + $(v).attr('name').substr(3)).val());
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
			
		});

		$('#js-form-submit').click(function() {
			//$('#js-form-submit').prop('disabled', true); // XXX
			// console.log('checking form validity'); // XXX
			// do something to look like you're loading.
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
		});
	});
})(jQuery)

	// XXX
  // function hasPlaceholderSupport() {
  //   var input = document.createElement('input');
  //   return ('placeholder' in input);
  // }

  // if(!hasPlaceholderSupport()){
  //   var inputs = document.getElementsByTagName('input');
  //   for(var i=0,count=inputs.length;i<count;i++){
  //     if(inputs[i].getAttribute('placeholder')){
  //       inputs[i].style.cssText = "color:#939393;"
  //       inputs[i].value = inputs[i].getAttribute("placeholder");
  //       inputs[i].onclick = function(){
  //         if(this.value == this.getAttribute("placeholder")){
  //           this.value = '';
  //           this.style.cssText = "color:#000;font-style:normal;"
  //         }
  //       }
  //       inputs[i].onblur = function(){
  //         if(this.value == ''){
  //           this.value = this.getAttribute("placeholder");
  //           this.style.cssText = "color:#939393;"
  //         }
  //       }
  //     }
  //   }
  // }