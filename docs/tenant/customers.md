# Customers

This product guide explains shopper accounts and their store-specific
relationship data. A customer account in one store is separate from an
account in another store. Field-level detail is in the database's own column
comments, not here.

## What an account holds

- A name, email and password. The password may be empty for an account that
  only signs in through a provider. An optional phone in international format
  and a preferred language for messages.
- When the customer proved they receive mail at their current email, whether
  sign-in is allowed, and when they last signed in.
- An optional tax ID with its country, checked for format but not against a
  government registry. Tax exemption is a separate flag staff set after
  checking that ID.
- Saved addresses, with an optional default for shipping and for billing;
  the database makes sure a default belongs to the same customer. Checkout
  and orders copy the address they use, so editing a saved address never
  rewrites a past order.
- Once personal details are erased, the account stays as an empty record so
  past orders still point to something.

## Other customer records

- Emailed one-time links (verifying an email, changing it, confirming an
  erasure): one pending link per customer and purpose, stored only as a hash
  and expiring; a new link replaces the old one. Password reset links work
  the same way.
- Customer groups (such as Wholesale or VIP) are managed by hand; they are
  not automatic segments, and guests belong to none. Promotions can target
  them.
- Marketing consent is recorded per channel, with its status, where it was
  given and when, never assumed from creating an account.
- Staff-only notes about a customer.
- Data exports the customer asked for, built in the background: pending,
  ready or failed, with a private file that expires.
- Linked sign-in providers (Google, Apple), identified by the provider's own
  user ID. Provider access tokens are not kept. Short-lived one-time records
  secure each social sign-in or linking attempt.

## Example

A shopper creates an account with email and password, verifies the address,
and saves a home address. They later add an office address and select it at
checkout. Checkout copies the chosen address into its data; order placement
copies it again into the immutable order record. If the customer later edits
or deletes the saved office address, the historical order still shows the
address used for that purchase.

Customers may also sign in through a linked provider, be added to a group by
staff, opt into email marketing at checkout, and request an export or
erasure of personal data. Erasure clears personal fields but keeps the row
where financial/order history requires a stable reference. Legal tax
documents may retain fields for their required retention period.

## Action flow and domain handoffs

`RegisterCustomer` creates a store-local account, signs the shopper in on
that device and sends an email-verification link. Email verification gates
sensitive actions such as linking eligible guest orders; it does not prevent
the new customer from shopping. Social sign-in has a separate start/finish
flow: `StartSocialSignIn` creates one-time state for the provider redirect,
then `FinishSocialSignIn` consumes and validates the callback before signing
in or creating/linking the account.

Customers connects to Cart when a guest cart is merged on sign-in, and to
Checkout when an account, saved address, tax-exempt status or contact
information affects the purchase. Checkout records marketing choices through
`RecordMarketingConsent`; account creation alone is not consent. Customer
groups can be used by Promotions as an eligibility rule, while staff
Identity controls who may manage customer records. Personal-data export and
erasure workflows preserve financial records required by Orders.

These are shopper accounts, not store-team or driver accounts. The full
purchase handoff is in [Sellora business flows](business-flows.md).
