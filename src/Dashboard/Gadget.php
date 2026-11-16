<?php

declare(strict_types=1);

namespace Atrium\Admin\Dashboard;

/**
 * Гаджет рабочего стола — плитка на главной странице админки.
 *
 * Повторяет устройство рабочего стола Битрикса: набор плиток задаётся
 * настройкой (`atrium.dashboard.gadgets`), каждая плитка сама решает,
 * что показать. Разница в том, что у Битрикса раскладка лежала в b_user_option
 * на пользователя и двигалась мышью, а у нас — в конфиге.
 */
abstract class Gadget
{
    /**
     * @param  array<string,mixed>  $options  настройки экземпляра из конфига
     */
    public function __construct(protected array $options = []) {}

    /** Имя Vue-компонента, который рисует плитку. */
    abstract public function component(): string;

    /** Заголовок плитки. */
    abstract public function title(): string;

    /** Данные для компонента. */
    abstract public function data(): array;

    /**
     * Ширина плитки: 'half' — половина ряда, 'full' — весь ряд.
     */
    public function width(): string
    {
        return $this->option('width', 'full') === 'half' ? 'half' : 'full';
    }

    /**
     * Плитку можно погасить, не выкидывая из конфига, — и она сама решает,
     * есть ли ей что показать (например, модель гаджета удалили).
     */
    public function isVisible(): bool
    {
        return (bool) $this->option('enabled', true);
    }

    public function toArray(): array
    {
        return [
            'component' => $this->component(),
            'title' => $this->title(),
            'width' => $this->width(),
            'data' => $this->data(),
        ];
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }
}
