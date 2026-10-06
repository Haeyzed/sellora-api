<?php

declare(strict_types=1);

namespace App\Providers;

use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Landlord\Legal\Models\LegalDocument;
use App\Landlord\Subscriptions\SubscriptionFeatureSource;
use App\Landlord\Tenancy\Models\DatabaseServer;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\StoreRegistrationRetention;
use App\Shared\Auth\ExpiredPasswordResetTokenRetention;
use App\Shared\Auth\Models\Role;
use App\Shared\Auth\TwoFactor\TwoFactorChallenges;
use App\Shared\Exceptions\ApiErrorResponseDocumentation;
use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Idempotency\IdempotencyKeyRetention;
use App\Shared\Privacy\PersonalDataRegistry;
use App\Shared\Retention\Policies\ActivityLogRetention;
use App\Shared\Retention\Policies\AuditRetention;
use App\Shared\Retention\Policies\ExpiredAccessTokenRetention;
use App\Shared\Retention\RetentionRegistry;
use App\Shared\Tenancy\Contracts\StoreOwnerAccounts;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Enums\StaffPermission;
use App\Tenant\Identity\Enums\StaffRole;
use App\Tenant\Identity\Models\StaffInvitation;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Identity\StaffInvitationRetention;
use App\Tenant\Identity\StaffPermissionCatalogue;
use App\Tenant\Identity\StaffStoreOwnerAccounts;
use Dedoc\Scramble\Scramble;
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
    }

    /**
     * The permissions a store's roles can grant. Each domain adds its permission enum here as it is built; modules will add theirs from their service providers.
     */
    private function registerStaffPermissions(): void
    {
        $this->app->singleton(StaffPermissionCatalogue::class, static function (): StaffPermissionCatalogue {
            $catalogue = new StaffPermissionCatalogue;
            $catalogue->register(StaffPermission::class);

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
        $this->tagErrorReportsWithGuard();
        $this->registerApiDocumentation();
    }

    /**
     * Documents the platform admin API and the store registration API separately from the store API, because they serve different audiences.
     *
     * The store API is Scramble's default API, configured in config/scramble.php.
     * Registration shares its /api/v1 prefix, so it is told apart by route
     * name. All document errors in the shape the API really returns.
     */
    private function registerApiDocumentation(): void
    {
        Scramble::registerExtension(ApiErrorResponseDocumentation::class);

        Scramble::registerApi('registration', [
            'api_path' => 'api/v1',
            'info' => [
                'description' => 'Endpoints for merchants registering a new store, served on the central domain only.',
            ],
            'ui' => [
                'title' => config('app.name').' Store Registration API',
            ],
        ])->routes(static fn (Route $route): bool => str_starts_with((string) $route->getName(), 'registration.'))
            ->expose(ui: 'docs/registration', document: 'docs/registration.json');

        Scramble::registerApi('platform', [
            'api_path' => 'api/v1/platform',
            'info' => [
                'description' => 'Endpoints for platform administrators, served on the central domain only.',
            ],
            'ui' => [
                'title' => config('app.name').' Platform API',
            ],
        ])->expose(ui: 'docs/platform', document: 'docs/platform.json');

        // Last: registering an API copies the store API's settings, and this filter is the store API's alone.
        $storeApi = Scramble::configure();
        $storeApi->routes(static fn (Route $route): bool => $storeApi->apiPath()->matches($route->uri)
            && ! str_starts_with((string) $route->getName(), 'registration.'));
    }

    /**
     * One privacy registry and one retention registry for the whole application, which every domain and module adds to.
     *
     * The retention policies for platform-wide records (tokens, idempotency
     * keys, activity and audit logs) are registered here; each domain
     * registers its own as it is built.
     */
    private function registerSharedRegistries(): void
    {
        $this->app->singleton(PersonalDataRegistry::class);

        $this->app->singleton(RetentionRegistry::class, static function (Application $app): RetentionRegistry {
            $registry = new RetentionRegistry($app);
            $registry->register(IdempotencyKeyRetention::class);
            $registry->register(ExpiredAccessTokenRetention::class);
            $registry->register(ExpiredPasswordResetTokenRetention::class);
            $registry->register(ActivityLogRetention::class);
            $registry->register(AuditRetention::class);
            $registry->register(StaffInvitationRetention::class);
            $registry->register(StoreRegistrationRetention::class);

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
            'driver' => Driver::class,
            'staff_invitation' => StaffInvitation::class,
            'role' => Role::class,
            'store' => Tenant::class,
            'legal_document' => LegalDocument::class,
            'database_server' => DatabaseServer::class,
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
