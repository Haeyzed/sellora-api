# Writing a payment gateway integration

Each gateway (Paystack first, then Stripe) is its own integration under
`integrations/<Name>/`, implementing `App\Tenant\Payments\Contracts\PaymentGateway`
and registering itself with `PaymentGatewayRegistry` from its service provider.
CLAUDE.md section 3.3 has the rules; these are the parts an integration owns.

## What core relies on

- **Amounts cross the contract as `Money`.** The integration converts to and
  from the gateway's own amount format, in both directions, with tests per
  currency (including 0- and 3-decimal currencies).
- **Our reference is the idempotency key.** `startPayment` and `refund` send
  the reference they are given, so a retried call never charges or refunds
  twice. `verifyRefund` answers `NotFound` only when the gateway has no refund
  under that reference; core then sends it again with the same reference.
- **A timeout or server error throws `GatewayUnavailableException`.** It
  means the outcome is unknown, never that it failed. Throw
  `GatewayRejectedException` only for a definite refusal.
- **Verification is fail-closed.** `verifyPayment` returns the amount and
  currency the gateway actually took. Core marks a payment paid only when
  they equal what was expected exactly; a missing amount stays pending, a
  different one goes to review and never counts.
- **Webhooks only say "go and ask".** `verifyWebhookSignature` is checked
  before anything is parsed; `parseWebhook` returns the event ID, its kind
  and our reference, and nothing it says about status is trusted.
- **Never card data, tokens or credentials in logs.** At most the card's
  brand and last four digits, when the gateway gives them.

## Paystack: fees passed to the customer

Paystack lets merchants pass transaction fees on to the customer. The amount
the customer is charged then includes the fee, so it is more than the order
total we asked for. Recorded test-mode answers (tests/Fixtures/Paystack) only
cover the merchant bearing the fee: `amount` equals `requested_amount`, and
the fee is a separate `fees` field.

So for now the integration is strict. It reports Paystack's `amount`, what
was taken, and only when Paystack also reports `requested_amount`; core counts
the payment only when that is exactly the order's amount, in its currency and
mode. A merchant who passes fees on gets every such payment in `needs_review`,
for staff to handle by hand. Nothing reconciles fees, and core's exact-amount
rule is unchanged. Recognising the fee needs a recorded answer for that case
first.
