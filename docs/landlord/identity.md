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

For store-side identity, see [Tenant Identity](../tenant/identity.md). For
the larger separation, see [Landlord overview](README.md).
