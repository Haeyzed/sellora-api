<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Shared\Http\Middleware\UseDashboardLanguage;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use App\Tenant\Storefront\Http\Controllers\PageController;
use App\Tenant\Storefront\Http\Controllers\PageImageController;
use App\Tenant\Storefront\Http\Controllers\PageStateController;
use App\Tenant\Storefront\Http\Controllers\ShareImageController;
use App\Tenant\Storefront\Http\Controllers\StorefrontPageController;
use App\Tenant\Storefront\Http\Controllers\StorefrontSeoController;
use App\Tenant\Storefront\Http\Controllers\StorefrontSettingsController;
use Illuminate\Support\Facades\Route;

/*
| The storefront's content (Tenant\Storefront). Staff manage it under
| /api/v1/staff/storefront with the storefront.view and storefront.manage
| permissions. Shoppers read what is published under /api/v1/storefront:
| open to anyone, rate-limited per visitor, cached per store, in the store
| language the client asks for (Accept-Language).
*/

Route::prefix('storefront')
    ->name('storefront.')
    ->middleware(['throttle:public', UseStoreLanguage::class])
    ->group(static function (): void {
        Route::get('seo', [StorefrontSeoController::class, 'show'])->name('seo.show');
        Route::get('home', [StorefrontPageController::class, 'home'])->name('home');
        Route::get('pages/{slug}', [StorefrontPageController::class, 'show'])->name('pages.show');
    });

Route::prefix('staff/storefront')
    ->name('staff.storefront.')
    ->middleware([UseDashboardLanguage::class, 'auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api', UseStoreLanguage::class])
    ->group(static function (): void {
        Route::get('settings', [StorefrontSettingsController::class, 'show'])->name('settings.show');
        Route::patch('settings', [StorefrontSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/share-image', [ShareImageController::class, 'store'])->name('settings.share-image.store');
        Route::delete('settings/share-image', [ShareImageController::class, 'destroy'])->name('settings.share-image.destroy');

        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}', [PageController::class, 'show'])->name('pages.show')->withTrashed();
        Route::patch('pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{page}/restore', [PageStateController::class, 'restore'])->name('pages.restore')->withTrashed();
        Route::post('pages/{page}/publish', [PageStateController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [PageStateController::class, 'unpublish'])->name('pages.unpublish');
        Route::post('pages/{page}/homepage', [PageStateController::class, 'homepage'])->name('pages.homepage');
        Route::post('pages/{page}/images', [PageImageController::class, 'store'])->name('pages.images.store');
        Route::delete('pages/{page}/images/{image}', [PageImageController::class, 'destroy'])->name('pages.images.destroy');
    });
