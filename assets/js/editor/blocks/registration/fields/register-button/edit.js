/**
 * Affiliate Registration Form Email register button Edit Component.
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
	RichText,
	useBlockProps,
	InspectorControls
} from '@wordpress/block-editor';

import {
	PanelBody,
	TextControl,
} from '@wordpress/components';

function AffiliateWPFieldSubmitButton({
	attributes,
	setAttributes,
	isSelected,
	name,
	clientId,
}) {

	const { text } = attributes;
	const blockProps = useBlockProps();

	const classes = classnames(
		'affwp-button-register'
	);

	return (
		<>
			<InspectorControls>

				<PanelBody
					title={__('Button Settings', 'affiliate-wp' )}
					initialOpen={true}
				>

					<TextControl
						label={__('Button Text', 'affiliate-wp' )}
						value={text}
						onChange={(text) => setAttributes({ text })}
					/>

				</PanelBody>

			</InspectorControls>

			<div {...blockProps}>
				<RichText
					identifier="text"
					placeholder={__('Add button text…', 'affiliate-wp' )}
					value={text}
					onChange={(text) => setAttributes({ text })}
					withoutInteractiveFormatting
					keepPlaceholderOnFocus
					allowedFormats={[]}
					className={classes}
				/>
			</div>
		</>
	);
}
export default AffiliateWPFieldSubmitButton;