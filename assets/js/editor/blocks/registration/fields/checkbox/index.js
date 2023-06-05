/**
 * Affiliate registration checkbox field Block.
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
			<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" fill="none" />
		</svg>
	}
/>

const name = 'affiliatewp/field-checkbox';

const settings = {
	/* translators: block name */
	title: __('Checkbox', 'affiliate-wp' ),
	category: 'affiliatewp',
	parent: ['affiliatewp/registration'],
	icon,
	attributes: {
		label: {
			type: 'string',
			default: __('Option', 'affiliate-wp' )
		},
		required: {
			type: 'boolean',
			default: false,
		},
	},
	/* translators: block description */
	description: __('Add a single checkbox.', 'affiliate-wp' ),
	keywords: [
		'affiliatewp',
		/* translators: block keyword */
		__('checkbox', 'affiliate-wp' )
	],
	supports: {
		html: false,
		lightBlockWrapper: true,
	},
	edit,
	save: () => null,
};

export { name, settings };
