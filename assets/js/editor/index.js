/**
 * WordPress Dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal Dependencies
 */
import * as affiliateArea from './blocks/affiliate-area';
import * as affiliateRegistration from './blocks/registration';
import * as affiliateLogin from './blocks/login';
import * as affiliateContent from './blocks/affiliate-content';
import * as nonAffiliateContent from './blocks/non-affiliate-content';
import * as optIn from './blocks/opt-in';
import * as affiliateReferralUrl from './blocks/affiliate-referral-url';
import * as affiliateCreatives from './blocks/affiliate-creatives';
import * as affiliateCreative from './blocks/affiliate-creative';
import * as fieldText from './blocks/registration/fields/text';
import * as fieldName from './blocks/registration/fields/name';
import * as fieldUsername from './blocks/registration/fields/username';
import * as fieldPaymentEmail from './blocks/registration/fields/payment-email';
import * as fieldAccountEmail from './blocks/registration/fields/account-email';
import * as fieldTextArea from './blocks/registration/fields/textarea';
import * as fieldEmail from './blocks/registration/fields/email';
import * as fieldWebsite from './blocks/registration/fields/website';
import * as fieldCheckbox from './blocks/registration/fields/checkbox';
import * as fieldPassword from './blocks/registration/fields/password';
import * as fieldPhone from './blocks/registration/fields/phone';
import * as registerButton from './blocks/registration/fields/register-button';

const registerBlocks = () => {
	[
		affiliateArea,
		affiliateRegistration,
		affiliateLogin,
		affiliateContent,
		nonAffiliateContent,
		optIn,
		affiliateReferralUrl,
		affiliateCreatives,
		affiliateCreative,
		fieldEmail,
		fieldText,
		fieldTextArea,
		fieldWebsite,
		fieldPassword,
		fieldPhone,
		fieldCheckbox,
		fieldName,
		fieldUsername,
		fieldPaymentEmail,
		fieldAccountEmail,
		registerButton,
	].forEach( ( { name, settings } ) => {
		registerBlockType( name, settings );
	} );

};
registerBlocks();