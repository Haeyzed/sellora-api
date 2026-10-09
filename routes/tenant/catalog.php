<?php

declare(strict_types=1);

use App\Shared\Auth\Http\Middleware\EnsureTwoFactorWhenRequired;
use App\Tenant\Catalog\Http\Controllers\ArchiveProductController;
use App\Tenant\Catalog\Http\Controllers\AttributeController;
use App\Tenant\Catalog\Http\Controllers\AttributeValueController;
use App\Tenant\Catalog\Http\Controllers\BrandController;
use App\Tenant\Catalog\Http\Controllers\BrandLogoController;
use App\Tenant\Catalog\Http\Controllers\CategoryController;
use App\Tenant\Catalog\Http\Controllers\CategoryImageController;
use App\Tenant\Catalog\Http\Controllers\ProductController;
use App\Tenant\Catalog\Http\Controllers\ProductImageController;
use App\Tenant\Catalog\Http\Controllers\ProductOptionsController;
use App\Tenant\Catalog\Http\Controllers\ProductVariantController;
use App\Tenant\Catalog\Http\Controllers\PublishProductController;
use App\Tenant\Catalog\Http\Controllers\ReorderCategoriesController;
use App\Tenant\Catalog\Http\Controllers\ReorderProductImagesController;
use App\Tenant\Catalog\Http\Controllers\RestoreBrandController;
use App\Tenant\Catalog\Http\Controllers\RestoreCategoryController;
use App\Tenant\Catalog\Http\Controllers\RestoreProductController;
use App\Tenant\Catalog\Http\Controllers\RestoreProductVariantController;
use App\Tenant\Catalog\Http\Controllers\StorefrontBrandController;
use App\Tenant\Catalog\Http\Controllers\StorefrontCategoryController;
use App\Tenant\Catalog\Http\Controllers\StorefrontProductController;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Settings\Http\Middleware\UseStoreLanguage;
use Illuminate\Support\Facades\Route;

/*
| The store's catalog (Tenant\Catalog). Staff manage it under
| /api/v1/staff/catalog with the catalog.view and catalog.manage permissions.
| Customers read it under /api/v1/catalog: open to anyone, rate-limited per
| visitor, published and priced products only. Answers follow the store
| language the client asks for (Accept-Language).
*/

Route::prefix('catalog')
    ->name('catalog.')
    ->middleware(['throttle:public', UseStoreLanguage::class])
    ->group(static function (): void {
        Route::get('products', [StorefrontProductController::class, 'index'])->name('products.index');
        Route::get('products/{slug}', [StorefrontProductController::class, 'show'])->name('products.show');
        Route::get('categories', [StorefrontCategoryController::class, 'index'])->name('categories.index');
        Route::get('brands', [StorefrontBrandController::class, 'index'])->name('brands.index');
    });

Route::prefix('staff/catalog')
    ->name('staff.catalog.')
    ->middleware(['auth:'.StaffMember::GUARD, EnsureTwoFactorWhenRequired::class, 'throttle:api', UseStoreLanguage::class])
    ->group(static function (): void {
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::get('brands/{brand}', [BrandController::class, 'show'])->name('brands.show')->withTrashed();
        Route::patch('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');
        Route::post('brands/{brand}/restore', RestoreBrandController::class)->name('brands.restore')->withTrashed();
        Route::post('brands/{brand}/logo', [BrandLogoController::class, 'store'])->name('brands.logo.store');
        Route::delete('brands/{brand}/logo', [BrandLogoController::class, 'destroy'])->name('brands.logo.destroy');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/order', ReorderCategoriesController::class)->name('categories.order');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show')->withTrashed();
        Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        Route::post('categories/{category}/restore', RestoreCategoryController::class)->name('categories.restore')->withTrashed();
        Route::post('categories/{category}/image', [CategoryImageController::class, 'store'])->name('categories.image.store');
        Route::delete('categories/{category}/image', [CategoryImageController::class, 'destroy'])->name('categories.image.destroy');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show')->withTrashed();
        Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('products/{product}/restore', RestoreProductController::class)->name('products.restore')->withTrashed();
        Route::post('products/{product}/publish', PublishProductController::class)->name('products.publish');
        Route::post('products/{product}/archive', ArchiveProductController::class)->name('products.archive');
        Route::put('products/{product}/options', ProductOptionsController::class)->name('products.options');
        Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
        Route::put('products/{product}/images/order', ReorderProductImagesController::class)->name('products.images.order');
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
        Route::patch('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('products.variants.update')->scopeBindings();
        Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('products.variants.destroy')->scopeBindings();
        Route::post('products/{product}/variants/{variant}/restore', RestoreProductVariantController::class)->name('products.variants.restore')->scopeBindings()->withTrashed();

        Route::get('attributes', [AttributeController::class, 'index'])->name('attributes.index');
        Route::post('attributes', [AttributeController::class, 'store'])->name('attributes.store');
        Route::get('attributes/{attribute}', [AttributeController::class, 'show'])->name('attributes.show');
        Route::patch('attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
        Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');
        Route::post('attributes/{attribute}/values', [AttributeValueController::class, 'store'])->name('attributes.values.store');
        Route::patch('attributes/{attribute}/values/{value}', [AttributeValueController::class, 'update'])->name('attributes.values.update')->scopeBindings();
        Route::delete('attributes/{attribute}/values/{value}', [AttributeValueController::class, 'destroy'])->name('attributes.values.destroy')->scopeBindings();
    });
