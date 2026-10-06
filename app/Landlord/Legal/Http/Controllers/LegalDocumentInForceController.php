<?php

declare(strict_types=1);

namespace App\Landlord\Legal\Http\Controllers;

use App\Landlord\Legal\Http\Resources\LegalDocumentResource;
use App\Landlord\Legal\Models\LegalDocument;
use App\Shared\Http\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The legal documents merchants accept to register a store.
 */
final class LegalDocumentInForceController extends Controller
{
    /**
     * List the legal documents in force.
     *
     * The version in force of each document, such as the terms of service.
     * Registering a store needs the IDs of all of them.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return LegalDocumentResource::collection(array_values(LegalDocument::inForce()));
    }
}
