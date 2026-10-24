<?php

declare(strict_types=1);

namespace Integrations\Paystack\ManualTest;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;

/**
 * Keeps each Paystack webhook received, once its signature checked out, in a git-ignored folder, while the manual test-mode command asks for it, so a real one can be turned into a fixture.
 *
 * Only ever on this machine's local environment, never in CI, and only
 * while the command runs (it marks until when). What is kept may hold a
 * buyer's details: it never leaves the folder, never reaches a log, and
 * must be sanitised before it becomes a fixture (tests/Fixtures/Paystack).
 */
final readonly class ReceivedWebhooks
{
    private const string UNTIL_FILE = '.saving-until';

    public function __construct(private Filesystem $files) {}

    public function isAllowed(): bool
    {
        return app()->environment('local') && ! config()->boolean('paystack.manual_test.in_ci');
    }

    public function startSaving(CarbonImmutable $until): void
    {
        $this->files->ensureDirectoryExists($this->folder());
        $this->files->put($this->folder().DIRECTORY_SEPARATOR.self::UNTIL_FILE, (string) $until->getTimestamp());
    }

    public function stopSaving(): void
    {
        $this->files->delete($this->folder().DIRECTORY_SEPARATOR.self::UNTIL_FILE);
    }

    /**
     * Keeps a webhook whose signature checked out, if saving is on; returns where, or null.
     */
    public function keep(string $body): ?string
    {
        if (! $this->isSaving()) {
            return null;
        }

        $path = $this->folder().DIRECTORY_SEPARATOR.CarbonImmutable::now()->format('Ymd-His').'-'.mb_substr(hash('sha256', $body), 0, 12).'.json';
        $this->files->put($path, $body);

        return $path;
    }

    public function folder(): string
    {
        return config()->string('paystack.manual_test.webhooks_path');
    }

    private function isSaving(): bool
    {
        $untilFile = $this->folder().DIRECTORY_SEPARATOR.self::UNTIL_FILE;

        if (! $this->isAllowed() || ! $this->files->exists($untilFile)) {
            return false;
        }

        return (int) $this->files->get($untilFile) > CarbonImmutable::now()->getTimestamp();
    }
}
