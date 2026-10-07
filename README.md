# Smart Bundles for WooCommerce 1.2.0

## What changed in 1.2.0

* **Cart page no longer reloads.** Ticking a "frequently bought together" product on the cart page and
  clicking "Add selected to cart" now refreshes only the cart region (table + totals + our block) via
  AJAX — no full page reload. Falls back to a normal reload only if a theme's cart markup is too
  unusual for the swap to find.
* **Summary line can be hidden.** New Settings → Frequently bought together / Cart page toggles:
  "Show the 'N selected · +Rs.X' line". Off by default view stays identical either way.
* **Per-product image badge removed.** The old "3. Badge on this product's image" section (and all its
  code — meta keys, CSS, front-end output) is gone. Use pack badges, bundle badges, or styling instead.
* **New: Bundle offer.** Pick a fixed set of other products in the product editor; they're shown
  together with the main product as one deal (toggle to add all, one combined price, one "Save Rs.X"
  line, one badge). The discount is one number applied to the **whole bundle**, not per product —
  set it as a % off the combined price or a fixed total price for the whole bundle. Arrange the
  picked products in the same drag-to-reorder table used for "frequently bought together". Each
  bundle can have its own heading and its own headline text next to the toggle (both fall back to the
  settings-page default when left empty). Full styling tab in Settings (card, toggle, price, badge,
  item list…). Shortcode: `[smb_offer]`.
* **Pack offers are now on by default, for every product.** No setup needed: every simple/variable
  product gets a "1 pack" (qty 1, no discount, pre-selected) and a "2 pack" (qty 2, no discount) the
  moment the plugin is active. Open a product and add a discount or more tiers in section 1 any time —
  your table replaces the default the moment you save. Turn pack offers off for one product with the
  "Show pack offers on this product" checkbox.
* **Pack offers and the Bundle offer are mutually exclusive, per product.** Switching the Bundle offer
  on for a product automatically takes over from Pack offers on that same product (the product editor
  shows a note; nothing needs to be unchecked by hand). Switch the bundle off and pack offers resume
  automatically. This is enforced on the server, so it holds even if the page's JavaScript didn't run.
* **Headings:** both the Bundle offer and "frequently bought together" keep the per-product heading
  override introduced in 1.1.0 (empty = use the Settings-page default).

## What changed in 1.1.0 (for reference)
* Fixed a critical error on product pages with Pack offers (a private method was being called from the template).
* "Frequently bought together" products appear in a drag-to-reorder table (name, badge, remove); no discount, no "sort by".
* Per-product headings for Pack offers / FBT; "frequently bought together" available on the cart page too.
* Settings page gained a live preview (desktop / tablet / mobile) with font, colour, spacing, border, radius, shadow controls for every block.

## Shortcodes
| Shortcode | Shows |
|---|---|
| `[smb_packs]` | Pack offers for the current (or `id="123"`) product |
| `[smb_bundle]` | "Frequently bought together" for the current (or `id="123"`) product |
| `[smb_offer]` | The new Bundle offer for the current (or `id="123"`) product |
| `[smb_cart_bundle]` | "Frequently bought together" on the cart page |

## Theme overrides
Copy `templates/packs.php`, `templates/fbt.php` or `templates/bundle.php` to `your-theme/smart-bundles/`.
**If you copied 1.0 or 1.1 templates earlier, delete or re-copy them** before updating — an old
`packs.php` will be missing the newer template variables.

## Known limitation
Removing the *main* product of a bundle from the cart removes the rest of the bundle with it. Using
WooCommerce's own "Undo" link after that does **not** restore the rest of the bundle — only the main
product comes back. Re-ticking the bundle toggle on the product page is the reliable way to add the
full bundle again.

## Before going live on a client site
This release's two newest code paths are the **Bundle offer** (cart math, linked cart lines, checkout
display) and the **cart-page partial refresh**. Both were smoke-tested against the real plugin classes
in a stub WordPress environment (pricing math, template rendering, the product-editor save round-trip,
and the new "pack vs bundle" precedence), but that is not the same as a live WooCommerce cart. Please
test, on staging, before using on a live store:
1. Add a product to the cart via a Bundle offer, confirm the combined price and the per-line prices in
   the cart and at checkout, then remove the main line and confirm the rest of the bundle is removed too.
2. On the cart page, tick a "frequently bought together" product and click "Add selected to cart" —
   confirm the cart updates in place (totals, your theme's mini-cart count) without a full reload.
3. Open a product that has never been edited before and confirm Pack offers show "1 pack" / "2 pack"
   with no discount, 1 pack pre-selected — then turn its Bundle offer on and confirm Pack offers
   disappear from that product.

## Notes
* Cart-page block works with the classic cart. For the block-based cart, put `[smb_cart_bundle]` in a Shortcode block.
* Google fonts are loaded from fonts.googleapis.com only when you pick one.
