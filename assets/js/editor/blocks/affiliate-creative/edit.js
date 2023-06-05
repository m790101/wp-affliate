/**
 * Affiliate Creative Component.
 *
 * @since 2.8
 */

/**
 * Internal dependencies
 */
import AffiliateCreative from '../../components/affiliate-creative';
import icon from '../../components/icon';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	PanelBody,
	Placeholder,
	Spinner,
	Icon,
	Notice,
	SelectControl
} from '@wordpress/components';

import { useState, useEffect } from '@wordpress/element';
import { InspectorControls } from '@wordpress/editor';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Affiliate Creatives Edit.
 *
 * Affiliate creative component.
 *
 * @since 2.8
 *
 * @param {object}   attributes    Block attributes.
 * @param {function} setAttributes Method used to set the attributes for this component in the global scope.
 * @returns {JSX.Element}          The rendered component.
 */
function AffiliateCreativeEdit( { attributes, setAttributes } ) {
	// The creative ID.
	const { id } = attributes;

	const [creatives, setCreatives] = useState( null );
	const [creative, setCreative] = useState( null );
	const [isLoading, setLoading] = useState( false );
	const [error, setError] = useState();

	const CREATIVES_QUERY = {
		number: 100 // Hardcoded limit for now.
	};

	/**
	 * Fetch a list of creatives.
	 */
	useEffect(() => {
		let ignore = false;

		if ( creatives ) {
			return;
		}

		async function fetchData() {
			setLoading(true);

			try {
				const result = await apiFetch( { path: addQueryArgs( `/affwp/v1/creatives/`, CREATIVES_QUERY ) } );

				if ( ! ignore ) {
					// Store the creatives in state.
					setCreatives( result );

					/**
					 * Instantly store the saved creative object in state so we
					 * can pass the values to <AffiliateCreative />
					 */
					if ( id ) {
						// Find the creative based on the ID we have saved.
						setCreative( result.find( creative => creative.creative_id === id ) );
					}

				}

			  } catch (error) {
				setError(error);
			  }

			  setLoading( false );
		}

		// Fetch the creatives.
		fetchData();

		return () => { ignore = true; }
	}, []);

	useEffect(() => {
		if ( creative ) {
			// After the creative is updated, set the attribute ID.
			setAttributes( { id: parseInt( creative.creative_id ) } );
		}

	}, [creative]);

	const affiliateWpIcon = () => {
		return (
			<Icon
				icon={ icon }
				color={ true }
			/>
		);
	}

	const isActiveCreative = ( creative ) => {
		if ( 'active' === creative.status ) {
			return true;
		}

		return false;
	}

	// Check to see if a creative exists.
	const isCreative = ( creativeId ) => {
		return creatives.find( creative => creative.creative_id === creativeId );
	}

	const CreativeSelector = () => {

		const creativesOptions = () => {
			const selectOption = {
				label: __( '- Select -', 'affiliate-wp' ),
				value: '',
				disabled: true,
			};

			const creativesOptions = creatives.map( ( creative ) => (
				{ label: `(id: ${creative.id}) ${creative.name}`, value: creative.id }
			) )

			return [ selectOption, ...creativesOptions ];
		}

		return (
			<>
				<SelectControl
					label={ __( 'Select the affiliate creative to display', 'affiliate-wp' ) }
					value={ id && typeof id !== 'undefined' && isCreative(id) ? id : '' }
					options={ creativesOptions() }
					onChange={ ( id ) => setCreative( creatives.find( creative => creative.creative_id === parseInt( id ) ) ) }
				/>
			</>
		);
	}

	const CreativeInspectorControls = () => {

		return (
			<InspectorControls>

				{ ( creative && id && ! isActiveCreative( creative ) ) &&
					<Notice
						className={"affwp-block-inspector-notice"}
						isDismissible={ false }
						status="warning"
					>
						{ __( 'This creative is inactive.', 'affiliate-wp' ) }
					</Notice>
				}

				{ ( id && ! isCreative( id ) ) &&
					<Notice
						className={"affwp-block-inspector-notice"}
						isDismissible={ false }
						status="error"
					>
						{ sprintf( __( 'This creative (id: %d) no longer exists.', 'affiliate-wp' ), id ) }
					</Notice>
				}

				<PanelBody>
					<CreativeSelector />
				</PanelBody>

			</InspectorControls>
		);
	}

	if ( isLoading ) {
		return <Spinner />;
	}

	/**
	 * If there are no creatives and there is no current creative set
	 * (in block's attributes), show an error message.
	 */
	if ( creatives === null && creative === null && ! id ) {
		return (
			<>
				<Placeholder
					icon={ affiliateWpIcon }
					label={ __( 'Affiliate Creative', 'affiliate-wp' ) }
				>
					{ /*
					 * Rather than retrieving the error message from the response
					 * and showing an unnecessary loading spinner, just show the
					 * message straight away.
					 */ }
					{ __( 'No creatives were found.', 'affiliate-wp' ) }
				</Placeholder>
			</>
		);
	}

	/**
	 * If there is no creative set at all, allow the user to select one.
	 *
	 * 1. No creative was ever selected.
	 * 2. A creative was previously saved, but the ID no longer exists (deleted)
	 */
	if (
		( creative === null && creatives !== null && ! id ) || // 1
		( creatives !== null && id && ! isCreative( id ) ) // 2
	) {
		return (
			<>
				<CreativeInspectorControls />
				<Placeholder
					icon={ affiliateWpIcon }
					label={ __( 'Affiliate Creative', 'affiliate-wp' ) }
				>
					<CreativeSelector />
				</Placeholder>
			</>
		);
	}

	/**
	 * If there's a creative (in state) and the ID has been set in the block
	 * attributes, show the creative preview.
	 */
	if ( creative && id ) {
		return (
			<>
				<CreativeInspectorControls />
				<AffiliateCreative
					id={creative.creative_id}
					name={creative.name}
					description={creative.description}
					image={creative.image}
					url={creative.url}
					text={creative.text}
					preview={true}
				/>
			</>
		);
	}

	return null;

}
export default AffiliateCreativeEdit;