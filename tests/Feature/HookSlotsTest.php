<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

/**
 * Slots: places in the site's templates that modules add HTML to.
 */
final class HookSlotsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['view']->addNamespace('acme', dirname(__DIR__).'/Fixtures/views');
    }

    public function test_an_empty_slot_is_nothing(): void
    {
        $this->assertSame('', Hook::renderSlot('footer.end'));
        $this->assertSame('', Blade::render("@atriumSlot('footer.end')", ['who' => 'Ann']));
    }

    public function test_views_and_closures_come_in_order_of_priority_with_the_page_s_variables(): void
    {
        Hook::slot('footer.end', 'acme::badge');
        Hook::slot('footer.end', static fn (array $page): string => '<i>late '.$page['who'].'</i>', 20);
        Hook::slot('footer.end', static fn (array $page): string => '<u>early</u>', 5);
        Hook::slot('header.end', static fn (): string => '<s>elsewhere</s>');

        $this->assertSame("<u>early</u><b>Ann</b>\n<i>late Ann</i>", Hook::renderSlot('footer.end', ['who' => 'Ann']));
        $this->assertTrue(Hook::has('atrium.slot.footer.end'));
    }

    public function test_a_slot_without_a_priority_has_ten(): void
    {
        Hook::slot('footer.end', static fn (): string => 'default ');
        Hook::slot('footer.end', static fn (): string => 'ten ', 10);
        Hook::slot('footer.end', static fn (): string => 'nine ', 9);

        $this->assertSame('nine default ten ', Hook::renderSlot('footer.end'));
    }

    public function test_a_slot_is_a_filter_others_may_change(): void
    {
        Hook::slot('footer.end', static fn (): string => '<b>ad</b>');
        Hook::filter('atrium.slot.footer.end', static fn (string $html): string => str_replace('ad', 'news', $html), 99);

        $this->assertSame('<b>news</b>', Hook::renderSlot('footer.end'));
    }

    public function test_the_directive_hands_over_the_template_s_own_variables_only(): void
    {
        $seen = [];
        Hook::slot('content.after', static function (array $page) use (&$seen): string {
            $seen = array_keys($page);

            return '<p>'.$page['who'].'</p>';
        });

        $this->assertSame('<main><p>Ann</p></main>', Blade::render("<main>@atriumSlot('content.after')</main>", ['who' => 'Ann']));
        $this->assertSame(['who'], $seen);
    }
}
