<?php

declare(strict_types=1);

/*
| Validation messages for text sent in several languages, such as a product
| name keyed by language: {"en": "Running shoes", "fr": "Chaussures de course"}.
*/

return [

    'not_translations' => 'The :attribute must be an object of texts keyed by language, such as {"en": "…"}.',
    'locale_not_enabled' => 'The store does not publish in ":locale". Enable the language in the store settings first.',
    'default_required' => 'The :attribute needs a text in the store\'s default language (:locale).',
    'empty' => 'The :attribute in ":locale" cannot be empty. Send null to remove that translation.',
    'too_long' => 'The :attribute in ":locale" may not be longer than :max characters.',

];
