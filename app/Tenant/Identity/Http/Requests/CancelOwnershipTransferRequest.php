<?php

declare(strict_types=1);

namespace App\Tenant\Identity\Http\Requests;

use App\Tenant\Identity\Models\OwnershipTransfer;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * The owner withdrawing their offer.
 */
final class CancelOwnershipTransferRequest extends FormRequest
{
    use ActsAsStaffMember;

    public function authorize(): bool
    {
        return $this->actor()->can('cancel', $this->ownershipTransfer());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
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
