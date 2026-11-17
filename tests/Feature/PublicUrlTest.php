<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

final class PublicUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PublicUrl::forgetPaths();
    }

    protected function tearDown(): void
    {
        PublicUrl::forgetPaths();

        parent::tearDown();
    }

    /**
     * Tours > Italy > Rome, and a section without a code under Tours.
     *
     * @return array<string,Handbook>
     */
    private function tree(): array
    {
        $tours = Handbook::query()->create(['name' => 'Tours', 'code' => 'tours']);
        $italy = Handbook::query()->create(['name' => 'Italy', 'code' => 'italy', 'parent_id' => $tours->id]);
        $rome = Handbook::query()->create(['name' => 'Rome', 'code' => 'rome', 'parent_id' => $italy->id]);
        $hidden = Handbook::query()->create(['name' => 'Hidden', 'parent_id' => $tours->id]);
        $hidden->forceFill(['code' => ''])->saveQuietly();

        return compact('tours', 'italy', 'rome', 'hidden');
    }

    private function rule(array $rule): void
    {
        config()->set('atrium.public_urls.'.Post::class, $rule);
    }

    public function test_a_model_without_a_rule_has_no_public_address(): void
    {
        $this->assertNull(PublicUrl::ruleFor(Post::class));

        config()->set('atrium.public_urls.'.Post::class, 'catalog');
        $this->assertNull(PublicUrl::ruleFor(Post::class));
    }

    public function test_a_rule_is_read_with_its_defaults(): void
    {
        $this->rule(['prefix' => '/catalog/']);

        $this->assertSame(['prefix' => 'catalog', 'parent' => null, 'tree' => null, 'parent_column' => 'parent_id'], PublicUrl::ruleFor(Post::class));

        $this->rule([]);
        $this->assertSame('', PublicUrl::ruleFor(Post::class)['prefix']);
    }

    public function test_a_parent_counts_only_with_a_tree(): void
    {
        $this->rule(['prefix' => 'catalog', 'parent' => 'section_id']);
        $this->assertNull(PublicUrl::ruleFor(Post::class)['parent']);

        $this->rule(['prefix' => 'catalog', 'parent' => 'section_id', 'tree' => Handbook::class, 'parent_column' => 'section_parent']);
        $this->assertSame(['prefix' => 'catalog', 'parent' => 'section_id', 'tree' => Handbook::class, 'parent_column' => 'section_parent'], PublicUrl::ruleFor(Post::class));
    }

    public function test_section_paths_join_the_codes_from_the_root(): void
    {
        ['tours' => $tours, 'italy' => $italy, 'rome' => $rome, 'hidden' => $hidden] = $this->tree();
        $orphan = Handbook::query()->create(['name' => 'Orphan', 'code' => 'orphan', 'parent_id' => 999]);

        $paths = PublicUrl::sectionPaths(Handbook::class);

        $this->assertSame('tours', $paths[$tours->id]);
        $this->assertSame('tours/italy', $paths[$italy->id]);
        $this->assertSame('tours/italy/rome', $paths[$rome->id]);
        // A section without a code adds nothing to the path.
        $this->assertSame('tours', $paths[$hidden->id]);
        // A parent that is gone ends the path.
        $this->assertSame('orphan', $paths[$orphan->id]);
        $this->assertCount(5, $paths);
    }

    public function test_a_loop_in_the_tree_stops_after_twenty_levels(): void
    {
        $a = Handbook::query()->create(['name' => 'A', 'code' => 'a']);
        $b = Handbook::query()->create(['name' => 'B', 'code' => 'b', 'parent_id' => $a->id]);
        $a->forceFill(['parent_id' => $b->id])->saveQuietly();

        $paths = PublicUrl::sectionPaths(Handbook::class);

        $this->assertCount(20, explode('/', $paths[$a->id]));
        $this->assertStringEndsWith('b/a', $paths[$a->id]);
        $this->assertStringEndsWith('a/b', $paths[$b->id]);
    }

    public function test_section_paths_are_remembered_until_forgotten(): void
    {
        ['italy' => $italy] = $this->tree();
        $this->assertSame('tours/italy', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);

        Handbook::query()->whereKey($italy->id)->update(['code' => 'italia']);
        $this->assertSame('tours/italy', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);

        PublicUrl::forgetPaths();
        $this->assertSame('tours/italia', PublicUrl::sectionPaths(Handbook::class)[$italy->id]);
    }

    public function test_a_field_that_is_not_a_slug_is_left_alone(): void
    {
        $this->rule(['prefix' => 'catalog']);
        $field = ['type' => 'text', 'name' => 'name'];

        $this->assertSame($field, PublicUrl::decorateSlugField($field, Post::class));
        $this->assertSame(['name' => 'code'], PublicUrl::decorateSlugField(['name' => 'code'], Post::class));
    }

    public function test_a_slug_field_of_a_model_without_pages_shows_no_preview(): void
    {
        $this->assertSame(['type' => 'slug', 'showPreview' => false], PublicUrl::decorateSlugField(['type' => 'slug'], Post::class));
    }

    public function test_a_slug_field_gets_the_prefix_and_the_section_paths(): void
    {
        $this->rule(['prefix' => 'news']);
        $this->assertSame(
            ['type' => 'slug', 'publicPrefix' => 'news', 'parentField' => null, 'parentPaths' => []],
            PublicUrl::decorateSlugField(['type' => 'slug'], Post::class),
        );

        ['tours' => $tours, 'italy' => $italy] = $this->tree();
        $this->rule(['prefix' => 'catalog', 'tree' => Handbook::class, 'parent' => 'parent_id']);
        $field = PublicUrl::decorateSlugField(['type' => 'slug'], Post::class);

        $this->assertSame('catalog', $field['publicPrefix']);
        $this->assertSame('parent_id', $field['parentField']);
        $this->assertSame('tours', $field['parentPaths'][$tours->id]);
        $this->assertSame('tours/italy', $field['parentPaths'][$italy->id]);
    }

    public function test_a_record_address_has_the_prefix_section_path_and_code(): void
    {
        ['rome' => $rome] = $this->tree();
        $this->rule(['prefix' => 'catalog', 'tree' => Handbook::class, 'parent' => 'section_id']);

        $this->assertSame('http://localhost/catalog/tours/italy/rome/colosseum', PublicUrl::forRecord($this->record(['code' => 'colosseum', 'section_id' => $rome->id])));
        // No section, or one that is gone: straight under the prefix.
        $this->assertSame('http://localhost/catalog/colosseum', PublicUrl::forRecord($this->record(['code' => 'colosseum'])));
        $this->assertSame('http://localhost/catalog/colosseum', PublicUrl::forRecord($this->record(['code' => 'colosseum', 'section_id' => 999])));

        $this->rule([]);
        $this->assertSame('http://localhost/colosseum', PublicUrl::forRecord($this->record(['code' => 'colosseum'])));
    }

    public function test_a_record_without_a_code_or_a_rule_has_no_address(): void
    {
        $this->assertNull(PublicUrl::forRecord($this->record(['code' => 'colosseum'])));

        $this->rule(['prefix' => 'catalog']);
        $this->assertNull(PublicUrl::forRecord($this->record(['code' => ''])));
        $this->assertNull(PublicUrl::forRecord($this->record([])));
    }

    public function test_a_record_of_another_site_gets_that_site_address(): void
    {
        $second = Site::query()->create(['code' => 'second', 'name' => 'Second', 'folder' => 'shop2']);
        $this->rule(['prefix' => 'catalog']);

        $this->assertSame('http://localhost/shop2/catalog/chair', PublicUrl::forRecord($this->record(['code' => 'chair', 'site_id' => $second->id])));
        $this->assertSame('http://localhost/catalog/chair', PublicUrl::forRecord($this->record(['code' => 'chair', 'site_id' => 999])));
        $this->assertSame('http://localhost/catalog/chair', PublicUrl::forRecord($this->record(['code' => 'chair'])));
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function record(array $attributes): Model
    {
        return (new Post)->forceFill($attributes);
    }
}
