<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Builder\Layout;
use Atrium\Admin\Builder\LayoutValidator;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Contracts\Previewable;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\LayoutField;
use Atrium\Admin\History\ActivityLog;
use Atrium\Admin\History\Revisions;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Revision;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Transfer\RecordWriter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The page editor: the screen a record's layout field is built on, its
 * preview, its draft and publishing (docs/site-builder.md). Its routes check
 * "update" of the module.
 *
 * Publishing saves the one field through the module's form, so it is
 * validated, logged and kept as a version like any save. A draft is not on
 * the record: it waits beside the versions (Revisions::draft()).
 *
 * Two people may have the same page open. A step names the draft the editor
 * has seen and, when it publishes, the published tree it started from; if
 * either changed meanwhile, the step is refused (409) until it is repeated
 * with `force`.
 */
final class BuilderController extends Controller
{
    public function __construct(
        private readonly ApiResources $resources,
        private readonly Revisions $revisions,
        private readonly ContentLanguages $languages,
    ) {}

    /**
     * Where the page editor of each layout field of a record is, for its edit
     * form; none for a module without the editor's routes.
     *
     * @param  array<int,Field>  $fields  those of the form
     * @return array<string,string> field name => address
     */
    public static function urlsFor(string $routeName, int $id, array $fields): array
    {
        if (! Route::has("{$routeName}.builder")) {
            return [];
        }

        $urls = [];

        foreach ($fields as $field) {
            if (self::opens($field)) {
                $urls[$field->getName()] = route("{$routeName}.builder", ['id' => $id, 'field' => $field->getName()]);
            }
        }

        return $urls;
    }

    public function show(int $id, string $field, string $module): Response
    {
        [$record, $layout] = $this->target($module, $id, $field);
        $published = $record->getAttribute($layout->getColumn());
        $draft = $this->revisions->draft($record, $field);
        $url = static fn (string $step): string => route("admin.{$module}.builder.{$step}", ['id' => $id, 'field' => $field]);

        return Inertia::render('Admin/Builder/Editor', [
            'title' => ActivityLog::titleOf($record),
            'adminLabel' => $this->resources->label($module),
            'routeName' => "admin.{$module}",
            'recordId' => $id,
            'field' => $layout->toArray(),
            // What the editor opens with: the draft, when there is one.
            'tree' => $draft === null ? $published : $draft->values[$field],
            'published' => $published,
            'base' => $draft->base ?? Layout::fingerprint($published),
            'draft' => $draft === null ? null : $this->described($draft, $published),
            'versions' => $this->versions($record, $layout, $module),
            // A record that is switched off: publishing does not put it on the site.
            'hidden' => array_key_exists('active', $record->getAttributes()) && ! $record->getAttribute('active'),
            'urls' => [
                'edit' => route("admin.{$module}.edit", $id),
                'preview' => $record instanceof Previewable ? $url('preview') : null,
                'draft' => $url('draft'),
                'discard' => $url('discard'),
                'publish' => $url('publish'),
            ],
        ]);
    }

    /**
     * The record's page as the site would draw it with this tree. Nothing is
     * saved.
     */
    public function preview(Request $request, int $id, string $field, string $module): HttpResponse
    {
        [$record, $layout] = $this->target($module, $id, $field);

        abort_unless($record instanceof Previewable, 404);

        $data = $request->validate([
            'tree' => ['present', 'nullable', 'array'],
            'language' => ['required', Rule::in($this->languages->codes())],
        ]);

        $record->setAttribute($layout->getColumn(), $data['tree']);

        $locale = app()->getLocale();
        app()->setLocale($data['language']);

        try {
            $html = $record->adminPreview($data['language'])->render();
        } finally {
            app()->setLocale($locale);
        }

        return response($this->framed($html));
    }

    /**
     * Saves the tree as the draft. Its blocks may be unfinished; only
     * publishing asks for them to be filled in.
     */
    public function draft(Request $request, int $id, string $field, string $module): JsonResponse
    {
        $data = $this->step($request, tree: true);

        return $this->locked($module, $id, $field, function (Model&Administrable $record, LayoutField $layout) use ($request, $data, $field): JsonResponse {
            $published = $record->getAttribute($layout->getColumn());
            $draft = $this->revisions->draft($record, $field);

            if (! $request->boolean('force') && $draft?->id !== $this->seen($data)) {
                return $this->conflict('draft', $draft, $published);
            }

            $refusal = $this->refusal($data['tree'], $published, complete: false);

            if ($refusal !== null) {
                return $refusal;
            }

            $kept = $this->revisions->keepDraft($record, $field, $data['tree'], $data['base'], $this->adminName());

            return response()->json(['draft' => $this->described($kept, $published)]);
        });
    }

    /**
     * Throws the draft away: the editor goes back to what is published.
     */
    public function discard(Request $request, int $id, string $field, string $module): JsonResponse
    {
        $data = $this->step($request, tree: false);

        return $this->locked($module, $id, $field, function (Model&Administrable $record, LayoutField $layout) use ($request, $data, $field, $module): JsonResponse {
            $draft = $this->revisions->draft($record, $field);

            if (! $request->boolean('force') && $draft?->id !== $this->seen($data)) {
                return $this->conflict('draft', $draft, $record->getAttribute($layout->getColumn()));
            }

            $this->revisions->dropDraft($record, $field);

            return $this->publishedState($record, $layout, $module);
        });
    }

    /**
     * Puts the tree on the record: saved as the module's form saves it
     * (validation, hooks, log, a version). The draft is done with.
     *
     * @throws ValidationException when the form does not take the record so
     */
    public function publish(Request $request, int $id, string $field, string $module): JsonResponse
    {
        $data = $this->step($request, tree: true);

        return $this->locked($module, $id, $field, function (Model&Administrable $record, LayoutField $layout) use ($request, $data, $field, $module): JsonResponse {
            $published = $record->getAttribute($layout->getColumn());
            $draft = $this->revisions->draft($record, $field);

            if (! $request->boolean('force')) {
                if ($draft?->id !== $this->seen($data)) {
                    return $this->conflict('draft', $draft, $published);
                }

                if ($data['base'] !== Layout::fingerprint($published)) {
                    return $this->conflict('published', $draft, $published);
                }
            }

            // The form checks the tree too; asked first, to say which node is wrong.
            $refusal = $this->refusal($data['tree'], $published, complete: true);

            if ($refusal !== null) {
                return $refusal;
            }

            // The form saves a copy of the record and hands that back.
            $saved = (new RecordWriter($record::class))->write([$field => $data['tree']], $record);
            $this->revisions->dropDraft($record, $field);

            return $this->publishedState($saved, $layout, $module);
        });
    }

    /**
     * The tree of an earlier version, for the editor to open.
     */
    public function version(int $id, string $field, int $revision, string $module): JsonResponse
    {
        [$record] = $this->target($module, $id, $field);

        abort_unless($this->revisions->enabledFor($record::class), 404);

        $values = $this->revisions->of($record)->findOrFail($revision)->values;

        abort_unless(array_key_exists($field, $values), 404);

        return response()->json(['tree' => $values[$field]]);
    }

    /**
     * Whether the editor works on this field: a layout one the form lets change.
     *
     * @phpstan-assert-if-true LayoutField $field
     */
    private static function opens(Field $field): bool
    {
        return $field instanceof LayoutField && $field->isEditable();
    }

    /**
     * The record and its layout field; 404 when the module's form has no
     * such field to change.
     *
     * @return array{0:Model&Administrable,1:LayoutField}
     */
    private function target(string $module, int $id, string $field, bool $lock = false): array
    {
        $model = $this->resources->modules()[$module];

        // A module whose records come from elsewhere is only read.
        abort_if(method_exists($model, 'isEditableInAdmin') && ! $model::isEditableInAdmin(), 403);

        $record = $model::query()->when($lock, static fn (Builder $query) => $query->lockForUpdate())->findOrFail($id);

        foreach ((new RecordWriter($model))->fields() as $one) {
            if ($one->getName() === $field && $one->shouldShowOnEdit() && self::opens($one)) {
                return [$record, $one];
            }
        }

        abort(404);
    }

    /**
     * Runs a step that changes things with the record locked, so that two
     * saves of one page take turns.
     *
     * @param  Closure(Model&Administrable,LayoutField):JsonResponse  $change
     */
    private function locked(string $module, int $id, string $field, Closure $change): JsonResponse
    {
        return DB::transaction(function () use ($module, $id, $field, $change): JsonResponse {
            return $change(...$this->target($module, $id, $field, lock: true));
        }, attempts: 3);
    }

    /**
     * What a step of the editor sends: the draft it has seen (null: none)
     * and, to save or publish, the tree and the fingerprint it started from.
     *
     * @return array{draft:int|string|null,tree?:array<array-key,mixed>|null,base?:string}
     */
    private function step(Request $request, bool $tree): array
    {
        return $request->validate([
            'draft' => ['present', 'nullable', 'integer'],
            'force' => ['boolean'],
            ...($tree ? [
                'tree' => ['present', 'nullable', 'array'],
                'base' => ['required', 'string', 'size:64'],
            ] : []),
        ]);
    }

    /**
     * @param  array{draft:int|string|null}  $data
     */
    private function seen(array $data): ?int
    {
        return $data['draft'] === null ? null : (int) $data['draft'];
    }

    /**
     * 422 with the problem of the tree and the node it is in, or null.
     *
     * @param  array<array-key,mixed>|null  $tree
     */
    private function refusal(?array $tree, mixed $published, bool $complete): ?JsonResponse
    {
        if ($tree === null) {
            return null;
        }

        $validator = app(LayoutValidator::class);
        $problem = $validator->problem($tree, $published, $complete);

        return $problem === null ? null : response()->json(['message' => $problem, 'node' => $validator->failedAt()], 422);
    }

    private function conflict(string $kind, ?Revision $draft, mixed $published): JsonResponse
    {
        return response()->json([
            'conflict' => $kind,
            'message' => __("atrium::builder.conflicts.{$kind}"),
            'draft' => $draft === null ? null : $this->described($draft, $published),
        ], 409);
    }

    /** The editor with nothing unpublished. */
    private function publishedState(Model&Administrable $record, LayoutField $layout, string $module): JsonResponse
    {
        $published = $record->getAttribute($layout->getColumn());

        return response()->json([
            'tree' => $published,
            'published' => $published,
            'base' => Layout::fingerprint($published),
            'draft' => null,
            'versions' => $this->versions($record, $layout, $module),
        ]);
    }

    /**
     * @return array{id:int,at:string|null,author:string|null,stale:bool}
     */
    private function described(Revision $draft, mixed $published): array
    {
        return [
            'id' => $draft->id,
            'at' => $draft->created_at?->toIso8601String(),
            'author' => $draft->author,
            // What is published changed since the draft was started from it.
            'stale' => $draft->base !== Layout::fingerprint($published),
        ];
    }

    /**
     * The versions in which the tree changed, newest first: saves that left
     * it as it was are not listed again.
     *
     * @return array<int,array{id:int,at:string|null,admin:string|null,source:string|null,url:string,current:bool}>
     */
    private function versions(Model&Administrable $record, LayoutField $layout, string $module): array
    {
        if (! $this->revisions->enabledFor($record::class)) {
            return [];
        }

        $name = $layout->getName();
        $published = Layout::fingerprint($record->getAttribute($layout->getColumn()));
        $listed = [];
        $before = null;

        foreach ($this->revisions->of($record)->with('activity')->get()->reverse() as $version) {
            if (! array_key_exists($name, $version->values)) {
                continue;
            }

            $print = Layout::fingerprint($version->values[$name]);

            if ($print !== $before) {
                $listed[] = [
                    ...$version->summary(),
                    'url' => route("admin.{$module}.builder.version", ['id' => $record->getKey(), 'field' => $name, 'revision' => $version->id]),
                ];
            }

            $before = $print;
        }

        $newest = array_reverse($listed);

        // Only the newest of them can be what the site shows now: an older one
        // with the same tree is a version the page has come back to.
        return array_map(
            static fn (array $version, int $at): array => [...$version, 'current' => $at === 0 && $before === $published],
            $newest,
            array_keys($newest),
        );
    }

    /**
     * The page as the editor's frame takes it: none of its scripts runs
     * there, and its relative addresses start at the site.
     */
    private function framed(string $html): string
    {
        $head = '<meta http-equiv="Content-Security-Policy" content="script-src \'none\'"><base href="'.e(url('/')).'/">';

        // Right after the opening tag of the head, before anything it loads.
        if (preg_match('/<head\b[^>]*>/i', $html, $tag, PREG_OFFSET_CAPTURE) !== 1) {
            return $head.$html;
        }

        return substr_replace($html, $head, $tag[0][1] + strlen($tag[0][0]), 0);
    }

    private function adminName(): ?string
    {
        $user = auth()->guard(config('atrium.auth.guard', 'admin'))->user();

        return $user instanceof AdminUser ? $user->name : null;
    }
}
