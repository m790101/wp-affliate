/**
 * Affiliate Referral URL Edit Component.
 *
 * @since 2.8
 */

import getReferralUrl from '../../utils/referral-url';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { URLInput } from '@wordpress/block-editor';
import { InspectorControls } from '@wordpress/editor';
import {
	PanelBody,
	RadioControl
} from '@wordpress/components';

/**
 * Affiliate Referral URL.
 *
 * Affiliate referral URL component.
 *
 * @since 2.8
 *
 * @param {object}   attributes    Block attributes.
 * @param {function} setAttributes Method used to set the attributes for this component in the global scope.
 * @param {string}   className     The class name for the referral URL wrapper.
 * @returns {JSX.Element}          The rendered component.
 */
function AffiliateReferralUrl( { attributes, setAttributes, className } ) {

	const { url, format, pretty } = attributes;

	const referralUrl = getReferralUrl( {
		url,
		format,
		pretty
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<RadioControl
						label={ __( 'Pretty Affiliate URLs', 'affiliate-wp' ) }
						selected={ pretty }
						options={ [
							{ label: __( 'Site Default', 'affiliate-wp' ), value: 'default' },
							{ label: __( 'Yes', 'affiliate-wp' ), value: 'yes' },
							{ label: __( 'No', 'affiliate-wp' ), value: 'no' },
						] }
						onChange={ ( option ) => { setAttributes( { pretty: option } ) } }
					/>
					<RadioControl
						label={ __( 'Referral Format', 'affiliate-wp' ) }
						selected={ format }
						options={ [
							{ label: __( 'Site Default', 'affiliate-wp' ), value: 'default' },
							{ label: __( 'ID', 'affiliate-wp' ), value: 'id' },
							{ label: __( 'Username', 'affiliate-wp' ), value: 'username' },
						] }
						onChange={ ( option ) => { setAttributes( { format: option } ) } }
					/>
					<URLInput
						label={ __( 'Custom URL', 'affiliate-wp' ) }
						className={ 'components-text-control__input is-full-width' }
						value={ url }
						onChange={ ( url, post ) => setAttributes( { url } ) }
						disableSuggestions={ true }
						placeholder={''}
					/>
				</PanelBody>
			</InspectorControls>

			<p className={className}>{referralUrl}</p>
		</>
	);
}
export default AffiliateReferralUrl;