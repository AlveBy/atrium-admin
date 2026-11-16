<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Contracts\Sitemapable;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\RecordUrls;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Story;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The public address of any record, for the packages that link to records of
 * other modules: the module's own answer, the resolvers other packages add,
 * and the address rule.
 */
final class RecordUrlsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Story::class, Note::class]);
        $app['config']->set('atrium.public_urls', [Post::class => ['prefix' => 'posts']]);
    }

    protected function defineRoutes($router): void
    {
        Route::localized(function (): void {
            Route::get('stories/{code}', fn (string $code) => $code)->name('stories.show');
        });
    }

    private function urls(): RecordUrls
    {
        return $this->app->make(RecordUrls::class);
    }

    private function makePost(?string $code = 'hello'): Post
    {
        $post = new Post(['name' => 'Hello', 'active' => true]);
        $post->forceFill(['code' => $code])->save();

        return $post;
    }

    /**
     * A model that lists its own pages and says what it is told to.
     */
    private function sitemapable(?string $answer): Model
    {
        $model = new class extends Model implements Sitemapable
        {
            public ?string $answer = null;

            public static function sitemapQuery(): Builder
            {
                return self::query();
            }

            public function sitemapUrl(string $language): ?string
            {
                return $this->answer;
            }
        };
        $model->answer = $answer;

        return $model;
    }

    // ---- a module that lists its own pages ----

    public function test_a_module_that_lists_its_pages_answers_in_the_language_asked(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $story = Story::query()->create(['name' => ['en' => 'Hello', 'de' => 'Hallo'], 'code' => ['en' => 'hello', 'de' => 'hallo']]);

        $this->assertSame(url('stories/hello'), $this->urls()->url($story, 'en'));
        $this->assertSame(url('de/stories/hallo'), $this->urls()->url($story, 'de'));
    }

    public function test_the_language_is_the_current_one_when_none_is_given(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $story = Story::query()->create(['name' => ['en' => 'Hello', 'de' => 'Hallo'], 'code' => ['en' => 'hello', 'de' => 'hallo']]);

        $this->assertSame(url('stories/hello'), $this->urls()->url($story));

        $this->app->setLocale('de');

        $this->assertSame(url('de/stories/hallo'), $this->urls()->url($story));
    }

    public function test_a_record_has_no_page_in_a_language_it_is_not_written_in(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $story = Story::query()->create(['name' => ['en' => 'Hello'], 'code' => ['en' => 'hello']]);

        $this->assertNull($this->urls()->url($story, 'de'));
    }

    /**
     * @return array<string,array{0:?string,1:?string}>
     */
    public static function answers(): array
    {
        return [
            'an address' => ['https://example.com/page', 'https://example.com/page'],
            'a path' => ['/page', '/page'],
            'no page' => [null, null],
            'an empty address is no page' => ['', null],
            'a zero is an address' => ['0', '0'],
        ];
    }

    #[DataProvider('answers')]
    public function test_the_answer_of_a_module_is_the_address_unless_it_is_empty(?string $answer, ?string $expected): void
    {
        $this->assertSame($expected, $this->urls()->url($this->sitemapable($answer)));
    }

    public function test_a_module_that_lists_its_pages_is_not_overruled_by_a_resolver_or_a_rule(): void
    {
        config()->set('atrium.public_urls', [Story::class => ['prefix' => 'stories']]);
        $this->urls()->extend(fn (): string => '/from-a-resolver');

        $english = Story::query()->create(['name' => ['en' => 'Hello'], 'code' => ['en' => 'hello']]);

        $this->assertSame(url('stories/hello'), $this->urls()->url($english));
        $this->assertNull($this->urls()->url($english, 'de'), 'no page stays no page');
        $this->assertSame('/own', $this->urls()->url($this->sitemapable('/own')));
        $this->assertNull($this->urls()->url($this->sitemapable(null)));
    }

    // ---- resolvers ----

    /**
     * What each resolver answers, in the order they were added, and what the address is.
     *
     * @return array<string,array{0:array<int,mixed>,1:?string}>
     */
    public static function resolvers(): array
    {
        return [
            'the first address wins' => [['/a', '/b'], '/a'],
            'a resolver that does not know the record is skipped' => [[null, '/b'], '/b'],
            'an empty address is skipped' => [['', '/b'], '/b'],
            'a zero is an address' => [['0', '/b'], '0'],
            'a number is skipped' => [[5, '/b'], '/b'],
            'true is skipped' => [[true, '/b'], '/b'],
            'a list is skipped' => [[['/a'], '/b'], '/b'],
            'false: the record has no page' => [[false, '/b'], null],
            'false after some that do not know' => [[null, '', false, '/b'], null],
            'an address before a false' => [['/a', false], '/a'],
            'none knows: the address rule answers' => [[null, '', 7], 'RULE'],
            'no resolvers: the address rule answers' => [[], 'RULE'],
        ];
    }

    /**
     * @param  array<int,mixed>  $answers
     */
    #[DataProvider('resolvers')]
    public function test_the_first_resolver_that_knows_the_record_answers(array $answers, ?string $expected): void
    {
        $post = $this->makePost();

        foreach ($answers as $answer) {
            $this->urls()->extend(fn () => $answer);
        }

        $this->assertSame($expected === 'RULE' ? url('posts/hello') : $expected, $this->urls()->url($post));
    }

    public function test_a_resolver_is_given_the_record_and_the_language(): void
    {
        $post = $this->makePost();
        $seen = [];
        $this->urls()->extend(function (Model $record, string $language) use (&$seen): ?string {
            $seen[] = [$record, $language];

            return null;
        });

        $this->urls()->url($post, 'de');
        $this->urls()->url($post);

        $this->assertCount(2, $seen);
        $this->assertSame($post, $seen[0][0]);
        $this->assertSame('de', $seen[0][1]);
        $this->assertSame('en', $seen[1][1], 'the current language when none is given');
    }

    public function test_a_resolver_added_by_a_package_answers_for_its_own_records_only(): void
    {
        $post = $this->makePost();
        $note = Note::query()->create(['name' => 'Note', 'code' => 'note']);
        $this->urls()->extend(fn (Model $record, string $language): ?string => $record instanceof Note ? "/{$language}/notes/{$record->code}" : null);

        $this->assertSame('/en/notes/note', $this->urls()->url($note));
        $this->assertSame('/fr/notes/note', $this->urls()->url($note, 'fr'));
        $this->assertSame(url('posts/hello'), $this->urls()->url($post));
    }

    // ---- the address rule ----

    public function test_the_address_rule_gives_the_address_of_the_slug_field(): void
    {
        $this->assertSame(url('posts/hello'), $this->urls()->url($this->makePost('hello')));
    }

    public function test_a_record_without_a_code_has_no_address(): void
    {
        $this->assertNull($this->urls()->url($this->makePost(null)));
        $this->assertNull($this->urls()->url($this->makePost('')));
    }

    public function test_a_record_of_a_module_without_a_rule_has_no_address(): void
    {
        $this->assertNull($this->urls()->url(Note::query()->create(['name' => 'Note', 'code' => 'note'])));
    }

    // ---- which modules have pages ----

    /**
     * @return array<string,array{0:class-string,1:bool}>
     */
    public static function modules(): array
    {
        return [
            'one with an address rule' => [Post::class, true],
            'one that lists its own pages' => [Story::class, true],
            'one with neither' => [Note::class, false],
            'not a module' => [Model::class, false],
            'not a class' => ['No\\Such\\Class', false],
        ];
    }

    /**
     * @param  class-string  $model
     */
    #[DataProvider('modules')]
    public function test_a_module_has_pages_when_it_lists_them_or_has_an_address_rule(string $model, bool $has): void
    {
        $this->assertSame($has, $this->urls()->hasPages($model));
    }

    public function test_a_module_with_a_rule_has_pages_even_when_its_prefix_is_empty(): void
    {
        config()->set('atrium.public_urls', [Note::class => ['prefix' => '']]);

        $this->assertTrue($this->urls()->hasPages(Note::class));
    }
}
