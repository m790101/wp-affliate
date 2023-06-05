/**
 * Affiliate Registration Form textarea Edit Component.
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
import { isRegistrationBlockChild, isCurrentRegistrationBlockChild } from '../../helpers';
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	PlainText,
} from '@wordpress/block-editor';

import {
	PanelBody,
	ToggleControl,
	TextControl,
} from '@wordpress/components';

import { useEffect, useState } from '@wordpress/element';

function AffiliateWPFieldTextArea({
	attributes,
	setAttributes,
	isSelected,
	name,
	context,
	clientId
}) {

	const {
		required,
		label,
		placeholder,
		type,
	} = attributes;

	const [promotionMethod, setPromotionMethod] = useState('promotionMethod' === type ? true : false);

	const fieldClassNames = classnames(
		'affwp-field',
		'affwp-field-textarea'
	);

	const blockProps = useBlockProps();
	const showPlaceholders = context['affiliatewp/placeholders'];

	useEffect(() => {
		setAttributes({ type: promotionMethod ? 'promotionMethod' : undefined })
	}, [promotionMethod])

	const disabled = !isCurrentRegistrationBlockChild('promotionMethod', clientId) && isRegistrationBlockChild('promotionMethod', clientId)

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Field settings', 'affiliate-wp' )}>
					<ToggleControl
						label={__('Required', 'affiliate-wp' )}
						className="affwp-field-label__required"
						checked={required}
						onChange={(boolean) => setAttributes({ required: boolean })}
					/>

					<ToggleControl
						label={__('Use as Promotion Method field', 'affiliate-wp' )}
						checked={promotionMethod}
						onChange={(boolean) => setPromotionMethod(boolean)}
						disabled={disabled}
					/>

					<TextControl
						label={__('Field Label', 'affiliate-wp' )}
						value={label}
						onChange={(label) => setAttributes({ label })}
					/>

					<TextControl
						label={__('Field Placeholder', 'affiliate-wp' )}
						value={placeholder}
						onChange={(placeholder) => setAttributes({ placeholder })}
					/>

				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
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

				<PlainText
					placeholder={showPlaceholders ? __('Add placeholder text…') : ''}
					className={fieldClassNames}
					value={placeholder}
					onChange={(placeholder) => setAttributes({ placeholder })}
					rows={5}
				/>
			</div>
		</>
	);
}
export default AffiliateWPFieldTextArea;
