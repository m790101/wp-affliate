/**
 * Affiliate Registration Form Email field Edit Component.
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
import EmailEdit from '../../components/email-edit';

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
	SelectControl,
} from '@wordpress/components';

import { useEffect } from '@wordpress/element';

function AffiliateWPFieldEmail( { attributes, setAttributes, isSelected, name, context, clientId, } ) {

	const {
		required,
		label,
		classNames,
		placeholder,
		type,
	} = attributes;

	const fieldClassNames = classnames(
		'affwp-field',
		'affwp-field-email'
	);

	return (
		<EmailEdit
			attributes={attributes}
			setAttributes={setAttributes}
			isSelected={isSelected}
			name={name}
			context={context}
			clientId={clientId}
		/>
	);
}

export default AffiliateWPFieldEmail;
