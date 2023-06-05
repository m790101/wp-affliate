/**
 * Affiliate Registration Form password field Edit Component.
 *
 * @since 2.8
 */

/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import AffiliateWPFieldLabel from '../../components/field-label';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	RichText,
} from '@wordpress/block-editor';

import {
	Notice,
	PanelBody,
	TextControl,
} from '@wordpress/components';

function AffiliateWPFieldPassword({
	attributes,
	setAttributes,
	isSelected,
	name,
	context,
}) {

	const {
		required,
		label,
		labelConfirm,
		placeholder,
		placeholderConfirm,
	} = attributes;

	const blockProps = useBlockProps();

	const fieldClassNames = classnames(
		'affwp-field',
		'affwp-field-password'
	);

	const showPlaceholders = context['affiliatewp/placeholders'];

	return (
		<>
			<InspectorControls>
				<Notice
					className={"affwp-block-inspector-notice"}
					isDismissible={false}
					status="warning"
				>
					{__('The Password fields will only show on the Affiliate Registration form to logged out users.', 'affiliate-wp' )}
				</Notice>

				<PanelBody
					title={__('Field settings', 'affiliate-wp' )}
					initialOpen={true}
				>

					<TextControl
						label={__('Password Field Label', 'affiliate-wp' )}
						value={label}
						onChange={(label) => setAttributes({ label })}
					/>

					<TextControl
						label={__('Password Field Placeholder', 'affiliate-wp' )}
						value={placeholder}
						onChange={(placeholder) => setAttributes({ placeholder })}
					/>

					<TextControl
						label={__('Password Confirm Field Label', 'affiliate-wp' )}
						value={labelConfirm}
						onChange={(labelConfirm) => setAttributes({ labelConfirm })}
					/>

					<TextControl
						label={__('Password Confirm Field Placeholder', 'affiliate-wp' )}
						value={placeholderConfirm}
						onChange={(placeholderConfirm) => setAttributes({ placeholderConfirm })}
					/>

				</PanelBody>

			</InspectorControls>

			<div {...blockProps}>
				<div style={{ marginBottom: 28 }}>
					<AffiliateWPFieldLabel
						identifier="label"
						required={required}
						requiredAttribute={'required'}
						label={label}
						labelAttribute={'label'}
						setAttributes={setAttributes}
						isSelected={isSelected}
						name={name}
						context={context}
					/>

					<RichText
						identifier="placeholder"
						placeholder={showPlaceholders ? __('Add placeholder text…') : ''}
						value={placeholder}
						onChange={(placeholder) => setAttributes({ placeholder })}
						allowedFormats={[]}
						type={'text'}
						className={fieldClassNames}
					/>
				</div>

				<div>
					<AffiliateWPFieldLabel
						identifier="labelConfirm"
						required={required}
						requiredAttribute={'required'}
						label={labelConfirm}
						labelAttribute={'labelConfirm'}
						setAttributes={setAttributes}
						isSelected={isSelected}
						name={name}
						context={context}
					/>

					<RichText
						identifier="placeholderConfirm"
						placeholder={showPlaceholders ? __('Add placeholder text…') : ''}
						value={placeholderConfirm}
						onChange={(placeholderConfirm) => setAttributes({ placeholderConfirm })}
						allowedFormats={[]}
						type={'text'}
						className={fieldClassNames}
					/>
				</div>

			</div>
		</>
	);
}

export default AffiliateWPFieldPassword;
