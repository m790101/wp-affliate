/**
 * Affiliate registration register button Block.
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
			<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" fill="none" />
		</svg>
	}
/>

/**
 * Block constants
 */

const name = 'affiliatewp/field-register-button';

const settings = {
	category: 'affiliatewp',
	icon,
	attributes: {
		placeholder: {
			type: 'string',
		},
		text: {
			type: 'string',
			default: __('Register', 'affiliate-wp' )
		},
	},
	/* translators: block name */
	title: __('Register Button', 'affiliate-wp' ),
	/* translators: block description */
	description: __('A button for submitting the affiliate registration.', 'affiliate-wp' ),
	keywords: [
		'affiliatewp',
		/* translators: block keyword */
		__('submit', 'affiliate-wp' ),
		/* translators: block keyword */
		__('button', 'affiliate-wp' ),
		/* translators: block keyword */
		__('register', 'affiliate-wp' )
	],
	parent: ['affiliatewp/registration'],
	supports: {
		reusable: false,
		html: false,
		multiple: false,
		lightBlockWrapper: true
	},
	edit,
	save: () => null,
};
export { name, settings };