<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Enums;

/**
 * Why the buyer is sending items back, from a fixed list shown in the buyer's language (lang/{locale}/returns.php), with an optional note.
 */
enum ReturnReason: string
{
    case Damaged = 'damaged';

    case WrongItem = 'wrong_item';

    case Faulty = 'faulty';

    case NotAsDescribed = 'not_as_described';

    case DoesNotFit = 'does_not_fit';

    case ChangedMind = 'changed_mind';

    case Other = 'other';

    /**
     * Whether the buyer's reason claims the store was at fault (damaged, the wrong item, faulty, or not as described): the store may require a photo, and staff confirm it or overrule it with a reason (OrderReturn::isStoresFault()).
     */
    public function isStoresFault(): bool
    {
        return in_array($this, [self::Damaged, self::WrongItem, self::Faulty, self::NotAsDescribed], true);
    }

    /**
     * Its name in the current language.
     */
    public function label(): string
    {
        return __('returns.reasons.'.$this->value);
    }
}
