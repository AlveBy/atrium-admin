<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Tests\TestCase;

/**
 * Registries: modules add items by key (the shop's payment methods), the
 * owner reads them all or one.
 */
final class HookRegistriesTest extends TestCase
{
    public function test_a_registry_nobody_added_to_is_empty(): void
    {
        $this->assertSame([], Hook::registered('acme.payment_methods'));
        $this->assertNull(Hook::resolve('acme.payment_methods', 'card'));
    }

    public function test_the_items_come_by_key_lower_priority_first(): void
    {
        Hook::register('acme.payment_methods', 'invoice', 'Invoice', 20);
        Hook::register('acme.payment_methods', 'card', 'Card');
        Hook::register('acme.payment_methods', 'cash', 'Cash', 5);
        Hook::register('acme.delivery', 'post', 'Post');

        $this->assertSame(['cash' => 'Cash', 'card' => 'Card', 'invoice' => 'Invoice'], Hook::registered('acme.payment_methods'));
        $this->assertSame('Card', Hook::resolve('acme.payment_methods', 'card'));
        $this->assertNull(Hook::resolve('acme.payment_methods', 'post'));
        $this->assertTrue(Hook::has('acme.payment_methods'));
    }

    public function test_an_item_without_a_priority_has_ten(): void
    {
        Hook::register('acme.payment_methods', 'card', 'Card');
        Hook::register('acme.payment_methods', 'cash', 'Cash', 10);
        Hook::register('acme.payment_methods', 'invoice', 'Invoice', 9);

        $this->assertSame(['invoice', 'card', 'cash'], array_keys(Hook::registered('acme.payment_methods')));
    }

    public function test_the_same_key_again_is_replaced_by_the_one_that_runs_later(): void
    {
        Hook::register('acme.payment_methods', 'card', 'Card of the shop');
        Hook::register('acme.payment_methods', 'cash', 'Cash');
        Hook::register('acme.payment_methods', 'card', 'Card of the bank', 15);
        // A lower priority runs earlier: the shop's card is put over it.
        Hook::register('acme.payment_methods', 'cash', 'Cash of nobody', 1);

        $this->assertSame(['cash' => 'Cash', 'card' => 'Card of the bank'], Hook::registered('acme.payment_methods'));
    }

    public function test_a_filter_of_the_registry_sees_its_arguments_and_may_drop_an_item(): void
    {
        $card = static fn (int $amount): string => "Paid {$amount} by card";
        Hook::register('acme.payment_methods', 'card', $card);
        Hook::register('acme.payment_methods', 'cash', 'Cash');
        Hook::filter('acme.payment_methods', static function (array $methods, string $site): array {
            if ($site === 'shop.by') {
                unset($methods['cash']);
            }

            return $methods;
        }, 50);

        $this->assertSame(['card' => $card], Hook::registered('acme.payment_methods', 'shop.by'));
        $this->assertSame(['card', 'cash'], array_keys(Hook::registered('acme.payment_methods', 'shop.ru')));
        $this->assertNull(Hook::resolve('acme.payment_methods', 'cash', 'shop.by'));
        $this->assertSame('Cash', Hook::resolve('acme.payment_methods', 'cash', 'shop.ru'));
        $this->assertSame('Paid 5 by card', Hook::resolve('acme.payment_methods', 'card', 'shop.by')(5));
    }
}
