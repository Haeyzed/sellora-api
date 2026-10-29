# Returns

The Returns area lets a buyer send units of their order back to be
refunded: asking for a return, staff approving, receiving and inspecting
it, putting what came back on the shelf or writing it off, and refunding
the buyer. Field-level detail is in the database's own column comments.

Returns never moves money or stock itself: refunds go through
[Payments](payments.md) and stock through [Inventory](inventory.md), each
through their own actions. Exchanges come in Step 18b; until then every
return is settled by a refund.

## A return from start to finish

1. The buyer asks for a return of delivered units, with a reason, an
   optional note and, when the store wants them, photos. Or staff start one
   for the buyer.
2. Staff approve it, or reject it with a reason. Staff can also let the
   buyer keep the items: nothing comes back, and the return goes straight to
   its refund.
3. The items arrive and staff mark the return received.
4. Staff inspect the items at a stock location: each unit goes back on the
   shelf or is written off as damaged.
5. Staff refund it. The return completes once the refund has gone back in
   full. Or, when nothing is owed (such as damage the buyer caused), staff
   reject it and the items go back to the buyer.

The buyer can call a return off while it is still requested; staff can
cancel it any time before the items arrive.

```text
requested → approved | rejected | cancelled
approved  → received | cancelled | completed (the buyer keeps the items)
received  → inspected
inspected → completed | rejected
```

Each return is numbered within its order, shown as the order number and its
own, such as `#1042-R1`. Every status change is kept, with who made it.

## Asking for a return

**What can be returned.** A line's units that were delivered, less those
already in a return that is still open (anything but rejected or cancelled)
and those refunded by line. Requests for the same order queue on a lock, so
two at once never claim the same units.

**Buyers** (a signed-in customer for their own order, or a guest with the
order's access token in the `X-Order-Token` header) can ask only:

- while the store takes buyers' requests (a store setting, on by default);
- for units delivered within the return window: 14 days from delivery by
  default, a store setting from 0 to 365 days (0 means only staff start
  returns);
- with a photo when the reason is damaged or the wrong item and the store
  requires one (a store setting, off by default).

**Staff** with `returns.manage` can always start a return for the buyer,
whatever the settings, and may go further, saying why:

- for units delivered before the window (`outside_window_reason`);
- for units shipped but never marked delivered (`undelivered_reason`). To
  find such shipments, list `GET /api/v1/staff/shipments?status=shipped`
  (`orders.view`); a shipment that did arrive can be marked delivered with
  `POST /api/v1/staff/shipments/{shipment}/deliver` (`orders.fulfil`).

Nobody can return units of a cancelled order, an order never sold, or an
order whose buyer's personal data was anonymised.

**Reasons** are a fixed list, translated: damaged, wrong item, not as
described, doesn't fit, changed mind, other. `GET /api/v1/returns/reasons`
lists them in the store's language.

**Photos.** Up to 5 JPEG, PNG or WebP images of at most 5 MB and 4096
pixels each. Each is re-encoded without its metadata, kept on the store's
private disk and counted towards the plan's storage. Staff download them one
by one, never cached; buyers only see how many there are.

## Approving, receiving and inspecting

- **Approve** a requested return. Letting the buyer keep the items
  (`keep_items`) refunds without anything coming back, so it also needs
  `payments.refund`.
- **Receive** an approved return whose items come back. Nothing moves in
  stock yet.
- **Inspect** a received return at an active stock location (the store's
  default when none is given). Say, for every line, how many units go back
  on the shelf and how many are written off; together they must add up to
  the line's quantity. Inventory records restocked units as `restocked` and
  damaged ones as `returned_damaged` then `written_off`, with the return as
  their source. Variants whose stock isn't counted are left out.
- **Reject** a requested return, or an inspected one when nothing is owed,
  with a reason the buyer sees. Rejecting an inspected return sends its
  units back to the buyer through Inventory, as new movements
  (`write_off_reversed` for written-off units, then `sent_back`) in the same
  transaction, so on hand ends where it was before the inspection. If
  restocked units were sold or held since, the rejection is refused
  (`returned_units_no_longer_on_hand`) and nothing changes.
- **Cancel** a return before its items arrive.

A return whose refund may have given money back (anything but refused or
failed) can't be rejected or cancelled.

Each change works from the return as saved under its lock, so two staff
approving, rejecting, cancelling or inspecting it at the same moment never
both succeed: the second gets `return_transition_not_allowed` (409).

## Refunds

Staff with `returns.manage` and `payments.refund` refund an inspected
return, or an approved one whose buyer keeps the items. The refund goes
through Payments' `RefundOrder` as a refund of lines, so each unit comes
back at what the buyer paid for it after discounts, tax included:

- every unit that came back, written off or not;
- shipping, when `shipping` is true. Left out, it is given back when the
  reason is damaged or the wrong item (the store was at fault), else not;
- less `withheld` (minor units) with a `withheld_reason`, such as when the
  return breaks a discount's condition. It comes off the lines, so their
  credit note credits only what went back.

Payments decides where the money comes from (the newest payments first)
and issues a credit note for each part that goes back; a test order's are
in the test series.

**Never twice.** Each attempt is saved before Payments is asked, with the
idempotency key `return:{return id}:{attempt}` and exactly what it asks
for. Payments is asked outside any of Returns' transactions, since it calls
gateways after its own commit. Asking again looks the last attempt up first:

| Last attempt | What happens |
|---|---|
| started (Payments never answered) | asked again under the same key, with the same request; Payments returns the refund it made, if any |
| pending (the gateway hasn't said) | shown as it stands; Payments keeps checking |
| in review (Payments can't tell whether it went through) | shown as it stands, for staff to settle in Payments; never tried again |
| mixed (part went back, part failed, across payments) | shown as it stands, for staff to settle; never tried again whole |
| succeeded | the return is completed |
| refused (Payments refused it; the code is saved) or failed (every part failed) | a new attempt under the next key |

The return stays inspected (or approved) until a refund has gone back in
full, then completes, once. When the gateway reports a refund as failed,
staff try again, and the first then goes through after all, both attempts
show as succeeded: the return stays completed, and Payments raises
`refund_succeeded_after_failing` and Orders flags the order as refunded
beyond what was paid, for staff to recover the money.

**What Payments refuses**, the return records on the attempt and shows:

- cash on delivery not yet recorded as collected (`order_not_refundable`);
- units refunded directly first, or a refund by amount that leaves too
  little (`refund_exceeds_refundable`, `refund_exceeds_sale`);
- a gateway that can't refund part of a payment
  (`partial_refund_not_supported`);
- the part a module's payment method holds, such as a gift card, which today
  only gives money back when the whole order is cancelled
  (`return_refund_module_held`): refund what was paid otherwise by amount
  from the order's payments.

## Privacy and retention

A return's note and photos are the buyer's personal data. When an order is
anonymised (its customer or guest buyer is erased, or its retention ends),
its returns lose their note and photos in the same erasure run and are
marked anonymised; the photo files are deleted once that run commits. The
returns keep their lines, statuses, times and refunds. A customer's or
guest's data export includes their returns.

| What | Kept for | Setting |
|---|---|---|
| Return photos | 180 days after the return ends (completed, rejected or cancelled) | `RETENTION_RETURN_PHOTOS_DAYS` |
| Returns, their lines, history and refund attempts | as business records | — |

Every Returns table and column is classified for the store export; photos
are in it until purged.

## Permissions

| Permission | Lets staff |
|---|---|
| `returns.view` | see returns, their history, refund attempts and photos, and the return settings |
| `returns.manage` | start, approve, reject, receive, inspect and cancel returns, and change the return settings |
| `payments.refund` (with `returns.manage`) | refund a return, and approve one with the buyer keeping the items |

## Endpoints

Every endpoint has a cURL example in [API testing](api-testing.md).

| Who | Endpoints |
|---|---|
| Anyone | `returns/reasons` |
| Customer, under `/api/v1/customer` | `orders/{order}/returns` (ask), `returns` (list), `returns/{return}`, `returns/{return}/cancel` |
| Guest, under `/api/v1/guest/orders/{order}/returns`, with `X-Order-Token` | ask, list, `{return}`, `{return}/cancel` |
| Staff, under `/api/v1/staff` | `orders/{order}/returns` (start); `returns` (list by status or order), `returns/{return}`, `/photos/{position}`, `/approve`, `/reject`, `/receive`, `/inspect`, `/refund` (with an `Idempotency-Key`), `/cancel`; `returns/settings` (show, change) |

Lists are paged by cursor.

## Configuration

| Setting | Default | What it does |
|---|---|---|
| `RETURN_PHOTOS_DISK` | `local` | the private disk photos are kept on |
| `RATE_LIMIT_RETURN_REQUESTS` | 5 | requests a minute per customer or order token |
| `RATE_LIMIT_RETURN_REQUESTS_IP_CEILING` | 60 | requests a minute per address |
| `RETENTION_RETURN_PHOTOS_DAYS` | 180 | see Privacy and retention |

The window, buyers' requests and required photos are store settings, changed
by staff; their defaults are in `config/returns.php`.

## Action flow and domain handoffs

`RequestReturn` checks the settings for buyers, locks the order's returns
(an advisory lock), works out what is left of each line from Orders'
shipments and Payments' `FindRefundedUnits`, and keeps the photos.
`ApproveReturn`, `RejectReturn`, `ReceiveReturn`, `CancelReturn` and
`InspectReturn` each lock the return and move it on; `InspectReturn` calls
Inventory's `RestockReturnedUnits` and `WriteOffReturnedUnits`, and
`RejectReturn` of an inspected return calls `SendReturnedUnitsBack`.
`RefundReturn` saves an attempt, calls Payments' `RefundOrder` outside its
transaction, and records what Payments answered.

Returns listens to Payments' `RefundSucceeded`, `RefundFailed` and
`RefundNeedsReview`, after Payments commits, to bring its attempts up to
date and complete the return; and to Orders' `OrderAnonymised`, to clear
the buyer's data. Locks go: the order's returns, then the return, then
(through Payments) the order and its payments, then (through Inventory)
stock levels; nothing locks the order before the return.

Returns announces `ReturnRequested`, `ReturnApproved`, `ReturnRejected`,
`ReturnReceived`, `ReturnInspected`, `ReturnRefundStarted` (once per
attempt Payments made a refund for), `ReturnCompleted` and
`ReturnCancelled`, each once it is saved, for Messaging (Step 20).
