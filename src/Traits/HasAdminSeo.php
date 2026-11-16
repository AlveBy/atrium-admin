<?php

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Support\MediaUrl;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

trait HasAdminSeo
{
    /**
     * Get SEO meta relationship
     *
     * @return MorphOne<SeoMeta, $this>
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
     * Page description from a text.
     *
     * Plain text first, then the cut — not the other way round. It used to be
     * `strip_tags(mb_substr($text, 0, 160))`: when the cut fell inside a tag,
     * strip_tags took the whole tail with the fragment, and some descriptions
     * shrank to 55 characters.
     *
     * The limit is 300, not 160. 160 is the snippet width in search results,
     * not a search engine limit: a long description is not penalised, it is
     * just shown partially, and for another query Google may take a different
     * piece of it. Cutting at that boundary threw text away up front for
     * nothing — and visibly, ending mid-sentence. 300 no longer trims the
     * snippet, only a half-page wall of text.
     */
    private static function excerpt(string $html, int $limit = 300): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);

        if ($text === '' || mb_strlen($text) <= $limit) {
            return $text;
        }

        // Cut at the end of a sentence if it falls within a reasonable part of
        // the limit: a fragment cut mid-phrase reads as a bug, a whole sentence
        // reads as a description. Half the limit, so that a single five-word
        // sentence is not served as the description.
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

        // Sections have no preview text: their own text lives in `description`
        // and is shown on the page itself. Without this branch sections went to
        // search results with no description at all, and Google made up the
        // snippet itself.
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
            return MediaUrl::to($this->seoMeta->og_image);
        }

        // Fallback to preview_picture or detail_picture. Pictures moved from
        // Bitrix keep their /upload/… path, so not everything is in storage/.
        return MediaUrl::to($this->preview_picture ?? null) ?? MediaUrl::to($this->detail_picture ?? null);
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
     * Form field name => seo_meta column.
     *
     * Derived from getSeoAdminFields() rather than written as a second list:
     * the field list and the column map used to live apart, the tab grew from
     * 4 fields to 12, the copy of the map in a Dusk test stayed old — and eight
     * fields on 31 resources "were not saved", although they always were. One
     * source: add a field to getSeoAdminFields() and both the controller and
     * the tests pick it up.
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
     * Fields of the SEO tab. The set and order repeat the SEO tab of a Bitrix
     * element (META TITLE / KEYWORDS / DESCRIPTION, element heading, preview
     * and detail picture settings, tags) — these are what editors actually
     * filled in on Bitrix sites.
     *
     * Multi-line textareas for ALT/TITLE are no accident: in Bitrix these
     * templates were filled line by line and reached 1156 characters.
     */
    public static function getSeoAdminFields(): array
    {
        return [
            TextField::make('seo_title')
                ->label(__('atrium::resource.seo.title'))
                ->maxLength(255)
                ->hint(__('atrium::resource.seo.title_hint'))
                ->ai('seo_title'),

            TextareaField::make('seo_keywords')
                ->label(__('atrium::resource.seo.keywords'))
                ->rows(3)
                ->hint(__('atrium::resource.seo.keywords_hint'))
                ->ai('seo_keywords'),

            TextareaField::make('seo_description')
                ->label(__('atrium::resource.seo.description'))
                ->rows(3)
                ->hint(__('atrium::resource.seo.description_hint'))
                ->ai('seo_description'),

            TextField::make('seo_page_title')
                ->label(__('atrium::resource.seo.page_title'))
                ->maxLength(255)
                ->hint(__('atrium::resource.seo.page_title_hint')),

            TextareaField::make('seo_preview_picture_alt')
                ->label(__('atrium::resource.seo.preview_picture_alt'))
                ->rows(2)
                ->ai('alt', from: 'preview_picture'),

            TextareaField::make('seo_preview_picture_title')
                ->label(__('atrium::resource.seo.preview_picture_title'))
                ->rows(2),

            TextareaField::make('seo_preview_picture_file_name')
                ->label(__('atrium::resource.seo.preview_picture_file_name'))
                ->rows(2),

            TextareaField::make('seo_detail_picture_alt')
                ->label(__('atrium::resource.seo.detail_picture_alt'))
                ->rows(2)
                ->ai('alt', from: 'detail_picture'),

            TextareaField::make('seo_detail_picture_title')
                ->label(__('atrium::resource.seo.detail_picture_title'))
                ->rows(2),

            TextareaField::make('seo_detail_picture_file_name')
                ->label(__('atrium::resource.seo.detail_picture_file_name'))
                ->rows(2),

            TextField::make('seo_tags')
                ->label(__('atrium::resource.seo.tags'))
                ->maxLength(500)
                ->hint(__('atrium::resource.seo.tags_hint')),

            ImageField::make('seo_og_image')
                ->label(__('atrium::resource.seo.og_image'))
                ->hint(__('atrium::resource.seo.og_image_hint')),
        ];
    }
}
