<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\TestCase;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * The scheduler puts a demo back by itself, and only a demo.
 */
final class DemoScheduleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.demo.enabled', $this->name() === 'test_the_scheduler_resets_a_demo_on_its_own');
        $app['config']->set('atrium.demo.reset', '*/30 * * * *');
    }

    public function test_the_scheduler_resets_a_demo_on_its_own(): void
    {
        $events = $this->resets();

        $this->assertCount(1, $events);
        $this->assertSame('*/30 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    public function test_the_scheduler_leaves_a_site_that_is_not_a_demo_alone(): void
    {
        $this->assertSame([], $this->resets());
    }

    /**
     * @return array<int,Event>
     */
    private function resets(): array
    {
        return array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains((string) $event->command, 'atrium:demo:reset'),
        ));
    }
}
