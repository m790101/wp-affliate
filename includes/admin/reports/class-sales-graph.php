<?php
/**
 * Admin: Sales Graph for Reports
 *
 * @package     AffiliateWP
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.5
 */

/**
 * Implements logic to display a graph depicting sales data over time.
 *
 * @since 2.5
 *
 * @see \Affiliate_WP_Graph
 */
class Affiliate_WP_Sales_Graph extends Affiliate_WP_Graph {

	/**
	 * Runs during instantiation of the affiliate registrations graph.
	 *
	 * @since 2.5
	 *
	 * @param array $_data Data for initializing the graph instance.
	 */
	public function __construct( $_data = array() ) {
		parent::__construct( $_data );

		$this->options['form_wrapper'] = false;
	}

	/**
	 * Retrieves net revenue data to use in the graph.
	 *
	 * @since 2.5
	 *
	 * @param array $commission_data The commission data.
	 * @param array $revenue_data    The revenue data.
	 * @return array Net revenue graph data.
	 */
	private function get_net_revenue_data( $commission_data, $revenue_data ) {

		$dates  = affwp_get_report_dates();
		$start  = $dates['year'] . '-' . $dates['m_start'] . '-' . $dates['day'];
		$end    = $dates['year_end'] . '-' . $dates['m_end'] . '-' . $dates['day_end'];
		$totals = array();

		$data = array(
			array( strtotime( $start ) * 1000 ),
			array( strtotime( $end ) * 1000 ),
		);

		if ( $revenue_data ) {

			$difference = ( strtotime( $end ) - strtotime( $start ) );

			foreach ( $revenue_data as $item ) {
				if ( in_array( $dates['range'], array( 'this_year', 'last_year' ), true )
						 || $difference >= YEAR_IN_SECONDS
				) {
					$date = date( 'Y-m', strtotime( $item->date ) );
				} else {
					$date = date( 'Y-m-d', strtotime( $item->date ) );
				}

				if ( empty( $totals[ $date ] ) ) {
					$totals[ $date ] = $item->order_total;
				} else {
					$totals[ $date ] += $item->order_total;
				}
			}
		}

		if ( $commission_data ) {

			$difference = ( strtotime( $end ) - strtotime( $start ) );

			foreach ( $commission_data as $item ) {
				if ( in_array( $dates['range'], array( 'this_year', 'last_year' ), true )
						 || $difference >= YEAR_IN_SECONDS
				) {
					$date = date( 'Y-m', strtotime( $item->date ) );
				} else {
					$date = date( 'Y-m-d', strtotime( $item->date ) );
				}

				if ( ! empty( $totals[ $date ] ) ) {
					$totals[ $date ] -= $item->amount;
				}
			}

		}

		foreach ( $totals as $date => $amount ) {
			$data[] = array( strtotime( $date ) * 1000, $amount );
		}

		return $data;
	}

	/**
	 * Parses raw data so that the graph system can use it.
	 *
	 * @since 2.5
	 *
	 * @param array  $raw_data Raw data to parse.
	 * @param string $field    Sales field to tie calculated data to.
	 * @return array Parsed graph data.
	 */
	private function parse_graph_data( $raw_data, $field ) {
		$dates  = affwp_get_report_dates();
		$start  = $dates['year'] . '-' . $dates['m_start'] . '-' . $dates['day'];
		$end    = $dates['year_end'] . '-' . $dates['m_end'] . '-' . $dates['day_end'];
		$totals = array();

		$data = array(
			array( strtotime( $start ) * 1000 ),
			array( strtotime( $end ) * 1000 ),
		);

		if ( $raw_data ) {

			$difference = ( strtotime( $end ) - strtotime( $start ) );

			foreach ( $raw_data as $item ) {
				if ( in_array( $dates['range'], array( 'this_year', 'last_year' ), true )
					 || $difference >= YEAR_IN_SECONDS
				) {
					$date = date( 'Y-m', strtotime( $item->date ) );
				} else {
					$date = date( 'Y-m-d', strtotime( $item->date ) );
				}

				if ( empty( $totals[ $date ] ) ) {
					$totals[ $date ] = $item->$field;
				} else {
					$totals[ $date ] += $item->$field;
				}
			}

				foreach ( $totals as $date => $amount ) {
					$data[] = array( strtotime( $date ) * 1000, $amount );
				}
		}

		return $data;
	}

	/**
	 * Retrieves payouts and earnings data.
	 *
	 * @since 2.5
	 *
	 * @return array Data to display in the graph.
	 */
	public function get_data() {
		$dates = affwp_get_report_dates();
		$start = $dates['year'] . '-' . $dates['m_start'] . '-' . $dates['day'];
		$end   = $dates['year_end'] . '-' . $dates['m_end'] . '-' . $dates['day_end'];

		$date = array(
			'start' => $start,
			'end'   => $end,
		);

		/**
		 * Filters the arguments used in the sales graph query.
		 *
		 * @since 2.5
		 *
		 * @param array $args The list of arguments to use in the get_sales query.
		 */
		$gross_revenue_args = apply_filters( 'affwp_reports_sales_graph_revenue_query_args', array(
			'status'  => array( 'paid', 'unpaid' ),
			'orderby' => 'date',
			'order'   => 'ASC',
			'date'    => $date,
			'number'  => -1,
			'fields'  => array( 'date', 'order_total' ),
		) );

		/**
		 * Filters the arguments used in the commission graph query.
		 *
		 * @since 2.5
		 *
		 * @param array $args The list of arguments to use in the get_referrals query.
		 */
		$commission_args = apply_filters( 'affwp_reports_sales_graph_commission_query_args',  array(
			'status'  => array( 'paid', 'unpaid' ),
			'orderby' => 'date',
			'order'   => 'ASC',
			'date'    => $date,
			'number'  => -1,
			'fields'  => array( 'amount', 'date' ),
		) );

		$gross_revenue_data = affiliate_wp()->referrals->sales->get_sales( $gross_revenue_args );
		$commission_data    = affiliate_wp()->referrals->get_referrals( $commission_args );

		$data = array(
			__( 'Gross Affiliate-generated Revenue', 'affiliate-wp' ) => $this->parse_graph_data( $gross_revenue_data, 'order_total' ),
			__( 'Total Affiliate Sales Earnings', 'affiliate-wp' )    => $this->parse_graph_data( $commission_data, 'amount' ),
			__( 'Net Affiliate-generated Revenue', 'affiliate-wp' )   => $this->get_net_revenue_data( $commission_data, $gross_revenue_data ),
		);

		return $data;
	}

}