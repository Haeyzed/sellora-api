<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Promotions\Actions\ManagePromotionCodes;
use App\Tenant\Promotions\Exceptions\PromotionCodeTakenException;
use App\Tenant\Promotions\Exceptions\PromotionTakesNoCodesException;
use App\Tenant\Promotions\Http\Requests\AddPromotionCodeRequest;
use App\Tenant\Promotions\Http\Requests\GeneratePromotionCodesRequest;
use App\Tenant\Promotions\Http\Requests\ListPromotionCodesRequest;
use App\Tenant\Promotions\Http\Requests\ManagePromotionRequest;
use App\Tenant\Promotions\Http\Requests\UpdatePromotionCodeRequest;
use App\Tenant\Promotions\Http\Resources\PromotionCodeResource;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The codes of a code promotion, which customers enter to get it. One code per order.
 */
final class PromotionCodeController extends Controller
{
    /**
     * List a promotion's codes.
     *
     * Needs the promotions.view permission. Newest first.
     */
    public function index(ListPromotionCodesRequest $request, Promotion $promotion): AnonymousResourceCollection
    {
        $query = PromotionCode::query()->where('promotion_id', $promotion->id);
        $request->applyFilters($query);

        return PromotionCodeResource::collection($query->orderByDesc('id')->cursorPaginate($request->perPage()));
    }

    /**
     * Add a code.
     *
     * Needs the promotions.manage permission. Only for a code promotion.
     * Audited.
     *
     * @throws PromotionTakesNoCodesException
     * @throws PromotionCodeTakenException
     */
    public function store(AddPromotionCodeRequest $request, Promotion $promotion, ManagePromotionCodes $managePromotionCodes): JsonResponse
    {
        $code = $managePromotionCodes->add($promotion, $request->code(), $request->usageLimit());

        return (new PromotionCodeResource($code))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Generate codes.
     *
     * Needs the promotions.manage permission and an Idempotency-Key header,
     * so a retry never makes a second batch. Only for a code promotion. Up to
     * 1,000 are made at once (200); more are made in the background (202).
     * Logged.
     *
     * @throws PromotionTakesNoCodesException
     */
    public function generate(GeneratePromotionCodesRequest $request, Promotion $promotion, ManagePromotionCodes $managePromotionCodes): JsonResponse
    {
        $result = $managePromotionCodes->generate($request->actor(), $promotion, $request->count(), $request->length(), $request->prefix(), $request->usageLimit());

        if ($result['queued']) {
            return new JsonResponse(['count' => $result['count'], 'queued' => true], Response::HTTP_ACCEPTED);
        }

        return new JsonResponse(['count' => $result['count'], 'queued' => false]);
    }

    /**
     * Change a code.
     *
     * Needs the promotions.manage permission. Switch it off or on, or change
     * its limit; the code itself never changes. Audited.
     */
    public function update(UpdatePromotionCodeRequest $request, Promotion $promotion, PromotionCode $code, ManagePromotionCodes $managePromotionCodes): PromotionCodeResource
    {
        return new PromotionCodeResource($managePromotionCodes->update($code, $request->changes()));
    }

    /**
     * Delete a code.
     *
     * Needs the promotions.manage permission. Audited.
     */
    public function destroy(ManagePromotionRequest $request, Promotion $promotion, PromotionCode $code, ManagePromotionCodes $managePromotionCodes): Response
    {
        $managePromotionCodes->delete($code);

        return response()->noContent();
    }
}
