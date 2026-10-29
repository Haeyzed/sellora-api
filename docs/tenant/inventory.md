# Inventory

This product guide explains inventory in everyday business terms. It
describes the behavior currently implemented in the tenant application.
Field-level detail is in the database's own column comments, not here.

## The short version

Inventory keeps five kinds of record, each with its own job:

- **Stock locations:** places where a store keeps or fulfills stock.
- **Inventory settings:** per variant, whatever the location: whether its
  stock is counted, whether it can be backordered, its low-stock warning
  level and its current unit cost.
- **Stock levels:** how many units of one variant are at one location, and
  how many of those are held.
- **Stock movements:** the history of every change to a stock level. It is
  only ever added to.
- **Stock reservations:** quantities currently held for checkouts and orders.

Stock quantities are whole units. For each stock level:

```text
available = on hand - reserved
```

*On hand* is the physical quantity recorded at that location. *Reserved* is
the quantity held for active checkouts and orders. A reservation does not
remove items from the shelf; fulfillment does.

## Location support in core

Every store gets one location, **Main location**, which is its default.
Stock levels, movements and reservations all name their location, so more
locations can be added later. However, core's `DefaultStockLocationSelector`
currently selects the single default location for order reservations. The
roadmap describes the `MultiLocation` module as the later addition for
multiple warehouses and physical stock transfers.

Therefore, the records are location-aware, but the full multi-location
fulfillment and transfer workflow is not implemented in core. In particular,
`TransferReservation` transfers a hold from a checkout to its resulting order;
it does **not** transfer physical stock between locations.

## Rules the database enforces

- At most one location is the default, and the default must stay active.
  A location that stock, movements or reservations refer to can't be
  deleted.
- A variant has at most one settings record. Without one, its stock is
  counted and backorders are not allowed. A variant without its own
  low-stock level uses the store-wide one in store settings (5 unless
  changed); neither can be negative.
- The unit cost is one current cost per variant in the store's base currency,
  kept to six decimal places, for staff only. A receipt with a cost updates
  it.
- Each variant has one stock level per location. On hand and reserved can't
  go below zero, but reserved may exceed on hand (with backorders, or after a
  recount finds a shortage), so available can be negative until stock
  arrives or holds are resolved. `StockLedger` is the only writer: it locks
  the stock level and adds the matching movement in the same transaction.
- A movement records one change to one stock level: its kind (received,
  adjusted, reserved, released, committed or expired), an optional reason and
  note, the signed change to on hand and to reserved, both balances after it,
  and optionally who caused it and the record it came from. A received
  movement can keep that receipt's unit cost. The database refuses a movement
  that changes nothing or leaves a negative balance, and refuses edits and
  deletions: a correction is a new movement.
- A reservation is held by a checkout or an order, for one variant at one
  location. It is active, committed, released or expired; only an active one
  holds stock. Its quantity includes any backordered part. A checkout's hold
  expires; an order's doesn't. A holder has at most one active reservation
  per variant and location, so reserving again changes that one.

## How the lifecycle works

### 1. Receive stock

Suppose a delivery brings 10 units of a variant to Main location, at a unit
cost of `5.50`:

- The stock level becomes `on_hand = 10`, `reserved = 0`.
- A movement has `type = received`, `on_hand_delta = 10`,
  `reserved_delta = 0`, and after-values `10` and `0`.
- If the receipt supplied `unit_cost = 5.50`, that cost is recorded on the
  movement and becomes the current unit cost for the
  variant. Since inventory settings are variant-level, this current cost is
  not different for Main location versus another location.

### 2. Reserve stock for checkout/order

A customer checks out with 3 units:

- An active reservation is added with `quantity = 3` and
  `backordered_quantity = 0`.
- The stock level stays at `on_hand = 10`, but `reserved` becomes `3`.
- A `reserved` movement records `on_hand_delta = 0`, `reserved_delta = 3`,
  and after-values `10` and `3`.
- Available stock is now `10 - 3 = 7`.

The reservation holds the units; it does not claim they have physically left.
If the customer abandons checkout, the hold can later expire and be released.

### 3. Fulfill all or part of the reservation

If 2 of those 3 units are shipped:

- A `committed` movement records `on_hand_delta = -2` and
  `reserved_delta = -2`.
- The balance becomes `on_hand = 8`, `reserved = 1`.
- The reservation remains active with `quantity = 1`.

When the last unit ships, the same deltas are applied for 1; the reservation
becomes `committed` and receives a `settled_at` timestamp. A committed
reservation cannot be reused as an active hold.

### 4. Release or expire a hold

If the order is cancelled instead of shipped, releasing all 3 units records:

- `on_hand_delta = 0` because the units never left physically.
- `reserved_delta = -3`.
- The balance returns to `on_hand = 10`, `reserved = 0`.
- The reservation status becomes `released`.

An expired checkout hold works similarly, but has status `expired` and a
movement type of `expired`. A scheduled sweep releases expired holds.

When a checkout becomes an order, the reservation is handed from the checkout
holder to the order holder and its expiry is removed. No quantities change,
so this holder change does not create a stock movement.

### 5. Backorder example

Suppose only 2 units are on hand and an order requests 5. If that variant
allows backorders:

- The reservation can have `quantity = 5` and `backordered_quantity = 3`.
- `on_hand` stays `2`; `reserved` becomes `5`.
- `available = 2 - 5 = -3`, showing that the active holds exceed physical
  stock by 3.
- Receiving 3 more units raises `on_hand` to `5`; the reservation remains
  held, and available becomes `0`.
- The order can be committed only when physical `on_hand` covers the quantity
  being fulfilled.

Without backorders allowed, a reservation that exceeds available stock is
rejected. A variant whose stock is not counted is treated as always
available and does not create a reservation.

### 6. Manual adjustment

For damage, a staff member may adjust the on-hand amount by a signed change or
set it to a physical recount. For example, removing 2 damaged units records
an `adjusted` movement with `on_hand_delta = -2`; reserved quantity is
unchanged. On-hand cannot be adjusted below zero. A recount can result in
on-hand falling below reserved, recording the real count even if that means
the store is now short against its holds.

### 7. Units a buyer sent back (Returns)

When staff inspect a [return](returns.md) at a location, Inventory records
what came back, with the return as the source and nothing held for anyone:

- a resellable unit is `restocked`: `on_hand_delta = +1`;
- a damaged unit is recorded as it arrives, `returned_damaged` (`+1`), and
  written off at once, `written_off` (`-1`), so the ledger shows every unit
  that came back even when none can be sold.

If staff then reject the return and the goods go back to the buyer, nothing
is edited: written-off units have their write-off reversed
(`write_off_reversed`, `+n`) and every unit leaves again (`sent_back`,
`-n`), so on hand ends where it was before the inspection. If restocked
units were sold or held since (fewer are free than are to leave), that is
refused and nothing changes. Variants whose stock isn't counted are left
out throughout.

## Transfers and cost: important distinctions

- **Stock transfer:** there is no physical location-to-location transfer
  movement type or transfer workflow in core yet. A real future transfer
  would need to reduce stock at a source location and increase it at a
  destination, linked as one business operation. `MultiLocation` is roadmap
  work; do not mistake the existing checkout-to-order reservation handoff
  for a physical stock transfer.
- **Cost:** the unit cost in inventory settings is one current cost per
  variant for the store. The cost kept on a received movement is that
  receipt's history, and each receipt movement also names its location.
  Nothing calculates a weighted average or a current cost per location.
- **Fulfillment locations:** shipping methods can refer to a stock location,
  and shipments record a stock location. These are operational references to
  where an order is fulfilled/picked up; they do not change the variant-level
  scope of the unit cost.

## Action flow and domain handoffs

`ReceiveStock` records incoming units as a positive `received` movement at a
specific location. If a unit cost is supplied, the receipt preserves that
cost in movement history and also updates the current variant-level unit cost.

At checkout placement, `ReserveStock` selects the configured stock location,
locks its balance and attempts to hold all counted lines together. Untracked
variants need no hold; backorder-enabled variants can be held beyond on-hand
stock. A successful checkout transfers its reservation to the resulting
order without moving physical stock. Cancelling or expiring a holder releases
the hold. The Orders workflow commits the reservation when physical goods
leave: when a shipping/own-delivery shipment is shipped, when a pickup is
collected, or when a paid line does not require shipping.

Returns calls `RestockReturnedUnits` and `WriteOffReturnedUnits` when a
return is inspected, and `SendReturnedUnitsBack` when an inspected return is
rejected, each inside Returns' transaction and after its lock on the return.

These action handoffs explain why `reserved_delta` changes availability
without changing physical `on_hand`, and why `on_hand_delta` becomes
negative only when stock is committed or adjusted out. Cart may display
availability before any hold exists; Checkout's successful placement is the
point that prevents two buyers from reserving the same counted units. See
[Sellora business flows](business-flows.md).
