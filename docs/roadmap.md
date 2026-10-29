# Module roadmap

The rules decided so far for each plan-gated module (CLAUDE.md section 18). Read a module's entry before planning it. Nothing here is built until it is approved as a step; when a module is built, its plan is checked against these notes and CLAUDE.md.

**First versions stay small.** Build what merchants need first and add the rest when they ask. For example: `Wallet` first without linked wallets or spending limits; `Installments` first with goods released only after the last part (100%), and the "release early" mode only once `AccountCredit` exists.

## `Hr`

Status: roadmap

classes named without an `Hr` prefix (`Employee`, not `HrEmployee`); only tables get `hr_`

## `Restaurant`

Status: roadmap

tables, reservations, modifiers, kitchen display. **One POS, not two:** in-house service (tables, open bills, kitchen tickets, split bills, tips and service charge) is built on top of `Pos` as a declared dependency, through extension points `Pos` offers; it never copies POS code. Menu modifiers ("extra cheese +₦500") change price and tax, so they go through core's order-line extension points, never around Pricing

## `Manufacturing`

Status: roadmap

bills of materials, work orders

## `AdvancedReporting`

Status: roadmap

includes "Store insights" (legacy AiAssistant, which is not AI)

## `Accounting`

Status: roadmap

ledger, expenses and income (legacy Expenses; "Biller" becomes "Payee")

## `Pos`

Status: roadmap

point of sale for counter selling (retail by default), designed with extension points so `Restaurant` (and later other industry modules) can add their own way of selling on the same POS; terminal providers are integrations; a customer's tag or card (NFC or similar) only identifies their wallet, never holds the balance, and needs a signed challenge or a PIN, instant blocking and spending limits

## `GiftCards`, `Loyalty` (RewardPoints)

Status: roadmap

plug into checkout through extension points (section 6)

## `Installments`

Status: roadmap

one order paid in a fixed schedule (for example 4 weekly parts), plugging into checkout through extension points (section 6). The store sets **how much must be paid before the goods are released** (default 100%: the goods go out after the last part, so nothing is lent). Until release the order stays unpaid, its payment deadline follows the schedule, and its stock stays reserved through an Orders extension point. A plan that stops being paid is cancelled: money back through Payments, stock released; whether a cancellation fee is allowed is checked per country. **Releasing the goods before everything is paid is lending**, so it is offered only while `AccountCredit` is Enabled (a declared dependency), for customers staff approved for credit: at release the unpaid rest is paid by `AccountCredit` (the order becomes paid and is invoiced), and the remaining parts repay the customer's credit ledger, never a second debt ledger. Outside "buy now, pay later" providers that pay the store in full are integrations, not this module

## `RecurringOrders` (ProductSubscriptions)

Status: roadmap

renamed to avoid confusion with platform subscriptions

## `MultiLocation`

Status: roadmap

multiple warehouses, transfers, per-location stock; a single location is core

## `Purchasing`, `B2B` (SalesQuotations), `SalesCommissions` (SalesAgents)

Status: roadmap

back-office add-ons

## `Booking`, `ApprovalWorkflows`, `Engagement` (BackInStock; also back-in-stock and price-drop alerts for wishlist items), `ContentMarketing` (blog, FAQ), `Helpdesk` (customer Support)

Status: roadmap

optional add-ons

## `DigitalProducts`

Status: roadmap

downloadable files and licence keys; secure, expiring download links; files linked to variants in the module's own tables

## `Bundles`

Status: roadmap

one item sold, stock taken from several variants (kits, gift sets)

## `Wallet`

Status: roadmap

store credit: a customer funds it with a real, verified payment and spends it at that store only (closed loop: no cash-out, no transfers between customers). An append-only ledger is the source of truth and the balance can never go negative (tested under contention). It plugs into checkout as a payment method through the extension points (hold, commit, release, give back). Top-ups are not sales: no tax and no invoice until the credit pays for an order. Balances stay spendable or refundable if the module is Locked or Disabled. Closing a store requires outstanding balances to be settled first. Needs a Payments extension point for payments that are not for an order, and may later be a refund resolution for Returns. Stored value can be regulated: check the rules of the merchants' countries before launch. Separate from `Loyalty` (rewards the store grants, not money) and `GiftCards` (anonymous codes). **Spending controls:** a wallet owner can set daily, weekly and per-purchase limits on their own wallet, and can fund a **linked wallet** for someone else (an employee or family member), with limits, an instant freeze, and a statement of what was bought. Limits are checked when a payment holds money, under the wallet's lock, in the store's timezone, so two purchases at the same moment can't pass the limit together. The person spending must have agreed to the link, and a linked wallet for a minor is only offered where the store's rules and local law allow it

## `AccountCredit`

Status: roadmap

**buy now, pay the store later** ("on account"), the opposite of `Wallet`: the customer owes the store, rather than the store owing the customer, so it has its own ledger and never shares one with `Wallet`. Staff approve each customer and set a credit limit and payment terms; the system may suggest customers from their paid order history, but never approves anyone automatically. Buying on credit is a payment method through the checkout extension points. The order is paid and invoiced at the sale, and the amount owed goes in the credit ledger. Repayments are ordinary payments. Overdue balances block further credit. No interest or fees in the first version, because charging them can make the store a regulated lender in many countries; check the rules of each merchant's country before launch

## `Recommendations`

Status: roadmap

"frequently bought together" (pairs counted from each store's own paid, non-test orders by a scheduled job on the `bulk` queue, shown only above a minimum number of purchases) and personalised "you may also like". Behaviour data about a person is personal data: registered with the privacy registry, with a retention period. Core keeps the simple versions (merchant-picked links, automatic similar products, best sellers)

## `Reviews`

Status: roadmap

ratings and reviews, which also unlocks "top rated". Only customers who bought the item can review, with moderation; reviewer details go in the privacy registry

## `Marketplace`

Status: later

multi-vendor; adds a `seller` guard only when built

## `Repair`

Status: later

postponed until there's demand

## `Projects`

Status: **dropped**

not commerce; don't port
