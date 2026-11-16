<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Atrium\Admin\Fields\Concerns\AssistedByAi;

/**
 * ContentField - a field with a Text/HTML/Visual editor mode switch
 *
 * Counterpart of the Bitrix content field with three editing modes.
 *
 * @example
 * ContentField::make('description')->label('Description')
 * ContentField::make('raw_html')->label('HTML code')->modes(['text', 'html'])
 * ContentField::make('embed')->label('Embed code')->defaultMode('html')
 */
class ContentField extends Field
{
    use AssistedByAi;

    protected string $defaultMode = 'wysiwyg';

    protected array $modes = ['text', 'html', 'wysiwyg'];

    protected int $rows = 6;

    /**
     * 'heading' is drawn as a style list ("Normal text", "Heading 1"…),
     * 'fontSize' as a list of sizes in points, like in Word, 'color' sets the
     * text colour, 'image'/'file' take a file from the media library.
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
