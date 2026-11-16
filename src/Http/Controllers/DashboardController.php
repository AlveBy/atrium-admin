<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Dashboard\Gadget;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Рабочий стол админки — плитки-гаджеты, как в Битриксе.
 *
 * Набор задаётся в `atrium.dashboard.gadgets`.
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

            // Сломанная плитка не должна уносить весь рабочий стол — тогда
            // админка становится недоступной из-за мелочи вроде опечатки
            // в имени модели.
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
