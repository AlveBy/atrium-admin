<?php

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

trait HasAdminSeo
{
    /**
     * Get SEO meta relationship
     */
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * Boot the trait
     */
    public static function bootHasAdminSeo(): void
    {
        static::deleting(function ($model) {
            $model->seoMeta()->delete();
        });
    }

    /**
     * Get SEO title with template support
     */
    public function getSeoTitle(): string
    {
        if ($this->seoMeta && $this->seoMeta->title) {
            return $this->processSeoTemplate($this->seoMeta->title);
        }

        return $this->getDefaultSeoTitle();
    }

    /**
     * Get default SEO title
     */
    protected function getDefaultSeoTitle(): string
    {
        $template = $this->getSeoTitleTemplate();

        if ($template) {
            return $this->processSeoTemplate($template);
        }

        return $this->name ?? $this->title ?? '';
    }

    /**
     * Get SEO title template
     */
    protected function getSeoTitleTemplate(): ?string
    {
        return config('atrium.seo.templates.'.static::class.'.title');
    }

    /**
     * Get SEO description with template support
     */
    public function getSeoDescription(): string
    {
        if ($this->seoMeta && $this->seoMeta->description) {
            return $this->processSeoTemplate($this->seoMeta->description);
        }

        return $this->getDefaultSeoDescription();
    }

    /**
     * Get default SEO description
     */
    /**
     * Описание страницы из текста.
     *
     * Сначала простой текст, потом рез — не наоборот. Раньше было
     * `strip_tags(mb_substr($text, 0, 160))`: если рез приходился на середину
     * тега, strip_tags уносил вместе с обрывком и весь хвост, и у «Санатория
     * Боржомское ущелье» от описания оставалось 55 знаков.
     *
     * Предел 300, а не 160. Полторы сотни — это ширина сниппета в выдаче, а не
     * ограничение поисковика: за длинное описание не штрафуют, его просто
     * показывают не целиком, зато по другому запросу Google может взять из
     * него другой кусок. Резать по этой границе значило выбрасывать текст
     * заранее и без пользы — и заметно: «мы помогаем вам делать визы
     * (формируем…» (замечание заказчика 02.09.2026). 300 отсекает уже не
     * сниппет, а простыню в полстраницы.
     */
    private static function excerpt(string $html, int $limit = 300): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);

        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }

        // Режем по концу предложения, если он попадает в разумную часть
        // лимита: обрывок «…визы (формируем…» читается как поломка, а целая
        // фраза — как описание. Половина лимита — чтобы не отдать вместо
        // описания одно короткое предложение из пяти слов.
        if (preg_match('/^(.*[.!?])\s/us', mb_substr($text, 0, $limit), $m)
            && mb_strlen($m[1]) >= intdiv($limit, 2)) {
            return $m[1];
        }

        return Str::limit($text, $limit - 1, '…', preserveWords: true);
    }

    protected function getDefaultSeoDescription(): string
    {
        $template = $this->getSeoDescriptionTemplate();

        if ($template) {
            return $this->processSeoTemplate($template);
        }

        if (isset($this->preview_text) && $this->preview_text) {
            return self::excerpt((string) $this->preview_text);
        }

        // У разделов анонса нет: их собственный текст лежит в `description`
        // и выводится на самой странице. Без этой ветки 52 раздела уходили в
        // выдачу вообще без описания, и Google сочинял сниппет сам.
        if (isset($this->description) && $this->description) {
            return self::excerpt((string) $this->description);
        }

        return '';
    }

    /**
     * Get SEO description template
     */
    protected function getSeoDescriptionTemplate(): ?string
    {
        return config('atrium.seo.templates.'.static::class.'.description');
    }

    /**
     * Get SEO keywords
     */
    public function getSeoKeywords(): ?string
    {
        return $this->seoMeta?->keywords;
    }

    /**
     * Get Open Graph image URL
     */
    public function getOgImage(): ?string
    {
        if ($this->seoMeta && $this->seoMeta->og_image) {
            return asset('storage/'.$this->seoMeta->og_image);
        }

        // Fallback to preview_picture or detail_picture
        if (isset($this->preview_picture) && $this->preview_picture) {
            return asset('storage/'.$this->preview_picture);
        }

        if (isset($this->detail_picture) && $this->detail_picture) {
            return asset('storage/'.$this->detail_picture);
        }

        return null;
    }

    /**
     * Process SEO template with model data
     */
    protected function processSeoTemplate(string $template): string
    {
        // Replace {field} placeholders with model values
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            $field = $matches[1];

            if (isset($this->$field)) {
                return strip_tags((string) $this->$field);
            }

            // Check relations
            if (method_exists($this, $field)) {
                $related = $this->$field;
                if ($related && isset($related->name)) {
                    return $related->name;
                }
            }

            return $matches[0];
        }, $template);
    }

    /**
     * Update SEO meta
     */
    public function updateSeoMeta(array $data): SeoMeta
    {
        return $this->seoMeta()->updateOrCreate(
            ['seoable_type' => static::class, 'seoable_id' => $this->id],
            $data
        );
    }

    /**
     * Имя поля формы => колонка seo_meta.
     *
     * Считается из getSeoAdminFields(), а не пишется вторым списком: раньше
     * список полей и карта колонок жили порознь, вкладка выросла с 4 полей до
     * 12, а копия карты в Dusk-тесте осталась старой — и восемь полей на 31
     * ресурсе «не сохранялись», хотя сохранялись всегда. Один источник —
     * добавили поле в getSeoAdminFields(), и его подхватывают и контроллер,
     * и тесты.
     *
     * @return array<string,string>
     */
    public static function seoFieldMap(): array
    {
        $map = [];

        foreach (static::getSeoAdminFields() as $field) {
            $name = $field->getName();

            if (! str_starts_with($name, 'seo_')) {
                continue;
            }

            $map[$name] = substr($name, 4);
        }

        return $map;
    }

    /**
     * Поля вкладки SEO. Набор и порядок повторяют вкладку SEO карточки
     * элемента в Битриксе (META TITLE / KEYWORDS / DESCRIPTION, «Заголовок
     * элемента», блоки настроек картинок анонса и детальных, теги) — именно
     * их заполняли на старом сайте: 206 META TITLE, 195 DESCRIPTION,
     * 172 KEYWORDS, 137 ALT картинок анонса.
     *
     * Многострочные textarea у ALT/TITLE не случайность: в Битриксе эти
     * шаблоны заполнялись построчно и доходили до 1156 символов.
     */
    public static function getSeoAdminFields(): array
    {
        return [
            TextField::make('seo_title')
                ->label('META TITLE')
                ->maxLength(255)
                ->hint('Заголовок вкладки браузера. Оставьте пустым — подставится название'),

            TextareaField::make('seo_keywords')
                ->label('META KEYWORDS')
                ->rows(3)
                ->hint('Ключевые слова через запятую'),

            TextareaField::make('seo_description')
                ->label('META DESCRIPTION')
                ->rows(3)
                ->hint('Описание для поисковой выдачи. Оставьте пустым — возьмётся из анонса'),

            TextField::make('seo_page_title')
                ->label('Заголовок элемента (H1)')
                ->maxLength(255)
                ->hint('Заголовок на самой странице. Оставьте пустым — подставится название'),

            TextareaField::make('seo_preview_picture_alt')
                ->label('Картинка анонса: ALT')
                ->rows(2),

            TextareaField::make('seo_preview_picture_title')
                ->label('Картинка анонса: TITLE')
                ->rows(2),

            TextareaField::make('seo_preview_picture_file_name')
                ->label('Картинка анонса: имя файла')
                ->rows(2),

            TextareaField::make('seo_detail_picture_alt')
                ->label('Детальная картинка: ALT')
                ->rows(2),

            TextareaField::make('seo_detail_picture_title')
                ->label('Детальная картинка: TITLE')
                ->rows(2),

            TextareaField::make('seo_detail_picture_file_name')
                ->label('Детальная картинка: имя файла')
                ->rows(2),

            TextField::make('seo_tags')
                ->label('Теги')
                ->maxLength(500)
                ->hint('Слова или словосочетания через запятую'),

            ImageField::make('seo_og_image')
                ->label('OG изображение')
                ->hint('Картинка для превью при репосте в соцсети'),
        ];
    }
}
