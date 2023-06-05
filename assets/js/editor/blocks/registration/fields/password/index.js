/**
 * Affiliate registration password field Block.
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
			<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" fill="none" />
		</svg>
	}
/>

const name = 'affiliatewp/field-password';

const settings = {
	/* translators: block name */
	title: __('Password', 'affiliate-wp' ),
	category: 'affiliatewp',
	parent: ['affiliatewp/registration'],
	icon,
	attributes: {
		label: {
			type: 'string',
			default: __('Password', 'affiliate-wp' )
		},
		labelConfirm: {
			type: 'string',
			default: __('Confirm Password', 'affiliate-wp' )
		},
		placeholder: {
			type: 'string',
		},
		placeholderConfirm: {
			type: 'string',
		},
	},
	/* translators: block description */
	description: __('A field for collecting the affiliate\'s desired password.', 'affiliate-wp' ),
	keywords: [
		'affiliatewp',
		/* translators: block keyword */
		__('password', 'affiliate-wp' )
	],
	supports: {
		html: false,
		multiple: false,
		lightBlockWrapper: true,
	},
	edit,
	save: () => null,
};

export { name, settings };
