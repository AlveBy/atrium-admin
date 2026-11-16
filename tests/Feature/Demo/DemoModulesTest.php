<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Demo;

use Atrium\Admin\Demo\Demo;
use Atrium\Admin\Demo\DemoSeeder;
use Atrium\Admin\Demo\Models\News;
use Atrium\Admin\Demo\Models\Product;
use Atrium\Admin\Demo\Models\Store;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Models\Slug;
use Atrium\Admin\Models\SlugHistory;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * The demo modules as a user of the admin sees them, and the model traits
 * they are the only users of in the repository.
 */
final class DemoModulesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.demo', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        (new DemoSeeder)->run();
    }

    public function test_every_field_type_of_the_panel_is_in_the_demo(): void
    {
        $all = [];

        foreach (glob(dirname(__DIR__, 3).'/src/Fields/*Field.php') ?: [] as $file) {
            $class = 'Atrium\\Admin\\Fields\\'.basename($file, '.php');

            if ($class !== Field::class) {
                $all[] = (new $class('x'))->getType();
            }
        }

        $used = [];

        foreach (Demo::MODELS as $model) {
            foreach ($model::getAdministrationFields() as $field) {
                $used[] = $field->getType();
            }
        }

        $this->assertCount(20, $all);
        $this->assertSame([], array_values(array_diff($all, $used)));
    }

    public function test_every_demo_module_opens_in_the_admin(): void
    {
        $this->actingAs($this->admin(), 'admin');

        foreach (Demo::MODELS as $model) {
            $key = $model::getAdminRoutePrefix();
            $record = $model::query()->firstOrFail();

            $this->get("/admin/{$key}")->assertOk();
            $this->get("/admin/{$key}/create")->assertOk();
            $this->get("/admin/{$key}/{$record->getKey()}/edit")->assertOk();
        }

        $this->get('/admin/demo_products')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('title', 'Products')
                ->where('items.total', 8)
                ->where('items.data.0.name', 'Aero 14'));
    }

    public function test_a_link_to_a_directory_is_a_list_of_its_records(): void
    {
        $brand = collect(Product::getAdministrationFields())->first(fn (Field $field): bool => $field->getName() === 'brand_id');

        $this->assertSame(['Lumen', 'Vantor', 'Orbita'], array_column($brand?->toArray()['options'] ?? [], 'label'));
    }

    public function test_the_product_form_has_the_bitrix_tabs_and_the_seo_tab(): void
    {
        $grouped = Product::getFieldsGroupedByTabs('edit');

        $this->assertSame(['params', 'preview', 'detail', 'seo', 'sections'], array_column($grouped['tabs'], 'name'));
        $this->assertContains('seo_title', array_map(static fn (Field $field): string => $field->getName(), $grouped['fields']['seo']));
    }

    public function test_a_product_keeps_its_old_code_and_its_seo(): void
    {
        $product = Product::query()->where('code', 'aero-14')->sole();

        $this->assertSame('aero-14', Slug::query()->where('sluggable_type', Product::class)->where('sluggable_id', $product->id)->value('slug'));
        $this->assertSame('Aero 14 laptop: 1.2 kg, 18 hours of battery', $product->getSeoTitle());
        $this->assertSame(1, SeoMeta::query()->count());

        $product->update(['code' => 'aero-14-2026']);

        $this->assertSame('aero-14', SlugHistory::query()->where('sluggable_id', $product->id)->value('slug'));
        $this->assertSame('aero-14-2026', Slug::query()->where('sluggable_id', $product->id)->where('sluggable_type', Product::class)->value('slug'));

        // A new product without a code gets one from its name.
        $this->assertSame('pulse-max', Product::query()->create(['name' => 'Pulse Max', 'price' => 129])->code);
    }

    public function test_files_attached_in_code_have_an_address(): void
    {
        $product = Product::query()->where('code', 'aero-14')->sole();

        $this->assertTrue($product->hasFile('manual'));
        $this->assertStringEndsWith('/demo/quick-start.pdf', (string) $product->getFileUrl('manual'));
        $this->assertSame('Aero 14 quick start.pdf', $product->getFileByField('manual')?->filename);
    }

    public function test_news_are_written_in_several_languages_for_two_sites(): void
    {
        $news = News::query()->get();

        $this->assertSame(2, $news->pluck('site_id')->unique()->count());
        $this->assertSame('Neue Filiale in der Innenstadt', $news->first()?->getTranslation('name', 'de'));
        $this->assertSame(['en'], array_keys($news->firstWhere(fn (News $item): bool => $item->getTranslation('code', 'en') === 'how-to-choose-a-laptop-for-study')?->getTranslations('name') ?? []));
    }

    public function test_a_store_keeps_its_phones_and_its_point_on_the_map(): void
    {
        $store = Store::query()->where('code', 'city-centre')->sole();

        $this->assertSame(['+1 555 0100', '+1 555 0101'], $store->phones);
        $this->assertSame(52.52, $store->location['lat'] ?? null);
    }

    public function test_seeding_twice_adds_nothing(): void
    {
        $this->assertFalse((new DemoSeeder)->run());
        $this->assertSame(8, Product::query()->count());
    }
}
