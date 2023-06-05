/**
 * Registration Edit Component.
 *
 * @since 2.8
 */

/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

import {
	InspectorControls,
	useBlockProps,
	RichText,
	__experimentalUseInnerBlocksProps as useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';

import {
	PanelBody,
	TextControl,
	ToggleControl,
	Notice,
} from '@wordpress/components';

import { useEffect } from '@wordpress/element';

import { useSelect } from '@wordpress/data';
import { name } from "./fields/username";

const ALLOWED_BLOCKS = [
	'affiliatewp/field-email',
	'affiliatewp/field-text',
	'affiliatewp/field-textarea',
	'affiliatewp/field-website',
	'affiliatewp/field-checkbox',
	'affiliatewp/field-password',
	'affiliatewp/field-phone',
	'affiliatewp/field-register-button',
];

const hasTermsOfUse = affwp_blocks.terms_of_use;
const termsOfUseLink = affwp_blocks.terms_of_use_link;

let template = [
	['affiliatewp/field-name', { label: __( 'Your Name', 'affiliate-wp' ), type: 'name' }],
	['affiliatewp/field-username', { label: __( 'Username', 'affiliate-wp' ), required: true, type: 'username' }],
	['affiliatewp/field-account-email', { label: __( 'Account Email', 'affiliate-wp' ), required: true, type: 'account' }],
	['affiliatewp/field-payment-email', { label: __( 'Payment Email', 'affiliate-wp' ), type: 'payment' }],
	['affiliatewp/field-website', { label: __( 'Website URL', 'affiliate-wp' ), type: 'websiteUrl' }],
	['affiliatewp/field-textarea', {
		label: __( 'How will you promote us?', 'affiliate-wp' ),
		type: 'promotionMethod'
	}],
];

if ( hasTermsOfUse ) {

	template.push(
		['affiliatewp/field-checkbox', {
			label: `Agree to our <a href="${termsOfUseLink}" target="_blank">Terms of Use and Privacy Policy</a>`,
			required: true,
			type: 'agreeToTerms'
		}],
	)
}

template.push(
	['affiliatewp/field-register-button'],
)

/**
 * Affiliate Registration.
 *
 * Affiliate registration edit block.
 *
 * @since 2.8
 *
 * @param {object}   attributes    Block attributes.
 * @param {function} setAttributes Method used to set the attributes for this component in the global scope.
 * @returns {JSX.Element}          The rendered component.
 */
function AffiliateRegistration( { name, attributes, setAttributes, isSelected, context, clientId, } ) {

	const { redirect, placeholders, legend } = attributes;
	const allowAffiliateRegistration = affwp_blocks.allow_affiliate_registration;

	const classes = classnames(
		'affwp-form'
	);

	const blockProps = useBlockProps( {
		className: classes,
	} );

	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		template,
		allowedBlocks: ALLOWED_BLOCKS,
	} );


	const isStandaloneForm = useSelect(
		( select ) => {
			const { getBlock, getBlockRootClientId } = select( blockEditorStore )
			const parentBlock = getBlock( getBlockRootClientId( clientId ) )
			return 'affiliatewp/affiliate-area' !== parentBlock?.name
		},
		[clientId]
	)

	// Clear any redirect if registration block is within Affiliate Area block.
	useEffect( () => {
		if ( false === isStandaloneForm ) {
			setAttributes( { redirect: undefined } )
		}
	}, [clientId] )

	return (
		<>
			<InspectorControls>

				<PanelBody
					title={__( 'General', 'affiliate-wp' )}
				>

					<TextControl
						label={__( 'Form Title', 'affiliate-wp' )}
						value={legend || __( 'Register a new affiliate account', 'affiliate-wp' )}
						onChange={( legend ) => setAttributes( { legend } )}
					/>

					<ToggleControl
						label={__( 'Show Placeholder Text', 'affiliate-wp' )}
						checked={placeholders}
						onChange={( boolean ) => setAttributes( { placeholders: boolean } )}
					/>

				</PanelBody>

				{!allowAffiliateRegistration &&
				<Notice
					className={"affwp-block-inspector-notice"}
					isDismissible={false}
					status="warning"
				>
					{__( 'Affiliates will not see this form as "Allow Affiliate Registration" is disabled.', 'affiliate-wp' )}
				</Notice>
				}

				{/* Only show Redirect control for a standalone registration form */}
				{true === isStandaloneForm &&
				<PanelBody>
					<TextControl
						label={__( 'Redirect' )}
						value={redirect}
						onChange={( redirect ) => setAttributes( { redirect } )}
					/>
				</PanelBody>
				}

			</InspectorControls>

			<div {...blockProps}>

				<RichText
					identifier={'legend'}
					tagName="h3"
					value={legend || __( 'Register a new affiliate account', 'affiliate-wp' )}
					onChange={( legend ) => {
						setAttributes( { legend } )
					}}
					allowedFormats={[]}
				/>

				<div {...innerBlocksProps} />

			</div>


		</>
	);
}

export default AffiliateRegistration;