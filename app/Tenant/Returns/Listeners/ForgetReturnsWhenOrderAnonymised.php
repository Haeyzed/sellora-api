<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Listeners;

use App\Tenant\Orders\Events\OrderAnonymised;
use App\Tenant\Returns\Models\OrderReturn;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Removes what an anonymised order's returns hold about its buyer, inside the same PersonalDataErasure run: the note added about the reason, and the photos.
 *
 * The returns themselves stay, with their lines, statuses, times and
 * refunds. Photo rows are deleted in the transaction and their files once it
 * commits, so a rolled-back erasure never loses a file its row still points
 * at.
 */
final readonly class ForgetReturnsWhenOrderAnonymised
{
    public function __construct(private FilesystemFactory $filesystems) {}

    public function handle(OrderAnonymised $event): void
    {
        $ids = OrderReturn::query()->where('order_id', $event->order->id)->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        OrderReturn::query()->whereKey($ids)->toBase()->update([
            'note' => null,
            'anonymised_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

        $photos = Media::query()->where('model_type', (new OrderReturn)->getMorphClass())->whereIn('model_id', $ids)->get();

        if ($photos->isEmpty()) {
            return;
        }

        // A query delete leaves the files alone; they go once the erasure commits.
        Media::query()->whereKey($photos->modelKeys())->delete();

        foreach ($photos as $media) {
            $directory = dirname($media->getPathRelativeToRoot());
            DB::afterCommit(fn () => $this->filesystems->disk($media->disk)->deleteDirectory($directory));
        }
    }
}
