<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Support\TruncatesCentralDatabase;

/*
 * Step 19a: a page's body is HTML cleaned down to what is safe to show
 * shoppers, when saved and again when served. Links go to https addresses,
 * relative paths, mailto: and tel: only; images come only from where the
 * store's own images are served. A page saved under looser rules is cleaned
 * on the way out.
 */
uses(TruncatesCentralDatabase::class);

beforeEach(function (): void {
    seedWorld();
    $this->featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $this->featureSource);
    ['owner' => $this->owner] = storefrontStore();
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * The body a page keeps when staff save this HTML.
 */
$savedBody = static fn (string $html): string => addStorefrontPage(['body' => ['en' => $html]])['body']['en'];

it('keeps text formatting, headings, lists, quotes and tables', function () use ($savedBody): void {
    $html = '<h2>Care</h2><p>Wash <strong>cold</strong>, <em>dry flat</em>.</p><ul><li>Linen</li></ul><blockquote>Soft.</blockquote><table><tbody><tr><td>S</td></tr></tbody></table>';

    expect($savedBody($html))->toBe($html);
});

it('removes scripts, styles, frames, forms and event handlers', function () use ($savedBody): void {
    $body = $savedBody('<p onclick="steal()">Hi</p><script>alert(1)</script><style>p{}</style><iframe src="https://evil.example"></iframe><form><input></form><img src="x" onerror="steal()">');

    expect($body)->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('style')
        ->not->toContain('iframe')
        ->not->toContain('form')
        ->toContain('<p>Hi</p>');
});

it('keeps links to https addresses, relative paths, mailto: and tel:, and drops every other kind', function () use ($savedBody): void {
    $body = $savedBody(
        '<a href="https://example.com">web</a> <a href="/pages/sizes">sizes</a> <a href="mailto:hello@example.com">mail</a> <a href="tel:+2348012345678">call</a> '
        .'<a href="javascript:alert(1)">bad</a> <a href="http://example.com">plain</a> <a href="data:text/html,x">data</a>',
    );

    expect($body)->toContain('href="https://example.com"')
        ->toContain('href="/pages/sizes"')
        // Attribute values come back with "@" and "+" as character references, which browsers read as the same address.
        ->toContain('href="mailto:hello&#64;example.com"')
        ->toContain('href="tel:&#43;2348012345678"')
        ->not->toContain('javascript:')
        ->not->toContain('http://example.com')
        ->not->toContain('data:')
        // Links never let the opened page reach back.
        ->toContain('rel="noopener noreferrer"');
});

it('keeps the store\'s own images and refuses images from any other site', function () use ($savedBody): void {
    $own = storefrontImageAddress();

    $body = $savedBody('<img src="'.$own.'" alt="Hero"><img src="https://evil.example/track.gif" alt="Pixel"><img src="/relative.png" alt="Relative"><img src="data:image/png;base64,AAAA" alt="Inline">');

    expect($body)->toContain('src="'.$own.'"')
        ->not->toContain('evil.example')
        ->not->toContain('relative.png')
        ->not->toContain('data:image');
});

it('keeps the content of wrappers it removes, so no text is lost', function () use ($savedBody): void {
    expect($savedBody('<div><span>Hand made</span> in Lagos</div>'))->toBe('Hand made in Lagos');
});

it('cleans a page saved under looser rules on its way to shoppers', function (): void {
    $page = addStorefrontPage(['body' => ['en' => '<p>Safe</p>']], publish: true);

    // As if saved before today's rules: written straight to the database.
    $looser = '<p>Safe</p><script>steal()</script><img src="https://evil.example/x.gif"><a href="javascript:alert(1)">x</a>';
    $this->store->run(static fn () => DB::table('pages')->where('public_id', $page['id'])->update(['body' => json_encode(['en' => $looser])]));

    $served = shopperRequest('pages/about-us')->assertOk()->json('data.body');

    expect($served)->toContain('<p>Safe</p>')
        ->not->toContain('script')
        ->not->toContain('evil.example')
        ->not->toContain('javascript:');
});
