# Writing a payment gateway integration

Each gateway (Paystack first, then Stripe) is its own integration under
`integrations/<Name>/`, implementing `App\Tenant\Payments\Contracts\PaymentGateway`
and registering itself with `PaymentGatewayRegistry` from its service provider.
CLAUDE.md section 3.3 has the rules; these are the parts an integration owns.

## What core relies on

- **Amounts cross the contract as `Money`.** The integration converts to and
  from the gateway's own amount format, in both directions, with tests per
  currency (including 0- and 3-decimal currencies).
- **Our reference goes with every payment and refund.** `startPayment` sends
  it as the payment's reference. `refund` sends it so the refund can be found
  again, but not every gateway refuses the same refund twice (Paystack makes
  a second one), so core never relies on that: each send is claimed first,
  and a refund is sent again at most once, only after `verifyRefund` twice
  answered `NotFound`, a while apart. `verifyRefund` asks by the gateway's own
  ID once it gave one; otherwise it looks for our reference, answering
  `NotFound` only when the gateway's whole answer holds none, and `Unclear`
  when it can't be tied to exactly this refund (core sends that to staff
  review).
- **A timeout or server error throws `GatewayUnavailableException`.** It
  means the outcome is unknown, never that it failed. Throw
  `GatewayRejectedException` only for a definite refusal.
- **Verification is fail-closed.** `verifyPayment` is given the amount
  expected and returns the amount and currency the gateway took. Core marks a
  payment paid only when they equal what was expected exactly; a missing
  amount stays pending, a different one goes to review and never counts. When
  the gateway's own record of the amount it was asked for differs from the
  amount expected, the integration reports that other amount (a real one the
  gateway gave, never a made-up one), so core's check refuses it.
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

So for now the integration is strict. When `requested_amount` is exactly the
amount expected it reports `amount`, what was taken; when Paystack was asked
for another amount it reports `requested_amount`; with either missing it
reports nothing and the payment stays pending. Core counts the payment only
when the amount reported is exactly the order's amount, in its currency and
mode. A merchant who passes fees on gets every such payment in `needs_review`,
for staff to handle by hand. Nothing reconciles fees, and core's exact-amount
rule is unchanged. Recognising the fee needs a recorded answer for that case
first.
