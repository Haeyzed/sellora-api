<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Storefront\Http\Resources\StorefrontSeoResource;
use App\Tenant\Storefront\Models\StorefrontSettings;

/**
 * How the storefront presents itself to search engines and social sites. Open to anyone, rate-limited per visitor.
 */
final class StorefrontSeoController extends Controller
{
    /**
     * Show the storefront's search engine settings.
     *
     * The title pattern, the default description in the language asked for
     * (or the store's default), the robots directive and the share image.
     */
    public function show(FindStoreSettings $findStoreSettings): StorefrontSeoResource
    {
        return new StorefrontSeoResource(StorefrontSettings::current()->load('media'), $findStoreSettings->handle()->name);
    }
}
