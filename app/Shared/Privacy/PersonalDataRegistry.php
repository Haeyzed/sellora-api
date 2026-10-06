<?php

declare(strict_types=1);

namespace App\Shared\Privacy;

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\DatabaseManager;
use LogicException;

/**
 * The one place that knows every feature holding personal data, so a data-protection request never misses any.
 *
 * Domains and modules register their handlers from their service providers.
 * An export collects every registered section; an erase runs every handler
 * inside one transaction on the current database (the store's own database
 * during a store request), so a request is never left half done.
 */
final class PersonalDataRegistry
{
    /**
     * @var array<string, array<string, class-string<PersonalDataHandler>>> Handler classes keyed by subject type, then section name.
     */
    private array $handlers = [];

    public function __construct(
        private readonly Container $container,
        private readonly DatabaseManager $databases,
    ) {}

    /**
     * Adds a feature's handler for one kind of person, under a section name shown in exports (for example "addresses").
     *
     * @param  class-string<PersonalDataHandler>  $handler
     *
     * @throws LogicException When the same section is registered twice for the same kind of person.
     */
    public function register(string $subjectType, string $section, string $handler): void
    {
        if (isset($this->handlers[$subjectType][$section])) {
            throw new LogicException("A personal data handler for [{$subjectType}.{$section}] is already registered.");
        }

        $this->handlers[$subjectType][$section] = $handler;
    }

    /**
     * Collects everything every feature holds about the person, grouped by section.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function export(DataSubject $subject): array
    {
        $exportedSections = [];

        foreach ($this->handlersFor($subject) as $section => $handler) {
            $exportedSections[$section] = [...$handler->export($subject)];
        }

        return $exportedSections;
    }

    /**
     * Erases the person's data from every feature, all or nothing.
     *
     * @return list<string> The sections that were erased.
     */
    public function erase(DataSubject $subject): array
    {
        $handlers = $this->handlersFor($subject);

        $this->databases->connection()->transaction(static function () use ($handlers, $subject): void {
            foreach ($handlers as $handler) {
                $handler->erase($subject);
            }
        });

        return array_keys($handlers);
    }

    /**
     * @return array<string, PersonalDataHandler>
     */
    private function handlersFor(DataSubject $subject): array
    {
        $handlers = [];

        foreach ($this->handlers[$subject->type] ?? [] as $section => $handlerClass) {
            $handlers[$section] = $this->container->make($handlerClass);
        }

        return $handlers;
    }
}
