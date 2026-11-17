<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Columns\ImageColumn;
use Atrium\Admin\Dashboard\Gadgets\RecentElementsGadget;
use Atrium\Admin\Http\Controllers\FileManagerController;
use Atrium\Admin\Models\MediaItem;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Support\Cdn;
use Atrium\Admin\Support\CdnPurge;
use Atrium\Admin\Support\MediaUrl;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use ReflectionMethod;
use RuntimeException;

final class CdnTest extends TestCase
{
    private const FOLDER = 'atrium-cdn-test';

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path(self::FOLDER));

        parent::tearDown();
    }

    public function test_everything_is_unchanged_while_the_cdn_is_off(): void
    {
        $this->assertSame(url('/storage/uploads/a.jpg'), MediaUrl::to('uploads/a.jpg'));
        $this->assertSame(url('/upload/iblock/b.png'), MediaUrl::to('/upload/iblock/b.png', 640));
        $this->assertSame('<img src="/storage/a.jpg">', Cdn::html('<img src="/storage/a.jpg">'));
        $this->assertNull(Cdn::srcset('/storage/a.jpg'));
        $this->assertFalse(CdnPurge::configured());
    }

    public function test_files_of_the_site_come_from_the_cdn(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test/']);

        $this->assertSame('https://cdn.test/storage/uploads/a%20b.jpg', MediaUrl::to('uploads/a b.jpg'));
        $this->assertSame('https://cdn.test/upload/iblock/b.png', MediaUrl::to('/upload/iblock/b.png'));
        $this->assertSame('https://cdn.test/storage/c.jpg', MediaUrl::to(['url' => '/storage/c.jpg']));
        // Full addresses (S3) and paths the CDN is not set for stay as they are.
        $this->assertSame('https://bucket.example.com/d.jpg', MediaUrl::to('https://bucket.example.com/d.jpg'));
        $this->assertSame(url('/robots.txt'), MediaUrl::to('/robots.txt'));
        $this->assertNull(MediaUrl::to(''));
        $this->assertNull(MediaUrl::to(null));
    }

    public function test_a_file_on_disk_gets_its_version_in_the_address(): void
    {
        File::ensureDirectoryExists(public_path(self::FOLDER));
        file_put_contents(public_path(self::FOLDER.'/a.css'), 'a{}');
        touch(public_path(self::FOLDER.'/a.css'), 1_800_000_000);
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.paths' => ['/'.self::FOLDER.'/']]);

        $this->assertSame('https://cdn.test/'.self::FOLDER.'/a.css?v='.base_convert('1800000000', 10, 36), Cdn::asset('/'.self::FOLDER.'/a.css'));

        // Changed file, new address: nothing to purge.
        touch(public_path(self::FOLDER.'/a.css'), 1_800_000_100);
        clearstatcache();
        $this->assertStringEndsWith('?v='.base_convert('1800000100', 10, 36), Cdn::asset('/'.self::FOLDER.'/a.css'));

        config(['atrium.cdn.version' => false]);
        $this->assertSame('https://cdn.test/'.self::FOLDER.'/a.css', Cdn::asset('/'.self::FOLDER.'/a.css'));
    }

    public function test_images_and_links_inside_a_text_come_from_the_cdn(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test']);

        $html = '<p><img src="/storage/a.jpg" alt=""><a href=\'/upload/doc.pdf\'>doc</a>'
            .'<a href="/about">about</a><img src="https://other.test/storage/b.jpg"><video poster="/storage/v.jpg"></video></p>';

        $this->assertSame(
            '<p><img src="https://cdn.test/storage/a.jpg" alt=""><a href=\'https://cdn.test/upload/doc.pdf\'>doc</a>'
            .'<a href="/about">about</a><img src="https://other.test/storage/b.jpg"><video poster="https://cdn.test/storage/v.jpg"></video></p>',
            Cdn::html($html),
        );
    }

    public function test_cloudflare_resizes_images_on_the_cdn(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.images.driver' => 'cloudflare']);

        $this->assertSame('https://cdn.test/cdn-cgi/image/width=640,quality=82,format=auto/storage/a.jpg', MediaUrl::to('/storage/a.jpg', 500));
        // Widths round up to a preset, the largest is the limit.
        $this->assertSame('https://cdn.test/cdn-cgi/image/width=1920,quality=82,format=auto/storage/a.jpg', MediaUrl::to('/storage/a.jpg', 5000));
        // Not an image, or not behind Cloudflare: as it is.
        $this->assertSame('https://cdn.test/storage/doc.pdf', MediaUrl::to('/storage/doc.pdf', 640));
        $this->assertSame('https://bucket.example.com/a.jpg', MediaUrl::to('https://bucket.example.com/a.jpg', 640));

        $this->assertSame(
            'https://cdn.test/cdn-cgi/image/width=320,quality=82,format=auto/storage/a.jpg 320w, https://cdn.test/cdn-cgi/image/width=640,quality=82,format=auto/storage/a.jpg 640w',
            Cdn::srcset('https://cdn.test/storage/a.jpg', 640),
        );
    }

    public function test_admin_thumbnails_and_og_images_come_from_the_cdn_at_twice_their_size(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.images.driver' => 'cloudflare']);
        $thumbnail = static fn (int $width): string => "https://cdn.test/cdn-cgi/image/width={$width},quality=82,format=auto/storage/a.jpg";

        // The list column: 2x its 50px cell, rounded up to the 320 preset; 200px makes 400, so 640.
        $this->assertSame($thumbnail(320), ImageColumn::make('picture')->formatValue('a.jpg', null));
        $this->assertSame($thumbnail(640), ImageColumn::make('picture')->size(200)->formatValue('a.jpg', null));
        $this->assertSame('/none.png', ImageColumn::make('picture')->defaultImage('/none.png')->formatValue('', null));

        $gadget = new ReflectionMethod(RecentElementsGadget::class, 'imageUrl');
        $this->assertSame($thumbnail(320), $gadget->invoke(new RecentElementsGadget, 'a.jpg'));
        $this->assertNull($gadget->invoke(new RecentElementsGadget, null));

        // Open Graph: the full image, never resized.
        $this->assertSame('https://cdn.test/storage/a.jpg', (new SeoMeta(['og_image' => 'a.jpg']))->og_image_url);
        $this->assertSame('https://cdn.test/storage/a.jpg', (new SeoMeta(['og_image' => 'a.jpg']))->getOgImageUrlAttribute());
        $this->assertNull((new SeoMeta)->og_image_url);
    }

    public function test_bunny_resizes_with_query_parameters(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.images.driver' => 'bunny', 'atrium.cdn.images.quality' => 75]);

        $this->assertSame('https://cdn.test/storage/a.jpg?width=960&quality=75', MediaUrl::to('/storage/a.jpg', 900));
    }

    public function test_imgproxy_addresses_are_signed_like_its_documentation_says(): void
    {
        $key = '943b421c9eb07c830af81030552c86009268de4e532ba2ee2eab8247c6da0881';
        $salt = '520f986b998545b4785e0defbc4f3c1203f22de2374a3d53cb7a7fe9fea309c5';
        config([
            'atrium.cdn.images.driver' => 'imgproxy',
            'atrium.cdn.images.url' => 'https://img.test',
            'atrium.cdn.images.format' => 'webp',
        ]);

        // No key yet: the "insecure" form imgproxy accepts when signing is off.
        $this->assertSame('https://img.test/insecure/rs:fit:640:0/q:82/plain/'.rawurlencode('https://shop.test/storage/a.jpg').'@webp', MediaUrl::to('https://shop.test/storage/a.jpg', 640));

        config(['atrium.cdn.images.key' => $key, 'atrium.cdn.images.salt' => $salt]);
        $path = '/rs:fit:640:0/q:82/plain/'.rawurlencode('https://shop.test/storage/a.jpg').'@webp';
        // The same computation as imgproxy's examples/signature.php.
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', pack('H*', $salt).$path, pack('H*', $key), true)), '+/', '-_'), '=');

        $this->assertSame('https://img.test/'.$signature.$path, MediaUrl::to('https://shop.test/storage/a.jpg', 640));
        // A path of this site is fetched by its full address.
        $this->assertStringContainsString(rawurlencode(url('/storage/b.png')), (string) MediaUrl::to('/storage/b.png', 320));
    }

    public function test_any_image_service_by_a_template(): void
    {
        config([
            'atrium.cdn.images.driver' => 'template',
            'atrium.cdn.images.template' => 'https://resize.test/{width}/{format}/{quality}/{path}?src={url}',
        ]);

        $this->assertSame(
            'https://resize.test/320/auto/82/storage/a.jpg?src='.rawurlencode(url('/storage/a.jpg')),
            MediaUrl::to('/storage/a.jpg', 300),
        );
    }

    public function test_cloudflare_purges_addresses_in_batches(): void
    {
        $this->cloudflare();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        CdnPurge::urls(array_map(static fn (int $i): string => "https://cdn.test/storage/{$i}.jpg", range(1, 31)));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone-1/purge_cache'
            && $request->hasHeader('Authorization', 'Bearer cf-token')
            && count($request['files']) === 30);
    }

    public function test_cloudflare_purges_everything(): void
    {
        $this->cloudflare();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        CdnPurge::everything();

        Http::assertSent(fn (Request $request): bool => $request['purge_everything'] === true);
    }

    public function test_bunny_purges_each_address_and_the_whole_zone(): void
    {
        config(['atrium.cdn.purge.driver' => 'bunny', 'atrium.cdn.purge.bunny.key' => 'bunny-key', 'atrium.cdn.purge.bunny.pull_zone' => '42']);
        Http::fake(['api.bunny.net/*' => Http::response('', 200)]);

        // The same address twice is purged once.
        CdnPurge::urls(['https://cdn.test/storage/a.jpg', 'https://cdn.test/storage/b.jpg', 'https://cdn.test/storage/a.jpg']);
        CdnPurge::everything();

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.bunny.net/purge?url='.rawurlencode('https://cdn.test/storage/a.jpg')
            && $request->hasHeader('AccessKey', 'bunny-key'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.bunny.net/pullzone/42/purgeCache'
            && $request->hasHeader('AccessKey', 'bunny-key'));
    }

    public function test_a_refused_purge_or_a_missing_token_says_why(): void
    {
        $this->cloudflare();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false, 'errors' => [['message' => 'Authentication error']]], 403)]);

        try {
            CdnPurge::urls(['https://cdn.test/storage/a.jpg']);
            $this->fail('A refused purge must throw.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 403', $exception->getMessage());
        }

        config(['atrium.cdn.purge.cloudflare.token' => '']);
        $this->expectExceptionMessage('atrium.cdn.purge.cloudflare.token is empty');
        CdnPurge::urls(['https://cdn.test/storage/a.jpg']);
    }

    public function test_the_purge_command(): void
    {
        $this->cloudflare();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->artisan('atrium:cdn:purge')->expectsOutput('Name the files, or pass --all.')->assertExitCode(2);
        $this->artisan('atrium:cdn:purge', ['paths' => ['/storage/a.jpg']])
            ->expectsOutput('Purged: https://cdn.test/storage/a.jpg, https://cdn.test/storage/a.jpg.webp')
            ->assertSuccessful();
        $this->artisan('atrium:cdn:purge', ['--all' => true])
            ->expectsOutput('The CDN cache is purged.')
            ->doesntExpectOutputToContain('Purged:')
            ->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => ($request['files'] ?? null) === ['https://cdn.test/storage/a.jpg', 'https://cdn.test/storage/a.jpg.webp']);
        Http::assertSent(fn (Request $request): bool => ($request['purge_everything'] ?? null) === true);

        config(['atrium.cdn.purge.driver' => null]);
        $this->artisan('atrium:cdn:purge', ['--all' => true])
            ->expectsOutput('No CDN purge is set up: ATRIUM_CDN_PURGE is cloudflare or bunny.')
            ->assertFailed();
    }

    public function test_an_upload_keeps_a_path_and_its_deletion_purges_the_cdn(): void
    {
        Storage::fake('public');
        $this->cloudflare();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->withoutDefer();

        $upload = $this->actingAs($this->admin(), 'admin')
            ->post('/admin/upload', ['file' => UploadedFile::fake()->createWithContent('photo.pdf', "%PDF-1.4\n".str_repeat(' ', 10240))])
            ->assertOk();

        // Host-independent: the CDN is put in front when the page is drawn.
        $this->assertMatchesRegularExpression('#^/storage/uploads/\d{4}/\d{2}/[0-9a-f-]+\.pdf$#', $upload->json('url'));

        $this->deleteJson('/admin/filemanager', ['id' => $upload->json('id')])->assertOk();

        Storage::disk('public')->assertMissing($upload->json('path'));
        Http::assertSent(fn (Request $request): bool => in_array('https://cdn.test'.$upload->json('url'), $request['files'] ?? [], true));
    }

    public function test_a_refused_purge_is_reported_and_the_file_is_deleted_anyway(): void
    {
        Storage::fake('public');
        $this->cloudflare();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => false], 500)]);
        Exceptions::fake();
        $this->withoutDefer();

        $upload = $this->actingAs($this->admin(), 'admin')
            ->post('/admin/upload', ['file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n".str_repeat(' ', 10240))]);
        $this->deleteJson('/admin/filemanager', ['id' => $upload->json('id')])->assertOk();

        Storage::disk('public')->assertMissing($upload->json('path'));
        Exceptions::assertReported(fn (RuntimeException $e): bool => str_contains($e->getMessage(), 'HTTP 500'));
    }

    public function test_purging_nothing_sends_nothing_and_purging_without_a_driver_says_so(): void
    {
        Http::fake();

        CdnPurge::urls([]);
        CdnPurge::urls(['', '']);
        Http::assertNothingSent();

        $this->expectExceptionMessage('No CDN purge is set up');
        CdnPurge::urls(['https://cdn.test/storage/a.jpg']);
    }

    public function test_a_full_address_is_purged_as_it_is_and_a_site_path_by_its_cdn_addresses(): void
    {
        $this->assertSame([], CdnPurge::addressesOf('uploads/a.jpg'));

        $this->cloudflare();

        $this->assertSame(['https://bucket.example.com/a.jpg'], CdnPurge::addressesOf('https://bucket.example.com/a.jpg'));
        $this->assertSame(['//bucket.example.com/a.jpg'], CdnPurge::addressesOf('//bucket.example.com/a.jpg'));
        $this->assertSame(
            ['https://cdn.test/storage/uploads/a.jpg', 'https://cdn.test/storage/uploads/a.jpg.webp'],
            CdnPurge::addressesOf('uploads/a.jpg'),
        );
        $this->assertSame([], CdnPurge::addressesOf('/robots.txt'));
        // A full address in capitals is still one; a doubled slash inside a path is not.
        $this->assertSame(['HTTPS://bucket.example.com/a.jpg'], CdnPurge::addressesOf('HTTPS://bucket.example.com/a.jpg'));
        $this->assertSame(
            ['https://cdn.test/storage/uploads//a.jpg', 'https://cdn.test/storage/uploads//a.jpg.webp'],
            CdnPurge::addressesOf('uploads//a.jpg'),
        );
    }

    public function test_texts_are_left_alone_when_no_path_goes_through_the_cdn(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.paths' => []]);

        $this->assertSame('<img src="/storage/a.jpg">', Cdn::html('<img src="/storage/a.jpg">'));
    }

    public function test_no_purge_when_turned_off(): void
    {
        Storage::fake('public');
        $this->cloudflare();
        config(['atrium.cdn.purge.on_delete' => false]);
        Http::fake();
        $this->withoutDefer();

        $upload = $this->actingAs($this->admin(), 'admin')
            ->post('/admin/upload', ['file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n".str_repeat(' ', 10240))]);
        $this->deleteJson('/admin/filemanager', ['id' => $upload->json('id')])->assertOk();

        Http::assertNothingSent();
    }

    public function test_a_remote_disk_keeps_its_own_address(): void
    {
        config(['filesystems.disks.media' => [
            'driver' => 's3', 'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'bucket' => 'media',
            'url' => 'https://media.cdn.test',
        ]]);

        $this->assertSame('https://media.cdn.test/uploads/2026/11/a.jpg', MediaUrl::forStored('media', 'uploads/2026/11/a.jpg'));
        $this->assertSame('/storage/uploads/a.jpg', MediaUrl::forStored('public', 'uploads/a.jpg'));
    }

    /**
     * End to end against an S3-compatible server: the upload lands in the
     * bucket, the field keeps the CDN address, deletion removes and purges.
     * ATRIUM_TEST_S3_ENDPOINT=http://127.0.0.1:9000 (MinIO, S3Mock), bucket
     * "media" (ATRIUM_TEST_S3_MEDIA_BUCKET).
     */
    public function test_uploads_to_s3_behind_a_cdn(): void
    {
        $endpoint = getenv('ATRIUM_TEST_S3_ENDPOINT');

        if (! is_string($endpoint) || $endpoint === '') {
            $this->markTestSkipped('Set ATRIUM_TEST_S3_ENDPOINT to an S3-compatible server.');
        }

        config([
            'filesystems.disks.media' => [
                'driver' => 's3',
                'key' => getenv('ATRIUM_TEST_S3_KEY') ?: 'test',
                'secret' => getenv('ATRIUM_TEST_S3_SECRET') ?: 'test',
                'region' => 'us-east-1',
                'bucket' => getenv('ATRIUM_TEST_S3_MEDIA_BUCKET') ?: 'media',
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => true,
                'url' => 'https://media.cdn.test',
                'throw' => true,
            ],
            'atrium.uploads.disk' => 'media',
        ]);
        $this->cloudflare();
        Http::fake(['api.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->withoutDefer();

        $upload = $this->actingAs($this->admin(), 'admin')
            ->post('/admin/upload', ['file' => UploadedFile::fake()->image('photo.jpg', 40, 30)])
            ->assertOk();
        $path = (string) $upload->json('path');

        $this->assertSame('https://media.cdn.test/'.$path, $upload->json('url'));
        $this->assertTrue(Storage::disk('media')->exists($path));
        $this->assertFalse(Storage::disk('media')->exists($path.'.webp'), 'No webp copy on a remote disk.');
        $this->assertSame('https://media.cdn.test/'.$path, MediaItem::query()->findOrFail($upload->json('id'))->url);
        // Drawn as stored: S3's address is already the CDN.
        $this->assertSame('https://media.cdn.test/'.$path, MediaUrl::to($upload->json('url')));

        $this->deleteJson('/admin/filemanager', ['id' => $upload->json('id')])->assertOk();

        $this->assertFalse(Storage::disk('media')->exists($path));
        Http::assertSent(fn (Request $request): bool => ($request['files'] ?? null) === ['https://media.cdn.test/'.$path]);
    }

    /**
     * @param  Application  $app
     */
    protected function cdnAssets($app): void
    {
        $app['config']->set('atrium.cdn.url', 'https://cdn.test');
        $app['config']->set('atrium.cdn.assets', true);
    }

    #[DefineEnvironment('cdnAssets')]
    public function test_built_scripts_and_styles_come_from_the_cdn_when_asked(): void
    {
        $this->withVite();
        File::ensureDirectoryExists(public_path(self::FOLDER));
        file_put_contents(public_path(self::FOLDER.'/manifest.json'), json_encode([
            'resources/js/app.js' => ['file' => 'assets/app-1a2b.js', 'src' => 'resources/js/app.js', 'isEntry' => true],
        ]));
        Vite::useBuildDirectory(self::FOLDER);

        $this->assertSame('https://cdn.test/'.self::FOLDER.'/assets/app-1a2b.js', Vite::asset('resources/js/app.js'));
    }

    /**
     * @param  Application  $app
     */
    protected function cdnWithoutAssets($app): void
    {
        $app['config']->set('atrium.cdn.url', 'https://cdn.test');
    }

    #[DefineEnvironment('cdnWithoutAssets')]
    public function test_built_scripts_stay_on_the_site_by_default(): void
    {
        $this->withVite();
        File::ensureDirectoryExists(public_path(self::FOLDER));
        file_put_contents(public_path(self::FOLDER.'/manifest.json'), json_encode([
            'resources/js/app.js' => ['file' => 'assets/app-1a2b.js', 'src' => 'resources/js/app.js', 'isEntry' => true],
        ]));
        Vite::useBuildDirectory(self::FOLDER);

        $this->assertSame(url(self::FOLDER.'/assets/app-1a2b.js'), Vite::asset('resources/js/app.js'));
    }

    public function test_addresses_may_be_written_with_spaces_and_a_trailing_slash(): void
    {
        config(['atrium.cdn.url' => ' https://cdn.test/ ', 'atrium.cdn.images.driver' => 'imgproxy', 'atrium.cdn.images.url' => ' https://img.test/ ']);

        $this->assertSame('https://cdn.test/storage/a.pdf', MediaUrl::to('a.pdf'));
        $this->assertStringStartsWith('https://img.test/insecure/rs:', Cdn::image('/storage/a.jpg', 640));
    }

    public function test_an_unknown_image_driver_resizes_nothing(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.images.driver' => 'fastly']);

        $this->assertSame('https://cdn.test/storage/a.jpg', MediaUrl::to('/storage/a.jpg', 640));
        $this->assertNull(Cdn::srcset('https://cdn.test/storage/a.jpg'));
    }

    public function test_widths_come_from_the_config_in_order_and_round_up_to_the_next_one(): void
    {
        // As from .env: strings, and a quality in quotes.
        config([
            'atrium.cdn.url' => 'https://cdn.test',
            'atrium.cdn.images.driver' => 'bunny',
            'atrium.cdn.images.quality' => '75',
            'atrium.cdn.images.widths' => ['800', 0, -5, 400],
        ]);
        $url = 'https://cdn.test/storage/a.jpg';

        $this->assertSame("{$url}?width=400&quality=75 400w, {$url}?width=800&quality=75 800w", Cdn::srcset($url));
        $this->assertSame("{$url}?width=400&quality=75", Cdn::image($url, 1));
        $this->assertSame("{$url}?width=400&quality=75", Cdn::image($url, 400));
        $this->assertSame("{$url}?width=800&quality=75", Cdn::image($url, 401));
        $this->assertSame("{$url}?width=800&quality=75", Cdn::image($url, 5000));

        // Nothing usable set: the built-in presets.
        config(['atrium.cdn.images.widths' => []]);
        $this->assertSame(
            implode(', ', array_map(static fn (int $width): string => "{$url}?width={$width}&quality=75 {$width}w", [320, 640, 960, 1280, 1920])),
            Cdn::srcset($url),
        );
    }

    public function test_paths_are_matched_as_written_and_only_text_counts(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.paths' => [5, '/up.load/']]);

        $this->assertSame(
            '<img src="https://cdn.test/up.load/a.jpg"><img src="/upXload/b.jpg">',
            Cdn::html('<img src="/up.load/a.jpg"><img src="/upXload/b.jpg">'),
        );
        $this->assertTrue(Cdn::serves('/up.load/a.jpg'));
        $this->assertFalse(Cdn::serves('/upXload/a.jpg'));
        $this->assertFalse(Cdn::serves('5/a.jpg'));
    }

    public function test_only_addresses_of_images_are_resized(): void
    {
        config(['atrium.cdn.images.driver' => 'template', 'atrium.cdn.images.template' => '{width}:{path}']);

        $this->assertSame('640:storage/A.JPG', Cdn::image('/storage/A.JPG', 640));
        $this->assertSame('/storage/a.jpg.pdf', Cdn::image('/storage/a.jpg.pdf', 640));
        // No path at all.
        $this->assertSame('https://cdn.test', Cdn::image('https://cdn.test', 640));
        $this->assertNull(Cdn::srcset('https://cdn.test'));
    }

    public function test_cloudflare_and_bunny_resize_only_what_passes_through_them(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.images.driver' => 'bunny']);

        $this->assertSame('https://other.test/a.jpg', Cdn::image('https://other.test/a.jpg', 640));
        $this->assertSame('https://cdn.test.example.com/a.jpg', Cdn::image('https://cdn.test.example.com/a.jpg', 640));

        // Only a zone for images: a path of this site does not pass through it.
        config(['atrium.cdn.url' => null, 'atrium.cdn.images.url' => 'https://img.test', 'atrium.cdn.images.driver' => 'cloudflare']);
        $this->assertSame('/storage/a.jpg', Cdn::image('/storage/a.jpg', 640));
        $this->assertSame('https://img.test/cdn-cgi/image/width=640,quality=82,format=auto/a.jpg', Cdn::image('https://img.test/a.jpg', 640));

        // Both: images are resized by their own zone.
        config(['atrium.cdn.url' => 'https://cdn.test']);
        $this->assertSame('https://img.test/cdn-cgi/image/width=640,quality=82,format=auto/a.jpg', Cdn::image('https://img.test/a.jpg', 640));

        // An address on the site's zone that merely starts like the images zone is fetched whole.
        config(['atrium.cdn.url' => 'https://img.tester']);
        $this->assertSame(
            'https://img.test/cdn-cgi/image/width=640,quality=82,format=auto/https://img.tester/a.jpg',
            Cdn::image('https://img.tester/a.jpg', 640),
        );
    }

    public function test_the_image_format_is_auto_unless_webp_or_avif(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.images.driver' => 'cloudflare']);
        $format = static function (mixed $format): string {
            config(['atrium.cdn.images.format' => $format]);

            return Cdn::image('https://cdn.test/a.jpg', 640);
        };

        $this->assertSame('https://cdn.test/cdn-cgi/image/width=640,quality=82,format=avif/a.jpg', $format('avif'));
        $this->assertSame('https://cdn.test/cdn-cgi/image/width=640,quality=82,format=webp/a.jpg', $format('webp'));
        $this->assertSame('https://cdn.test/cdn-cgi/image/width=640,quality=82,format=auto/a.jpg', $format('png'));
        $this->assertSame('https://cdn.test/cdn-cgi/image/width=640,quality=82,format=auto/a.jpg', $format(null));
    }

    public function test_imgproxy_signs_only_with_both_a_key_and_a_salt_in_hex(): void
    {
        $key = '943b421c9eb07c830af81030552c86009268de4e532ba2ee2eab8247c6da0881';
        $salt = '520f986b998545b4785e0defbc4f3c1203f22de2374a3d53cb7a7fe9fea309c5';
        config(['atrium.cdn.images.driver' => 'imgproxy', 'atrium.cdn.images.url' => 'https://img.test']);

        foreach ([[$key, ''], ['', $salt], ['zz', $salt], [$key, 'abc'], [null, null]] as [$k, $s]) {
            config(['atrium.cdn.images.key' => $k, 'atrium.cdn.images.salt' => $s]);

            $this->assertStringStartsWith('https://img.test/insecure/', Cdn::image('https://shop.test/a.jpg', 640), "key {$k}, salt {$s}");
        }
    }

    public function test_thumbnails_ask_for_twice_the_size_they_are_shown_at(): void
    {
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.images.driver' => 'bunny', 'atrium.cdn.images.widths' => [100, 159, 160, 320]]);

        // A 50px cell, an 80px gadget picture.
        $this->assertSame('https://cdn.test/storage/a.jpg?width=100&quality=82', ImageColumn::make('picture')->formatValue('a.jpg', null));
        $this->assertSame(
            'https://cdn.test/storage/a.jpg?width=160&quality=82',
            (new ReflectionMethod(RecentElementsGadget::class, 'imageUrl'))->invoke(new RecentElementsGadget, 'a.jpg'),
        );
    }

    public function test_a_refusal_says_the_status_and_the_start_of_the_answer(): void
    {
        $this->cloudflare();
        Http::fake([
            'api.cloudflare.com/*' => Http::response(['success' => false], 200),
            'api.bunny.net/*' => Http::response('a'.str_repeat('ё', 400), 500),
        ]);

        try {
            CdnPurge::everything();
            $this->fail('An answer without success must throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cloudflare refused the purge: HTTP 200 {"success":false}', $exception->getMessage());
        }

        config(['atrium.cdn.purge.driver' => 'bunny', 'atrium.cdn.purge.bunny.key' => 'bunny-key', 'atrium.cdn.purge.bunny.pull_zone' => '42']);

        try {
            CdnPurge::urls(['https://cdn.test/a.jpg']);
            $this->fail('An error status must throw.');
        } catch (RuntimeException $exception) {
            // The first 300 characters, not bytes.
            $this->assertSame('BunnyCDN refused the purge: HTTP 500 a'.str_repeat('ё', 299), $exception->getMessage());
        }
    }

    public function test_a_file_on_disk_is_purged_with_and_without_its_version(): void
    {
        File::ensureDirectoryExists(public_path(self::FOLDER));
        file_put_contents(public_path(self::FOLDER.'/a.jpg'), 'jpg');
        touch(public_path(self::FOLDER.'/a.jpg'), 1_800_000_000);
        file_put_contents(public_path(self::FOLDER.'/a.jpg.webp'), 'webp');
        touch(public_path(self::FOLDER.'/a.jpg.webp'), 1_800_000_100);
        config(['atrium.cdn.url' => 'https://cdn.test', 'atrium.cdn.paths' => ['/'.self::FOLDER.'/']]);
        $url = 'https://cdn.test/'.self::FOLDER.'/a.jpg';

        $this->assertSame(
            [$url, $url.'?v='.base_convert('1800000000', 10, 36), $url.'.webp', $url.'.webp?v='.base_convert('1800000100', 10, 36)],
            CdnPurge::addressesOf('/'.self::FOLDER.'/a.jpg'),
        );
    }

    public function test_a_webp_copy_is_made_only_when_asked_and_only_on_a_disk_of_this_server(): void
    {
        Storage::fake('public');
        Storage::fake('remote');
        config(['filesystems.disks.remote.driver' => 's3']);
        $admin = $this->admin();
        $upload = fn (): string => (string) $this->actingAs($admin, 'admin')
            ->post('/admin/upload', ['file' => UploadedFile::fake()->image('a.jpg', 400, 300)])
            ->assertOk()
            ->json('path');

        $path = $upload();
        Storage::disk('public')->assertExists($path.'.webp');
        $this->assertNotNull(MediaItem::query()->where('path', $path)->value('collection_id'), 'Uploads go to their collection.');

        config(['atrium.uploads.webp' => false]);
        Storage::disk('public')->assertMissing($upload().'.webp');

        config(['atrium.uploads.webp' => true, 'atrium.uploads.disk' => 'remote']);
        $path = $upload();
        Storage::disk('remote')->assertExists($path);
        Storage::disk('remote')->assertMissing($path.'.webp');
    }

    public function test_a_file_the_library_does_not_erase_is_not_purged(): void
    {
        $this->cloudflare();
        Http::fake();
        $this->withoutDefer();
        $this->actingAs($this->admin(), 'admin');

        foreach ([['disk' => null, 'path' => 'a.jpg'], ['disk' => 'public', 'path' => null]] as $where) {
            $item = MediaItem::create(['name' => 'a.jpg', 'url' => '/storage/a.jpg', ...$where]);

            $this->deleteJson('/admin/filemanager', ['id' => $item->id])->assertOk();
        }

        Http::assertNothingSent();
    }

    public function test_the_file_manager_deletes_a_file_by_its_path_too(): void
    {
        Storage::fake('public');
        $path = (string) $this->actingAs($this->admin(), 'admin')
            ->post('/admin/upload', ['file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n".str_repeat(' ', 10240))])
            ->json('path');

        $response = app(FileManagerController::class)->destroy(HttpRequest::create('/admin/filemanager', 'DELETE', ['path' => $path]));

        $this->assertSame(200, $response->getStatusCode());
        Storage::disk('public')->assertMissing($path);
        $this->assertFalse(MediaItem::query()->where('path', $path)->exists());
    }

    public function test_the_file_manager_takes_an_upload_called_directly(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin(), 'admin');

        $response = app(FileManagerController::class)->upload(HttpRequest::create('/admin/upload', 'POST', [], [], ['file' => UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n".str_repeat(' ', 10240))]));

        $path = (string) $response->getData(true)['path'];
        $this->assertSame(200, $response->getStatusCode());
        Storage::disk('public')->assertExists($path);
        $this->assertTrue(MediaItem::query()->where('path', $path)->exists());
    }

    public function test_a_file_of_the_public_disk_is_kept_by_its_path_whatever_its_address(): void
    {
        config(['filesystems.disks.public.url' => 'https://shop.test/storage']);

        $this->assertSame('/storage/uploads/a.jpg', MediaUrl::forStored('public', '/uploads/a.jpg'));
    }

    private function cloudflare(): void
    {
        config([
            'atrium.cdn.url' => 'https://cdn.test',
            'atrium.cdn.purge.driver' => 'cloudflare',
            'atrium.cdn.purge.cloudflare.zone' => 'zone-1',
            'atrium.cdn.purge.cloudflare.token' => 'cf-token',
        ]);
    }
}
