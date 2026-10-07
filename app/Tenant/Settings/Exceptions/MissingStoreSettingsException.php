<?php

declare(strict_types=1);

namespace App\Tenant\Settings\Exceptions;

use LogicException;

/**
 * Raised when the current store has no settings row. Every store gets its settings when it is set up, so this is a bug, never patched over by creating them on read (section 3.3).
 */
final class MissingStoreSettingsException extends LogicException
{
    public function __construct()
    {
        parent::__construct('The current store has no settings row; settings are created when a store is set up.');
    }
}
