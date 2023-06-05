/**
 * Affiliate Login Block.
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

const name = 'affiliatewp/login';

const settings = {
	title: __( 'Affiliate Login', 'affiliate-wp' ),
	description: __(
		'Allow your affiliates to login.',
		'affiliate-wp'
	),
	keywords: [
		__( 'Login', 'affiliate-wp' ),
		__( 'Form', 'affiliate-wp' ),
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