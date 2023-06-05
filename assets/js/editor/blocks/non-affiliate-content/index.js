/**
 * Non-Affiliate Content Block.
 *
 * @since 2.8
 */

/**
 * Internal dependencies
 */
import icon from '../../components/icon';
import edit from './edit';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { InnerBlocks } from '@wordpress/block-editor';

const name = 'affiliatewp/non-affiliate-content';

const settings = {
	title: __( 'Non Affiliate Content', 'affiliate-wp' ),
	description: __(
		'Show content to non affiliates.',
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