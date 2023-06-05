( function(){
	if( typeof affwp_tooltips !== "undefined" ){

		/**
		 * Stores the postbox click callback so we can use it later.
		 *
		 * @type {function(*): boolean}
		 */
		var handlePostboxClick = postboxes.handle_click;

		/**
		 * Wraps postbox.handle_click method with a check that will prevent the action from firing when clicking on a tooltip.
		 * @param e
		 * @returns {boolean}
		 */
		postboxes.handle_click = function( e ){
			if( false === jQuery( e.target ).parent().hasClass( 'affwp-tooltip' ) ){
				var boundClick = handlePostboxClick.bind( this );
				return boundClick();
			}

			return false;
		};

		jQuery( document ).ready( function( $ ){

			var adminBarHeight = $( '#wpadminbar' ).outerHeight();
			var adminMenuWidth = $( '#adminmenuback' ).outerWidth();

			var Tooltip = function( instance ){

				/**
				 * The tooltip content to display on-hover.
				 * @type (string)
				 */
				this.content = affwp_tooltips.tooltips[instance.id];

				/**
				 * The meta box target
				 *
				 * @type {jQuery|HTMLElement}
				 */
				this.target = $( instance );

				/**
				 * The meta box title target.
				 *
				 * @type {jQuery}
				 */
				this.titleTarget = $( this.target ).find( '.hndle' );

				/**
				 * The meta box marker target.
				 *
				 * This will automatically be set when the appendMarker is ran.
				 *
				 * @type {boolean|jQuery}
				 */
				this.markerTarget = false;

				/**
				 * The meta box tooltip target.
				 *
				 * This will automatically be set when the appendMarkerContent is ran.
				 *
				 * @type {boolean|jQuery}
				 */
				this.tooltipContentTarget = false;

				/**
				 * Appends the tooltip hover marker (?) element to the meta box title.
				 */
				this.appendMarker = function(){
					var marker = "<span class='affwp-tooltip'><span class='dashicons dashicons-editor-help'></span></span>";
					this.titleTarget.append( marker );
					this.markerTarget = this.target.find( '.affwp-tooltip' );
				};

				/**
				 * Appends the tooltip content to the element to the meta box title.
				 */
				this.appendTooltipContent = function(){
					if( false !== this.markerTarget ){
						var content = "<span class='affwp-tooltip-content'>" + this.content + "</span>";
						this.markerTarget.append( content );
						this.tooltipContentTarget = this.target.find( '.affwp-tooltip-content' );
					}
				};

				/**
				 * Adds event listeners to the tooltip.
				 */
				this.addEventListeners = function(){
					var instance = this;
					if( false !== this.tooltipContentTarget ) {
						var tooltipContent = this.tooltipContentTarget;

						// Adds the tooltip on hover
						this.markerTarget.on( 'mouseenter', function( e ) {
							var classes = ['active'];
							var overflows = instance.getTooltipOverflows();

							$( Object.keys( overflows ) ).each( function() {
								if( false !== overflows[this] ) {
									classes.push( "overflow-" + this );
								}
							} );

							tooltipContent.addClass( classes.join( ' ' ) );
						} );

						// Removes the tooltip
						this.markerTarget.on( 'mouseleave', function( e ) {
							tooltipContent.removeClass( 'overflow-top overflow-right active' );
						} );
					}
				};

				/**
				 * Determines from what directions the tooltip will overflow when displayed.
				 *
				 * @returns {{left, right}}
				 */
				this.getTooltipOverflows = function(){
					var overflows = {};

					var target = $( this.tooltipContentTarget );
					var offset = target.offset();
					var targetWidth = target.outerWidth();

					var documentHeight = document.documentElement.clientHeight;
					var documentWidth = document.documentElement.clientWidth;
					var topOfWindow = ( documentHeight + window.scrollY + adminBarHeight ) - documentHeight;
					var rightOfWindow = documentWidth - window.scrollX;

					var topOverflow = topOfWindow - offset.top;
					var rightOverflow = ( offset.left + targetWidth ) - rightOfWindow;

					overflows.top = topOverflow > 0 ? topOverflow : false;
					overflows.right = rightOverflow > 0 ? rightOverflow : false;

					return overflows;
				};

				/**
				 * Inserts the tooltip into the metabox.
				 */
				this.create = function(){
					// Append the marker to the title
					this.appendMarker();

					// Append the content to the title
					this.appendTooltipContent();

					// Registers the event listeners
					this.addEventListeners();
				}
			};

			// Loop through each tooltip that has a tooltip, and insert the tooltip.
			$( '.postbox.has-tooltip' ).each( function(){
				new Tooltip( this ).create();
			} );
		} );

	}
} )();