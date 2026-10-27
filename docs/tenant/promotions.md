# Promotions

Promotions are the store's automatic or code-based discounts. They can reduce
eligible product lines, the order, or shipping. This guide describes the
merchant and shopper experience; field-level detail is in the database's own
column comments.

## Promotion setup

A promotion has a name, a translated customer-facing title,
the method (automatic or code), the discount type, eligibility, dates and
usage rules.

Supported types:

- Percentage off products or the order, optionally capped at a maximum.
- Fixed amount off products or the order.
- Free shipping.
- Buy X, get Y at a percentage discount.

Settings may include a minimum subtotal or quantity, whether sale items
qualify, first-order-only eligibility, maximum total/per-customer use, and
whether it combines with other product/order/shipping discounts. The start
time is inclusive; an end time is exclusive.

A code promotion has codes shoppers can enter. Codes are case-insensitive
for matching and unique ignoring case; the code can have its own usage limit
and active flag.

Targets narrow or define eligibility:

- Products, variants or categories can be included or excluded (these also
  give the “get” items for Buy X Get Y).
- Customer groups, which staff assign by hand, can limit who may use it.
- A promotion can set exact fixed amounts, caps or minimums in a specific
  offered currency. Without an exact amount, the base
  currency amount is converted using the checkout rate.

## How discounts are chosen

During cart/checkout pricing, each eligible discount source proposes a
discount. The system allows at most one product discount per line, one order
discount and one shipping discount. Discounts combine only when the
promotions' combination settings allow it. Among allowed combinations, the
buyer gets the combination that saves the most; ties favor the older
promotion. A discount cannot reduce an item below zero.

Entering a code does not necessarily mean it applies immediately. For
example, a valid code can remain on a cart while the minimum spend is not
met; adding another item may make it apply. The cart shows the reason when a
code is currently not applicable.

## Usage holds and redemption scenario

1. A shopper enters a code or qualifies for an automatic offer.
2. The cart calculates the discount but does not permanently consume a use.
3. On checkout placement, each limited promotion use is reserved, alongside
   stock. This prevents two buyers from taking the last permitted use.
4. When the checkout becomes an order, the reservation follows the order.
   Once paid, the use is committed. If the checkout expires or the order is
   cancelled, the use is released.

Each use is recorded: the promotion and code,
checkout or order holder, customer/email where known, discount amount,
currency, status and expiry/settlement times. Usage limits are counted from
these records rather than from a counter that can drift.

## Merchant control

Promotions can be paused or archived; archiving keeps historical
relationships and prevents future use. Code-attempt rate limits reduce
guessing. The calculator and reservation process re-check eligibility and
limits at placement, so a code displayed in a cart cannot guarantee the
discount if its limits run out before the order is placed.

## Action flow and domain handoffs

Cart and Checkout use `PriceWithDiscounts` to price the basket, apply
eligible offers through `ApplyDiscounts`, then pass the discounted line and
shipping amounts into Pricing so tax is calculated on what the buyer pays.
The eligible buyer context can include a signed-in Customer and their
customer groups. A code may be shown as not currently applicable without
being consumed.

When `PlaceCheckoutOrder` commits a valid checkout, it reserves the selected
limited promotion uses alongside Inventory stock. Orders transfers the live
reservation to the new order. `SettlePromotionUses` commits those uses when
the order is paid, or releases them when an unpaid order is cancelled; an
abandoned checkout's expiring hold can also be swept. A refund does not
restore promotion capacity. The shopper sees the discount in Cart, confirms
it in Checkout, and the final amount is frozen in the Orders snapshot. See
[Sellora business flows](business-flows.md).
