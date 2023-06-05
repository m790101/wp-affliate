/**
 * Affiliate registration form block.
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

const name = 'affiliatewp/registration';

const settings = {
	title: __( 'Affiliate Registration', 'affiliate-wp' ),
	description: __(
		'Allow your affiliates to register.',
		'affiliate-wp'
	),
	keywords: [
		__( 'Registration', 'affiliate-wp' ),
		__( 'Form', 'affiliate-wp' ),
		__( 'Register', 'affiliate-wp' ),
	],
	category: 'affiliatewp',
	icon,
	supports: {
		html: false,
		lightBlockWrapper: true
	},
	edit,
	save,
}
export { name, settings };