# Testing the API locally

Written for developers running Sellora with Laravel Herd on their own machine.

## Addresses

| What | Address |
|---|---|
| Platform API (admins) | `http://sellora-api.test/api/v1/platform/...` |
| Registration API | `http://sellora-api.test/api/v1/...` |
| A store's API | `http://<subdomain>.sellora-api.test/api/v1/...` |
| API docs | `http://sellora-api.test/docs/platform`, `/docs/registration`, `/docs/api` (store) |

The central domain is the first entry of `PLATFORM_CENTRAL_DOMAINS`, and store
subdomains sit on `PLATFORM_DOMAIN`. Keep both on the Herd site's domain
(`sellora-api.test`).

**Each store subdomain needs its own hosts entry on Windows**, because the
hosts file has no wildcards. As administrator, add to
`C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1 ada-fabrics.sellora-api.test
```

Herd itself already sends every `*.sellora-api.test` request to this project.
Use `http://`; HTTPS needs a valid certificate (`herd secure sellora-api`).

**If `PLATFORM_DOMAIN` changes after stores were registered**, their addresses
keep the old domain. Move them with:

```sh
php artisan stores:move-platform-domain <old-domain> --dry-run
php artisan stores:move-platform-domain <old-domain>
```

An address no store uses answers `404 store_not_found`; a wrong path on a
real store answers `404 not_found`.

## Signing in as a store's owner

Every request needs `Accept: application/json`.

```sh
# 1. Get a token (the owner's email and password from registration).
curl -s http://ada-fabrics.sellora-api.test/api/v1/staff/auth/tokens \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"owner@example.com","password":"your-password","device_name":"curl"}'
# -> {"data":{"token":"1|abc...","token_type":"Bearer","expires_at":"..."}}

# 2. Call a staff endpoint with it.
curl -s http://ada-fabrics.sellora-api.test/api/v1/staff/auth/me \
  -H "Accept: application/json" -H "Authorization: Bearer 1|abc..."
```

If the store requires two-factor authentication for staff and the owner has
it on, step 1 answers `202` with `{"data":{"challenge_token":"...","expires_at":"..."}}`.
Finish with the code from the authenticator app:

```sh
curl -s http://ada-fabrics.sellora-api.test/api/v1/staff/auth/two-factor-challenges \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"challenge_token":"<challenge_token>","code":"123456"}'
```

## Signing in as a platform admin

Platform admins always use two-factor authentication:

```sh
curl -s http://sellora-api.test/api/v1/platform/auth/tokens \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"your-password","device_name":"curl"}'
# -> 202 {"data":{"challenge_token":"...","expires_at":"..."}}  (5 minutes)

curl -s http://sellora-api.test/api/v1/platform/auth/two-factor-challenges \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"challenge_token":"<challenge_token>","code":"123456"}'
# -> {"data":{"token":"...","token_type":"Bearer",...}}
```

A recovery code works instead of the code: send `"recovery_code"` instead of `"code"`.

## "Try it" in the API docs

Each docs page sends requests to where that API is served: the platform and
registration docs to the central domain, the store docs to
`http://{store}.sellora-api.test/api/v1`. Fill in `store` with the store's
subdomain. Calling a store from the docs page is a cross-origin request,
which CORS allows from the central domain only when `APP_ENV=local` (or
`API_DOCS_TRY_IT=true`). Paste a token into the bearer authentication field.

## Postman

1. Export the three OpenAPI documents: `composer docs:export`. They are
   written to `api-docs/` (not committed): `platform.json`,
   `registration.json` and `store.json`.
2. In Postman: **Import**, choose the three files, and import each as a
   collection.
3. Create an environment with these variables:
   - `centralUrl` = `http://sellora-api.test`
   - `store` = the store's subdomain, such as `ada-fabrics`
   - `storeToken`, `platformToken` (left empty for now)
4. In each collection, open **Variables** and set `baseUrl`:
   - Platform: `{{centralUrl}}/api/v1/platform`
   - Registration: `{{centralUrl}}/api/v1`
   - Store: `http://{{store}}.sellora-api.test/api/v1`
5. On the Store collection, **Authorization**: type Bearer Token, token
   `{{storeToken}}`; on the Platform collection, `{{platformToken}}`.
   Requests inherit it from the collection.
6. Add `Accept: application/json` to every request: collection **Headers**
   (or a collection pre-request script:
   `pm.request.headers.upsert({key: 'Accept', value: 'application/json'});`).
7. Store the token automatically. On the store's `POST /staff/auth/tokens`
   request, **Scripts → Post-response**:

   ```js
   const body = pm.response.json();
   if (body.data && body.data.token) pm.environment.set('storeToken', body.data.token);
   ```

8. Platform sign-in takes two requests. On `POST /auth/tokens`:

   ```js
   const body = pm.response.json();
   if (pm.response.code === 202) pm.environment.set('challengeToken', body.data.challenge_token);
   ```

   and on `POST /auth/two-factor-challenges`, send
   `{"challenge_token": "{{challengeToken}}", "code": "123456"}` with:

   ```js
   const body = pm.response.json();
   if (body.data && body.data.token) pm.environment.set('platformToken', body.data.token);
   ```

Re-run `composer docs:export` and re-import (choosing "replace") whenever the
API changes.
