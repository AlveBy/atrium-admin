<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Task;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Views\KanbanView;
use Atrium\Admin\Views\ModuleViews;
use InvalidArgumentException;

/**
 * The looks of a module's list besides the table: how a module declares
 * them, which of them is drawn and what the administrator keeps.
 */
final class ModuleViewsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Task::class]);
    }

    public function test_a_view_is_declared_in_one_line_and_says_what_it_writes_and_needs(): void
    {
        $view = KanbanView::make('status');

        $this->assertSame('kanban', $view->type());
        $this->assertSame('status', $view->getField());
        $this->assertNull($view->getTitleField());
        $this->assertSame([], $view->getCards());
        $this->assertNull($view->getOrder());
        $this->assertSame(KanbanView::PER_COLUMN, $view->getPerColumn());
        $this->assertSame(['status'], $view->writes());
        $this->assertSame(['status' => ['select', 'belongsTo']], $view->needs());

        $full = KanbanView::make('status')->title('name')->cards('a', 'b', 'c')->order('rank')->perColumn(5);

        $this->assertSame('name', $full->getTitleField());
        $this->assertSame(['a', 'b', 'c'], $full->getCards());
        $this->assertSame('rank', $full->getOrder());
        $this->assertSame(5, $full->getPerColumn());
        $this->assertSame(['status', 'rank'], $full->writes());
        $this->assertSame(['status' => ['select', 'belongsTo'], 'rank' => ['number']], $full->needs());
        $this->assertSame(1, KanbanView::make('status')->perColumn(0)->getPerColumn());
        $this->assertSame(1, KanbanView::make('status')->perColumn(-4)->getPerColumn());
    }

    public function test_a_card_shows_at_most_three_fields(): void
    {
        $this->assertSame(['a', 'b', 'c'], KanbanView::make('status')->cards('a', 'b', 'c')->getCards());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A card shows at most 3 fields, 4 given.');

        KanbanView::make('status')->cards('a', 'b', 'c', 'd');
    }

    public function test_a_module_s_views_are_its_own_and_other_modules_by_type(): void
    {
        $views = app(ModuleViews::class);

        $this->assertSame([], $views->for(Post::class));
        $this->assertSame(['kanban'], array_keys($views->for(Task::class)));
        $this->assertSame(['table', 'kanban'], $views->types($views->for(Task::class)));
        $this->assertSame(['table'], $views->types([]));

        $second = KanbanView::make('title');
        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Task::class ? [...$views, $second, 'not a view'] : $views);

        $this->assertSame('status', $views->for(Task::class)['kanban']->getField(), 'Of two views of a type the first one wins.');
        $this->assertSame(['kanban'], array_keys($views->for(Task::class)));
    }

    public function test_another_module_may_add_a_view_to_a_module(): void
    {
        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Post::class ? [KanbanView::make('sort')] : $views);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The kanban view of '.Post::class.' is built on the field "sort", which has to be a field of the form of type select or belongsTo.');

        app(ModuleViews::class)->for(Post::class);
    }

    public function test_a_view_built_on_a_field_the_form_does_not_have_says_so(): void
    {
        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Post::class ? [KanbanView::make('nothing')] : $views);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('built on the field "nothing"');

        app(ModuleViews::class)->for(Post::class);
    }

    public function test_the_field_that_keeps_the_order_has_to_be_a_number(): void
    {
        Hook::filter('atrium.module.views', static fn (array $views, string $model): array => $model === Task::class ? [KanbanView::make('status')->order('title')] : $views);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('built on the field "title", which has to be a field of the form of type number');

        app(ModuleViews::class)->for(Task::class);
    }

    public function test_the_chosen_view_is_the_one_asked_for_else_the_one_remembered(): void
    {
        $views = app(ModuleViews::class);
        $declared = $views->for(Task::class);
        $admin = $this->admin();

        $this->assertNull($views->choose($declared, null, $admin, 'tasks'), 'No ask, nothing remembered: the table.');
        $this->assertSame('kanban', $views->choose($declared, 'kanban', $admin, 'tasks')?->type());
        $this->assertSame(['tasks' => 'kanban'], $admin->fresh()->list_views);
        $this->assertSame('kanban', $views->choose($declared, null, $admin->fresh(), 'tasks')?->type(), 'Remembered.');
        $this->assertSame('kanban', $views->choose($declared, 'calendar', $admin->fresh(), 'tasks')?->type(), 'A view the module has not is no ask.');
        $this->assertSame('kanban', $views->choose($declared, ['kanban'], $admin->fresh(), 'tasks')?->type(), 'Nor is a list.');
        $this->assertNull($views->choose($declared, 'table', $admin->fresh(), 'tasks'));
        $this->assertSame(['tasks' => 'table'], $admin->fresh()->list_views);
        $this->assertNull($views->choose($declared, null, $admin->fresh(), 'tasks'), 'The table is remembered too.');
    }

    public function test_a_choice_is_kept_per_module(): void
    {
        $views = app(ModuleViews::class);
        $declared = $views->for(Task::class);
        $admin = $this->admin();
        $admin->forceFill(['list_views' => ['other' => 'kanban']])->save();

        $views->choose($declared, 'kanban', $admin, 'tasks');
        $this->assertSame(['other' => 'kanban', 'tasks' => 'kanban'], $admin->fresh()->list_views);

        $views->choose($declared, 'nonsense', $admin, 'tasks');
        $this->assertSame(['other' => 'kanban', 'tasks' => 'kanban'], $admin->fresh()->list_views, 'A view that is not there is not remembered.');
    }

    public function test_a_stranger_has_no_memory_but_may_still_ask(): void
    {
        $declared = app(ModuleViews::class)->for(Task::class);

        $this->assertSame('kanban', app(ModuleViews::class)->choose($declared, 'kanban', null, 'tasks')?->type());
        $this->assertNull(app(ModuleViews::class)->choose($declared, null, null, 'tasks'));
        $this->assertNull(app(ModuleViews::class)->choose($declared, 'table', null, 'tasks'));
    }
}
