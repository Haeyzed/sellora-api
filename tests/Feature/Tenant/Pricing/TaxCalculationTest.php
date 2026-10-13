<?php

declare(strict_types=1);

use App\Shared\Features\Contracts\FeatureSource;
use App\Shared\Features\FeatureDefinition;
use App\Shared\Features\FeatureKind;
use App\Shared\Features\FeatureRegistry;
use App\Shared\Features\Features;
use App\Shared\Money\Decimal;
use App\Shared\Money\Money;
use App\Shared\Money\TaxMode;
use App\Tenant\Pricing\Actions\CalculateTax;
use App\Tenant\Pricing\Actions\CreateTaxRate;
use App\Tenant\Pricing\Data\TaxableLine;
use App\Tenant\Pricing\Data\TaxAddress;
use App\Tenant\Pricing\Data\TaxClassData;
use App\Tenant\Pricing\Data\TaxRequest;
use App\Tenant\Pricing\Enums\TaxLineKind;
use App\Tenant\Pricing\Enums\TaxOutcome;
use App\Tenant\Pricing\Models\TaxClass;
use App\Tenant\Pricing\TaxCalculatorRegistry;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Fixtures\Features\FakeFeatureSource;
use Tests\Fixtures\Pricing\ExemptLinesNamed;
use Tests\Fixtures\Pricing\ForgetfulTaxPlugIn;
use Tests\Fixtures\Pricing\RecordingTaxPlugIn;

/*
 * Section 3.3: tax is calculated from the request alone, by a country
 * plug-in while its module or integration is Enabled for the store, else by
 * the store's own rates. The most specific rate wins, no rate means no tax
 * (marked so staff can see it), exempt lines are settled by core before any
 * calculator is asked.
 */
uses(DatabaseTruncation::class);

beforeEach(function (): void {
    seedWorld();
    $this->store = createStore('tax-store');
    RecordingTaxPlugIn::$askedAbout = [];
});

afterEach(function (): void {
    deleteAllStores();
});

/**
 * The store's class with this name, as a calculator sees it.
 */
function taxClassNamed(string $name): TaxClassData
{
    return TaxClassData::of(TaxClass::query()->where('name', $name)->sole());
}

function addTaxRate(string $className, string $countryCode, ?string $stateCode, string $rate, string $name = 'VAT'): void
{
    app(CreateTaxRate::class)->handle(TaxClass::query()->where('name', $className)->sole(), $countryCode, $stateCode, $name, Decimal::of($rate));
}

/**
 * @param  array<string, array{0: int, 1: string}>  $lines  Minor amount and class name, by line key.
 */
function taxFor(array $lines, TaxAddress $destination, TaxMode $taxMode = TaxMode::Exclusive, string $currency = 'NGN'): App\Tenant\Pricing\Data\TaxResult
{
    $taxableLines = [];

    foreach ($lines as $key => [$amount, $className]) {
        $taxableLines[] = new TaxableLine((string) $key, TaxLineKind::Goods, Money::ofMinor($amount, $currency), taxClassNamed($className));
    }

    return app(CalculateTax::class)->handle(new TaxRequest($currency, $taxMode, new TaxAddress('NG', 'LA'), $destination, $taxableLines));
}

it('uses the destination state\'s rate over its country\'s, the country\'s elsewhere, and charges nothing without either', function (): void {
    $this->store->run(static function (): void {
        addTaxRate('Standard', 'US', null, '5', 'Federal');
        addTaxRate('Standard', 'US', 'CA', '7.25', 'California sales tax');
        addTaxRate('Reduced', 'US', null, '2');

        $california = taxFor(['shirt' => [10000, 'Standard'], 'book' => [10000, 'Reduced']], new TaxAddress('US', 'CA'));
        expect($california->line('shirt')->tax->minorAmount())->toBe(725)
            ->and($california->line('shirt')->taxes[0]->name)->toBe('California sales tax')
            ->and($california->line('shirt')->taxes[0]->stateCode)->toBe('CA')
            // No Reduced rate for California: the country-wide one applies; they never add up.
            ->and($california->line('book')->tax->minorAmount())->toBe(200)
            ->and($california->calculatedBy)->toBe('rate_table');

        expect(taxFor(['shirt' => [10000, 'Standard']], new TaxAddress('US', 'NY'))->line('shirt')->tax->minorAmount())->toBe(500);

        $nowhere = taxFor(['shirt' => [10000, 'Standard']], new TaxAddress('DE'));
        expect($nowhere->line('shirt')->outcome)->toBe(TaxOutcome::NoRate)
            ->and($nowhere->totalTax()->isZero())->toBeTrue()
            ->and($nowhere->hasMissingRates())->toBeTrue();
    });
});

it('tells zero-rated from exempt, though neither is charged', function (): void {
    $this->store->run(static function (): void {
        addTaxRate('Zero-rated', 'NG', null, '0');

        $result = taxFor(['bread' => [5000, 'Zero-rated'], 'insurance' => [5000, 'Exempt']], new TaxAddress('NG', 'LA'), TaxMode::Inclusive);

        expect($result->line('bread')->outcome)->toBe(TaxOutcome::ZeroRated)
            ->and($result->line('bread')->taxes)->toHaveCount(1)
            ->and($result->line('insurance')->outcome)->toBe(TaxOutcome::Exempt)
            ->and($result->line('insurance')->taxes)->toBe([])
            ->and($result->totalTax()->isZero())->toBeTrue()
            ->and($result->totalGross()->minorAmount())->toBe(10000);
    });
});

it('keeps the shown price of a tax-inclusive store fixed, whatever the destination\'s rate', function (): void {
    $this->store->run(static function (): void {
        addTaxRate('Standard', 'NG', null, '7.5');
        addTaxRate('Standard', 'DE', null, '19');

        $home = taxFor(['shirt' => [10750, 'Standard']], new TaxAddress('NG'), TaxMode::Inclusive);
        $abroad = taxFor(['shirt' => [10750, 'Standard']], new TaxAddress('DE'), TaxMode::Inclusive);

        expect([$home->line('shirt')->gross->minorAmount(), $home->line('shirt')->tax->minorAmount()])->toBe([10750, 750])
            // 10750 × 19 / 119 = 1716.39 → 1716: the merchant's share changes, not the customer's price.
            ->and([$abroad->line('shirt')->gross->minorAmount(), $abroad->line('shirt')->tax->minorAmount()])->toBe([10750, 1716]);
    });
});

it('uses a country plug-in only while its module or integration is enabled for the store, and the rate table otherwise', function (): void {
    app(FeatureRegistry::class)->register(new FeatureDefinition(RecordingTaxPlugIn::FEATURE_KEY, FeatureKind::Module));
    app(TaxCalculatorRegistry::class)->register(RecordingTaxPlugIn::FEATURE_KEY, ['NG'], RecordingTaxPlugIn::class);
    $featureSource = new FakeFeatureSource;
    app()->instance(FeatureSource::class, $featureSource);
    $storeId = (string) $this->store->getTenantKey();

    $this->store->run(static function () use ($featureSource, $storeId): void {
        addTaxRate('Standard', 'NG', null, '7.5');
        $lagos = new TaxAddress('NG', 'LA');

        $featureSource->give($storeId, FakeFeatureSource::snapshot(entitled: [RecordingTaxPlugIn::FEATURE_KEY]));
        $enabled = taxFor(['shirt' => [10000, 'Standard']], $lagos);
        expect($enabled->calculatedBy)->toBe(RecordingTaxPlugIn::FEATURE_KEY)
            ->and(array_map(static fn ($applied): string => $applied->name, $enabled->line('shirt')->taxes))->toBe(['CGST', 'SGST'])
            ->and($enabled->line('shirt')->tax->minorAmount())->toBe(1800);

        // Only for the countries it registered for.
        expect(taxFor(['shirt' => [10000, 'Standard']], new TaxAddress('US'))->calculatedBy)->toBe('rate_table');

        foreach (['switched off' => FakeFeatureSource::snapshot(entitled: [RecordingTaxPlugIn::FEATURE_KEY], merchantDisabled: [RecordingTaxPlugIn::FEATURE_KEY]),
            'downgraded' => FakeFeatureSource::snapshot(removed: [RecordingTaxPlugIn::FEATURE_KEY]),
            'never on the plan' => FakeFeatureSource::snapshot()] as $snapshot) {
            $featureSource->give($storeId, $snapshot);
            app(Features::class)->forget($storeId);

            $fallback = taxFor(['shirt' => [10000, 'Standard']], $lagos);
            expect($fallback->calculatedBy)->toBe('rate_table')->and($fallback->line('shirt')->tax->minorAmount())->toBe(750);
        }
    });
});

it('settles exempt lines before any calculator is asked, so plug-ins never see them', function (): void {
    app(FeatureRegistry::class)->register(new FeatureDefinition(RecordingTaxPlugIn::FEATURE_KEY, FeatureKind::Module));
    app(TaxCalculatorRegistry::class)->register(RecordingTaxPlugIn::FEATURE_KEY, ['NG'], RecordingTaxPlugIn::class);
    $featureSource = (new FakeFeatureSource)->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(entitled: [RecordingTaxPlugIn::FEATURE_KEY]));
    app()->instance(FeatureSource::class, $featureSource);
    app()->tag([ExemptLinesNamed::class], CalculateTax::EXEMPTION_RULES_TAG);

    $this->store->run(static function (): void {
        $result = taxFor(['shirt' => [10000, 'Standard'], 'exempt-shirt' => [10000, 'Standard'], 'insurance' => [10000, 'Exempt']], new TaxAddress('NG'));

        expect(RecordingTaxPlugIn::$askedAbout)->toBe([['shirt']])
            ->and(array_keys($result->lines))->toBe(['shirt', 'exempt-shirt', 'insurance'])
            ->and($result->line('exempt-shirt')->outcome)->toBe(TaxOutcome::Exempt)
            ->and($result->line('insurance')->outcome)->toBe(TaxOutcome::Exempt)
            ->and($result->totalTax()->minorAmount())->toBe(1800);

        RecordingTaxPlugIn::$askedAbout = [];
        expect(taxFor(['exempt-only' => [500, 'Exempt']], new TaxAddress('NG'))->totalTax()->isZero())->toBeTrue()
            ->and(RecordingTaxPlugIn::$askedAbout)->toBe([]);
    });
});

it('refuses a plug-in\'s answer about other lines than it was asked', function (): void {
    app(FeatureRegistry::class)->register(new FeatureDefinition('forgetful', FeatureKind::Integration));
    app(TaxCalculatorRegistry::class)->register('forgetful', ['NG'], ForgetfulTaxPlugIn::class);
    app()->instance(FeatureSource::class, (new FakeFeatureSource)->give((string) $this->store->getTenantKey(), FakeFeatureSource::snapshot(entitled: ['forgetful'])));

    $this->store->run(static function (): void {
        expect(static fn () => taxFor(['shirt' => [10000, 'Standard']], new TaxAddress('NG')))->toThrow(LogicException::class);
    });
});

it('works in currencies with no decimals and with three', function (): void {
    $this->store->run(static function (): void {
        addTaxRate('Standard', 'JP', null, '10', 'Consumption tax');
        addTaxRate('Standard', 'KW', null, '5');

        expect(taxFor(['camera' => [19999, 'Standard']], new TaxAddress('JP'), currency: 'JPY')->line('camera')->tax->minorAmount())->toBe(2000)
            ->and(taxFor(['watch' => [12345, 'Standard']], new TaxAddress('KW'), TaxMode::Inclusive, 'KWD')->line('watch')->tax->minorAmount())->toBe(588);
    });
});

it('keeps each store\'s rates to itself', function (): void {
    $other = createStore('other-tax-store');
    $this->store->run(static fn () => addTaxRate('Standard', 'NG', null, '7.5'));

    $other->run(static function (): void {
        expect(taxFor(['shirt' => [10000, 'Standard']], new TaxAddress('NG'))->line('shirt')->outcome)->toBe(TaxOutcome::NoRate);
    });
    $this->store->run(static function (): void {
        expect(taxFor(['shirt' => [10000, 'Standard']], new TaxAddress('NG'))->line('shirt')->tax->minorAmount())->toBe(750);
    });
});
