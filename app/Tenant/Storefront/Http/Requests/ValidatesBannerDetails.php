<?php

declare(strict_types=1);

namespace App\Tenant\Storefront\Http\Requests;

use App\Tenant\Settings\Actions\FindStoreSettings;
use App\Tenant\Settings\Concerns\ValidatesStoreTranslations;
use App\Tenant\Storefront\Data\BannerData;
use App\Tenant\Storefront\Enums\BannerKind;
use App\Tenant\Storefront\Models\Banner;
use App\Tenant\Storefront\Models\MenuItem;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The rules for a banner's details, for adding and changing banners.
 */
trait ValidatesBannerDetails
{
    use ValidatesStoreTranslations;

    /** The translated fields a banner has; the title can't be removed. */
    private const array TRANSLATED = ['title', 'body', 'link_label'];

    /**
     * @return array<string, mixed>
     */
    protected function bannerRules(bool $creating): array
    {
        $kind = $this->bannerKind();
        $positions = config()->array('storefront.banner_positions');

        $kindRule = $creating ? [
            /** "image" for an image placed somewhere on the storefront, or "announcement" for a message in the bar along the top. It never changes. */
            'kind' => ['required', 'string', Rule::enum(BannerKind::class)],
        ] : [];

        return [
            ...$kindRule,
            /** Where an image banner is placed: one of "home_hero", "home_secondary" or "catalog_top" (the store's theme decides how each looks). Not sent for an announcement. */
            'position' => $kind === BannerKind::Announcement
                ? ['prohibited']
                : [$creating ? 'required_if:kind,image' : 'sometimes', 'string', Rule::in($positions)],
            /**
             * By language: an image banner's headline (also its image's description), or an announcement's message; up to 160 characters each. Must include the store's default language when the banner is added.
             *
             * @var array<string, string|null>
             */
            'title' => [$creating ? 'required' : 'sometimes', 'array', $this->storeTranslatedText(160, requiresDefault: $creating)],
            /**
             * By language: a line of text under the headline; up to 500 characters each.
             *
             * @var array<string, string|null>|null
             */
            'body' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(500)],
            /** Where it leads: an https address, a mailto: or tel: link, or a path on the storefront such as "/sale". Null removes the link and its button text. */
            'link_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:'.MenuItem::URL_PATTERN],
            /**
             * By language: the text of its button, up to 40 characters each; only with a link.
             *
             * @var array<string, string|null>|null
             */
            'link_label' => ['sometimes', 'nullable', 'array', $this->storeTranslatedText(40)],
            /** Its text colour as "#" and six hexadecimal digits, such as "#ffffff"; null for the theme's. */
            'text_color' => ['sometimes', 'nullable', 'string', 'regex:'.Banner::COLOR_PATTERN],
            /** Its background colour, such as "#1a1a1a"; null for the theme's. */
            'background_color' => ['sometimes', 'nullable', 'string', 'regex:'.Banner::COLOR_PATTERN],
            /** When it starts being shown, such as "2026-11-27T09:00"; without an offset, in the store's timezone. Null to show it at once. */
            'starts_at' => ['sometimes', 'nullable', 'date'],
            /** When it stops being shown (not included); without an offset, in the store's timezone. Null to keep showing it. */
            'ends_at' => ['sometimes', 'nullable', 'date'],
            /** Whether it is switched on; true when the banner is added without it. */
            'is_active' => ['sometimes', 'boolean:strict'],
            /** Its order among the banners in its place, or among announcements, lowest first; 0 when left out. */
            'sort_order' => ['sometimes', 'integer:strict', 'min:0', 'max:10000'],
        ];
    }

    /**
     * A banner's kind never changes; the end must come after the start, and button text needs a link, counting whichever of them isn't being changed.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->routeBanner() !== null && $this->has('kind')) {
                    $validator->errors()->add('kind', __('validation.prohibited', ['attribute' => 'kind']));
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['starts_at', 'ends_at'])) {
                    return;
                }

                $banner = $this->routeBanner();
                $startsAt = $this->has('starts_at') ? $this->storeTime('starts_at') : $banner?->starts_at;
                $endsAt = $this->has('ends_at') ? $this->storeTime('ends_at') : $banner?->ends_at;

                if ($startsAt !== null && $endsAt !== null && ! $endsAt->greaterThan($startsAt)) {
                    $validator->errors()->add('ends_at', __('validation.after', ['attribute' => 'ends at', 'date' => 'starts at']));
                }
            },
            function (Validator $validator): void {
                $linkUrl = $this->has('link_url') ? $this->input('link_url') : $this->routeBanner()?->link_url;

                if ($linkUrl === null && is_array($this->input('link_label')) && $this->input('link_label') !== []) {
                    $validator->errors()->add('link_label', __('validation.required_with', ['attribute' => 'link label', 'values' => 'link url']));
                }
            },
        ];
    }

    public function bannerData(): BannerData
    {
        $attributes = [];

        if ($this->has('position')) {
            $attributes['position'] = $this->validatedString('position');
        }

        foreach (['link_url', 'text_color', 'background_color'] as $key) {
            if ($this->has($key)) {
                $value = $this->validatedString($key);
                $attributes[$key] = $key === 'link_url' || $value === null ? $value : strtolower($value);
            }
        }

        foreach (['starts_at', 'ends_at'] as $key) {
            if ($this->has($key)) {
                $attributes[$key] = $this->storeTime($key);
            }
        }

        if ($this->has('is_active')) {
            $attributes['is_active'] = $this->validated('is_active') === true;
        }

        if ($this->has('sort_order')) {
            $sortOrder = $this->validated('sort_order');
            $attributes['sort_order'] = is_int($sortOrder) ? $sortOrder : 0;
        }

        $translations = [];
        $removes = [];

        foreach (self::TRANSLATED as $key) {
            $texts = $this->validatedTranslations($key);

            if ($texts !== null) {
                $translations[$key] = $texts;
            } elseif ($key !== 'title' && $this->has($key)) {
                $removes[] = $key;
            }
        }

        $kind = $this->validatedString('kind');

        return new BannerData(
            kind: $kind === null ? null : BannerKind::from($kind),
            attributes: $attributes,
            translations: $translations,
            removes: $removes,
        );
    }

    /**
     * A time sent, read in the store's timezone when it has no offset, in UTC; null when sent as null.
     */
    private function storeTime(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, app(FindStoreSettings::class)->handle()->timezone)->utc() : null;
    }

    private function validatedString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }

    private function bannerKind(): ?BannerKind
    {
        $banner = $this->routeBanner();

        if ($banner !== null) {
            return $banner->kind;
        }

        return is_string($this->input('kind')) ? BannerKind::tryFrom($this->string('kind')->value()) : null;
    }

    private function routeBanner(): ?Banner
    {
        $banner = $this->route('banner');

        return $banner instanceof Banner ? $banner : null;
    }
}
