/**
 * Referral URL Function.
 *
 * Helper function to generate a referral URL.
 *
 * @since 2.8
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { trailingSlashIt } from 'trailing-slash-it';

/**
 * ReferralUrl.
 *
 * Generates a referral URL.
 *
 * @since 2.8
 *
 * @param {string} url    The URL to convert to a referral URL.
 * @param {string} format The referral format. Can be "id" or "username". Defaults to "id".
 * @param {string} pretty Format the URL as pretty. Leave empty to use non-pretty URLs.
 *                        Can be "yes", "default", or undefined. If "yes", this will make the URL pretty. If "default"
 *                        This will use the default settings set in AffiliateWP. If undefined, this will make the url
 *                        non-pretty. Default Undefined (non-pretty URL).
 * @returns {string}      The referral URL.
 */
function referralUrl( { url, format, pretty } ) {

	// The global "Default Referral Format" setting. Either "username" or "ID".
	const referralFormat = affwp_blocks.referral_format;

	// The global "Pretty Affiliate URLs" option.
	const prettyAffiliateUrls = affwp_blocks.pretty_referral_urls;

	/**
	 * Get the affiliate ID of the currently logged in user. If they are not an
	 * affiliate, we'll show a demo ID.
	 */
	const affiliateId = affwp_blocks.affiliate_id || __( '123', 'affiliate-wp' );

	/**
	 * Get the affiliate username of the currently logged in user. If they are not an
	 * affiliate, we'll show a demo username.
	 */
	const affiliateUsername = affwp_blocks.affiliate_username || __( 'demoaffiliate', 'affiliate-wp' );

	/**
	 * Get the referral variable. E.g. "ref".
	 */
	const referralVariable = affwp_blocks.referral_variable;

	/**
	 * Get the permalink. If no custom URL has been entered it will fall back
	 * to the current page's permalink.
	 */
	const permalink = url ? trailingSlashIt(url) : wp.data.select('core/editor').getPermalink();

	let referralFormatValue = '';

	// "Site Default" option selected
	if ( 'default' === format ) {

		switch (referralFormat) {
			case 'username':
				referralFormatValue = affiliateUsername;
				break;

			case 'id':
			default:
				referralFormatValue = affiliateId;
				break;
		}

	} else if( 'id' === format ) {
		referralFormatValue = affiliateId;
	} else if( 'username' === format ) {
		referralFormatValue = affiliateUsername;
	}

	// Build the referral URL to show.
	let referralURL = addQueryArgs( permalink, { [referralVariable]: referralFormatValue } );

	let isPrettyAffiliateURLs = false;

	if ( 'default' === pretty ) {
		// Check that the site default is currently set.
		if ( prettyAffiliateUrls ) {
			isPrettyAffiliateURLs = true;
		}
	}

	// Explicitly enabled.
	if ( 'yes' === pretty ) {
		isPrettyAffiliateURLs = true;
	}

	if ( isPrettyAffiliateURLs ) {
		referralURL = `${permalink}${trailingSlashIt(referralVariable)}${trailingSlashIt(referralFormatValue)}`;
	}

	return referralURL;

}
export default referralUrl;