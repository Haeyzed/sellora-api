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

    case NotAsDescribed = 'not_as_described';

    case DoesNotFit = 'does_not_fit';

    case ChangedMind = 'changed_mind';

    case Other = 'other';

    /**
     * The store's fault: shipping is offered back by default, and the store may require a photo.
     */
    public function isStoresFault(): bool
    {
        return in_array($this, [self::Damaged, self::WrongItem], true);
    }

    /**
     * Its name in the current language.
     */
    public function label(): string
    {
        return __('returns.reasons.'.$this->value);
    }
}
