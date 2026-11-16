<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Dashboard\Gadget;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Admin dashboard — gadget tiles, as in Bitrix.
 *
 * The set is configured in `atrium.dashboard.gadgets`.
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
     * @return array<int,array<string,mixed>>
     */
    protected function gadgets(): array
    {
        $gadgets = [];

        foreach ((array) config('atrium.dashboard.gadgets', []) as $index => $entry) {
            $gadget = $this->makeGadget($entry);

            if ($gadget === null || ! $gadget->isVisible()) {
                continue;
            }

            // A broken tile must not take the whole dashboard down — otherwise
            // the panel becomes unreachable over a trifle like a typo in a
            // model name.
            try {
                $gadgets[] = ['key' => 'gadget-'.$index] + $gadget->toArray();
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $gadgets;
    }

    private function makeGadget(mixed $entry): ?Gadget
    {
        $class = is_array($entry) ? ($entry['gadget'] ?? null) : $entry;
        $options = is_array($entry) ? array_diff_key($entry, ['gadget' => null]) : [];

        if (! is_string($class) || ! is_subclass_of($class, Gadget::class)) {
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
