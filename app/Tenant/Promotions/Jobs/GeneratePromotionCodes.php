<?php

declare(strict_types=1);

namespace App\Tenant\Promotions\Jobs;

use App\Tenant\Promotions\Actions\ManagePromotionCodes;
use App\Tenant\Promotions\Models\Promotion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Generates a large batch of promotion codes on the bulk queue, in the store that asked for them.
 *
 * Not retried: a partly made batch would be made again in full, so staff
 * generate the rest themselves instead. A promotion archived meanwhile gets none.
 */
final class GeneratePromotionCodes implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $promotionId,
        public readonly int $count,
        public readonly int $length,
        public readonly string $prefix,
        public readonly ?int $usageLimit,
    ) {
        $this->onQueue('bulk');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('tenant-bulk-jobs')];
    }

    public function handle(ManagePromotionCodes $managePromotionCodes): void
    {
        $promotion = Promotion::query()->find($this->promotionId);

        if ($promotion !== null) {
            $managePromotionCodes->insertGenerated($promotion, $this->count, $this->length, $this->prefix, $this->usageLimit);
        }
    }
}
