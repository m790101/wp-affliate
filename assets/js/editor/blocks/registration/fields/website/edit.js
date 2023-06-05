/**
 * Affiliate Registration Form website field Edit Component.
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
import { isRegistrationBlockChild, isCurrentRegistrationBlockChild } from '../../helpers';

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

import { useEffect, useState } from '@wordpress/element';

function AffiliateWPFieldWebsite({
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
		placeholder,
		type,
	} = attributes;

	const [websiteUrl, setWebsiteUrl] = useState('websiteUrl' === type ? true : false);

	const fieldClassNames = classnames(
		'affwp-field',
		'affwp-field-website'
	);

	useEffect(() => {
		setAttributes({ type: websiteUrl ? 'websiteUrl' : undefined })
	}, [websiteUrl])

	const disabled = !isCurrentRegistrationBlockChild('websiteUrl', clientId) && isRegistrationBlockChild('websiteUrl', clientId)

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

					<ToggleControl
						label={__('Save to affiliate\'s user profile', 'affiliate-wp' )}
						checked={websiteUrl}
						onChange={(boolean) => setWebsiteUrl(boolean)}
						disabled={disabled}
						help={disabled ? __('Only one Website can be saved as the "Website" field on the WordPress user profile.', 'affiliate-wp') : __('The Website will be saved to the "Website" field on the affiliate\'s WordPress user profile.', 'affiliate-wp' )}
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
				type="url"
				required={required}
				setAttributes={setAttributes}
				isSelected={isSelected}
				name={name}
				fieldClassNames={fieldClassNames}
				placeholder={placeholder}
				context={context}
			/>
		</>
	);
}

export default AffiliateWPFieldWebsite;
