/**
 * Affiliate Area Block.
 *
 * @since 2.8
 */


/**
 * Internal Dependencies
 */
import icon from '../../components/icon';
import edit from './edit';
import save from './save';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

const name = 'affiliatewp/affiliate-area';

const settings = {
	title: __( 'Affiliate Area', 'affiliate-wp' ),
	description: __(
		'Displays the affiliate registration and login forms to a logged out user. A logged-in user will see the Affiliate Area instead of these forms.',
		'affiliate-wp'
	),
	keywords: [
		__( 'Affiliate Area', 'affiliate-wp' ),
		__( 'Area', 'affiliate-wp' ),
		__( 'Dashboard', 'affiliate-wp' )
	],
	category: 'affiliatewp',
	icon,
	supports: {
		html: false,
	},
	edit,
	save,
}
export { name, settings };