<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/*
 * No real gateway key is ever committed: not in code, tests, fixtures, docs
 * or examples. Stores' keys live encrypted in their databases, and a
 * developer's in their own .env only, which git ignores.
 */

it('finds no real gateway key in any file git would commit', function (): void {
    $basePath = dirname(__DIR__, 2);
    $files = new Process(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], $basePath);
    $files->mustRun();

    $leaks = filesWithGatewayKeys($basePath, array_values(array_filter(explode("\0", $files->getOutput()))));

    expect($leaks)->toBe([], 'Remove these keys, rotate them with the gateway, and keep keys in .env only: '.implode(', ', $leaks));
});

it('finds a real-looking key in any text file, and ignores made-up ones and binary files', function (): void {
    $folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gateway-key-scan-'.bin2hex(random_bytes(4));
    mkdir($folder);
    $realLooking = 'sk_'.'test_'.str_repeat('a1b2c3d4e5', 4);
    $files = [
        'config.php' => "<?php return ['key' => '{$realLooking}'];",
        'notes.md' => 'Paystack live key: pk_'.'live_'.str_repeat('0123456789', 3),
        'made-up.php' => "'".PAYSTACK_FAKE_TEST_KEY."', '".PAYSTACK_FAKE_LIVE_KEY."', 'sk_live_0123456789'",
        'image.png' => "\0PNG ".$realLooking,
    ];

    try {
        foreach ($files as $name => $contents) {
            file_put_contents($folder.DIRECTORY_SEPARATOR.$name, $contents);
        }

        expect(filesWithGatewayKeys($folder, [...array_keys($files), 'missing.txt']))->toBe(['config.php', 'notes.md']);
    } finally {
        array_map(unlink(...), glob($folder.DIRECTORY_SEPARATOR.'*') ?: []);
        rmdir($folder);
    }
});
