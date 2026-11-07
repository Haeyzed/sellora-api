# Platform identity

Platform identity is how Sellora's own team signs in to manage the service.
It is not the same as a merchant's store-staff account, shopper account or
delivery-driver account.

## Who signs in where?

| Person | Identity area | Stored in | Guard |
| --- | --- | --- | --- |
| Sellora platform administrator | Landlord Identity | Central database | `platform` |
| Merchant's owner/manager/cashier | Tenant Identity | That store's database | `staff` |
| Shopper | Tenant Customers | That store's database | `customer` |
| Store delivery driver | Tenant Delivery | That store's database | `driver` |

Each store has its own staff users and staff permissions. A role granted to
a platform administrator cannot be used as a store-staff role.

## Platform admin sign-in

`SignInPlatformAdmin` checks the email/password and account status. A
successful password check starts a second-factor challenge if two-factor
authentication is enabled; `CompletePlatformAdminSignIn` checks the
authenticator or recovery code before issuing the access token. Platform
administrators are required to use two-factor authentication.

Platform roles and permissions govern landlord actions such as managing
stores, plans, legal documents and other platform administrators. Invitation,
role-change, deactivation and recovery actions are audited. A platform admin
does not automatically become a staff member inside each tenant.

## Platform alerts

Problems on the platform side are emailed to every active super admin and
every active holder of `stores.manage`, each in their saved language
(`PUT /api/v1/platform/auth/language`; English when they saved none):

| Alert | Raised when | How often |
| --- | --- | --- |
| Exchange rates not refreshed | no rate fetch has succeeded for 12 hours (`exchange_rates.alert_after_hours`) | at most once a day |
| Store setup failed | setting up a new store failed after every retry | each time |
| Stock levels don't match their ledger | the scheduled `inventory:reconcile` finds a mismatch in a store | at most once a day per store |
| Invoices don't match the sales kept | the scheduled `orders:reconcile-invoices` finds a mismatch in a store | at most once a day per store |

The once-a-day limit is claimed atomically in the cache. An alert holds the
store's name and public ID and a count, never personal data; the details are
in the log of the check that found it. An alert that can't be sent is
reported to the error tracker and never breaks what raised it.

For store-side identity, see [Tenant Identity](../tenant/identity.md). For
the larger separation, see [Landlord overview](README.md).
