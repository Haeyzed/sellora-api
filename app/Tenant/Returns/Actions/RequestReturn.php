<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Actions;

use App\Shared\Features\Exceptions\UsageLimitReachedException;
use App\Shared\Media\EncodedImage;
use App\Tenant\Catalog\Models\ProductVariant;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Returns\Data\ReturnableLine;
use App\Tenant\Returns\Data\ReturnRequester;
use App\Tenant\Returns\Enums\ReturnReason;
use App\Tenant\Returns\Enums\ReturnResolution;
use App\Tenant\Returns\Enums\ReturnStatus;
use App\Tenant\Returns\Exceptions\OrderNotReturnableException;
use App\Tenant\Returns\Exceptions\RefundDestinationNotOfferedException;
use App\Tenant\Returns\Exceptions\ReturnPhotoRequiredException;
use App\Tenant\Returns\Exceptions\ReturnQuantityTooHighException;
use App\Tenant\Returns\Exceptions\ReturnReplacementInvalidException;
use App\Tenant\Returns\Exceptions\ReturnReplacementNotAvailableException;
use App\Tenant\Returns\Exceptions\ReturnsNotOfferedException;
use App\Tenant\Returns\Exceptions\ReturnStaffReasonRequiredException;
use App\Tenant\Returns\Exceptions\ReturnWindowClosedException;
use App\Tenant\Returns\Models\OrderReturn;
use App\Tenant\Returns\Models\ReturnLine;
use App\Tenant\Returns\Models\ReturnSettings;
use App\Tenant\Returns\Services\ReturnableUnits;
use App\Tenant\Returns\Services\ReturnPhotos;
use App\Tenant\Returns\Services\ReturnProgress;
use App\Tenant\Returns\Services\ReturnReplacements;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Asks to return units of an order's lines (section 13): a customer for their own order, a guest with its access token, or staff for the buyer.
 *
 * Only an order that was sold and isn't cancelled can have returns. Buyers
 * can ask only while the store takes their requests, for delivered units
 * within the window, with a photo when the store requires one for damage or
 * the wrong item. Staff can always start a return: for units delivered before
 * the window, or shipped and never marked delivered, they say why. Units
 * already in a return that isn't rejected or cancelled, or refunded by line,
 * can't be returned again. What can be returned is read under the order's
 * returns lock, so two requests at once never claim the same units. Photos
 * are kept in the same transaction, counted against the store's storage.
 * An exchange names the variant sent in place of each returned line, which must be for sale and in stock (or take backorders); no stock is held for it until staff confirm the exchange. ReturnRequested is announced once it is saved.
 */
final readonly class RequestReturn
{
    public function __construct(
        private ReturnableUnits $returnableUnits,
        private ReturnProgress $returnProgress,
        private ReturnPhotos $returnPhotos,
        private ChooseRefundDestination $chooseRefundDestination,
        private ReturnReplacements $returnReplacements,
    ) {}

    /**
     * @param  array<string, int>  $lines  Units to return, by order line public ID.
     * @param  list<EncodedImage>  $photos  Already re-encoded; their temporary files are deleted either way.
     * @param  string|null  $refundDestination  Where the signed-in customer asking wants the refund to go; null leaves it going back to the original payment. For an exchange, where any difference goes.
     * @param  ReturnResolution  $resolution  A refund, or an exchange (Step 18d): other items sent in place of the returned ones.
     * @param  array<string, string>  $replacements  For an exchange: the variant sent in place of each returned line, by order line public ID; none for a refund.
     *
     * @throws RefundDestinationNotOfferedException When anyone but the order's signed-in customer chooses, or the destination isn't on offer.
     * @throws ReturnsNotOfferedException
     * @throws ReturnPhotoRequiredException
     * @throws OrderNotReturnableException
     * @throws ReturnWindowClosedException
     * @throws ReturnQuantityTooHighException
     * @throws ReturnStaffReasonRequiredException
     * @throws UsageLimitReachedException When the photos would take the store over its plan's storage.
     * @throws ReturnReplacementInvalidException When the replacements don't fit the resolution.
     * @throws ReturnReplacementNotAvailableException When a replacement can't be sent now.
     */
    public function handle(
        ReturnRequester $requester,
        Order $order,
        array $lines,
        ReturnReason $reason,
        ?string $note = null,
        array $photos = [],
        ?string $outsideWindowReason = null,
        ?string $undeliveredReason = null,
        ?string $refundDestination = null,
        ReturnResolution $resolution = ReturnResolution::Refund,
        array $replacements = [],
    ): OrderReturn {
        try {
            return DB::transaction(function () use ($requester, $order, $lines, $reason, $note, $photos, $outsideWindowReason, $undeliveredReason, $refundDestination, $resolution, $replacements): OrderReturn {
                $settings = ReturnSettings::current();
                $this->ensureBuyerMayAsk($requester, $settings, $reason, $photos);

                // An anonymised order's buyer is gone: nothing new about them is kept.
                if ($order->status === OrderStatus::Cancelled || ! $order->hasBeenSold() || $order->anonymised_at !== null) {
                    throw new OrderNotReturnableException;
                }

                $this->returnProgress->lockOrderReturns($order);
                $returnable = $this->returnableUnits->of($order, $settings->window_days, CarbonImmutable::now());
                $this->ensureReturnable($requester, $returnable, $lines, $outsideWindowReason, $undeliveredReason);
                $variants = $this->returnReplacements->resolve(array_keys($lines), $resolution, $replacements);
                $this->returnReplacements->ensureAvailable($variants);

                $return = $this->createReturn($requester, $order, $reason, $note, $outsideWindowReason, $undeliveredReason, $resolution);
                $this->createLines($return, $returnable, $lines, $variants);
                $this->returnProgress->record($return, null, ReturnStatus::Requested, $requester->account);
                $this->returnPhotos->keep($return, $photos);

                if ($refundDestination !== null) {
                    $this->chooseRefundDestination->handle($return, $refundDestination, $requester->customer ?? throw new RefundDestinationNotOfferedException);
                }

                return $return->load('lines');
            });
        } finally {
            ReturnPhotos::discard($photos);
        }
    }

    /**
     * @param  list<EncodedImage>  $photos
     *
     * @throws ReturnsNotOfferedException
     * @throws ReturnPhotoRequiredException
     */
    private function ensureBuyerMayAsk(ReturnRequester $requester, ReturnSettings $settings, ReturnReason $reason, array $photos): void
    {
        if ($requester->isStaff) {
            return;
        }

        if (! $settings->customer_requests || $settings->window_days === 0) {
            throw new ReturnsNotOfferedException;
        }

        if ($settings->photos_required && $reason->isStoresFault() && $photos === []) {
            throw new ReturnPhotoRequiredException;
        }
    }

    /**
     * @param  array<string, ReturnableLine>  $returnable
     * @param  array<string, int>  $lines
     *
     * @throws ReturnWindowClosedException
     * @throws ReturnQuantityTooHighException
     * @throws ReturnStaffReasonRequiredException
     */
    private function ensureReturnable(ReturnRequester $requester, array $returnable, array $lines, ?string $outsideWindowReason, ?string $undeliveredReason): void
    {
        $tooMany = [];
        $windowClosed = false;
        $reasons = [];

        foreach ($lines as $publicId => $quantity) {
            $line = $returnable[$publicId] ?? null;
            $left = $line === null ? 0 : ($requester->isStaff ? $line->leftForStaff() : $line->leftForBuyer());

            if ($line === null || $quantity > $left) {
                // A buyer asking for units that were delivered, but before the window, is told the window closed.
                $windowClosed = $windowClosed || ($line !== null && ! $requester->isStaff && $line->line->requires_shipping && $quantity <= max(0, $line->delivered - $line->used));
                $tooMany[$publicId] = $left;

                continue;
            }

            if ($requester->isStaff && $line->needsOutsideWindowReason($quantity) && $outsideWindowReason === null) {
                $reasons['outside_window_reason'] = true;
            }

            if ($requester->isStaff && $line->needsUndeliveredReason($quantity) && $undeliveredReason === null) {
                $reasons['undelivered_reason'] = true;
            }
        }

        if ($windowClosed) {
            throw new ReturnWindowClosedException;
        }

        if ($tooMany !== []) {
            throw new ReturnQuantityTooHighException($tooMany);
        }

        if ($reasons !== []) {
            throw new ReturnStaffReasonRequiredException(array_keys($reasons));
        }
    }

    private function createReturn(ReturnRequester $requester, Order $order, ReturnReason $reason, ?string $note, ?string $outsideWindowReason, ?string $undeliveredReason, ReturnResolution $resolution): OrderReturn
    {
        $return = new OrderReturn;
        $return->forceFill([
            'order_id' => $order->id,
            'sequence' => (int) OrderReturn::query()->where('order_id', $order->id)->max('sequence') + 1,
            'status' => ReturnStatus::Requested,
            'resolution' => $resolution,
            'reason' => $reason,
            'note' => $note,
            'is_test' => $order->is_test,
            'outside_window_reason' => $requester->isStaff ? $outsideWindowReason : null,
            'undelivered_reason' => $requester->isStaff ? $undeliveredReason : null,
            'requested_by_type' => $requester->account?->type,
            'requested_by_id' => $requester->account?->publicId,
            'requested_at' => CarbonImmutable::now(),
        ])->save();
        $return->setRelation('order', $order);

        return $return;
    }

    /**
     * @param  array<string, ReturnableLine>  $returnable
     * @param  array<string, int>  $lines
     * @param  array<string, ProductVariant|null>  $replacements
     */
    private function createLines(OrderReturn $return, array $returnable, array $lines, array $replacements): void
    {
        foreach ($lines as $publicId => $quantity) {
            (new ReturnLine)->forceFill([
                'return_id' => $return->id,
                'order_line_id' => $returnable[$publicId]->line->id,
                'replacement_variant_id' => $replacements[$publicId]?->id,
                'quantity' => $quantity,
            ])->save();
        }
    }
}
