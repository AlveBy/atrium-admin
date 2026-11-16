<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class WysiwygField extends Field
{
    protected array $toolbar = ['bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'heading', 'image', 'file'];

    protected int $minHeight = 200;

    protected ?int $maxHeight = null;

    protected bool $allowImages = true;

    protected ?int $maxLength = null;

    public function getType(): string
    {
        return 'wysiwyg';
    }

    /**
     * Set toolbar buttons
     */
    public function toolbar(array $buttons): static
    {
        $this->toolbar = $buttons;

        return $this;
    }

    /**
     * Simple toolbar (no images, basic formatting)
     */
    public function simple(): static
    {
        $this->toolbar = ['bold', 'italic', 'link', 'bulletList', 'orderedList'];
        $this->allowImages = false;

        return $this;
    }

    /**
     * Full toolbar
     */
    public function full(): static
    {
        $this->toolbar = [
            'bold', 'italic', 'underline', 'strike',
            'link', 'bulletList', 'orderedList',
            'heading', 'blockquote', 'code',
            'image', 'file', 'horizontalRule',
            'undo', 'redo',
        ];

        return $this;
    }

    /**
     * Set min height in pixels
     */
    public function minHeight(int $height): static
    {
        $this->minHeight = $height;

        return $this;
    }

    /**
     * Set max height in pixels
     */
    public function maxHeight(int $height): static
    {
        $this->maxHeight = $height;

        return $this;
    }

    /**
     * Allow/disallow images
     */
    public function allowImages(bool $allow = true): static
    {
        $this->allowImages = $allow;
        if (! $allow) {
            $this->toolbar = array_diff($this->toolbar, ['image']);
        }

        return $this;
    }

    /**
     * Set max length
     */
    public function maxLength(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'toolbar' => $this->toolbar,
            'minHeight' => $this->minHeight,
            'maxHeight' => $this->maxHeight,
            'allowImages' => $this->allowImages,
            'maxLength' => $this->maxLength,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);

        if ($this->maxLength) {
            $rules[] = "max:{$this->maxLength}";
        }

        return $rules;
    }
}
