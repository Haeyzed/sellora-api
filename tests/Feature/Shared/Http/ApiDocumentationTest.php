<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\AccessTokenIssuer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/*
 * The API docs' "Try it" sends each request where that API is served: the
 * platform and registration APIs to the central domain, the store API to
 * the store subdomain the reader fills in.
 */

it('sends the central APIs to the central domain and the store API to a store subdomain the reader chooses', function (): void {
    $central = 'http://'.config()->array('platform.central_domains')[0];

    expect(documentedServers('platform'))->toBe([['url' => "{$central}/api/v1/platform", 'description' => 'Central']])
        ->and(documentedServers('registration'))->toBe([['url' => "{$central}/api/v1", 'description' => 'Central']]);

    $store = documentedServers('default');
    expect($store[0]['url'])->toBe('http://{store}.'.config('platform.domain').'/api/v1')
        ->and($store[0]['variables']['store']['default'])->toBe('your-store');
});

/*
 * Section 11: every list is paged by page number or by cursor, chosen when
 * it is built, and its docs say which. Changing a list's type later breaks
 * API clients, so this table is the record: a new list must be added here.
 */

const PAGE_NUMBER_LISTS = [
    'default' => [
        '/staff/team/members', '/staff/team/roles', '/staff/team/invitations',
        '/staff/catalog/brands', '/staff/catalog/categories', '/staff/catalog/attributes', '/staff/customer-groups', '/staff/promotions',
        '/catalog/brands', '/catalog/categories',
        '/staff/inventory/locations',
        '/staff/pricing/tax-classes', '/staff/pricing/tax-rates', '/staff/pricing/currencies', '/catalog/currencies',
    ],
    'platform' => [
        '/team/admins', '/team/roles', '/team/invitations',
        '/stores', '/stores/{store}/feature-grants', '/stores/{store}/exports',
        '/legal-documents',
    ],
];

const CURSOR_LISTS = [
    'default' => ['/staff/catalog/products', '/catalog/products', '/staff/inventory/levels', '/staff/inventory/variants/{variant}/movements', '/staff/customers', '/staff/customers/{customer}/notes', '/staff/customers/{customer}/marketing-consents', '/staff/customer-groups/{customerGroup}/members', '/staff/promotions/{promotion}/codes'],
    'platform' => ['/exchange-rates'],
];

it('documents every list as paged by page number or by cursor, never both and never neither', function (): void {
    foreach (['default', 'platform'] as $api) {
        $document = documentedApi($api);
        $found = ['page' => [], 'cursor' => []];

        foreach ($document['paths'] as $path => $operations) {
            $list = $operations['get'] ?? null;
            $meta = $list['responses'][200]['content']['application/json']['schema']['properties']['meta']['properties'] ?? null;
            $parameters = array_column($list['parameters'] ?? [], 'name');

            if ($meta === null && ! in_array('per_page', $parameters, true)) {
                continue;
            }

            $type = in_array('page', $parameters, true) ? 'page' : 'cursor';
            $found[$type][] = $path;

            if ($type === 'page') {
                expect($meta)->toHaveKeys(['total', 'last_page', 'current_page'], "{$path} is paged by number")->not->toHaveKey('next_cursor')
                    ->and($parameters)->not->toContain('cursor')
                    ->and($list['description'])->toContain('Paged by page number');
            } else {
                expect($meta)->toHaveKeys(['next_cursor', 'prev_cursor'], "{$path} is paged by cursor")->not->toHaveKey('total')
                    ->and($parameters)->toContain('cursor')
                    ->and($list['description'])->toContain('Paged by cursor');
            }

            expect($list['description'])->toContain('Up to '.config()->integer('api.pagination.max_per_page').' records per page');
        }

        expect($found['page'])->toEqualCanonicalizing(PAGE_NUMBER_LISTS[$api])
            ->and($found['cursor'])->toEqualCanonicalizing(CURSOR_LISTS[$api]);
    }
});

/*
 * Step 9: every pricing, tax and exchange rate endpoint is documented, with
 * the storefront's currency fallback spelled out and business errors listed.
 */
it('documents every pricing, tax and exchange rate endpoint', function (): void {
    $store = documentedApi('default');
    $platform = documentedApi('platform');

    $expected = [
        '/staff/pricing/tax-classes' => ['get', 'post'],
        // Resource routes take changes as PUT or PATCH; the docs list them under PUT.
        '/staff/pricing/tax-classes/{taxClass}' => ['get', 'put', 'delete'],
        '/staff/pricing/tax-rates' => ['get', 'post'],
        '/staff/pricing/tax-rates/{taxRate}' => ['get', 'put', 'delete'],
        '/staff/pricing/variants/{variant}' => ['get', 'patch'],
        '/staff/pricing/variants/{variant}/prices/{currency}' => ['put', 'delete'],
        '/staff/pricing/products/{product}/tax-class' => ['put'],
        '/staff/pricing/currencies' => ['get', 'post'],
        '/staff/pricing/currencies/{currency}' => ['get', 'patch'],
        '/staff/pricing/currencies/{currency}/rate' => ['put', 'delete'],
        '/staff/pricing/quotes' => ['post'],
        '/catalog/currencies' => ['get'],
    ];

    foreach ($expected as $path => $methods) {
        expect($store['paths'])->toHaveKey($path)
            ->and(array_keys($store['paths'][$path]))->toContain(...$methods);
    }

    expect($platform['paths'])->toHaveKey('/exchange-rates');

    foreach (['/catalog/products', '/catalog/products/{slug}'] as $path) {
        $currency = collect($store['paths'][$path]['get']['parameters'])->firstWhere('name', 'currency');
        expect($currency['description'] ?? '')->toContain("store's own currency");
    }

    expect($store['paths']['/catalog/products']['get']['description'])->toContain("store's own currency")
        ->and(json_encode($store['paths']['/staff/pricing/quotes']['post']['responses'][422]))->toContain('currency_not_offered')->toContain('discount_exceeds_line')
        ->and(json_encode($store['paths']['/staff/pricing/tax-classes/{taxClass}']['delete']['responses'][409]))->toContain('tax_class_in_use')
        ->and(json_encode($store['paths']['/staff/pricing/currencies']['post']['responses'][403]))->toContain('usage_limit_reached');
});

/*
 * Step 10: every customer endpoint is documented, with its business errors.
 */
it('documents every customer endpoint', function (): void {
    $store = documentedApi('default');

    $expected = [
        '/customer/account' => ['patch', 'delete'],
        '/customer/account/data-exports' => ['get', 'post'],
        '/customer/account/data-exports/{export}/download' => ['get'],
        '/staff/customers/{customer}/personal-data' => ['get'],
        '/staff/customers/{customer}/erasure' => ['post'],
        '/customer/account/email-verifications' => ['post'],
        '/customer/account/email-changes' => ['post'],
        '/customer/account/email-changes/confirmations' => ['post'],
        '/customer/auth/email-verifications' => ['post'],
        '/customer/account/addresses' => ['get', 'post'],
        '/customer/account/addresses/{address}' => ['get', 'put', 'delete'],
        '/staff/customers' => ['get'],
        '/staff/customers/{customer}' => ['get', 'patch', 'delete'],
        '/staff/customers/{customer}/restore' => ['post'],
        '/staff/customers/{customer}/deactivate' => ['post'],
        '/staff/customers/{customer}/reactivate' => ['post'],
        '/staff/customers/{customer}/addresses' => ['get'],
        '/staff/customers/{customer}/notes' => ['get', 'post'],
        '/staff/customers/{customer}/notes/{note}' => ['delete'],
        '/customer/account/tax-id' => ['put', 'delete'],
        '/staff/customers/{customer}/tax-exemption' => ['put'],
        '/customer/account/marketing-consents' => ['get'],
        '/customer/account/marketing-consents/{channel}' => ['put'],
        '/staff/customers/{customer}/marketing-consents' => ['get'],
        '/staff/customers/{customer}/marketing-consents/withdrawals' => ['post'],
        '/staff/customer-groups' => ['get', 'post'],
        '/staff/customer-groups/{customerGroup}' => ['get', 'patch', 'delete'],
        '/staff/customer-groups/{customerGroup}/members' => ['get', 'post'],
        '/staff/customer-groups/{customerGroup}/members/{customer}' => ['delete'],
    ];

    foreach ($expected as $path => $methods) {
        expect($store['paths'])->toHaveKey($path)
            ->and(array_keys($store['paths'][$path]))->toContain(...$methods);
    }

    expect(json_encode($store['paths']['/customer/auth/email-verifications']['post']['responses'][422]))->toContain('customer_email_link_invalid')
        ->and(json_encode($store['paths']['/customer/account/email-verifications']['post']['responses'][409]))->toContain('customer_email_already_verified')
        ->and(json_encode($store['paths']['/customer/account/email-changes']['post']['responses'][422]))->toContain('current_password_incorrect')
        ->and(json_encode($store['paths']['/customer/account/addresses']['post']['responses'][422]))->toContain('customer_address_limit_reached')
        ->and($store['paths']['/customer/account/addresses']['post']['requestBody']['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/CustomerAddressRequest')
        ->and(array_keys($store['components']['schemas']['CustomerAddressRequest']['properties']))
        ->toContain('recipient_name', 'line1', 'city', 'state', 'postal_code', 'country', 'phone', 'is_default_shipping', 'is_default_billing')
        ->and($store['paths']['/customer/auth/email-verifications']['post']['security'] ?? null)->toBe([])
        ->and(array_keys($store['paths']['/staff/customers']))->not->toContain('post')
        ->and(json_encode($store['paths']['/customer/account/marketing-consents/{channel}']['put']['responses'][422]))->toContain('marketing_contact_missing')
        ->and(array_keys($store['paths']['/staff/customers/{customer}/marketing-consents']))->toBe(['get'])
        ->and(json_encode($store['paths']['/staff/customers/{customer}/tax-exemption']['put']['responses'][422]))->toContain('customer_tax_id_missing')
        ->and(json_encode($store['paths']['/customer/account/data-exports']['post']['responses'][403]))->toContain('customer_email_not_verified')
        ->and(json_encode($store['paths']['/customer/account']['delete']['responses'][422]))->toContain('current_password_incorrect')
        ->and(json_encode($store['paths']['/staff/customers/{customer}/erasure']['post']['responses'][422]))->toContain('current_password_incorrect')
        ->and(collect($store['paths']['/staff/customers']['get']['parameters'])->pluck('name')->all())->toContain('search', 'is_active', 'email_verified', 'trashed', 'group', 'cursor')
        ->and(json_encode($store['paths']['/staff/customer-groups']['post']['responses'][422]))->toContain('customer_group_name_taken')
        ->and($store['paths']['/staff/customers/{customer}']['patch']['description'])->toContain('Staff never change a customer\'s');
});

/*
 * Step 11: every promotion endpoint is documented, with its business errors.
 */
it('documents every promotion endpoint', function (): void {
    $store = documentedApi('default');

    $expected = [
        '/staff/promotions' => ['get', 'post'],
        '/staff/promotions/{promotion}' => ['get', 'patch', 'delete'],
        '/staff/promotions/{promotion}/activate' => ['post'],
        '/staff/promotions/{promotion}/deactivate' => ['post'],
        '/staff/promotions/{promotion}/codes' => ['get', 'post'],
        '/staff/promotions/{promotion}/codes/generate' => ['post'],
        '/staff/promotions/{promotion}/codes/{code}' => ['patch', 'delete'],
        '/staff/promotions/preview' => ['post'],
    ];

    foreach ($expected as $path => $methods) {
        expect($store['paths'])->toHaveKey($path)
            ->and(array_keys($store['paths'][$path]))->toContain(...$methods);
    }

    expect(collect($store['paths']['/staff/promotions']['get']['parameters'])->pluck('name')->all())->toContain('status', 'method', 'type', 'search', 'page')
        ->and(array_keys($store['components']['schemas']['SavePromotionRequest']['properties']))->toContain('type', 'percent', 'discount', 'currency_amounts', 'combines_with', 'products', 'customer_groups')
        ->and(json_encode($store['paths']['/staff/promotions/{promotion}/codes']['post']['responses'][422]))->toContain('promotion_code_taken')->toContain('promotion_takes_no_codes')
        ->and(array_keys($store['paths']['/staff/promotions/{promotion}/codes/generate']['post']['responses']))->toContain(200, 202)
        ->and(array_keys($store['components']['schemas']['DiscountedBasketResource']['properties']))->toBe(['basket', 'discounts', 'shipping_discount', 'code']);
});

/*
 * Section 11: the docs are for development. Outside a developer's machine
 * they are shown only to platform admins signed in with two-factor
 * authentication, through their bearer token.
 */

const API_DOCS_PAGES = ['/docs/api', '/docs/api.json', '/docs/platform', '/docs/platform.json', '/docs/registration', '/docs/registration.json'];

it('shows the API docs to anyone on a developer\'s machine', function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
    serveGeneratedApiDocumentation();

    foreach (API_DOCS_PAGES as $page) {
        $this->get(centralUrl($page))->assertOk();
    }
});

it('shows the API docs elsewhere only to a platform admin signed in with two-factor authentication', function (): void {
    app()->detectEnvironment(static fn (): string => 'production');
    serveGeneratedApiDocumentation();
    $tokenOf = static fn (PlatformAdmin $admin): string => app(AccessTokenIssuer::class)->issue($admin, PlatformAdmin::GUARD, 'test')->plainTextToken;

    $withoutTwoFactor = PlatformAdmin::factory()->create();
    $withTwoFactor = PlatformAdmin::factory()->create();
    enableTwoFactor($withTwoFactor);

    foreach (API_DOCS_PAGES as $page) {
        forgetSignIns();
        $this->get(centralUrl($page))->assertForbidden();
        forgetSignIns();
        $this->withToken($tokenOf($withoutTwoFactor))->get(centralUrl($page))->assertForbidden();
        forgetSignIns();
        $this->withToken($tokenOf($withTwoFactor))->get(centralUrl($page))->assertOk();
        $this->withoutToken();
    }
});
