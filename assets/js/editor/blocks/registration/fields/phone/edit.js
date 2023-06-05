/**
 * Affiliate Registration Form phone field Edit Component.
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
import AffiliateWPField from '../../components/field';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
} from '@wordpress/block-editor';

import {
	PanelBody,
	ToggleControl,
	TextControl,
} from '@wordpress/components';

function AffiliateWPFieldPhone({
	attributes,
	setAttributes,
	isSelected,
	name,
	context,
	clientId,
}) {

	const {
		required,
		label,
		classNames,
		placeholder,
	} = attributes;

	const fieldClassNames = classnames(
		'affwp-field',
		'affwp-field-phone'
	);

	return (
		<>
			<InspectorControls>

				<PanelBody
					title={__('Field settings', 'affiliate-wp' )}
					initialOpen={true}
				>

					<ToggleControl
						label={__('Required', 'affiliate-wp' )}
						className="affwp-field-label__required"
						checked={required}
						onChange={(required) => setAttributes({ required })}
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

			<AffiliateWPField
				label={label}
				type="tel"
				required={required}
				setAttributes={setAttributes}
				isSelected={isSelected}
				name={name}
				classNames={classNames}
				fieldClassNames={fieldClassNames}
				placeholder={placeholder}
				context={context}
			/>
		</>
	);
}
export default AffiliateWPFieldPhone;
