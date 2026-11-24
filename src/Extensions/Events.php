<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Extensions;

use Atrium\Admin\Hooks\Hooks;
use Atrium\Admin\Resources\ResourceRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;

/**
 * The event catalogue: every event a rule, a webhook or an outside app may
 * hear, by key.
 *
 * Every module of the panel has its record events, `{module}.created`,
 * `{module}.updated` and `{module}.deleted`, without a line of its own.
 * A module declares the rest in the `atrium.events` registry (see Event).
 * When a declared hook fires, `atrium.event.occurred` is fired with an
 * Occurrence for every event it means; nothing is built while nobody
 * listens to that action.
 */
final class Events
{
    /** @var array<string,true> the hooks already listened to */
    private array $listening = [];

    public function __construct(
        private readonly Hooks $hooks,
        private readonly Dispatcher $events,
        private readonly ResourceRegistry $resources,
        private readonly Auth $auth,
    ) {}

    /**
     * Every event, by key: the record events of the modules first, then the
     * declared ones in the order they were registered.
     *
     * @return array<string,Event>
     */
    public function all(): array
    {
        $events = [];

        foreach ($this->resources->all() as $key => $model) {
            $module = $model::getAdminLabel();
            $events["{$key}.created"] = Event::make('atrium.record.saved')->subject($model)
                ->label(__('atrium::events.created', ['module' => $module]))
                ->when(static fn (mixed $record, string $context): bool => $context === 'create');
            $events["{$key}.updated"] = Event::make('atrium.record.saved')->subject($model)
                ->label(__('atrium::events.updated', ['module' => $module]))
                ->when(static fn (mixed $record, string $context): bool => $context === 'edit');
            $events["{$key}.deleted"] = Event::make('atrium.record.deleted')->subject($model)
                ->label(__('atrium::events.deleted', ['module' => $module]));
        }

        return [...$events, ...$this->declared()];
    }

    /**
     * Start listening to the hooks of the catalogue. The service provider
     * calls it once every module has booted; it may be called again after
     * an event is declared later, and listens to each hook once.
     */
    public function listen(): void
    {
        $hooks = ['atrium.record.saved', 'atrium.record.deleted', ...array_map(static fn (Event $event): string => $event->hook(), $this->declared())];

        foreach ($hooks as $hook) {
            if (! isset($this->listening[$hook])) {
                $this->listening[$hook] = true;
                $this->events->listen($hook, fn (mixed ...$args) => $this->occurred($hook, $args));
            }
        }
    }

    /**
     * A hook of the catalogue has fired: hand every event it means to the
     * listeners of `atrium.event.occurred`.
     *
     * @param  array<int,mixed>  $args
     */
    private function occurred(string $hook, array $args): void
    {
        if (! $this->hooks->has('atrium.event.occurred')) {
            return;
        }

        foreach ($this->all() as $key => $event) {
            if ($event->hook() !== $hook || ! $event->matches($args)) {
                continue;
            }

            $subject = $event->subjectModel();

            $this->hooks->fire('atrium.event.occurred', new Occurrence(
                id: (string) Str::uuid(),
                event: $key,
                subject: $subject === null ? null : $args[0],
                module: $subject === null ? null : $this->resources->keyOf($subject),
                data: $event->data($args),
                actor: $this->auth->guard((string) config('atrium.auth.guard', 'admin'))->user(),
                at: CarbonImmutable::now(),
            ));
        }
    }

    /**
     * What modules registered in `atrium.events`; anything that is not an
     * Event is left out.
     *
     * @return array<string,Event>
     */
    private function declared(): array
    {
        return array_filter($this->hooks->registered('atrium.events'), static fn (mixed $event): bool => $event instanceof Event);
    }
}
