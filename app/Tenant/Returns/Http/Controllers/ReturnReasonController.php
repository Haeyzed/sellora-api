<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Http\Resources\ReturnReasonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The reasons a buyer can give for a return, named in their language.
 */
final class ReturnReasonController extends Controller
{
    /**
     * List return reasons.
     *
     * The fixed list a return's reason comes from, in the language asked for
     * (Accept-Language), falling back to the store's default.
     */
    public function index(): AnonymousResourceCollection
    {
        return ReturnReasonResource::collection(ReturnReason::cases());
    }
}
