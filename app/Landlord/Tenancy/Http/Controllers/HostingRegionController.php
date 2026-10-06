<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Http\Resources\HostingRegionResource;
use App\Landlord\Tenancy\Services\DatabaseServerPlacement;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Where a new store's data can be hosted.
 */
final class HostingRegionController extends Controller
{
    /**
     * List hosting regions.
     *
     * The regions a new store can choose now: only those with a database
     * server accepting new stores. A store's region is fixed once registered.
     *
     * @unauthenticated
     */
    public function index(DatabaseServerPlacement $databaseServerPlacement): AnonymousResourceCollection
    {
        $regionCodes = array_values(array_filter(
            config()->array('platform.regions'),
            static fn (mixed $regionCode): bool => is_string($regionCode) && $databaseServerPlacement->hasRoomIn($regionCode),
        ));

        return HostingRegionResource::collection($regionCodes);
    }
}
