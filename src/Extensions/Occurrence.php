<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Extensions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * One time an event of the catalogue happened: what `atrium.event.occurred`
 * hands its listeners, and what a webhook sends as JSON (toArray()).
 */
final class Occurrence
{
    /**
     * @param  string  $id  a UUID, the same for every listener: a receiver that sees it twice ignores the second
     * @param  string  $event  the key in the catalogue: `deals.created`, `tracker.task.status_changed`
     * @param  Model|null  $subject  the record it is about
     * @param  string|null  $module  the subject's module key (`deals`), null when it is not a module of the panel
     * @param  array<mixed>  $data  the event's payload
     * @param  Authenticatable|null  $actor  the administrator who did it, null for the console, the queue or a visitor
     */
    public function __construct(
        public readonly string $id,
        public readonly string $event,
        public readonly ?Model $subject,
        public readonly ?string $module,
        public readonly array $data,
        public readonly ?Authenticatable $actor,
        public readonly CarbonImmutable $at,
    ) {}

    /**
     * The site of the subject, when it belongs to one.
     */
    public function site(): ?int
    {
        $site = $this->subject?->getAttribute('site_id');

        return $site === null ? null : (int) $site;
    }

    /**
     * @return array{id:string,event:string,occurred_at:string,site:int|null,actor:array{type:string,id:mixed}|null,subject:array{module:string|null,id:mixed}|null,data:array<mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'occurred_at' => $this->at->toIso8601String(),
            'site' => $this->site(),
            'actor' => $this->actor === null ? null : ['type' => 'user', 'id' => $this->actor->getAuthIdentifier()],
            'subject' => $this->subject === null ? null : ['module' => $this->module, 'id' => $this->subject->getKey()],
            'data' => $this->data,
        ];
    }
}
