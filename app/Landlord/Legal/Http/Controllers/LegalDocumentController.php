<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Controllers;

use App\Landlord\Legal\Actions\DraftLegalDocument;
use App\Landlord\Legal\Actions\ReviseLegalDocument;
use App\Landlord\Legal\Exceptions\LegalDocumentAlreadyPublishedException;
use App\Landlord\Legal\Exceptions\LegalDocumentVersionTakenException;
use App\Landlord\Legal\Http\Requests\DraftLegalDocumentRequest;
use App\Landlord\Legal\Http\Requests\ListLegalDocumentsRequest;
use App\Landlord\Legal\Http\Requests\ReviseLegalDocumentRequest;
use App\Landlord\Legal\Http\Requests\ViewLegalDocumentRequest;
use App\Landlord\Legal\Http\Resources\ManagedLegalDocumentResource;
use App\Landlord\Legal\Models\LegalDocument;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sellora's legal documents and their versions.
 */
final class LegalDocumentController extends Controller
{
    /**
     * List legal document versions.
     *
     * Needs the legal_documents.manage permission. Every version, drafts
     * included, newest first.
     */
    public function index(ListLegalDocumentsRequest $request): AnonymousResourceCollection
    {
        $legalDocuments = LegalDocument::query()
            ->when($request->type() !== null, static fn ($query) => $query->where('type', $request->type()))
            ->orderByDesc('id')
            ->cursorPaginate($request->perPage());

        return ManagedLegalDocumentResource::collection($legalDocuments);
    }

    /**
     * Draft a new version.
     *
     * Needs the legal_documents.manage permission. Merchants don't see it
     * until it is published.
     *
     * @throws LegalDocumentVersionTakenException
     */
    public function store(DraftLegalDocumentRequest $request, DraftLegalDocument $draftLegalDocument): JsonResponse
    {
        $legalDocument = $draftLegalDocument->handle($request->actor(), $request->type(), $request->version(), $request->title(), $request->body());

        return (new ManagedLegalDocumentResource($legalDocument))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * View a version.
     *
     * Needs the legal_documents.manage permission.
     */
    public function show(ViewLegalDocumentRequest $request, LegalDocument $legalDocument): ManagedLegalDocumentResource
    {
        return new ManagedLegalDocumentResource($legalDocument);
    }

    /**
     * Revise a draft.
     *
     * Needs the legal_documents.manage permission. Only drafts can change; a
     * published version needs a new version instead.
     *
     * @throws LegalDocumentAlreadyPublishedException
     * @throws LegalDocumentVersionTakenException
     */
    public function update(ReviseLegalDocumentRequest $request, LegalDocument $legalDocument, ReviseLegalDocument $reviseLegalDocument): ManagedLegalDocumentResource
    {
        return new ManagedLegalDocumentResource(
            $reviseLegalDocument->handle($request->actor(), $legalDocument, $request->version(), $request->title(), $request->body()),
        );
    }
}
