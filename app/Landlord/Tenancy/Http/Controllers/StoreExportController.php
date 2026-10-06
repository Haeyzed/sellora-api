<?php

declare(strict_types=1);

namespace App\Landlord\Tenancy\Http\Controllers;

use App\Landlord\Tenancy\Actions\FindRequestedStoreExport;
use App\Landlord\Tenancy\Actions\OpenStoreExport;
use App\Landlord\Tenancy\Actions\RequestStoreExport;
use App\Landlord\Tenancy\Exceptions\StoreStatusConflictException;
use App\Landlord\Tenancy\Http\Requests\ExportStoreRequest;
use App\Landlord\Tenancy\Http\Resources\StoreExportResource;
use App\Landlord\Tenancy\Models\StoreExport;
use App\Landlord\Tenancy\Models\Tenant;
use App\Landlord\Tenancy\PlatformStoreExports;
use App\Shared\Http\Controller;
use App\Shared\Tenancy\Exceptions\StoreExportInProgressException;
use App\Shared\Tenancy\Exceptions\StoreExportNotFoundException;
use App\Shared\Tenancy\Exceptions\StoreExportNotReadyException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Full exports of a store's data for Sellora's team, for example when a merchant asks for theirs after closing.
 */
final class StoreExportController extends Controller
{
    /**
     * List your exports of a store.
     *
     * Needs the stores.export permission. Only the exports you requested,
     * newest first; each is kept for 7 days.
     */
    public function index(ExportStoreRequest $request, Tenant $store): AnonymousResourceCollection
    {
        $storeExports = StoreExport::query()
            ->where('tenant_id', $store->id)
            ->requestedBy($request->requester())
            ->orderByDesc('id')
            ->get();

        return StoreExportResource::collection($storeExports->map(PlatformStoreExports::summaryOf(...)));
    }

    /**
     * Export a store's data.
     *
     * Needs the stores.export permission, because an export holds every
     * customer's personal data. Works on active, suspended and closed stores.
     * The export is built in the background, one at a time per store: check
     * its status, then download it. It is kept for 7 days, and only you can
     * download it. Passwords, PINs, tokens, two-factor secrets and other
     * secrets are never included; the manifest lists everything left out.
     *
     * @throws StoreStatusConflictException
     * @throws StoreExportInProgressException
     */
    public function store(ExportStoreRequest $request, Tenant $store, RequestStoreExport $requestStoreExport): JsonResponse
    {
        $storeExport = $requestStoreExport->handle($store, $request->requester(), $request->actor());

        return (new StoreExportResource(PlatformStoreExports::summaryOf($storeExport->refresh())))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Show one of your exports.
     *
     * @throws StoreExportNotFoundException
     */
    public function show(ExportStoreRequest $request, Tenant $store, string $storeExport, FindRequestedStoreExport $findRequestedStoreExport): StoreExportResource
    {
        return new StoreExportResource(PlatformStoreExports::summaryOf($findRequestedStoreExport->handle($store, $storeExport, $request->requester())));
    }

    /**
     * Download one of your exports.
     *
     * A ZIP of JSON Lines files, one per table, plus a manifest. Only the
     * platform admin who requested it, while it is ready; every download is
     * recorded.
     *
     * @throws StoreExportNotFoundException
     * @throws StoreExportNotReadyException
     */
    public function download(ExportStoreRequest $request, Tenant $store, string $storeExport, FindRequestedStoreExport $findRequestedStoreExport, OpenStoreExport $openStoreExport): StreamedResponse
    {
        $requester = $request->requester();
        $download = $openStoreExport->handle($findRequestedStoreExport->handle($store, $storeExport, $requester), $requester, $request->actor());

        return response()->stream(static function () use ($download): void {
            $stream = ($download->openStream)();
            fpassthru($stream);
            fclose($stream);
        }, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $download->fileName),
            'Cache-Control' => 'no-store',
        ]);
    }
}
