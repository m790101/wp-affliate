/**
 * Affiliate Creative Block.
 *
 * @since 2.8
 */


/**
 * Internal dependencies
 */
import getReferralUrl from '../utils/referral-url';

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Disabled } from '@wordpress/components';
import classnames from 'classnames';


/**
 * Affiliate Creative.
 *
 * Affiliate Creative Component.
 *
 * @returns {JSX.Element} Rendered form component.
 * @constructor
 */
const AffiliateCreative = ({
	id,
	name,
	description,
	image,
	url,
	text,
	preview
}) => {

	const referralUrl = getReferralUrl( {
		url,
		format: affwp_blocks.referral_format,
		pretty: affwp_blocks.pretty_referral_urls
	} );

	const code = String.raw`<a href="${referralUrl}" title="${text}">${text}</a>`;

	const classes = classnames(
		'affwp-creative',
		'creative-' + id,
	);

	const ImageOrText = () => {

		if ( image ) {
			return (
				<img
					alt={ text }
					src={ image }
				/>
			);
		} else {
			return (
				<>
					{text}
				</>
			);
		}

	}

	return (
		<>
			<div className={ classes }>

				{ description &&
				<p className="affwp-creative-desc">
					{description}
				</p>
				}

				{ preview &&
					<Disabled>
						<p>
							<a
								href={ referralUrl }
								title={ text }
							>
								<ImageOrText />
							</a>
						</p>
					</Disabled>
				}

				<p>{ __( 'Copy and paste the following:', 'affiliate-wp' ) }</p>

				<pre><code>{code}</code></pre>

			</div>
		</>
	);
}
export default AffiliateCreative;