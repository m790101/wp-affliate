/**
 * Affiliate Referral URL Block.
 *
 * @since 2.8
 */

/**
 * Internal Dependencies
 */
import icon from '../../components/icon';
import edit from './edit';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';


const name = 'affiliatewp/affiliate-referral-url';

const settings = {
	title: __( 'Affiliate Referral URL', 'affiliate-wp' ),
	description: __(
		'Display the referral URL of the currently logged in affiliate.',
		'affiliate-wp'
	),
	keywords: [
		__( 'URL', 'affiliate-wp' ),
		__( 'Referral', 'affiliate-wp' ),
	],
	category: 'affiliatewp',
	icon,
	supports: {
		html: false,
	},
	edit,
	save() {
		return null;
	},
}
export { name, settings };