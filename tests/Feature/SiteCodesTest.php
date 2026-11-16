<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

final class SiteCodesTest extends TestCase
{
    private const LAYOUT = '<html><head><x-atrium::head /></head><body><x-atrium::body-start /><p>page</p><x-atrium::body-end /></body></html>';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'http://main.test');
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('page', fn () => Blade::render(self::LAYOUT));
    }

    private function main(): Site
    {
        return Site::query()->where('code', 'main')->sole();
    }

    private function withCodes(array $codes = []): Site
    {
        $site = $this->main();
        $site->update([
            'google_tag_id' => 'g-abc123xyz',
            'yandex_metrika_id' => ' 12345678 ',
            'yandex_webvisor' => true,
            'google_verification' => '<meta name="google-site-verification" content="google-token" />',
            'yandex_verification' => 'yandex-token',
            'head_code' => '<meta name="own-head" content="1">',
            'body_start_code' => '<div id="own-start"></div>',
            'body_end_code' => '<script>window.chat = 1</script>',
            ...$codes,
        ]);

        return $site;
    }

    public function test_codes_are_tidied_on_save(): void
    {
        $site = $this->withCodes(['bing_verification' => '  ', 'head_code' => "  \n"]);

        $this->assertSame('G-ABC123XYZ', $site->google_tag_id);
        $this->assertSame('12345678', $site->yandex_metrika_id);
        $this->assertSame('google-token', $site->google_verification);
        $this->assertNull($site->bing_verification);
        $this->assertNull($site->head_code);
    }

    public function test_a_site_shows_its_own_counters_and_codes(): void
    {
        $this->withCodes();

        $this->get('http://main.test/page')->assertOk()
            ->assertSee('<meta name="google-site-verification" content="google-token">', false)
            ->assertSee('<meta name="yandex-verification" content="yandex-token">', false)
            ->assertDontSee('msvalidate.01')
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ', false)
            ->assertSee("gtag('config','G-ABC123XYZ')", false)
            ->assertSee("ym(12345678,'init',{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true})", false)
            ->assertSee('https://mc.yandex.ru/watch/12345678', false)
            ->assertSee('<meta name="own-head" content="1">', false)
            ->assertSeeInOrder(['<body>', '<div id="own-start"></div>', '<p>page</p>', '<script>window.chat = 1</script>', '</body>'], false)
            ->assertDontSee('data-atrium-consent', false);
    }

    public function test_another_site_does_not_get_them(): void
    {
        $this->withCodes();
        Site::query()->create(['code' => 'fr', 'name' => 'France', 'domains' => ['example.fr'], 'yandex_metrika_id' => '87654321']);

        $this->get('http://example.fr/page')->assertOk()
            ->assertSee('ym(87654321', false)
            ->assertDontSee('G-ABC123XYZ')
            ->assertDontSee('12345678')
            ->assertDontSee('google-token')
            ->assertDontSee('own-head')
            ->assertDontSee('own-start')
            ->assertDontSee('window.chat');
    }

    public function test_a_tag_manager_container_gets_its_own_snippet(): void
    {
        $this->withCodes(['google_tag_id' => 'GTM-K9X2PQ', 'yandex_metrika_id' => null]);

        $this->get('http://main.test/page')->assertOk()
            ->assertSee("(window,document,'script','dataLayer','GTM-K9X2PQ')", false)
            ->assertSee('https://www.googletagmanager.com/ns.html?id=GTM-K9X2PQ', false)
            ->assertDontSee('gtag/js')
            ->assertDontSee('mc.yandex.ru');
    }

    public function test_administrators_are_not_counted_unless_asked(): void
    {
        $this->withCodes();
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('http://main.test/page')->assertOk()
            ->assertDontSee('G-ABC123XYZ')
            ->assertDontSee('mc.yandex.ru')
            ->assertSee('google-token')
            ->assertSee('own-head')
            ->assertSee('window.chat');

        $this->main()->update(['counters_skip_admins' => false]);

        $this->actingAs($admin, 'admin')->get('http://main.test/page')->assertOk()
            ->assertSee('G-ABC123XYZ')
            ->assertSee('mc.yandex.ru');
    }

    public function test_counters_can_wait_for_consent(): void
    {
        config(['atrium.counters.consent' => true]);
        $this->withCodes();

        $this->get('http://main.test/page')->assertOk()
            ->assertSee('window.atriumConsent=function()', false)
            ->assertSee('<script async type="text/plain" data-atrium-consent src="https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ"></script>', false)
            ->assertSee('<script type="text/plain" data-atrium-consent>(function(m,e,t,r,i,k,a)', false)
            ->assertDontSee('mc.yandex.ru/watch')
            ->assertSee('<script>window.chat = 1</script>', false);
    }

    public function test_codes_are_set_in_the_admin(): void
    {
        $site = $this->main();

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/sites/{$site->id}", [
                'name' => $site->name, 'domains' => $site->domains, 'active' => true, 'is_default' => true, 'sort' => 100,
                'google_tag_id' => 'not a tag',
                'yandex_metrika_id' => '12a',
            ])
            ->assertSessionHasErrors(['google_tag_id', 'yandex_metrika_id']);

        $this->put("/admin/sites/{$site->id}", [
            'name' => $site->name, 'domains' => $site->domains, 'active' => true, 'is_default' => true, 'sort' => 100,
            'google_tag_id' => 'G-ABC123XYZ',
            'yandex_metrika_id' => '12345678',
            'bing_verification' => '<meta name="msvalidate.01" content="BING123" />',
            'robots_txt' => "User-agent: *\nDisallow: /search",
        ])->assertSessionHasNoErrors();

        $site->refresh();
        $this->assertSame('G-ABC123XYZ', $site->google_tag_id);
        $this->assertSame('BING123', $site->bing_verification);
        $this->assertSame("User-agent: *\nDisallow: /search", $site->robots_txt);
    }
}
