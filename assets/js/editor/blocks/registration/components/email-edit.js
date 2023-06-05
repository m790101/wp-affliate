/**
 * Affiliate registration form email field edit component.
 *
 * @since 2.8
 */

import { _x } from '@wordpress/i18n';
import AffiliateWPField from "./field";

import {
	InspectorControls,
} from '@wordpress/block-editor';

import {
	PanelBody,
	ToggleControl,
	TextControl,
	SelectControl,
} from '@wordpress/components';
import classnames from "classnames";

function EmailEdit( props ) {
	const {
		required,
		label,
		classNames,
		placeholder,
		type,
	} = props.attributes;

	const fieldClassNames = classnames(
		'affwp-field',
		'affwp-field-text'
	);

	return (
		<>

			<InspectorControls>

				<PanelBody
					title={_x( 'Field settings', 'Email field', 'affiliate-wp' )}
					initialOpen={true}
				>

					<ToggleControl
						label={_x( 'Required', 'Email field', 'affiliate-wp' )}
						className="affwp-field-label__required"
						checked={required}
						disabled={props.disableRequired || false}
						help={props.help || ''}
						onChange={( required ) => props.setAttributes( { required } )}
					/>

					<TextControl
						label={_x( 'Field Label', 'Email field', 'affiliate-wp' )}
						value={label}
						onChange={( label ) => props.setAttributes( { label } )}
					/>

					<TextControl
						label={_x( 'Field Placeholder', 'Email field', 'affiliate-wp' )}
						value={placeholder}
						onChange={( placeholder ) => props.setAttributes( { placeholder } )}
					/>

				</PanelBody>

			</InspectorControls>

			<AffiliateWPField
				label={label}
				type="email"
				required={required}
				setAttributes={props.setAttributes}
				isSelected={props.isSelected}
				name={props.name}
				classNames={classNames}
				fieldClassNames={fieldClassNames}
				placeholder={placeholder}
				context={props.context}
			/>
		</>
	);

}

export default EmailEdit;