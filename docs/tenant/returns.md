# Returns

The Returns area lets a buyer send units of their order back to be
refunded or exchanged: asking for a return, staff approving, receiving and
inspecting it, putting what came back on the shelf or writing it off, and
then refunding the buyer or sending them a replacement. Field-level detail
is in the database's own column comments.

Returns never moves money or stock itself: refunds go through
[Payments](payments.md), stock through [Inventory](inventory.md), and an
exchange's replacement order through Checkout's
[replacement orders](checkout.md#replacement-orders-exchanges), each
through their own actions. A refund goes back to how the buyer paid unless
they chose another [destination](#where-a-refund-goes) a module offers.

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
   full. Or, for an exchange, staff confirm the replacement order, and the
   return completes once it is paid (see [Exchanges](#exchanges)). Or, when
   nothing is owed (such as damage the buyer caused), staff reject it and
   the items go back to the buyer.

The buyer can call a return off while it is still requested; staff can
cancel it any time before the items arrive.

```text
requested         → approved | rejected | cancelled
approved          → received | cancelled | completed | awaiting_exchange (the buyer keeps the items)
received          → inspected
inspected         → completed | rejected | awaiting_exchange
awaiting_exchange → completed | back to inspected or approved (the replacement expired or was cancelled unpaid)
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

**Reasons** are a fixed list, translated: damaged, wrong item, faulty, not
as described, doesn't fit, changed mind, other. `GET /api/v1/returns/reasons`
lists them in the store's language. The first four claim the store was at
fault; staff confirm or overrule that (see
[The store's fault](#the-stores-fault)).

**Refund or exchange.** A return is settled by a refund (`resolution`
`refund`, the default) or an exchange (`exchange`), naming a replacement
variant for every line (`lines.*.replacement`, same quantity). The
replacement must be for sale when asked for, and again at approval, but
nothing holds its stock until the exchange is confirmed.

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

### The store's fault

The buyer's reason is a claim: damaged, wrong item, faulty and not as
described say the store was at fault. Staff confirm it when they inspect
the items, or when they approve a return whose buyer keeps them
(`stores_fault`, true or false; left out, the claim stands). Saying
otherwise than the reason claims needs `fault_overruled_reason`
(`return_fault_reason_required`, 422 without it), shown in the return and
kept in its history; it is not for personal data. Until staff confirm, the
claim counts. Only a confirmed fault gives an exchange a free like-for-like
replacement and free delivery; it also decides whether a refund gives the
shipping back by default.

## Refunds

Staff with `returns.manage` and `payments.refund` refund an inspected
return, or an approved one whose buyer keeps the items. The refund goes
through Payments' `RefundOrder` as a refund of lines, so each unit comes
back at what the buyer paid for it after discounts, tax included:

- every unit that came back, written off or not;
- shipping, when `shipping` is true. Left out, it is given back when the
  store was at fault ([as confirmed](#the-stores-fault)), else not;
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

## Where a refund goes

A return's refund goes back to how the buyer paid (`original_payment`),
core's own destination and the only one without modules. A module can offer
others, such as store credit (`Wallet`) or a voucher (`GiftCards`), by
registering a `RefundDestination` with Payments' `RefundDestinations` from
its service provider, under its feature key.

**Who chooses.** The customer, never staff:

- A module's destination is offered only to the signed-in customer whose
  order it is, only while the module is Enabled, and only when the module's
  own rules allow. A guest's return always goes back to how they paid.
- The customer chooses when asking for the return (`refund_destination`),
  or afterwards (`PUT /customer/returns/{return}/refund-destination`), from
  the list for the order or the return.
- Staff (`returns.manage`) only record the customer's choice
  (`PUT /staff/returns/{return}/refund-destination`), with a required `note`
  on how the customer asked. It goes in the store's activity log; the audit
  trail leaves the note out.
- With no choice, the refund goes back to how the buyer paid.

**When.** The choice is open until a refund attempt starts. It reopens only
once the last attempt definitely got nothing back: refused by Payments, or
failed in full, such as a destination that clearly refused it or one no
module registers any more. While an attempt may be giving money back
(started, pending, in review or mixed) it is closed
(`refund_destination_choice_closed`, 409). Each attempt saves the
destination it asked for; the next attempt follows the latest choice.

**Only real money.** The choice covers only what was paid with real money
(card, transfer, cash). What a module's payment method paid, such as a gift
card, is never sent to the chosen destination: such a return is refused as
before (`return_refund_module_held`). Store-issued value can never be
turned into cash.

**How it is paid out.** A refund to a module's destination still draws its
parts from the order's payments, so what each can give back stays exact,
and it reduces the sale, so it gets a credit note. No gateway is ever asked
about it. After the refund is saved, the module pays it out, outside any
transaction, under a claim so two callers at once never both ask:

| The module | What happens |
|---|---|
| paid it out | every part succeeds, with the module's reference; the return completes |
| definitely refused (`refund_destination_refused`) | every part fails; the choice reopens |
| is no longer registered (`destination_not_registered`) or unavailable to the store (`destination_unavailable`) | every part fails, never a crash; the choice reopens |
| gave no answer (an error or a timeout) | asked again after 5, 30, 120 and 720 minutes, then sent to staff review (`destination_unclear`); the choice stays closed |

A destination already chosen is paid out whatever state its module is in
(Locked, Disabled, the store Suspended), except Unavailable: money in
motion is never blocked by plan gating. A test order's refund is marked as
such for the module.

## Exchanges

An exchange sends the buyer other items in place of the returned ones, as
a replacement order paid with what the returned items were worth. It never
reruns the original order's promotions.

**Choosing.** Staff with `returns.manage` change a return's replacements
(`PUT /staff/returns/{return}/replacements`), or how it is settled
(`PUT /staff/returns/{return}/resolution`: an exchange made a refund, its
replacements cleared, such as when nothing can be sent; or a refund made an
exchange, naming every replacement). Both only before the exchange is
confirmed, and never once a refund of it may have given money back. A
change of resolution goes in the store's activity log. Refunding an
exchange is refused (`return_is_an_exchange`, 409): make it a refund first.

**Preview.** Once the return is inspected (or approved with the buyer
keeping the items), staff with `returns.view` preview it
(`GET /staff/returns/{return}/exchange-preview`, optionally with a
`shipping_method`; the way the order was sent otherwise). Before that it is
`exchange_not_ready` (409). The preview prices the replacement with
Checkout's replacement quote, today's prices and today's automatic
promotions, and shows:

- **What the returned items are worth:** what their units cost the buyer
  after discounts, tax included, as a refund would give back.
- **Like-for-like:** with a [confirmed fault](#the-stores-fault), a
  replacement of the same product at the same price as the returned variant
  today (or, when that has no price today, as what the buyer paid) costs no
  more than the returned units did.
- **Delivery:** free when the store was at fault; otherwise charged only
  when the return setting `charge_shipping_on_exchanges` is on (off by
  default).
- **The settlement:** what the returned items credit toward the
  replacement, the difference that goes back to the buyer when the
  replacement costs less, and what the store keeps (only for a free
  like-for-like replacement that cost less than the returned units). What
  the buyer still owes on a dearer replacement is the replacement order's
  amount to pay.
- **`quote_hash`**, to confirm with.

**Confirming.** Staff with `returns.exchange` (sensitive) and
`payments.refund` confirm it as previewed (`POST /staff/returns/{return}/exchange`,
with the `quote_hash` and an `Idempotency-Key`). In one transaction the
return is locked, the replacement order is placed through Checkout, with
the credit held on it and its stock and promotion uses held, and the
exchange is saved; the return moves to `awaiting_exchange`. When anything
changed since the preview, or the replacement can't be sent, nothing is
placed and the answer is 409 with the new preview. Confirming again returns
the same exchange.

**Settling.** When the credit covers the replacement, it is paid at once;
otherwise the buyer pays the rest as for any order. Once it is paid:

1. The original order gets a refund of the returned lines to the
   `exchange` destination, for what they credited, the rest withheld, so
   its credit note credits exactly that. It is made once, under the key
   `exchange:{exchange id}`, however often settling runs.
2. With no difference, the return completes.
3. A difference goes back to the buyer by amount, to the
   [destination](#where-a-refund-goes) they chose, as a refund attempt of
   the return, under the same rules as any refund. It reduces the sale, so
   it gets a credit note. Once it went back, the return completes.

Nothing is refunded while the replacement is unpaid. A replacement that
expires or is cancelled unpaid releases the exchange: the return goes back
to inspected, or approved when the buyer keeps the items, with its earlier
times, ready for another exchange or a refund.

If Payments refuses the refund or the difference, the credit stays final;
the refusal is logged and sent to the error tracker. Staff settle again
from the return's refund endpoint, which on a return awaiting its exchange
settles it (an exchange not yet settled) or gives the difference again (one
whose last attempt definitely failed, after the customer may choose again).

**Open.** A paid replacement that is cancelled leaves its order flagged
`refund_due` for staff: its credit can't be given back yet, and how it goes
back is still to be decided. Which part of a split-paid original order goes
back first is decided with the first payment module.

## Privacy and retention

A return's note and photos are the buyer's personal data, and so are
staff's notes on how the buyer asked for their refund to go somewhere. When
an order is anonymised (its customer or guest buyer is erased, or its
retention ends), its returns lose their note, photos and those notes in the
same erasure run and are marked anonymised; the photo files are deleted
once that run commits. The returns keep their lines, statuses, times,
refunds and destination choices. A customer's or guest's data export
includes their returns, with every destination choice.

| What | Kept for | Setting |
|---|---|---|
| Return photos | 180 days after the return ends (completed, rejected or cancelled) | `RETENTION_RETURN_PHOTOS_DAYS` |
| Returns, their lines, history, refund attempts, destination choices and exchanges | as business records | — |

Every Returns table and column is classified for the store export; photos
are in it until purged.

## Permissions

| Permission | Lets staff |
|---|---|
| `returns.view` | see returns, their history, refund attempts, photos and exchanges, preview an exchange, and see the return settings |
| `returns.manage` | start, approve, reject, receive, inspect and cancel returns, confirm the store's fault, change replacements and the resolution, record where the customer asked a refund to go, and change the return settings |
| `payments.refund` (with `returns.manage`) | refund a return, and approve one with the buyer keeping the items |
| `returns.exchange` (sensitive, with `payments.refund`) | confirm an exchange, placing its replacement order |

## Endpoints

Every endpoint has a cURL example in [API testing](api-testing.md).

| Who | Endpoints |
|---|---|
| Anyone | `returns/reasons` |
| Customer, under `/api/v1/customer` | `orders/{order}/returns` (ask), `orders/{order}/refund-destinations`; `returns` (list), `returns/{return}`, `returns/{return}/cancel`, `returns/{return}/refund-destinations`, `returns/{return}/refund-destination` (choose) |
| Guest, under `/api/v1/guest/orders/{order}/returns`, with `X-Order-Token` | ask, list, `{return}`, `{return}/cancel` |
| Staff, under `/api/v1/staff` | `orders/{order}/returns` (start); `returns` (list by status or order), `returns/{return}`, `/photos/{position}`, `/approve`, `/reject`, `/receive`, `/inspect`, `/refund` (with an `Idempotency-Key`), `/cancel`, `/refund-destination` (record the customer's choice), `/replacements`, `/resolution`, `/exchange-preview`, `/exchange` (confirm, with an `Idempotency-Key`); `returns/settings` (show, change) |

Lists are paged by cursor.

## Configuration

| Setting | Default | What it does |
|---|---|---|
| `RETURN_PHOTOS_DISK` | `local` | the private disk photos are kept on |
| `RATE_LIMIT_RETURN_REQUESTS` | 5 | requests a minute per customer or order token |
| `RATE_LIMIT_RETURN_REQUESTS_IP_CEILING` | 60 | requests a minute per address |
| `RETENTION_RETURN_PHOTOS_DAYS` | 180 | see Privacy and retention |

The window, buyers' requests, required photos and
`charge_shipping_on_exchanges` are return settings, changed by staff. Every
store has its row from the moment it is set up, with the defaults in
`config/returns.php`; a missing row is an error, never filled in.

## Action flow and domain handoffs

`RequestReturn` checks the settings for buyers, locks the order's returns
(an advisory lock), works out what is left of each line from Orders'
shipments and Payments' `FindRefundedUnits`, and keeps the photos.
`ApproveReturn`, `RejectReturn`, `ReceiveReturn`, `CancelReturn` and
`InspectReturn` each lock the return and move it on; `InspectReturn` calls
Inventory's `RestockReturnedUnits` and `WriteOffReturnedUnits`, and
`RejectReturn` of an inspected return calls `SendReturnedUnitsBack`.
`RefundReturn` saves an attempt with the customer's chosen destination,
calls Payments' `RefundOrder` outside its transaction, and records what
Payments answered. `ChooseRefundDestination` locks the return, checks the
choice is open, and asks Payments' `FindRefundDestinations` whether the
destination is on offer. For a refund to a module's destination, Payments'
`GiveRefundToDestination` asks the module after commit, and its
`GiveRefundToDestinationAgain` job and the hourly sweep ask again after an
unknown outcome.

For exchanges, `ChangeReturnReplacements` and `ChangeReturnResolution` lock
the return and check the replacements with Catalog. `PreviewExchange` works
out the terms (`ReturnExchangeTerms`, with Payments'
`FindReturnedUnitValues`) and asks Checkout's `QuoteReplacementOrder`;
`ConfirmExchange` locks the return and calls Checkout's
`PlaceReplacementOrder`, whose quote hash is the one check of what staff
were shown. `SettleExchange` makes the refund to the exchange with
`RefundOrder` and the difference with `RefundReturn`, each outside its
transaction.

Returns listens to Payments' `RefundSucceeded`, `RefundFailed` and
`RefundNeedsReview`, after Payments commits, to bring its attempts up to
date and complete the return; to Checkout's `ExchangeCreditCommitted` and
`ExchangeCreditReleased`, after Checkout commits, to settle or release an
exchange; and to Orders' `OrderAnonymised`, to clear the buyer's data. Locks go: the order's returns, then the return, then
(through Payments) the order and its payments, then (through Inventory)
stock levels; nothing locks the order before the return.

Returns announces `ReturnRequested`, `ReturnApproved`, `ReturnRejected`,
`ReturnReceived`, `ReturnInspected`, `ReturnRefundStarted` (once per
attempt Payments made a refund for), `ReturnCompleted`, `ReturnCancelled`
`ReturnRefundDestinationChosen`, `ReturnExchangeConfirmed` (the return
awaits its replacement) and `ReturnExchangeReleased` (the replacement
expired or was cancelled unpaid, and the return went back), each once it is
saved, for Messaging (Step 20). Payments announces `RefundGivenToDestination` and
`RefundDestinationFailed` for refunds to a module's destination.
