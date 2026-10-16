<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Http\Requests;

use App\Shared\Http\CursorListRequest;
use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Orders\Enums\FulfilmentStatus;
use App\Tenant\Orders\Enums\OrderStatus;
use App\Tenant\Orders\Enums\PaymentStatus;
use App\Tenant\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * A page of the store's orders, newest first, optionally searched by number or email, or narrowed by status, date, customer and more.
 */
final class ListOrdersRequest extends CursorListRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('viewAny', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** An order number ("1042" or "#1042") or a buyer's whole email: exact matches only. An email finds orders placed with it or corrected to it. */
            'search' => ['sometimes', 'string', 'min:1', 'max:254'],
            'status' => ['sometimes', 'string', Rule::enum(OrderStatus::class)],
            'payment_status' => ['sometimes', 'string', Rule::enum(PaymentStatus::class)],
            'fulfilment_status' => ['sometimes', 'string', Rule::enum(FulfilmentStatus::class)],
            /** True for cancelled orders whose payment is owed back. */
            'refund_due' => ['sometimes', 'boolean'],
            /** Placed at or after this moment (ISO 8601). */
            'placed_from' => ['sometimes', 'date'],
            /** Placed before this moment (ISO 8601). */
            'placed_to' => ['sometimes', 'date'],
            /** A customer's ID: only their orders. */
            'customer' => ['sometimes', 'string', 'max:26'],
            /** ISO 4217, such as "USD": only orders in it. */
            'currency' => ['sometimes', 'string', 'size:3'],
            /** A shipping method's ID: only orders shipped with it. */
            'shipping_method' => ['sometimes', 'string', 'max:26'],
            /** A promotion's ID: only orders it discounted. */
            'promotion' => ['sometimes', 'string', 'max:26'],
            /** Only orders with this tag. */
            'tag' => ['sometimes', 'string', 'max:40'],
        ];
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function applyFilters(Builder $query): void
    {
        $search = $this->validated('search');

        if (is_string($search)) {
            $this->search($query, trim($search));
        }

        foreach (['status', 'payment_status', 'fulfilment_status'] as $status) {
            $value = $this->validated($status);

            if (is_string($value)) {
                $query->where($status, $value);
            }
        }

        if ($this->has('refund_due')) {
            $query->where('refund_due', $this->boolean('refund_due'));
        }

        if ($this->filled('placed_from')) {
            $query->where('placed_at', '>=', CarbonImmutable::parse($this->string('placed_from')->value()));
        }

        if ($this->filled('placed_to')) {
            $query->where('placed_at', '<', CarbonImmutable::parse($this->string('placed_to')->value()));
        }

        $this->whereRelated($query, 'customer', static fn (Builder $orders, string $id) => $orders->whereHas('customer', static fn (Builder $customer) => $customer->where('public_id', $id)));
        $this->whereRelated($query, 'currency', static fn (Builder $orders, string $currency) => $orders->where('currency', mb_strtoupper($currency)));
        $this->whereRelated($query, 'shipping_method', static fn (Builder $orders, string $id) => $orders->whereHas('shippingLine.method', static fn (Builder $method) => $method->where('public_id', $id)));
        $this->whereRelated($query, 'promotion', static fn (Builder $orders, string $id) => $orders->whereHas('discounts.promotion', static fn (Builder $promotion) => $promotion->where('public_id', $id)));
        $this->whereRelated($query, 'tag', static fn (Builder $orders, string $tag) => $orders->whereHas('tags', static fn (Builder $tags) => $tags->where('tag', mb_strtolower(trim($tag)))));
    }

    /**
     * An order number, or a whole email as placed or corrected.
     *
     * @param  Builder<Order>  $query
     */
    private function search(Builder $query, string $search): void
    {
        if (preg_match('/^#?(\d{1,18})$/', $search, $matches) === 1) {
            $query->where('number', (int) $matches[1]);

            return;
        }

        $email = mb_strtolower($search);
        $query->where(static fn (Builder $byEmail) => $byEmail->where('contact_email', $email)->orWhereHas('contactCorrections', static fn (Builder $corrections) => $corrections->where('email', $email)));
    }

    /**
     * @param  Builder<Order>  $query
     * @param  callable(Builder<Order>, string): mixed  $apply
     */
    private function whereRelated(Builder $query, string $key, callable $apply): void
    {
        $value = $this->validated($key);

        if (is_string($value)) {
            $apply($query, $value);
        }
    }
}
