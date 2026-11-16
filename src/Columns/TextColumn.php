<?php

declare(strict_types=1);

namespace Atrium\Admin\Columns;

class TextColumn extends Column
{
    protected ?int $limit = null;

    protected ?string $default = '-';

    protected bool $copyable = false;

    protected ?string $url = null;

    protected ?string $urlFrom = null;

    public function getType(): string
    {
        return 'text';
    }

    /**
     * Limit text length
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set default value when empty
     */
    public function default(string $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Make copyable
     */
    public function copyable(bool $copyable = true): static
    {
        $this->copyable = $copyable;

        return $this;
    }

    /**
     * Make clickable with URL
     */
    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Сделать ссылкой по адресу из соседней колонки той же строки.
     *
     * В отличие от url() адрес у каждой записи свой: у заявки это карточка
     * тура, с которой её оставили. Строки без адреса остаются текстом.
     */
    public function urlFrom(string $column): static
    {
        $this->urlFrom = $column;

        return $this;
    }

    protected function getColumnProps(): array
    {
        return [
            'limit' => $this->limit,
            'default' => $this->default,
            'copyable' => $this->copyable,
            'url' => $this->url,
            'urlFrom' => $this->urlFrom,
        ];
    }

    public function formatValue(mixed $value, $model): mixed
    {
        if ($value === null || $value === '') {
            return $this->default;
        }

        if ($this->limit && mb_strlen($value) > $this->limit) {
            return mb_substr($value, 0, $this->limit).'...';
        }

        return $value;
    }
}
