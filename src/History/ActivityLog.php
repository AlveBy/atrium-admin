<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\History;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Models\Activity;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Traits\BelongsToSite;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The activity log of the panel: who created, changed, deleted or restored a
 * record of a module, when, from where, and which fields went from what to
 * what.
 *
 * Every module has it without a line of its own: the form, the API, the MCP
 * tools and the import all save through AdminController::saveItem() and
 * delete through RecordDeleter, and both write here, in the same transaction
 * as the record. A module's buttons (RecordAction, BulkAction) change records
 * in their own way; what they change is logged while they run (watch()).
 *
 * A module leaves the log with `public static function hasAdminHistory():
 * bool { return false; }`, a whole site with `atrium.history.enabled`.
 * Passwords, keys and tokens are never written: that one changed is.
 */
final class ActivityLog
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const RESTORED = 'restored';

    /** Deleted from the trash: gone for good. */
    public const PURGED = 'purged';

    public const EVENTS = [self::CREATED, self::UPDATED, self::DELETED, self::RESTORED, self::PURGED];

    /** Saved in the panel. */
    public const ADMIN = 'admin';

    /** Through /api/v1 or the MCP tools. */
    public const API = 'api';

    /** From a CSV or Excel file. */
    public const IMPORT = 'import';

    /** By a console command. */
    public const CONSOLE = 'console';

    /** By the scheduler, on its own. */
    public const SCHEDULE = 'schedule';

    /** Columns every record changes on every save, which tell nothing. */
    private const NOISE = ['created_at', 'updated_at'];

    private ?string $source = null;

    private ?AdminUser $actor = null;

    /** How deep in watch(): changes of records are logged as they happen. */
    private int $watching = 0;

    /** How deep in quietly(): the save logs itself, the watch keeps out. */
    private int $quiet = 0;

    /**
     * Whether changes of the module's records are logged.
     *
     * @param  class-string<Model>  $model
     */
    public function enabledFor(string $model): bool
    {
        return config()->boolean('atrium.history.enabled')
            && (! method_exists($model, 'hasAdminHistory') || $model::hasAdminHistory());
    }

    /**
     * Runs $work as $source on behalf of $actor: an import started by an
     * administrator, the scheduler. What is logged meanwhile says so.
     *
     * @template T
     *
     * @param  Closure():T  $work
     * @return T
     */
    public function during(string $source, ?AdminUser $actor, Closure $work): mixed
    {
        [$source, $this->source] = [$this->source, $source];
        [$actor, $this->actor] = [$this->actor, $actor];

        try {
            return $work();
        } finally {
            $this->source = $source;
            $this->actor = $actor;
        }
    }

    /**
     * Runs $work and logs every change of a module's record it makes through
     * Eloquent, field by field: what a module's button does.
     *
     * @template T
     *
     * @param  Closure():T  $work
     * @return T
     */
    public function watch(Closure $work): mixed
    {
        $this->watching++;

        try {
            return $work();
        } finally {
            $this->watching--;
        }
    }

    /**
     * Runs $work with the watch off: a save or a delete that logs itself.
     *
     * @template T
     *
     * @param  Closure():T  $work
     * @return T
     */
    public function quietly(Closure $work): mixed
    {
        $this->quiet++;

        try {
            return $work();
        } finally {
            $this->quiet--;
        }
    }

    /**
     * Called for every record Eloquent creates or updates: logged while a
     * module's button runs.
     */
    public function observed(string $event, Model $record): void
    {
        if ($this->watching === 0 || $this->quiet > 0 || ! $record instanceof Administrable || ! $this->enabledFor($record::class)) {
            return;
        }

        $changes = [];

        foreach (array_keys($event === self::CREATED ? $record->getAttributes() : $record->getChanges()) as $name) {
            if ($name === $record->getKeyName() || in_array($name, self::NOISE, true)) {
                continue;
            }

            if (self::isSecretName($name, $record)) {
                $changes[$name] = ['secret' => true];

                continue;
            }

            $old = $event === self::CREATED ? null : self::attribute($record, $name, $record->getRawOriginal($name));
            $new = self::attribute($record, $name, $record->getAttributes()[$name]);

            if (! self::same($old, $new)) {
                $changes[$name] = ['old' => $old, 'new' => $new];
            }
        }

        if ($event === self::CREATED || $changes !== []) {
            $this->record($record, $event, $changes);
        }
    }

    /**
     * Writes an entry about $record. `$changes` comes from changes().
     *
     * @param  Model&Administrable  $record
     * @param  array<string,array<string,mixed>>  $changes
     */
    public function record(Model $record, string $event, array $changes = []): Activity
    {
        $actor = $this->actor();

        return Activity::query()->create([
            'site_id' => in_array(BelongsToSite::class, class_uses_recursive($record), true) ? $record->getAttribute('site_id') : null,
            'module' => $record::getAdminRoutePrefix(),
            'record_id' => $record->getKey(),
            'record_title' => Str::limit(self::titleOf($record), 250),
            'event' => $event,
            'changes' => $changes === [] ? null : $changes,
            'admin_user_id' => $actor?->getKey(),
            'admin_name' => $actor?->name,
            'source' => $this->source(),
        ]);
    }

    /**
     * What differs between two snapshots of a record's form (field => value),
     * as field => ['old' => …, 'new' => …]. An empty text and no value are
     * the same; `$secrets` are the secret fields that were given a value:
     * ['secret' => true], without it.
     *
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     * @param  array<int,string>  $secrets
     * @return array<string,array<string,mixed>>
     */
    public function changes(array $before, array $after, array $secrets = []): array
    {
        $changes = [];

        foreach (array_keys($before + $after) as $name) {
            $old = $before[$name] ?? null;
            $new = $after[$name] ?? null;

            if (! self::same($old, $new)) {
                $changes[$name] = ['old' => $old, 'new' => $new];
            }
        }

        foreach ($secrets as $name) {
            $changes[$name] = ['secret' => true];
        }

        return $changes;
    }

    /**
     * What the record is called in the panel: its form's title.
     */
    public static function titleOf(Model $record): string
    {
        return method_exists($record, 'getAdminTitle') ? $record->getAdminTitle() : '#'.$record->getKey();
    }

    /**
     * Whether a field or attribute holds a password, a key or a token: one
     * the model hides from arrays, or whose name says so, in any case
     * (`atrium.history.secrets`).
     */
    public static function isSecretName(string $name, Model $record): bool
    {
        return in_array($name, $record->getHidden(), true)
            || Str::contains($name, config()->array('atrium.history.secrets', []), true);
    }

    /**
     * Two values a field had are the same: as the form would show them.
     */
    public static function same(mixed $a, mixed $b): bool
    {
        return self::comparable($a) === self::comparable($b);
    }

    private static function comparable(mixed $value): mixed
    {
        return match (true) {
            $value === '' => null,
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            is_array($value) => self::comparableArray($value),
            default => $value,
        };
    }

    /**
     * A list item by item; the empty entries of a map (a language with no
     * text yet) left out, so {"en": "Hi", "de": null} is {"en": "Hi"}, and
     * a map of nothing but empty entries is no value at all.
     *
     * @param  array<array-key,mixed>  $value
     * @return array<array-key,mixed>|null
     */
    private static function comparableArray(array $value): ?array
    {
        $items = array_map(self::comparable(...), $value);

        if (! array_is_list($value)) {
            $items = array_filter($items, static fn (mixed $item): bool => $item !== null);
        }

        return $items === [] ? null : $items;
    }

    /**
     * An attribute as stored, readable: a boolean as one, JSON (translations,
     * lists) decoded.
     */
    private static function attribute(Model $record, string $name, mixed $raw): mixed
    {
        if ($raw !== null && $record->hasCast($name, ['bool', 'boolean'])) {
            return (bool) $raw;
        }

        if (is_string($raw) && $raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : $raw;
        }

        return $raw;
    }

    /**
     * Who the entries written now are by: the one during() names, else the
     * administrator signed in (in the API, the token's owner).
     */
    public function actor(): ?AdminUser
    {
        $user = $this->actor ?? auth()->guard((string) config('atrium.auth.guard', 'admin'))->user();

        return $user instanceof AdminUser ? $user : null;
    }

    /**
     * Where the entries written now come from: what during() names, else the
     * API's routes, the panel's, or neither (a command, a job).
     */
    public function source(): string
    {
        $route = (string) Route::currentRouteName();

        return match (true) {
            $this->source !== null => $this->source,
            str_starts_with($route, 'admin.api.') => self::API,
            str_starts_with($route, 'admin.') => self::ADMIN,
            default => self::CONSOLE,
        };
    }
}
