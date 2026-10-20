<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Actions;

use App\Tenant\Payments\Data\ManualPaymentMethod;
use App\Tenant\Payments\Enums\PaymentMethod;
use App\Tenant\Payments\Models\PaymentMethodSetting;

/**
 * The payment methods paid by hand, as the store has set them up: off, with the defaults in config/payments.php, until it does.
 */
final readonly class FindManualPaymentMethods
{
    /**
     * @return list<ManualPaymentMethod>
     */
    public function all(): array
    {
        $settings = PaymentMethodSetting::query()->get()->keyBy(static fn (PaymentMethodSetting $setting): string => $setting->method->value);

        return array_map(static fn (PaymentMethod $method): ManualPaymentMethod => self::from($method, $settings->get($method->value)), PaymentMethod::manual());
    }

    public function find(PaymentMethod $method): ManualPaymentMethod
    {
        return self::from($method, PaymentMethodSetting::query()->where('method', $method->value)->first());
    }

    private static function from(PaymentMethod $method, ?PaymentMethodSetting $setting): ManualPaymentMethod
    {
        if ($setting !== null) {
            return new ManualPaymentMethod($method, $setting->enabled, $setting->deadline_hours, $setting->collection_alert_hours, $setting->instructions);
        }

        $defaults = 'payments.manual_methods.'.$method->value;

        return new ManualPaymentMethod(
            $method,
            false,
            config()->integer($defaults.'.deadline_hours'),
            $method === PaymentMethod::CashOnDelivery ? config()->integer($defaults.'.collection_alert_hours') : null,
            null,
        );
    }
}
