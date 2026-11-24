<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Extensions;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * An event in the catalogue: what a hook means to those outside the module
 * that fires it — automation rules, webhooks, outside apps.
 *
 *     Hook::register('atrium.events', 'tracker.task.status_changed', Event::make('atrium.tracker.task.status_changed')
 *         ->label('atrium-tracker::events.status_changed')
 *         ->subject(Task::class)
 *         ->payload(fn (Task $task, string $from, string $to): array => ['from' => $from, 'to' => $to]));
 *
 * The key it is registered under is the public name; the hook is the one the
 * module fires. When the hook fires, the catalogue (Events) turns it into an
 * Occurrence and fires `atrium.event.occurred` with it.
 */
final class Event
{
    private string $label;

    /** @var class-string<Model>|null */
    private ?string $subject = null;

    private ?Closure $payload = null;

    private ?Closure $when = null;

    private function __construct(private readonly string $hook)
    {
        $this->label = $hook;
    }

    /**
     * @param  string  $hook  the action the module fires: `atrium.tracker.task.status_changed`
     */
    public static function make(string $hook): self
    {
        return new self($hook);
    }

    /**
     * Its name for people: text or a translation key.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The record the event is about: the hook's first argument, a model of
     * this class. Who may hear the event is decided by its module.
     *
     * @param  class-string<Model>  $model
     */
    public function subject(string $model): self
    {
        $this->subject = $model;

        return $this;
    }

    /**
     * What the event tells, built from the hook's arguments: plain data that
     * goes out as JSON, never secrets. It gets the hook's arguments and
     * returns an array; anything else counts as no data.
     */
    public function payload(Closure $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Only when the hook's arguments say so: one hook may mean several events.
     * It gets the hook's arguments; only `true` means the event happened.
     */
    public function when(Closure $when): self
    {
        $this->when = $when;

        return $this;
    }

    public function hook(): string
    {
        return $this->hook;
    }

    public function labelText(): string
    {
        return (string) __($this->label);
    }

    /**
     * @return class-string<Model>|null
     */
    public function subjectModel(): ?string
    {
        return $this->subject;
    }

    /**
     * Whether this firing of the hook is this event.
     *
     * @param  array<int,mixed>  $args
     */
    public function matches(array $args): bool
    {
        if ($this->subject !== null && ! ($args[0] ?? null) instanceof $this->subject) {
            return false;
        }

        return $this->when === null || ($this->when)(...$args) === true;
    }

    /**
     * @param  array<int,mixed>  $args
     * @return array<mixed>
     */
    public function data(array $args): array
    {
        $data = $this->payload === null ? [] : ($this->payload)(...$args);

        return is_array($data) ? $data : [];
    }
}
