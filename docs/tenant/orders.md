# Orders

An order is the permanent record of what a customer bought, what the store
charged, and how the purchase is paid and fulfilled. It is created from a
confirmed checkout. This guide focuses on the business lifecycle; field-level
detail is in the database's own column comments.

## What an order keeps

At placement the order copies the product and variant names, SKU, quantity,
unit prices, discounts, taxes, shipping choice, addresses, buyer contact,
currency, exchange rate and totals as they stood at that time. Later edits to
the catalog, tax setup, saved addresses or promotion do not rewrite an old
order.

An order has a public ID and a sequential number for people to read (for
example `#1042`); the number is not a secret and doesn't unlock anything.

It moves along three separate tracks:

- **Order:** open, completed or cancelled.
- **Payment:** unpaid, partially paid, paid, partially refunded or refunded.
- **Fulfilment:** unfulfilled, partially fulfilled, fulfilled or not
  required.

It also remembers when it was placed, when payment is due, and when it was
paid, fulfilled, completed or cancelled. A paid order that is cancelled is
marked as having money due back; that mark is not proof a refund was sent.
Whether it is a test order and whether it is paid on delivery are decided at
placement and never change.

Around the order:

- Each purchased variant becomes a line that can't change, with its labels,
  quantity, whether it ships, tax class and outcome, and its amounts.
- Each discount applied is kept with where it came from and its amount;
  product and order discounts belong to lines, shipping discounts don't.
  Taxes are kept per line and for shipping.
- The chosen shipping or pickup method is copied with its price, discount,
  tax and any delivery estimate.
- Addresses are versioned: the first version is what the buyer gave, and a
  correction adds a new version saying who made it.
- A timeline of events (placed, paid, cancelled, linked to a customer,
  fulfilment changes) is only ever added to, and holds no contact details or
  addresses.
- Staff can add notes and tags, which buyers never see.
- Shipments say which quantities go together, how, from which location, and
  where each shipment is: pending, ready for pickup, shipped, delivered or
  cancelled.
- A guest gets temporary access to their order without an account; only a
  hash of it is stored.
- Invoices and credit notes copy the order, buyer and seller details as they
  were when issued.

Order amounts and copies are financial records and are never overwritten.

## Example journey

1. A shopper confirms the checkout quote. Checkout reserves the stock and
   applicable promotion uses, then creates an order.
2. The order receives the next display number and freezes the purchase
   details. A guest receives a temporary order-access credential.
3. Payments are attempted or recorded. The order's payment state is derived
   from successful payments; a pending gateway attempt is not treated as
   money received.
4. Staff create one or more shipments. An order can be fulfilled in parts.
   Shipment progress drives fulfillment status; pickup becomes fulfilled
   when collected.
5. An order completes when it is paid and fulfillment is complete (or no
   fulfillment is required). A payment deadline can cause an unpaid order to
   be cancelled.
6. If money is returned, Payments records refunds. If the refund reduces a
   paid sale, the system can issue a credit note against the invoice.

## Historical and privacy rules

Orders and their financial line snapshots cannot be deleted or rewritten.
Statuses and allowed lifecycle fields change through the order workflow.
Personal details can be cleared through the privacy-erasure process while
financial values and required tax records remain. A guest order may be linked
to a customer only under the supported verified-account rules.

## Action flow and domain handoffs

Checkout invokes `PlaceOrder` only after the buyer's quote has passed its
final checks. It copies the charged items, addresses, shipping selection,
discounts, taxes and currency into order snapshots, then transfers the
checkout's stock and promotion holds to the order. The order is consequently
the durable record Payments and fulfillment operate on, rather than a live
view of mutable Cart or Catalog data.

Payment confirmation arrives from Payments through `MarkOrderAsPaid`.
Payment can commit promotion uses and stock for lines that do not ship. For
physical lines, staff use `CreateShipment` to split unshipped quantities
across shipments. `MoveShipment` validates each state transition, records an
order event, commits reserved stock when the goods leave or are collected,
and recomputes fulfillment. Shipping provides the chosen method; Inventory
provides the stock balance and reservation. For the store's own deliveries,
Delivery moves the shipment through `MoveShipment` (shipped on pick-up,
delivered on hand-over, returned when a failed delivery comes back); while a
driver has a shipment, staff can't ship or deliver it here (Orders asks
through its `ShipmentDrivers` contract). An own-delivery order counts as
fulfilled only once delivered, and a returned shipment's stock is back on
the shelf, held for the order, whose payment deadline starts again.

Before fulfillment leaves, `CancelOrder` cancels eligible open shipments and
releases active inventory and promotion holds. Payments handles any refund
that is owed. `IssueInvoice` and `IssueCreditNote` create immutable sale
documents when the order/refund qualifies; refunds themselves are handled by
Payments. For the complete lifecycle see
[Sellora business flows](business-flows.md).
