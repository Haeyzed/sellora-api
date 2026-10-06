<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Who did something, as an account type and a public ID, for records kept outside that account's database.
 *
 * A store's staff live in the store's database and platform admins in the
 * central one, so a central record can't point at a staff member with a
 * foreign key. The type is the account's morph name, such as "staff_member".
 */
final readonly class AccountReference
{
    public function __construct(
        public string $type,
        public string $publicId,
    ) {}

    /**
     * @throws LogicException When the account has no public ID, which is a programming mistake.
     */
    public static function to(Model $account): self
    {
        $publicId = $account->getAttribute('public_id');

        if (! is_string($publicId) || ! in_array(HasPublicId::class, class_uses_recursive($account), true)) {
            throw new LogicException('Only accounts with a public ID can be referred to.');
        }

        return new self($account->getMorphClass(), $publicId);
    }
}
