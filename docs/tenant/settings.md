# Store settings

Store Settings contains the core profile and operating choices shared across
the storefront and dashboard. It is one row per store; optional modules and
integrations keep their own settings in their own domains.

## What the store can configure

### Store profile and localization

- Store name, country, base currency, timezone and default language.
- Enabled content languages; the default language must be included.
- Contact email/phone and business address.
- The logo and translated invoice footer.
- Weight and dimension units used for display; stored product weights and
  dimensions still use standard internal units.

### Checkout and buyer requirements

- Guest checkout: whether a shopper may purchase without making an account.
- Buyer tax ID: whether a buyer tax identifier is optional or
  required at checkout.
- Social sign-in providers: which supported customer sign-in providers,
  such as Google or Apple, are offered.
- Staff two-factor requirement: a store-wide policy that owners control.

### Inventory and fulfillment defaults

- Low-stock level: the fallback warning level for variants that do not set
  their own inventory threshold.
- Working days: the days the store operates, used to calculate delivery
  estimates.
- Order personal-data retention period, where the store may configure it
  subject to legal requirements. Tax documents follow country-specific
  retention rules and cannot be shortened by the merchant.

### Tax documents

- Legal name, tax-registration label/number, invoice and credit-note prefixes,
  and numbering method.
- The store's tax mode is inclusive or exclusive.
- Store country/base currency and tax mode are locked once pricing exists,
  because changing them would change the interpretation of existing prices
  and orders.

Settings are audited. The single-row rule prevents conflicting copies of
store-wide settings.

## Example

A Nigerian store sets NGN as its base currency, English as its default
language, Hausa as an additional enabled language, and a Lagos timezone.
It allows guest checkout, leaves buyer tax IDs optional, sets its default
low-stock warning to five units, and configures business details for
invoices. A variant can override the stock warning; a shopper's chosen
currency and checkout destination are handled by Pricing and Cart rather
than by changing the store's base settings.

## Scope boundaries

Payment gateway credentials belong to Payments. Shipping zones and delivery
prices belong to Shipping. Tax classes/rates and additional currencies belong
to Pricing. Inventory quantities and locations belong to Inventory. Settings
provides store-wide defaults and profile values, not every module's
configuration.

## Action flow and domain handoffs

`FindStoreSettings` provides the shared store defaults to workflows such as
Cart pricing, Checkout eligibility, inventory warning thresholds and
customer-facing document generation. `UpdateStoreSettings` locks and updates
the single settings row, audits changes, and prevents base currency or tax
mode changes after priced records exist. When profile details change, it
queues a platform-profile sync after the database commit.

Settings does not own every configuration used by the store: Pricing owns
tax rates and additional currencies, Shipping owns zones/methods, Payments
owns gateway connections, and Inventory owns quantities. Settings supplies
the core values those workflows read. See
[Sellora business flows](business-flows.md) for the settings dependencies
across a purchase.
