/**
 * Non-Affiliate Content Edit Component.
 *
 * @since 2.8
 */

/**
 * Internal dependencies
 */
import icon from '../../components/icon';

/**
 * WordPress dependencies
 */
import { InnerBlocks } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Icon, Notice } from '@wordpress/components';

export default function NonAffiliateContentEdit({ className }) {
	return (
		<div className={ className }>
			<InnerBlocks />
		</div>
	);
}

const withNotice = createHigherOrderComponent( ( BlockListBlock ) => {

	return ( props ) => {

		if( props.isSelected ) {

			// Get ID of parent block.
			const parentBlockId = wp.data.select('core/block-editor').getBlockHierarchyRootClientId(props.clientId);

			if ( parentBlockId && props.name !== 'affiliatewp/non-affiliate-content' ) {

				// Get parent block.
				const parentBlock = wp.data.select('core/block-editor').getBlock(parentBlockId);

				// If the parent block is the affiliate content block, show a message.
				if ( 'affiliatewp/non-affiliate-content' === parentBlock.name ) {
					return (
						<>
							<BlockListBlock { ...props } />
							<Notice isDismissible={ false }>
							<Icon
								icon={ icon }
								color={ true }
							/>
								{ __( 'This block will only be shown to non affiliates', 'affiliate-wp' ) }
							</Notice>
						</>
					);
				}
			}

		}

		return <BlockListBlock { ...props } />;

	};
}, 'withNotice' );

wp.hooks.addFilter( 'editor.BlockListBlock', 'affiliate-wp/with-notice', withNotice );