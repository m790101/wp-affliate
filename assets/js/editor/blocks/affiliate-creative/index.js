/**
 * Affiliate Creative Block.
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


const name = 'affiliatewp/affiliate-creative';

const settings = {
	title: __( 'Affiliate Creative', 'affiliate-wp' ),
	description: __(
		'Show an affiliate creative.',
		'affiliate-wp'
	),
	keywords: [
		__( 'Creative', 'affiliate-wp' ),
		__( 'Banner', 'affiliate-wp' ),
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