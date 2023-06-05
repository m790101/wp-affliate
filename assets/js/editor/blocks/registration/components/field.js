/**
 * Affiliate registration field component.
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
 import AffiliateWPFieldLabel from './field-label';

 /**
  * WordPress dependencies
  */
 import { __ } from '@wordpress/i18n';
 import {
	 useBlockProps,
	 RichText
 } from '@wordpress/block-editor';

 function AffiliateWPField( {
	 isSelected,
	 required,
	 requiredAttribute,
	 label,
	 setAttributes,
	 name,
	 type,
	 classNames,
	 fieldClassNames,
	 placeholder,
	 context,
 } ) {

	 const blockProps = useBlockProps();

	 const showPlaceholders = context['affiliatewp/placeholders'];

	 return (
		 <>
			 <div { ...blockProps }>
				 <AffiliateWPFieldLabel
					 identifier="label"
					 required={ required }
					 requiredAttribute={ requiredAttribute }
					 label={ label }
					 labelAttribute={ 'label' }
					 setAttributes={ setAttributes }
					 isSelected={ isSelected }
					 name={ name }
					 context={context}
				 />

				 <RichText
					 identifier="placeholder"
					 placeholder={ showPlaceholders ? __( 'Add placeholder text…' ) : '' }
					 value={ placeholder }
					 onChange={ ( placeholder ) => setAttributes( { placeholder } ) }
					 allowedFormats={ [] }
					 type={type}
					 className={fieldClassNames}
				 />

			 </div>
		 </>
	 );
 }
 export default AffiliateWPField;
