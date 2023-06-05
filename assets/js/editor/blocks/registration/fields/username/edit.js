/**
 * Affiliate Registration Form username field Edit Component.
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

import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import TextEdit from '../../components/text-edit';

function AffiliateWPFieldText({
	attributes,
	setAttributes,
	isSelected,
	name,
	context,
	clientId,
}) {

	// Username is always required
	setAttributes({ required: true })

	/* translators: Username help text */
	const helpText = __('The Username field is always required', 'affiliate-wp' );

	return <TextEdit attributes={attributes}
		 setAttributes={setAttributes}
		 isSelected={isSelected}
		 disableRequired={true}
		 help={helpText}
		 name={name}
		 context={context}
	/>
}
export default AffiliateWPFieldText;
