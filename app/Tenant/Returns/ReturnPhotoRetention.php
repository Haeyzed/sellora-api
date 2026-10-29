<?php

declare(strict_types=1);

namespace App\Tenant\Returns;

use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use App\Tenant\Returns\Models\OrderReturn;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Deletes return photos once their return ended (completed, rejected or cancelled) longer ago than the retention period, in every store. The return stays.
 */
final class ReturnPhotoRetention implements RetentionPolicy
{
    public function periodKey(): string
    {
        return 'return_photos';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $deleted = 0;
        $ended = OrderReturn::query()->select('id')->where(static fn (Builder $returns) => $returns
            ->where('completed_at', '<', $cutoff)
            ->orWhere('rejected_at', '<', $cutoff)
            ->orWhere('cancelled_at', '<', $cutoff));
        $old = Media::query()->where('model_type', (new OrderReturn)->getMorphClass())->whereIn('model_id', $ended);

        // Deleting the media row deletes its file too.
        foreach ($old->lazyById(100) as $media) {
            $media->delete();
            $deleted++;
        }

        return $deleted;
    }
}
