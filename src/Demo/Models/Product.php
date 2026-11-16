<?php

declare(strict_types=1);

namespace Atrium\Admin\Demo\Models;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\ImageColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\ContentField;
use Atrium\Admin\Fields\DateField;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\FileField;
use Atrium\Admin\Fields\GalleryField;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\MultiSelectField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\RepeatableField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\SlugField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Fields\WysiwygField;
use Atrium\Admin\Traits\HasAdminFiles;
use Atrium\Admin\Traits\HasAdminSeo;
use Atrium\Admin\Traits\HasAdminSlug;
use Atrium\Admin\Traits\HasAdminTabs;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A product, written the way the Bitrix migrator writes a model: the
 * standard iblock fields on the Bitrix tabs, properties after them, the same
 * traits (slug history, SEO, tabs, files).
 *
 * @property int $id
 * @property int|null $section_id
 * @property string $name
 * @property string|null $code
 */
class Product extends Model implements Administrable
{
    use HasAdminFiles, HasAdminSeo, HasAdminSlug, HasAdminTabs, InteractsWithAdmin;

    protected $table = 'demo_products';

    protected $fillable = [
        'section_id', 'name', 'code', 'active', 'active_from', 'active_to', 'sort',
        'preview_picture', 'preview_text', 'preview_text_type', 'detail_picture', 'detail_text', 'detail_text_type',
        'article', 'price', 'brand_id', 'label', 'colors', 'released_on', 'in_stock', 'features', 'package',
        'specs', 'photos', 'manual', 'related',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'active_from' => 'datetime',
            'active_to' => 'datetime',
            'sort' => 'integer',
            'price' => 'decimal:2',
            'colors' => 'array',
            'released_on' => 'date',
            'features' => 'array',
            'photos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CatalogSection,$this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CatalogSection::class, 'section_id');
    }

    /**
     * @return BelongsTo<Brand,$this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * @return BelongsToMany<self,$this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'demo_product_related', 'product_id', 'related_id')
            ->withPivot('sort')
            ->orderBy('demo_product_related.sort');
    }

    public static function getAdminLabel(): string
    {
        return __('atrium::demo.modules.products');
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'demo_products';
    }

    public static function getAdminCreateLabel(): string
    {
        return __('atrium::demo.create.product');
    }

    public static function getDefaultSortColumn(): string
    {
        return 'sort';
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAdminTabs(): array
    {
        return [
            ['name' => 'params', 'label' => __('atrium::fields.tabs.main')],
            ['name' => 'preview', 'label' => __('atrium::fields.tabs.preview')],
            ['name' => 'detail', 'label' => __('atrium::fields.tabs.detail')],
            ['name' => 'seo', 'label' => __('atrium::fields.tabs.seo')],
            ['name' => 'sections', 'label' => __('atrium::fields.tabs.sections')],
        ];
    }

    public static function getAdministrationFields(): array
    {
        return [
            CheckboxField::make('active')->label(__('atrium::fields.standard.active'))->default(true)->tab('params'),
            DateTimeField::make('active_from')->label(__('atrium::fields.standard.active_from'))->tab('params'),
            DateTimeField::make('active_to')->label(__('atrium::fields.standard.active_to'))->tab('params'),
            TextField::make('name')->label(__('atrium::fields.standard.name'))->required()->tab('params'),
            SlugField::make('code')->label(__('atrium::fields.standard.code'))->from('name')->tab('params'),
            NumberField::make('sort')->label(__('atrium::fields.standard.sort'))->default(500)->tab('params'),
            TextField::make('article')->label(__('atrium::demo.fields.article'))->tab('params'),
            NumberField::make('price')->label(__('atrium::demo.fields.price'))->tab('params'),
            BelongsToField::make('brand_id')->label(__('atrium::demo.fields.brand'))->model(Brand::class)->tab('params'),
            SelectField::make('label')->label(__('atrium::demo.fields.label'))->options([
                'new' => __('atrium::demo.options.label.new'),
                'hit' => __('atrium::demo.options.label.hit'),
                'sale' => __('atrium::demo.options.label.sale'),
            ])->tab('params'),
            MultiSelectField::make('colors')->label(__('atrium::demo.fields.colors'))->options([
                'black' => __('atrium::demo.options.colors.black'),
                'silver' => __('atrium::demo.options.colors.silver'),
                'blue' => __('atrium::demo.options.colors.blue'),
                'pink' => __('atrium::demo.options.colors.pink'),
            ])->tab('params'),
            DateField::make('released_on')->label(__('atrium::demo.fields.released_on'))->tab('params'),
            // Bitrix keeps a one-option checkbox list as the option's code.
            CheckboxField::make('in_stock')->label(__('atrium::demo.fields.in_stock'))->checkedValue('Y')->tab('params'),
            RepeatableField::make('features')->label(__('atrium::demo.fields.features'))->itemType('text')->maxItems(10)->tab('params'),
            BelongsToManyField::make('related')->label(__('atrium::demo.fields.related'))->model(self::class)->tab('params'),

            ImageField::make('preview_picture')->label(__('atrium::fields.standard.preview_picture'))->directory('demo')->tab('preview'),
            ContentField::make('preview_text')->label(__('atrium::fields.standard.preview_text'))->tab('preview'),

            ImageField::make('detail_picture')->label(__('atrium::fields.standard.detail_picture'))->directory('demo')->tab('detail'),
            ContentField::make('detail_text')->label(__('atrium::fields.standard.detail_text'))->tab('detail'),
            WysiwygField::make('specs')->label(__('atrium::demo.fields.specs'))->tab('detail'),
            TextareaField::make('package')->label(__('atrium::demo.fields.package'))->tab('detail'),
            GalleryField::make('photos')->label(__('atrium::demo.fields.photos'))->directory('demo')->tab('detail'),
            FileField::make('manual')->label(__('atrium::demo.fields.manual'))->directory('demo')->tab('detail'),

            TreeSelectField::make('section_id')->label(__('atrium::fields.standard.section'))->model(CatalogSection::class)->tab('sections'),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('id')->label(__('atrium::fields.standard.id'))->sortable(),
            ImageColumn::make('preview_picture')->label(__('atrium::fields.standard.preview_picture')),
            TextColumn::make('name')->label(__('atrium::fields.standard.name'))->sortable()->searchable(),
            TextColumn::make('price')->label(__('atrium::demo.fields.price'))->sortable(),
            TextColumn::make('sort')->label(__('atrium::fields.standard.sort'))->sortable(),
            BooleanColumn::make('active')->label(__('atrium::fields.standard.active')),
        ];
    }
}
