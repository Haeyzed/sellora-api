<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Tenant\Customers\Models\Customer;
use App\Tenant\Customers\Privacy\CustomerDataSubject;
use App\Tenant\Promotions\Models\PromotionRedemption;
use Illuminate\Database\Eloquent\Builder;

/**
 * The promotions a customer used, by account or by their current email (guest checkouts included).
 *
 * Erasure keeps each use, which the store's usage counts and its orders need,
 * but removes who it was: the account and the email. Per-customer limits then
 * no longer apply to that person.
 */
final readonly class PromotionUseData implements PersonalDataHandler
{
    /**
     * @return list<array{promotion: string, code: string|null, status: string, discount: array{amount: int, currency: string}, created_at: string}>
     */
    public function export(DataSubject $subject): array
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer === null) {
            return [];
        }

        return array_values(self::usesOf($customer)->with(['promotion', 'code'])->orderBy('id')->get()->map(static fn (PromotionRedemption $use): array => [
            'promotion' => (string) $use->promotion->getTranslation('title', app()->getLocale()),
            'code' => $use->code?->code,
            'status' => $use->status->value,
            'discount' => ['amount' => $use->discount->minorAmount(), 'currency' => $use->discount->currency()],
            'created_at' => (string) $use->created_at?->toIso8601String(),
        ])->all());
    }

    public function erase(DataSubject $subject): void
    {
        $customer = CustomerDataSubject::customer($subject);

        if ($customer !== null) {
            self::usesOf($customer)->update(['customer_id' => null, 'email' => null]);
        }
    }

    /**
     * @return Builder<PromotionRedemption>
     */
    private static function usesOf(Customer $customer): Builder
    {
        return PromotionRedemption::query()->where(static function (Builder $own) use ($customer): void {
            $own->where('customer_id', $customer->id);

            if ($customer->email !== null) {
                $own->orWhere('email', $customer->email);
            }
        });
    }
}
