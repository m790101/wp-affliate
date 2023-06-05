/**
 * Affiliate registration website field Block.
 *
 * @since 2.8
 */

/**
 * Internal dependencies
 */
import edit from './edit';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon } from '@wordpress/components';

const icon = <Icon
	icon={
		<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
			<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" fill="none" />
		</svg>
	}
/>

const name = 'affiliatewp/field-website';

const settings = {
	/* translators: block name */
	title: __('Website', 'affiliate-wp' ),
	category: 'affiliatewp',
	parent: ['affiliatewp/registration'],
	icon,
	attributes: {
		label: {
			type: 'string',
			default: __('Website', 'affiliate-wp' )
		},
		required: {
			type: 'boolean',
			default: false,
		},
		placeholder: {
			type: 'string',
		},
		type: {
			type: 'string',
		},
	},
	/* translators: block description */
	description: __('A field for collecting a website URL.', 'affiliate-wp' ),
	keywords: [
		'affiliatewp',
		/* translators: block keyword */
		__('url', 'affiliate-wp' ),
		__('website', 'affiliate-wp' ),
		__('link', 'affiliate-wp' )
	],
	supports: {
		html: false,
		lightBlockWrapper: true,
	},
	edit,
	save: () => null,
};

export { name, settings };
