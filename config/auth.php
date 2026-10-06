<?php

declare(strict_types=1);

use App\Landlord\Identity\Models\PlatformAdmin;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Delivery\Models\Driver;
use App\Tenant\Identity\Models\StaffMember;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | Every route names its guard explicitly. The default only applies when
    | code forgets to, and a platform guard never authenticates store users.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'platform'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'platform_admins'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Four Sanctum guards, one per kind of identity. Sanctum rejects a token
    | whose owner is not the guard's provider model, so one guard's tokens
    | never authorize another guard's routes. Platform tokens live in the
    | central database; staff, customer and driver tokens live in each
    | store's own database.
    |
    */

    'guards' => [
        'platform' => [
            'driver' => 'sanctum',
            'provider' => 'platform_admins',
        ],

        'staff' => [
            'driver' => 'sanctum',
            'provider' => 'staff_members',
        ],

        'customer' => [
            'driver' => 'sanctum',
            'provider' => 'customers',
        ],

        'driver' => [
            'driver' => 'sanctum',
            'provider' => 'drivers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Each identity has its own model in its own domain (see CLAUDE.md
    | section 10). The models are created with each identity feature.
    |
    */

    'providers' => [
        'platform_admins' => [
            'driver' => 'eloquent',
            'model' => PlatformAdmin::class,
        ],

        'staff_members' => [
            'driver' => 'eloquent',
            'model' => StaffMember::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model' => Customer::class,
        ],

        'drivers' => [
            'driver' => 'eloquent',
            'model' => Driver::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | One broker per identity that signs in with an email, each with its own
    | token table: the platform table in the central database, the others in
    | each store's database. Drivers have no broker: staff reset their PINs.
    |
    | "reset_url" is the link emailed to the person, into the frontend app
    | they use. {token} and {email} are filled in; {domain} becomes the store
    | domain the request came from, so each store's links point to itself.
    |
    | A broker with a "connection" lives in the central database; one without
    | uses the current store's database. The daily retention purge relies on
    | this to clean each table in the right place.
    |
    */

    'passwords' => [
        'platform_admins' => [
            'provider' => 'platform_admins',
            'table' => 'platform_admin_password_reset_tokens',
            'connection' => env('DB_CONNECTION', 'central'),
            'expire' => 60,
            'throttle' => 60,
            'reset_url' => env('PLATFORM_ADMIN_PASSWORD_RESET_URL', 'http://localhost:3000/reset-password?token={token}&email={email}'),
        ],

        'staff_members' => [
            'provider' => 'staff_members',
            'table' => 'staff_member_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
            'reset_url' => env('STAFF_PASSWORD_RESET_URL', 'https://{domain}/admin/reset-password?token={token}&email={email}'),
        ],

        'customers' => [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
            'reset_url' => env('CUSTOMER_PASSWORD_RESET_URL', 'https://{domain}/reset-password?token={token}&email={email}'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Number of seconds before a password confirmation expires.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
