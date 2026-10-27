<?php

declare(strict_types=1);

namespace App\Tenant\Delivery\Http\Requests;

use App\Tenant\Delivery\Enums\DeliveryFailureReason;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * The rules for how a delivery ended: who took the goods, or why it failed.
 */
trait ValidatesDeliveryOutcome
{
    /**
     * @return list<string>
     */
    protected function recipientNameRules(string $required = 'required'): array
    {
        return [$required, 'string', 'min:1', 'max:255'];
    }

    /**
     * @return list<string>
     */
    protected function cashCollectedRules(): array
    {
        return ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX];
    }

    /**
     * The cash collected, in minor units of the order's currency; null when none was given.
     */
    public function cashCollected(): ?int
    {
        return $this->filled('cash_collected') ? $this->integer('cash_collected') : null;
    }

    /**
     * @return array<string, list<string|Enum>>
     */
    protected function failureRules(string $required = 'required'): array
    {
        return [
            /** Why it couldn't be delivered. */
            'reason' => [$required, 'string', Rule::enum(DeliveryFailureReason::class)],
            /** What happened, in a few words; required when the reason is "other". */
            'note' => ['nullable', 'string', 'max:500', 'required_if:reason,'.DeliveryFailureReason::Other->value],
        ];
    }

    public function recipientName(): string
    {
        return trim($this->string('recipient_name')->toString());
    }

    public function failureReason(): DeliveryFailureReason
    {
        return DeliveryFailureReason::from($this->string('reason')->toString());
    }

    public function failureNote(): ?string
    {
        $note = $this->input('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
