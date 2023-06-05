/**
 * Affiliate registration payment email field Block.
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
		<svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
			<path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
						d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"
						fill="none"/>
		</svg>
	}
/>

const name = 'affiliatewp/field-payment-email';

const settings = {
	/* translators: block name */
	title: __( 'Payment Email', 'affiliate-wp' ),
	category: 'affiliatewp',
	parent: ['affiliatewp/registration'],
	icon,
	attributes: {
		label: {
			type: 'string',
			default: __( 'Email Address', 'affiliate-wp' )
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
	description: __( 'A field for collecting a valid payment email address.', 'affiliate-wp' ),
	keywords: [
		'affiliatewp',
		/* translators: block keyword */
		__( 'e-mail', 'affiliate-wp' ),
		/* translators: block keyword */
		__( 'mail', 'affiliate-wp' ),
		/* translators: block keyword */
		__( 'payment', 'affiliate-wp' )
	],
	supports: {
		html: false,
		lightBlockWrapper: true,
	},
	edit,
	save: () => null,
};

export { name, settings };
