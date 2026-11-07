# Messaging

Messaging is how a store's messages leave the platform. Step 20a built the
foundation, email first: a contract every channel implements, the store's
sender identity, and alerts that tell staff when something needs them.
Customer messages (order confirmations, shipping and return updates) come in
Step 20b.

## Channels

A message is written once (`Message`: its email form and, for channels that
send text, its text form) and sent to a person looked up when it is sent
(`MessageAddressee`). Looking the person up at send time means someone
deactivated, or who changed their email or language, after the message was
queued gets the current answer, or nothing.

| Channel | Where | Used |
| --- | --- | --- |
| Email | core (`EmailChannel`) | always |
| SMS, WhatsApp, push | integrations, each implementing `MessageChannel` | only while their integration is Enabled for the store (none built yet) |

Integrations register their channel in `MessageChannels` with their feature
key; core never names them.

## Sending: own queue, per-store limit, delayed never dropped

- Every message is sent by the `SendMessage` job on its own `messages`
  queue (config `messaging.queue`), so a burst of mail never delays payments
  or bulk work. Workers listen on `payments,messages,default,bulk`.
- The job is encrypted on the queue and dispatched only after the change it
  is about has committed.
- **Each store may send at most `messaging.sending.per_minute` messages a
  minute (120).** A message over the limit is put back on the queue for when
  the store has room again: it is delayed, never dropped. Sending is retried
  for `messaging.sending.retry_for_hours` (24) with growing waits.

## The store's sender identity

Every email a store sends goes out from the platform's address, with the
store's name, so a store can never send as another domain. The settings:

| Setting | Default | Meaning |
| --- | --- | --- |
| `sender_name` | empty: the store's name | The name shown as the sender |
| `reply_to_email` | empty: the store's contact email | Where replies go, unless the email sets its own |
| `has_new_order_alert_emails` | on | Whether new-order alerts are also emailed (they are always listed) |

`GET` and `PATCH /api/v1/staff/messaging/settings` (`settings.view`,
`settings.manage`). The settings row is created with the store and audited.
A footer and merchant-edited wording are not in the first version.

## Staff alerts

When something needs staff attention, an alert is raised: listed in the
dashboard, and emailed to the active staff holding the permission that lets
them act on it, plus the owner, each in their saved language (the store's
default language when they saved none).

| Alert | Raised by | Who (permission) | Money |
| --- | --- | --- | --- |
| `new_order` | `OrderPlaced` | `orders.fulfil` | |
| `payment_needs_review`, `payment_unresolved` | `PaymentNeedsReview`, `PaymentUnresolved` | `payments.confirm` | yes |
| `payment_hold_keeps_failing`, `order_payments_keep_failing` | `PaymentHoldKeepsFailing`, `OrderPaymentsKeepFailing` | `payments.confirm` | yes |
| `payment_overpaid`, `payment_disputed` | `PaymentOverpaid`, `PaymentDisputed` | `payments.refund` | yes |
| `order_paid_after_cancellation`, `order_refunded_beyond_paid` | the Orders events of the same names | `payments.refund` | yes |
| `refund_needs_review`, `refund_failed`, `refund_still_pending`, `refund_succeeded_after_failing` | the Payments events of the same names | `payments.refund` | yes |
| `committed_payment_refund_failed` | `CommittedPaymentRefundFailed` | `payments.refund` | yes |
| `payment_gateway_needs_reconnecting` | `PaymentGatewayNeedsReconnecting` | `payments.manage_gateways` | yes |
| `exchange_settlement_refused` | `ExchangeSettlementRefused` | `returns.exchange` | yes |
| `cash_collection_overdue` | `CashCollectionOverdue` | `delivery.cash.manage` | yes |
| `delivery_failed` | `DeliveryFailed` | `delivery.manage` | |
| `stock_running_low`, `variant_sold_out` | the Inventory events of the same names | `inventory.adjust` | |
| `return_requested` | `ReturnRequested`, when the customer asked (not staff) | `returns.manage` | |

- **Once per thing.** The same alert about the same thing is raised once,
  even when two arrive at the same moment (a unique repeat key). Kinds that
  may come again (a gateway to reconnect, a committed payment that can't be
  given back, stock running low, sold out) are raised at most once a day.
- **The hourly email cap.** A store emails at most
  `messaging.staff_alerts.emails_per_hour` (30) alerts an hour. Past it,
  alerts are only listed, and each recipient gets one "more alerts are
  waiting" email that hour. **Money and payment alerts are never held back
  and never count towards the cap: they are always emailed.**
- **New orders** are always listed; whether they are also emailed follows
  `has_new_order_alert_emails`.
- Alerts are raised only once the change they are about has committed. An
  alert that can't be raised is reported to the error tracker, never allowed
  to break what announced it (a webhook, a stock change).
- Alerts hold public IDs, order numbers, amounts, SKUs and product names,
  never personal data. They are deleted after `retention.periods.staff_alerts`
  (180 days) and are part of the store export.

**The alert list:** `GET /api/v1/staff/messaging/alerts`, newest first,
cursor-paginated, optionally `?kind=`. Each staff member sees only the kinds
whose permission they hold (the owner sees all). Titles and messages are
written in the request's language.

Muting alerts is not in the first version. When it is built, money and
payment alerts can never be muted.

## Preferred language

Staff and platform admins can save the language messages are written in:
`PUT /api/v1/staff/auth/language` and `PUT /api/v1/platform/auth/language`
with `{"locale": "fr"}`, or `null` to use the store's default (staff) or
English (platform admins). Platform admins get their own alerts in it too
(see [Platform identity](../landlord/identity.md#platform-alerts)).

## Not here yet (Step 20b)

- Customer messages: order confirmation, payment, shipping, delivery and
  return updates. The order-paid email waits for the invoice for at most a
  time set in config, then is sent without the attachment (the invoice can
  be fetched as usual).
- An email footer in the sender settings.
- A platform alert when a store export fails.
- Double opt-in for marketing consent, on by default for some countries at
  sign-up (from `config/geography.php`).
