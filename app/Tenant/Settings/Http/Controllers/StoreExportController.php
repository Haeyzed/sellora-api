<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Http\Controllers;

use App\Shared\Http\Controller;
use App\Shared\Tenancy\Exceptions\StoreExportInProgressException;
use App\Shared\Tenancy\Exceptions\StoreExportNotFoundException;
use App\Shared\Tenancy\Exceptions\StoreExportNotReadyException;
use App\Tenant\Settings\Actions\ExportOwnStore;
use App\Tenant\Settings\Http\Requests\ExportOwnStoreRequest;
use App\Tenant\Settings\Http\Resources\StoreExportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The owner's full exports of their own store.
 */
final class StoreExportController extends Controller
{
    /**
     * Export the store's data.
     *
     * Owner only. Everything the store holds, built in the background, one
     * export at a time: check its status, then download it. It is kept for 7
     * days, and only you can download it. Passwords, PINs, tokens, two-factor
     * secrets and other secrets are never included; the manifest lists
     * everything left out. Export before closing the store: a closed store
     * can't be signed in to.
     *
     * @throws StoreExportInProgressException
     */
    public function store(ExportOwnStoreRequest $request, ExportOwnStore $exportOwnStore): JsonResponse
    {
        return (new StoreExportResource($exportOwnStore->request($request->requester())))->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Show one of your exports.
     *
     * @throws StoreExportNotFoundException
     */
    public function show(ExportOwnStoreRequest $request, string $storeExport, ExportOwnStore $exportOwnStore): StoreExportResource
    {
        return new StoreExportResource($exportOwnStore->find($storeExport, $request->requester()));
    }

    /**
     * Download one of your exports.
     *
     * A ZIP of JSON Lines files, one per table, plus a manifest. Only while it
     * is ready; every download is recorded.
     *
     * @throws StoreExportNotFoundException
     * @throws StoreExportNotReadyException
     */
    public function download(ExportOwnStoreRequest $request, string $storeExport, ExportOwnStore $exportOwnStore): StreamedResponse
    {
        $download = $exportOwnStore->open($storeExport, $request->requester());

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
