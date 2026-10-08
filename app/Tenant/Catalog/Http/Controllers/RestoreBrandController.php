<?php

declare(strict_types=1);

namespace App\Tenant\Catalog\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Catalog\Actions\RestoreBrand;
use App\Tenant\Catalog\Exceptions\BrandRestoreConflictException;
use App\Tenant\Catalog\Http\Requests\ManageBrandRequest;
use App\Tenant\Catalog\Http\Resources\BrandResource;
use App\Tenant\Catalog\Models\Brand;

/**
 * Bringing a brand back from the trash.
 */
final class RestoreBrandController extends Controller
{
    /**
     * Restore a brand.
     *
     * Needs the catalog.manage permission. Refused while another brand uses
     * its slug. Restoring a brand that isn't in the trash changes nothing.
     *
     * @throws BrandRestoreConflictException
     */
    public function __invoke(ManageBrandRequest $request, Brand $brand, RestoreBrand $restoreBrand): BrandResource
    {
        return new BrandResource($restoreBrand->handle($brand));
    }
}
