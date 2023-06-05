/**
 * Opt-In Form Block.
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

const name = 'affiliatewp/opt-in';

const settings = {
	title: __( 'Opt-in Form', 'affiliate-wp' ),
	description: __(
		'Show an opt-in form that integrates with Mailchimp, ActiveCampaign, or ConvertKit.',
		'affiliate-wp'
	),
	keywords: [
		__( 'Opt-in', 'affiliate-wp' ),
		__( 'Form', 'affiliate-wp' ),
		__( 'Sign Up', 'affiliate-wp' ),
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