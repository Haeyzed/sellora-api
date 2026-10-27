# Shipping

Shipping configures the customer-facing choices for sending an order,
delivering it with the store's own drivers, or pickup at a store location. It
also calculates which options are offered and what they cost. Field-level
detail is in the database's own column comments.

## Zones: where a method is offered

A zone names a group of destinations, such as a country or region, and lists
the countries (and optionally states) it covers.
A destination region belongs to at most one zone. A state-specific zone
takes priority over a country-wide zone. One zone may cover the rest of the
world; it cannot also list regions. An inactive zone does not offer its
methods.

## Methods: what the buyer can choose

A shipping method has a translated name/description, fulfillment
type, price method, display position and active/archive status.

- It is fulfilled by a carrier (ship), by the store's own drivers, or by
  pickup.
- A method is tied to a shipping zone, except pickup. Pickup names
  the stock location and the pickup address and instructions, and is offered to
  shoppers in its country.
- Its price can be flat, based on parcel weight, based on order value, or
  quoted live by a carrier integration. **Planned:** no carrier integration
  is built yet; core has the carrier contract, and carriers are roadmap
  integrations (CLAUDE.md section 18), not in the numbered build order.
- A free-over threshold can make the method free when the value of shippable
  items after discounts reaches the threshold.
- Delivery estimates can show minimum/maximum business days.
- Carrier services can have a backup price if the live carrier cannot quote.
- Methods are archived, not deleted, so old orders preserve what was
  selected.

Rates are brackets that don't overlap. A bracket includes its start and
excludes its end; no end means “and above.” Weight
brackets use grams; order-value brackets use minor units of the base currency.
Gaps mean no price is available for that range. Exact prices and thresholds can be set in other currencies; otherwise the
store's checkout exchange rate is used.

## How a shipping quote works

1. The cart/checkout identifies the destination and items requiring physical
   shipping. Digital/service items do not add parcel weight.
2. Shipping finds the matching zone, then its active methods plus pickup
   options available in the destination country.
3. It totals shipping weight and eligible item value after discounts, then
   selects the method's applicable flat/weight/value rate.
4. A live carrier rate may need the buyer's full address. Before then it can
   be shown as calculated at checkout. If a carrier cannot quote and no
   backup is configured, that method is not offered.
5. Checkout saves the selected option in its quote. When the order is placed,
   the chosen method, amounts, tax, pickup details and estimate are copied
   into the order so later configuration edits do not change the sale.

A basket with no shippable items needs no shipping method. A free-shipping
promotion can reduce shipping to zero.

## Shipping versus Delivery and Inventory

Shipping decides what a shopper can choose and what that choice costs.
Orders owns shipment records and statuses. Inventory identifies the stock
location tied to pickup or fulfillment. The separate Delivery area currently
supports driver sign-in, but it does not yet assign shipments to drivers.

## Action flow and domain handoffs

`QuoteShipping` receives the destination, exchange rate and shippable basket
lines. It filters out items that do not require physical shipping, finds the
matching zone, calculates total weight/value, and returns eligible flat,
weight, value, pickup or carrier options. A carrier may need the full address
for a live quote. The quote is calculated, not saved as an order or
reservation.

Cart uses the quote to show possible methods and Checkout keeps the buyer's
selected method in its expiring quote. Promotions may reduce the shipping
charge before Pricing calculates tax. At order placement, Orders copies the
selected method, fulfillment type, price, tax, pickup/carrier details and
estimate into its immutable shipping snapshot. Orders then creates and moves
shipment records; Inventory commits stock at shipping or pickup collection.
Delivery currently only signs drivers in and does not assign these shipments.

See [Sellora business flows](business-flows.md) for the full path from quote
to fulfillment.
