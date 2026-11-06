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
Nothing in Storefront is personal data.

## Not here yet

Step 19b adds the shopping aids: the wishlist, fetching several products
for comparison and recently viewed, merchant-picked product links, similar
products and best sellers. Page sections (blocks a page is built from)
were left out of the first version.
