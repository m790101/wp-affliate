/* global affwp_vars */
jQuery(document).ready(function($) {

		// Settings uploader
	var file_frame;
	window.formfield = '';

	$('body').on('click', '.affwp_settings_upload_button', function(e) {

		e.preventDefault();

		var button = $(this);

		window.formfield = $(this).parent().prev();

		// If the media frame already exists, reopen it.
		if( file_frame ) {
			file_frame.open();
			return;
		}

		// Create the media frame
		file_frame = wp.media.frames.file_frame = wp.media({
			frame: 'post',
			state: 'insert',
			title: button.data( 'uploader_title' ),
			button: {
				text: button.data( 'uploader_button_text' )
			},
			multiple: false
		});

		file_frame.on( 'menu:render:default', function( view ) {
			// Store our views in an object,
			var views = {};

			// Unset default menu items
			view.unset( 'library-separator' );
			view.unset( 'gallery' );
			view.unset( 'featured-image' );
			view.unset( 'embed' );

			// Initialize the views in our view object
			view.set( views );
		});

		// When an image is selected, run a callback
		file_frame.on( 'insert', function() {
			var selection = file_frame.state().get( 'selection' );

			selection.each( function( attachment, index ) {
				attachment = attachment.toJSON();
				window.formfield.val(attachment.url);
			});
		});

		// Open the modal
		file_frame.open();
	});

	var file_frame;
	window.formfield = '';

	// Show referral export form
	$('.affwp-referrals-export-toggle').click(function() {
		$('.affwp-referrals-export-toggle').toggle();
		$('#affwp-referrals-export-form').slideToggle();
	});

	// datepicker
	if( $('.affwp-datepicker').length ) {
		$('.affwp-datepicker').datepicker({dateFormat: 'mm/dd/yy'});
	}

	// Ajax user search.
	$( '.affwp-user-search' ).each( function(){
		var $all_input_fields,
			$all_fields_excluding_search,
			$this = $( this ),
			$action = 'affwp_search_users',
			$search = $this.val(),
			$status = $this.data( 'affwp-status' ),
			$form = $this.closest( 'form' ),
			$submit = $form.find( ':submit.button-primary' ),
			$submit_default_value = $submit.val(),
			$user_creation_fields = $( '.affwp-user-email-wrap, .affwp-user-pass-wrap' ),
			$on_complete_enabled = $this.hasClass( 'affwp-enable-on-complete' );

		if( $on_complete_enabled === true ){
			$all_input_fields = $form.find( 'input, select' );
			$all_fields_excluding_search = $all_input_fields.not( $this );
		}

		$this.on( 'input', function(){
			$user_creation_fields.hide();
		} );

		$this.autocomplete( {
			source: ajaxurl + '?action=' + $action + '&term=' + $search + '&status=' + $status,
			messages: {
				// This is specified as such because we want to force screen readers to read the message in the response callback
				noResults: $on_complete_enabled ? '' : 'No users found'
			},
			delay: 500,
			minLength: 2,
			position: { offset: '0, -1' },
			search: function(){
				if( $this.hasClass( 'affwp-enable-on-complete' ) ){
					$( 'div.notice' ).remove();
					$user_creation_fields.hide();
				}
			},
			open: function(){
				$this.addClass( 'open' );
			},
			close: function(){
				$this.removeClass( 'open' );
			},
			response: function( event, ui ){
				if( ui.content.length === 0 && $this.hasClass( 'affwp-enable-on-complete' ) ){
					// This triggers when no results are found
					$( '<div class="notice notice-error affwp-new-affiliate-error"><p>' + affwp_vars.no_user_found + '</p></div>' ).insertAfter( $this );
					wp.a11y.speak( affwp_vars.no_user_found, 'polite' );

					$form.find( 'input, select' ).prop( 'disabled', false );

					$( '.affwp-user-email-wrap, .affwp-user-pass-wrap' ).show();
					$( '.affwp-user-email' ).prop( 'required' );
					$( '.search-description' ).hide();

					$submit.val( affwp_vars.user_and_affiliate_input );
				}else{
					wp.a11y.speak( affwp_vars.valid_user_selected, 'polite' );
				}
			},
			select: function(){

				if( $this.hasClass( 'affwp-enable-on-complete' ) ){

					$.ajax( {
						type: 'POST',
						url: ajaxurl,
						data: {
							action: 'affwp_check_user_login',
							user: $this.val()
						},
						dataType: "json",
						success: function( response ){

							if( response.success ){

								if( false === response.data.affiliate ){

									$form.find( 'input, select' ).prop( 'disabled', false );

								}else{

									var viewLink = '<a href="' + response.data.url + '">' + affwp_vars.view_affiliate + '</a>';

									$( '<div class="notice notice-info affwp-new-affiliate-error"><p>' + affwp_vars.existing_affiliate + ' ' + viewLink + '</p></div>' ).insertAfter( $this );

									$this.prop( 'disabled', false );

								}
							}

						}

					} ).fail( function( response ){
						if( window.console && window.console.log ){
							console.log( response );
						}
					} );
				}
			}
		} );

		// Immediately disable all other fields when this input updates.
		if( $on_complete_enabled === true ){
			$this.on( 'input', function(){
				$all_fields_excluding_search.prop( 'disabled', true );
				$submit.val( $submit_default_value );
			} );
		}
	} );

	// select image for creative
	var file_frame;
	$( 'body' ).on( 'click', '.upload_image_button', function( e ){

		e.preventDefault();

		var formfield = $( this ).prev();

		// If the media frame already exists, reopen it.
		if( file_frame ){
			//file_frame.uploader.uploader.param( 'post_id', set_to_post_id );
			file_frame.open();
			return;
		}

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media( {
			frame: 'select',
			title: 'Choose Image',
			multiple: false,
			library: {
				type: 'image'
			},
			button: {
				text: 'Use Image'
			}
		} );

		file_frame.on( 'menu:render:default', function(view) {
			// Store our views in an object.
			var views = {};

			// Unset default menu items
			view.unset('library-separator');
			view.unset('gallery');
			view.unset('featured-image');
			view.unset('embed');

			// Initialize the views in our view object.
			view.set(views);
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			var attachment = file_frame.state().get('selection').first().toJSON();
			formfield.val(attachment.url);

			var img = $('<img />');
			img.attr('src', attachment.url);
			// replace previous image with new one if selected
			$('#preview_image').empty().append( img );

			// show preview div when image exists
			if ( $('#preview_image img') ) {
				$('#preview_image').show();
			}
		});

		// Finally, open the modal
		file_frame.open();
	});

	// Confirm referral deletion
	$('body').on('click', '.affiliates_page_affiliate-wp-referrals .delete', function(e) {

		if( confirm( affwp_vars.confirm_delete_referral) ) {
			return true;
		}

		return false;

	});

	function maybe_activate_migrate_users_button() {
		var checked = $('#affiliate-wp-migrate-user-accounts input:checkbox:checked' ).length,
				$button = $('#affiliate-wp-migrate-user-accounts input[type=submit]');

		if ( checked > 0 ) {
			$button.prop( 'disabled', false );
		} else {
			$button.prop( 'disabled', true );
		}
	}

	maybe_activate_migrate_users_button();

	$('body').on('change', '#affiliate-wp-migrate-user-accounts input:checkbox', function() {
		maybe_activate_migrate_users_button();
	});

	$('#affwp_add_affiliate #status').change(function() {

		var status = $(this).val();
		if( 'active' == status ) {
			$('#affwp-welcome-email-row').show();
		} else {
			$('#affwp-welcome-email-row').hide();
			$('#affwp-welcome-email-row #welcome_email').prop( 'checked', false );
		}

	});

	// Generate Code button in Affiliates > Add / Edit.
	$( '#generate-coupon-code-button' ).on( 'click', function( event ) {
		event.preventDefault();

		$.ajax( {
			type : 'POST',
			url  : ajaxurl,
			data : {
				action: 'affwp_generate_coupon_code',
				affiliate_id: $( this ).data( 'affiliate_id' )
			},
			dataType: "json",
			success : function (response) {
				if ( response.success && response.data.coupon_code ) {
					$( '#coupon_code' ).val( response.data.coupon_code );
				}
			}
		} );

	} );

	// Live preview functionality for the coupon tab settings.
	if ( '?page=affiliate-wp-settings&tab=coupons' === window.location.search ) {
		/**
		 * Gets and sanitizes the current value of the custom text field.
		 *
		 * @since 2.8
		 *
		 * @return {string} Returns sanitized custom text value. If empty, defaults to 'TEXT'.
		 */
		const getCustomText = function () {
			var customText = document.getElementById( 'affwp_settings[coupon_custom_text]' ).value;

			if ( '' === customText ) {
				return 'TEXT';
			} else {
				// Only display alphanumeric.
				return customText.replace(/[^0-9a-z]/gi, '');
			}
		}

		/**
		 * Returns the live preview version of a given coupon format text.
		 *
		 * This replaces merge tags with preview defaults, capitalizes it, and removes hyphens if necessary.
		 *
		 * @since 2.8
		 *
		 * @param  {string} text Coupon format option text.
		 * @return {string}      Return coupon format live preview text.
		 */
		const returnLivePreviewText = function ( text ) {
			// Replace coupon code, username, first name, coupon amount, and custom text merge tags.
			var previewText = text;
			previewText     = previewText.replace( '{coupon_code}',   'FGFUVCQOK1' );
			previewText     = previewText.replace( '{user_name}',     'CREATIVELABS' );
			previewText     = previewText.replace( '{first_name}',    'BOB' );
			previewText     = previewText.replace( '{coupon_amount}', '10' );
			previewText     = previewText.replace( '{custom_text}',    getCustomText() );

			// Capitalize preview.
			previewText = previewText.toUpperCase();

			// Keep or remove hyphens.
			var keepHyphens = checkHyphenDelimiterSetting();
			if ( true === keepHyphens ) {
				return previewText;
			} else {
				return previewText.replace( /-/g, '' );
			}
		}

		/**
		 * Updates coupon format options with live preview options.
		 * Also, adds/removes hyphens as necessary.
		 *
		 * @since  2.8
		 *
		 * @return void.
		 */
		const updateCouponFormatOptions = function () {
			var hyphenSettingChecked = checkHyphenDelimiterSetting();
			var formatOptions        = document.getElementById('affwp_settings[coupon_format]').options;

			if ( true === hyphenSettingChecked ) {
				// Add hyphens.
				for ( var index = 0; index < formatOptions.length; index++ ) {
					formatOptions[index].text = returnLivePreviewText( couponFormatOptions[index] );
				}
			} else {
				// Remove hyphens.
				for ( var index = 0; index < formatOptions.length; index++ ) {
				    formatOptions[index].text = formatOptions[index].text.replace( /-/g, '' );
				}
			}

		}

		/**
		 * Checks if the Coupon Hyphen Delimiter setting is enabled.
		 *
		 * @since  2.8
		 *
		 * @return {boolean} True if Coupon Hyphen Delimiter setting is checked, otherwise, false.
		 */
		const checkHyphenDelimiterSetting = function () {
			return $( "input[id='affwp_settings[coupon_hyphen_delimiter]']" ).is( ':checked' );
		}

		/**
		 * Resets the currently selected coupon format option to be the coupon format value with merge tags.
		 *
		 * @since  2.8
		 *
		 * @return void.
		 */
		const resetCFSelectedOption = function () {
			// Get the currently selected coupon format option's value.
			var formatValue = $( "select[id='affwp_settings[coupon_format]'] option:selected" )[0].value;

			// Remove hyphen if hyphen delimiter setting is false.
			if ( false === checkHyphenDelimiterSetting() ) {
				formatValue = formatValue.replace( /-/g, '' );
			}

			// Make the selected option display the coupon format value.
			$( "select[id='affwp_settings[coupon_format]'] option:selected" )[0].text = formatValue;
		}

		// Setup format options.
		var formatOptions       = document.getElementById('affwp_settings[coupon_format]').options;
		var couponFormatOptions = [];

		for ( var option = 0; option < formatOptions.length; option++ ) {
			// Add options for live preview.
		    couponFormatOptions.push( formatOptions[option].text );
			// Change Coupon Format options to live preview.
		    formatOptions[option].text = returnLivePreviewText( couponFormatOptions[option] );
		};

		// Update the selected option to display coupon format value
		resetCFSelectedOption();

		// On coupon format setting change, return options to live preview and the selected option as the coupon format.
		$( "select[id='affwp_settings[coupon_format]']" ).on( 'change', function ( event ) {
			// Make options display preview values.
			updateCouponFormatOptions();
			// Make the current selected option display as the coupon format with merge tags.
			resetCFSelectedOption();
		});

		// Add or remove hyphens in the coupon format live preview based on the Hyphen Delimiter setting.
		$( "input[id='affwp_settings[coupon_hyphen_delimiter]']" ).on( 'click', function ( event ) {
			// Update options display preview values with or without hyphens.
			updateCouponFormatOptions();
			// Make the current selected option display as the coupon format with merge tags.
			resetCFSelectedOption();
		});

		// Update live preview as you edit the custom text field.
		$( "input[id='affwp_settings[coupon_custom_text]']" ).on( 'keyup', function () {
			// Change Coupon Format options to live preview.
			for ( var i = 0; i < formatOptions.length; i++ ) {
			    formatOptions[i].text = returnLivePreviewText( couponFormatOptions[i] );
			}
		});
	};

	// Dismiss promo notices.
	$( '.affwp-promo-notice' ).each( function () {
		const notice = $( this );
		const noticeId = notice.data( 'id' );
		const nonce = notice.data( 'nonce' );
		const lifespan = notice.data( 'lifespan' );

		notice.on( 'click', '.notice-dismiss, .affwp-notice-dismiss', function( event ) {
			wp.ajax.send( 'affwp_dismiss_promo', {
				data: {
					notice_id: noticeId,
					nonce: nonce,
					lifespan: lifespan,
				},
				success: function() {
					notice.slideUp( 'fast' );

					// Remove previously set "seen" local storage.
					const uid = userSettings.uid ? userSettings.uid : 0;
					const seenKey = 'affwp-notice-' + noticeId + '-seen-' + uid;
					window.localStorage.removeItem( seenKey );
				},
			} );
		} );
	} );

	// Move "Top of Page" promos to the top of content (before Help/Screen Options).
	const topOfPageNotice = jQuery( '.affwp-admin-notice-top-of-page' );

	if ( topOfPageNotice.length > 0 ) {
		const topOfPageNoticeEl = topOfPageNotice.detach();

		jQuery( '#wpbody-content' ).prepend( topOfPageNoticeEl );

		const uid = userSettings.uid ? userSettings.uid : 0;
		const noticeId = topOfPageNoticeEl.data( 'id' );
		const seenKey = 'affwp-notice-' + noticeId + '-seen-' + uid;

		if ( window.localStorage.getItem( seenKey ) ) {
			topOfPageNoticeEl.show();
		} else {
			setTimeout( function() {
				window.localStorage.setItem( seenKey, true );
				topOfPageNotice.slideDown();
			}, 1500 );
		}
	}

	/**
	 * Support to show/hide the Referral basis based on the affiliate rate type.
	 * Used in Affiliate WP Settings, edit affiliate admin page, and new affiliate admin page.
	 *
	 * @since 2.3
	 */
	if( $.inArray( pagenow, ['affiliates_page_affiliate-wp-affiliates', 'affiliates_page_affiliate-wp-settings'] ) > -1 ){
		var flatRateBasisField;
		var referralRateTypeField;

		if( pagenow === 'affiliates_page_affiliate-wp-settings' ){
			flatRateBasisField = $( '.affwp-referral-rate-type-field' );
			referralRateTypeField = $( 'input[name="affwp_settings[referral_rate_type]"]' );
		}else{
			flatRateBasisField = $( '#flat_rate_basis' ).closest( 'tr' );
			referralRateTypeField = $( '#rate_type' );
		}

		referralRateTypeField.on( 'change', function( e ){
			if( 'flat' !== e.target.value ){
				flatRateBasisField.hide();
			}else{
				flatRateBasisField.show();
			}
		} );
	}

	/**
	 * Enable meta box toggle states
	 *
	 * @since  1.9
	 *
	 * @param  typeof postboxes postboxes object
	 *
	 * @return {void}
	 */
	if ( typeof postboxes !== 'undefined' && /affiliate-wp/.test( pagenow ) ) {
		postboxes.add_postbox_toggles( pagenow );
	}

} );
