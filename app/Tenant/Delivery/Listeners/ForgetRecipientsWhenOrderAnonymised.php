<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Listeners;

use App\Tenant\Delivery\Models\Delivery;
use App\Tenant\Delivery\Models\DriverLocation;
use App\Tenant\Orders\Events\OrderAnonymised;
use App\Tenant\Orders\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Removes what an anonymised order's deliveries hold about its buyer, inside the same PersonalDataErasure run: who took the goods, failure notes, proof of delivery, and where the driver was on the way to them.
 *
 * The deliveries themselves stay, with their statuses and times. Proof rows
 * are deleted in the transaction and their files once it commits, so a
 * rolled-back erasure never loses a file its row still points at.
 */
final readonly class ForgetRecipientsWhenOrderAnonymised
{
    public function __construct(private FilesystemFactory $filesystems) {}

    public function handle(OrderAnonymised $event): void
    {
        $deliveries = Delivery::query()->whereIn('shipment_id', Shipment::query()->select('id')->where('order_id', $event->order->id));
        $ids = $deliveries->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        Delivery::query()->whereKey($ids)->toBase()->update([
            'recipient_name' => null,
            'failure_note' => null,
            'anonymised_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
        DriverLocation::query()->whereIn('delivery_id', $ids)->delete();

        $proof = Media::query()->where('model_type', (new Delivery)->getMorphClass())->whereIn('model_id', $ids)->get();

        if ($proof->isEmpty()) {
            return;
        }

        // A query delete leaves the files alone; they go once the erasure commits.
        Media::query()->whereKey($proof->modelKeys())->delete();

        foreach ($proof as $media) {
            $directory = dirname($media->getPathRelativeToRoot());
            DB::afterCommit(fn () => $this->filesystems->disk($media->disk)->deleteDirectory($directory));
        }
    }
}
