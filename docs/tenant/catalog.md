# Catalog

This product guide explains the store's sellable product information: how
shoppers browse products and choose an exact variant. Field-level detail is
in the database's own column comments, not here.

## Products and variants

A **product** is the customer-facing parent, such as “Linen shirt”. It is a
draft, active or archived; only an active product with at least one priced
variant is shown to shoppers. Its name and description are translated, and
a name is required. Its slug is the readable URL identifier, unique among
products not in the trash; it is made from the default-language name when
the product is created and is not changed by later edits.

A product can have a brand and sits in one or more categories, one of which
is its primary category. Its photos form an ordered gallery; the first is
used in listings. Search covers product names, variant identifiers and brand
names.

A **variant** is what is actually selected and bought, for example
“Linen shirt / Blue / Medium”. Every product has at least one.

- A variant may have a SKU and a barcode; a SKU is unique in the store among
  variants not in the trash.
- Its price is in the store's base currency, in minor units. A variant may be
  unpriced while it is being prepared, but can't be bought until priced. An
  optional compare-at price, shown as the former price, must be higher than
  the selling price.
- A variant that needs shipping must have a weight. Dimensions are optional,
  but if one is given all three must be.
- No two variants of a product may have the same combination of option
  values.

## Brands, categories and options

- Brands and categories have translated names and descriptions and their own
  slugs. Categories form a tree and have a display order.
- Options such as Size or Colour are attributes with translated values
  (M, Blue). A product chooses which options it uses and in what order, and
  each variant picks one value for each option; the database makes sure the
  value belongs to that option.

## Example

Staff create a draft “Linen shirt”, assign it to “Clothing”, and add Colour
and Size options. They create Blue/Medium and Blue/Large variants, assign
each a SKU, price, weight, and option values, then publish the product.
A shopper sees one product page but adds a specific variant to the cart.
Changing the product name later does not rewrite old order lines: orders copy
the names and prices at the time of purchase.

## Rules that affect selling

- Draft, archived, trashed, or unpriced products/variants are not buyable.
- A variant's price is in the store's base currency. Alternate currencies and
  exact foreign-currency prices are handled by Pricing, not by a second
  catalog product.
- Stock rules are maintained by Inventory; catalog data does not itself
  reserve stock.
- Product, variant, brand, and category records are audited/soft-deleted so
  the store can retain history and recover records where supported.

## Action flow and domain handoffs

Catalog actions let staff prepare and maintain products, variants,
categories, brands, options and media. `PublishProduct` is the gate from
preparation to storefront availability: a product needs its default-language
name and at least one priced variant. A published product can still be
unavailable to buy if its variant has no price or its inventory policy says
there is no stock and no backorder option.

When a shopper browses, Cart and Checkout use the chosen `ProductVariant` as
the sellable line. Pricing supplies the current customer-facing price and
Inventory supplies availability; neither of those values is a stock or price
snapshot in Catalog. At order placement, Orders copies the product and
variant labels, SKU and charged amounts so later catalog edits do not change
the historical order. The handoffs are mapped in
[Sellora business flows](business-flows.md).
