<?php

declare(strict_types=1);

use App\Shared\Privacy\Contracts\PersonalDataHandler;
use App\Shared\Privacy\DataSubject;
use App\Shared\Privacy\PersonalDataRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

final class SampleAddressesHandler implements PersonalDataHandler
{
    public function export(DataSubject $subject): iterable
    {
        return DB::table('sample_addresses')->where('owner', $subject->publicId)->get(['line'])
            ->map(static fn (object $address): array => (array) $address)->all();
    }

    public function erase(DataSubject $subject): void
    {
        DB::table('sample_addresses')->where('owner', $subject->publicId)->delete();
    }
}

final class SampleWishlistHandler implements PersonalDataHandler
{
    public function export(DataSubject $subject): iterable
    {
        yield ['product' => 'Blue shoes'];
    }

    public function erase(DataSubject $subject): void {}
}

final class FailingSampleHandler implements PersonalDataHandler
{
    public function export(DataSubject $subject): iterable
    {
        return [];
    }

    public function erase(DataSubject $subject): void
    {
        throw new RuntimeException('Erase failed halfway');
    }
}

beforeEach(function (): void {
    Schema::create('sample_addresses', static function (Blueprint $table): void {
        $table->id();
        $table->string('owner');
        $table->string('line');
    });

    DB::table('sample_addresses')->insert([
        ['owner' => 'customer-ada', 'line' => '1 Marina Road'],
        ['owner' => 'customer-grace', 'line' => '9 Harbour Street'],
    ]);

    $this->registry = app(PersonalDataRegistry::class);
    $this->ada = new DataSubject('sample_customer', 'customer-ada');
});

it('exports every registered section for the person', function (): void {
    $this->registry->register('sample_customer', 'addresses', SampleAddressesHandler::class);
    $this->registry->register('sample_customer', 'wishlist', SampleWishlistHandler::class);

    expect($this->registry->export($this->ada))->toBe([
        'addresses' => [['line' => '1 Marina Road']],
        'wishlist' => [['product' => 'Blue shoes']],
    ]);
});

it('does not include sections registered for a different kind of person', function (): void {
    $this->registry->register('sample_driver', 'locations', SampleWishlistHandler::class);

    expect($this->registry->export($this->ada))->toBe([]);
});

it('erases the person from every registered section without touching other people', function (): void {
    $this->registry->register('sample_customer', 'addresses', SampleAddressesHandler::class);
    $this->registry->register('sample_customer', 'wishlist', SampleWishlistHandler::class);

    $erasedSections = $this->registry->erase($this->ada);

    expect($erasedSections)->toBe(['addresses', 'wishlist'])
        ->and(DB::table('sample_addresses')->pluck('owner')->all())->toBe(['customer-grace']);
});

it('undoes the whole erase when one section fails', function (): void {
    $this->registry->register('sample_customer', 'addresses', SampleAddressesHandler::class);
    $this->registry->register('sample_customer', 'failing', FailingSampleHandler::class);

    expect(fn () => $this->registry->erase($this->ada))->toThrow(RuntimeException::class, 'Erase failed halfway');

    expect(DB::table('sample_addresses')->where('owner', 'customer-ada')->exists())->toBeTrue();
});

it('refuses to register the same section twice for the same kind of person', function (): void {
    $this->registry->register('sample_customer', 'addresses', SampleAddressesHandler::class);

    expect(fn () => $this->registry->register('sample_customer', 'addresses', SampleWishlistHandler::class))
        ->toThrow(LogicException::class);
});
