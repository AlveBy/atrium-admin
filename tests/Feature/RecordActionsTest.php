<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Actions\CreateFromRecord;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Chapter;
use Atrium\Admin\Tests\Fixtures\Ledger;
use Atrium\Admin\Tests\Fixtures\PanelAccount;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;

/**
 * Buttons on a record that open a filled create form, the section preselected
 * from the public site, and modules that are only read in the panel.
 */
final class RecordActionsTest extends TestCase
{
    use PinsExtensionSurface;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Chapter::class, Ledger::class]);
    }

    public function test_an_action_is_its_name_label_and_fill_map(): void
    {
        $action = CreateFromRecord::make('subpage')->label('Subpage')->fill(['parent_id' => 'id']);

        $this->assertSame(['parent_id' => 'id'], $action->getFill());
        $this->assertSame(['name' => 'subpage', 'label' => 'Subpage', 'fill' => ['parent_id' => 'id']], $action->toArray());
        $this->assertSame('{"name":"subpage","label":"Subpage","fill":{"parent_id":"id"}}', json_encode($action));
        $this->assertSame(['name' => 'bare', 'label' => '', 'fill' => []], CreateFromRecord::make('bare')->toArray());
    }

    public function test_pages_list_only_real_actions_for_those_who_may_create(): void
    {
        $chapter = Chapter::query()->create(['name' => 'One']);
        $expected = [
            ['name' => 'subchapter', 'label' => 'Subchapter', 'fill' => ['parent_id' => 'id']],
            ['name' => 'sibling', 'label' => 'Same kind', 'fill' => ['kind' => 'kind']],
        ];

        $this->actingAs($this->admin(), 'admin');
        foreach (['/admin/chapters', '/admin/chapters/create', "/admin/chapters/{$chapter->id}/edit"] as $page) {
            $this->get($page)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actions', $expected)->where('can.create', true));
        }
    }

    public function test_an_editor_who_may_not_create_sees_no_actions(): void
    {
        $chapter = Chapter::query()->create(['name' => 'One']);
        $this->actingAs($this->editor(['chapters.view', 'chapters.update']), 'admin');

        $this->get('/admin/chapters')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('actions', [])->where('can.create', false)->where('can.update', true));
        $this->get("/admin/chapters/{$chapter->id}/edit")->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actions', []));
    }

    public function test_the_create_form_is_filled_from_the_link_of_an_action(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $response = $this->get('/admin/chapters/create?parent_id=12&kind=guide&name=Injected&section_id=0');

        $this->assertSame(12, $this->default($response, 'parent_id'));
        $this->assertSame('guide', $this->default($response, 'kind'));
        $this->assertNull($this->default($response, 'name'), 'Only the fields an action fills come from the link.');
        $this->assertNull($this->default($response, 'section_id'));
        $this->assertSame(12, $this->default($response, 'parent_id', 'fields'), 'The flat field list gets it too.');
    }

    public function test_odd_values_in_the_link_are_kept_as_text_or_dropped(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $response = $this->get('/admin/chapters/create?parent_id=-3&kind=');
        $this->assertSame('-3', $this->default($response, 'parent_id'), 'Not all digits: kept as text, not cast.');
        $this->assertNull($this->default($response, 'kind'), 'An empty value sets nothing.');

        $response = $this->get('/admin/chapters/create?parent_id[]=4&kind=007');
        $this->assertNull($this->default($response, 'parent_id'), 'An array is not a value.');
        $this->assertSame(7, $this->default($response, 'kind'));
    }

    public function test_a_section_from_the_public_site_is_preselected_when_it_exists(): void
    {
        $section = Post::query()->create(['name' => 'Section']);
        $this->actingAs($this->admin(), 'admin');

        $this->assertSame($section->id, $this->default($this->get("/admin/chapters/create?section_id={$section->id}"), 'section_id'));
        $this->assertNull($this->default($this->get('/admin/chapters/create?section_id='.($section->id + 1)), 'section_id'));
        $this->assertNull($this->default($this->get('/admin/chapters/create?section_id=-'.$section->id), 'section_id'));
    }

    public function test_a_module_without_sections_ignores_the_section_link(): void
    {
        Post::query()->create(['name' => 'Section']);
        $this->actingAs($this->admin(), 'admin');

        $plain = $this->get('/admin/posts/create')->assertOk()->viewData('page')['props']['fields'];

        $this->assertSame($plain, $this->get('/admin/posts/create?section_id=1')->assertOk()->viewData('page')['props']['fields']);
    }

    public function test_a_read_only_module_is_listed_and_deleted_but_not_created_or_edited(): void
    {
        $entry = Ledger::query()->create(['name' => 'Entry']);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/ledgers')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('can', ['view' => true, 'create' => false, 'update' => false, 'delete' => true])
            ->where('actions', []));

        $this->get('/admin/ledgers/create')->assertForbidden();
        $this->post('/admin/ledgers', ['name' => 'New'])->assertForbidden();
        $this->put("/admin/ledgers/{$entry->id}", ['name' => 'Changed'])->assertForbidden();
        $this->assertSame(['Entry'], Ledger::query()->pluck('name')->all());

        $this->delete("/admin/ledgers/{$entry->id}")->assertRedirect();
        $this->assertSame(0, Ledger::query()->count());
    }

    public function test_an_account_that_is_not_an_administrator_model_gets_every_button_of_a_writable_module(): void
    {
        $this->admin();
        config(['auth.providers.admin_users.model' => PanelAccount::class]);
        $this->actingAs(PanelAccount::query()->sole(), 'admin');

        $this->get('/admin/posts')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('can', ['view' => true, 'create' => true, 'update' => true, 'delete' => true]));
        $this->get('/admin/ledgers')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('can', ['view' => true, 'create' => false, 'update' => false, 'delete' => true]));
    }

    private function default(TestResponse $response, string $field, string $where = 'fieldsByTab'): mixed
    {
        $props = $response->assertOk()->viewData('page')['props'];
        $fields = $where === 'fields' ? $props['fields'] : Arr::flatten($props['fieldsByTab'], 1);

        return collect($fields)->firstWhere('name', $field)['default'] ?? null;
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Editors', 'permissions' => $permissions]));

        return $user->fresh();
    }
}
