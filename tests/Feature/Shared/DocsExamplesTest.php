<?php

declare(strict_types=1);

/*
 * The hand-written guides (README.md and docs/) stay true to the code: each
 * cURL example calls a real route, each API route has an example, and every
 * artisan command, class name in backticks and relative link exists. A
 * route renamed or added without updating the guides fails here.
 */

beforeEach(function (): void {
    $this->problems = guideProblems();
});

it('calls a real route in every cURL example', function (): void {
    expect($this->problems['examples'])->toBe([], 'No route for: '.implode(', ', $this->problems['examples']));
});

it('has a cURL example for every API route', function (): void {
    expect($this->problems['routes'])->toBe([], 'Add an example to docs/landlord/api-testing.md or docs/tenant/api-testing.md for: '.implode(', ', $this->problems['routes']));
});

it('names only artisan commands that exist', function (): void {
    expect($this->problems['commands'])->toBe([], 'No such command: '.implode(', ', $this->problems['commands']));
});

it('names only classes that exist in backticks', function (): void {
    expect($this->problems['classes'])->toBe([], 'No such class: '.implode(', ', $this->problems['classes']));
});

it('links only to files that exist', function (): void {
    expect($this->problems['links'])->toBe([], 'Broken links: '.implode(', ', $this->problems['links']));
});
