<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Controllers;

use App\Landlord\Legal\Actions\PublishLegalDocument;
use App\Landlord\Legal\Exceptions\LegalDocumentAlreadyPublishedException;
use App\Landlord\Legal\Http\Requests\PublishLegalDocumentRequest;
use App\Landlord\Legal\Http\Resources\ManagedLegalDocumentResource;
use App\Landlord\Legal\Models\LegalDocument;
use App\Shared\Http\Controller;

final class PublishLegalDocumentController extends Controller
{
    /**
     * Publish a draft.
     *
     * Needs the legal_documents.manage permission. It takes effect now or on
     * the given date; the version in force until then stays in force. New
     * merchants accept it from its effective date. A published version never
     * changes.
     *
     * @throws LegalDocumentAlreadyPublishedException
     */
    public function __invoke(PublishLegalDocumentRequest $request, LegalDocument $legalDocument, PublishLegalDocument $publishLegalDocument): ManagedLegalDocumentResource
    {
        return new ManagedLegalDocumentResource($publishLegalDocument->handle($request->actor(), $legalDocument, $request->effectiveAt()));
    }
}
