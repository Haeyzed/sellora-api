# Testing a tenant store API locally

This guide covers one merchant's store API, served on that store's domain.
The examples use PowerShell and `curl.exe` on Windows with Laravel Herd.

## Point requests at one store

Each store has its own subdomain and database. Add the subdomain to the
Windows hosts file (as administrator) before sending requests:

```text
127.0.0.1 ada-fabrics.sellora-api.test
```

Herd routes the domain to this application. A request to a store that does
not exist returns `404 store_not_found`; a request to the central domain does
not initialize a tenant store.

Set the store URL and optional credentials in PowerShell:

```powershell
$StoreOrigin = 'http://ada-fabrics.sellora-api.test'
$StoreBase = "$StoreOrigin/api/v1"
$StaffToken = 'replace-with-store-staff-bearer-token'
$CustomerToken = 'replace-with-customer-bearer-token'
$DriverToken = 'replace-with-driver-bearer-token'
$CartToken = 'replace-with-guest-cart-token'
$OrderToken = 'replace-with-guest-order-token'
```

Every request should send `Accept: application/json`. Replace the subdomain
with a real provisioned store. The store API docs are at `/docs/api` on that
store domain. The central domain serves the platform and registration docs;
see the [landlord API testing guide](../landlord/api-testing.md).

## Authentication and guest access

Tenant endpoints use different identities:

- **Staff** endpoints require a store staff bearer token. Staff sign-in can
  return a two-factor challenge when the store requires it.
- **Customer** account and order endpoints require a customer bearer token.
- **Driver** endpoints require a driver bearer token.
- **Public** storefront endpoints need no bearer token.
- **Guest cart** endpoints use `X-Cart-Token`, returned when a guest cart is
  created. Send it in the header, never in a URL.
- **Guest order** endpoints use `X-Order-Token`, returned when the guest
  order is placed. Send it in the header, never in a URL.

Some cart changes require `If-Match` with the cart version/ETag from the
latest cart response. Checkout order creation and other idempotent operations
require an `Idempotency-Key`; use a new unique value for a new action and
reuse the same value only when safely retrying that action. The examples
include documented required headers.

Having a bearer token does not by itself grant permission: staff routes can
also require role permissions and two-factor authentication. Use the
appropriate token variable in the examples below; do not send a platform
admin token to a store endpoint.

## Endpoint cURL reference

The following commands cover all 295 operations in the current tenant
Scramble OpenAPI export, including the shared geography endpoints available
on the store domain. Request bodies include required fields (or one
representative field when all are optional); values such as IDs, email
addresses and secrets are placeholders and must be replaced. Optional filters
and pagination can be added using the query parameters shown in the API docs.
The route list also accepts `PATCH` on five resource update routes; those
alternatives are included below even though Scramble exports their `PUT`
forms. Business-state checks may impose additional requirements.

```powershell
$Address = 'replace-with-address'
$Attribute = 'replace-with-attribute'
$Brand = 'replace-with-brand'
$Category = 'replace-with-category'
$Channel = 'replace-with-channel'
$Code = 'replace-with-code'
$Country = 'replace-with-country'
$CreditNote = 'replace-with-credit-note'
$Currency = 'replace-with-currency'
$Customer = 'replace-with-customer'
$CustomerGroup = 'replace-with-customer-group'
$Export = 'replace-with-export'
$Gateway = 'replace-with-gateway'
$Identity = 'replace-with-identity'
$Image = 'replace-with-image'
$Invoice = 'replace-with-invoice'
$Line = 'replace-with-line'
$Method = 'replace-with-method'
$Note = 'replace-with-note'
$Order = 'replace-with-order'
$OwnershipTransfer = 'replace-with-ownership-transfer'
$Payment = 'replace-with-payment'
$Product = 'replace-with-product'
$Promotion = 'replace-with-promotion'
$Provider = 'replace-with-provider'
$Reference = 'replace-with-reference'
$Refund = 'replace-with-refund'
$Shipment = 'replace-with-shipment'
$Slug = 'replace-with-slug'
$StaffInvitation = 'replace-with-staff-invitation'
$StaffMember = 'replace-with-staff-member'
$StaffRole = 'replace-with-staff-role'
$StoreExport = 'replace-with-store-export'
$TaxClass = 'replace-with-tax-class'
$TaxRate = 'replace-with-tax-rate'
$Value = 'replace-with-value'
$Variant = 'replace-with-variant'
$Zone = 'replace-with-zone'
```

#### AccessToken

**DELETE** `/customer/auth/tokens/current` — customer bearer token. Sign out

```powershell
curl.exe --request DELETE "${StoreBase}/customer/auth/tokens/current" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**PUT** `/customer/auth/tokens/current` — customer bearer token. Refresh the session token

```powershell
curl.exe --request PUT "${StoreBase}/customer/auth/tokens/current" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**DELETE** `/driver/auth/tokens/current` — driver bearer token. Sign out

```powershell
curl.exe --request DELETE "${StoreBase}/driver/auth/tokens/current" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $DriverToken"
```

**PUT** `/driver/auth/tokens/current` — driver bearer token. Refresh the session token

```powershell
curl.exe --request PUT "${StoreBase}/driver/auth/tokens/current" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $DriverToken"
```

**DELETE** `/staff/auth/tokens/current` — staff bearer token. Sign out

```powershell
curl.exe --request DELETE "${StoreBase}/staff/auth/tokens/current" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/auth/tokens/current` — staff bearer token. Refresh the session token

```powershell
curl.exe --request PUT "${StoreBase}/staff/auth/tokens/current" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### ArchiveProduct

**POST** `/staff/catalog/products/{product}/archive` — staff bearer token. Archive a product

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/products/${Product}/archive" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### Attribute

**GET** `/staff/catalog/attributes` — staff bearer token. List attributes

```powershell
curl.exe --request GET "${StoreBase}/staff/catalog/attributes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/catalog/attributes` — staff bearer token. Add an attribute

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/attributes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":{"replace-with-key":"replace-with-value"}}'
```

**DELETE** `/staff/catalog/attributes/{attribute}` — staff bearer token. Delete an attribute

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/attributes/${Attribute}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/catalog/attributes/{attribute}` — staff bearer token. Show an attribute

```powershell
curl.exe --request GET "${StoreBase}/staff/catalog/attributes/${Attribute}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/catalog/attributes/{attribute}` — staff bearer token. Rename an attribute

```powershell
curl.exe --request PATCH "${StoreBase}/staff/catalog/attributes/${Attribute}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":{"replace-with-key":"replace-with-value"}}'
```

#### AttributeValue

**POST** `/staff/catalog/attributes/{attribute}/values` — staff bearer token. Add a value

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/attributes/${Attribute}/values" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"label":{"replace-with-key":"replace-with-value"}}'
```

**DELETE** `/staff/catalog/attributes/{attribute}/values/{value}` — staff bearer token. Delete a value

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/attributes/${Attribute}/values/${Value}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/catalog/attributes/{attribute}/values/{value}` — staff bearer token. Change a value's label

```powershell
curl.exe --request PATCH "${StoreBase}/staff/catalog/attributes/${Attribute}/values/${Value}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"label":{"replace-with-key":"replace-with-value"}}'
```

#### Brand

**GET** `/staff/catalog/brands` — staff bearer token. List brands

```powershell
curl.exe --request GET "${StoreBase}/staff/catalog/brands" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/catalog/brands` — staff bearer token. Add a brand

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/brands" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":{"replace-with-key":"replace-with-value"}}'
```

**DELETE** `/staff/catalog/brands/{brand}` — staff bearer token. Move a brand to the trash

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/brands/${Brand}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/catalog/brands/{brand}` — staff bearer token. Show a brand

```powershell
curl.exe --request GET "${StoreBase}/staff/catalog/brands/${Brand}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/catalog/brands/{brand}` — staff bearer token. Change a brand

```powershell
curl.exe --request PATCH "${StoreBase}/staff/catalog/brands/${Brand}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":{"replace-with-key":"replace-with-value"}}'
```

#### BrandLogo

**DELETE** `/staff/catalog/brands/{brand}/logo` — staff bearer token. Remove a brand's logo

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/brands/${Brand}/logo" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/catalog/brands/{brand}/logo` — staff bearer token. Set a brand's logo

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/brands/${Brand}/logo" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --form 'image=@C:\path\to\file.jpg'
```

#### Cart

**GET** `/cart` — public; optional customer or guest-cart token. Get my cart

```powershell
curl.exe --request GET "${StoreBase}/cart" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken"
```

**PATCH** `/cart` — public; optional customer or guest-cart token. Change my cart's currency, place or shipping method

```powershell
curl.exe --request PATCH "${StoreBase}/cart" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'If-Match: "7"' `
  --header 'Content-Type: application/json' `
  --data-raw '{"currency":"replace-with-value"}'
```

**POST** `/cart` — public. Create a cart

```powershell
curl.exe --request POST "${StoreBase}/cart" `
  --header 'Accept: application/json'
```

**POST** `/cart/price-change-acknowledgements` — public; optional customer or guest-cart token. Acknowledge price changes

```powershell
curl.exe --request POST "${StoreBase}/cart/price-change-acknowledgements" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'If-Match: "7"'
```

**GET** `/cart/shipping-options` — public; optional customer or guest-cart token. List my cart's shipping options

```powershell
curl.exe --request GET "${StoreBase}/cart/shipping-options" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken"
```

#### CartCode

**DELETE** `/cart/code` — public; optional customer or guest-cart token. Remove the promotion code

```powershell
curl.exe --request DELETE "${StoreBase}/cart/code" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'If-Match: "7"'
```

**PUT** `/cart/code` — public; optional customer or guest-cart token. Enter a promotion code

```powershell
curl.exe --request PUT "${StoreBase}/cart/code" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'If-Match: "7"' `
  --header 'Content-Type: application/json' `
  --data-raw '{"code":"replace-with-value"}'
```

#### CartLine

**DELETE** `/cart/lines` — public; optional customer or guest-cart token. Empty my cart

```powershell
curl.exe --request DELETE "${StoreBase}/cart/lines" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'If-Match: "7"'
```

**POST** `/cart/lines` — public; optional customer or guest-cart token. Add to my cart

```powershell
curl.exe --request POST "${StoreBase}/cart/lines" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"variant":"replace-with-value"}'
```

**DELETE** `/cart/lines/{line}` — public; optional customer or guest-cart token. Remove a line

```powershell
curl.exe --request DELETE "${StoreBase}/cart/lines/${Line}" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'If-Match: "7"'
```

**PATCH** `/cart/lines/{line}` — public; optional customer or guest-cart token. Change a line's quantity

```powershell
curl.exe --request PATCH "${StoreBase}/cart/lines/${Line}" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'If-Match: "7"' `
  --header 'Content-Type: application/json' `
  --data-raw '{"quantity":1}'
```

#### CartMerge

**POST** `/cart/merges` — customer bearer token. Merge my guest cart into my cart

```powershell
curl.exe --request POST "${StoreBase}/cart/merges" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header "X-Cart-Token: $CartToken"
```

#### Category

**GET** `/staff/catalog/categories` — staff bearer token. List categories

```powershell
curl.exe --request GET "${StoreBase}/staff/catalog/categories" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/catalog/categories` — staff bearer token. Add a category

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/categories" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":{"replace-with-key":"replace-with-value"}}'
```

**DELETE** `/staff/catalog/categories/{category}` — staff bearer token. Move a category to the trash

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/categories/${Category}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/catalog/categories/{category}` — staff bearer token. Show a category

```powershell
curl.exe --request GET "${StoreBase}/staff/catalog/categories/${Category}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/catalog/categories/{category}` — staff bearer token. Change or move a category

```powershell
curl.exe --request PATCH "${StoreBase}/staff/catalog/categories/${Category}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":{"replace-with-key":"replace-with-value"}}'
```

#### CategoryImage

**DELETE** `/staff/catalog/categories/{category}/image` — staff bearer token. Remove a category's image

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/categories/${Category}/image" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/catalog/categories/{category}/image` — staff bearer token. Set a category's image

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/categories/${Category}/image" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --form 'image=@C:\path\to\file.jpg'
```

#### Checkout

**GET** `/checkout` — public; optional customer or guest-cart token. Show my checkout

```powershell
curl.exe --request GET "${StoreBase}/checkout" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken"
```

**PATCH** `/checkout` — public; optional customer or guest-cart token. Change my checkout

```powershell
curl.exe --request PATCH "${StoreBase}/checkout" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"contact":{"email":"owner@example.com"}}'
```

**POST** `/checkout` — public; optional customer or guest-cart token. Start checking out

```powershell
curl.exe --request POST "${StoreBase}/checkout" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken"
```

#### CheckoutOrder

**POST** `/checkout/orders` — public; optional customer or guest-cart token. Place the order

```powershell
curl.exe --request POST "${StoreBase}/checkout/orders" `
  --header 'Accept: application/json' `
  --header "X-Cart-Token: $CartToken" `
  --header 'Idempotency-Key: replace-with-unique-request-key' `
  --header 'Content-Type: application/json' `
  --data-raw '{"checkout":"replace-with-value","version":1}'
```

#### CurrentCustomer

**GET** `/customer/auth/me` — customer bearer token. Get the signed-in customer

```powershell
curl.exe --request GET "${StoreBase}/customer/auth/me" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

#### CurrentDriver

**GET** `/driver/auth/me` — driver bearer token. Get the signed-in driver

```powershell
curl.exe --request GET "${StoreBase}/driver/auth/me" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $DriverToken"
```

#### CurrentStaffMember

**GET** `/staff/auth/me` — staff bearer token. Get the signed-in staff member

```powershell
curl.exe --request GET "${StoreBase}/staff/auth/me" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### CustomerAccount

**PATCH** `/customer/account` — customer bearer token. Update my profile

```powershell
curl.exe --request PATCH "${StoreBase}/customer/account" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

#### CustomerAddress

**GET** `/customer/account/addresses` — customer bearer token. List my addresses

```powershell
curl.exe --request GET "${StoreBase}/customer/account/addresses" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**POST** `/customer/account/addresses` — customer bearer token. Save an address

```powershell
curl.exe --request POST "${StoreBase}/customer/account/addresses" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"recipient_name":"replace-with-value","line1":"replace-with-value","country":"replace-with-value"}'
```

**DELETE** `/customer/account/addresses/{address}` — customer bearer token. Delete an address

```powershell
curl.exe --request DELETE "${StoreBase}/customer/account/addresses/${Address}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**GET** `/customer/account/addresses/{address}` — customer bearer token. Get an address

```powershell
curl.exe --request GET "${StoreBase}/customer/account/addresses/${Address}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**PUT** `/customer/account/addresses/{address}` — customer bearer token. Replace an address

```powershell
curl.exe --request PUT "${StoreBase}/customer/account/addresses/${Address}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"recipient_name":"replace-with-value","line1":"replace-with-value","country":"replace-with-value"}'
```

**PATCH** `/customer/account/addresses/{address}` — this route accepts PATCH with the same update request shape.

```powershell
curl.exe --request PATCH "${StoreBase}/customer/account/addresses/${Address}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"recipient_name":"replace-with-value","line1":"replace-with-value","country":"replace-with-value"}'
```

#### CustomerErasureConfirmation

**POST** `/customer/account/erasure-emails` — customer bearer token. Email me an erasure link

```powershell
curl.exe --request POST "${StoreBase}/customer/account/erasure-emails" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**POST** `/customer/account/erasure-reauthentications/{provider}` — customer bearer token. Sign in again to erase

```powershell
curl.exe --request POST "${StoreBase}/customer/account/erasure-reauthentications/${Provider}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"state":"replace-with-value","code":"replace-with-value"}'
```

**POST** `/customer/account/erasure-reauthentications/{provider}/authorizations` — customer bearer token. Start signing in again to erase

```powershell
curl.exe --request POST "${StoreBase}/customer/account/erasure-reauthentications/${Provider}/authorizations" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"return_path":"replace-with-value"}'
```

#### CustomerGroup

**GET** `/staff/customer-groups` — staff bearer token. List customer groups

```powershell
curl.exe --request GET "${StoreBase}/staff/customer-groups" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/customer-groups` — staff bearer token. Add a customer group

```powershell
curl.exe --request POST "${StoreBase}/staff/customer-groups" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**DELETE** `/staff/customer-groups/{customerGroup}` — staff bearer token. Delete a customer group

```powershell
curl.exe --request DELETE "${StoreBase}/staff/customer-groups/${CustomerGroup}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/customer-groups/{customerGroup}` — staff bearer token. Show a customer group

```powershell
curl.exe --request GET "${StoreBase}/staff/customer-groups/${CustomerGroup}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/customer-groups/{customerGroup}` — staff bearer token. Change a customer group

```powershell
curl.exe --request PATCH "${StoreBase}/staff/customer-groups/${CustomerGroup}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**GET** `/staff/customer-groups/{customerGroup}/members` — staff bearer token. List a group's customers

```powershell
curl.exe --request GET "${StoreBase}/staff/customer-groups/${CustomerGroup}/members" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/customer-groups/{customerGroup}/members` — staff bearer token. Add customers to a group

```powershell
curl.exe --request POST "${StoreBase}/staff/customer-groups/${CustomerGroup}/members" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"customers":["replace-with-value"]}'
```

**DELETE** `/staff/customer-groups/{customerGroup}/members/{customer}` — staff bearer token. Remove a customer from a group

```powershell
curl.exe --request DELETE "${StoreBase}/staff/customer-groups/${CustomerGroup}/members/${Customer}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### CustomerIdentity

**GET** `/customer/account/identities` — customer bearer token. List my sign-in providers

```powershell
curl.exe --request GET "${StoreBase}/customer/account/identities" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**DELETE** `/customer/account/identities/{identity}` — customer bearer token. Disconnect a provider

```powershell
curl.exe --request DELETE "${StoreBase}/customer/account/identities/${Identity}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**POST** `/customer/account/identities/{provider}` — customer bearer token. Connect a provider

```powershell
curl.exe --request POST "${StoreBase}/customer/account/identities/${Provider}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"state":"replace-with-value","code":"replace-with-value"}'
```

**POST** `/customer/account/identities/{provider}/authorizations` — customer bearer token. Start connecting a provider

```powershell
curl.exe --request POST "${StoreBase}/customer/account/identities/${Provider}/authorizations" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"return_path":"replace-with-value"}'
```

#### CustomerNote

**GET** `/staff/customers/{customer}/notes` — staff bearer token. List a customer's notes

```powershell
curl.exe --request GET "${StoreBase}/staff/customers/${Customer}/notes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/customers/{customer}/notes` — staff bearer token. Add a note

```powershell
curl.exe --request POST "${StoreBase}/staff/customers/${Customer}/notes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"body":"replace-with-value"}'
```

**DELETE** `/staff/customers/{customer}/notes/{note}` — staff bearer token. Delete a note

```powershell
curl.exe --request DELETE "${StoreBase}/staff/customers/${Customer}/notes/${Note}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### CustomerOrder

**GET** `/customer/orders` — customer bearer token. List your orders

```powershell
curl.exe --request GET "${StoreBase}/customer/orders" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**GET** `/customer/orders/{order}` — customer bearer token. Show one of your orders

```powershell
curl.exe --request GET "${StoreBase}/customer/orders/${Order}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**POST** `/customer/orders/{order}/cancel` — customer bearer token. Cancel one of your orders

```powershell
curl.exe --request POST "${StoreBase}/customer/orders/${Order}/cancel" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

#### CustomerOrderPayment

**POST** `/customer/orders/{order}/payments` — customer bearer token. Pay for my order again

```powershell
curl.exe --request POST "${StoreBase}/customer/orders/${Order}/payments" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Idempotency-Key: replace-with-unique-request-key' `
  --header 'Content-Type: application/json' `
  --data-raw '{"payment_method":"replace-with-value"}'
```

#### CustomerPrivacy

**DELETE** `/customer/account` — customer bearer token. Erase my account

```powershell
curl.exe --request DELETE "${StoreBase}/customer/account" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**GET** `/customer/account/data-exports` — customer bearer token. List my data exports

```powershell
curl.exe --request GET "${StoreBase}/customer/account/data-exports" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**POST** `/customer/account/data-exports` — customer bearer token. Ask for my data

```powershell
curl.exe --request POST "${StoreBase}/customer/account/data-exports" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**GET** `/customer/account/data-exports/{export}/download` — customer bearer token. Download my data

```powershell
curl.exe --request GET "${StoreBase}/customer/account/data-exports/${Export}/download" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

#### CustomerTaxDocument

**GET** `/customer/orders/{order}/credit-notes/{creditNote}` — customer bearer token. Show one of your order's credit notes

```powershell
curl.exe --request GET "${StoreBase}/customer/orders/${Order}/credit-notes/${CreditNote}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**GET** `/customer/orders/{order}/credit-notes/{creditNote}/pdf` — customer bearer token. Download one of your order's credit notes

```powershell
curl.exe --request GET "${StoreBase}/customer/orders/${Order}/credit-notes/${CreditNote}/pdf" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**GET** `/customer/orders/{order}/invoice` — customer bearer token. Show your order's invoice

```powershell
curl.exe --request GET "${StoreBase}/customer/orders/${Order}/invoice" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**GET** `/customer/orders/{order}/invoice/pdf` — customer bearer token. Download your order's invoice

```powershell
curl.exe --request GET "${StoreBase}/customer/orders/${Order}/invoice/pdf" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

#### CustomerTaxId

**DELETE** `/customer/account/tax-id` — customer bearer token. Remove my tax ID

```powershell
curl.exe --request DELETE "${StoreBase}/customer/account/tax-id" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**PUT** `/customer/account/tax-id` — customer bearer token. Set my tax ID

```powershell
curl.exe --request PUT "${StoreBase}/customer/account/tax-id" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"tax_id":"replace-with-value","country":"replace-with-value"}'
```

#### DiscountPreview

**POST** `/staff/promotions/preview` — staff bearer token. Preview a basket's discounts

```powershell
curl.exe --request POST "${StoreBase}/staff/promotions/preview" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"lines":[{"variant":"replace-with-value","quantity":1}]}'
```

#### EmailChange

**POST** `/customer/account/email-changes` — customer bearer token. Start an email change

```powershell
curl.exe --request POST "${StoreBase}/customer/account/email-changes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com","current_password":"replace-with-value"}'
```

**POST** `/customer/account/email-changes/confirmations` — customer bearer token. Confirm an email change

```powershell
curl.exe --request POST "${StoreBase}/customer/account/email-changes/confirmations" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value"}'
```

#### EmailVerification

**POST** `/customer/account/email-verifications` — customer bearer token. Send a new verification link

```powershell
curl.exe --request POST "${StoreBase}/customer/account/email-verifications" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**POST** `/customer/auth/email-verifications` — public. Confirm an email

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/email-verifications" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value"}'
```

#### Geography

**GET** `/geography/countries` — public. List countries

```powershell
curl.exe --request GET "${StoreBase}/geography/countries" `
  --header 'Accept: application/json'
```

**GET** `/geography/countries/{country}` — public. Show a country

```powershell
curl.exe --request GET "${StoreBase}/geography/countries/${Country}" `
  --header 'Accept: application/json'
```

**GET** `/geography/countries/{country}/states` — public. List a country's states

```powershell
curl.exe --request GET "${StoreBase}/geography/countries/${Country}/states" `
  --header 'Accept: application/json'
```

**GET** `/geography/currencies` — public. List currencies

```powershell
curl.exe --request GET "${StoreBase}/geography/currencies" `
  --header 'Accept: application/json'
```

**GET** `/geography/locales` — public. List content languages

```powershell
curl.exe --request GET "${StoreBase}/geography/locales" `
  --header 'Accept: application/json'
```

**GET** `/geography/timezones` — public. List timezones

```powershell
curl.exe --request GET "${StoreBase}/geography/timezones" `
  --header 'Accept: application/json'
```

#### GuestOrder

**GET** `/guest/orders/{order}` — guest order token. Show your order

```powershell
curl.exe --request GET "${StoreBase}/guest/orders/${Order}" `
  --header 'Accept: application/json' `
  --header "X-Order-Token: $OrderToken"
```

**POST** `/guest/orders/{order}/cancel` — guest order token. Cancel your order

```powershell
curl.exe --request POST "${StoreBase}/guest/orders/${Order}/cancel" `
  --header 'Accept: application/json' `
  --header "X-Order-Token: $OrderToken"
```

#### GuestOrderPayment

**POST** `/guest/orders/{order}/payments` — guest order token. Pay for your order again

```powershell
curl.exe --request POST "${StoreBase}/guest/orders/${Order}/payments" `
  --header 'Accept: application/json' `
  --header "X-Order-Token: $OrderToken" `
  --header 'Idempotency-Key: replace-with-unique-request-key' `
  --header 'Content-Type: application/json' `
  --data-raw '{"payment_method":"replace-with-value"}'
```

#### GuestPrivacy

**POST** `/staff/guest-personal-data` — staff bearer token. Get a guest's personal data

```powershell
curl.exe --request POST "${StoreBase}/staff/guest-personal-data" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com"}'
```

**POST** `/staff/guest-personal-data/erasure` — staff bearer token. Erase a guest

```powershell
curl.exe --request POST "${StoreBase}/staff/guest-personal-data/erasure" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com"}'
```

#### GuestTaxDocument

**GET** `/guest/orders/{order}/credit-notes/{creditNote}` — guest order token. Show one of your order's credit notes

```powershell
curl.exe --request GET "${StoreBase}/guest/orders/${Order}/credit-notes/${CreditNote}" `
  --header 'Accept: application/json' `
  --header "X-Order-Token: $OrderToken"
```

**GET** `/guest/orders/{order}/credit-notes/{creditNote}/pdf` — guest order token. Download one of your order's credit notes

```powershell
curl.exe --request GET "${StoreBase}/guest/orders/${Order}/credit-notes/${CreditNote}/pdf" `
  --header 'Accept: application/json' `
  --header "X-Order-Token: $OrderToken"
```

**GET** `/guest/orders/{order}/invoice` — guest order token. Show your order's invoice

```powershell
curl.exe --request GET "${StoreBase}/guest/orders/${Order}/invoice" `
  --header 'Accept: application/json' `
  --header "X-Order-Token: $OrderToken"
```

**GET** `/guest/orders/{order}/invoice/pdf` — guest order token. Download your order's invoice

```powershell
curl.exe --request GET "${StoreBase}/guest/orders/${Order}/invoice/pdf" `
  --header 'Accept: application/json' `
  --header "X-Order-Token: $OrderToken"
```

#### ManualPaymentMethod

**GET** `/staff/payment-methods` — staff bearer token. List payment methods paid by hand

```powershell
curl.exe --request GET "${StoreBase}/staff/payment-methods" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/payment-methods/{method}` — staff bearer token. Set up a payment method paid by hand

```powershell
curl.exe --request PATCH "${StoreBase}/staff/payment-methods/${Method}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"enabled":true}'
```

#### MarketingConsent

**GET** `/customer/account/marketing-consents` — customer bearer token. List my marketing consent

```powershell
curl.exe --request GET "${StoreBase}/customer/account/marketing-consents" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken"
```

**PUT** `/customer/account/marketing-consents/{channel}` — customer bearer token. Answer for a channel

```powershell
curl.exe --request PUT "${StoreBase}/customer/account/marketing-consents/${Channel}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"granted":true}'
```

#### OrderNote

**GET** `/staff/orders/{order}/notes` — staff bearer token. List an order's notes

```powershell
curl.exe --request GET "${StoreBase}/staff/orders/${Order}/notes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/orders/{order}/notes` — staff bearer token. Add a note to an order

```powershell
curl.exe --request POST "${StoreBase}/staff/orders/${Order}/notes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"body":"replace-with-value"}'
```

**DELETE** `/staff/orders/{order}/notes/{note}` — staff bearer token. Delete a note on an order

```powershell
curl.exe --request DELETE "${StoreBase}/staff/orders/${Order}/notes/${Note}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### OwnershipTransfer

**POST** `/staff/team/ownership-transfers` — staff bearer token. Offer the store to a colleague

```powershell
curl.exe --request POST "${StoreBase}/staff/team/ownership-transfers" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"staff_member":"replace-with-value","current_password":"replace-with-value","roles":["replace-with-value"]}'
```

**GET** `/staff/team/ownership-transfers/pending` — staff bearer token. Show the pending ownership transfer

```powershell
curl.exe --request GET "${StoreBase}/staff/team/ownership-transfers/pending" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**DELETE** `/staff/team/ownership-transfers/{ownershipTransfer}` — staff bearer token. Cancel the offer

```powershell
curl.exe --request DELETE "${StoreBase}/staff/team/ownership-transfers/${OwnershipTransfer}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/team/ownership-transfers/{ownershipTransfer}/acceptance` — staff bearer token. Accept the store

```powershell
curl.exe --request POST "${StoreBase}/staff/team/ownership-transfers/${OwnershipTransfer}/acceptance" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"accepted_terms_of_service":"replace-with-value"}'
```

#### Password

**PUT** `/customer/auth/password` — customer bearer token. Change password

```powershell
curl.exe --request PUT "${StoreBase}/customer/auth/password" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $CustomerToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"current_password":"replace-with-value","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

**PUT** `/staff/auth/password` — staff bearer token. Change password

```powershell
curl.exe --request PUT "${StoreBase}/staff/auth/password" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"current_password":"replace-with-value","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

#### PasswordReset

**POST** `/customer/auth/password-resets` — public. Reset password

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/password-resets" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value","email":"owner@example.com","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

**POST** `/staff/auth/password-resets` — public. Reset password

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/password-resets" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value","email":"owner@example.com","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

#### PasswordResetLink

**POST** `/customer/auth/password-reset-links` — public. Request a password reset link

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/password-reset-links" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com"}'
```

**POST** `/staff/auth/password-reset-links` — public. Request a password reset link

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/password-reset-links" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com"}'
```

#### Payment

**GET** `/staff/orders/{order}/payments` — staff bearer token. List an order's payments

```powershell
curl.exe --request GET "${StoreBase}/staff/orders/${Order}/payments" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/payments` — staff bearer token. List payments

```powershell
curl.exe --request GET "${StoreBase}/staff/payments" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/payments/{payment}` — staff bearer token. Show a payment

```powershell
curl.exe --request GET "${StoreBase}/staff/payments/${Payment}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/payments/{payment}/checks` — staff bearer token. Ask the gateway again about a payment

```powershell
curl.exe --request POST "${StoreBase}/staff/payments/${Payment}/checks" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/payments/{payment}/collections` — staff bearer token. Record cash collected on delivery

```powershell
curl.exe --request POST "${StoreBase}/staff/payments/${Payment}/collections" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"amount":1}'
```

**POST** `/staff/payments/{payment}/receipts` — staff bearer token. Record a bank transfer received

```powershell
curl.exe --request POST "${StoreBase}/staff/payments/${Payment}/receipts" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"amount":1}'
```

#### PaymentGateway

**GET** `/staff/payment-gateways` — staff bearer token. List payment gateways

```powershell
curl.exe --request GET "${StoreBase}/staff/payment-gateways" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**DELETE** `/staff/payment-gateways/{gateway}/connection` — staff bearer token. Disconnect a payment gateway

```powershell
curl.exe --request DELETE "${StoreBase}/staff/payment-gateways/${Gateway}/connection" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/payment-gateways/{gateway}/connection` — staff bearer token. Change a payment gateway's connection

```powershell
curl.exe --request PATCH "${StoreBase}/staff/payment-gateways/${Gateway}/connection" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"enabled":true}'
```

**PUT** `/staff/payment-gateways/{gateway}/connection` — staff bearer token. Connect a payment gateway

```powershell
curl.exe --request PUT "${StoreBase}/staff/payment-gateways/${Gateway}/connection" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"credentials":["replace-with-value"]}'
```

**POST** `/staff/payment-gateways/{gateway}/connection/checks` — staff bearer token. Check a payment gateway's credentials

```powershell
curl.exe --request POST "${StoreBase}/staff/payment-gateways/${Gateway}/connection/checks" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### PaymentReturn

**GET** `/checkout/payments/{reference}` — public. Check a payment after the gateway

```powershell
curl.exe --request GET "${StoreBase}/checkout/payments/${Reference}" `
  --header 'Accept: application/json'
```

#### Product

**GET** `/staff/catalog/products` — staff bearer token. List products

```powershell
curl.exe --request GET "${StoreBase}/staff/catalog/products" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/catalog/products` — staff bearer token. Add a product

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/products" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":{"replace-with-key":"replace-with-value"},"variant":{"price":1}}'
```

**DELETE** `/staff/catalog/products/{product}` — staff bearer token. Move a product to the trash

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/products/${Product}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/catalog/products/{product}` — staff bearer token. Show a product

```powershell
curl.exe --request GET "${StoreBase}/staff/catalog/products/${Product}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/catalog/products/{product}` — staff bearer token. Change a product

```powershell
curl.exe --request PATCH "${StoreBase}/staff/catalog/products/${Product}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":{"replace-with-key":"replace-with-value"}}'
```

#### ProductImage

**POST** `/staff/catalog/products/{product}/images` — staff bearer token. Add an image

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/products/${Product}/images" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --form 'image=@C:\path\to\file.jpg'
```

**DELETE** `/staff/catalog/products/{product}/images/{image}` — staff bearer token. Delete an image

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/products/${Product}/images/${Image}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### ProductOptions

**PUT** `/staff/catalog/products/{product}/options` — staff bearer token. Set a product's options

```powershell
curl.exe --request PUT "${StoreBase}/staff/catalog/products/${Product}/options" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"attributes":["replace-with-value"],"variants":[{"id":"replace-with-value","values":["replace-with-value"]}]}'
```

#### ProductVariant

**POST** `/staff/catalog/products/{product}/variants` — staff bearer token. Add a variant

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/products/${Product}/variants" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"values":["replace-with-value"]}'
```

**DELETE** `/staff/catalog/products/{product}/variants/{variant}` — staff bearer token. Move a variant to the trash

```powershell
curl.exe --request DELETE "${StoreBase}/staff/catalog/products/${Product}/variants/${Variant}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/catalog/products/{product}/variants/{variant}` — staff bearer token. Change a variant

```powershell
curl.exe --request PATCH "${StoreBase}/staff/catalog/products/${Product}/variants/${Variant}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"price":1}'
```

#### Promotion

**GET** `/staff/promotions` — staff bearer token. List promotions

```powershell
curl.exe --request GET "${StoreBase}/staff/promotions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/promotions` — staff bearer token. Add a promotion

```powershell
curl.exe --request POST "${StoreBase}/staff/promotions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value","title":["replace-with-value"],"method":"automatic","type":"percentage","applies_to":"products","percent":"replace-with-value","discount":1,"buy_quantity":1,"get_quantity":1}'
```

**DELETE** `/staff/promotions/{promotion}` — staff bearer token. Archive a promotion

```powershell
curl.exe --request DELETE "${StoreBase}/staff/promotions/${Promotion}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/promotions/{promotion}` — staff bearer token. Show a promotion

```powershell
curl.exe --request GET "${StoreBase}/staff/promotions/${Promotion}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/promotions/{promotion}` — staff bearer token. Change a promotion

```powershell
curl.exe --request PATCH "${StoreBase}/staff/promotions/${Promotion}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value","title":["replace-with-value"],"method":"automatic","type":"percentage","applies_to":"products","percent":"replace-with-value","discount":1,"buy_quantity":1,"get_quantity":1}'
```

**POST** `/staff/promotions/{promotion}/activate` — staff bearer token. Activate a promotion

```powershell
curl.exe --request POST "${StoreBase}/staff/promotions/${Promotion}/activate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/promotions/{promotion}/deactivate` — staff bearer token. Deactivate a promotion

```powershell
curl.exe --request POST "${StoreBase}/staff/promotions/${Promotion}/deactivate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### PromotionCode

**GET** `/staff/promotions/{promotion}/codes` — staff bearer token. List a promotion's codes

```powershell
curl.exe --request GET "${StoreBase}/staff/promotions/${Promotion}/codes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/promotions/{promotion}/codes` — staff bearer token. Add a code

```powershell
curl.exe --request POST "${StoreBase}/staff/promotions/${Promotion}/codes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"code":"replace-with-value"}'
```

**POST** `/staff/promotions/{promotion}/codes/generate` — staff bearer token. Generate codes

```powershell
curl.exe --request POST "${StoreBase}/staff/promotions/${Promotion}/codes/generate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Idempotency-Key: replace-with-unique-request-key' `
  --header 'Content-Type: application/json' `
  --data-raw '{"count":1}'
```

**DELETE** `/staff/promotions/{promotion}/codes/{code}` — staff bearer token. Delete a code

```powershell
curl.exe --request DELETE "${StoreBase}/staff/promotions/${Promotion}/codes/${Code}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/promotions/{promotion}/codes/{code}` — staff bearer token. Change a code

```powershell
curl.exe --request PATCH "${StoreBase}/staff/promotions/${Promotion}/codes/${Code}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"is_active":true}'
```

#### PublishProduct

**POST** `/staff/catalog/products/{product}/publish` — staff bearer token. Publish a product

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/products/${Product}/publish" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### Quote

**POST** `/staff/pricing/quotes` — staff bearer token. Preview prices and tax

```powershell
curl.exe --request POST "${StoreBase}/staff/pricing/quotes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"lines":[{"variant":"replace-with-value","quantity":1}]}'
```

#### RecoveryCode

**POST** `/staff/auth/two-factor/recovery-codes` — staff bearer token. Replace recovery codes

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/two-factor/recovery-codes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"current_password":"replace-with-value"}'
```

#### Refund

**POST** `/staff/orders/{order}/refunds` — staff bearer token. Refund an order

```powershell
curl.exe --request POST "${StoreBase}/staff/orders/${Order}/refunds" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Idempotency-Key: replace-with-unique-request-key' `
  --header 'Content-Type: application/json' `
  --data-raw '{"amount":1}'
```

**GET** `/staff/refunds` — staff bearer token. List refunds

```powershell
curl.exe --request GET "${StoreBase}/staff/refunds" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/refunds/{refund}` — staff bearer token. Show a refund

```powershell
curl.exe --request GET "${StoreBase}/staff/refunds/${Refund}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### Registration

**POST** `/customer/auth/accounts` — public. Create a customer account

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/accounts" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value","email":"owner@example.com","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

#### ReorderCategories

**PUT** `/staff/catalog/categories/order` — staff bearer token. Reorder categories

```powershell
curl.exe --request PUT "${StoreBase}/staff/catalog/categories/order" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"parent":"replace-with-value","categories":["replace-with-value"]}'
```

#### ReorderProductImages

**PUT** `/staff/catalog/products/{product}/images/order` — staff bearer token. Reorder a product's images

```powershell
curl.exe --request PUT "${StoreBase}/staff/catalog/products/${Product}/images/order" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"images":["replace-with-value"]}'
```

#### RestoreBrand

**POST** `/staff/catalog/brands/{brand}/restore` — staff bearer token. Restore a brand

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/brands/${Brand}/restore" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### RestoreCategory

**POST** `/staff/catalog/categories/{category}/restore` — staff bearer token. Restore a category

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/categories/${Category}/restore" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### RestoreProduct

**POST** `/staff/catalog/products/{product}/restore` — staff bearer token. Restore a product

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/products/${Product}/restore" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### RestoreProductVariant

**POST** `/staff/catalog/products/{product}/variants/{variant}/restore` — staff bearer token. Restore a variant

```powershell
curl.exe --request POST "${StoreBase}/staff/catalog/products/${Product}/variants/${Variant}/restore" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### Shipment

**GET** `/staff/orders/{order}/shipments` — staff bearer token. List an order's shipments

```powershell
curl.exe --request GET "${StoreBase}/staff/orders/${Order}/shipments" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/orders/{order}/shipments` — staff bearer token. Start a shipment

```powershell
curl.exe --request POST "${StoreBase}/staff/orders/${Order}/shipments" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Idempotency-Key: replace-with-unique-request-key' `
  --header 'Content-Type: application/json' `
  --data-raw '{"lines":[{"line":"replace-with-value","quantity":1}]}'
```

**GET** `/staff/shipments` — staff bearer token. List shipments

```powershell
curl.exe --request GET "${StoreBase}/staff/shipments" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/shipments/{shipment}` — staff bearer token. Show a shipment

```powershell
curl.exe --request GET "${StoreBase}/staff/shipments/${Shipment}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/shipments/{shipment}` — staff bearer token. Change a shipment's tracking

```powershell
curl.exe --request PATCH "${StoreBase}/staff/shipments/${Shipment}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"tracking_number":"replace-with-value","tracking_url":"https://example.test/resource"}'
```

**POST** `/staff/shipments/{shipment}/cancel` — staff bearer token. Cancel a shipment

```powershell
curl.exe --request POST "${StoreBase}/staff/shipments/${Shipment}/cancel" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/shipments/{shipment}/deliver` — staff bearer token. Mark a shipment delivered, or a pickup collected

```powershell
curl.exe --request POST "${StoreBase}/staff/shipments/${Shipment}/deliver" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/shipments/{shipment}/ready` — staff bearer token. Mark a pickup ready

```powershell
curl.exe --request POST "${StoreBase}/staff/shipments/${Shipment}/ready" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/shipments/{shipment}/ship` — staff bearer token. Mark a shipment shipped

```powershell
curl.exe --request POST "${StoreBase}/staff/shipments/${Shipment}/ship" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### ShippingMethod

**GET** `/staff/shipping/methods` — staff bearer token. List shipping methods

```powershell
curl.exe --request GET "${StoreBase}/staff/shipping/methods" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/shipping/methods` — staff bearer token. Add a shipping method

```powershell
curl.exe --request POST "${StoreBase}/staff/shipping/methods" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"fulfilment":"ship","pricing":"flat","zone":"replace-with-value","name":["replace-with-value"],"rates":[{"from":1,"to":"replace-with-value","amount":1}]}'
```

**GET** `/staff/shipping/methods/{method}` — staff bearer token. Show a shipping method

```powershell
curl.exe --request GET "${StoreBase}/staff/shipping/methods/${Method}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/shipping/methods/{method}` — staff bearer token. Change a shipping method

```powershell
curl.exe --request PUT "${StoreBase}/staff/shipping/methods/${Method}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"fulfilment":"ship","pricing":"flat","zone":"replace-with-value","name":["replace-with-value"],"rates":[{"from":1,"to":"replace-with-value","amount":1}]}'
```

**PATCH** `/staff/shipping/methods/{method}` — this route accepts PATCH with the same update request shape.

```powershell
curl.exe --request PATCH "${StoreBase}/staff/shipping/methods/${Method}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"fulfilment":"ship","pricing":"flat","zone":"replace-with-value","name":["replace-with-value"],"rates":[{"from":1,"to":"replace-with-value","amount":1}]}'
```

**POST** `/staff/shipping/methods/{method}/archive` — staff bearer token. Archive a shipping method

```powershell
curl.exe --request POST "${StoreBase}/staff/shipping/methods/${Method}/archive" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### ShippingQuote

**POST** `/staff/shipping/quotes` — staff bearer token. Preview shipping

```powershell
curl.exe --request POST "${StoreBase}/staff/shipping/quotes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"country_code":"replace-with-value","lines":[{"variant":"replace-with-value","quantity":1}]}'
```

#### ShippingZone

**GET** `/staff/shipping/zones` — staff bearer token. List shipping zones

```powershell
curl.exe --request GET "${StoreBase}/staff/shipping/zones" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/shipping/zones` — staff bearer token. Add a shipping zone

```powershell
curl.exe --request POST "${StoreBase}/staff/shipping/zones" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**DELETE** `/staff/shipping/zones/{zone}` — staff bearer token. Delete a shipping zone

```powershell
curl.exe --request DELETE "${StoreBase}/staff/shipping/zones/${Zone}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/shipping/zones/{zone}` — staff bearer token. Show a shipping zone

```powershell
curl.exe --request GET "${StoreBase}/staff/shipping/zones/${Zone}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/shipping/zones/{zone}` — staff bearer token. Change a shipping zone

```powershell
curl.exe --request PUT "${StoreBase}/staff/shipping/zones/${Zone}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**PATCH** `/staff/shipping/zones/{zone}` — this route accepts PATCH with the same update request shape.

```powershell
curl.exe --request PATCH "${StoreBase}/staff/shipping/zones/${Zone}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**PUT** `/staff/shipping/zones/{zone}/regions` — staff bearer token. Set a zone's regions

```powershell
curl.exe --request PUT "${StoreBase}/staff/shipping/zones/${Zone}/regions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"regions":[{"country_code":"replace-with-value"}]}'
```

#### SignIn

**POST** `/customer/auth/tokens` — public. Sign in as a customer

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/tokens" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com","password":"replace-with-value"}'
```

**POST** `/driver/auth/tokens` — public. Sign in as a driver

```powershell
curl.exe --request POST "${StoreBase}/driver/auth/tokens" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"phone":"replace-with-value","pin":"replace-with-value"}'
```

**POST** `/staff/auth/tokens` — public. Sign in as a staff member

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/tokens" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com","password":"replace-with-value"}'
```

#### SocialAccountLink

**POST** `/customer/auth/social/link-confirmations` — public. Prove the account is yours with its password

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/social/link-confirmations" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"ticket":"replace-with-value","password":"replace-with-value"}'
```

**POST** `/customer/auth/social/link-email-confirmations` — public. Confirm from the emailed link

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/social/link-email-confirmations" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value"}'
```

**POST** `/customer/auth/social/link-emails` — public. Prove the account is yours by email

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/social/link-emails" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"ticket":"replace-with-value"}'
```

#### SocialSignIn

**POST** `/customer/auth/social/email-completions` — public. Give an email to finish signing in

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/social/email-completions" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"ticket":"replace-with-value","email":"owner@example.com"}'
```

**GET** `/customer/auth/social/providers` — public. List sign-in providers

```powershell
curl.exe --request GET "${StoreBase}/customer/auth/social/providers" `
  --header 'Accept: application/json'
```

**POST** `/customer/auth/social/{provider}/authorizations` — public. Start signing in with a provider

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/social/${Provider}/authorizations" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"return_path":"replace-with-value"}'
```

**POST** `/customer/auth/social/{provider}/callbacks` — public. Finish signing in with a provider

```powershell
curl.exe --request POST "${StoreBase}/customer/auth/social/${Provider}/callbacks" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"state":"replace-with-value","code":"replace-with-value"}'
```

#### StaffCustomer

**GET** `/staff/customers` — staff bearer token. List customers

```powershell
curl.exe --request GET "${StoreBase}/staff/customers" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**DELETE** `/staff/customers/{customer}` — staff bearer token. Move a customer to the trash

```powershell
curl.exe --request DELETE "${StoreBase}/staff/customers/${Customer}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/customers/{customer}` — staff bearer token. Show a customer

```powershell
curl.exe --request GET "${StoreBase}/staff/customers/${Customer}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/customers/{customer}` — staff bearer token. Update a customer

```powershell
curl.exe --request PATCH "${StoreBase}/staff/customers/${Customer}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**GET** `/staff/customers/{customer}/addresses` — staff bearer token. List a customer's addresses

```powershell
curl.exe --request GET "${StoreBase}/staff/customers/${Customer}/addresses" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/customers/{customer}/deactivate` — staff bearer token. Deactivate a customer

```powershell
curl.exe --request POST "${StoreBase}/staff/customers/${Customer}/deactivate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/customers/{customer}/reactivate` — staff bearer token. Reactivate a customer

```powershell
curl.exe --request POST "${StoreBase}/staff/customers/${Customer}/reactivate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/customers/{customer}/restore` — staff bearer token. Restore a customer from the trash

```powershell
curl.exe --request POST "${StoreBase}/staff/customers/${Customer}/restore" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/customers/{customer}/tax-exemption` — staff bearer token. Change a customer's tax exemption

```powershell
curl.exe --request PUT "${StoreBase}/staff/customers/${Customer}/tax-exemption" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"is_tax_exempt":true}'
```

#### StaffCustomerCart

**GET** `/staff/customers/{customer}/cart` — staff bearer token. Get a customer's cart

```powershell
curl.exe --request GET "${StoreBase}/staff/customers/${Customer}/cart" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### StaffCustomerPrivacy

**POST** `/staff/customers/{customer}/erasure` — staff bearer token. Erase a customer

```powershell
curl.exe --request POST "${StoreBase}/staff/customers/${Customer}/erasure" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"password":"replace-with-value"}'
```

**GET** `/staff/customers/{customer}/personal-data` — staff bearer token. Get a customer's personal data

```powershell
curl.exe --request GET "${StoreBase}/staff/customers/${Customer}/personal-data" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### StaffInvitation

**GET** `/staff/team/invitations` — staff bearer token. List open invitations

```powershell
curl.exe --request GET "${StoreBase}/staff/team/invitations" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/team/invitations` — staff bearer token. Invite someone to the team

```powershell
curl.exe --request POST "${StoreBase}/staff/team/invitations" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com","roles":["replace-with-value"]}'
```

**DELETE** `/staff/team/invitations/{staffInvitation}` — staff bearer token. Cancel an invitation

```powershell
curl.exe --request DELETE "${StoreBase}/staff/team/invitations/${StaffInvitation}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/team/invitations/{staffInvitation}/resend` — staff bearer token. Resend an invitation

```powershell
curl.exe --request POST "${StoreBase}/staff/team/invitations/${StaffInvitation}/resend" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### StaffInvitationAcceptance

**POST** `/staff/auth/invitation-acceptances` — public. Accept an invitation

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/invitation-acceptances" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value","name":"replace-with-value","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

**POST** `/staff/auth/invitation-previews` — public. Look up an invitation

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/invitation-previews" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value"}'
```

#### StaffMarketingConsent

**GET** `/staff/customers/{customer}/marketing-consents` — staff bearer token. List a customer's consent history

```powershell
curl.exe --request GET "${StoreBase}/staff/customers/${Customer}/marketing-consents" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/customers/{customer}/marketing-consents/withdrawals` — staff bearer token. Record a withdrawal

```powershell
curl.exe --request POST "${StoreBase}/staff/customers/${Customer}/marketing-consents/withdrawals" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"channel":"email"}'
```

#### StaffOrder

**GET** `/staff/customers/{customer}/orders` — staff bearer token. List a customer's orders

```powershell
curl.exe --request GET "${StoreBase}/staff/customers/${Customer}/orders" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/orders` — staff bearer token. List orders

```powershell
curl.exe --request GET "${StoreBase}/staff/orders" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/orders/{order}` — staff bearer token. Show an order

```powershell
curl.exe --request GET "${StoreBase}/staff/orders/${Order}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/orders/{order}/cancel` — staff bearer token. Cancel an order

```powershell
curl.exe --request POST "${StoreBase}/staff/orders/${Order}/cancel" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/orders/{order}/contact` — staff bearer token. Correct an order's contact details

```powershell
curl.exe --request PUT "${StoreBase}/staff/orders/${Order}/contact" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com"}'
```

**GET** `/staff/orders/{order}/events` — staff bearer token. Show an order's history

```powershell
curl.exe --request GET "${StoreBase}/staff/orders/${Order}/events" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/orders/{order}/shipping-address` — staff bearer token. Correct an order's shipping address

```powershell
curl.exe --request PUT "${StoreBase}/staff/orders/${Order}/shipping-address" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"recipient_name":"replace-with-value","line1":"replace-with-value","country":"replace-with-value"}'
```

**PUT** `/staff/orders/{order}/tags` — staff bearer token. Change an order's tags

```powershell
curl.exe --request PUT "${StoreBase}/staff/orders/${Order}/tags" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"tags":["replace-with-value"]}'
```

#### StaffRole

**GET** `/staff/team/permissions` — staff bearer token. List permissions roles can grant

```powershell
curl.exe --request GET "${StoreBase}/staff/team/permissions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/team/roles` — staff bearer token. List roles

```powershell
curl.exe --request GET "${StoreBase}/staff/team/roles" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/team/roles` — staff bearer token. Create a role

```powershell
curl.exe --request POST "${StoreBase}/staff/team/roles" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value","permissions":["catalog.manage"]}'
```

**DELETE** `/staff/team/roles/{staffRole}` — staff bearer token. Delete a role

```powershell
curl.exe --request DELETE "${StoreBase}/staff/team/roles/${StaffRole}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/team/roles/{staffRole}` — staff bearer token. Show a role

```powershell
curl.exe --request GET "${StoreBase}/staff/team/roles/${StaffRole}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/team/roles/{staffRole}` — staff bearer token. Update a role

```powershell
curl.exe --request PUT "${StoreBase}/staff/team/roles/${StaffRole}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value","permissions":["catalog.manage"]}'
```

#### StaffTwoFactorRequirement

**PUT** `/staff/store/settings/staff-two-factor` — staff bearer token. Require two-factor authentication for staff, or stop requiring it

```powershell
curl.exe --request PUT "${StoreBase}/staff/store/settings/staff-two-factor" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"required":true,"current_password":"replace-with-value"}'
```

#### StockLevel

**GET** `/staff/inventory/levels` — staff bearer token. List stock levels

```powershell
curl.exe --request GET "${StoreBase}/staff/inventory/levels" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### StockLocation

**GET** `/staff/inventory/locations` — staff bearer token. List locations

```powershell
curl.exe --request GET "${StoreBase}/staff/inventory/locations" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### StoreClosure

**POST** `/staff/store/closure` — staff bearer token. Close the store

```powershell
curl.exe --request POST "${StoreBase}/staff/store/closure" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"current_password":"replace-with-value"}'
```

#### StoreCurrency

**GET** `/staff/pricing/currencies` — staff bearer token. List the store's currencies

```powershell
curl.exe --request GET "${StoreBase}/staff/pricing/currencies" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/pricing/currencies` — staff bearer token. Add a currency

```powershell
curl.exe --request POST "${StoreBase}/staff/pricing/currencies" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"currency":"replace-with-value"}'
```

**GET** `/staff/pricing/currencies/{currency}` — staff bearer token. Show one of the store's currencies

```powershell
curl.exe --request GET "${StoreBase}/staff/pricing/currencies/${Currency}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/pricing/currencies/{currency}` — staff bearer token. Change one of the store's currencies

```powershell
curl.exe --request PATCH "${StoreBase}/staff/pricing/currencies/${Currency}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"is_active":true}'
```

**DELETE** `/staff/pricing/currencies/{currency}/rate` — staff bearer token. Go back to the market rate

```powershell
curl.exe --request DELETE "${StoreBase}/staff/pricing/currencies/${Currency}/rate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/pricing/currencies/{currency}/rate` — staff bearer token. Set a manual exchange rate

```powershell
curl.exe --request PUT "${StoreBase}/staff/pricing/currencies/${Currency}/rate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"rate":"replace-with-value"}'
```

#### StoreExport

**POST** `/staff/store/exports` — staff bearer token. Export the store's data

```powershell
curl.exe --request POST "${StoreBase}/staff/store/exports" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/store/exports/{storeExport}` — staff bearer token. Show one of your exports

```powershell
curl.exe --request GET "${StoreBase}/staff/store/exports/${StoreExport}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/store/exports/{storeExport}/download` — staff bearer token. Download one of your exports

```powershell
curl.exe --request GET "${StoreBase}/staff/store/exports/${StoreExport}/download" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### StorefrontBrand

**GET** `/catalog/brands` — public. List brands

```powershell
curl.exe --request GET "${StoreBase}/catalog/brands" `
  --header 'Accept: application/json'
```

#### StorefrontCategory

**GET** `/catalog/categories` — public. List categories

```powershell
curl.exe --request GET "${StoreBase}/catalog/categories" `
  --header 'Accept: application/json'
```

#### StorefrontCurrency

**GET** `/catalog/currencies` — public. List the currencies you can buy in

```powershell
curl.exe --request GET "${StoreBase}/catalog/currencies" `
  --header 'Accept: application/json'
```

#### StorefrontProduct

**GET** `/catalog/products` — public. List products

```powershell
curl.exe --request GET "${StoreBase}/catalog/products" `
  --header 'Accept: application/json'
```

**GET** `/catalog/products/{slug}` — public. Show a product

```powershell
curl.exe --request GET "${StoreBase}/catalog/products/${Slug}" `
  --header 'Accept: application/json'
```

#### StoreLogo

**DELETE** `/staff/store/settings/logo` — staff bearer token. Remove the store's logo

```powershell
curl.exe --request DELETE "${StoreBase}/staff/store/settings/logo" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/store/settings/logo` — staff bearer token. Set the store's logo

```powershell
curl.exe --request POST "${StoreBase}/staff/store/settings/logo" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --form 'logo=@C:\path\to\file.jpg'
```

#### StoreSettings

**GET** `/staff/store/settings` — staff bearer token. Show the store's settings

```powershell
curl.exe --request GET "${StoreBase}/staff/store/settings" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/store/settings` — staff bearer token. Change the store's settings

```powershell
curl.exe --request PATCH "${StoreBase}/staff/store/settings" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

#### TaxClass

**GET** `/staff/pricing/tax-classes` — staff bearer token. List tax classes

```powershell
curl.exe --request GET "${StoreBase}/staff/pricing/tax-classes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/pricing/tax-classes` — staff bearer token. Add a tax class

```powershell
curl.exe --request POST "${StoreBase}/staff/pricing/tax-classes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**DELETE** `/staff/pricing/tax-classes/{taxClass}` — staff bearer token. Delete a tax class

```powershell
curl.exe --request DELETE "${StoreBase}/staff/pricing/tax-classes/${TaxClass}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/pricing/tax-classes/{taxClass}` — staff bearer token. Show a tax class

```powershell
curl.exe --request GET "${StoreBase}/staff/pricing/tax-classes/${TaxClass}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/pricing/tax-classes/{taxClass}` — staff bearer token. Change a tax class

```powershell
curl.exe --request PUT "${StoreBase}/staff/pricing/tax-classes/${TaxClass}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**PATCH** `/staff/pricing/tax-classes/{taxClass}` — this route accepts PATCH with the same update request shape.

```powershell
curl.exe --request PATCH "${StoreBase}/staff/pricing/tax-classes/${TaxClass}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

#### TaxDocument

**GET** `/staff/credit-notes` — staff bearer token. List credit notes

```powershell
curl.exe --request GET "${StoreBase}/staff/credit-notes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/credit-notes/{creditNote}` — staff bearer token. Show a credit note

```powershell
curl.exe --request GET "${StoreBase}/staff/credit-notes/${CreditNote}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/credit-notes/{creditNote}/pdf` — staff bearer token. Download a credit note's PDF

```powershell
curl.exe --request GET "${StoreBase}/staff/credit-notes/${CreditNote}/pdf" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/invoices` — staff bearer token. List invoices

```powershell
curl.exe --request GET "${StoreBase}/staff/invoices" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/invoices/{invoice}` — staff bearer token. Show an invoice

```powershell
curl.exe --request GET "${StoreBase}/staff/invoices/${Invoice}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/invoices/{invoice}/pdf` — staff bearer token. Download an invoice's PDF

```powershell
curl.exe --request GET "${StoreBase}/staff/invoices/${Invoice}/pdf" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

#### TaxRate

**GET** `/staff/pricing/tax-rates` — staff bearer token. List tax rates

```powershell
curl.exe --request GET "${StoreBase}/staff/pricing/tax-rates" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/pricing/tax-rates` — staff bearer token. Add a tax rate

```powershell
curl.exe --request POST "${StoreBase}/staff/pricing/tax-rates" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"tax_class":"replace-with-value","country_code":"replace-with-value","name":"replace-with-value","rate":"replace-with-value"}'
```

**DELETE** `/staff/pricing/tax-rates/{taxRate}` — staff bearer token. Delete a tax rate

```powershell
curl.exe --request DELETE "${StoreBase}/staff/pricing/tax-rates/${TaxRate}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/pricing/tax-rates/{taxRate}` — staff bearer token. Show a tax rate

```powershell
curl.exe --request GET "${StoreBase}/staff/pricing/tax-rates/${TaxRate}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/pricing/tax-rates/{taxRate}` — staff bearer token. Change a tax rate

```powershell
curl.exe --request PUT "${StoreBase}/staff/pricing/tax-rates/${TaxRate}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

**PATCH** `/staff/pricing/tax-rates/{taxRate}` — this route accepts PATCH with the same update request shape.

```powershell
curl.exe --request PATCH "${StoreBase}/staff/pricing/tax-rates/${TaxRate}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value"}'
```

#### TeamMember

**GET** `/staff/team/members` — staff bearer token. List team members

```powershell
curl.exe --request GET "${StoreBase}/staff/team/members" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/team/members/{staffMember}` — staff bearer token. Show a team member

```powershell
curl.exe --request GET "${StoreBase}/staff/team/members/${StaffMember}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/team/members/{staffMember}/deactivate` — staff bearer token. Deactivate a team member

```powershell
curl.exe --request POST "${StoreBase}/staff/team/members/${StaffMember}/deactivate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**GET** `/staff/team/members/{staffMember}/permissions` — staff bearer token. Show what a team member may do, and why

```powershell
curl.exe --request GET "${StoreBase}/staff/team/members/${StaffMember}/permissions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/team/members/{staffMember}/permissions` — staff bearer token. Change a team member's direct permissions

```powershell
curl.exe --request PUT "${StoreBase}/staff/team/members/${StaffMember}/permissions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"permissions":["catalog.manage"]}'
```

**POST** `/staff/team/members/{staffMember}/reactivate` — staff bearer token. Reactivate a team member

```powershell
curl.exe --request POST "${StoreBase}/staff/team/members/${StaffMember}/reactivate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/team/members/{staffMember}/roles` — staff bearer token. Change a team member's roles

```powershell
curl.exe --request PUT "${StoreBase}/staff/team/members/${StaffMember}/roles" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"roles":["replace-with-value"]}'
```

#### TwoFactor

**DELETE** `/staff/auth/two-factor` — staff bearer token. Turn two-factor authentication off

```powershell
curl.exe --request DELETE "${StoreBase}/staff/auth/two-factor?current_password=$([uri]::EscapeDataString('replace-with-current_password'))" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/auth/two-factor` — staff bearer token. Start two-factor setup

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/two-factor" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"current_password":"replace-with-value"}'
```

#### TwoFactorChallenge

**POST** `/staff/auth/two-factor-challenges` — public. Answer a two-factor challenge

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/two-factor-challenges" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"challenge_token":"replace-with-value"}'
```

#### TwoFactorConfirmation

**POST** `/staff/auth/two-factor/confirmation` — staff bearer token. Confirm two-factor setup

```powershell
curl.exe --request POST "${StoreBase}/staff/auth/two-factor/confirmation" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"code":"replace-with-value"}'
```

#### VariantPricing

**PUT** `/staff/pricing/products/{product}/tax-class` — staff bearer token. Change the tax class of a product's variants

```powershell
curl.exe --request PUT "${StoreBase}/staff/pricing/products/${Product}/tax-class" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"tax_class":"replace-with-value"}'
```

**GET** `/staff/pricing/variants/{variant}` — staff bearer token. Show a variant's pricing

```powershell
curl.exe --request GET "${StoreBase}/staff/pricing/variants/${Variant}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/pricing/variants/{variant}` — staff bearer token. Change a variant's tax class

```powershell
curl.exe --request PATCH "${StoreBase}/staff/pricing/variants/${Variant}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"tax_class":"replace-with-value"}'
```

**DELETE** `/staff/pricing/variants/{variant}/prices/{currency}` — staff bearer token. Remove a variant's fixed price in a currency

```powershell
curl.exe --request DELETE "${StoreBase}/staff/pricing/variants/${Variant}/prices/${Currency}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PUT** `/staff/pricing/variants/{variant}/prices/{currency}` — staff bearer token. Set a variant's fixed price in a currency

```powershell
curl.exe --request PUT "${StoreBase}/staff/pricing/variants/${Variant}/prices/${Currency}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"price":1}'
```

#### VariantStock

**GET** `/staff/inventory/variants/{variant}` — staff bearer token. Show a variant's stock

```powershell
curl.exe --request GET "${StoreBase}/staff/inventory/variants/${Variant}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**PATCH** `/staff/inventory/variants/{variant}` — staff bearer token. Change how a variant's stock is handled

```powershell
curl.exe --request PATCH "${StoreBase}/staff/inventory/variants/${Variant}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"tracks_stock":true}'
```

**POST** `/staff/inventory/variants/{variant}/adjustments` — staff bearer token. Correct a variant's stock

```powershell
curl.exe --request POST "${StoreBase}/staff/inventory/variants/${Variant}/adjustments" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"reason":"recount"}'
```

**GET** `/staff/inventory/variants/{variant}/movements` — staff bearer token. List a variant's stock history

```powershell
curl.exe --request GET "${StoreBase}/staff/inventory/variants/${Variant}/movements" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken"
```

**POST** `/staff/inventory/variants/{variant}/receipts` — staff bearer token. Receive stock

```powershell
curl.exe --request POST "${StoreBase}/staff/inventory/variants/${Variant}/receipts" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $StaffToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"quantity":1}'
```

## Tenant asset delivery route

The tenancy package also registers `GET /tenancy/assets/{path?}` on tenant
domains. This serves tenant assets and is separate from the JSON API/OpenAPI
document. Use the asset path returned by the application:

```powershell
$AssetPath = 'replace-with-tenant-asset-path'
curl.exe --request GET "$StoreOrigin/tenancy/assets/$AssetPath" `
  --header 'Accept: */*'
```

## API docs and Postman

The interactive store API docs are available at `/docs/api` on the store
domain. For Postman, run `composer docs:export` from the repository root and
import `api-docs/store.json`. Set the collection base URL to this store's
`/api/v1` address. Use the staff, customer or driver bearer token only for
the matching endpoint family; guest tokens belong in their dedicated
headers.

The store docs' cross-origin “Try it” requests are allowed from the central
domain only in local development (`APP_ENV=local`). In other environments,
the API docs are restricted to authenticated platform admins with two-factor
authentication enabled.
