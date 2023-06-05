/**
 * Affiliate Creatives URL Block.
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

const name = 'affiliatewp/affiliate-creatives';

const settings = {
	title: __( 'Affiliate Creatives', 'affiliate-wp' ),
	description: __(
		'Show creatives to your affiliates.',
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