<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\Fixtures\Bare;
use Atrium\Admin\Tests\TestCase;
use Inertia\Testing\AssertableInertia;

/**
 * A model that only meets the Administrable contract works in the panel: what
 * InteractsWithAdmin adds is asked for, never assumed.
 */
final class BareModelTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Bare::class]);
    }

    public function test_the_list_the_forms_and_saving_work_without_the_trait(): void
    {
        $record = Bare::query()->create(['name' => 'First']);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/bares')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->where('can', ['view' => true, 'create' => true, 'update' => true, 'delete' => true])
            ->where('actions', []));
        $this->get('/admin/bares/create')->assertOk();
        $this->get("/admin/bares/{$record->id}/edit")->assertOk();

        $this->post('/admin/bares', ['name' => 'Second'])->assertRedirect();
        $this->put("/admin/bares/{$record->id}", ['name' => 'Changed'])->assertRedirect();

        $this->assertSame(['Changed', 'Second'], Bare::query()->orderBy('id')->pluck('name')->all());
    }
}
