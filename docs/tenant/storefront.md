# Storefront

`Tenant\Storefront` holds what a store shows shoppers besides its catalog:
its own pages, its menus, its banners and announcements, and its search
engine settings. Staff manage it under `/api/v1/staff/storefront`; shoppers
read it under `/api/v1/storefront`, with no sign-in, rate-limited per
visitor, in the language they ask for (`Accept-Language`), falling back to
the store's default. The storefront itself is a separate frontend that
builds its pages from these endpoints.

Two permissions cover it: `storefront.view` to see everything (drafts,
switched-off banners), and `storefront.manage` to change it.

## Settings and search engines

Each store has one `storefront_settings` row, created with the store; a
missing row fails loudly (`MissingStorefrontSettingsException`), never
filled in by a read. It holds:

- the **title pattern** for page titles, such as `{title} · {store}`
  (`{title}` is required), and a default description by language;
- the **robots** directive (`index_follow`, `noindex_follow`,
  `noindex_nofollow`), for a store that isn't ready to be found yet;
- the **share image** social sites show (on the public disk, counted
  against storage);
- which **pages serve the store's policies**: privacy, terms, refunds,
  shipping. `GET storefront/seo` lists a policy page only while it is
  published and outside the trash.

Products, categories and brands have their own search engine title and
description by language (`seo` in their resources), for when they should
differ from the name and the description shoppers read.

`GET storefront/sitemap/{type}` lists one part of the sitemap (`pages`,
`products`, `categories`, `brands`): only what shoppers may see, each with
its slug and last change, paged by cursor. The storefront writes the XML.

## Pages

A page ("About us", "Shipping policy") has a title, a body, a search engine
title and description, all by language, and a slug made once from the
title in the default language. Renaming a page keeps its slug, so links
keep working; staff change the slug on purpose. Slugs are unique among
pages outside the trash.

A page starts as a **draft**. Publishing keeps the first publication time;
unpublishing hides the page again. One page can be the **homepage**
(`GET storefront/home`); choosing another takes it from the first, under
the settings row's lock, and a partial unique index refuses two. A page in
the trash is never the homepage, and restoring it re-checks its slug.

### Page bodies are cleaned HTML

Staff write a body in HTML. `PageHtml` (Symfony's HTML sanitizer) cleans it
**when it is saved and again when it is served**, so tightening the rules
later also cleans pages saved before. What survives:

- text formatting, headings `h2` to `h6`, lists, quotes, code, tables and
  figures;
- links to `https` addresses, relative paths, `mailto:` and `tel:` only,
  given `rel="noopener noreferrer"`;
- images only from where the store's own images are served
  (`StorefrontMediaHost`: the store's domain while images are on the local
  disk, the disk's own address, such as a CDN, otherwise). Upload a page's
  images with its images endpoint and put the returned address in the body.

Wrappers such as `div`, `span` and `h1` go but keep their text. Scripts,
styles, frames, forms, event handlers, remote, relative and `data:` images
never survive. A body is at most 100,000 characters per language.

The served body is the one costly read, so it is **cached per store** as a
plain string (`ServedPageBody`), keyed by the rules' version
(`PageHtml::VERSION`) and the page's update time. Every change to the
storefront's content renews the store's content version once the change
commits (`StorefrontContentChanged`), so shoppers see it on their next
visit. Everything else here is read live: it is a few indexed queries. The
cache goes through the `Cache` facade, which files entries under the
store's own tag; an architecture rule forbids injecting the cache
repository in store code, which would bypass that tag.

## Menus

A menu ("main", "footer") is found by its **key**: lower-case letters,
numbers and single hyphens, unique in the store. Its links are two levels
deep, at most 100 in all, and staff replace them together
(`PUT menus/{menu}/items`), in order, under the menu row's lock so two
replacements never mix.

Each link has a label by language and goes to exactly one of: a page, a
category, a brand, a product, or an address (`https`, `mailto:`, `tel:` or a
path on the storefront, never `//`). The database refuses a link with no
destination or two, and a link under one from another menu.

A link may go to a draft page or a product shoppers can't see yet. A link
to something missing or in the trash is refused (`menu_link_target_not_found`,
pointing at that link). Shoppers (`GET storefront/menus/{key}`) see only
links they may follow: a published page, a category or brand outside the
trash, a product they can buy, or an address. A hidden link's own links
are hidden with it. Each shown link gives its type, target and slug (or
address), and the storefront builds the address.

## Banners and announcements

Two kinds, fixed when a banner is added:

| Kind | What it is | Shown to shoppers |
|---|---|---|
| `image` | an image in one place on the storefront, from config `storefront.banner_positions` (`home_hero`, `home_secondary`, `catalog_top`) | `GET storefront/banners?position=...`, at most 10, only once it has its image |
| `announcement` | a message for the bar along the top, text only | `GET storefront/announcements`, at most 3 |

Both have a title, a line of text and button text by language, a link (the
same addresses as menus), text and background colours as `#rrggbb`, an
order, a switch, and optional start and end times. Shoppers see a banner
while it is switched on, from its start (included) until its end (not
included). Times sent without an offset are read in the store's timezone
and kept in UTC.

## What a store export holds

Every storefront table is classified column by column for the store
export, and the share image, pages' images and banners' images are in it.
The only personal data in Storefront is the wishlist (below).

## Shopping aids

Built in Step 19b. None of them ever shows a stock quantity: a product or
item says only whether it can be bought now (in stock, not counted, or open
to backorders). Lists of products show only what customers can buy
(published and priced), in the order described, with prices in the
currency asked for (`?currency=`).

| What | Endpoint | Rules |
|---|---|---|
| Several products | `GET catalog/several-products?ids[]=...` | At most `catalog.max_products_fetched` (50) product IDs, answered in the order sent, repeats once. For comparison and recently viewed: the storefront keeps the list in the browser and the API stores nothing |
| Product links | `GET catalog/products/{slug}/links?type=related` (`upsell`, `cross_sell`) | Picked by staff, in their order. A link to a product customers can't buy is left out, never shown |
| Similar products | `GET catalog/products/{slug}/similar` | Worked out when asked, never stored: the product's related links first, then the same category, then the same brand, then a price within 25% either way, newest first in each, never the product itself. At most 12, cached per product for 15 minutes (config `catalog.similar_products`) |
| Best sellers | `GET catalog/best-sellers` (optional `?category=`) | Units sold over the last 30 days (`orders.best_sellers_period_days`), counting only paid, non-test, uncancelled orders (refunded ones still count: units, not money). At most 12, cached per store and category for an hour (config `catalog.best_sellers`) |

**Product links** are managed by staff on the product:
`GET staff/catalog/products/{product}/links` (`catalog.view`) shows all
three kinds, and `PUT staff/catalog/products/{product}/links/{type}`
(`catalog.manage`) replaces one kind with `product_ids` in the order given
(at most `catalog.max_links_per_type`, 20; an empty list removes them all).
A product never links to itself, the same product never twice in one
kind, and only products outside the trash.

### Wishlist

A signed-in customer's own list of what they want to buy later, under
`/api/v1/customer/wishlist` (`auth:customer`). It is not a cart: no
quantity, price or stock is held and nothing is reserved.

- **Saving** (`POST wishlist`) takes a product customers can buy, out of
  stock included, and optionally one of its variants. Saving what is
  already saved changes nothing (200); a new item is 201.
- **At most `storefront.wishlist_max_items` (500)**, refused with
  `wishlist_full`. Saves for one customer take a lock per customer, so two
  saves at the same moment can never pass the limit; saving what is
  already there still works when full.
- **Listing** (`GET wishlist`) is newest first, by cursor. An item that
  stopped being sold stays, with `is_for_sale` false and no price, until
  the customer removes it (`DELETE wishlist/{item}`). With no variant
  chosen, the price is the lowest of its variants' (`price_is_from`).
- **A guest's list** is kept by the storefront in the browser and sent at
  sign-in (`POST wishlist/merges`, at most the limit). Repeats and what is
  already saved are skipped, and what can no longer be bought is dropped.
  It is all or nothing: if the new items would not fit, nothing is saved
  and the answer is `wishlist_full`.
- **Move to cart** (`POST wishlist/{item}/cart`, with `quantity`, and
  `variant_id` for an item saved without one) goes through the cart's own
  rules (for sale, not sold out, the cart's limits). The item leaves the
  wishlist only once it is in the cart, so a refusal keeps it saved.
- Each customer sees and changes only their own; another customer's item
  is not found (404).
- **Privacy:** the wishlist is in the customer's data export (`wishlist`)
  and deleted when they are erased.

Back-in-stock and price-drop alerts for wishlist items belong to the
`Engagement` module, not core.

## Not here yet

Page sections (blocks a page is built from) were left out of the first
version.
