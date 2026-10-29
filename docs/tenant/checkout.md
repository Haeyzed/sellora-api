# Checkout

This product guide explains how checkout collects the details needed to
convert a cart into an order and shows the buyer the price they are
confirming. Field-level detail is in the database's own column comments, not
here.

## What a checkout holds

- A cart has at most one open checkout. It is open until the order is
  placed, then placed. While open it saves the shopper's details and a
  short-lived quote, but holds no stock and no promotion uses.
- It keeps the buyer's contact details (email in lower case, phone in
  international format), copies of the shipping and billing addresses rather
  than links to saved addresses that could change (no billing address means
  the same as shipping), the chosen shipping or pickup method, the core
  payment method for what's left to pay, and any module payment methods used
  first, such as a gift card.
- A buyer tax ID, when given, always comes with its country.
- Marketing consent by email and by SMS is asked explicitly and starts as no.
- The quote has a version, an expiry and a fingerprint of what is being
  charged. It also remembers the cart version it was worked out from and the
  exchange rate used for its lifetime.
- When the order is placed, each chosen payment method gets a payment hold
  for its share of the order, in minor units. A hold is held, then committed
  or released. A zero share is allowed for a method that holds no money
  itself. A module method left with nothing to cover (the ones before it
  covered everything, or it has nothing to give) gets no hold and isn't
  listed as chosen in the quote; the shopper's choice is kept, so it applies
  again if the basket grows.
- Each hold is committed or released on its own, so a payment method that
  fails (it throws, or its module is no longer installed) never holds up the
  others. The failure is recorded on that hold and logged, and the hourly
  payments sweep tries again until it settles: committed for a paid order,
  released for an expired or cancelled one. Staff are alerted once when a
  hold has failed `CHECKOUT_HOLD_SETTLE_ALERT_AFTER` tries in a row (3 by
  default); once a retry settles it, the failure is cleared and the time it
  recovered is kept.

## Buyer journey

1. Checkout starts from a non-empty cart. Guest purchase is allowed only if
   the store setting allows it. Signed-in customers use their account email.
2. The buyer supplies contact information, addresses when shipping requires
   them, a shipping method, optional tax ID and payment choice.
3. Checkout pricing reuses the cart's pricing path. The quote includes
   product prices, discounts, shipping, tax, currency/exchange-rate
   information, payment methods and any issues that need correction.
4. The quote has a version and expiry. When the buyer confirms, placement
   locks and rechecks the cart and quote. If price, tax, shipping, stock,
   promotions, payment options, or the rate changed, no order is placed;
   checkout returns an updated quote for buyer confirmation.
5. If still valid, one transaction reserves inventory and promotion uses,
   creates the order and payment holds, records explicitly selected marketing
   consent, gives a guest an order-access token, and clears the cart.
6. Only after that transaction does the app contact a gateway. A slow gateway
   therefore does not hold database locks or erase the placed order; the
   buyer can retry payment.

## Important distinction

An open checkout is a saved set of details plus a quote; it is not an order,
not a stock hold, and not a completed payment. Inventory and promotion holds
begin at placement. Gateway payment success is handled by Payments. Whether
guests may check out and when a tax ID is asked for are store settings.

## Action flow and domain handoffs

`StartCheckout` requires a non-empty cart and opens or reuses its open
checkout. `UpdateCheckout` stores the buyer's latest contact details,
addresses, selected shipping/payment choices, locale and consent answers,
then refreshes the quote through the same pricing path used by Cart. That
path combines Pricing, Promotions, Shipping and Settings; Customer account
data may supply the signed-in buyer and saved addresses.

`PlaceCheckoutOrder` is the commitment point. It locks and verifies the
checkout, cart version, quote hash/version/expiry, and relevant choices
before it creates anything. If the buyer's quote is no longer valid, the
action returns the updated quote instead of silently charging a different
amount. If valid, the transaction reserves Inventory stock and Promotions
uses, creates the immutable Orders record and payment holds, records
explicitly selected marketing consent, provides guest order access if
needed, and clears the Cart. Gateway calls are made after commit by Payments.

Thus, saving checkout details does not reserve stock; pressing confirm and
passing the quote checks does. See [Sellora business flows](business-flows.md)
for what happens after the order exists.
