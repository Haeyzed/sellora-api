<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Section 13: queue workers listen on every queue the app uses, or that work
 * never runs. The queues jobs are put on (named in code or in config) must all
 * be in the deployment guide's worker command and the local dev script, with
 * payments first, so money never waits behind bulk work.
 */

it('listens on every queue the app puts jobs on, payments first', function (): void {
    $queues = ['default', config()->string('payments.queue'), config()->string('retention.queue'), config()->string('tenancy.store_exports.queue')];

    foreach (Finder::create()->files()->in(app_path())->name('*.php')->contains('onQueue(\'') as $file) {
        preg_match_all("/onQueue\('([a-z0-9_-]+)'\)/", $file->getContents(), $matches);
        array_push($queues, ...$matches[1]);
    }

    $queues = array_values(array_unique($queues));

    foreach ([['docs/deployment.md', 'queue:work'], ['composer.json', 'queue:listen']] as [$path, $command]) {
        $listened = queuesListenedIn($path, $command);

        expect($listened)->toContain(...$queues)
            ->and($listened[0] ?? null)->toBe(config()->string('payments.queue'));
    }
});
