<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

/**
 * ContentField - поле с переключателем режимов Текст/HTML/Визуальный редактор
 *
 * Аналог поля контента в Bitrix с тремя режимами редактирования.
 *
 * @example
 * ContentField::make('description')->label('Описание')
 * ContentField::make('raw_html')->label('HTML код')->modes(['text', 'html'])
 * ContentField::make('embed')->label('Код вставки')->defaultMode('html')
 */
class ContentField extends Field
{
    protected string $defaultMode = 'wysiwyg';

    protected array $modes = ['text', 'html', 'wysiwyg'];

    protected int $rows = 6;

    /**
     * 'heading' рисуется списком стилей («Обычный текст», «Заголовок 1»…),
     * 'fontSize' — списком размеров в пунктах, как в Ворде, 'color' — цветом
     * текста, 'image'/'file' берут файл из медиабиблиотеки.
     */
    protected array $toolbar = ['bold', 'italic', 'underline', 'strike', 'color', 'link', 'bulletList', 'orderedList', 'align', 'heading', 'fontSize', 'image', 'file', 'table'];

    protected int $minHeight = 200;

    public function getType(): string
    {
        return 'content';
    }

    /**
     * Set default mode (text, html, wysiwyg)
     */
    public function defaultMode(string $mode): static
    {
        $this->defaultMode = $mode;

        return $this;
    }

    /**
     * Set available modes
     *
     * @param  array  $modes  Array of modes: 'text', 'html', 'wysiwyg'
     */
    public function modes(array $modes): static
    {
        $this->modes = $modes;

        return $this;
    }

    /**
     * Set textarea rows for text/html modes
     */
    public function rows(int $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * Set toolbar buttons for WYSIWYG mode
     */
    public function toolbar(array $toolbar): static
    {
        $this->toolbar = $toolbar;

        return $this;
    }

    /**
     * Set minimum height for WYSIWYG editor
     */
    public function minHeight(int $height): static
    {
        $this->minHeight = $height;

        return $this;
    }

    /**
     * Simple mode - only text and html
     */
    public function simple(): static
    {
        $this->modes = ['text', 'html'];
        $this->defaultMode = 'text';

        return $this;
    }

    /**
     * Full mode - all three modes with wysiwyg default
     */
    public function full(): static
    {
        $this->modes = ['text', 'html', 'wysiwyg'];
        $this->defaultMode = 'wysiwyg';

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'defaultMode' => $this->defaultMode,
            'modes' => $this->modes,
            'rows' => $this->rows,
            'toolbar' => $this->toolbar,
            'minHeight' => $this->minHeight,
        ];
    }
}
