<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Dashboard\Gadget;
use Atrium\Admin\Dashboard\Gadgets\PerformanceGadget;
use Atrium\Admin\Dashboard\Gadgets\RecentElementsGadget;
use Atrium\Admin\Dashboard\Gadgets\SiteSpeedGadget;
use Atrium\Admin\Dashboard\PageSpeedStats;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\PageTiming;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class DashboardGadgetsTest extends TestCase
{
    /**
     * @param  array<int,int>  $durations
     */
    private function timings(array $durations, string $path = '/'): void
    {
        foreach ($durations as $ms) {
            PageTiming::query()->create(['path' => $path, 'duration_ms' => $ms]);
        }
    }

    public function test_page_speed_is_summed_up_over_the_window(): void
    {
        $this->travelTo(now()->subDays(8));
        $this->timings([90000], '/ancient');
        $this->travelBack();
        $this->timings([100, 300], '/a');
        $this->timings([200, 400], '/b');

        $stats = new PageSpeedStats;

        $this->assertSame(4, $stats->samples());
        $this->assertSame(300, $stats->medianMs());
        $this->assertSame(250.0, $stats->averageMs());
        // Four pages in one second of work.
        $this->assertSame(4.0, $stats->pagesPerSecond());
        $this->assertSame(7, $stats->windowDays());
        $this->assertSame([
            ['path' => '/b', 'ms' => 300, 'hits' => 2],
            ['path' => '/a', 'ms' => 200, 'hits' => 2],
        ], $stats->slowest());
    }

    public function test_the_median_of_an_odd_count_is_the_middle_one(): void
    {
        $this->timings([5, 1000, 20]);

        $this->assertSame(20, (new PageSpeedStats)->medianMs());
    }

    public function test_only_the_five_slowest_pages_are_listed_with_rounded_times(): void
    {
        foreach ([10, 20, 30, 40, 50, 60] as $ms) {
            $this->timings([$ms, $ms + 1], "/page-{$ms}");
        }

        $slowest = (new PageSpeedStats)->slowest();

        $this->assertSame(['/page-60', '/page-50', '/page-40', '/page-30', '/page-20'], array_column($slowest, 'path'));
        // 60.5 ms rounds up.
        $this->assertSame(61, $slowest[0]['ms']);
    }

    public function test_without_measurements_there_are_no_numbers(): void
    {
        $stats = new PageSpeedStats;

        $this->assertSame(0, $stats->samples());
        $this->assertNull($stats->medianMs());
        $this->assertNull($stats->averageMs());
        $this->assertNull($stats->pagesPerSecond());
        $this->assertSame([], $stats->slowest());
    }

    public function test_pages_that_took_no_time_give_no_rate(): void
    {
        $this->timings([0, 0]);

        $stats = new PageSpeedStats;

        $this->assertSame(2, $stats->samples());
        $this->assertSame(0.0, $stats->averageMs());
        $this->assertNull($stats->pagesPerSecond());
    }

    public function test_a_shorter_window_leaves_older_measurements_out(): void
    {
        $this->travelTo(now()->subDays(3)->subHour());
        $this->timings([900]);
        $this->travelBack();
        $this->timings([100]);

        $this->assertSame(1, (new PageSpeedStats(3))->samples());
        $this->assertSame(3, (new PageSpeedStats(3))->windowDays());
        $this->assertSame(2, (new PageSpeedStats(7))->samples());
    }

    public function test_the_summary_is_cached_for_the_given_time(): void
    {
        $this->timings([100]);
        $this->assertSame(1, (new PageSpeedStats(7, 60))->samples());

        $this->timings([200]);
        $this->assertSame(1, (new PageSpeedStats(7, 60))->samples());
        $this->assertSame(1, Cache::get('atrium.page-speed.7')['samples']);

        $this->travel(61)->seconds();
        $this->assertSame(2, (new PageSpeedStats(7, 60))->samples());
    }

    public function test_the_summary_is_worked_out_once_per_instance(): void
    {
        $this->timings([100]);
        $stats = new PageSpeedStats;
        $this->assertSame(1, $stats->samples());

        $this->timings([200]);
        Cache::flush();

        $this->assertSame(1, $stats->samples());
    }

    public function test_the_summary_is_kept_for_five_minutes_by_default(): void
    {
        $this->timings([100]);
        $this->assertSame(1, (new PageSpeedStats)->samples());

        $this->timings([200]);
        $this->travel(299)->seconds();
        $this->assertSame(1, (new PageSpeedStats)->samples());

        $this->travel(1)->seconds();
        $this->assertSame(2, (new PageSpeedStats)->samples());
    }

    public function test_an_empty_window_is_a_result_too_and_is_kept(): void
    {
        $this->assertSame(0, (new PageSpeedStats)->samples());

        $this->assertSame(
            ['samples' => 0, 'median_ms' => null, 'average_ms' => null, 'rate' => null, 'slowest' => []],
            Cache::get('atrium.page-speed.7'),
        );
    }

    public function test_the_summary_has_the_right_types_when_the_database_hands_everything_over_as_text(): void
    {
        // MySQL and PostgreSQL return aggregates as strings; SQLite can be made to do the same.
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        $this->beforeApplicationDestroyed(fn () => $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false));
        $this->timings([10, 10, 11, 11, 10], '/a');
        $this->timings([20, 20], '/10');

        $stats = new PageSpeedStats;

        $this->assertSame(7, $stats->samples());
        $this->assertSame(11, $stats->medianMs());
        $this->assertSame(
            [
                'samples' => 7,
                'median_ms' => 11,
                'average_ms' => 92 / 7,
                'rate' => 7 / (92 / 1000),
                // 10.4 rounds down and 20 stays: a slowest list by average, rounded, not rounded up.
                'slowest' => [['path' => '/10', 'ms' => 20, 'hits' => 2], ['path' => '/a', 'ms' => 10, 'hits' => 5]],
            ],
            Cache::get('atrium.page-speed.7'),
        );
        $this->assertSame(
            [['path' => '/10', 'ms' => 20, 'hits' => 2], ['path' => '/a', 'ms' => 10, 'hits' => 5]],
            $stats->slowest(),
        );
    }

    public function test_the_average_is_a_float_even_when_it_is_whole(): void
    {
        $this->timings([100, 300]);

        $this->assertSame(200.0, (new PageSpeedStats)->averageMs());
        $this->assertSame(200.0, Cache::get('atrium.page-speed.7')['average_ms']);
    }

    public function test_the_gadgets_use_the_summary_they_are_given(): void
    {
        $this->timings([100]);
        $stats = new PageSpeedStats(3);

        $this->assertSame(3, (new SiteSpeedGadget([], $stats))->data()['windowDays']);
        $this->assertSame(3, (new PerformanceGadget([], $stats))->data()['windowDays']);
        $this->assertSame(7, (new SiteSpeedGadget)->data()['windowDays']);
        $this->assertSame(7, (new PerformanceGadget)->data()['windowDays']);
    }

    public function test_a_broken_cache_leaves_the_dashboard_without_numbers(): void
    {
        $this->timings([100]);
        Cache::shouldReceive('remember')->once()->andThrow(new RuntimeException('cache is down'));

        $stats = new PageSpeedStats;

        $this->assertSame(0, $stats->samples());
        $this->assertNull($stats->medianMs());
        $this->assertSame([], $stats->slowest());
    }

    public function test_the_performance_gadget_shows_the_rate_and_average(): void
    {
        $this->timings([100, 300], '/a');
        $this->timings([200, 400], '/b');

        $gadget = new PerformanceGadget;

        $this->assertSame('PerformanceGadget', $gadget->component());
        $this->assertSame('Performance monitor', $gadget->title());
        $this->assertSame('half', $gadget->width());
        $this->assertSame([
            'rate' => '4,00',
            'caption' => 'Current score',
            'unit' => 'pages per second',
            'averageSeconds' => '0,250',
            'samples' => 4,
            'windowDays' => 7,
            'slowest' => [
                ['path' => '/b', 'ms' => 300, 'hits' => 2],
                ['path' => '/a', 'ms' => 200, 'hits' => 2],
            ],
        ], $gadget->data());
    }

    public function test_the_performance_gadget_groups_thousands(): void
    {
        $this->timings([1, 1, 1, 1, 1, 1, 1, 1, 1, 1500]);

        $data = (new PerformanceGadget)->data();

        $this->assertSame('6,63', $data['rate']);
        $this->assertSame('0,151', $data['averageSeconds']);

        Cache::flush();
        PageTiming::query()->delete();
        $this->timings([1, 1]);
        $this->assertSame('1 000,00', (new PerformanceGadget)->data()['rate']);

        Cache::flush();
        PageTiming::query()->delete();
        $this->timings([1234567]);
        $this->assertSame('1 234,567', (new PerformanceGadget)->data()['averageSeconds']);
    }

    public function test_the_performance_gadget_without_measurements(): void
    {
        $data = (new PerformanceGadget(['window_days' => 3]))->data();

        $this->assertNull($data['rate']);
        $this->assertNull($data['averageSeconds']);
        $this->assertSame(0, $data['samples']);
        $this->assertSame(3, $data['windowDays']);
        $this->assertSame([], $data['slowest']);
    }

    public function test_the_performance_gadget_takes_its_title_and_width_from_options(): void
    {
        $this->assertSame('Speed', (new PerformanceGadget(['title' => 'Speed']))->title());
        $this->assertSame('full', (new PerformanceGadget(['width' => 'full']))->width());
        $this->assertSame('half', (new PerformanceGadget(['width' => 'wide']))->width());
    }

    /**
     * @return array<string,array{int,string,float,string}>
     */
    public static function medians(): array
    {
        return [
            'almost instant: the pointer stays off the left edge' => [50, 'Very fast', 4.0, '0,05'],
            'just under half a second' => [499, 'Very fast', 19.96, '0,50'],
            'half a second' => [500, 'Fast', 20.0, '0,50'],
            'just under a second' => [999, 'Fast', 39.96, '1,00'],
            'a second' => [1000, 'Not fast', 40.0, '1,00'],
            'odd number' => [1234, 'Not fast', 49.36, '1,23'],
            'a second and a half' => [1500, 'Slow', 60.0, '1,50'],
            'two seconds' => [2000, 'Very slow', 80.0, '2,00'],
            'just under the scale: the pointer stays off the right edge' => [2499, 'Very slow', 98.0, '2,50'],
            'the end of the scale' => [2500, 'Very slow', 98.0, '2,50'],
            'far past the scale' => [10000, 'Very slow', 98.0, '10,00'],
        ];
    }

    #[DataProvider('medians')]
    public function test_the_site_speed_gadget_places_the_median_on_its_scale(int $ms, string $label, float $percent, string $seconds): void
    {
        $this->timings([$ms]);

        $data = (new SiteSpeedGadget)->data();

        $this->assertSame($label, $data['label']);
        $this->assertSame($percent, $data['percent']);
        $this->assertSame($seconds, $data['seconds']);
    }

    public function test_the_site_speed_gadget_describes_its_scale(): void
    {
        $this->timings([100, 200]);

        $gadget = new SiteSpeedGadget(['window_days' => 2]);
        $data = $gadget->data();

        $this->assertSame('SiteSpeedGadget', $gadget->component());
        $this->assertSame('Site speed', $gadget->title());
        $this->assertSame('half', $gadget->width());
        $this->assertSame('s', $data['unit']);
        $this->assertSame(2, $data['samples']);
        $this->assertSame(2, $data['windowDays']);
        $this->assertSame(['Very fast', 'Fast', 'Not fast', 'Slow', 'Very slow'], $data['bands']);
        $this->assertSame('2,5', $data['scaleSeconds']);
    }

    public function test_the_site_speed_gadget_without_measurements(): void
    {
        $data = (new SiteSpeedGadget)->data();

        $this->assertNull($data['seconds']);
        $this->assertNull($data['percent']);
        $this->assertSame('no data', $data['label']);
        $this->assertSame(0, $data['samples']);
    }

    public function test_the_site_speed_gadget_takes_its_title_and_width_from_options(): void
    {
        $this->assertSame('Speed', (new SiteSpeedGadget(['title' => 'Speed']))->title());
        $this->assertSame('full', (new SiteSpeedGadget(['width' => 'full']))->width());
        $this->assertSame('half', (new SiteSpeedGadget(['width' => 'wide']))->width());
    }

    public function test_the_site_speed_gadget_is_translated(): void
    {
        $this->timings([100]);
        app()->setLocale('ru');

        $data = (new SiteSpeedGadget)->data();

        $this->assertSame(__('atrium::dashboard.site_speed.bands.very_fast', [], 'ru'), $data['label']);
        $this->assertNotSame('Very fast', $data['label']);
    }

    public function test_a_gadget_is_full_width_and_shown_unless_told_otherwise(): void
    {
        $gadget = new RecentElementsGadget(['model' => Post::class]);

        $this->assertSame('full', $gadget->width());
        $this->assertSame('half', (new RecentElementsGadget(['width' => 'half']))->width());
        $this->assertSame('full', (new RecentElementsGadget(['width' => 'wide']))->width());
        $this->assertTrue($gadget->isVisible());
        $this->assertFalse((new RecentElementsGadget(['model' => Post::class, 'enabled' => false]))->isVisible());
        $this->assertTrue((new RecentElementsGadget(['model' => Post::class, 'enabled' => 1]))->isVisible());
    }

    public function test_a_gadget_turns_into_what_its_component_needs(): void
    {
        $gadget = new class(['width' => 'half']) extends Gadget
        {
            public function component(): string
            {
                return 'Tile';
            }

            public function title(): string
            {
                return 'A tile';
            }

            public function data(): array
            {
                return ['answer' => 42];
            }
        };

        $this->assertSame(['component' => 'Tile', 'title' => 'A tile', 'width' => 'half', 'data' => ['answer' => 42]], $gadget->toArray());
    }

    public function test_the_recent_items_gadget_lists_the_last_changed_records(): void
    {
        $this->travelTo('2026-08-01 10:00:00');
        $old = Post::query()->create(['name' => 'Old']);
        $this->travelTo('2026-08-03 10:00:00');
        $new = Post::query()->create(['name' => 'New']);
        $twin = Post::query()->create(['name' => '']);
        $this->travelBack();

        $data = (new RecentElementsGadget(['model' => Post::class]))->data();

        $this->assertSame([
            ['id' => $twin->id, 'name' => '#'.$twin->id, 'date' => '3 August 2026', 'image' => null, 'url' => route('admin.posts.edit', $twin->id)],
            ['id' => $new->id, 'name' => 'New', 'date' => '3 August 2026', 'image' => null, 'url' => route('admin.posts.edit', $new->id)],
            ['id' => $old->id, 'name' => 'Old', 'date' => '1 August 2026', 'image' => null, 'url' => route('admin.posts.edit', $old->id)],
        ], $data['items']);
        $this->assertSame(route('admin.posts.index'), $data['allUrl']);
        $this->assertSame('All items', $data['allLabel']);
    }

    public function test_the_recent_items_gadget_writes_dates_in_the_interface_language(): void
    {
        $this->travelTo('2026-08-03 10:00:00');
        Post::query()->create(['name' => 'New']);
        app()->setLocale('de');

        $this->assertSame('3 August 2026', (new RecentElementsGadget(['model' => Post::class]))->data()['items'][0]['date']);

        app()->setLocale('ru');
        $this->assertSame('3 августа 2026', (new RecentElementsGadget(['model' => Post::class]))->data()['items'][0]['date']);
    }

    /**
     * @return array<string,array{mixed,int}>
     */
    public static function counts(): array
    {
        return [
            'default' => [null, 5],
            'zero means one' => [0, 1],
            'negative means one' => [-3, 1],
            'within the range' => [7, 7],
            'the most' => [20, 20],
            'past the most' => [21, 20],
            'a numeric string' => ['3', 3],
        ];
    }

    #[DataProvider('counts')]
    public function test_the_recent_items_gadget_shows_between_one_and_twenty_records(mixed $count, int $shown): void
    {
        foreach (range(1, 22) as $i) {
            Post::query()->create(['name' => "Post {$i}"]);
        }

        $options = ['model' => Post::class] + ($count === null ? [] : ['count' => $count]);

        $this->assertCount($shown, (new RecentElementsGadget($options))->data()['items']);
    }

    public function test_the_recent_items_gadget_shows_the_picture_of_a_record(): void
    {
        $this->travelTo('2026-08-01 10:00:00');
        Handbook::query()->create(['name' => 'Stored', 'preview_picture' => 'articles/a.jpg']);
        $this->travelTo('2026-08-02 10:00:00');
        Handbook::query()->create(['name' => 'Moved', 'preview_picture' => '/upload/b.jpg']);
        $this->travelTo('2026-08-03 10:00:00');
        Handbook::query()->create(['name' => 'Remote', 'preview_picture' => 'https://cdn.example.com/c.jpg']);
        $this->travelTo('2026-08-04 10:00:00');
        Handbook::query()->create(['name' => 'None', 'preview_picture' => '']);
        $this->travelBack();

        $data = (new RecentElementsGadget(['model' => Handbook::class]))->data();

        $this->assertSame([null, 'https://cdn.example.com/c.jpg', asset('upload/b.jpg'), asset('storage/articles/a.jpg')], array_column($data['items'], 'image'));
        // Not a module of the panel: no links.
        $this->assertSame([null, null, null, null], array_column($data['items'], 'url'));
        $this->assertNull($data['allUrl']);
    }

    public function test_the_recent_items_gadget_of_a_model_without_dates_sorts_by_key(): void
    {
        $first = Undated::query()->create(['name' => 'First']);
        $second = Undated::query()->create(['name' => 'Second']);

        $items = (new RecentElementsGadget(['model' => Undated::class]))->data()['items'];

        $this->assertSame([$second->id, $first->id], array_column($items, 'id'));
        $this->assertSame([null, null], array_column($items, 'date'));
    }

    public function test_the_recent_items_gadget_survives_a_missing_table(): void
    {
        // A database of its own: on PostgreSQL a failed query would abort the
        // test's transaction on the main one.
        config(['database.connections.unmigrated' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);

        $data = (new RecentElementsGadget(['model' => Tableless::class]))->data();

        $this->assertSame([], $data['items']);
    }

    public function test_the_recent_items_gadget_without_a_model_shows_nothing(): void
    {
        foreach ([[], ['model' => 'Acme\\Missing'], ['model' => self::class], ['model' => 42]] as $options) {
            $gadget = new RecentElementsGadget($options);

            $this->assertSame(['items' => [], 'allUrl' => null, 'allLabel' => 'All items'], $gadget->data());
            $this->assertFalse($gadget->isVisible());
            $this->assertSame('Recent items', $gadget->title());
        }
    }

    public function test_the_recent_items_gadget_takes_its_title_from_the_option_then_the_module(): void
    {
        $this->assertSame('Latest', (new RecentElementsGadget(['model' => Post::class, 'title' => 'Latest']))->title());
        $this->assertSame('Posts', (new RecentElementsGadget(['model' => Post::class, 'title' => '']))->title());
        $this->assertSame('Posts', (new RecentElementsGadget(['model' => Post::class, 'title' => ['Latest']]))->title());
        $this->assertSame('Recent items', (new RecentElementsGadget(['model' => Handbook::class]))->title());
    }

    public function test_the_recent_items_gadget_is_hidden_from_an_administrator_without_the_right(): void
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $this->actingAs($user, 'admin');

        $this->assertFalse((new RecentElementsGadget(['model' => Post::class]))->isVisible());
        // A model outside the panel has no right to check.
        $this->assertTrue((new RecentElementsGadget(['model' => Handbook::class]))->isVisible());

        $reader = AdminUser::query()->create(['name' => 'Reader', 'email' => 'reader@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $reader->roles()->attach(AdminRole::query()->create(['name' => 'Reader', 'permissions' => ['posts.view']]));
        $this->actingAs($reader->fresh(), 'admin');
        $this->assertTrue((new RecentElementsGadget(['model' => Post::class]))->isVisible());
    }

    public function test_the_recent_items_gadget_is_shown_to_a_superadmin(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->assertTrue((new RecentElementsGadget(['model' => Post::class]))->isVisible());
    }

    public function test_the_recent_items_gadget_draws_with_its_own_component(): void
    {
        $this->assertSame('RecentElementsGadget', (new RecentElementsGadget)->component());
    }

    public function test_the_recent_items_of_a_module_without_routes_link_nowhere(): void
    {
        $post = Unrouted::query()->create(['name' => 'Chair']);

        $data = (new RecentElementsGadget(['model' => Unrouted::class]))->data();

        $this->assertSame($post->id, $data['items'][0]['id']);
        $this->assertNull($data['items'][0]['url']);
        $this->assertNull($data['allUrl']);
    }
}

/**
 * A module whose admin routes were never registered.
 */
final class Unrouted extends Model
{
    use InteractsWithAdmin;

    protected $table = 'posts';

    protected $guarded = [];

    public static function getAdminLabel(): string
    {
        return 'Unrouted';
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'unrouted';
    }
}

final class Undated extends Model
{
    public $timestamps = false;

    protected $table = 'posts';

    protected $guarded = [];
}

final class Tableless extends Model
{
    protected $connection = 'unmigrated';

    protected $table = 'no_such_table';
}
