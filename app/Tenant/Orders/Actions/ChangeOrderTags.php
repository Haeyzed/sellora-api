<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Actions;

use App\Shared\Auth\AccountReference;
use App\Tenant\Identity\Models\StaffMember;
use App\Tenant\Orders\Enums\OrderEventType;
use App\Tenant\Orders\Models\Order;
use App\Tenant\Orders\Models\OrderTag;
use App\Tenant\Orders\Services\OrderActivity;
use App\Tenant\Orders\Services\OrderEventLog;
use App\Tenant\Orders\Services\OrderProgress;
use Illuminate\Support\Facades\DB;

/**
 * Staff replacing an order's tags, labels for finding orders again. Tags are in lower case and listed once each.
 */
final readonly class ChangeOrderTags
{
    public function __construct(
        private OrderProgress $orderProgress,
        private OrderEventLog $orderEventLog,
        private OrderActivity $orderActivity,
    ) {}

    /**
     * @param  list<string>  $tags
     */
    public function handle(StaffMember $actor, Order $order, array $tags): Order
    {
        return DB::transaction(function () use ($actor, $order, $tags): Order {
            $locked = $this->orderProgress->lock($order);
            $wanted = array_values(array_unique(array_map(static fn (string $tag): string => mb_strtolower(trim($tag)), $tags)));
            sort($wanted);
            /** @var list<string> $current */
            $current = OrderTag::query()->where('order_id', $locked->id)->orderBy('tag')->pluck('tag')->all();
            $added = array_values(array_diff($wanted, $current));
            $removed = array_values(array_diff($current, $wanted));

            if ($added === [] && $removed === []) {
                return $locked;
            }

            OrderTag::query()->where('order_id', $locked->id)->whereIn('tag', $removed)->delete();
            OrderTag::query()->insert(array_map(static fn (string $tag): array => ['order_id' => $locked->id, 'tag' => $tag], $added));

            $this->orderEventLog->record($locked, OrderEventType::TagsChanged, ['added' => $added, 'removed' => $removed], AccountReference::to($actor));
            $this->orderActivity->record($actor, $locked, 'order_tags_changed', 'Changed the tags of');

            return $locked;
        });
    }
}
