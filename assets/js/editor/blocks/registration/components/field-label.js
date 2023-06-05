/**
 * Affiliate registration field label component
 *
 * @since 2.8
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { RichText } from '@wordpress/block-editor';

const AffiliateWPFieldLabel = ({
	identifier,
	setAttributes,
	label,
	labelAttribute,
	resetFocus,
	isSelected,
	required,
	requiredAttribute,
	name,
	showRequiredToggle = true,
}) => {

	return (
		<div className="affwp-field-label">
			<RichText
				identifier={identifier}
				tagName="label"
				value={label}
				onChange={(value) => {
					if (resetFocus) {
						resetFocus();
					}
					setAttributes({ [labelAttribute]: value });
				}}
				placeholder={__('Add label…', 'affiliate-wp' )}
				allowedFormats={[]}
			/>

			{ required && (
				<span className="required">{ __( '(required)', 'affiliate-wp' ) }</span>
			) }

		</div>
	);
};

export default AffiliateWPFieldLabel;
