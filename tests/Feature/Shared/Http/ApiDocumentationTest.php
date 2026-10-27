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
        '/staff/catalog/brands', '/staff/catalog/categories', '/staff/catalog/attributes', '/staff/customer-groups', '/staff/promotions', '/staff/shipping/zones', '/staff/shipping/methods',
        '/catalog/brands', '/catalog/categories',
        '/staff/inventory/locations',
        '/staff/delivery/drivers',
        '/staff/pricing/tax-classes', '/staff/pricing/tax-rates', '/staff/pricing/currencies', '/catalog/currencies',
    ],
    'platform' => [
        '/team/admins', '/team/roles', '/team/invitations',
        '/stores', '/stores/{store}/feature-grants', '/stores/{store}/exports',
        '/legal-documents',
    ],
];

const CURSOR_LISTS = [
    'default' => ['/staff/catalog/products', '/catalog/products', '/staff/inventory/levels', '/staff/inventory/variants/{variant}/movements', '/staff/customers', '/staff/customers/{customer}/notes', '/staff/customers/{customer}/marketing-consents', '/staff/customer-groups/{customerGroup}/members', '/staff/promotions/{promotion}/codes', '/staff/orders', '/staff/customers/{customer}/orders', '/staff/shipments', '/customer/orders', '/staff/payments', '/staff/refunds', '/staff/invoices', '/staff/credit-notes'],
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
        ->and(json_encode($store['paths']['/staff/promotions/{promotion}/codes/{code}']['delete']['responses'][409]))->toContain('promotion_code_in_use')
        ->and(array_keys($store['components']['schemas']['DiscountedBasketResource']['properties']))->toBe(['basket', 'discounts', 'shipping_discount', 'code']);
});

/*
 * Fields and filters Scramble couldn't type are documented with their real
 * types, never as an unknown one shown as a string.
 */
it('documents counts and filters with their real types', function (): void {
    $store = documentedApi('default');
    $status = collect($store['paths']['/staff/inventory/levels']['get']['parameters'])->firstWhere('name', 'status');

    expect($status['schema'])->toBe(['type' => 'string', 'enum' => ['low', 'out']])
        ->and($store['components']['schemas']['StaffRoleResource']['properties']['staff_count']['type'])->toBe('integer')
        ->and($store['components']['schemas']['StaffRoleResource']['required'])->not->toContain('staff_count')
        ->and($store['components']['schemas']['PromotionResource']['properties']['times_reserved']['type'])->toBe('integer')
        ->and($store['components']['schemas']['PromotionResource']['required'])->toContain('times_reserved');
});

it('types everything in each API, with no unknown types and no empty operations', function (string $api): void {
    $this->artisan('scramble:analyze', ['--api' => $api, '--fail-on-unknown' => true, '--fail-on-empty' => true])->assertExitCode(0);
})->with(['default', 'platform', 'registration']);

/*
 * Step 12: every cart endpoint is documented, with the guest's cart token,
 * the version a change is made from, each line's status and price change,
 * the tax estimate, and the business errors.
 */
it('documents every shipping endpoint', function (): void {
    $store = documentedApi('default');

    $expected = [
        '/staff/shipping/zones' => ['get', 'post'],
        '/staff/shipping/zones/{zone}' => ['get', 'put', 'delete'],
        '/staff/shipping/zones/{zone}/regions' => ['put'],
        '/staff/shipping/methods' => ['get', 'post'],
        '/staff/shipping/methods/{method}' => ['get', 'put'],
        '/staff/shipping/methods/{method}/archive' => ['post'],
        '/staff/shipping/quotes' => ['post'],
    ];

    foreach ($expected as $path => $methods) {
        expect($store['paths'])->toHaveKey($path)
            ->and(array_keys($store['paths'][$path]))->toContain(...$methods);
    }

    expect($store['paths']['/staff/shipping/zones']['get']['description'])->toContain('Paged by page number')
        ->and(array_keys($store['components']['schemas']['ShippingZoneResource']['properties']))->toContain('id', 'name', 'is_active', 'covers_rest_of_world', 'regions')
        ->and(array_keys($store['components']['schemas']['ShippingZoneResource']['properties']['regions']['items']['properties']))->toBe(['country_code', 'state_code'])
        ->and(json_encode($store['paths']['/staff/shipping/zones/{zone}/regions']['put']['responses'][409]))->toContain('shipping_region_taken')
        ->and(json_encode($store['paths']['/staff/shipping/zones']['post']['responses'][409]))->toContain('rest_of_world_zone_exists')
        ->and(json_encode($store['paths']['/staff/shipping/zones/{zone}']['put']['responses'][422]))->toContain('rest_of_world_zone_has_regions')->toContain('shipping_zone_name_taken')
        ->and(json_encode($store['paths']['/staff/shipping/zones/{zone}']['delete']['responses'][409]))->toContain('shipping_zone_has_methods')
        ->and($store['paths']['/staff/shipping/methods']['get']['description'])->toContain('Paged by page number')
        ->and(array_keys($store['components']['schemas']['ShippingMethodResource']['properties']))->toContain('zone', 'name', 'fulfilment', 'pricing', 'carrier', 'carrier_service', 'backup', 'free_over', 'currency_amounts', 'rates', 'warnings', 'pickup', 'archived_at')
        ->and(array_keys($store['components']['schemas']['QuoteShippingRequest']['properties']))->toContain('country_code', 'state_code', 'address', 'lines')
        ->and(array_keys($store['components']['schemas']['ShippingMethodResource']['properties']['warnings']['items']['properties']))->toBe(['code', 'from', 'to'])
        ->and(array_keys($store['components']['schemas']['ShippingMethodResource']['properties']['rates']['items']['properties']))->toBe(['from', 'to', 'amount', 'currency_amounts'])
        ->and(json_encode($store['paths']['/staff/shipping/methods/{method}']['put']['responses'][409]))->toContain('shipping_method_archived')
        ->and(json_encode($store['paths']['/staff/shipping/methods']['post']['responses'][422]))->toContain('shipping_rates_overlap')
        ->and(json_encode($store['paths']['/staff/shipping/quotes']['post']['responses'][422]))->toContain('currency_not_offered')
        ->and(array_keys($store['components']['schemas']['ShippingQuoteResource']['properties']))->toBe(['required', 'reason', 'weight_grams', 'value', 'options'])
        ->and(array_keys($store['components']['schemas']['ShippingOptionResource']['properties']))->toContain('id', 'name', 'fulfilment', 'amount', 'calculated_at_checkout', 'is_free', 'pickup', 'estimate')
        ->and(array_keys($store['components']['schemas']['ShippingOptionResource']['properties']['estimate']['properties'] ?? $store['components']['schemas']['ShippingOptionResource']['properties']['estimate']['anyOf'][0]['properties'] ?? []))->toBe(['min_days', 'max_days', 'earliest', 'latest'])
        ->and(array_keys($store['components']['schemas']['ShippingMethodResource']['properties']))->toContain('min_days', 'max_days')
        ->and(array_keys($store['components']['schemas']['StoreSettingsResource']['properties']))->toContain('working_days');
});

it('documents every cart endpoint', function (): void {
    $store = documentedApi('default');

    $expected = [
        '/cart' => ['get', 'post', 'patch'],
        '/cart/price-change-acknowledgements' => ['post'],
        '/cart/shipping-options' => ['get'],
        '/cart/code' => ['put', 'delete'],
        '/cart/merges' => ['post'],
        '/cart/lines' => ['post', 'delete'],
        '/cart/lines/{line}' => ['patch', 'delete'],
    ];

    // A guest's cart token is its own security scheme, an API key in a header.
    expect($store['components']['securitySchemes']['cartToken'])->toMatchArray(['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Cart-Token'])
        ->and($store['components']['securitySchemes']['http'])->toMatchArray(['type' => 'http', 'scheme' => 'bearer']);

    foreach ($expected as $path => $methods) {
        expect($store['paths'])->toHaveKey($path)
            ->and(array_keys($store['paths'][$path]))->toContain(...$methods);

        foreach ($methods as $method) {
            // Each takes a customer's sign-in or a cart token; a new cart, a sign-in or nothing; a merge, both.
            $security = match ("{$method} {$path}") {
                'post /cart' => [['http' => []], new stdClass],
                'post /cart/merges' => [['http' => [], 'cartToken' => []]],
                default => [['http' => []], ['cartToken' => []]],
            };

            expect($store['paths'][$path][$method]['security'])->toEqual($security)
                ->and(collect($store['paths'][$path][$method]['parameters'] ?? [])->pluck('name')->all())->not->toContain('X-Cart-Token');
        }
    }

    expect($store['paths']['/staff/customers/{customer}/cart']['get'])->not->toHaveKey('security')
        ->and($store['security'])->toEqual([['http' => []]])
        ->and(array_keys($store['paths']['/cart']['post']['responses']))->toContain(200, 201)
        ->and(array_keys($store['components']['schemas']['CartResource']['properties']))->toContain('id', 'version', 'token', 'currency', 'chosen_currency_unavailable', 'can_check_out', 'checkout_blockers', 'lines', 'discounts', 'totals', 'tax')
        ->and($store['components']['schemas']['CartResource']['properties']['checkout_blockers']['description'])->toContain('shipping_method_required')->toContain('shipping_not_available')
        ->and(array_keys($store['components']['schemas']['CartResource']['properties']['lines']['items']['properties']))->toContain('status', 'backorder', 'unit_price', 'total', 'price_changed')
        ->and(array_keys($store['components']['schemas']['CartResource']['properties']['tax']['properties']))->toContain('is_estimate', 'country_code')
        ->and(json_encode($store['paths']['/cart']['patch']['responses'][422]))->toContain('currency_not_offered')->toContain('state_not_in_country')->toContain('shipping_method_not_offered')
        ->and(array_keys($store['components']['schemas']['CartResource']['properties']['shipping']['properties']))->toContain('required', 'is_estimate', 'method', 'chosen_method_unavailable', 'calculated_at_checkout', 'amount', 'discount')
        ->and(array_keys($store['components']['schemas']['CartResource']['properties']['totals']['properties']))->toContain('shipping')
        ->and($store['paths']['/cart/shipping-options']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['$ref'] ?? '')->toEndWith('ShippingQuoteResource')
        ->and(json_encode($store['paths']['/cart/code']['put']['responses'][422]))->toContain('code_not_valid')
        ->and(json_encode($store['paths']['/cart/code']['put']['responses'][429]))->toContain('too_many_code_attempts')
        ->and(array_keys($store['components']['schemas']['CartResource']['properties']))->toContain('code')
        // Storefront apps are told to merge a guest's cart right after sign-in.
        ->and($store['paths']['/cart/merges']['post']['description'])->toContain('right after a shopper signs in')
        ->and($store['paths']['/customer/auth/tokens']['post']['description'])->toContain('/cart/merges')
        ->and($store['paths']['/customer/auth/accounts']['post']['description'])->toContain('/cart/merges')
        ->and(array_keys($store['components']['schemas']['CartResource']['properties']))->toContain('skipped_lines')
        // Staff see a customer's cart, read only.
        ->and(array_keys($store['paths']['/staff/customers/{customer}/cart']))->toBe(['get'])
        ->and(json_encode($store['paths']['/staff/customers/{customer}/cart']['get']['responses'][404]))->toContain('cart_not_found')
        ->and(array_keys($store['paths']['/staff/customers/{customer}/cart']['get']['responses']))->toContain(403)
        ->and(json_encode($store['paths']['/cart']['get']['responses'][404]))->toContain('cart_not_found')
        ->and(json_encode($store['paths']['/cart/lines']['post']['responses'][422]))->toContain('variant_not_available')->toContain('variant_out_of_stock')->toContain('cart_full')->toContain('cart_line_quantity_too_high')
        ->and(collect($store['paths']['/cart/lines/{line}']['patch']['parameters'])->pluck('name')->all())->toContain('If-Match')
        ->and(json_encode($store['paths']['/cart/lines/{line}']['patch']['responses'][412]))->toContain('cart_changed')
        ->and(json_encode($store['paths']['/cart/lines/{line}']['delete']['responses'][428]))->toContain('cart_version_required');
});

/*
 * Step 14: every order endpoint is documented, with cursor paging, the
 * business errors, and the order as staff and customers see it.
 */
it('documents every order endpoint', function (): void {
    $store = documentedApi('default');

    $expected = [
        '/staff/orders' => ['get'],
        '/staff/orders/{order}' => ['get'],
        '/staff/orders/{order}/events' => ['get'],
        '/staff/orders/{order}/cancel' => ['post'],
        '/staff/orders/{order}/tags' => ['put'],
        '/staff/orders/{order}/contact' => ['put'],
        '/staff/orders/{order}/shipping-address' => ['put'],
        '/staff/orders/{order}/notes' => ['get', 'post'],
        '/staff/orders/{order}/notes/{note}' => ['delete'],
        '/staff/customers/{customer}/orders' => ['get'],
    ];

    foreach ($expected as $path => $methods) {
        expect($store['paths'])->toHaveKey($path)
            ->and(array_keys($store['paths'][$path]))->toContain(...$methods);
    }

    expect($store['paths']['/staff/orders']['get']['description'])->toContain('Paged by cursor')
        ->and(collect($store['paths']['/staff/orders']['get']['parameters'])->pluck('name')->all())->toContain('search', 'status', 'payment_status', 'fulfilment_status', 'placed_from', 'placed_to', 'customer', 'tag')
        ->and(json_encode($store['paths']['/staff/orders/{order}/cancel']['post']['responses'][409]))->toContain('order_cannot_be_cancelled')
        ->and(json_encode($store['paths']['/staff/orders/{order}/shipping-address']['put']['responses'][409]))->toContain('order_address_cannot_be_corrected')
        ->and(json_encode($store['paths']['/staff/orders/{order}/shipping-address']['put']['responses'][422]))->toContain('order_address_region_changed')
        ->and(array_keys($store['components']['schemas']['OrderResource']['properties']))->toContain('number', 'status', 'payment_status', 'fulfilment_status', 'refund_due', 'contact', 'totals', 'exchange_rate', 'lines', 'shipping', 'shipping_address', 'billing_address', 'tags', 'payment_due_at')
        ->and(array_keys($store['components']['schemas']['OrderResource']['properties']['contact']['properties']))->toBe(['email', 'name', 'phone', 'is_corrected']);
});

it('documents the required Idempotency-Key header on every endpoint that needs one', function (): void {
    $store = documentedApi('default');
    $idempotent = collect(Route::getRoutes()->getRoutes())->filter(static fn (Illuminate\Routing\Route $route): bool => in_array('idempotent', $route->gatherMiddleware(), true));

    expect($idempotent)->not->toBeEmpty();

    foreach ($idempotent as $route) {
        $path = Str::after($route->uri(), 'api/v1');
        $method = mb_strtolower(collect($route->methods())->reject(static fn (string $method): bool => $method === 'HEAD')->first());
        $header = collect($store['paths'][$path][$method]['parameters'] ?? [])->firstWhere('name', 'Idempotency-Key');

        expect($header)->not->toBeNull("{$method} {$path} documents Idempotency-Key")
            ->and($header['in'])->toBe('header')
            ->and($header['required'])->toBeTrue();
    }
});

it('documents every shipment endpoint', function (): void {
    $store = documentedApi('default');

    $expected = [
        '/staff/shipments' => ['get'],
        '/staff/shipments/{shipment}' => ['get', 'patch'],
        '/staff/shipments/{shipment}/ready' => ['post'],
        '/staff/shipments/{shipment}/ship' => ['post'],
        '/staff/shipments/{shipment}/deliver' => ['post'],
        '/staff/shipments/{shipment}/cancel' => ['post'],
        '/staff/orders/{order}/shipments' => ['get', 'post'],
    ];

    foreach ($expected as $path => $methods) {
        expect($store['paths'])->toHaveKey($path)
            ->and(array_keys($store['paths'][$path]))->toContain(...$methods);
    }

    expect($store['paths']['/staff/shipments']['get']['description'])->toContain('Paged by cursor')
        ->and(collect($store['paths']['/staff/orders/{order}/shipments']['post']['parameters'])->pluck('name')->all())->toContain('Idempotency-Key')
        ->and(json_encode($store['paths']['/staff/orders/{order}/shipments']['post']['responses'][409]))->toContain('order_cannot_be_fulfilled')
        ->and(json_encode($store['paths']['/staff/orders/{order}/shipments']['post']['responses'][422]))->toContain('shipment_quantity_too_high')
        ->and(json_encode($store['paths']['/staff/shipments/{shipment}/ship']['post']['responses'][422]))->toContain('tracking_number_required')
        ->and(json_encode($store['paths']['/staff/shipments/{shipment}/ship']['post']['responses'][409]))->toContain('shipment_transition_not_allowed')->toContain('insufficient_stock')
        ->and(array_keys($store['components']['schemas']['ShipmentResource']['properties']))->toContain('fulfilment', 'status', 'tracking_number', 'tracking_url', 'lines', 'shipped_at', 'delivered_at');
});

it('documents a customer\'s order history and staff answering guests\' data requests', function (): void {
    $store = documentedApi('default');

    expect(array_keys($store['paths']['/customer/orders']))->toBe(['get'])
        ->and(array_keys($store['paths']['/customer/orders/{order}']))->toBe(['get'])
        ->and(array_keys($store['paths']['/customer/orders/{order}/cancel']))->toBe(['post'])
        ->and($store['paths']['/customer/orders']['get']['description'])->toContain('Paged by cursor')
        ->and(json_encode($store['paths']['/customer/orders/{order}/cancel']['post']['responses'][409]))->toContain('order_already_paid')->toContain('order_cannot_be_cancelled')
        ->and(array_keys($store['components']['schemas']['CustomerOrderResource']['properties']))->toContain('number', 'status', 'lines', 'totals', 'shipments')->not->toContain('tags', 'refund_due')
        ->and(array_keys($store['paths']['/staff/guest-personal-data']))->toBe(['post'])
        ->and(array_keys($store['paths']['/staff/guest-personal-data/erasure']))->toBe(['post'])
        ->and(array_keys($store['components']['schemas']['StoreSettingsResource']['properties']))->toContain('order_retention_years', 'order_retention_default_years');
});

it('documents every payment gateway endpoint, never with credentials in a response', function (): void {
    $store = documentedApi('default');

    expect(array_keys($store['paths']['/staff/payment-gateways']))->toBe(['get'])
        ->and(array_keys($store['paths']['/staff/payment-gateways/{gateway}/connection']))->toContain('put', 'patch', 'delete')
        ->and(array_keys($store['paths']['/staff/payment-gateways/{gateway}/connection/checks']))->toBe(['post'])
        ->and(json_encode($store['paths']['/staff/payment-gateways/{gateway}/connection']['put']['responses'][422]))->toContain('payment_gateway_credentials_invalid')
        ->and(json_encode($store['paths']['/staff/payment-gateways/{gateway}/connection']['put']['responses'][503]))->toContain('payment_gateway_unreachable')
        ->and(json_encode($store['paths']['/staff/payment-gateways/{gateway}/connection']['delete']['responses'][409]))->toContain('payment_gateway_not_connected')
        ->and(array_keys($store['components']['schemas']['PaymentGatewayConnectionResource']['properties']))->not->toContain('credentials', 'endpoint_id', 'account_id');
});

it('documents every payment and refund endpoint, never with card numbers', function (): void {
    $store = documentedApi('default');

    $expected = [
        '/staff/payments' => ['get'],
        '/staff/payments/{payment}' => ['get'],
        '/staff/payments/{payment}/receipts' => ['post'],
        '/staff/payments/{payment}/collections' => ['post'],
        '/staff/payments/{payment}/checks' => ['post'],
        '/staff/orders/{order}/payments' => ['get'],
        '/staff/orders/{order}/refunds' => ['post'],
        '/staff/refunds' => ['get'],
        '/staff/refunds/{refund}' => ['get'],
    ];

    foreach ($expected as $path => $methods) {
        expect($store['paths'])->toHaveKey($path)
            ->and(array_keys($store['paths'][$path]))->toBe($methods);
    }

    expect($store['paths']['/staff/payments']['get']['description'])->toContain('Paged by cursor')
        ->and($store['paths']['/staff/refunds']['get']['description'])->toContain('Paged by cursor')
        ->and(collect($store['paths']['/staff/orders/{order}/refunds']['post']['parameters'])->pluck('name')->all())->toContain('Idempotency-Key')
        ->and(json_encode($store['paths']['/staff/orders/{order}/refunds']['post']['responses'][409]))->toContain('order_not_refundable')
        ->and(json_encode($store['paths']['/staff/orders/{order}/refunds']['post']['responses'][422]))->toContain('refund_exceeds_refundable')->toContain('refund_invalid')->toContain('partial_refund_not_supported')
        ->and(json_encode($store['paths']['/staff/payments/{payment}/receipts']['post']['responses'][409]))->toContain('payment_not_awaiting_receipt')
        ->and(array_keys($store['components']['schemas']['PaymentResource']['properties']))->toContain('status', 'amount', 'received', 'refunded', 'card_last4', 'events')->not->toContain('card_number', 'redirect_url')
        ->and(array_keys($store['components']['schemas']['RefundResource']['properties']))->toContain('status', 'amount', 'shipping', 'lines', 'parts');
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
