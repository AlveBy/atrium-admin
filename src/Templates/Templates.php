<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Templates;

use Atrium\Admin\Cache\RecordCache;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\PublicUrl;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;

/**
 * The templates an editor picks from for a record of a module that has a
 * public page, like the page templates of WordPress, and the one that draws
 * the record.
 *
 * A template is a Blade file. Those in `templates/{module}/` of the site's
 * views (or of a theme or a skin, see roots()) belong to the module, those
 * in `templates/all/` to every module. The file name is the template's key,
 * the name an editor sees is the comment at the top of the file:
 * `{{-- Template: Full width --}}`, a translation key or a text of the
 * site's JSON translations as well; without it, the key as a heading.
 * Packages add templates in code with source(), a site adds, replaces or
 * hides them in `atrium.templates.{module}`. "default" is always the first
 * and means the view the page is drawn with without a template.
 */
final class Templates
{
    public const DEFAULT = 'default';

    /** The folder of the templates every module may use. */
    public const SHARED = 'all';

    /** @var array<int,Closure():array<int,string>> */
    private array $roots = [];

    /** @var array<class-string<Model>,Closure():array<string,string>> */
    private array $sources = [];

    /** @var array<string,array<string,string>> the views found by module */
    private array $found = [];

    /** @var array{class:class-string<Model>,key:string}|null the choice in the form being saved */
    private ?array $pending = null;

    public function __construct(private readonly Modules $modules) {}

    /**
     * Where else templates are looked for, besides the site's views: a theme
     * or a skin names the prefix of its views (`atrium-theme::`,
     * `atrium-theme::skins.travel.`), which has `templates/` below it.
     * Asked each time the templates are listed, so a skin may depend on the
     * request. The site's own views come first, then the roots in the order
     * they were added.
     *
     * @param  Closure():array<int,string>  $prefixes
     */
    public function roots(Closure $prefixes): void
    {
        $this->roots[] = $prefixes;
        $this->forget();
    }

    /**
     * Templates a package brings in code: view by key. A "default" among
     * them is the view of the default template.
     *
     * @param  class-string<Model>  $model
     * @param  Closure():array<string,string>  $templates
     */
    public function source(string $model, Closure $templates): void
    {
        $this->sources[$model] = $templates;
    }

    /**
     * Forget what the folders held: after a template is added or removed.
     */
    public function forget(): void
    {
        $this->found = [];
    }

    /**
     * The name of the module's folder: its key, or the model's table for a
     * model of the application itself.
     *
     * @param  class-string<Model>  $model
     */
    public function module(string $model): string
    {
        return $this->modules->ownerOf($model)?->key() ?? (new $model)->getTable();
    }

    /**
     * The column a model keeps the key in when it has one of its own (a
     * page): it implements OwnsTemplateColumn. Null: the choice is kept in
     * admin_record_templates.
     *
     * @param  class-string<Model>  $model
     */
    public function ownColumn(string $model): ?string
    {
        return is_subclass_of($model, OwnsTemplateColumn::class) ? $model::templateColumn() : null;
    }

    /**
     * Whether the form of the model should offer a template: it has a page
     * and there is a template besides the default one.
     *
     * @param  class-string<Model>  $model
     */
    public function offers(string $model): bool
    {
        return PublicUrl::ruleFor($model) !== null && $this->available($model) !== [];
    }

    /**
     * The templates of a model besides the default one, by key.
     *
     * @param  class-string<Model>  $model
     * @return array<string,Template>
     */
    public function available(string $model): array
    {
        $templates = [];

        foreach ($this->views($model) as $key => $view) {
            // A file named 404 has an integer for a key.
            $key = (string) $key;

            if ($key !== self::DEFAULT) {
                $templates[$key] = new Template($key, $view, $this->label($view, $key));
            }
        }

        return $templates;
    }

    /**
     * What the editor picks from: key => name, "default" first.
     *
     * @param  class-string<Model>  $model
     * @return array<string,string>
     */
    public function options(string $model): array
    {
        return [self::DEFAULT => __('atrium::templates.default')]
            + array_map(static fn (Template $template): string => $template->label, $this->available($model));
    }

    /**
     * The view the model's default template names, when a package or the
     * site's config names one.
     *
     * @param  class-string<Model>  $model
     */
    public function defaultView(string $model): ?string
    {
        return $this->views($model)[self::DEFAULT] ?? null;
    }

    /**
     * The key of the template picked for the record: "default" when none
     * is, or the one picked has been removed since.
     */
    public function key(Model $record): string
    {
        $key = $this->picked($record);

        return $key !== null && $key !== self::DEFAULT && isset($this->views($record::class)[$key]) ? $key : self::DEFAULT;
    }

    /**
     * The view that draws a model's record by the key of its template, or
     * $default, the view it is drawn with otherwise, when it has none, no
     * template is known by the key or the file is gone.
     *
     * @param  class-string<Model>  $model
     */
    public function view(string $model, ?string $key, string $default): string
    {
        $default = $this->defaultView($model) ?? $default;
        $view = $key === null || $key === self::DEFAULT ? null : ($this->views($model)[$key] ?? null);

        return $view !== null && view()->exists($view) ? $view : $default;
    }

    /**
     * The view that draws a record, by the template it was given.
     */
    public function viewOf(Model $record, string $default): string
    {
        return $this->view($record::class, $this->picked($record), $default);
    }

    /**
     * Pick the template of a record: the default one forgets the choice.
     */
    public function pick(Model $record, ?string $key): void
    {
        $where = ['templatable_type' => $record->getMorphClass(), 'templatable_id' => $record->getKey()];

        if ($key === null || $key === self::DEFAULT) {
            foreach (RecordTemplate::query()->where($where)->get() as $row) {
                $row->delete();
            }

            return;
        }

        RecordTemplate::query()->updateOrCreate($where, ['template' => $key]);
    }

    /**
     * The choice in the form being saved: the record does not exist yet, so
     * it is kept until `atrium.record.saved` says where it was saved.
     *
     * @param  class-string<Model>  $model
     */
    public function hold(string $model, ?string $key): void
    {
        $this->pending = $key === null ? null : ['class' => $model, 'key' => $key];
    }

    /**
     * Write the choice held for the model of the record just saved.
     */
    public function release(Model $record): void
    {
        if ($this->pending === null || $this->pending['class'] !== $record::class) {
            return;
        }

        $key = $this->pending['key'];
        $this->pending = null;
        $this->pick($record, $key);
    }

    /**
     * The key kept for the record, valid or not.
     */
    private function picked(Model $record): ?string
    {
        $model = $record::class;

        // No template to draw with, so no query on the public page.
        if ($this->views($model) === []) {
            return null;
        }

        $column = $this->ownColumn($model);

        if ($column !== null) {
            $key = $record->getAttribute($column);

            return is_string($key) ? $key : null;
        }

        // Kept with the cache of the module's records, so a warm page runs no
        // query; saving the choice drops it, as it drops the record's cards.
        $key = app(RecordCache::class)->remember($model, "template:{$record->getKey()}", fn (): mixed => RecordTemplate::query()
            ->where('templatable_type', $record->getMorphClass())
            ->where('templatable_id', $record->getKey())
            ->value('template'));

        return is_string($key) ? $key : null;
    }

    /**
     * Key => view: what packages brought, then what the folders and the
     * config add, in that order of the list; a site's config wins over a
     * file, a file over a package.
     *
     * @param  class-string<Model>  $model
     * @return array<string,string>
     */
    private function views(string $model): array
    {
        $module = $this->module($model);
        $given = isset($this->sources[$model]) ? ($this->sources[$model])() : [];
        $files = $this->found[$module] ??= $this->discover($module);
        $config = config('atrium.templates.'.$module, []);
        $views = [];

        foreach ([...array_keys($given), ...array_keys($config), ...array_keys($files)] as $key) {
            $view = array_key_exists($key, $config) ? $config[$key] : ($files[$key] ?? $given[$key]);

            // A key set to null or false in the config is taken away.
            if (is_string($view)) {
                $views[$key] = $view;
            }
        }

        return $views;
    }

    /**
     * Key => view of the files of the module's folder and of the shared one,
     * in every root: the first to have a key keeps it.
     *
     * @return array<string,string>
     */
    private function discover(string $module): array
    {
        $found = [];

        foreach ($this->prefixes() as $prefix) {
            foreach ([$module, self::SHARED] as $folder) {
                foreach ($this->names($prefix, $folder) as $name) {
                    $found[$name] ??= $prefix.'templates.'.$folder.'.'.$name;
                }
            }
        }

        return $found;
    }

    /**
     * @return array<int,string>
     */
    private function prefixes(): array
    {
        $prefixes = [''];

        foreach ($this->roots as $roots) {
            array_push($prefixes, ...$roots());
        }

        return $prefixes;
    }

    /**
     * The names of the Blade files in a folder below a view prefix.
     *
     * @return array<int,string>
     */
    private function names(string $prefix, string $folder): array
    {
        $namespace = str_contains($prefix, '::') ? Str::before($prefix, '::') : null;
        $path = Str::after($prefix, '::');
        $finder = view()->getFinder();

        // Only the file finder knows folders; any other finds nothing to list.
        if (! $finder instanceof FileViewFinder) {
            return [];
        }

        $roots = $namespace === null ? $finder->getPaths() : ($finder->getHints()[$namespace] ?? []);
        $names = [];

        foreach ($roots as $root) {
            $directory = $root.'/'.str_replace('.', '/', $path).'templates/'.$folder;

            foreach (File::glob($directory.'/*.blade.php') as $file) {
                $name = basename($file, '.blade.php');

                // A name that is a view name too: no dots, no spaces; and "default"
                // is the view the page has without a template, not a template.
                if ($name !== self::DEFAULT && preg_match('/^[A-Za-z0-9_-]+$/', $name) === 1) {
                    $names[] = $name;
                }
            }
        }

        // A name found in two places is found twice, and discover() keeps the first.
        sort($names);

        return $names;
    }

    /**
     * The name of a template: the comment at the top of its file, else its
     * key as a heading.
     */
    private function label(string $view, string $key): string
    {
        try {
            $path = view()->getFinder()->find($view);
        } catch (InvalidArgumentException) {
            return Str::headline($key);
        }

        $head = File::get($path);

        // The copyright header is a comment too: the name may follow it.
        while (preg_match('/\A\s*\{\{--(.*?)--\}\}/s', $head, $comment) === 1) {
            $text = trim($comment[1]);

            if (str_starts_with($text, 'Template:') && ($label = trim(substr($text, 9))) !== '') {
                // A translation key or a text of the JSON translations; the text itself otherwise.
                $line = Lang::get($label);

                return is_string($line) ? $line : $label;
            }

            $head = substr($head, strlen($comment[0]));
        }

        return Str::headline($key);
    }
}
