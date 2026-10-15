import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import { createEdit } from '../shared/edit';

registerBlockType( metadata.name, {
	edit: createEdit( 'bundle', 'bundles' ),
	save: () => null, // rendered on the server
} );
