# Sellora business flows

This guide connects the tenant product areas into the business journeys that
the application actually runs. It follows the domain actions, not just the
database tables. Each linked guide covers its own records and rules in more
detail.

## From a product draft to a sellable item

1. Staff create a product and its variants in [Catalog](catalog.md). A
   variant is the exact item the shopper buys, such as a particular size and
   colour.
2. `PublishProduct` checks the product's publication requirements, including
   a name in the default language and at least one priced variant. Catalog
   determines whether an item can be sold; it does not decide stock.
3. [Pricing](pricing.md) interprets the variant's base price in the shopper's
   selected currency and calculates tax for the destination. [Inventory](inventory.md)
   independently determines whether physical stock is counted, available, or
   allowed to be backordered.
4. [Promotions](promotions.md) may discount eligible lines or shipping;
   [Shipping](shipping.md) determines the methods and prices for the
   destination. Neither domain changes the catalog's underlying product
   price.

## Shopper to order

1. [Customers](customers.md) provides an optional shopper account. A guest
   may shop if the store's [Settings](settings.md) allow guest checkout.
   `CreateCart` creates a guest-token cart or finds the customer's cart.
2. [Cart](cart.md)'s `PriceCart` calculates a current display quote. It draws
   on Catalog variants, Inventory availability, Pricing, Promotions,
   Shipping, and store defaults. It does not reserve stock or lock promotion
   uses.
3. `StartCheckout` opens or reuses the cart's open checkout.
   `UpdateCheckout` saves buyer contact/address/payment choices and refreshes
   the quote. Checkout keeps a short-lived quote and the cart version it was
   based on, not a stock hold.
4. On confirmation, `PlaceCheckoutOrder` locks and rechecks the checkout,
   cart version and quote. If the quote is stale, the buyer must review the
   refreshed quote; if it remains valid, one transaction reserves stock and
   promotion uses, creates the order and payment holds, records the buyer's
   explicit marketing choices, issues guest order access when applicable,
   and clears the cart.
5. The [Orders](orders.md) workflow snapshots the charged product, address,
   shipping, discount, tax and total information. Later changes to the
   catalog, prices, addresses or promotion do not rewrite that purchase.
6. Gateway communication happens after order placement has committed.
   [Payments](payments.md) verifies the gateway outcome and updates the
   order's payment state; pending or ambiguous attempts do not count as
   received money.

## Payment to fulfillment

1. When payment is confirmed, `MarkOrderAsPaid` marks the order paid,
   commits its promotion uses and fulfills any non-shipping lines. A late
   payment does not revive a cancelled order; it flags money as due back.
2. Staff create one or more shipments through `CreateShipment`. Shipment
   lines cannot exceed the order quantities that remain unshipped. The
   shipment records its fulfillment method and stock location.
3. `MoveShipment` advances the shipment through its allowed states. A
   carrier or own-delivery shipment commits reserved stock when marked
   shipped; pickup stock is committed when the buyer collects it. Order
   fulfillment progress is recalculated after each transition.
4. [Delivery](delivery.md) currently authenticates drivers, but does not
   dispatch or assign shipments. For store-run delivery today, the shipment
   lifecycle is owned by Orders and changed by authorized actors.
5. An order is completed when payment and fulfillment are both complete, or
   when no physical fulfillment is required.

## Cancellation, refund and sale documents

- Before shipment leaves, `CancelOrder` cancels open shipments, releases
  stock reservations and promotion uses, then records whether money is owed
  back. A payment arriving after cancellation is flagged for staff; it does
  not reopen the order.
- `RefundOrder` records a refund against successful payments (or money held
  by a cancelled order), then sends gateway portions after the database
  transaction. A refund does **not** put physical stock back; a future
  Returns workflow is a separate concern.
- An invoice is issued only once the order qualifies as a sale. A qualifying
  sale refund may result in a credit note. See [Orders](orders.md) and
  [Payments](payments.md) for their detailed rules.

## Where the other domains fit

- [Identity](identity.md) manages the store team and the permissions staff
  use for catalog, settings, order and payment operations. It is separate
  from shopper Customers and driver Delivery identities.
- [Settings](settings.md) supplies store-wide defaults used by other
  workflows, such as the base currency, tax mode, guest-checkout policy and
  business identity on documents.
- [Messaging](messaging.md) is not yet implemented as a tenant domain.
  Emails or other notifications currently belong to the feature that
  triggers them; this map does not imply a central messaging workflow.

## Domain guides

- [Cart](cart.md)
- [Catalog](catalog.md)
- [Checkout](checkout.md)
- [Customers](customers.md)
- [Delivery](delivery.md)
- [Identity](identity.md)
- [Inventory](inventory.md)
- [Messaging](messaging.md)
- [Orders](orders.md)
- [Payments](payments.md)
- [Pricing](pricing.md)
- [Promotions](promotions.md)
- [Settings](settings.md)
- [Shipping](shipping.md)
