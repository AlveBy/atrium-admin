<?php

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Support\ContentLanguages;
use Spatie\Translatable\HasTranslations;

/**
 * Columns listed in the model's `$translatable` hold one value per content
 * language, as JSON: {"en": "Hello", "de": "Hallo"}. Works on MySQL,
 * PostgreSQL and SQLite.
 *
 *     use Translatable;
 *
 *     protected array $translatable = ['name', 'code', 'detail_text'];
 *
 * Reading `$model->name` gives the current locale's text, and the default
 * content language's text where a translation is missing. The admin form
 * shows a language switch for these fields by itself.
 */
trait Translatable
{
    use HasTranslations;

    public function getFallbackLocale(): string
    {
        return app(ContentLanguages::class)->default();
    }
}
