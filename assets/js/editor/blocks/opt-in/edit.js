/**
 * Opt-In Form Edit Component.
 *
 * @since 2.8
 */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/editor';
import {
	PanelBody,
	TextControl,
} from '@wordpress/components';

/**
 * Opt-In Form.
 *
 * Affiliate registration opt-in form.
 *
 * @since 2.8
 *
 * @param {object}   attributes    Block attributes.
 * @param {function} setAttributes Method used to set the attributes for this component in the global scope.
 * @returns {JSX.Element}          The rendered component.
 */
function OptInForm( { attributes, setAttributes } ) {

	const { redirect } = attributes;

	return (
		<>

			<InspectorControls>
				<PanelBody>

					<TextControl
					label={ __( 'Redirect' ) }
					value={ redirect }
					onChange={ ( redirect ) => setAttributes({ redirect }) }
				/>

			</PanelBody>
		</InspectorControls>

		<div id="affwp-login-form" className="affwp-form">

			<p>
				<label htmlFor="affwp-opt-in-name">{ __( 'First Name', 'affiliate-wp' ) }</label>
				<input
					id="affwp-opt-in-name"
					className="required"
					type="text"
					name="affwp_first_name"
					title={ __( 'First Name', 'affiliate-wp' ) }
				/>
			</p>

			<p>
				<label htmlFor="affwp-opt-in-name">{ __( 'Last Name', 'affiliate-wp' ) }</label>
				<input
					id="affwp-opt-in-name"
					className="required"
					type="text"
					name="affwp_last_name"
					title={ __( 'Last Name', 'affiliate-wp' ) }
				/>
			</p>

			<p>
				<label htmlFor="affwp-opt-in-email">{ __( 'Email Address', 'affiliate-wp' ) }</label>
				<input
					id="affwp-opt-in-email"
					className="required"
					type="text"
					name="affwp_email"
					title={ __( 'Email Address', 'affiliate-wp' ) }
				/>
			</p>

			<p>
				<input
					className="button"
					type="submit"
					value={ __( 'Subscribe', 'affiliate-wp' ) }
				/>
			</p>

		</div>

	</>
	);
}

export default OptInForm;