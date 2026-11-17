<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Modules\Soft;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\Fixtures\Gadget;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Remark;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * Links to a module that may not be installed: Atrium\Admin\Modules\Soft and
 * Atrium\Admin\Traits\HasSoftRelations.
 */
final class SoftRelationsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app->resolving(Modules::class, static fn (Modules $modules) => $modules->add(Label::module()));
        $app['config']->set('atrium.resources', [Post::class, Gadget::class]);
        // Installed from the start: its migrations run and its routes exist.
        $app['config']->set('atrium.modules', ['acme/atrium-labels']);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    private function withoutLabels(): void
    {
        config()->set('atrium.modules', []);
        $this->app->make(ResourceRegistry::class)->flush();
    }

    /**
     * @return array{Label,Gadget}
     */
    private function gadget(): array
    {
        $label = Label::query()->create(['name' => 'Red']);
        $gadget = Gadget::query()->create(['name' => 'Kettle', 'label_id' => $label->id]);
        $gadget->tags()->attach($label->id);

        return [$label, $gadget];
    }

    public function test_the_fields_and_columns_are_there_only_with_the_module(): void
    {
        $names = static fn (array $items): array => array_map(static fn ($item): string => $item->toArray()['name'], $items);

        $this->assertSame(['name', 'label_id', 'tags'], array_map(static fn (Field $field): string => $field->getName(), Gadget::getAdministrationFields()));
        $this->assertSame(['name', 'label.name'], $names(Gadget::getAdministrationListColumns()));

        $this->withoutLabels();

        $this->assertSame(['name'], array_map(static fn (Field $field): string => $field->getName(), Gadget::getAdministrationFields()));
        $this->assertSame(['name'], $names(Gadget::getAdministrationListColumns()));
        $this->assertSame([], Soft::when([Label::class, Post::class], ['anything']));
        $this->assertSame(['anything'], Soft::when(Post::class, ['anything']));
        $this->assertSame([], Soft::when('Acme\\Gone\\Thing', ['anything']));
    }

    public function test_the_api_list_reads_the_tags_of_every_record_in_one_query(): void
    {
        $label = Label::query()->create(['name' => 'Red']);

        foreach (['Kettle', 'Toaster', 'Lamp'] as $name) {
            Gadget::query()->create(['name' => $name, 'label_id' => $label->id])->tags()->attach($label->id);
        }

        $token = $this->admin()->createToken('robot', ['gadgets:read'])->plainTextToken;
        $queries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries += str_contains($query->sql, 'gadget_label') ? 1 : 0;
        });

        $rows = $this->withToken($token)->getJson('/api/v1/gadgets')->assertOk()->json('data');

        $this->assertSame([[$label->id], [$label->id], [$label->id]], array_column($rows, 'tags'));
        $this->assertSame(1, $queries);
    }

    public function test_relations_find_the_records_with_the_module(): void
    {
        [$label, $gadget] = $this->gadget();

        $this->assertTrue($gadget->label?->is($label));
        $this->assertSame([$label->id], $gadget->tags->modelKeys());
        $this->assertSame([$label->id], $gadget->labelsWithMyId->modelKeys());
        $this->assertSame(1, Gadget::query()->whereHas('label')->count());
    }

    public function test_relations_find_nothing_without_the_module_and_query_no_table_of_it(): void
    {
        [, $gadget] = $this->gadget();

        $this->withoutLabels();
        Schema::drop('labels');

        $gadget = Gadget::query()->with(['label', 'tags', 'labelsWithMyId'])->findOrFail($gadget->id);

        $this->assertNull($gadget->label);
        $this->assertCount(0, $gadget->tags);
        $this->assertCount(0, $gadget->labelsWithMyId);
        $this->assertNull($gadget->fresh()?->label);
        $this->assertSame(0, Gadget::query()->whereHas('label')->count());
        $this->assertSame(0, Gadget::query()->whereHas('tags')->count());
    }

    public function test_a_record_attached_to_a_missing_module_reads_as_null(): void
    {
        [$label] = $this->gadget();
        $post = Post::query()->create(['name' => 'Hello']);

        $onLabel = Remark::query()->create(['text' => 'Nice colour', 'subject_type' => Label::class, 'subject_id' => $label->id]);
        $onPost = Remark::query()->create(['text' => 'Well put', 'subject_type' => Post::class, 'subject_id' => $post->id]);
        $onGone = Remark::query()->create(['text' => 'Gone', 'subject_type' => 'Acme\\Gone\\Thing', 'subject_id' => 1]);

        $this->assertTrue($onLabel->subject?->is($label));
        $this->assertNull($onGone->subject);
        $this->assertNull(Remark::query()->make()->subject);

        $this->withoutLabels();

        $this->assertNull($onLabel->fresh()?->subject);
        $this->assertTrue($onPost->fresh()?->subject?->is($post));

        $remarks = Remark::query()->with('subject')->orderBy('id')->get();
        $this->assertSame([null, $post->id, null], $remarks->map(static fn (Remark $remark) => $remark->subject?->getKey())->all());
    }

    public function test_a_relation_is_named_after_the_method_that_declares_it_unless_it_is_given_a_name(): void
    {
        [, $gadget] = $this->gadget();
        $remark = Remark::query()->make();

        foreach ([true, false] as $installed) {
            if (! $installed) {
                $this->withoutLabels();
            }

            $this->assertSame('label', $gadget->label()->getRelationName(), 'installed: '.json_encode($installed));
            $this->assertSame('tags', $gadget->tags()->getRelationName());
            $this->assertSame('chosen', $gadget->named()->getRelationName());
            $this->assertSame('chosen', $gadget->namedTags()->getRelationName());
            $this->assertSame('subject', $remark->subject()->getRelationName());
            $this->assertSame('subject', $remark->other()->getRelationName());
        }
    }

    public function test_the_link_keys_come_from_the_names_unless_they_are_given(): void
    {
        [, $gadget] = $this->gadget();

        foreach ([true, false] as $installed) {
            if (! $installed) {
                $this->withoutLabels();
            }

            $why = 'installed: '.json_encode($installed);
            $this->assertSame('label_id', $gadget->label()->getForeignKeyName(), $why);
            $this->assertSame('label2_id', $gadget->label2()->getForeignKeyName(), $why);
            $this->assertSame('label_id', $gadget->named()->getForeignKeyName(), $why);
            $this->assertSame('shade_id', $gadget->namedTags()->getRelatedPivotKeyName(), $why);
            $this->assertSame(['gadget_id', 'label_id'], [$gadget->tags()->getForeignPivotKeyName(), $gadget->tags()->getRelatedPivotKeyName()], $why);
            $this->assertSame(['gadget_id', 'label_id'], [$gadget->tags2()->getForeignPivotKeyName(), $gadget->tags2()->getRelatedPivotKeyName()], $why);
        }
    }

    public function test_a_record_of_a_missing_module_is_kept_out_by_the_query_itself(): void
    {
        $onGone = Remark::query()->create(['text' => 'Gone', 'subject_type' => 'Acme\\Gone\\Thing', 'subject_id' => 1]);
        [$label] = $this->gadget();
        $onLabel = Remark::query()->create(['text' => 'Nice', 'subject_type' => Label::class, 'subject_id' => $label->id]);

        $this->assertStringContainsString('1 = 0', $onGone->subject()->toSql());
        $this->assertStringNotContainsString('1 = 0', $onLabel->subject()->toSql());

        $this->withoutLabels();
        $this->assertStringContainsString('1 = 0', $onLabel->fresh()->subject()->toSql());
    }

    public function test_the_list_shows_an_attribute_of_the_linked_record(): void
    {
        $this->gadget();
        $this->actingAs($this->admin(), 'admin');

        $row = $this->get('/admin/gadgets')->assertOk()->viewData('page')['props']['items']['data'][0];

        $this->assertSame('Kettle', $row['name']);
        $this->assertSame('Red', $row['label.name']);

        // Neither searched nor sorted by: it is not a column of the table.
        $this->get('/admin/gadgets?search=Kettle&sort=label.name')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('items.total', 1));
    }

    public function test_the_role_form_and_the_attach_targets_offer_the_installed_module(): void
    {
        $this->assertContains('labels.view', $this->app->make(Permissions::class)->all());

        $targets = Soft::targets();
        $this->assertSame('Labels', $targets[Label::class] ?? null);
        $this->assertArrayHasKey(Gadget::class, $targets);
        $this->assertArrayNotHasKey(Site::class, $targets);

        $this->withoutLabels();
        $this->assertArrayNotHasKey(Label::class, Soft::targets());
    }
}
