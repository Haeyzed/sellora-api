<?php

declare(strict_types=1);

namespace App\Tenant\Delivery;

use App\Shared\Retention\Contracts\RetentionPolicy;
use App\Shared\Retention\RetentionScope;
use App\Tenant\Delivery\Models\Delivery;
use Carbon\CarbonImmutable;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Deletes proof of delivery files (photo and signature) once their delivery is older than the retention period, in every store. The delivery and who took the goods stay.
 */
final class ProofOfDeliveryRetention implements RetentionPolicy
{
    public function periodKey(): string
    {
        return 'proof_of_delivery';
    }

    public function scope(): RetentionScope
    {
        return RetentionScope::Tenant;
    }

    public function purgeOlderThan(CarbonImmutable $cutoff): int
    {
        $deleted = 0;
        $old = Media::query()
            ->where('model_type', (new Delivery)->getMorphClass())
            ->whereIn('model_id', Delivery::query()->select('id')->where('delivered_at', '<', $cutoff));

        // Deleting the media row deletes its file too.
        foreach ($old->lazyById(100) as $media) {
            $media->delete();
            $deleted++;
        }

        return $deleted;
    }
}
