<?php

declare(strict_types=1);

namespace App\Tenant\Messaging\Exceptions;

use LogicException;

/**
 * Raised when the current store has no messaging settings row. Every store gets it when it is set up (or from the migration that added it for existing stores), so this is a bug, never patched over with defaults on read (section 10).
 */
final class MissingMessagingSettingsException extends LogicException
{
    public function __construct()
    {
        parent::__construct('The current store has no messaging settings row; messaging settings are created when a store is set up.');
    }
}
