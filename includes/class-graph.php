<?php
/**
 * Graphs
 *
 * This class handles building pretty report graphs
 *
 * @package     AffiliateWP
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Affiliate_WP_Graph Class
 *
 * @since 1.0
 */
class Affiliate_WP_Graph {

	/*

	Simple example:

	data format for each point: array( location on x, location on y )

	$data = array(

		'Label' => array(
			array( 1, 5 ),
			array( 3, 8 ),
			array( 10, 2 )
		),

		'Second Label' => array(
			array( 1, 7 ),
			array( 4, 5 ),
			array( 12, 8 )
		)
	);

	$graph = new Affiliate_WP_Graph( $data );
	$graph->display();

	*/

	/**
	 * Data to graph
	 *
	 * @var array
	 * @since 1.0
	 */
	public $data;

	/**
	 * Unique ID for the graph
	 *
	 * @var string
	 * @since 1.0
	 */
	public $id = '';

	/**
	 * Graph options
	 *
	 * @var array
	 * @since 1.0
	 */
	public $options = array();

	/**
	 * Get things started
	 *
	 * @since 1.0
	 */
	public function __construct( $_data = array() ) {

		$this->data = $_data;

		// Generate unique ID
		$this->id   = md5( rand() );

		// Setup default options;
		$this->options = array(
			'y_mode'          => null,
			'x_mode'          => null,
			'y_decimals'      => 0,
			'x_decimals'      => 0,
			'y_position'      => 'right',
			'time_format'     => '%d/%b',
			'ticksize_unit'   => 'day',
			'ticksize_num'    => 1,
			'multiple_y_axes' => false,
			'bgcolor'         => '#f9f9f9',
			'bordercolor'     => '#ccc',
			'borderwidth'     => 2,
			'bars'            => false,
			'lines'           => true,
			'points'          => true,
			'currency'        => true,
			'show_controls'   => true,
			'form_wrapper'    => true,
		);

	}

	/**
	 * Set an option
	 *
	 * @param $key The option key to set
	 * @param $value The value to assign to the key
	 * @since 1.0
	 */
	public function set( $key, $value ) {
		if( 'data' == $key ) {

			$this->data = $_data;

		} else {

			$this->options[ $key ] = $value;

		}
	}

	/**
	 * Get an option
	 *
	 * @param $key The option key to get
	 * @since 1.0
	 */
	public function get( $key ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : false;
	}

	/**
	 * Get graph data
	 *
	 * @since 1.0
	 */
	public function get_data() {
		/**
		 * Filters the data for the current reporting graph.
		 *
		 * @since 1.0
		 *
		 * @param array               $data     Graph data.
		 * @param \Affiliate_WP_Graph $instance Current Affiliate_WP_Graph instance.
		 */
		return apply_filters( 'affwp_get_graph_data', $this->data, $this );
	}

	/**
	 * Load the graphing library script
	 *
	 * @since 1.0
	 */
	public function load_scripts() {
		// Use minified libraries if SCRIPT_DEBUG is turned off
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		wp_enqueue_script( 'jquery-flot', AFFILIATEWP_PLUGIN_URL . 'assets/js/jquery.flot' . $suffix . '.js' );

		if( $this->load_resize_script() ) {
			wp_enqueue_script( 'jquery-flot-resize', AFFILIATEWP_PLUGIN_URL . 'assets/js/jquery.flot.resize' . $suffix . '.js' );
		}
	}

	/**
	 * Determines if the resize script should be loaded
	 *
	 * @since 1.1
	 */
	public function load_resize_script() {

		$ret = true;

		// The DMS theme is known to cause some issues with the resize script
		if( defined( 'DMS_CORE' ) ) {
			$ret = false;
		}

		/**
		 * Filters whether to load the resize script for a Flot.js.
		 *
		 * @since 1.1
		 *
		 * @param bool $load Whether to load the resize script or not.
		 */
		return apply_filters( 'affwp_load_flot_resize', $ret );
	}

	/**
	 * Build the graph and return it as a string
	 *
	 * @var array
	 * @since 1.0
	 * @return string
	 */
	public function build_graph() {
		$this->load_scripts();

		ob_start();

		wp_add_inline_script( 'jquery-flot', $this->graph_js() );

		if ( false !== $this->get( 'show_controls' ) ) {
			echo $this->graph_controls();
		}
		?><div id="affwp-graph-<?php echo $this->id; ?>" class="affwp-graph" style="height: 300px; width:97%;"></div><?php
		return ob_get_clean();
	}

	/**
	 * Retrieves the Graph initialization JS for output inline.
	 *
	 * @access public
	 * @since  1.9.5
	 *
	 * @return string Graph JS output.
	 */
	public function graph_js() {
		$yaxis_count = 1;

		ob_start();
		?>
		var affwp_vars;
		jQuery( document ).ready( function($) {
			$.plot(
				$("#affwp-graph-<?php echo $this->id; ?>"),
				[
					<?php foreach( $this->get_data() as $label => $data ) : ?>
					{
						label: "<?php echo esc_attr( $label ); ?>",
						id: "<?php echo sanitize_key( $label ); ?>",
						// data format is: [ point on x, value on y ]
						data: [<?php foreach( $data as $point ) { echo '[' . implode( ',', $point ) . '],'; } ?>],
						points: {
							show: <?php echo $this->options['points'] ? 'true' : 'false'; ?>,
						},
						bars: {
							show: <?php echo $this->options['bars'] ? 'true' : 'false'; ?>,
							barWidth: 2,
							align: 'center'
						},
						lines: {
							show: <?php echo $this->options['lines'] ? 'true' : 'false'; ?>
						},
						<?php if( $this->options[ 'multiple_y_axes' ] ) : ?>
						yaxis: <?php echo $yaxis_count; ?>
						<?php endif; ?>
					},
					<?php $yaxis_count++; endforeach; ?>
				],
				{
					// Options
					grid: {
						show: true,
						aboveData: false,
						backgroundColor: "<?php echo $this->options[ 'bgcolor' ]; ?>",
						borderColor: "<?php echo $this->options[ 'bordercolor' ]; ?>",
						borderWidth: <?php echo absint( $this->options[ 'borderwidth' ] ); ?>,
						clickable: false,
						hoverable: true
					},
					xaxis: {
						mode: "<?php echo $this->options['x_mode']; ?>",
						timeformat: "<?php echo $this->options['x_mode'] == 'time' ? $this->options['time_format'] : ''; ?>",
						tickSize: "<?php echo $this->options['x_mode'] == 'time' ? '' : 1; ?>",
						<?php if( $this->options['x_mode'] != 'time' ) : ?>
						tickDecimals: <?php echo $this->options['x_decimals']; ?>
						<?php endif; ?>
					},
					yaxis: {
						position: 'right',
						min: 0,
						mode: "<?php echo $this->options['y_mode']; ?>",
						timeformat: "<?php echo $this->options['y_mode'] == 'time' ? $this->options['time_format'] : ''; ?>",
						<?php if( $this->options['y_mode'] != 'time' ) : ?>
						tickDecimals: <?php echo $this->options['y_decimals']; ?>
						<?php endif; ?>
					}
				}

			);

			function affwp_flot_tooltip(x, y, contents) {
				$('<div id="affwp-flot-tooltip">' + contents + '</div>').css( {
					position: 'absolute',
					display: 'none',
					top: y + 5,
					left: x + 5,
					border: '1px solid #fdd',
					padding: '2px',
					'background-color': '#fee',
					opacity: 0.80
				}).appendTo("body").fadeIn(200);
			}

			var previousPoint = null;
			$("#affwp-graph-<?php echo $this->id; ?>").bind("plothover", function (event, pos, item) {
				$("#x").text(pos.x.toFixed(2));
				$("#y").text(pos.y.toFixed(2));
				if (item) {
					if (previousPoint != item.dataIndex) {
						previousPoint = item.dataIndex;
						$("#affwp-flot-tooltip").remove();
						var x = item.datapoint[0].toFixed(2),
							y = item.datapoint[1].toFixed(2);

						<?php if( $this->get( 'currency' ) ) : ?>
						if( affwp_vars.currency_pos == 'before' ) {
							affwp_flot_tooltip( item.pageX, item.pageY, item.series.label + ' ' + affwp_vars.currency_sign + y );
						} else {
							affwp_flot_tooltip( item.pageX, item.pageY, item.series.label + ' ' + y + affwp_vars.currency_sign );
						}
						<?php else : ?>
						affwp_flot_tooltip( item.pageX, item.pageY, item.series.label + ' ' + y );
						<?php endif; ?>
					}
				} else {
					$("#affwp-flot-tooltip").remove();
					previousPoint = null;
				}
			});

			$( '#affwp-graphs-date-options' ).change( function() {
				var $this = $(this);
				if( $this.val() == 'other' ) {
					$( '#affwp-date-range-options' ).css('display', 'inline-block');
				} else {
					$( '#affwp-date-range-options' ).hide();
				}
			});

		});
		<?php
		return ob_get_clean();
	}

	/**
	 * Output the final graph
	 *
	 * @since 1.0
	 */
	public function display() {
		/**
		 * Fires just prior to the graph output.
		 *
		 * @since 1.0
		 *
		 * @param stdClass $graph The graph object.
		 */
		do_action( 'affwp_before_graph', $this );

		echo $this->build_graph();

		/**
		 * Fires immediately after the graph output.
		 *
		 * @since 1.0
		 *
		 * @param stdClass $graph The graph object.
		 */
		do_action( 'affwp_after_graph', $this );
	}

	/**
	 * Displays the report graph date filters.
	 *
	 * @internal Note that this method is also used on the front-end. Any changes here
	 *           should be equally tested in the Affiliate Area..
	 *
	 * @access public
	 * @since  1.0
	*/
	public function graph_controls() {
		/**
		 * Filters the date filter options to use in the controls for the current reports graph.
		 *
		 * @since 1.0
		 *
		 * @param array $date_options List of date options and their user-facing, translatable labels.
		 */
		$date_options = apply_filters( 'affwp_report_date_options', array(
			'today' 	    => __( 'Today', 'affiliate-wp' ),
			'yesterday'     => __( 'Yesterday', 'affiliate-wp' ),
			'this_week' 	=> __( 'This Week', 'affiliate-wp' ),
			'last_week' 	=> __( 'Last Week', 'affiliate-wp' ),
			'this_month' 	=> __( 'This Month', 'affiliate-wp' ),
			'last_month' 	=> __( 'Last Month', 'affiliate-wp' ),
			'this_quarter'	=> __( 'This Quarter', 'affiliate-wp' ),
			'last_quarter'	=> __( 'Last Quarter', 'affiliate-wp' ),
			'this_year'		=> __( 'This Year', 'affiliate-wp' ),
			'last_year'		=> __( 'Last Year', 'affiliate-wp' ),
			'other'			=> __( 'Custom', 'affiliate-wp' )
		) );

		$dates = affwp_get_report_dates();

		$display = $dates['range'] == 'other' ? 'style="display:inline-block;"' : 'style="display:none;"';

		$current_time = current_time( 'timestamp' );

		if ( $this->get( 'form_wrapper' ) ) {
			?>
			<form id="affwp-graphs-filter" method="get">
			<div class="tablenav top">
			<?php
		}

		if( is_admin() ) : ?>
			<?php $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'referral'; ?>
			<?php $page = isset( $_GET['page'] ) ? $_GET['page'] : 'affiliate-wp'; ?>
			<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>"/>
		<?php else: ?>
			<?php $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'graphs'; ?>
			<input type="hidden" name="page_id" value="<?php echo esc_attr( get_the_ID() ); ?>"/>
		<?php endif; ?>

		<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>"/>

		<?php if( isset( $_GET['affiliate_id'] ) ) : ?>
		<input type="hidden" name="affiliate_id" value="<?php echo absint( $_GET['affiliate_id'] ); ?>"/>
		<input type="hidden" name="action" value="view_affiliate"/>
		<?php endif; ?>

		<select id="affwp-graphs-date-options" class="affwp-graphs-date-options" name="range">
		<?php
			foreach ( $date_options as $key => $option ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $dates['range'] ) . '>' . esc_html( $option ) . '</option>';
			}
		?>
		</select>

		<div id="affwp-date-range-options" <?php echo $display; ?>>

			<?php
			$from = empty( $_REQUEST['filter_from'] ) ? '' : $_REQUEST['filter_from'];
			$to   = empty( $_REQUEST['filter_to'] )   ? '' : $_REQUEST['filter_to'];
			?>
			<span class="affwp-search-date">
				<span><?php _ex( 'From', 'date filter', 'affiliate-wp' ); ?></span>
				<input type="text" class="affwp-datepicker" autocomplete="off" name="filter_from" placeholder="<?php esc_attr_e( 'From - mm/dd/yyyy', 'affiliate-wp' ); ?>" aria-label="<?php esc_attr_e( 'From - mm/dd/yyyy', 'affiliate-wp' ); ?>" value="<?php echo esc_attr( $from ); ?>" />
				<span><?php _ex( 'To', 'date filter', 'affiliate-wp' ); ?></span>
				<input type="text" class="affwp-datepicker" autocomplete="off" name="filter_to" placeholder="<?php esc_attr_e( 'To - mm/dd/yyyy', 'affiliate-wp' ); ?>" aria-label="<?php esc_attr_e( 'To - mm/dd/yyyy', 'affiliate-wp' ); ?>" value="<?php echo esc_attr( $to ); ?>" />
			</span>

		</div>
		<?php
		if ( $this->get( 'form_wrapper' ) ) {
			?>
			<input name="submit" id="submit" class="button" value="<?php esc_attr_e( 'Filter', 'affiliate-wp' ); ?>" type="submit">
			</div><!-- .tablenav .top -->
			</form><!-- .affwp-graphs-filter -->
			<?php
		}
	}

}

/**
 * Sets up the dates used to filter graph data
 *
 * Date sent via $_GET is read first and then modified (if needed) to match the
 * selected date-range (if any)
 *
 * @since 1.0
 * @since 2.6.2 The `$args` parameter was added, removing reliance alone on request-based arguments.
 *
 * @param array $args {
 *     Optional. Arguments for filtering graph data.
 *
 *     @type string $range    Date range to filter for. Accepts 'this_month', 'last_month', 'today',
 *                            'yesterday', 'this_week', 'last_week', 'this_quarter', 'last_quarter',
 *                            'this_year', and 'last_year'. Default 'this_month'.
 *     @type string $year     Beginning year to filter for. The year for the 'date_from' timestamp
 *                            is used as a fallback, and the current year the fallback after that.
 *     @type string $year_end Ending year to filter for. The year for the 'date_to' timestamp
 *                            is used as a fallback, and the current year the fallback after that.
 *     @type string $m_start  Beginning month to filter for. The month for the 'date_from' timestamp
 *                            is used as a fallback, and the current month the fallback after that.
 *     @type string $m_end    Ending month to filter for. The month for the 'date_to' timestamp
 *                            is used as a fallback, and the current month the fallback after that.
 *     @type string $day      Beginning day to filter for. The day for the 'date_from' timestamp
 *                            is used as a fallback, and the current day the fallback after that.
 *     @type string $day_end  Ending day to filter for. The day for the 'date_to' timestamp
 *                            is used as a fallback, and the current day the fallback after that.
 * }
 * @return array {
 *     Date values used by the reports API.
 *
 *     @type string $date_form Complete date to filter from if set, otherwise the current timestamp.
 *     @type string $date_to   Complete date to filter to if set, otherwise the current timestamp.
 *     @type int    $day      Day of the month (1-31) to start filtering results by.
 *     @type int    $day_end  Day of the month (1-31) to end filtering results by.
 *     @type int    $m_start  Month of the year (1-12) to start filtering results by.
 *     @type int    $m_end    Month of the year (1-12) to end filtering results by.
 *     @type int    $year     Year to start filtering results by.
 *     @type int    $year_end Year to end filtering results by.
 * }
*/
function affwp_get_report_dates( $args = array() ) {
	$dates = array();

	$current_time = current_time( 'timestamp' );

	$dates['date_from']  = ! empty( $_REQUEST['filter_from'] ) ? $_REQUEST['filter_from'] : date( 'j/n/Y', $current_time );
	$dates['date_to']    = ! empty( $_REQUEST['filter_to'] )   ? $_REQUEST['filter_to']   : date( 'j/n/Y', $current_time );

	$variable_from_time  = ! empty( $_REQUEST['filter_from'] ) ? strtotime( $dates['date_from'] ) : $current_time;
	$variable_to_time    = ! empty( $_REQUEST['filter_to'] )   ? strtotime( $dates['date_to'] )   : $current_time;

	$date_filters = array(
		'range'    => 'this_month',
		'year'     => date( 'Y', $variable_from_time ),
		'year_end' => date( 'Y', $variable_to_time ),
		'm_start'  => date( 'n', $variable_from_time ),
		'm_end'    => date( 'n', $variable_to_time ),
		'day'      => date( 'd', $variable_from_time ),
		'day_end'  => date( 'd', $variable_to_time ),
	);

	// Parse in dates from args first.
	$date_filters = array_merge( $date_filters, array_intersect_key( $args, $date_filters ) );

	// Parse in dates from $_GET.
	$date_filters = array_merge( $date_filters, array_intersect_key( $_GET, $date_filters ) );

	$dates = array_merge( $dates, $date_filters );

	// Modify dates based on predefined ranges
	switch ( $dates['range'] ) :

		case 'this_month' :
			$dates['day']       = 1;
			$dates['m_start']   = date( 'n', $current_time );
			$dates['m_end']	    = date( 'n', $current_time );
			$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, $dates['m_start'], date( 'Y', $current_time ) );
			$dates['year']      = date( 'Y', $current_time );
		break;

		case 'last_month' :
			if( date( 'n' ) == 1 ) {
				$dates['day']     = 1;
				$dates['day_end'] = cal_days_in_month( CAL_GREGORIAN, 12, date( 'Y', $current_time ) );
				$dates['m_start'] = 12;
				$dates['m_end']	  = 12;
				$dates['year']    = date( 'Y', $current_time ) - 1;
				$dates['year_end']= date( 'Y', $current_time ) - 1;
			} else {
				$dates['day']     = 1;
				$dates['day_end'] = cal_days_in_month( CAL_GREGORIAN, date( 'n' ) - 1, date( 'Y', $current_time ) );
				$dates['m_start'] = date( 'n' ) - 1;
				$dates['m_end']	  = date( 'n' ) - 1;
				$dates['year_end']= $dates['year'];
			}
		break;

		case 'today' :
			$dates['day']       = date( 'd', $current_time );
			$dates['day_end']   = date( 'd', $current_time );
			$dates['m_start'] 	= date( 'n', $current_time );
			$dates['m_end']		= date( 'n', $current_time );
			$dates['year']		= date( 'Y', $current_time );
		break;

		case 'yesterday' :
			$month              = date( 'n', $current_time ) == 1 && date( 'd', $current_time ) == 1 ? 12 : date( 'n', $current_time );
			$days_in_month      = cal_days_in_month( CAL_GREGORIAN, $month, date( 'Y', $current_time ) );
			$yesterday          = date( 'd', $current_time ) == 1 ? $days_in_month : date( 'd', $current_time ) - 1;
			$dates['day']		= $yesterday;
			$dates['day_end']   = $yesterday;
			$dates['m_start'] 	= $month;
			$dates['m_end'] 	= $month;
			$dates['year']		= $month == 1 && date( 'd', $current_time ) == 1 ? date( 'Y', $current_time ) - 1 : date( 'Y', $current_time );
		break;

		case 'this_week' :
			$dates['day']       = date( 'd', $current_time - ( date( 'w', $current_time ) - 1 ) *60*60*24 ) - 1;
			$dates['day']      += get_option( 'start_of_week' );
			$dates['day_end']   = $dates['day'] + 6;
			$dates['m_start'] 	= date( 'n', $current_time );
			$dates['m_end']		= date( 'n', $current_time );
			$dates['year']		= date( 'Y', $current_time );
		break;

		case 'last_week' :
			$dates['day']       = date( 'd', $current_time - ( date( 'w' ) - 1 ) *60*60*24 ) - 8;
			$dates['day']      += get_option( 'start_of_week' );
			$dates['day_end']   = $dates['day'] + 6;
			$dates['year']		= date( 'Y', $current_time );

			if( date( 'j', $current_time ) <= 7 ) {
				$dates['m_start'] 	= date( 'n', $current_time ) - 1;
				$dates['m_end']		= date( 'n', $current_time ) - 1;
				if( $dates['m_start'] <= 1 ) {
					$dates['year'] = date( 'Y', $current_time ) - 1;
					$dates['year_end'] = date( 'Y', $current_time ) - 1;
				}
			} else {
				$dates['m_start'] 	= date( 'n', $current_time );
				$dates['m_end']		= date( 'n', $current_time );
			}
		break;

		case 'this_quarter' :
			$month_now = date( 'n', $current_time );
			$dates['day'] = 1;

			if ( $month_now <= 3 ) {

				$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 4, date( 'Y', $current_time ) );
				$dates['m_start'] 	= 1;
				$dates['m_end']		= 4;
				$dates['year']		= date( 'Y', $current_time );

			} else if ( $month_now <= 6 ) {

				$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 7, date( 'Y', $current_time ) );
				$dates['m_start'] 	= 4;
				$dates['m_end']		= 7;
				$dates['year']		= date( 'Y', $current_time );

			} else if ( $month_now <= 9 ) {

				$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 10, date( 'Y', $current_time ) );
				$dates['m_start'] 	= 7;
				$dates['m_end']		= 10;
				$dates['year']		= date( 'Y', $current_time );

			} else {

				$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 1, date( 'Y', $current_time ) + 1 );
				$dates['m_start'] 	= 10;
				$dates['m_end']		= 1;
				$dates['year']		= date( 'Y', $current_time );
				$dates['year_end']  = date( 'Y', $current_time ) + 1;

			}
		break;

		case 'last_quarter' :
			$month_now = date( 'n' );
			$dates['day'] = 1;

			if ( $month_now <= 3 ) {

				$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 9, date( 'Y', $current_time ) - 1 );
				$dates['m_start']   = 10;
				$dates['m_end']     = 12;
				$dates['year']      = date( 'Y', $current_time ) - 1; // Previous year

			} else if ( $month_now <= 6 ) {

				$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 3, date( 'Y', $current_time ) );
				$dates['m_start'] 	= 1;
				$dates['m_end']		= 3;
				$dates['year']		= date( 'Y', $current_time );

			} else if ( $month_now <= 9 ) {

				$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 6, date( 'Y', $current_time ) );
				$dates['m_start'] 	= 4;
				$dates['m_end']		= 6;
				$dates['year']		= date( 'Y', $current_time );

			} else {

				$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 9, date( 'Y', $current_time ) );
				$dates['m_start'] 	= 7;
				$dates['m_end']		= 9;
				$dates['year']		= date( 'Y', $current_time );

			}
		break;

		case 'this_year' :
			$dates['day']       = 1;
			$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 12, date( 'Y', $current_time ) );
			$dates['m_start'] 	= 1;
			$dates['m_end']		= 12;
			$dates['year']		= date( 'Y', $current_time );
			$dates['year_end']  = date( 'Y', $current_time );
		break;

		case 'last_year' :
			$dates['day']       = 1;
			$dates['day_end']   = cal_days_in_month( CAL_GREGORIAN, 12, date( 'Y', $current_time ) - 1 );
			$dates['m_start'] 	= 1;
			$dates['m_end']		= 12;
			$dates['year']		= date( 'Y', $current_time ) - 1;
			$dates['year_end']  = date( 'Y', $current_time ) - 1;
		break;

	endswitch;

	/**
	 * Filters the dates array for the current reports graph.
	 *
	 * @since 1.0
	 *
	 * @param array $dates {
	 *     Date values used by the reports API.
	 *
	 *     @type int $day      Day of the month (1-31) to start filtering results by.
	 *     @type int $day_end  Day of the month (1-31) to end filtering results by.
	 *     @type int $m_start  Month of the year (1-12) to start filtering results by.
	 *     @type int $m_end    Month of the year (1-12) to end filtering results by.
	 *     @type int $year     Year to start filtering results by.
	 *     @type int $year_end Year to end filtering results by.
	 * }
	 */
	return apply_filters( 'affwp_report_dates', $dates );
}
