<?php

declare(strict_types=1);

use App\Shared\Money\Decimal;
use App\Shared\Money\DecimalCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

/**
 * @property Decimal|null $exchange_rate
 */
final class RatedSample extends Model
{
    public $timestamps = false;

    protected $table = 'rated_samples';

    protected $fillable = ['exchange_rate'];

    protected function casts(): array
    {
        return [
            'exchange_rate' => DecimalCast::class.':12',
        ];
    }
}

beforeEach(function (): void {
    Schema::create('rated_samples', static function (Blueprint $table): void {
        $table->id();
        $table->decimal('exchange_rate', 24, 12)->nullable();
    });
});

it('stores a decimal at the column\'s scale and reads the exact value back', function (): void {
    $sample = RatedSample::query()->create(['exchange_rate' => Decimal::of('1550.25')]);

    $this->assertDatabaseHas('rated_samples', ['id' => $sample->id, 'exchange_rate' => '1550.250000000000']);
    expect($sample->fresh()->exchange_rate->isEqualTo(Decimal::of('1550.25')))->toBeTrue();
});

it('refuses a value with more decimal places than the column holds instead of silently rounding it', function (): void {
    $sample = new RatedSample;

    expect(fn () => $sample->exchange_rate = Decimal::of('0.0000000000005'))->toThrow(InvalidArgumentException::class);
});

it('refuses values that are not Decimal, such as floats', function (): void {
    $sample = new RatedSample;

    expect(fn () => $sample->exchange_rate = 1.5)->toThrow(InvalidArgumentException::class);
});

it('requires the column scale to be given', function (): void {
    expect(fn (): DecimalCast => new DecimalCast('twelve'))->toThrow(InvalidArgumentException::class);
});
