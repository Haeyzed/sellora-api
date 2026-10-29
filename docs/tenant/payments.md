# Payments

Payments answers two different questions: how much money has actually
arrived for an order, and whether money has been returned. A payment attempt
is not counted as received until it succeeds or staff confirms a manual
payment. Field-level detail is in the database's own column comments, not
here.

## What a payment holds

A payment is one attempt or receipt toward one order. An order may have
several, such as a gift-card portion followed by a gateway payment.

- It knows its order, its method, the gateway connection it went through
  and whether it was in test or live mode.
- It keeps what was expected and, once, what actually arrived.
- It is pending, succeeded, failed, needs review, unresolved or cancelled.
  Only succeeded money in the order's currency counts toward the balance.
- It has references for safe retries and for matching it with the gateway.
  At most a card brand and last four digits are kept if the gateway gives
  them; full card details never are.
- A manual receipt records which staff member confirmed it.
- Each payment has a timeline that is only ever added to.

## Gateway and manual-method settings

- A store's gateway connection says which gateway it connected, whether it
  is offered for new payments, its priority and its test or live mode. Keys
  are stored encrypted and never appear in API answers, audit records or
  exports. If the gateway refuses the keys, the connection is marked as
  needing attention and staff are told. Turning a gateway off never blocks
  payments already in motion.
- Manual methods such as bank transfer and cash on delivery have their own
  settings: on or off, payment deadline, instructions for the buyer, and for
  cash on delivery when staff are alerted about cash not yet recorded.
- Signed gateway webhooks are recorded once each. A webhook only prompts the
  store to ask the gateway; what it claims is never trusted by itself. What
  webhooks said is kept for 30 days, the record that one arrived for 180.

## Refunds

- A refund is staff's one-time request for an amount, chosen items or
  shipping, with a reason, who asked, and a key that makes retrying safe.
- Refunded items record which order lines and how many, with their amount
  including tax.
- A refund is split across the original payments: each part goes back
  through its original gateway or is recorded as returned by hand, and is
  pending, succeeded or failed.

- A refund of lines can keep part back, with a reason (for [Returns](returns.md),
  when a return breaks the condition a discount depended on). It comes off
  the lines themselves, spread by what each was worth, tax split the same
  way, so their credit note credits only what went back. It is never as much
  as the lines are worth, and only ever with a reason.

Refund requests and their amounts never change. Gateway outcomes are
checked, and unclear cases go to staff review rather than being declared
successful.

## Example: online payment

1. An order is placed for 100.00. Payments creates a pending attempt for what
   remains unpaid and saves it before contacting the gateway.
2. The buyer is sent to the gateway's hosted payment page.
3. A return from the gateway or a signed webhook triggers verification with
   the gateway. The app checks the amount, currency, mode and reference; the
   browser return or webhook payload alone cannot mark an order paid.
4. A successful, matching payment is recorded. Payments recalculates the
   order's payment status. If the gateway times out, the attempt remains
   pending and is checked later instead of falsely marked failed.

For bank transfer or cash on delivery, staff record the amount actually
received. A short receipt can leave a new payment open for the remainder.
Cash-on-delivery orders can ship before payment and need staff to record cash
collection after delivery.

## Safety and settlement

Gateway attempts are retry-safe. A gateway error may mean the outcome is
unknown; it is checked again rather than assumed to have failed. Mismatched
or late money is held for review and does not silently count as an ordinary
payment. Payment, refund and event records are protected against deletion or
changing their core financial meaning.

## Action flow and domain handoffs

Checkout creates the order and its payment holds first. Payments then creates
the gateway attempt and contacts the gateway after the database transaction;
callbacks and webhook events trigger verification against the gateway rather
than being trusted as proof by themselves. When money is confirmed, Payments
recalculates the payment totals and tells Orders to run `MarkOrderAsPaid`.
That transition commits promotion uses and handles non-shipping inventory;
Orders remains the owner of the order lifecycle.

For a refund, staff use `RefundOrder`. The action checks the order and
payment/refundable balances under locks, records the refund and its payment
parts, and sends pending gateway parts after commit. Refunds affect Payments
and may affect whether Orders issues a credit note, but they do not return
physical goods to Inventory: [Returns](returns.md) does that, and refunds a
return by calling `RefundOrder` outside its own transaction with the key
`return:{return id}:{attempt}`. Returns reads which units were refunded by
line through `FindRefundedUnits`, and follows `RefundSucceeded`,
`RefundFailed` and `RefundNeedsReview` (after Payments commits) to complete
its returns.

See [Sellora business flows](business-flows.md) for the transitions from
checkout through payment and fulfillment.
