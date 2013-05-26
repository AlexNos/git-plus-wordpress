jQuery(document).ready(function($){

	$.extend({
		getParameterByName: function(name) {
			name = name.replace(/[\[]/, '\\\[').replace(/[\]]/, '\\\]');
			var regexS = '[\\?&]' + name + '=([^&#]*)';
			var regex = new RegExp(regexS);
			var results = regex.exec(window.location.search);
			if(results == null) {
				return '';
			} else {
				return decodeURIComponent(results[1].replace(/\+/g, ' '));
			}
		},
		showHideSubscriptionMeta: function(){
			if ($('select#product-type').val()==WCSubscriptions.productType) {
				$('.show_if_simple').show();
				$('.show_if_subscription').show();
				$('.grouping_options').hide();
				$('.options_group.pricing ._regular_price_field').hide();
				$('#sale-price-period').show();
			} else {
				$('.show_if_subscription').hide();
				$('.options_group.pricing ._regular_price_field').show();
				$('#sale-price-period').hide();
			}
		},
		setSubscriptionLengths: function(){
			var selectedLength = $('#_subscription_length').val();
			$('#_subscription_length').empty();
			$.each(WCSubscriptions.subscriptionLengths[$('#_subscription_period').val()], function(length,description) {
				if(parseInt(length) == 0 || 0 == (parseInt(length) % parseInt($('#_subscription_period_interval').val())))
					$('#_subscription_length').append($('<option></option>').attr('value',length).text(description));
			});
			$('#_subscription_length').val(selectedLength);
		},
    	setTrialPeriods: function(){
			var trialLength = $('#_subscription_trial_length').val(),
				selectedTrialPeriod = $('#_subscription_trial_period').val(),
				selected = '',
				periodStrings;

			$('#_subscription_trial_period').empty();

			if( parseInt(trialLength) == 1 ) {
				periodStrings = WCSubscriptions.trialPeriodSingular;
			} else {
				periodStrings = WCSubscriptions.trialPeriodPlurals;
			}
			$.each(periodStrings, function(key,description) {
				$('#_subscription_trial_period').append($('<option></option>').attr('value',key).text(description));
			});
			$('#_subscription_trial_period').val(selectedTrialPeriod);
		},
		setSalePeriod: function(){
			$('#sale-price-period').fadeOut(80,function(){
				$('#sale-price-period').text($('#_subscription_period_interval option:selected').text()+' '+$('#_subscription_period option:selected').text());
				$('#sale-price-period').fadeIn(180);
			});
		}
	});

	$('.options_group.pricing ._sale_price_field .description').prepend('<span id="sale-price-period" style="display: none;"></span>');

	if($('.options_group.pricing').length > 0) {
		$.setSalePeriod();
		$.showHideSubscriptionMeta();
		$.setSubscriptionLengths();
		$.setTrialPeriods();
	}

	// Move the subscription pricing section to the same location as the normal pricing section
	$('.options_group.subscription_pricing').insertBefore($('.options_group.pricing'));
	$('.show_if_subscription.clear').insertAfter($('.options_group.subscription_pricing'));

	// Update subscription ranges when subscription period or interval is changed
	$('#_subscription_period, #_subscription_period_interval').change(function(){
		$.setSubscriptionLengths();
		$.setSalePeriod();
	});

	$('#_subscription_trial_length').bind('propertychange keyup input paste',function(){
		$.setTrialPeriods();
	});

	$('body').bind('woocommerce-product-type-change',function(){
		$.showHideSubscriptionMeta();
	});

	if($.getParameterByName('select_subscription')=='true'){
		$('select#product-type option[value="'+WCSubscriptions.productType+'"]').attr('selected', 'selected');
		$('select#product-type').trigger('woocommerce-product-type-change');
		$('select#product-type').select();
	}

	// Before saving a subscription product, validate the trial period
	$('#post').submit(function(){
		var trialLength = $('#_subscription_trial_length').val(),
			selectedTrialPeriod = $('#_subscription_trial_period').val();

		if ( parseInt(trialLength) >= WCSubscriptions.subscriptionLengths[selectedTrialPeriod].length ) {
			alert(WCSubscriptions.trialTooLongMessages[selectedTrialPeriod]);
			$('#ajax-loading').hide();
			$('#publish').removeClass('button-primary-disabled');
			return false;
		}

	});

	// On "Manage Subscriptions" page, handle editing a date
	$('.date-picker-div').siblings('a.edit-timestamp').click(function() {
		var $pickerDiv = $(this).siblings('.date-picker-div'),
			$editDiv = $(this).parents('.edit-date-div');

		if ($pickerDiv.is(":hidden")) {
			$editDiv.css({visibility:'visible'});
			$pickerDiv.slideDown('fast');
			$(this).hide();
		} else {
			$editDiv.removeAttr( 'style' );;
			$pickerDiv.slideUp('fast');
		}

		return false;
	});

	$('.cancel-timestamp', '.date-picker-div').click(function() {
		var $pickerDiv = $(this).parents('.date-picker-div'),
			$editDiv = $(this).parents('.edit-date-div');

		$editDiv.removeAttr( 'style' );;
		$pickerDiv.slideUp('fast');
		$pickerDiv.siblings('a.edit-timestamp').show();
		return false;
	});

	$('.save-timestamp', '.date-picker-div').click(function () {
		var $pickerDiv = $(this).parents('.date-picker-div'),
			$editDiv = $pickerDiv.parents('.edit-date-div');
			$timeDiv = $editDiv.siblings('.next-payment-date');
			$subscriptionRow = $pickerDiv.parents('tr');

		$pickerDiv.slideUp('fast');
		$pickerDiv.parents('.row-actions').css({'background-image': 'url('+WCSubscriptions.ajaxLoaderImage+')'});

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: {
				action: 'wcs_update_next_payment_date',
				wcs_subscription_key: $('.subscription_key',$subscriptionRow).val(),
				wcs_user_id: $('.user_id',$subscriptionRow).val(),
				wcs_day: $('[name="edit-day"]', $pickerDiv).val(),
				wcs_month: $('[name="edit-month"]', $pickerDiv).val(),
				wcs_year: $('[name="edit-year"]', $pickerDiv).val(),
				wcs_nonce: WCSubscriptions.ajaxDateChangeNonce
			},
			success: function(response){
				response = $.parseJSON(response);
				if('error'==response.status){ // Output error message
					$editDiv.css({'background-image':''});
					$(response.message).hide().prependTo($timeDiv.parent()).slideDown('fast').fadeIn('fast');
					$pickerDiv.slideDown('fast');
					setTimeout(function() {
						$('.error',$timeDiv.parent()).slideUp();
					}, 4000);
				} else { // Update displayed payment date
					$editDiv.removeAttr( 'style' );;
					$timeDiv.fadeOut('fast',function(){
						$timeDiv.html(response.dateToDisplay);
						$timeDiv.attr('title',response.timestamp);
						$timeDiv.fadeIn('fast');
						$pickerDiv.siblings('a.edit-timestamp').fadeIn('fast');
						$(response.message).hide().prependTo($timeDiv.parent()).slideDown('fast').fadeIn('fast');
						setTimeout(function() {
							$('.updated',$timeDiv.parent()).slideUp();
						}, 3000);
					});
				}
			}
		});

		return false;
	});

	// Remove subscription item meta on Edit Order page (until changing the meta is handled)
	$('tbody.meta_items tr').each(function(){
		if($('input',$(this)).val().match(/^_recurring_.*/)||$('input',$(this)).val().match(/^_subscription_.*/))
			$(this).hide();
	});

	// Notify store manager that deleting an order also deletes subscriptions
	$('#posts-filter').submit(function(){
		if($('[name="post_type"]').val()=='shop_order' && $('[name="action"]').val()=='trash'){
			var containsSubscription = false;
			$('[name="post[]"]:checked').each(function(){
				if($('[name="contains_subscription"]',$('#post-'+$(this).val())).val()=='true'){
					containsSubscription = true;
					return false;
				}
			});
			if(containsSubscription)
				return confirm(WCSubscriptions.bulkTrashWarning);
		}
	});

	$('.order_actions .submitdelete').click(function(){
		if($('[name="contains_subscription"]').val()=='true')
			return confirm(WCSubscriptions.bulkTrashWarning);
	});

	$(window).load(function(){
		if($('[name="contains_subscription"]').length > 0 && $('[name="contains_subscription"]').val()=='true'){
			$('#woocommerce-order-items #add_item_id').hide();
			$('#woocommerce-order-items #add_item_id_chzn').hide();
			$('#woocommerce-order-items .add_shop_order_item').hide();
		}
	});
});