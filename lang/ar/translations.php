<?php

declare(strict_types=1);

return [
    'not_translations' => 'يجب أن يكون :attribute كائنًا من النصوص التي تحتوي على مفاتيح باللغة، مثل {"en": "..."}.',
    'locale_not_enabled' => 'لا ينشر المتجر في ":locale". قم بتمكين اللغة في إعدادات المتجر أولاً.',
    'default_required' => 'يحتاج :attribute إلى نص باللغة الافتراضية للمتجر (:locale).',
    'empty' => 'لا يمكن أن يكون :attribute في ":locale" فارغًا. أرسل null لإزالة تلك الترجمة.',
    'too_long' => 'لا يجوز أن يكون :attribute في ":locale" أطول من :max حرفًا.',
];
