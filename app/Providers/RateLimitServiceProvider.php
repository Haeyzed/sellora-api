<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Tenancy\TenantScopedKey;
use App\Tenant\Cart\CartToken;
use App\Tenant\Customers\Models\Customer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Defines the named rate limits that protect the platform from abuse, such as password guessing or checkout flooding.
 *
 * Every limit is counted per store (see TenantScopedKey), so one store's
 * traffic never uses up another store's limit. Limits are per minute and
 * configured in config/api.php. Routes opt in with `throttle:<name>`.
 */
final class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Registers every named rate limit.
     */
    public function boot(): void
    {
        $this->defineRequestLimits();
        $this->defineSignInLimits();
        $this->defineStoreWideLimits();
    }

    private function defineRequestLimits(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => $this->perMinute('api')->by($this->requesterKey('api', $request)));
        RateLimiter::for('public', fn (Request $request): Limit => $this->perMinute('public')->by($this->ipKey('public', $request)));
        RateLimiter::for('registration', fn (Request $request): Limit => $this->perMinute('registration')->by($this->ipKey('registration', $request)));
        // Emails a signed-in account asks to be sent (verification and email-change links), so a token can't be used to flood a mailbox.
        RateLimiter::for('account-emails', fn (Request $request): Limit => $this->perMinute('account_emails')->by($this->requesterKey('account-emails', $request)));
        $this->defineShopperLimits();
    }

    /**
     * Shoppers' carts are limited per cart (or per signed-in customer) under a much higher ceiling per IP address, never per IP address alone:
     * many shoppers can share one address, and one of them must not lock out the rest (section 14).
     *
     * A new guest cart has nothing to be told apart by yet but its address, so making carts has only the ceiling.
     */
    private function defineShopperLimits(): void
    {
        RateLimiter::for('cart', fn (Request $request): array => [
            $this->perMinute('cart')->by($this->shopperKey('cart', $request)),
            $this->perMinute('cart_ip_ceiling')->by($this->ipKey('cart-ip-ceiling', $request)),
        ]);
        RateLimiter::for('checkout', fn (Request $request): array => [
            $this->perMinute('checkout')->by($this->shopperKey('checkout', $request)),
            $this->perMinute('checkout_ip_ceiling')->by($this->ipKey('checkout-ip-ceiling', $request)),
        ]);
        RateLimiter::for('payment-return', fn (Request $request): array => [
            $this->perMinute('payment_return')->by(TenantScopedKey::forIdentifier('payment-return', (string) $request->route('reference'))),
            $this->perMinute('payment_return_ip_ceiling')->by($this->ipKey('payment-return-ip-ceiling', $request)),
        ]);
        RateLimiter::for('cart-creation', fn (Request $request): Limit => $this->perMinute('cart_creation_ip_ceiling')->by($this->ipKey('cart-creation', $request)));
        // The ceiling for promotion codes entered from one address; each cart or customer's own limit is counted where the code is checked (FindPromotionCode).
        RateLimiter::for('coupon-redemption', fn (Request $request): Limit => $this->perMinute('coupon_redemption_ip_ceiling')->by($this->ipKey('coupon-redemption-ip-ceiling', $request)));
    }

    /**
     * The signed-in customer, or else the guest's cart by its token (hashed: it is a secret), or else the IP address.
     */
    private function shopperKey(string $limitName, Request $request): string
    {
        $customerId = $request->bearerToken() === null ? null : Auth::guard(Customer::GUARD)->id();

        if ($customerId !== null) {
            return TenantScopedKey::make($limitName, Customer::GUARD, (string) $customerId);
        }

        $token = (string) $request->headers->get(CartToken::HEADER, '');

        return $token === '' ? $this->ipKey($limitName, $request) : TenantScopedKey::forIdentifier($limitName, $token);
    }

    /**
     * Sign-in limits count per IP address and per account identifier together,
     * so an attacker can't spread guesses across accounts from one address, and
     * formatting an email or phone differently doesn't reset the count.
     */
    private function defineSignInLimits(): void
    {
        RateLimiter::for('login', fn (Request $request): Limit => $this->perMinute('login')->by($this->identifierKey('login', $request)));
        RateLimiter::for('password-reset', fn (Request $request): Limit => $this->perMinute('password_reset')->by($this->identifierKey('password-reset', $request)));
        RateLimiter::for('one-time-password', fn (Request $request): Limit => $this->perMinute('one_time_password')->by($this->identifierKey('one-time-password', $request)));
        // Starting a social sign-in has nothing to tell shoppers apart by yet, so only a ceiling per address, high enough for many shoppers behind one.
        RateLimiter::for('social-sign-in', fn (Request $request): Limit => $this->perMinute('social_sign_in_ip_ceiling')->by($this->ipKey('social-sign-in', $request)));
        // Links emailed to an existing account to confirm connecting a provider: per ticket, under a ceiling per address, so a mailbox can't be flooded.
        RateLimiter::for('social-link-emails', fn (Request $request): array => [
            $this->perMinute('account_emails')->by(TenantScopedKey::forIdentifier('social-link-emails', $request->string('ticket')->value())),
            $this->perMinute('social_sign_in_ip_ceiling')->by($this->ipKey('social-link-emails', $request)),
        ]);
        // The central relay page: each state works once anyway; this only stops floods from one address.
        RateLimiter::for('social-sign-in-relay', fn (Request $request): Limit => $this->perMinute('social_sign_in_relay_ip_ceiling')->by($this->ipKey('social-sign-in-relay', $request)));
    }

    /**
     * Store-wide limits stop one busy store from filling shared queues or webhook capacity.
     * Queued bulk jobs use them through Laravel's `RateLimited` job middleware.
     */
    private function defineStoreWideLimits(): void
    {
        RateLimiter::for('tenant-webhooks', fn (): Limit => $this->perMinute('tenant_webhooks')->by(TenantScopedKey::make('webhooks')));
        // Counted only for webhooks that found no store, on the central domain: there is no store to count them against.
        RateLimiter::for('unknown-webhook-endpoints', fn (Request $request): Limit => $this->perMinute('unknown_webhook_endpoints')->by($this->ipKey('unknown-webhook-endpoints', $request)));
        RateLimiter::for('tenant-bulk-jobs', fn (): Limit => $this->perMinute('tenant_bulk_jobs')->by(TenantScopedKey::make('bulk-jobs')));
        // Rendering PDFs is heavy: one store issuing many documents at once never holds up another's on the default queue.
        RateLimiter::for('tax-document-pdfs', fn (): Limit => $this->perMinute('tax_document_pdfs')->by(TenantScopedKey::make('tax-document-pdfs')));
    }

    private function perMinute(string $limitName): Limit
    {
        return Limit::perMinute(config()->integer('api.rate_limits.'.$limitName));
    }

    private function requesterKey(string $limitName, Request $request): string
    {
        $userId = Auth::id();

        if ($userId === null) {
            return $this->ipKey($limitName, $request);
        }

        return TenantScopedKey::make($limitName, Auth::getDefaultDriver(), (string) $userId);
    }

    private function ipKey(string $limitName, Request $request): string
    {
        return TenantScopedKey::make($limitName, 'ip', (string) $request->ip());
    }

    private function identifierKey(string $limitName, Request $request): string
    {
        return TenantScopedKey::forIdentifier($limitName, (string) $request->ip().'|'.$this->normalisedIdentifier($request));
    }

    /**
     * The email (lower-cased) or phone (digits only) the request is about, so "Ada@Mail.com" and "ada@mail.com" count as one.
     */
    private function normalisedIdentifier(Request $request): string
    {
        $email = $request->string('email')->trim()->lower()->value();

        if ($email !== '') {
            return $email;
        }

        return (string) preg_replace('/\D+/', '', $request->string('phone')->value());
    }
}
