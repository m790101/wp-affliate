/**
 * Helper functions for registration fields.
 *
 * @since 2.8
 */

/**
 * isRegistrationBlockChild
 *
 * Determines if a block type is a child of the specified root block.
 *
 * @since 2.8
 *
 * @param type The block type to check
 * @param clientId The clientID passed from the parent block
 * @returns {true|undefined} Returns true if the block type is a child of the specified root block.
 */
export const isRegistrationBlockChild = ( type, clientId ) => {
	const blockEditor = wp.data.select( 'core/block-editor' )
	const blockRootClientId = blockEditor.getBlockRootClientId(clientId)
	const innerBlocks = blockEditor.getBlock(blockRootClientId).innerBlocks;
	const block = innerBlocks.find( ({ attributes }) => attributes.type === type );

	if (block) {
		return block
	}

	return undefined
}

/**
 * isCurrentRegistrationBlockChild
 *
 * Determines if the block type is a the current registraiton block.
 *
 * @since 2.8
 *
 * @param type The block type to check
 * @param clientId The clientID passed from the parent block
 * @returns {true|undefined}  Returns true if the block type is a the current registraiton block.
 */
export const isCurrentRegistrationBlockChild = ( type, clientId ) => {
	if ( clientId === isRegistrationBlockChild(type, clientId)?.clientId ) {
		return true
	}
	return false
}