<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Concerns\ActsAsStaffMember;
use App\Tenant\Identity\Models\OwnershipTransfer;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * The colleague taking over the store, accepting Sellora's terms of service in force as they do.
 */
final class AcceptOwnershipTransferRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('accept', $this->ownershipTransfer());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** The ID of the terms of service version in force, from the legal documents list. The store's contract with Sellora becomes yours. */
            'accepted_terms_of_service' => ['required', 'string', 'ulid'],
        ];
    }

    public function acceptedTermsOfServiceId(): string
    {
        return $this->string('accepted_terms_of_service')->value();
    }

    /**
     * @throws LogicException When the route has no {ownershipTransfer} parameter, which is a routing mistake.
     */
    public function ownershipTransfer(): OwnershipTransfer
    {
        $ownershipTransfer = $this->route('ownershipTransfer');

        return $ownershipTransfer instanceof OwnershipTransfer ? $ownershipTransfer : throw new LogicException('This route needs an {ownershipTransfer} parameter.');
    }
}
