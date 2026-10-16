<?php

declare(strict_types=1);

namespace App\Tenant\Payments\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Payments\Data\CredentialField;
use App\Tenant\Payments\Models\PaymentGatewayConnection;
use App\Tenant\Payments\PaymentGatewayRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The store's credentials for a gateway, as that gateway's integration declares them. Needs the payments.manage_gateways permission. Never logged or returned.
 */
final class ConnectPaymentGatewayRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('manage', PaymentGatewayConnection::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fields = $this->fields();
        $rules = [
            /** The gateway's credential fields (listed with each gateway), such as {"secret_key": "sk_live_…"}. Never shown again. */
            'credentials' => ['required', $fields === [] ? 'array' : 'array:'.implode(',', array_map(static fn (CredentialField $field): string => $field->name, $fields))],
        ];

        foreach ($fields as $field) {
            $rules['credentials.'.$field->name] = ['required', 'string', 'max:500', ...$field->rules];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function credentials(): array
    {
        $credentials = [];

        foreach ($this->fields() as $field) {
            $credentials[$field->name] = trim($this->string('credentials.'.$field->name)->value());
        }

        return $credentials;
    }

    public function gatewayKey(): string
    {
        return (string) $this->route('gateway');
    }

    /**
     * @return list<CredentialField>
     */
    private function fields(): array
    {
        $registry = app(PaymentGatewayRegistry::class);

        // An unknown gateway is answered with a 404 by the action; the docs read these rules with no gateway at all.
        return $registry->has($this->gatewayKey()) ? $registry->get($this->gatewayKey())->credentialFields() : [];
    }
}
