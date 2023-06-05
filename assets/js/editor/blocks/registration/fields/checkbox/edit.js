/**
 * Affiliate Registration Form Checkbox Edit Component.
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
import { _x } from '@wordpress/i18n';
import {
	InspectorControls,
	useBlockProps,
	RichText,
} from '@wordpress/block-editor';

import {
	PanelBody,
	ToggleControl,
	TextControl,
} from '@wordpress/components';

function AffiliateWPFieldCheckbox( { attributes, setAttributes, isSelected, resetFocus, name, context, clientId, } ) {

	const {
		required,
		label,
	} = attributes;

	const blockProps = useBlockProps();

	const fieldClassNames = classnames(
		'affwp-field',
		'affwp-field-checkbox'
	);

	return (
		<>
			<InspectorControls>

				<PanelBody
					title={_x( 'Field settings', 'Checkbox field', 'affiliate-wp' )}
					initialOpen={true}
				>

					<ToggleControl
						label={_x( 'Required', 'Checkbox field', 'affiliate-wp' )}
						className="affwp-field-label__required"
						checked={required}
						onChange={( required ) => setAttributes( { required } )}
					/>

					<TextControl
						label={_x( 'Field Label', 'Checkbox field', 'affiliate-wp' )}
						value={label}
						onChange={( label ) => setAttributes( { label } )}
					/>

				</PanelBody>

			</InspectorControls>

			<div {...blockProps}>
				<input
					className={fieldClassNames}
					type="checkbox"
				/>

				<RichText
					identifier={'label'}
					tagName="label"
					value={label}
					onChange={( label ) => {
						if ( resetFocus ) {
							resetFocus();
						}
						setAttributes( { label } );
					}}
					placeholder={_x( 'Add label ...', 'Checkbox field', 'affiliate-wp' )}
				/>

			</div>

		</>
	);
}

export default AffiliateWPFieldCheckbox;
