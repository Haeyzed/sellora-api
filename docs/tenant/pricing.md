# Pricing

Pricing decides what a product costs in the buyer's chosen currency and how
tax is calculated. It is shared by product pages, carts and checkout; the
same calculation path is used when an order is placed. Field-level detail is
in the database's own column comments.

## Currency and product price

- The store has one base currency, set in store settings. It is
  locked once any prices exist, so existing amounts cannot change meaning.
- A variant's base price is stored in whole minor units (for example cents). A missing price means the variant
  cannot be bought.
- The store can list additional currencies the store accepts. A
  currency has an active flag, optional merchant margin on the market rate,
  or a manual rate. A manual rate replaces the market rate; the margin is
  kept separate from the market rate.
- A variant can have an optional exact price for a variant in an
  additional currency. If present, that amount is used instead of converting
  the base price. A compare-at amount, if present, must be higher.
- If no exact price exists, the base price is converted using the applicable
  exchange rate and rounded according to that currency's standard precision.
  A checkout keeps the rate used for its quote until that quote expires.

This is customer selling price logic; it is separate from Inventory's
internal unit cost.

## Tax classes and rates

- Tax classes group variants that receive the same tax treatment. Stores
  start with Standard, Reduced, Zero-rated and Exempt classes. One class is
  the default; one may be selected for shipping.
- Tax rates are a named percentage for a tax class and a country or
  state. A state-specific rate replaces the country-wide rate; rates are not
  added together. Rates range from zero to 100 percent.
- A variant can be put in a tax class. Without an
  assignment, the default class applies.

Zero-rated means the item is still taxable/reportable at 0%; Exempt means it
is outside tax and the calculator does not look up a rate.

## Tax calculation scenario

A shopper in a destination region has a taxable item and a shipping charge.
Pricing identifies the variant's tax class, destination country/state and
the store's inclusive/exclusive tax mode. The configured country tax
calculator (or the core rate table when no enabled country plug-in handles
it) returns tax for each line and shipping if configured. Checkout presents
that result; order placement copies the tax name, rate, region and amount so
later rate changes do not alter the sale.

If the customer is marked tax-exempt by staff, or a registered rule exempts
the relevant lines, those lines skip ordinary rate calculation. A tax ID
entered at checkout is normalized/formatted but is not checked against a
government registry.

## Important boundaries

- Prices shown on product pages and cart can change before order placement.
- The checkout quote snapshots price and exchange rate temporarily; the order
  snapshots the final values permanently.
- Tax rates are merchant-maintained and no country tax rate is seeded
  automatically.
- Tax behavior for a country may be supplied by a tax-calculator plug-in;
  the platform core provides the generic rate-table calculator.

## Action flow and domain handoffs

Pricing's `VariantPricer` resolves each variant's unit price for the selected
currency and exchange-rate snapshot. `PriceCalculator` assembles line,
shipping and tax totals; `CalculateTax` first removes exempt lines, then uses
the destination's registered calculator or the core rate table. Cart and
Checkout call this shared pricing path, so they do not each invent a separate
tax calculation.

Promotions calculates discounts against the priced lines before the final
tax calculation, and Shipping supplies the selected/available delivery
amounts. Checkout preserves the applied exchange rate and quote identity for
the quote's lifetime. Once the order is placed, Orders snapshots the final
amounts; subsequent rate, price or tax configuration changes apply to future
quotes, not old orders. See [Sellora business flows](business-flows.md).
