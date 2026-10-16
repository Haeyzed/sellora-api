<?php

declare(strict_types=1);

namespace App\Providers;

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Identity\Models\PlatformAdminInvitation;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Plans\Models\Plan;
use App\Landlord\Plans\Models\PlanFeature;
use App\Landlord\Plans\Models\PlanLimit;
use App\Landlord\Subscriptions\Models\FeatureGrant;
use App\Landlord\Subscriptions\Models\TenantLimitOverride;
use App\Landlord\Subscriptions\SubscriptionFeatureSource;
use App\Landlord\Tenancy\Models\DatabaseServer;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\PlatformStoreClosure;
use App\Landlord\Tenancy\PlatformStoreExports;
use App\Landlord\Tenancy\PlatformStoreOwnerContact;
use App\Landlord\Tenancy\PlatformStoreProfile;
use App\Landlord\Tenancy\StoreExportRetention;
use App\Landlord\Tenancy\StoreRegistrationRetention;
use App\Shared\Auth\Contracts\TwoFactorRequirement;
use App\Shared\Auth\ExpiredPasswordResetTokenRetention;
use App\Shared\Auth\Models\Role;
use App\Shared\Auth\TwoFactor\TwoFactorChallenges;
use App\Shared\Exceptions\ApiErrorResponseDocumentation;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Geography\AddressFormats;
use App\Shared\Http\PaginationDocumentation;
use App\Shared\Idempotency\GuestRequester;
use App\Shared\Idempotency\IdempotencyKeyRetention;
use App\Shared\Money\ExchangeRates\ExchangeRateRetention;
use App\Shared\Money\PricedRecordsRegistry;
use App\Shared\Privacy\PersonalDataErasure;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Privacy\SharedStoreTables;
use App\Shared\Privacy\StoreExportRegistry;
use App\Shared\Retention\Policies\ActivityLogRetention;
use App\Shared\Retention\Policies\AuditRetention;
use App\Shared\Retention\Policies\ExpiredAccessTokenRetention;
use App\Shared\Retention\RetentionRegistry;
use App\Shared\Tenancy\Contracts\StoreAccountNotifications;
use App\Shared\Tenancy\Contracts\StoreClosure;
use App\Shared\Tenancy\Contracts\StoreExports;
use App\Shared\Tenancy\Contracts\StoreOwnerAccounts;
use App\Shared\Tenancy\Contracts\StoreOwnerContact;
use App\Shared\Tenancy\Contracts\StorePermissions;
use App\Shared\Tenancy\Contracts\StoreProfile;
use App\Shared\Tenancy\Contracts\StorePromotionHolds;
use App\Shared\Tenancy\Contracts\StoreSessions;
use App\Shared\Tenancy\Contracts\StoreSettingsSetup;
use App\Shared\Tenancy\Contracts\StoreStockLedger;
use App\Shared\Tenancy\Contracts\StoreStockReservations;
use App\Shared\Tenancy\Contracts\StoreUnpaidOrders;
use App\Tenant\Cart\CartStoreTables;
use App\Tenant\Cart\CustomerCartRetention;
use App\Tenant\Cart\GuestCartRetention;
use App\Tenant\Cart\Http\CartGuestRequester;
use App\Tenant\Cart\Http\CartTokenDocumentation;
use App\Tenant\Cart\Privacy\CustomerCartData;
use App\Tenant\Catalog\CatalogPricedRecords;
use App\Tenant\Catalog\CatalogStoreFiles;
use App\Tenant\Catalog\CatalogStoreTables;
use App\Tenant\Catalog\Enums\CatalogPermission;
use App\Tenant\Catalog\Models\Attribute;
use App\Tenant\Catalog\Models\AttributeValue;
use App\Tenant\Catalog\Models\Brand;
use App\Tenant\Catalog\Models\Category;
use App\Tenant\Catalog\Models\Product;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Customers\CustomerDataExportRetention;
use App\Tenant\Customers\CustomerEmailTokenRetention;
use App\Tenant\Customers\CustomerStoreTables;
use App\Tenant\Customers\Enums\CustomerPermission;
use App\Tenant\Customers\Events\CustomerEmailVerified;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Models\CustomerGroup;
use App\Tenant\Customers\Privacy\CustomerAccountData;
use App\Tenant\Customers\Privacy\CustomerAddressData;
use App\Tenant\Customers\Privacy\CustomerDataExportData;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Customers\Privacy\CustomerGroupData;
use App\Tenant\Customers\Privacy\CustomerNoteData;
use App\Tenant\Customers\Privacy\MarketingConsentData;
use App\Tenant\Customers\TrashedCustomerRetention;
use App\Tenant\Delivery\DeliveryStoreTables;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Enums\StaffPermission;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\IdentityStoreTables;
use App\Tenant\Identity\Models\OwnershipTransfer;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffAccountNotifications;
use App\Tenant\Identity\StaffInvitationRetention;
use App\Tenant\Identity\StaffPermissionCatalogue;
use App\Tenant\Identity\StaffPermissionSync;
use App\Tenant\Identity\StaffStoreOwnerAccounts;
use App\Tenant\Identity\StoreSignOut;
use App\Tenant\Inventory\Contracts\StockLocationSelector;
use App\Tenant\Inventory\DefaultStockLocationSelector;
use App\Tenant\Inventory\Enums\InventoryPermission;
use App\Tenant\Inventory\ExpiredReservationSweep;
use App\Tenant\Inventory\InventoryPricedRecords;
use App\Tenant\Inventory\InventoryStoreTables;
use App\Tenant\Inventory\Models\InventoryItem;
use App\Tenant\Inventory\Models\StockItem;
use App\Tenant\Inventory\Models\StockLocation;
use App\Tenant\Inventory\Models\StockMovement;
use App\Tenant\Inventory\Models\StockReservation;
use App\Tenant\Inventory\SettledReservationRetention;
use App\Tenant\Inventory\StockLedgerReconciliation;
use App\Tenant\Orders\Enums\OrderPermission;
use App\Tenant\Orders\ExpiredOrderSweep;
use App\Tenant\Orders\Listeners\LinkGuestOrdersWhenEmailVerified;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderNote;
use App\Tenant\Orders\Models\Shipment;
use App\Tenant\Orders\OrderPricedRecords;
use App\Tenant\Orders\OrderRetention;
use App\Tenant\Orders\OrderStoreTables;
use App\Tenant\Orders\Privacy\CustomerOrderData;
use App\Tenant\Orders\Privacy\GuestDataSubject;
use App\Tenant\Orders\Privacy\GuestOrderData;
use App\Tenant\Orders\Services\PlacedOrderHistory;
use App\Tenant\Pricing\Actions\CalculateTax;
use App\Tenant\Pricing\Enums\PricingPermission;
use App\Tenant\Pricing\Models\FixedPrice;
use App\Tenant\Pricing\Models\StoreCurrency;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Pricing\Models\TaxRate;
use App\Tenant\Pricing\Models\VariantTaxClass;
use App\Tenant\Pricing\PricingPricedRecords;
use App\Tenant\Pricing\PricingStoreTables;
use App\Tenant\Pricing\TaxCalculatorRegistry;
use App\Tenant\Pricing\TaxExemptCustomerRule;
use App\Tenant\Promotions\Contracts\OrderHistory;
use App\Tenant\Promotions\DiscountSourceRegistry;
use App\Tenant\Promotions\Enums\PromotionPermission;
use App\Tenant\Promotions\ExpiredPromotionUseSweep;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCode;
use App\Tenant\Promotions\Models\PromotionRedemption;
use App\Tenant\Promotions\Privacy\PromotionUseData;
use App\Tenant\Promotions\PromotionDiscountSource;
use App\Tenant\Promotions\PromotionPricedRecords;
use App\Tenant\Promotions\PromotionStoreTables;
use App\Tenant\Promotions\ReleasedPromotionUseRetention;
use App\Tenant\Settings\Enums\SettingsPermission;
use App\Tenant\Settings\Models\StoreSettings;
use App\Tenant\Settings\Policies\StoreLifecyclePolicy;
use App\Tenant\Settings\SettingsStoreFiles;
use App\Tenant\Settings\SettingsStoreTables;
use App\Tenant\Settings\StoreLocales;
use App\Tenant\Settings\StoreSettingsDefaults;
use App\Tenant\Settings\StoreTwoFactorRequirement;
use App\Tenant\Shipping\Enums\ShippingPermission;
use App\Tenant\Shipping\Models\ShippingMethod;
use App\Tenant\Shipping\Models\ShippingZone;
use App\Tenant\Shipping\ShippingCarrierRegistry;
use App\Tenant\Shipping\ShippingPricedRecords;
use App\Tenant\Shipping\ShippingStoreTables;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\ServerVariable;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Laravel\Telescope\TelescopeServiceProvider as TelescopePackageServiceProvider;
use Sentry\State\Scope;
use Spatie\Translatable\Translatable;

use function Sentry\configureScope;

/**
 * Registers application-wide services that belong to no single domain.
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Registers services, including the local-only debugging tools.
     */
    public function register(): void
    {
        $this->registerTelescopeLocally();
        $this->registerSharedRegistries();
        $this->registerPlanGating();
        $this->registerBreachedPasswordCheck();
        $this->app->singleton(TwoFactorChallenges::class);
        $this->registerStaffPermissions();
        $this->app->bind(StoreOwnerAccounts::class, StaffStoreOwnerAccounts::class);
        $this->app->bind(StoreOwnerContact::class, PlatformStoreOwnerContact::class);
        $this->app->bind(StoreSessions::class, StoreSignOut::class);
        $this->app->bind(StoreClosure::class, PlatformStoreClosure::class);
        $this->app->bind(StoreExports::class, PlatformStoreExports::class);
        $this->app->bind(StoreAccountNotifications::class, StaffAccountNotifications::class);
        $this->app->bind(StoreProfile::class, PlatformStoreProfile::class);
        $this->app->bind(StoreSettingsSetup::class, StoreSettingsDefaults::class);
        $this->app->bind(StorePermissions::class, StaffPermissionSync::class);
        $this->app->bind(StoreStockLedger::class, StockLedgerReconciliation::class);
        $this->app->bind(StoreStockReservations::class, ExpiredReservationSweep::class);
        $this->app->bind(StorePromotionHolds::class, ExpiredPromotionUseSweep::class);
        $this->app->bind(StoreUnpaidOrders::class, ExpiredOrderSweep::class);
        $this->app->bind(StockLocationSelector::class, DefaultStockLocationSelector::class);
        // One list of country tax plug-ins, filled in by modules' and integrations' service providers.
        $this->app->singleton(TaxCalculatorRegistry::class);
        // One list of shipping carriers, filled in by integrations' service providers.
        $this->app->singleton(ShippingCarrierRegistry::class);
        // Lines a tax-exempt customer buys are never taxed; modules may tag rules of their own.
        $this->app->tag([TaxExemptCustomerRule::class], CalculateTax::EXEMPTION_RULES_TAG);
        $this->app->bind(TwoFactorRequirement::class, StoreTwoFactorRequirement::class);
        // Per request or job, so a long-running worker never reuses one store's languages for the next.
        $this->app->scoped(StoreLocales::class);
        // Per request or job: it remembers the formats it has read.
        $this->app->scoped(AddressFormats::class);
        $this->registerDiscountSources();
    }

    /**
     * Where discounts come from: the store's promotions, and any module that registers a source (loyalty points and the like) from its own service provider, used only while it is enabled.
     *
     * First-order promotions ask Orders for the buyer's history: their orders
     * that weren't cancelled, by account or by the email they were placed with.
     */
    private function registerDiscountSources(): void
    {
        $this->app->singleton(DiscountSourceRegistry::class, static function (Application $app): DiscountSourceRegistry {
            $registry = new DiscountSourceRegistry($app);
            $registry->register(PromotionDiscountSource::class);

            return $registry;
        });
        $this->app->bind(OrderHistory::class, PlacedOrderHistory::class);
        // A guest's Idempotency-Keys are theirs by their cart token.
        $this->app->bind(GuestRequester::class, CartGuestRequester::class);
    }

    /**
     * The permissions a store's roles can grant. Each domain adds its permission enum here as it is built; modules will add theirs from their service providers.
     */
    private function registerStaffPermissions(): void
    {
        $this->app->singleton(StaffPermissionCatalogue::class, static function (): StaffPermissionCatalogue {
            $catalogue = new StaffPermissionCatalogue;
            $catalogue->register(StaffPermission::class);
            $catalogue->register(SettingsPermission::class);
            $catalogue->register(CatalogPermission::class);
            $catalogue->register(InventoryPermission::class);
            $catalogue->register(PricingPermission::class);
            $catalogue->register(CustomerPermission::class);
            $catalogue->register(PromotionPermission::class);
            $catalogue->register(ShippingPermission::class);
            $catalogue->register(OrderPermission::class);

            return $catalogue;
        });
    }

    /**
     * Boots application-wide behaviour.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/landlord'));
        $this->useStableMorphNames();
        $this->definePasswordRules();
        $this->rejectTokensOfDeactivatedAccounts();
        $this->grantFullAccessRoles();
        $this->defineStoreLifecycleAbilities();
        $this->tagErrorReportsWithGuard();
        $this->listenAcrossDomains();
        $this->registerApiDocumentation();
        $this->fallBackToAnyTranslation();
    }

    /**
     * A text missing in both the requested and the store's default language shows any language it has, rather than nothing.
     *
     * The store's default language comes first (HasStoreTranslations); this
     * last resort is the same for every store, so it is safe to set once.
     */
    private function fallBackToAnyTranslation(): void
    {
        $this->app->make(Translatable::class)->fallback(fallbackAny: true);
    }

    /**
     * Documents the platform admin API and the store registration API separately from the store API, because they serve different audiences.
     *
     * The store API is Scramble's default API, configured in config/scramble.php.
     * Registration shares its /api/v1 prefix, so it is told apart by route
     * name. The geography reference data is served on both the central and
     * store domains, so it appears in both documents. All document errors in
     * the shape the API really returns.
     */
    private function registerApiDocumentation(): void
    {
        Scramble::registerExtension(ApiErrorResponseDocumentation::class);
        // Before registering the other APIs, which copy the default API's operation transformers.
        Scramble::configure()->withOperationTransformers(PaginationDocumentation::class);

        // "Try it" sends requests here: the central APIs to the first central domain, the store API to a store's subdomain chosen in the docs.
        $scheme = parse_url(config()->string('app.url'), PHP_URL_SCHEME) ?: 'https';
        $centralUrl = $scheme.'://'.(config()->array('platform.central_domains')[0] ?? 'localhost');

        Scramble::registerApi('registration', [
            'api_path' => 'api/v1',
            'servers' => ['Central' => $centralUrl.'/api/v1'],
            'info' => [
                'description' => 'Endpoints for merchants registering a new store, served on the central domain only.',
            ],
            'ui' => [
                'title' => config('app.name').' Store Registration API',
            ],
        ])->routes(static fn (Route $route): bool => str_starts_with((string) $route->getName(), 'registration.') || str_starts_with((string) $route->getName(), 'geography.'))
            ->expose(ui: 'docs/registration', document: 'docs/registration.json');

        Scramble::registerApi('platform', [
            'api_path' => 'api/v1/platform',
            'servers' => ['Central' => $centralUrl.'/api/v1/platform'],
            'info' => [
                'description' => 'Endpoints for platform administrators, served on the central domain only.',
            ],
            'ui' => [
                'title' => config('app.name').' Platform API',
            ],
        ])->expose(ui: 'docs/platform', document: 'docs/platform.json');

        // Last: registering an API copies the store API's settings, and this filter is the store API's alone.
        $storeApi = Scramble::configure()
            ->useConfig([...config()->array('scramble'), 'servers' => ['Store' => $scheme.'://{store}.'.config()->string('platform.domain').'/api/v1']])
            ->withServerVariables(['store' => ServerVariable::make('your-store', description: "The store's subdomain, such as \"ada-fabrics\" for ada-fabrics.".config()->string('platform.domain').'.')])
            // Guests reach their cart with its token, not a sign-in.
            ->withDocumentTransformers(static fn (OpenApi $openApi): Components => $openApi->components->addSecurityScheme(CartTokenDocumentation::SCHEME, CartTokenDocumentation::scheme()))
            ->withOperationTransformers(CartTokenDocumentation::class);
        $storeApi->routes(static fn (Route $route): bool => $storeApi->apiPath()->matches($route->uri)
            && ! str_starts_with((string) $route->getName(), 'registration.'));
    }

    /**
     * One privacy registry, one retention registry and one store export registry for the whole application, which every domain and module adds to.
     *
     * The retention policies for platform-wide records (tokens, idempotency
     * keys, activity and audit logs) are registered here; each domain
     * registers its own as it is built. Every store table is classified for
     * exports by the domain that owns it.
     */
    private function registerSharedRegistries(): void
    {
        $this->app->singleton(PersonalDataRegistry::class, static function (Application $app): PersonalDataRegistry {
            $registry = new PersonalDataRegistry($app, $app->make(PersonalDataErasure::class));

            // The account last: the others still need the customer's email and phone to find what is theirs.
            $registry->register(CustomerDataSubject::TYPE, 'addresses', CustomerAddressData::class);
            $registry->register(CustomerDataSubject::TYPE, 'staff_notes', CustomerNoteData::class);
            $registry->register(CustomerDataSubject::TYPE, 'customer_groups', CustomerGroupData::class);
            $registry->register(CustomerDataSubject::TYPE, 'marketing_consents', MarketingConsentData::class);
            $registry->register(CustomerDataSubject::TYPE, 'data_exports', CustomerDataExportData::class);
            $registry->register(CustomerDataSubject::TYPE, 'promotion_uses', PromotionUseData::class);
            $registry->register(CustomerDataSubject::TYPE, 'cart', CustomerCartData::class);
            $registry->register(CustomerDataSubject::TYPE, 'orders', CustomerOrderData::class);
            $registry->register(CustomerDataSubject::TYPE, 'account', CustomerAccountData::class);
            // A guest has only their orders, found by the email they were placed with.
            $registry->register(GuestDataSubject::TYPE, 'orders', GuestOrderData::class);

            return $registry;
        });

        // Domains register their priced records here as they start storing prices (Catalog, Pricing, Orders).
        $this->app->singleton(PricedRecordsRegistry::class, static function (Application $app): PricedRecordsRegistry {
            $registry = new PricedRecordsRegistry($app);
            $registry->register(CatalogPricedRecords::class);
            $registry->register(InventoryPricedRecords::class);
            $registry->register(PricingPricedRecords::class);
            $registry->register(PromotionPricedRecords::class);
            $registry->register(ShippingPricedRecords::class);
            $registry->register(OrderPricedRecords::class);

            return $registry;
        });

        $this->app->singleton(RetentionRegistry::class, static function (Application $app): RetentionRegistry {
            $registry = new RetentionRegistry($app);
            $registry->register(IdempotencyKeyRetention::class);
            $registry->register(ExpiredAccessTokenRetention::class);
            $registry->register(ExpiredPasswordResetTokenRetention::class);
            $registry->register(ActivityLogRetention::class);
            $registry->register(AuditRetention::class);
            $registry->register(StaffInvitationRetention::class);
            $registry->register(CustomerEmailTokenRetention::class);
            $registry->register(TrashedCustomerRetention::class);
            $registry->register(CustomerDataExportRetention::class);
            $registry->register(SettledReservationRetention::class);
            $registry->register(ReleasedPromotionUseRetention::class);
            $registry->register(GuestCartRetention::class);
            $registry->register(CustomerCartRetention::class);
            $registry->register(StoreRegistrationRetention::class);
            $registry->register(StoreExportRetention::class);
            $registry->register(ExchangeRateRetention::class);
            $registry->register(OrderRetention::class);

            return $registry;
        });

        $this->app->singleton(StoreExportRegistry::class, static function (Application $app): StoreExportRegistry {
            $registry = new StoreExportRegistry;

            foreach ([SharedStoreTables::class, IdentityStoreTables::class, SettingsStoreTables::class, CustomerStoreTables::class, DeliveryStoreTables::class, CatalogStoreTables::class, InventoryStoreTables::class, PricingStoreTables::class, PromotionStoreTables::class, CartStoreTables::class, ShippingStoreTables::class, OrderStoreTables::class] as $storeTables) {
                $app->make($storeTables)->classify($registry);
            }

            $registry->addFileSource(SettingsStoreFiles::class);
            $registry->addFileSource(CatalogStoreFiles::class);

            return $registry;
        });
    }

    /**
     * Polymorphic columns (token owners, role holders, activity subjects) store these short names, never PHP class names.
     *
     * Class names would break stored rows whenever a class moves. Every model
     * used in a polymorphic relation must be listed here, or saving it fails.
     */
    private function useStableMorphNames(): void
    {
        Relation::enforceMorphMap([
            'platform_admin' => PlatformAdmin::class,
            'staff_member' => StaffMember::class,
            'customer' => Customer::class,
            'customer_group' => CustomerGroup::class,
            'driver' => Driver::class,
            'staff_invitation' => StaffInvitation::class,
            'role' => Role::class,
            'store' => Tenant::class,
            'legal_document' => LegalDocument::class,
            'database_server' => DatabaseServer::class,
            'platform_admin_invitation' => PlatformAdminInvitation::class,
            'feature_grant' => FeatureGrant::class,
            'limit_override' => TenantLimitOverride::class,
            'store_settings' => StoreSettings::class,
            'plan' => Plan::class,
            'plan_feature' => PlanFeature::class,
            'plan_limit' => PlanLimit::class,
            'ownership_transfer' => OwnershipTransfer::class,
            'brand' => Brand::class,
            'category' => Category::class,
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'attribute' => Attribute::class,
            'attribute_value' => AttributeValue::class,
            'stock_location' => StockLocation::class,
            'inventory_item' => InventoryItem::class,
            'stock_item' => StockItem::class,
            'stock_movement' => StockMovement::class,
            'stock_reservation' => StockReservation::class,
            'tax_class' => TaxClass::class,
            'tax_rate' => TaxRate::class,
            'variant_tax_class' => VariantTaxClass::class,
            'store_currency' => StoreCurrency::class,
            'fixed_price' => FixedPrice::class,
            'promotion' => Promotion::class,
            'promotion_code' => PromotionCode::class,
            'promotion_redemption' => PromotionRedemption::class,
            'shipping_zone' => ShippingZone::class,
            'shipping_method' => ShippingMethod::class,
            'order' => Order::class,
            'order_note' => OrderNote::class,
            'shipment' => Shipment::class,
        ]);
    }

    /**
     * Every new password must be at least 12 characters and must not appear in a known data breach. Length protects better than forced symbols.
     *
     * The breach check sends only a 5-character prefix of the password's
     * SHA-1 hash. If the service doesn't answer in time the password is
     * accepted, so an outage never blocks sign-up.
     */
    private function definePasswordRules(): void
    {
        Password::defaults(static fn (): Password => Password::min(12)->max(128)->uncompromised());
    }

    /**
     * Gives the breached-password check a short timeout instead of Laravel's 30 seconds.
     */
    private function registerBreachedPasswordCheck(): void
    {
        $this->app->singleton(UncompromisedVerifier::class, static fn (Application $app): UncompromisedVerifier => new NotPwnedVerifier(
            $app->make(HttpFactory::class),
            $app->make('config')->integer('api.breached_password_check.timeout_in_seconds'),
        ));
    }

    /**
     * A deactivated account's existing tokens stop working on its very next request, not when they expire.
     *
     * Every account type has an is_active flag; anything without one is refused.
     */
    private function rejectTokensOfDeactivatedAccounts(): void
    {
        Sanctum::authenticateAccessTokensUsing(static function (PersonalAccessToken $accessToken, bool $isValid): bool {
            $account = $accessToken->tokenable;

            return $isValid && $account instanceof Model && $account->getAttribute('is_active') === true;
        });
    }

    /**
     * Platform super admins and store owners may do everything on their own side, without needing each permission.
     *
     * Returning null (not false) lets everyone else fall through to their
     * policies and permissions. Business rules live in Actions, not policies,
     * so this never lets anyone break a rule such as refunding twice.
     */
    private function grantFullAccessRoles(): void
    {
        Gate::before(static function (Authenticatable $account): ?bool {
            $hasFullAccess = match (true) {
                $account instanceof PlatformAdmin => $account->hasRole(PlatformRole::SuperAdmin->value),
                $account instanceof StaffMember => $account->hasRole(StaffRole::Owner->value),
                default => false,
            };

            return $hasFullAccess ? true : null;
        });
    }

    /**
     * Abilities over the store as a whole, which belong to no model: only its owner may close it or export all of its data.
     */
    private function defineStoreLifecycleAbilities(): void
    {
        Gate::define('closeStore', [StoreLifecyclePolicy::class, 'close']);
        Gate::define('exportStore', [StoreLifecyclePolicy::class, 'export']);
    }

    /**
     * Plan gating: one list of installed modules and integrations, and the platform's subscriptions as the source of each store's plan.
     *
     * Shared never imports Landlord, so the subscriptions are connected to
     * Features here, through the FeatureSource contract.
     */
    private function registerPlanGating(): void
    {
        $this->app->singleton(FeatureRegistry::class);
        $this->app->bind(FeatureSource::class, SubscriptionFeatureSource::class);
    }

    /**
     * Telescope records requests, queries and jobs, so it only ever runs on a developer's machine.
     */
    private function registerTelescopeLocally(): void
    {
        if (! $this->app->environment('local') || ! class_exists(TelescopePackageServiceProvider::class)) {
            return;
        }

        $this->app->register(TelescopePackageServiceProvider::class);
        $this->app->register(TelescopeServiceProvider::class);
    }

    /**
     * Listeners that live in a domain rather than app/Listeners, so they are not found on their own.
     */
    private function listenAcrossDomains(): void
    {
        Event::listen(CustomerEmailVerified::class, LinkGuestOrdersWhenEmailVerified::class);
    }

    /**
     * Labels error reports with the kind of user signed in (platform, staff, customer or driver).
     */
    private function tagErrorReportsWithGuard(): void
    {
        Event::listen(static function (Authenticated $event): void {
            configureScope(static function (Scope $scope) use ($event): void {
                $scope->setTag('guard', $event->guard);
            });
        });
    }
}
