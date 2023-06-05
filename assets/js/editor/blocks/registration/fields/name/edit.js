/**
 * Affiliate Registration Form name field Edit Component.
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

	return <TextEdit attributes={attributes}
		 setAttributes={setAttributes}
		 isSelected={isSelected}
		 name={name}
		 context={context}
	/>
}
export default AffiliateWPFieldText;
