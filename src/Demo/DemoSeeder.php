<?php

declare(strict_types=1);

namespace Atrium\Admin\Demo;

use Atrium\Admin\Demo\Models\Brand;
use Atrium\Admin\Demo\Models\CatalogSection;
use Atrium\Admin\Demo\Models\News;
use Atrium\Admin\Demo\Models\Product;
use Atrium\Admin\Demo\Models\Store;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Support\Sites;
use Illuminate\Support\Facades\Storage;

/**
 * Content of the demo modules: a small electronics shop with two sites,
 * news in three languages and every field type filled in.
 *
 * Runs once: when the catalog already has products, it does nothing, so a
 * second `atrium:install --demo` does not duplicate anything and edits made
 * in the admin stay.
 */
final class DemoSeeder
{
    /** Where the pictures and documents go on the uploads disk. */
    public const DIRECTORY = 'demo';

    public function run(): bool
    {
        if (Product::query()->exists()) {
            return false;
        }

        $this->copyFiles();
        $outlet = $this->sitesAndLanguages();

        $brands = $this->brands();
        $sections = $this->sections();
        $this->products($brands, $sections);
        $this->news($outlet);
        $this->stores();

        PublicUrl::forgetPaths();

        return true;
    }

    private function copyFiles(): void
    {
        $disk = Storage::disk((string) config('atrium.uploads.disk', 'public'));

        $source = dirname(__DIR__, 2).'/resources/demo';

        foreach ([...(glob($source.'/images/*') ?: []), ...(glob($source.'/files/*') ?: [])] as $file) {
            $disk->put(self::DIRECTORY.'/'.basename($file), (string) file_get_contents($file));
        }
    }

    private function path(string $file): string
    {
        return self::DIRECTORY.'/'.$file;
    }

    /**
     * German and Russian next to English, and a second site, the outlet,
     * that speaks English and German and opens in German.
     */
    private function sitesAndLanguages(): Site
    {
        foreach (['en' => 'English', 'de' => 'Deutsch', 'ru' => 'Русский'] as $code => $name) {
            Language::query()->firstOrCreate(['code' => $code], ['name' => $name, 'active' => true, 'sort' => 100 + Language::query()->count()]);
        }

        $default = app(Sites::class)->default();
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = $host === '' || filter_var($host, FILTER_VALIDATE_IP) ? 'localhost' : $host;

        $outlet = Site::query()->firstOrCreate(['code' => 'outlet'], [
            'name' => 'Atrium Outlet',
            'domains' => ['outlet.'.$host],
            'languages' => ['de', 'en'],
            'default_language' => 'de',
            'active' => true,
            'sort' => (int) $default->sort + 10,
        ]);

        app(Sites::class)->flush();

        return $outlet;
    }

    /**
     * @return array<string,Brand>
     */
    private function brands(): array
    {
        $brands = [];

        foreach ([
            'lumen' => ['Lumen', '#1d4ed8', 'https://example.com/lumen', 'Laptops and tablets designed for long days away from a socket.'],
            'vantor' => ['Vantor', '#b91c1c', 'https://example.com/vantor', "Sound for home and the road.\nHeadphones and speakers tuned by ear."],
            'orbita' => ['Orbita', '#047857', 'https://example.com/orbita', 'Watches that count steps, sleep and the minutes to the next meeting.'],
        ] as $code => [$name, $color, $website, $description]) {
            $brands[$code] = Brand::query()->create([
                'name' => $name,
                'code' => $code,
                'logo' => $this->path($code.'.svg'),
                'color' => $color,
                'website' => $website,
                'description' => $description,
            ]);
        }

        return $brands;
    }

    /**
     * @return array<string,CatalogSection>
     */
    private function sections(): array
    {
        $tree = [
            'computers' => ['Computers', 'laptop.svg', '<p>Laptops and tablets for work, study and everything in between.</p>', [
                'laptops' => ['Laptops', 'laptop-open.svg', '<p>From a light 14-inch for the road to a 16-inch workstation.</p>'],
                'tablets' => ['Tablets', 'tablet.svg', null],
            ]],
            'audio' => ['Audio', 'headphones.svg', '<p>Headphones and speakers.</p>', [
                'headphones' => ['Headphones', 'headphones.svg', null],
                'speakers' => ['Speakers', 'speaker.svg', null],
            ]],
            'wearables' => ['Wearables', 'watch.svg', '<p>Watches and trackers.</p>', []],
        ];

        $sections = [];
        $sort = 100;

        foreach ($tree as $code => [$name, $picture, $description, $children]) {
            $parent = $sections[$code] = CatalogSection::query()->create([
                'name' => $name, 'code' => $code, 'sort' => $sort += 100, 'picture' => $this->path($picture), 'description' => $description,
            ]);

            foreach ($children as $childCode => [$childName, $childPicture, $childDescription]) {
                $sections[$childCode] = CatalogSection::query()->create([
                    'parent_id' => $parent->id, 'name' => $childName, 'code' => $childCode, 'sort' => $sort += 10,
                    'picture' => $this->path($childPicture), 'description' => $childDescription,
                ]);
            }
        }

        return $sections;
    }

    /**
     * @param  array<string,Brand>  $brands
     * @param  array<string,CatalogSection>  $sections
     */
    private function products(array $brands, array $sections): void
    {
        $products = [
            'aero-14' => [
                'section' => 'laptops', 'brand' => 'lumen', 'name' => 'Aero 14', 'sort' => 100,
                'picture' => 'laptop.svg', 'article' => 'LM-A14', 'price' => 1199, 'label' => 'hit', 'colors' => ['silver', 'blue'],
                'released_on' => '2026-03-12', 'in_stock' => 'Y',
                'preview' => '<p>A 14-inch laptop that weighs 1.2 kg and lasts a working day and a half.</p>',
                'detail' => '<p>The Aero 14 is built for people who work where they happen to be. The aluminium case is 14 mm thin, the screen is bright enough for a sunny café, and the battery lasts up to 18 hours.</p><p>Two USB-C ports charge it from any power bank.</p>',
                'features' => ['18 hours of battery', '1.2 kg', 'Matte 2.8K screen'],
                'specs' => '<table><tbody><tr><th>Screen</th><td>14", 2880 × 1800, 120 Hz</td></tr><tr><th>Memory</th><td>16 GB</td></tr><tr><th>Storage</th><td>512 GB SSD</td></tr><tr><th>Weight</th><td>1.2 kg</td></tr></tbody></table>',
                'package' => "Laptop\n65 W USB-C charger\nQuick start guide",
                'photos' => ['laptop.svg', 'laptop-open.svg', 'laptop-side.svg'], 'manual' => 'quick-start.pdf',
                'seo' => ['title' => 'Aero 14 laptop: 1.2 kg, 18 hours of battery', 'description' => 'The Aero 14 by Lumen: a 14-inch laptop for work on the road. Price, specifications and where to buy.'],
            ],
            'aero-16-pro' => [
                'section' => 'laptops', 'brand' => 'lumen', 'name' => 'Aero 16 Pro', 'sort' => 200,
                'picture' => 'laptop-pro.svg', 'article' => 'LM-A16P', 'price' => 1999, 'label' => 'new', 'colors' => ['black'],
                'released_on' => '2026-09-01', 'in_stock' => 'Y',
                'preview' => '<p>A 16-inch workstation for photo and video.</p>',
                'detail' => '<p>More screen, more memory and a graphics card that renders a 4K video while you answer e-mail.</p>',
                'features' => ['32 GB of memory', 'Calibrated screen'],
                'specs' => '<ul><li>16", 3456 × 2160</li><li>32 GB / 1 TB</li><li>2.1 kg</li></ul>',
                'photos' => ['laptop-pro.svg', 'laptop-open.svg'],
            ],
            'slate-11' => [
                'section' => 'tablets', 'brand' => 'lumen', 'name' => 'Slate 11', 'sort' => 300,
                'picture' => 'tablet.svg', 'article' => 'LM-S11', 'price' => 549, 'colors' => ['silver', 'pink'],
                'released_on' => '2025-11-20', 'in_stock' => 'Y',
                'preview' => '<p>An 11-inch tablet with a pen that is always charged.</p>',
            ],
            'quiet-one' => [
                'section' => 'headphones', 'brand' => 'vantor', 'name' => 'Quiet One', 'sort' => 400,
                'picture' => 'headphones.svg', 'article' => 'VT-Q1', 'price' => 299, 'label' => 'sale', 'colors' => ['black', 'pink'],
                'released_on' => '2025-05-15', 'in_stock' => 'Y',
                'preview' => '<p>Noise-cancelling headphones that fold into a jacket pocket.</p>',
                'detail' => '<p>Three levels of noise cancelling, 40 hours of music, and a transparency mode for announcements at the station.</p>',
                'features' => ['40 hours of music', 'Noise cancelling', 'Folds flat'],
                'package' => "Headphones\nCase\nUSB-C cable",
            ],
            'pulse-mini' => [
                'section' => 'speakers', 'brand' => 'vantor', 'name' => 'Pulse Mini', 'sort' => 500,
                'picture' => 'speaker.svg', 'article' => 'VT-PM', 'price' => 89, 'colors' => ['blue', 'black'],
                'in_stock' => null,
                'preview' => '<p>A speaker the size of a can that is not afraid of rain.</p>',
                // Plain text, the way Bitrix keeps a text-type field: escaped, line breaks kept.
                'detail' => "Waterproof to IP67.\n12 hours on one charge.\nTwo of them play in stereo.",
                'detail_type' => 'text',
            ],
            'stride' => [
                'section' => 'wearables', 'brand' => 'orbita', 'name' => 'Stride', 'sort' => 600,
                'picture' => 'watch.svg', 'article' => 'OR-ST', 'price' => 249, 'label' => 'new', 'colors' => ['black', 'silver'],
                'released_on' => '2026-06-01', 'in_stock' => 'Y',
                'preview' => '<p>A watch with a week of battery and a screen you can read in the sun.</p>',
            ],
            'gift-card' => [
                'section' => null, 'brand' => null, 'name' => 'Gift card', 'sort' => 700,
                'picture' => 'gift-card.svg', 'price' => 50,
                'preview' => '<p>Not sure what to give? Let them choose. Lives right under the catalog, outside any section.</p>',
            ],
        ];

        $created = [];

        foreach ($products as $code => $data) {
            $product = Product::query()->create([
                'section_id' => $data['section'] === null ? null : $sections[$data['section']]->id,
                'brand_id' => $data['brand'] === null ? null : $brands[$data['brand']]->id,
                'name' => $data['name'],
                'code' => $code,
                'sort' => $data['sort'],
                'active_from' => now()->subMonths(2),
                'preview_picture' => $this->path($data['picture']),
                'preview_text' => $data['preview'],
                'detail_picture' => isset($data['detail']) ? $this->path($data['picture']) : null,
                'detail_text' => $data['detail'] ?? null,
                'detail_text_type' => $data['detail_type'] ?? 'html',
                'article' => $data['article'] ?? null,
                'price' => $data['price'],
                'label' => $data['label'] ?? null,
                'colors' => $data['colors'] ?? null,
                'released_on' => $data['released_on'] ?? null,
                'in_stock' => $data['in_stock'] ?? null,
                'features' => $data['features'] ?? null,
                'specs' => $data['specs'] ?? null,
                'package' => $data['package'] ?? null,
                'photos' => isset($data['photos']) ? array_map($this->path(...), $data['photos']) : null,
                'manual' => isset($data['manual']) ? $this->path($data['manual']) : null,
            ]);

            if (isset($data['seo'])) {
                $product->updateSeoMeta($data['seo']);
            }

            if (isset($data['manual'])) {
                $product->attachFile('manual', $this->path($data['manual']), ['filename' => 'Aero 14 quick start.pdf']);
            }

            $created[$code] = $product;
        }

        // Shown as links on the product page.
        $created['aero-14']->related()->attach([
            $created['quiet-one']->id => ['sort' => 1],
            $created['pulse-mini']->id => ['sort' => 2],
        ]);

        // Retired: kept in the admin, gone from the site.
        Product::query()->create(['section_id' => $sections['laptops']->id, 'name' => 'Aero 13 (2023)', 'code' => 'aero-13', 'active' => false, 'sort' => 900, 'price' => 899]);
    }

    private function news(Site $outlet): void
    {
        $default = app(Sites::class)->default();

        $news = [
            [$default, 3, 'store.svg', [
                'en' => ['A new store in the city centre', 'a-new-store-in-the-city-centre', 'Come and try every laptop and pair of headphones before you buy.', '<p>Our second store opened on Market Street. Every model from the catalog is on the tables: open it, type on it, listen to it.</p><p>Open daily from 10:00 to 21:00.</p>'],
                'de' => ['Neue Filiale in der Innenstadt', 'neue-filiale-in-der-innenstadt', 'Probieren Sie jedes Notebook und jeden Kopfhörer vor dem Kauf aus.', '<p>Unsere zweite Filiale in der Market Street hat eröffnet. Alle Modelle aus dem Katalog liegen zum Ausprobieren bereit.</p><p>Täglich von 10 bis 21 Uhr geöffnet.</p>'],
                'ru' => ['Новый магазин в центре города', 'novyj-magazin-v-centre', 'Любой ноутбук и наушники можно попробовать до покупки.', '<p>Наш второй магазин открылся на Рыночной улице. Все модели из каталога выставлены: открывайте, печатайте, слушайте.</p><p>Работаем каждый день с 10:00 до 21:00.</p>'],
            ]],
            [$default, 10, 'sale.svg', [
                'en' => ['Autumn sale: 20% off headphones', 'autumn-sale', 'Until the end of the month, every pair of Vantor headphones is 20% cheaper.', '<p>The discount is applied at checkout, online and in the stores.</p>'],
                'de' => ['Herbstaktion: 20 % auf Kopfhörer', 'herbstaktion', 'Bis Monatsende sind alle Vantor-Kopfhörer 20 % günstiger.', '<p>Der Rabatt wird an der Kasse abgezogen, online und in den Filialen.</p>'],
                'ru' => ['Осенняя распродажа: −20% на наушники', 'osennyaya-rasprodazha', 'До конца месяца все наушники Vantor дешевле на 20%.', '<p>Скидка применяется при оформлении заказа — на сайте и в магазинах.</p>'],
            ]],
            // English only: it has no German or Russian page.
            [$default, 20, 'guide.svg', [
                'en' => ['How to choose a laptop for study', 'how-to-choose-a-laptop-for-study', 'Weight, battery and screen matter more than the processor.', '<h2>Weight</h2><p>You will carry it every day: under 1.5 kg is comfortable.</p><h2>Battery</h2><p>A full day of lectures needs at least 10 hours.</p><h2>Screen</h2><p>A matte screen is easier on the eyes in a bright room.</p>'],
            ]],
            [$outlet, 1, 'outlet.svg', [
                'de' => ['Das Outlet öffnet am Samstag', 'outlet-eroeffnung', 'Vorführgeräte und Vorjahresmodelle bis zu 40 % günstiger.', '<p>Alle Geräte sind geprüft und haben die volle Garantie.</p>'],
                'en' => ['The outlet opens on Saturday', 'outlet-opening', 'Display units and last year’s models up to 40% off.', '<p>Every device is checked and comes with the full warranty.</p>'],
            ]],
        ];

        foreach ($news as [$site, $daysAgo, $picture, $texts]) {
            $record = new News([
                'site_id' => $site->id,
                'active_from' => now()->subDays($daysAgo),
                'sort' => 500,
                'preview_picture' => $this->path($picture),
            ]);

            foreach ($texts as $language => [$name, $code, $preview, $detail]) {
                $record->setTranslation('name', $language, $name)
                    ->setTranslation('code', $language, $code)
                    ->setTranslation('preview_text', $language, '<p>'.$preview.'</p>')
                    ->setTranslation('detail_text', $language, $detail);
            }

            $record->save();
        }
    }

    private function stores(): void
    {
        Store::query()->create([
            'name' => 'City centre',
            'code' => 'city-centre',
            'sort' => 100,
            'preview_picture' => $this->path('store.svg'),
            'address' => "12 Market Street\nOld Town",
            'phones' => ['+1 555 0100', '+1 555 0101'],
            'email' => 'centre@example.com',
            'opens_at' => now()->subDays(3)->setTime(10, 0),
            'location' => ['lat' => 52.5200, 'lng' => 13.4050, 'address' => '12 Market Street'],
            'detail_text' => '<p>Two floors, every model from the catalog on the tables, and a service desk for repairs.</p>',
        ]);

        Store::query()->create([
            'name' => 'Riverside outlet',
            'code' => 'riverside-outlet',
            'sort' => 200,
            'preview_picture' => $this->path('outlet.svg'),
            'address' => "3 River Road\nHarbour district",
            'phones' => ['+1 555 0150'],
            'location' => ['lat' => 52.5070, 'lng' => 13.4440, 'address' => '3 River Road'],
            'detail_text' => '<p>Display units and last year’s models.</p>',
        ]);
    }
}
