<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Dashboard\Gadget;
use Atrium\Admin\Facades\Hook;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Admin dashboard — gadget tiles, as in Bitrix.
 *
 * The tiles there are registered by key in `atrium.ui.gadgets`, the
 * panel's own the same way as a package's. `atrium.dashboard.gadgets` is the
 * layout: the order, the options and the tiles switched off. A registered
 * tile the layout does not name comes after the ones it does.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'gadgets' => $this->gadgets(),
        ]);
    }

    /**
     * @return list<array<mixed>>
     */
    protected function gadgets(): array
    {
        $registered = array_filter(
            Hook::registered('atrium.ui.gadgets'),
            static fn (mixed $class): bool => is_string($class) && is_subclass_of($class, Gadget::class),
        );
        $gadgets = [];
        $placed = [];

        foreach ((array) config('atrium.dashboard.gadgets', []) as $index => $entry) {
            $name = is_array($entry) ? ($entry['gadget'] ?? null) : $entry;
            $class = is_string($name) ? ($registered[$name] ?? $name) : null;
            $placed[] = $class;

            $gadgets[] = $this->tile('gadget-'.$index, $class, is_array($entry) ? array_diff_key($entry, ['gadget' => null]) : []);
        }

        foreach ($registered as $key => $class) {
            if (! in_array($class, $placed, true)) {
                $gadgets[] = $this->tile('gadget-'.$key, $class, []);
            }
        }

        return array_values(array_filter($gadgets));
    }

    /**
     * @param  array<string,mixed>  $options
     * @return array<mixed>|null
     */
    private function tile(string $key, ?string $class, array $options): ?array
    {
        $gadget = $this->makeGadget($class, $options);

        if ($gadget === null || ! $gadget->isVisible()) {
            return null;
        }

        // A broken tile must not take the whole dashboard down — otherwise
        // the panel becomes unreachable over a trifle like a typo in a
        // model name.
        try {
            return ['key' => $key] + $gadget->toArray();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<string,mixed>  $options
     */
    private function makeGadget(?string $class, array $options): ?Gadget
    {
        if ($class === null || ! is_subclass_of($class, Gadget::class)) {
            return null;
        }

        try {
            return new $class($options);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
