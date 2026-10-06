<?php

declare(strict_types=1);

use App\Shared\Money\CurrencyMismatchException;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

/**
 * @property Money|null $price
 * @property Money|null $subtotal
 * @property Money|null $total
 */
final class PricedSample extends Model
{
    public $timestamps = false;

    protected $table = 'priced_samples';

    protected $fillable = ['price', 'subtotal', 'total'];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'subtotal' => MoneyCast::class.':currency',
            'total' => MoneyCast::class.':currency',
        ];
    }
}

beforeEach(function (): void {
    Schema::create('priced_samples', static function (Blueprint $table): void {
        $table->id();
        $table->bigInteger('price_amount')->nullable();
        $table->char('price_currency', 3)->nullable();
        $table->bigInteger('subtotal_amount')->nullable();
        $table->bigInteger('total_amount')->nullable();
        $table->char('currency', 3)->nullable();
    });
});

it('stores an amount as minor units and a currency code, and reads the same amount back', function (): void {
    $sample = PricedSample::query()->create(['price' => Money::ofMinor(1250, 'KWD')]);

    $this->assertDatabaseHas('priced_samples', ['id' => $sample->id, 'price_amount' => 1250, 'price_currency' => 'KWD']);
    expect($sample->fresh()->price->isEqualTo(Money::ofMinor(1250, 'KWD')))->toBeTrue();
});

it('stores and reads an empty amount as null', function (): void {
    $sample = PricedSample::query()->create(['price' => null]);

    $this->assertDatabaseHas('priced_samples', ['id' => $sample->id, 'price_amount' => null, 'price_currency' => null]);
    expect($sample->fresh()->price)->toBeNull();
});

it('lets several amounts share one currency column', function (): void {
    $sample = PricedSample::query()->create([
        'subtotal' => Money::ofMinor(9000, 'NGN'),
        'total' => Money::ofMinor(9675, 'NGN'),
    ]);

    $this->assertDatabaseHas('priced_samples', ['id' => $sample->id, 'subtotal_amount' => 9000, 'total_amount' => 9675, 'currency' => 'NGN']);
});

it('refuses to switch a shared currency column through one amount, which would change the others\' currency', function (): void {
    $sample = PricedSample::query()->create(['subtotal' => Money::ofMinor(9000, 'NGN')]);

    expect(fn () => $sample->total = Money::ofMinor(60, 'USD'))->toThrow(CurrencyMismatchException::class);

    $this->assertDatabaseHas('priced_samples', ['id' => $sample->id, 'currency' => 'NGN', 'total_amount' => null]);
});

it('refuses values that are not Money, such as floats', function (mixed $notMoney): void {
    $sample = new PricedSample;

    expect(fn () => $sample->price = $notMoney)->toThrow(InvalidArgumentException::class);
})->with([19.99, 1999, '19.99']);

it('fails loudly when a stored amount has no currency', function (): void {
    $id = DB::table('priced_samples')->insertGetId(['price_amount' => 1999, 'price_currency' => null]);

    expect(fn () => PricedSample::query()->findOrFail($id)->price)->toThrow(UnexpectedValueException::class);
});
