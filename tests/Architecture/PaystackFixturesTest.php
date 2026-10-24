<?php

declare(strict_types=1);

/*
 * Paystack's answers recorded in test mode (tests/Fixtures/Paystack) are
 * committed only with every personal value replaced by an obviously fake
 * one: IP addresses from the documentation ranges, everything else saying
 * "fake". Nothing shaped like a key, however short, is ever in one.
 */

it('finds no key and no real-looking personal value in any Paystack fixture', function (): void {
    $folder = dirname(__DIR__).DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'Paystack';

    expect(glob($folder.DIRECTORY_SEPARATOR.'*.json'))->not->toBeEmpty()
        ->and(paystackFixtureProblems($folder))->toBe([]);
});

it('catches a key, a real-looking value in any personal field however deep, and a file that is not JSON', function (): void {
    $folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'paystack-fixture-scan-'.bin2hex(random_bytes(4));
    mkdir($folder);
    $files = [
        'a-clean.json' => json_encode(['data' => ['ip_address' => '198.51.100.7', 'email' => 'fake-buyer@example.test', 'customer' => ['customer_code' => null], 'signature' => '']]),
        'b-key.json' => json_encode(['note' => 'sk_'.'live_x']),
        'c-personal.json' => json_encode(['data' => [
            'ip_address' => '102.89.1.2',
            'refunded_by' => 'someone',
            'initiated_by' => 'someone',
            'customer' => ['email' => 'ada@gmail.com', 'customer_code' => 'CUS_abc'],
            'authorization' => ['authorization_code' => 'AUTH_abc', 'signature' => 'SIG_abc'],
            'amount' => 500000,
        ]]),
        'd-number.json' => json_encode(['customer_code' => 12345]),
        'e-broken.json' => '{"status":',
    ];

    try {
        foreach ($files as $name => $contents) {
            file_put_contents($folder.DIRECTORY_SEPARATOR.$name, $contents);
        }

        expect(paystackFixtureProblems($folder))->toBe([
            'b-key.json: something shaped like a Paystack key',
            'c-personal.json: ip_address looks real',
            'c-personal.json: refunded_by looks real',
            'c-personal.json: initiated_by looks real',
            'c-personal.json: email looks real',
            'c-personal.json: customer_code looks real',
            'c-personal.json: authorization_code looks real',
            'c-personal.json: signature looks real',
            'd-number.json: customer_code looks real',
            'e-broken.json: not JSON',
        ]);
    } finally {
        array_map(unlink(...), glob($folder.DIRECTORY_SEPARATOR.'*') ?: []);
        rmdir($folder);
    }
});
