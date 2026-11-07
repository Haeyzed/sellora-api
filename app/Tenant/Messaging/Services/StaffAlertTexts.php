<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Services;

use App\Shared\Money\Money;
use App\Tenant\Messaging\Models\StaffAlert;

/**
 * A staff alert's title and message in the current language, from lang/*\/staff_alerts.php, with its details filled in: "#1042" for an order, an amount formatted for the language, a SKU.
 */
final readonly class StaffAlertTexts
{
    public function title(StaffAlert $alert): string
    {
        return __('staff_alerts.'.$alert->kind->value.'.title', $this->placeholders($alert));
    }

    public function message(StaffAlert $alert): string
    {
        return __('staff_alerts.'.$alert->kind->value.'.message', $this->placeholders($alert));
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(StaffAlert $alert): array
    {
        $details = $alert->details;
        $amount = $details['amount'] ?? null;
        $currency = $details['currency'] ?? null;

        return array_filter([
            'order' => isset($details['order_number']) ? '#'.$details['order_number'] : null,
            'amount' => is_int($amount) && is_string($currency) ? Money::ofMinor($amount, $currency)->format(app()->getLocale()) : null,
            'gateway' => is_string($details['gateway'] ?? null) ? $details['gateway'] : null,
            'method' => is_string($details['method'] ?? null) ? $details['method'] : null,
            'reason' => is_string($details['reason'] ?? null) ? $details['reason'] : null,
            'product' => is_string($details['product'] ?? null) ? $details['product'] : null,
            'sku' => is_string($details['sku'] ?? null) ? $details['sku'] : '-',
            'available' => isset($details['available']) ? (string) $details['available'] : null,
        ], static fn (?string $value): bool => $value !== null);
    }
}
