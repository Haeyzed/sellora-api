<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Http\Controllers;

use App\Shared\Http\Controller;
use App\Tenant\Promotions\Actions\ChangePromotionStatus;
use App\Tenant\Promotions\Actions\SavePromotion;
use App\Tenant\Promotions\Http\Requests\ListPromotionsRequest;
use App\Tenant\Promotions\Http\Requests\ManagePromotionRequest;
use App\Tenant\Promotions\Http\Requests\SavePromotionRequest;
use App\Tenant\Promotions\Http\Requests\ViewPromotionRequest;
use App\Tenant\Promotions\Http\Resources\PromotionResource;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Settings\Actions\FindStoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The store's promotions: discounts applied automatically or with a code.
 */
final class PromotionController extends Controller
{
    public function __construct(private readonly FindStoreSettings $findStoreSettings) {}

    /**
     * List promotions.
     *
     * Needs the promotions.view permission. Newest first; archived ones only
     * with status=archived.
     */
    public function index(ListPromotionsRequest $request): AnonymousResourceCollection
    {
        $query = Promotion::query()->with(PromotionResource::RELATIONS);
        $request->applyFilters($query);
        $timezone = $this->timezone();

        return PromotionResource::collection($query->orderByDesc('id')->paginate($request->perPage())
            ->through(static fn (Promotion $promotion): PromotionResource => new PromotionResource($promotion, $timezone)));
    }

    /**
     * Add a promotion.
     *
     * Needs the promotions.manage permission. A code promotion needs codes
     * before anyone can use it. Audited and logged.
     */
    public function store(SavePromotionRequest $request, SavePromotion $savePromotion): JsonResponse
    {
        $promotion = $savePromotion->create($request->actor(), $request->details());

        return $this->resource($promotion)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show a promotion.
     *
     * Needs the promotions.view permission. Works for archived ones too.
     */
    public function show(ViewPromotionRequest $request, Promotion $promotion): PromotionResource
    {
        return $this->resource($promotion);
    }

    /**
     * Change a promotion.
     *
     * Needs the promotions.manage permission. Send only what changes; each
     * list sent replaces that list. The method and type can't change. Orders
     * that already used it keep what they got. Audited and logged.
     */
    public function update(SavePromotionRequest $request, Promotion $promotion, SavePromotion $savePromotion): PromotionResource
    {
        return $this->resource($savePromotion->update($request->actor(), $promotion, $request->details()));
    }

    /**
     * Archive a promotion.
     *
     * Needs the promotions.manage permission. For good: it never applies
     * again, and it stays for the orders that used it. Audited and logged.
     */
    public function destroy(ManagePromotionRequest $request, Promotion $promotion, ChangePromotionStatus $changePromotionStatus): Response
    {
        $changePromotionStatus->archive($request->actor(), $promotion);

        return response()->noContent();
    }

    /**
     * Activate a promotion.
     *
     * Needs the promotions.manage permission. It applies again within its
     * dates. Audited and logged.
     */
    public function activate(ManagePromotionRequest $request, Promotion $promotion, ChangePromotionStatus $changePromotionStatus): PromotionResource
    {
        return $this->resource($changePromotionStatus->activate($request->actor(), $promotion));
    }

    /**
     * Deactivate a promotion.
     *
     * Needs the promotions.manage permission. New baskets stop getting it;
     * checkouts that already reserved a use keep it. Audited and logged.
     */
    public function deactivate(ManagePromotionRequest $request, Promotion $promotion, ChangePromotionStatus $changePromotionStatus): PromotionResource
    {
        return $this->resource($changePromotionStatus->deactivate($request->actor(), $promotion));
    }

    private function resource(Promotion $promotion): PromotionResource
    {
        return new PromotionResource($promotion->load(PromotionResource::RELATIONS), $this->timezone());
    }

    private function timezone(): string
    {
        return $this->findStoreSettings->handle()->timezone;
    }
}
