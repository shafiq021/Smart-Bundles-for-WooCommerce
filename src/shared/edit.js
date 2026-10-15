import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ComboboxControl, Placeholder, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Shared editor for the three blocks. `route` is the REST collection (packs | bundles | fbt) under
 * /smart-bundles/v1 that lists the products showing that kind of offer.
 */
export function createEdit( blockSlug, route ) {
	const blockName = 'smart-bundles/' + blockSlug;

	return function Edit( { attributes, setAttributes } ) {
		const { productId } = attributes;
		const blockProps = useBlockProps();
		const [ options, setOptions ] = useState( [] );
		const [ loading, setLoading ] = useState( false );
		const [ failed, setFailed ] = useState( false );
		const timer = useRef();

		const postId = useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			return editor && editor.getCurrentPostId ? editor.getCurrentPostId() : 0;
		}, [] );

		// Product list from the REST API (debounced search, first 20 matches).
		const search = ( term ) => {
			clearTimeout( timer.current );
			timer.current = setTimeout( () => {
				setLoading( true );
				apiFetch( {
					path: addQueryArgs( '/smart-bundles/v1/' + route, { search: term || '', per_page: 20 } ),
				} )
					.then( ( rows ) => {
						setFailed( false );
						setOptions( rows.map( ( r ) => ( { value: r.id, label: r.name + ' (#' + r.id + ')' } ) ) );
					} )
					.catch( () => setFailed( true ) )
					.finally( () => setLoading( false ) );
			}, 250 );
		};

		useEffect( () => {
			search( '' );
			return () => clearTimeout( timer.current );
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [] );

		const choices = [
			{ value: 0, label: __( 'Current product (the page this block is on)', 'smart-bundles' ) },
			...options.filter( ( o ) => o.value !== 0 ),
		];

		// A saved product that is not in the first page of results must still show in the list.
		if ( productId && ! choices.some( ( o ) => o.value === productId ) ) {
			choices.push( { value: productId, label: '#' + productId } );
		}

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Offer', 'smart-bundles' ) }>
						<ComboboxControl
							label={ __( 'Product', 'smart-bundles' ) }
							help={
								failed
									? __( 'The product list could not be loaded.', 'smart-bundles' )
									: __( 'Only products that currently show this offer are listed. Leave on "Current product" to use the page the block is on.', 'smart-bundles' )
							}
							value={ productId }
							options={ choices }
							onChange={ ( v ) => setAttributes( { productId: v ? parseInt( v, 10 ) : 0 } ) }
							onFilterValueChange={ search }
						/>
						{ loading && <Spinner /> }
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					{ ! productId && ! postId ? (
						<Placeholder
							label={ __( 'Smart Bundles', 'smart-bundles' ) }
							instructions={ __( 'Choose a product in the block settings.', 'smart-bundles' ) }
						/>
					) : (
						<ServerSideRender
							block={ blockName }
							attributes={ attributes }
							urlQueryArgs={ postId ? { post_id: postId } : {} }
						/>
					) }
				</div>
			</>
		);
	};
}
