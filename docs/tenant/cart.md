# Cart

This product guide explains the cart from the shopper's and merchant's point
of view. A cart is the shopper's editable list before an order exists; it is
not an inventory reservation or a final price record. Field-level detail is
in the database's own column comments, not here.

## What a cart holds

- A cart belongs either to a signed-in customer or to a guest, never both.
  A customer has at most one cart. A guest gets a secret cart token; only its
  hash is stored.
- It remembers the shopper's chosen currency (the store's base currency when
  none is chosen), a destination country and state for an early shipping and
  tax estimate (the full address comes later, in checkout), the selected
  shipping or pickup option, and the one promotion code entered. The code
  stays on the cart even while the basket doesn't meet its conditions yet.
- Its version goes up with every change. Clients send the version they saw,
  so an old edit never overwrites a newer one.
- Each line is one variant and a whole quantity of at least one, within the
  configured per-line limit. Adding a variant already in the cart changes
  that line instead of adding a second one.
- Each line keeps the unit price the shopper last saw, and whether it was the
  merchant's exact price in that currency or a converted one. That lets the
  app say when a price changed. A movement in the exchange rate alone is not
  treated as a price change when the source price hasn't changed.

## What happens in practice

1. A guest receives a one-time secret token; the cart lookup stores and uses
   its hash. A signed-in shopper's cart is found from their account.
2. Adding an item checks that the variant is for sale and priced. It adds to
   the existing line if that variant is already present. Cart size and line
   quantity limits are enforced.
3. The cart recalculates prices, tax estimates, shipping and discounts when
   it is read. A line stays visible if its variant later becomes unavailable,
   so the shopper can decide what to do.
4. A cart never holds stock and does not guarantee a price. Stock is
   reserved only when checkout is placed as an order. An old cart version is
   rejected rather than silently replacing a newer edit.
5. If a guest signs in, the guest cart can be merged into the customer's cart.
   After successful order placement, the ordered cart is cleared.

## Connected domains

- Catalog supplies variants and base prices.
- Pricing supplies currency conversion and tax calculations.
- Promotions proposes applicable discounts; actual promotion usage is held
  at order placement.
- Shipping quotes options based on destination and shippable lines.
- Checkout collects full buyer information, confirms a time-limited quote,
  and then turns the cart into an order.

## Action flow and domain handoffs

`CreateCart` makes a cart for a guest or finds the signed-in customer's
existing cart. Cart line actions validate the requested variant and quantity
and update the cart version so clients can detect stale edits. If a shopper
signs in with a guest cart, `MergeGuestCart` keeps the larger quantity when
the same variant exists in both carts, moves eligible other lines, reports
lines it cannot move, and deletes the guest cart after the merge.

`PriceCart` is the important cross-domain action. It reads the current
Catalog variants, asks Inventory for availability, gets buyer prices and tax
from Pricing, applies Promotions, and requests shipping options from
Shipping. The result is a live quote, not a financial commitment: no stock
or promotion use is held until [Checkout](checkout.md) places an order.

The order placement workflow clears the cart only after it successfully
creates the order. For the complete lifecycle, see
[Sellora business flows](business-flows.md).
