<?php

declare(strict_types=1);

namespace App\Shared\Privacy\Contracts;

use App\Shared\Privacy\DataSubject;

/**
 * Exports and erases the personal data one feature holds about a person, for data-protection requests (GDPR, NDPA, CCPA).
 *
 * Every domain or module that stores personal data implements one handler
 * per kind of data and registers it in the PersonalDataRegistry, so "export
 * my data" and "delete my data" automatically cover every feature, including
 * modules added later. Financial records are anonymised rather than deleted.
 */
interface PersonalDataHandler
{
    /**
     * Returns every record this feature holds about the person, ready to include in their data export.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function export(DataSubject $subject): iterable;

    /**
     * Deletes, or anonymises where the law requires keeping the record, everything this feature holds about the person.
     */
    public function erase(DataSubject $subject): void;
}
