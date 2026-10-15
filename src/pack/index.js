import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import { createEdit } from '../shared/edit';

registerBlockType( metadata.name, {
	edit: createEdit( 'pack', 'packs' ),
	save: () => null, // rendered on the server
} );
