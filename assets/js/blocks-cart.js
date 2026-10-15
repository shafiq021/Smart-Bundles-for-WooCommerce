/**
 * Smart Bundles on the WooCommerce Cart block: put products into the block cart through its own
 * data store, so the cart (lines, totals, shipping) refreshes by itself without a page reload.
 * Loaded by the Cart / Checkout block integration (see SMB_Blocks_Integration).
 */
(function () {
	'use strict';

	function cartActions() {
		return window.wp && window.wp.data && window.wp.data.dispatch ? window.wp.data.dispatch('wc/store/cart') : null;
	}

	/**
	 * @param {string[]} tokens "productId:variationId" values (as the bought-together box produces).
	 * @return {Promise} resolves when every product is in the cart, rejects on the first failure.
	 */
	function addToCart(tokens) {
		var actions = cartActions();
		if (!actions || typeof actions.addItemToCart !== 'function') {
			return Promise.reject(new Error('The block cart is not available.'));
		}
		// One at a time: every add returns the whole cart and must build on the previous one.
		var chain = Promise.resolve();
		(tokens || []).forEach(function (token) {
			var bits = String(token).split(':');
			// A variation is added by its own id; a simple product by its id.
			var id = parseInt(bits[1], 10) || parseInt(bits[0], 10) || 0;
			if (!id) {
				return;
			}
			chain = chain.then(function () {
				return actions.addItemToCart(id, 1);
			});
		});
		return chain;
	}

	window.smbBlocks = { addToCart: addToCart };
})();
