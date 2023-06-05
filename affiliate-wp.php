<?php
/**
 * Plugin Name: AffiliateWP
 * Plugin URI: https://affiliatewp.com
 * Description: Affiliate Plugin for WordPress
 * Author: Sandhills Development, LLC
 * Author URI: https://sandhillsdev.com
 * Version: 2.8.3
 * Text Domain: affiliate-wp
 * Domain Path: languages
 * GitHub Plugin URI: affiliatewp/affiliatewp
 *
 * AffiliateWP is distributed under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * AffiliateWP is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AffiliateWP. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package AffiliateWP
 * @category Core
 * @author Pippin Williamson
 * @version 2.8.3
 */

if ( ! class_exists( 'AffiliateWP_Requirements_Check_v1_1' ) ) {
	require_once dirname( __FILE__ ) . '/includes/libraries/affwp/class-affiliatewp-requirements-check-v1-1.php';
}

/**
 * Class used to check requirements for and bootstrap AffiliateWP.
 *
 * @since 2.7
 *
 * @see Affiliate_WP_Requirements_Check
 */
class AffiliateWP_Core_Requirements_Check extends AffiliateWP_Requirements_Check_v1_1 {

	/**
	 * Plugin slug.
	 *
	 * @since 2.7
	 * @var   string
	 */
	protected $slug = 'affiliate-wp';

	/**
	 * Add-on requirements.
	 *
	 * @since 2.7
	 * @var   array[]
	 */
	protected $addon_requirements = array(
		// PHP.
		'php' => array(
			'minimum' => '5.6',
			'name'    => 'PHP',
			'exists'  => true,
			'current' => false,
			'checked' => false,
			'met'     => false,
		),
	);

	/**
	 * Bootstrap everything.
	 *
	 * @since 2.7
	 */
	public function bootstrap() {
		$instance = \Affiliate_WP::instance( __FILE__ );

		/**
		 * Fires once AffiliateWP has loaded.
		 *
		 * @since 2.7
		 *
		 * @param \Affiliate_WP $instance Affiliate_WP instance.
		 */
		do_action( 'affwp_plugins_loaded', $instance );
	}

	/**
	 * Loads the add-on.
	 *
	 * @since 2.7
	 */
	protected function load() {
		// Maybe include the bundled bootstrapper.
		if ( ! class_exists( 'Affiliate_WP' ) ) {
			require_once dirname( __FILE__ ) . '/includes/class-affiliate-wp.php';
		}

		// Maybe hook-in the bootstrapper.
		if ( class_exists( 'Affiliate_WP' ) ) {

			// Bootstrap to plugins_loaded before priority 10 to make sure
			// add-ons are loaded after us.
			add_action( 'plugins_loaded', array( $this, 'bootstrap' ), -1 );

			// Register the activation hook.
			register_activation_hook( __FILE__, array( $this, 'install' ) );
		}
	}

	/**
	 * Install, usually on an activation hook.
	 *
	 * @since 2.7
	 */
	public function install() {
		// Bootstrap to include all of the necessary files
		$this->bootstrap();

		affiliate_wp_install();
	}

	/**
	 * Plugin-specific aria label text to describe the requirements link.
	 *
	 * @since 2.7
	 *
	 * @return string Aria label text.
	 */
	protected function unmet_requirements_label() {
		return esc_html__( 'AffiliateWP Requirements', 'affiliate-wp' );
	}

	/**
	 * Plugin-specific text used in CSS to identify attribute IDs and classes.
	 *
	 * @since 2.7
	 *
	 * @return string CSS selector.
	 */
	protected function unmet_requirements_name() {
		return 'affiliate-wp-requirements';
	}

	/**
	 * Plugin specific URL for an external requirements page.
	 *
	 * @since 2.7
	 *
	 * @return string Unmet requirements URL.
	 */
	protected function unmet_requirements_url() {
		return 'https://docs.affiliatewp.com/article/2361-minimum-requirements-roadmaps';
	}

}

$requirements = new AffiliateWP_Core_Requirements_Check( __FILE__ );

$requirements->maybe_load();
