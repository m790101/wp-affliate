/**
 * Affiliate Login Edit Component.
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
	PanelBody,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import {
	InspectorControls,
	RichText,
	store as blockEditorStore,
} from '@wordpress/block-editor';

import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Affiliate Login.
 *
 * Affiliate Login Form Component.
 *
 * @since 2.8
 *
 * @param {object}   attributes    Block attributes.
 * @param {function} setAttributes Method used to set the attributes for this component in the global scope.
 * @returns {JSX.Element}          The rendered component.
 */
function AffiliateLogin( { attributes, setAttributes, clientId } ) {

	const {
		redirect,
		legend,
		label,
		buttonText,
		placeholder,
		placeholders,
	} = attributes;

	const classes = classnames(
		'affwp-button-login'
	);

	const checkboxClasses = classnames(
		'affwp-field',
		'affwp-field-checkbox'
	);

	const isStandaloneForm = useSelect(
		( select ) => {
			const { getBlock, getBlockRootClientId } = select( blockEditorStore )
			const parentBlock = getBlock( getBlockRootClientId( clientId ) )
			return 'affiliatewp/affiliate-area' !== parentBlock?.name
		},
		[clientId]
	)

	// Clear any redirect if login block is within Affiliate Area block.
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
						label={__( 'Redirect', 'affiliate-wp' )}
						value={redirect}
						onChange={( redirect ) => setAttributes( { redirect } )}
					/>

					<TextControl
						label={__( 'Form Title', 'affiliate-wp' )}
						value={legend || __( 'Log into your account', 'affiliate-wp' )}
						onChange={( legend ) => setAttributes( { legend } )}
					/>

					<TextControl
						label={__( 'Button Text', 'affiliate-wp' )}
						value={buttonText}
						onChange={( buttonText ) => setAttributes( { buttonText } )}
					/>

					<ToggleControl
						label={__( 'Show Placeholder Text', 'affiliate-wp' )}
						checked={placeholders}
						onChange={( boolean ) => setAttributes( { placeholders: boolean } )}
					/>
				</PanelBody>

				<PanelBody
					title={__( 'Field Labels', 'affiliate-wp' )}
					initialOpen={false}
				>

					<TextControl
						label={__( 'Username', 'affiliate-wp' )}
						value={label?.username}
						onChange={( value ) => {
							setAttributes( { label: { ...label, username: value } } )
						}}
					/>

					<TextControl
						label={__( 'Password', 'affiliate-wp' )}
						value={label?.password}
						onChange={( value ) => {
							setAttributes( { label: { ...label, password: value } } )
						}}
					/>

					<TextControl
						label={__( 'Remember Text', 'affiliate-wp' )}
						value={label?.userRemember}
						onChange={( value ) => {
							setAttributes( { label: { ...label, userRemember: value } } )
						}}
					/>

				</PanelBody>

				{placeholders &&
				<PanelBody
					title={__( 'Field Placeholders', 'affiliate-wp' )}
					initialOpen={false}
				>

					<TextControl
						label={__( 'Username', 'affiliate-wp' )}
						value={placeholder?.username}
						onChange={( value ) => {
							setAttributes( { placeholder: { ...placeholder, username: value } } )
						}}
					/>

					<TextControl
						label={__( 'Password', 'affiliate-wp' )}
						value={placeholder?.password}
						onChange={( value ) => {
							setAttributes( { placeholder: { ...placeholder, password: value } } )
						}}
					/>

				</PanelBody>
				}

			</InspectorControls>

			<div id="affwp-login-form" className="affwp-form">

				<RichText
					identifier={'legend'}
					tagName="h3"
					value={legend || __( 'Log into your account', 'affiliate-wp' )}
					onChange={( legend ) => {
						setAttributes( { legend } )
					}}
					allowedFormats={[]}
				/>

				<div className={'wp-block block-editor-block-list__block'}>
					<div className="affwp-field-label">
						<RichText
							identifier={'labelUsername'}
							tagName="label"
							value={label?.username}
							onChange={( value ) => {
								setAttributes( { label: { ...label, username: value } } )
							}}
							placeholder={__( 'Add label…', 'affiliate-wp' )}
							allowedFormats={[]}
						/>
					</div>

					<RichText
						identifier="fieldUsername"
						placeholder={placeholders ? __( 'Add placeholder text…', 'affiliate-wp' ) : ''}
						value={placeholders ? placeholder?.username : ''}
						onChange={( value ) => {
							setAttributes( { placeholder: { ...placeholder, username: value } } )
						}}
						allowedFormats={[]}
						type={'text'}
						className={'affwp-field affwp-field-text'}
					/>
				</div>

				<div className={'wp-block block-editor-block-list__block'}>
					<div className="affwp-field-label">
						<RichText
							identifier={'labelPassword'}
							tagName="label"
							value={label?.password}
							onChange={( value ) => {
								setAttributes( { label: { ...label, password: value } } )
							}}
							placeholder={__( 'Add label…', 'affiliate-wp' )}
							allowedFormats={[]}
						/>

					</div>

					<RichText
						identifier="fieldPassword"
						placeholder={placeholders ? __( 'Add placeholder text…', 'affiliate-wp' ) : ''}
						value={placeholders ? placeholder?.password : ''}
						onChange={( value ) => {
							setAttributes( { placeholder: { ...placeholder, password: value } } )
						}}
						allowedFormats={[]}
						type={'text'}
						className={'affwp-field affwp-field-text'}
					/>
				</div>

				<div className={'wp-block block-editor-block-list__block'}>
					<input
						className={checkboxClasses}
						type="checkbox"
					/>

					<RichText
						identifier={'labelUserRemember'}
						tagName="label"
						value={label?.userRemember}
						onChange={( value ) => {
							setAttributes( { label: { ...label, userRemember: value } } )
						}}
						placeholder={__( 'Add label…', 'affiliate-wp' )}
					/>
				</div>

				<div className={'wp-block block-editor-block-list__block'}>
					<RichText
						identifier="loginButton"
						placeholder={__( 'Add button text…', 'affiliate-wp' )}
						value={buttonText}
						onChange={( buttonText ) => setAttributes( { buttonText } )}
						withoutInteractiveFormatting
						keepPlaceholderOnFocus
						allowedFormats={[]}
						className={classes}
					/>
				</div>

				<p className="affwp-lost-password">
					<a>{__( 'Lost your password?', 'affiliate-wp' )}</a>
				</p>

			</div>

		</>
	);
}

export default AffiliateLogin;