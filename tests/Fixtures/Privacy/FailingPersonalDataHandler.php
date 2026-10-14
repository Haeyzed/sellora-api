<?php

declare(strict_types=1);

namespace Tests\Fixtures\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use RuntimeException;

/**
 * A feature whose erasure fails, registered after the others, to prove an erasure is all or nothing.
 */
final class FailingPersonalDataHandler implements PersonalDataHandler
{
    /**
     * @return list<array<string, mixed>>
     */
    public function export(DataSubject $subject): array
    {
        return [];
    }

    public function erase(DataSubject $subject): void
    {
        throw new RuntimeException('This feature could not erase its data.');
    }
}
