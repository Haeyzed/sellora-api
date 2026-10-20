<?php

declare(strict_types=1);

namespace App\Tenant\Orders\Events;

use App\Tenant\Orders\Models\CreditNote;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A credit note was issued for part of a refund that reduced the sale. Announced only once it is saved.
 */
final class CreditNoteIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly CreditNote $creditNote) {}
}
