<?php
/**
 * Tools: Migration Admin
 *
 * @package     AffiliateWP
 * @subpackage  Tools
 * @copyright   Copyright (c) 2014, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */

/**
 * The migration processing screen
 *
 * @since 1.0
 * @return void
 */
function affwp_migrate_admin() {
	$step  = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
	$type  = isset( $_GET['type'] ) ? $_GET['type'] : false;
	$part  = isset( $_GET['part'] ) ? $_GET['part'] : false;
	$roles = isset( $_GET['roles'] ) ? $_GET['roles'] : array();
	$roles = is_array( $roles ) ? implode( ',', $roles ) : '';
?>
	<div class="wrap">
		<h2><?php _e( 'AffiliateWP Migration', 'affiliate-wp' ); ?></h2>
		<div id="affwp-upgrade-status">
			<p><?php _e( 'The upgrade process is running, please be patient. This could take several minutes to complete while affiliate records are upgraded in batches of 100.', 'affiliate-wp' ); ?></p>
			<p>
				<strong>
					<?php
					/* translators: Step number */
					printf( __( 'Step %d running', 'affiliate-wp' ), $step );
					?>
				</strong>
			</p>
		</div>
		<script type="text/javascript">
			document.location.href = "index.php?affwp_action=migrate&step=<?php echo absint( $step ); ?>&type=<?php echo $type; ?>&part=<?php echo $part; ?><?php if ( 'users' === $type ) : ?>&roles=<?php echo $roles; ?><?php endif; ?>";
		</script>
	</div>
<?php
}
