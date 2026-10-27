# Testing the landlord and registration APIs locally

This guide covers requests to the central Sellora domain: platform-admin
operations and public registration APIs. It is written for local development
with Laravel Herd on Windows. The examples use PowerShell and `curl.exe`.

## Addresses and setup

| API | Base URL |
| --- | --- |
| Platform API | `http://sellora-api.test/api/v1/platform` |
| Registration and shared geography API | `http://sellora-api.test/api/v1` |
| API docs | `/docs/platform` and `/docs/registration` on the central domain |

The central host must be listed in `PLATFORM_CENTRAL_DOMAINS`, and store
subdomains use `PLATFORM_DOMAIN`; keep both on Herd's `sellora-api.test`
domain for local development. If `PLATFORM_DOMAIN` changes after stores
exist, move their addresses with `php artisan stores:move-platform-domain
<old-domain> --dry-run` before running the command without `--dry-run`.

Set the common URLs and platform token in PowerShell:

```powershell
$CentralOrigin = 'http://sellora-api.test'
$CentralBase = "$CentralOrigin/api/v1"
$PlatformBase = "$CentralBase/platform"
$PlatformToken = 'replace-with-platform-admin-bearer-token'
```

Every request should send `Accept: application/json`. Store registration and
geography endpoints are public. Platform management endpoints require a
platform-admin bearer token; token sign-in and two-factor challenge endpoints
are public. A valid token still needs the platform permissions required by
the operation.

If you are testing a store from Windows, add its subdomain to
`C:\Windows\System32\drivers\etc\hosts` as described in the
[tenant API testing guide](../tenant/api-testing.md).

## Sign in as a platform administrator

Platform admins must complete two-factor authentication after password
sign-in:

```powershell
curl.exe --request POST "$PlatformBase/auth/tokens" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"admin@example.com","password":"replace-with-password","device_name":"curl"}'
```

The response is a two-factor challenge. Complete it with a current
authenticator code (or a recovery code):

```powershell
curl.exe --request POST "$PlatformBase/auth/two-factor-challenges" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"challenge_token":"replace-with-challenge-token","code":"123456"}'
```

Copy the returned bearer token into `$PlatformToken`. To inspect the current
admin and verify the token:

```powershell
curl.exe --request GET "$PlatformBase/auth/me" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

## Register a store

Registration starts on the central domain and does not require an existing
platform or tenant token. The first request begins a registration and sends
an email verification code; the later verification request creates the
central store record and queues tenant provisioning. Use the public
registration ID returned by the API in subsequent paths. Check the
registration OpenAPI page for the exact fields and current validation rules.

## Endpoint cURL reference

The commands below cover all 55 platform operations and all 12 registration
and shared-geography operations in the current Scramble OpenAPI exports.
The route list also accepts `PATCH` for the legal-document update route; that
alternative is included below even though Scramble exports its `PUT` form.
Request bodies show the documented shape; replace placeholder values with
valid local data. Examples include the required body fields (or one
representative field when all are optional). Path-variable assignments are
examples only. Optional
filters and pagination can be added using the query parameters shown in the
API docs. A route may also enforce business-state checks beyond its request
schema.

### Platform API

```powershell
$Store = 'replace-with-store-public-id'
$StoreRegistration = 'replace-with-registration-public-id'
$Plan = 'replace-with-plan-public-id'
$PlatformAdmin = 'replace-with-platform-admin-public-id'
$PlatformAdminInvitation = 'replace-with-invitation-public-id'
$PlatformRole = 'replace-with-role-public-id'
$FeatureGrant = 'replace-with-feature-grant-public-id'
$LimitKey = 'replace-with-limit-key'
$LegalDocument = 'replace-with-legal-document-public-id'
$StoreExport = 'replace-with-store-export-public-id'
```

#### AccessToken

**DELETE** `/auth/tokens/current` — platform bearer token. Sign out

```powershell
curl.exe --request DELETE "${PlatformBase}/auth/tokens/current" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**PUT** `/auth/tokens/current` — platform bearer token. Refresh the session token

```powershell
curl.exe --request PUT "${PlatformBase}/auth/tokens/current" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

#### CurrentPlatformAdmin

**GET** `/auth/me` — platform bearer token. Get the signed-in platform admin

```powershell
curl.exe --request GET "${PlatformBase}/auth/me" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

#### ExchangeRate

**GET** `/exchange-rates` — platform bearer token. List fetched exchange rates

```powershell
curl.exe --request GET "${PlatformBase}/exchange-rates" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

#### FeatureGrant

**GET** `/stores/{store}/feature-grants` — platform bearer token. List a store's feature grants

```powershell
curl.exe --request GET "${PlatformBase}/stores/${Store}/feature-grants" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/stores/{store}/feature-grants` — platform bearer token. Grant a feature

```powershell
curl.exe --request POST "${PlatformBase}/stores/${Store}/feature-grants" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"feature":"paystack","reason":"replace-with-value"}'
```

**DELETE** `/stores/{store}/feature-grants/{featureGrant}` — platform bearer token. Revoke a feature grant

```powershell
curl.exe --request DELETE "${PlatformBase}/stores/${Store}/feature-grants/${FeatureGrant}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

#### LegalDocument

**GET** `/legal-documents` — platform bearer token. List legal document versions

```powershell
curl.exe --request GET "${PlatformBase}/legal-documents" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/legal-documents` — platform bearer token. Draft a new version

```powershell
curl.exe --request POST "${PlatformBase}/legal-documents" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"type":"terms_of_service","version":"replace-with-value","title":"replace-with-value","body":"replace-with-value"}'
```

**GET** `/legal-documents/{legalDocument}` — platform bearer token. View a version

```powershell
curl.exe --request GET "${PlatformBase}/legal-documents/${LegalDocument}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**PUT** `/legal-documents/{legalDocument}` — platform bearer token. Revise a draft

```powershell
curl.exe --request PUT "${PlatformBase}/legal-documents/${LegalDocument}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"version":"replace-with-value","title":"replace-with-value","body":"replace-with-value"}'
```

**PATCH** `/legal-documents/{legalDocument}` — this route accepts PATCH with the same update request shape.

```powershell
curl.exe --request PATCH "${PlatformBase}/legal-documents/${LegalDocument}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"version":"replace-with-value","title":"replace-with-value","body":"replace-with-value"}'
```

#### LimitOverride

**GET** `/stores/{store}/limit-overrides` — platform bearer token. List a store's limit overrides

```powershell
curl.exe --request GET "${PlatformBase}/stores/${Store}/limit-overrides" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**DELETE** `/stores/{store}/limit-overrides/{limitKey}` — platform bearer token. Remove a limit override

```powershell
curl.exe --request DELETE "${PlatformBase}/stores/${Store}/limit-overrides/${LimitKey}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**PUT** `/stores/{store}/limit-overrides/{limitKey}` — platform bearer token. Set a limit override

```powershell
curl.exe --request PUT "${PlatformBase}/stores/${Store}/limit-overrides/${LimitKey}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"reason":"replace-with-value"}'
```

#### ManagedStore

**GET** `/stores` — platform bearer token. List stores

```powershell
curl.exe --request GET "${PlatformBase}/stores" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**GET** `/stores/{store}` — platform bearer token. Show a store

```powershell
curl.exe --request GET "${PlatformBase}/stores/${Store}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

#### ManagedStoreClosure

**DELETE** `/stores/{store}/closure` — platform bearer token. Restore a closed store

```powershell
curl.exe --request DELETE "${PlatformBase}/stores/${Store}/closure" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/stores/{store}/closure` — platform bearer token. Close a store

```powershell
curl.exe --request POST "${PlatformBase}/stores/${Store}/closure" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"reason":"replace-with-value"}'
```

#### Password

**PUT** `/auth/password` — platform bearer token. Change password

```powershell
curl.exe --request PUT "${PlatformBase}/auth/password" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"current_password":"replace-with-value","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

#### PasswordReset

**POST** `/auth/password-resets` — public. Reset password

```powershell
curl.exe --request POST "${PlatformBase}/auth/password-resets" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value","email":"owner@example.com","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

#### PasswordResetLink

**POST** `/auth/password-reset-links` — public. Request a password reset link

```powershell
curl.exe --request POST "${PlatformBase}/auth/password-reset-links" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com"}'
```

#### PlatformAdminInvitation

**GET** `/team/invitations` — platform bearer token. List open invitations

```powershell
curl.exe --request GET "${PlatformBase}/team/invitations" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/team/invitations` — platform bearer token. Invite someone to the platform team

```powershell
curl.exe --request POST "${PlatformBase}/team/invitations" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com","roles":["replace-with-value"]}'
```

**DELETE** `/team/invitations/{platformAdminInvitation}` — platform bearer token. Cancel an invitation

```powershell
curl.exe --request DELETE "${PlatformBase}/team/invitations/${PlatformAdminInvitation}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/team/invitations/{platformAdminInvitation}/resend` — platform bearer token. Resend an invitation

```powershell
curl.exe --request POST "${PlatformBase}/team/invitations/${PlatformAdminInvitation}/resend" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

#### PlatformAdminInvitationAcceptance

**POST** `/auth/invitation-acceptances` — public. Accept an invitation

```powershell
curl.exe --request POST "${PlatformBase}/auth/invitation-acceptances" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value","name":"replace-with-value","password":"replace-with-value","password_confirmation":"replace-with-value"}'
```

**POST** `/auth/invitation-previews` — public. Look up an invitation

```powershell
curl.exe --request POST "${PlatformBase}/auth/invitation-previews" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"token":"replace-with-value"}'
```

#### PlatformRole

**GET** `/team/permissions` — platform bearer token. List permissions platform roles can grant

```powershell
curl.exe --request GET "${PlatformBase}/team/permissions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**GET** `/team/roles` — platform bearer token. List platform roles

```powershell
curl.exe --request GET "${PlatformBase}/team/roles" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/team/roles` — platform bearer token. Create a platform role

```powershell
curl.exe --request POST "${PlatformBase}/team/roles" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value","permissions":["legal_documents.manage"]}'
```

**DELETE** `/team/roles/{platformRole}` — platform bearer token. Delete a platform role

```powershell
curl.exe --request DELETE "${PlatformBase}/team/roles/${PlatformRole}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**GET** `/team/roles/{platformRole}` — platform bearer token. Show a platform role

```powershell
curl.exe --request GET "${PlatformBase}/team/roles/${PlatformRole}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**PUT** `/team/roles/{platformRole}` — platform bearer token. Update a platform role

```powershell
curl.exe --request PUT "${PlatformBase}/team/roles/${PlatformRole}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"name":"replace-with-value","permissions":["legal_documents.manage"]}'
```

#### PlatformTeamMember

**GET** `/team/admins` — platform bearer token. List platform admins

```powershell
curl.exe --request GET "${PlatformBase}/team/admins" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**GET** `/team/admins/{platformAdmin}` — platform bearer token. Show a platform admin

```powershell
curl.exe --request GET "${PlatformBase}/team/admins/${PlatformAdmin}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/team/admins/{platformAdmin}/deactivate` — platform bearer token. Deactivate a platform admin

```powershell
curl.exe --request POST "${PlatformBase}/team/admins/${PlatformAdmin}/deactivate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**GET** `/team/admins/{platformAdmin}/permissions` — platform bearer token. Show what a platform admin may do, and why

```powershell
curl.exe --request GET "${PlatformBase}/team/admins/${PlatformAdmin}/permissions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**PUT** `/team/admins/{platformAdmin}/permissions` — platform bearer token. Change a platform admin's direct permissions

```powershell
curl.exe --request PUT "${PlatformBase}/team/admins/${PlatformAdmin}/permissions" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"permissions":["legal_documents.manage"]}'
```

**POST** `/team/admins/{platformAdmin}/reactivate` — platform bearer token. Reactivate a platform admin

```powershell
curl.exe --request POST "${PlatformBase}/team/admins/${PlatformAdmin}/reactivate" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**PUT** `/team/admins/{platformAdmin}/roles` — platform bearer token. Change a platform admin's roles

```powershell
curl.exe --request PUT "${PlatformBase}/team/admins/${PlatformAdmin}/roles" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"roles":["replace-with-value"]}'
```

**POST** `/team/admins/{platformAdmin}/two-factor-reset` — platform bearer token. Reset a platform admin's two-factor authentication

```powershell
curl.exe --request POST "${PlatformBase}/team/admins/${PlatformAdmin}/two-factor-reset" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"password":"replace-with-value"}'
```

#### PublishLegalDocument

**POST** `/legal-documents/{legalDocument}/publication` — platform bearer token. Publish a draft

```powershell
curl.exe --request POST "${PlatformBase}/legal-documents/${LegalDocument}/publication" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"effective_at":"2026-01-01T00:00:00Z"}'
```

#### RecoveryCode

**POST** `/auth/two-factor/recovery-codes` — platform bearer token. Replace recovery codes

```powershell
curl.exe --request POST "${PlatformBase}/auth/two-factor/recovery-codes" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"current_password":"replace-with-value"}'
```

#### RetryStoreProvisioning

**POST** `/stores/{store}/provisioning-retries` — platform bearer token. Retry setting up a store

```powershell
curl.exe --request POST "${PlatformBase}/stores/${Store}/provisioning-retries" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

#### SignIn

**POST** `/auth/tokens` — public. Sign in as a platform admin

```powershell
curl.exe --request POST "${PlatformBase}/auth/tokens" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"email":"owner@example.com","password":"replace-with-value"}'
```

#### StoreExport

**GET** `/stores/{store}/exports` — platform bearer token. List your exports of a store

```powershell
curl.exe --request GET "${PlatformBase}/stores/${Store}/exports" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/stores/{store}/exports` — platform bearer token. Export a store's data

```powershell
curl.exe --request POST "${PlatformBase}/stores/${Store}/exports" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**GET** `/stores/{store}/exports/{storeExport}` — platform bearer token. Show one of your exports

```powershell
curl.exe --request GET "${PlatformBase}/stores/${Store}/exports/${StoreExport}" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**GET** `/stores/{store}/exports/{storeExport}/download` — platform bearer token. Download one of your exports

```powershell
curl.exe --request GET "${PlatformBase}/stores/${Store}/exports/${StoreExport}/download" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

#### StoreSuspension

**DELETE** `/stores/{store}/suspension` — platform bearer token. Lift a suspension

```powershell
curl.exe --request DELETE "${PlatformBase}/stores/${Store}/suspension" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/stores/{store}/suspension` — platform bearer token. Suspend a store

```powershell
curl.exe --request POST "${PlatformBase}/stores/${Store}/suspension" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"reason":"replace-with-value"}'
```

#### TwoFactor

**DELETE** `/auth/two-factor` — platform bearer token. Turn two-factor authentication off

```powershell
curl.exe --request DELETE "${PlatformBase}/auth/two-factor?current_password=$([uri]::EscapeDataString('replace-with-current_password'))" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken"
```

**POST** `/auth/two-factor` — platform bearer token. Start two-factor setup

```powershell
curl.exe --request POST "${PlatformBase}/auth/two-factor" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"current_password":"replace-with-value"}'
```

#### TwoFactorChallenge

**POST** `/auth/two-factor-challenges` — public. Answer a two-factor challenge

```powershell
curl.exe --request POST "${PlatformBase}/auth/two-factor-challenges" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"challenge_token":"replace-with-value"}'
```

#### TwoFactorConfirmation

**POST** `/auth/two-factor/confirmation` — platform bearer token. Confirm two-factor setup

```powershell
curl.exe --request POST "${PlatformBase}/auth/two-factor/confirmation" `
  --header 'Accept: application/json' `
  --header "Authorization: Bearer $PlatformToken" `
  --header 'Content-Type: application/json' `
  --data-raw '{"code":"replace-with-value"}'
```

### Registration and shared geography API

```powershell
$Country = 'US'
$State = 'replace-with-state-code'
$StoreRegistration = 'replace-with-registration-public-id'
$HostingRegion = 'replace-with-region-public-id'
```

#### Geography

**GET** `/geography/countries` — public. List countries

```powershell
curl.exe --request GET "${CentralBase}/geography/countries" `
  --header 'Accept: application/json'
```

**GET** `/geography/countries/{country}` — public. Show a country

```powershell
curl.exe --request GET "${CentralBase}/geography/countries/${Country}" `
  --header 'Accept: application/json'
```

**GET** `/geography/countries/{country}/states` — public. List a country's states

```powershell
curl.exe --request GET "${CentralBase}/geography/countries/${Country}/states" `
  --header 'Accept: application/json'
```

**GET** `/geography/currencies` — public. List currencies

```powershell
curl.exe --request GET "${CentralBase}/geography/currencies" `
  --header 'Accept: application/json'
```

**GET** `/geography/locales` — public. List content languages

```powershell
curl.exe --request GET "${CentralBase}/geography/locales" `
  --header 'Accept: application/json'
```

**GET** `/geography/timezones` — public. List timezones

```powershell
curl.exe --request GET "${CentralBase}/geography/timezones" `
  --header 'Accept: application/json'
```

#### HostingRegion

**GET** `/hosting-regions` — public. List hosting regions

```powershell
curl.exe --request GET "${CentralBase}/hosting-regions" `
  --header 'Accept: application/json'
```

#### LegalDocumentInForce

**GET** `/legal-documents` — public. List the legal documents in force

```powershell
curl.exe --request GET "${CentralBase}/legal-documents" `
  --header 'Accept: application/json'
```

#### StoreRegistration

**POST** `/store-registrations` — public. Start registering a store

```powershell
curl.exe --request POST "${CentralBase}/store-registrations" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"store_name":"replace-with-value","subdomain":"replace-with-value","owner_name":"replace-with-value","email":"owner@example.com","password":"replace-with-value","country_code":"replace-with-value","hosting_region":"africa","accepted_legal_documents":["replace-with-value"],"password_confirmation":"replace-with-value"}'
```

**GET** `/store-registrations/{storeRegistration}` — public. Check a registration

```powershell
curl.exe --request GET "${CentralBase}/store-registrations/${StoreRegistration}" `
  --header 'Accept: application/json'
```

#### StoreRegistrationCode

**POST** `/store-registrations/{storeRegistration}/codes` — public. Send a new code

```powershell
curl.exe --request POST "${CentralBase}/store-registrations/${StoreRegistration}/codes" `
  --header 'Accept: application/json'
```

#### StoreRegistrationVerification

**POST** `/store-registrations/{storeRegistration}/verification` — public. Confirm a registration

```powershell
curl.exe --request POST "${CentralBase}/store-registrations/${StoreRegistration}/verification" `
  --header 'Accept: application/json' `
  --header 'Content-Type: application/json' `
  --data-raw '{"code":"replace-with-value"}'
```

## External callbacks (not in the OpenAPI exports)

These routes are callbacks for external services, not ordinary endpoints to
call with invented credentials.

### Payment webhook

`POST /webhooks/payments/{endpoint}` is called by the configured payment
provider. The provider signature must match the exact raw request body. For
local testing, use the provider's webhook tooling or a correctly signed
captured event; an unsigned cURL request should be rejected.

```powershell
$WebhookEndpoint = 'replace-with-endpoint-id'
$WebhookSignatureHeader = 'replace-with-provider-signature-header'
$WebhookSignature = 'replace-with-valid-signature-for-the-exact-file'
curl.exe --request POST "$CentralOrigin/webhooks/payments/$WebhookEndpoint" `
  --header 'Content-Type: application/json' `
  --header "$($WebhookSignatureHeader): $WebhookSignature" `
  --data-binary '@webhook-event.json'
```

### Social sign-in relay

The central `GET|POST /oauth/{provider}/callback` route is reached by the identity
provider after a tenant starts social sign-in. The provider-issued state and
authorization response must be intact; direct calls without them cannot
complete a sign-in.

```powershell
$Provider = 'google'
$ProviderAuthorizationCode = 'replace-with-provider-issued-code'
$ProviderState = 'replace-with-provider-issued-state'
curl.exe --get "$CentralOrigin/oauth/$Provider/callback" `
  --data-urlencode "code=$ProviderAuthorizationCode" `
  --data-urlencode "state=$ProviderState"
```

For Apple, the provider posts its callback response to the same callback path.
Use the exact form fields sent by the provider when reproducing that request:

```powershell
$Provider = 'apple'
curl.exe --request POST "$CentralOrigin/oauth/$Provider/callback" `
  --header 'Content-Type: application/x-www-form-urlencoded' `
  --data-urlencode "code=$ProviderAuthorizationCode" `
  --data-urlencode "state=$ProviderState"
```

If Apple's callback includes additional form fields (such as its first-login
user payload), include those exact provider-generated values too.

## API docs and Postman

The interactive docs are served at `/docs/platform` and `/docs/registration`
on the central domain. To import the latest API definitions into Postman, run
`composer docs:export`; the generated JSON files are written to the ignored
`api-docs/` directory as `platform.json`, `registration.json`, and
`store.json`.

Use the matching collection base URL and set a bearer token only for the
platform collection. Registration requests are public. Re-export after route
or request-contract changes so the imported collections stay current.
Outside local development, the docs pages may be restricted to an authenticated
platform admin with two-factor authentication enabled or disabled by
configuration.
