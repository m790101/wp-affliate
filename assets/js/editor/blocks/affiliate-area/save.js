/**
 * WordPress dependencies
 */
import { InnerBlocks } from '@wordpress/block-editor';

export default function save({ attributes }) {

	return (
		<InnerBlocks.Content />
	);
}