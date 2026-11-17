<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use App\Models\Blog\RegenBooklet;
use App\Models\RegenFlyer;
use Atrium\Admin\Models\Slug;
use Atrium\Admin\Models\SlugHistory;
use Atrium\Admin\Tests\Fixtures\Leaflet;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

/**
 * atrium:slug:regenerate: cached addresses built again, for one model or
 * every model in app/Models, and old history cleaned up.
 */
final class SlugRegenerateTest extends TestCase
{
    private string $appPath;

    protected function setUp(): void
    {
        parent::setUp();

        // An app/ of its own: --all reads app/Models, and the real one is shared.
        $this->appPath = sys_get_temp_dir().'/atrium-slug-app-'.getmypid().'-'.uniqid();
        mkdir($this->appPath.'/Models/Blog', 0777, true);
        $this->app->useAppPath($this->appPath);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->appPath);

        parent::tearDown();
    }

    /**
     * Writes a model into the private app/Models and loads it.
     */
    private function model(string $relative, string $code): void
    {
        $file = $this->appPath.'/Models/'.$relative.'.php';
        file_put_contents($file, "<?php\n\n".$code."\n");

        $class = 'App\\Models\\'.str_replace('/', '\\', $relative);
        if (! class_exists($class, false)) {
            require $file;
        }
    }

    public function test_one_model_gets_its_cached_addresses_back(): void
    {
        Leaflet::query()->create(['name' => 'Opening hours', 'code' => 'hours']);
        Leaflet::query()->create(['name' => 'Price list', 'code' => 'prices']);
        Leaflet::query()->create(['name' => 'Untitled']);
        Slug::query()->delete();

        $this->artisan('atrium:slug:regenerate', ['model' => Leaflet::class])
            ->expectsOutput('Regenerating slugs for '.Leaflet::class.'...')
            ->expectsOutput('Regenerated 3 slugs')
            ->assertSuccessful();

        $this->assertSame(['hours', 'prices', 'untitled'], Slug::query()->orderBy('slug')->pluck('slug')->all());
    }

    public function test_a_model_is_required_unless_all_is_asked(): void
    {
        $this->artisan('atrium:slug:regenerate')
            ->expectsOutput('Please specify a model class or use --all option')
            ->assertFailed();
    }

    public function test_an_unknown_class_is_refused(): void
    {
        $this->artisan('atrium:slug:regenerate', ['model' => 'App\\Models\\Nothing'])
            ->expectsOutput('Model class not found: App\\Models\\Nothing')
            ->assertFailed();
    }

    public function test_a_model_without_addresses_is_refused(): void
    {
        $this->artisan('atrium:slug:regenerate', ['model' => Post::class])
            ->expectsOutput('Model '.Post::class.' does not implement HasSlug or use HasAdminSlug trait')
            ->assertFailed();
    }

    public function test_all_finds_the_models_with_addresses_in_app_models(): void
    {
        $this->model('RegenFlyer', 'namespace App\\Models; class RegenFlyer extends \\Atrium\\Admin\\Tests\\Fixtures\\Leaflet {}');
        $this->model('Blog/RegenBooklet', 'namespace App\\Models\\Blog; class RegenBooklet extends \\Atrium\\Admin\\Tests\\Fixtures\\Leaflet {}');
        $this->model('RegenPlain', 'namespace App\\Models; class RegenPlain extends \\Illuminate\\Database\\Eloquent\\Model {}');
        file_put_contents($this->appPath.'/Models/helpers.php', "<?php\n\n// no class here\n");

        RegenFlyer::query()->create(['name' => 'Flyer', 'code' => 'flyer']);
        RegenBooklet::query()->create(['name' => 'Booklet', 'code' => 'booklet']);
        Slug::query()->delete();

        $this->artisan('atrium:slug:regenerate', ['--all' => true])
            ->expectsOutput('Found 2 model(s)')
            ->expectsOutput('Processing App\\Models\\Blog\\RegenBooklet...')
            ->expectsOutput('  - Regenerated 2 slugs')
            ->expectsOutput('Processing App\\Models\\RegenFlyer...')
            ->expectsOutput('  - Regenerated 2 slugs')
            ->expectsOutput('Total: regenerated 4 slugs')
            ->doesntExpectOutputToContain('RegenPlain')
            ->assertSuccessful();

        $this->assertSame(4, Slug::query()->count());
    }

    public function test_all_without_app_models_finds_nothing(): void
    {
        (new Filesystem)->deleteDirectory($this->appPath.'/Models');

        $this->artisan('atrium:slug:regenerate', ['--all' => true])
            ->expectsOutput('No models found with HasSlug trait')
            ->assertSuccessful();
    }

    public function test_cleanup_drops_history_older_than_asked(): void
    {
        $leaflet = Leaflet::query()->create(['name' => 'Hours', 'code' => 'hours']);
        foreach (['ancient' => 400, 'old' => 40, 'recent' => 10] as $slug => $days) {
            $this->travelTo(now()->subDays($days));
            SlugHistory::query()->create(['sluggable_type' => Leaflet::class, 'sluggable_id' => $leaflet->id, 'slug' => $slug, 'full_path' => $slug]);
            $this->travelBack();
        }

        $this->artisan('atrium:slug:regenerate', ['--cleanup' => true])
            ->expectsOutput('Cleaning up slug history older than 365 days...')
            ->expectsOutput('Deleted 1 old history entries')
            ->assertSuccessful();
        $this->assertSame(['old', 'recent'], SlugHistory::query()->orderBy('slug')->pluck('slug')->all());

        $this->artisan('atrium:slug:regenerate', ['--cleanup' => true, '--cleanup-days' => '30', '--all' => true])
            ->expectsOutput('Deleted 1 old history entries')
            ->assertSuccessful();
        $this->assertSame(['recent'], SlugHistory::query()->pluck('slug')->all());
    }
}
