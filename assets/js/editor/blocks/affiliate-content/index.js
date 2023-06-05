/**
 * Affiliate Content Block.
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
import { InnerBlocks } from '@wordpress/block-editor';

const name = 'affiliatewp/affiliate-content';

const settings = {
	title: __( 'Affiliate Content', 'affiliate-wp' ),
	description: __(
		'Restrict content to logged-in affiliates.',
		'affiliate-wp'
	),
	keywords: [
		__( 'Content', 'affiliate-wp' ),
		__( 'Restrict', 'affiliate-wp' ),
	],
	category: 'affiliatewp',
	icon,
	supports: {
		html: false,
	},
	edit,
	save( { className } ) {
		return (
			<div className={ className }>
				<InnerBlocks.Content />
			</div>
		);
	}
}
export { name, settings };