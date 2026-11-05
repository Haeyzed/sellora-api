<?php

declare(strict_types=1);

namespace App\Tenant\Returns\Exceptions;

use LogicException;

/**
 * Raised when the current store has no return settings row. Every store gets its return settings when it is set up (or from the migration that added them for existing stores), so this is a bug, never patched over with defaults on read (section 10).
 */
final class MissingReturnSettingsException extends LogicException
{
    public function __construct()
    {
        parent::__construct('The current store has no return settings row; return settings are created when a store is set up.');
    }
}
