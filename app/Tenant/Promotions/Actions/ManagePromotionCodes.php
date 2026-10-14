<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Actions;

use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Promotions\Enums\PromotionMethod;
use App\Tenant\Promotions\Exceptions\PromotionCodeTakenException;
use App\Tenant\Promotions\Exceptions\PromotionTakesNoCodesException;
use App\Tenant\Promotions\Jobs\GeneratePromotionCodes;
use App\Tenant\Promotions\Models\Promotion;
use App\Tenant\Promotions\Models\PromotionCode;
use App\Tenant\Promotions\Services\PromotionActivity;
use App\Tenant\Promotions\Services\PromotionCodeGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Staff add codes to a code promotion one at a time or generate them in bulk, switch codes off or on, change their limits and delete them.
 *
 * Codes are unique in the store ignoring case. Generating more than
 * promotions.generated_codes.made_at_once codes happens on the bulk queue.
 */
final readonly class ManagePromotionCodes
{
    public function __construct(
        private PromotionActivity $promotionActivity,
        private PromotionCodeGenerator $promotionCodeGenerator,
    ) {}

    /**
     * @throws PromotionTakesNoCodesException When the promotion is automatic.
     * @throws PromotionCodeTakenException When another code is the same, ignoring case.
     */
    public function add(Promotion $promotion, string $code, ?int $usageLimit): PromotionCode
    {
        self::ensureTakesCodes($promotion);

        try {
            return $promotion->codes()->create(['code' => $code, 'usage_limit' => $usageLimit]);
        } catch (UniqueConstraintViolationException $exception) {
            throw new PromotionCodeTakenException(previous: $exception);
        }
    }

    /**
     * @return array{count: int, queued: bool}
     *
     * @throws PromotionTakesNoCodesException When the promotion is automatic.
     */
    public function generate(StaffMember $actor, Promotion $promotion, int $count, int $length, string $prefix, ?int $usageLimit): array
    {
        self::ensureTakesCodes($promotion);
        $queued = $count > config()->integer('promotions.generated_codes.made_at_once');

        DB::transaction(function () use ($actor, $promotion, $count, $length, $prefix, $usageLimit, $queued): void {
            $this->promotionActivity->record($actor, $promotion, 'promotion_codes_generated', 'Generated codes for', ['count' => $count, 'queued' => $queued]);

            if ($queued) {
                GeneratePromotionCodes::dispatch($promotion->id, $count, $length, $prefix, $usageLimit)->afterCommit();
            } else {
                $this->insertGenerated($promotion, $count, $length, $prefix, $usageLimit);
            }
        });

        return ['count' => $count, 'queued' => $queued];
    }

    /**
     * Inserts exactly $count new codes in chunks, making fresh ones for any that clash with a code already in the store.
     */
    public function insertGenerated(Promotion $promotion, int $count, int $length, string $prefix, ?int $usageLimit): void
    {
        $inserted = 0;

        while ($inserted < $count) {
            $now = now();
            $rows = array_map(static fn (string $code): array => [
                'public_id' => Str::lower((string) Str::ulid()), 'promotion_id' => $promotion->id, 'code' => $code,
                'usage_limit' => $usageLimit, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ], $this->promotionCodeGenerator->make(min(500, $count - $inserted), $length, $prefix));

            $inserted += DB::table('promotion_codes')->insertOrIgnore($rows);
        }
    }

    /**
     * @param  array{is_active?: bool, usage_limit?: int|null}  $changes
     */
    public function update(PromotionCode $code, array $changes): PromotionCode
    {
        $code->fill($changes)->save();

        return $code;
    }

    public function delete(PromotionCode $code): void
    {
        $code->delete();
    }

    private static function ensureTakesCodes(Promotion $promotion): void
    {
        if ($promotion->method !== PromotionMethod::Code) {
            throw new PromotionTakesNoCodesException;
        }
    }
}
