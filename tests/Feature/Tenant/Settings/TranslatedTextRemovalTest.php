<?php

declare(strict_types=1);

use App\Tenant\Catalog\Models\Product;
use App\Tenant\Storefront\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Symfony\Component\Finder\Finder;

/*
 * Section 3.3: a translated text staff remove is gone in every language. On
 * its own the translations package clears only the current language when a
 * text is set to null, so the store's shared translations concern does it
 * for every model; this walks every model with translated texts, so a model
 * that skips the concern, or one added later, is caught.
 */

it('removes a translated text in every language when it is set to null, on every model that has translated texts', function (): void {
    $translatedModels = [];

    foreach (Finder::create()->files()->in(app_path())->path('Models')->name('*.php') as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (is_subclass_of($class, Model::class) && in_array(HasTranslations::class, class_uses_recursive($class), true)) {
            $translatedModels[] = $class;
        }
    }

    expect($translatedModels)->toContain(Product::class, MenuItem::class);

    foreach ($translatedModels as $class) {
        $model = new $class;

        expect($model->getTranslatableAttributes())->not->toBeEmpty("{$class} lists no translated texts");

        foreach ($model->getTranslatableAttributes() as $field) {
            $model->setTranslations($field, ['en' => 'In English', 'fr' => 'En français']);

            $model->setAttribute($field, null);

            expect($model->getAttributes()[$field])->toBeNull("{$class}::{$field} kept a language when set to null")
                ->and($model->getTranslations($field))->toBe([], "{$class}::{$field} kept a language when set to null");
        }
    }
});
