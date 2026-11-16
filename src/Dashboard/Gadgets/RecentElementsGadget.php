<?php

declare(strict_types=1);

namespace Atrium\Admin\Dashboard\Gadgets;

use Atrium\Admin\Dashboard\Gadget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * «Элементы инфоблока» — последние изменённые элементы одного раздела.
 *
 * Повторяет гаджет bitrix/gadgets/bitrix/admin_iblock_elements: дата, картинка
 * анонса, заголовок ссылкой на форму редактирования и «Все элементы» внизу.
 *
 * У заказчика этот гаджет на рабочем столе стоял, но настроен не был — в
 * b_user_option у него нет ключа SETTINGS, а без IBLOCK_ID гаджет Битрикса не
 * рисует ничего. То есть копировать там нечего: в админке висела пустая рамка
 * с заголовком. Поэтому берём смысл гаджета, а инфоблок задаём в конфиге.
 *
 * Сортировка по «изменён», а не по ID, как у Битрикса по умолчанию: гаджет
 * нужен, чтобы вернуться к тому, что правил вчера, а не к тому, что завёл.
 */
final class RecentElementsGadget extends Gadget
{
    private const MONTHS = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    public function component(): string
    {
        return 'RecentElementsGadget';
    }

    public function title(): string
    {
        $title = $this->option('title');

        if (is_string($title) && $title !== '') {
            return $title;
        }

        $model = $this->modelClass();

        if ($model !== null && method_exists($model, 'getAdminLabel')) {
            return (string) $model::getAdminLabel();
        }

        return 'Элементы инфоблока';
    }

    public function isVisible(): bool
    {
        return parent::isVisible() && $this->modelClass() !== null;
    }

    public function data(): array
    {
        $model = $this->modelClass();

        if ($model === null) {
            return ['items' => [], 'allUrl' => null, 'allLabel' => 'Все элементы'];
        }

        return [
            'items' => $this->items($model),
            'allUrl' => $this->routeTo($model, 'index'),
            'allLabel' => 'Все элементы',
        ];
    }

    /** @return class-string<Model>|null */
    private function modelClass(): ?string
    {
        $class = $this->option('model');

        return is_string($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function items(string $model): array
    {
        $count = max(1, min((int) $this->option('count', 5), 20));

        try {
            /** @var Model $sample */
            $sample = new $model;
            $table = $sample->getTable();

            $sortColumn = $sample->usesTimestamps() && Schema::hasColumn($table, $sample->getUpdatedAtColumn())
                ? $sample->getUpdatedAtColumn()
                : $sample->getKeyName();

            $pictureColumn = $this->firstExistingColumn($table, ['preview_picture', 'detail_picture']);

            return $model::query()
                ->orderByDesc($sortColumn)
                ->orderByDesc($sample->getKeyName())
                ->limit($count)
                ->get()
                ->map(fn (Model $item) => [
                    'id' => $item->getKey(),
                    'name' => (string) ($item->getAttribute('name') ?: '#'.$item->getKey()),
                    'date' => $this->formatDate($item->getAttribute($sortColumn)),
                    'image' => $pictureColumn ? $this->imageUrl($item->getAttribute($pictureColumn)) : null,
                    'url' => $this->routeTo($model, 'edit', $item->getKey()),
                ])
                ->all();
        } catch (Throwable) {
            // Таблицы может не быть (модель есть, миграция не прогонялась) —
            // рабочий стол из-за одной плитки падать не должен.
            return [];
        }
    }

    /** @param array<int,string> $candidates */
    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value instanceof \DateTimeInterface) {
            return null;
        }

        // «3 августа 2026» — как FormatDate("j F Y") в гаджете Битрикса.
        return $value->format('j').' '.self::MONTHS[(int) $value->format('n')].' '.$value->format('Y');
    }

    private function imageUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return str_starts_with($value, 'http') || str_starts_with($value, '/')
            ? $value
            : asset('storage/'.$value);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function routeTo(string $model, string $action, mixed $key = null): ?string
    {
        if (! method_exists($model, 'getAdminRouteName')) {
            return null;
        }

        $name = $model::getAdminRouteName().'.'.$action;

        if (! Route::has($name)) {
            return null;
        }

        return $key === null ? route($name) : route($name, $key);
    }
}
