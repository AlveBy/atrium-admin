<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Columns;

class ImageColumn extends Column
{
    protected int $imgWidth = 50;

    protected int $imgHeight = 50;

    protected bool $rounded = false;

    protected ?string $defaultImage = null;

    public function getType(): string
    {
        return 'image';
    }

    /**
     * Set size
     */
    public function size(int $size): static
    {
        $this->imgWidth = $size;
        $this->imgHeight = $size;

        return $this;
    }

    /**
     * Set dimensions
     */
    public function dimensions(int $width, int $height): static
    {
        $this->imgWidth = $width;
        $this->imgHeight = $height;

        return $this;
    }

    /**
     * Make rounded
     */
    public function rounded(bool $rounded = true): static
    {
        $this->rounded = $rounded;

        return $this;
    }

    /**
     * Make circular
     */
    public function circular(): static
    {
        $this->rounded = true;

        return $this;
    }

    /**
     * Set default image
     */
    public function defaultImage(string $url): static
    {
        $this->defaultImage = $url;

        return $this;
    }

    protected function getColumnProps(): array
    {
        return [
            'width' => $this->imgWidth,
            'height' => $this->imgHeight,
            'rounded' => $this->rounded,
            'defaultImage' => $this->defaultImage,
        ];
    }

    public function formatValue(mixed $value, $model): mixed
    {
        if (! $value) {
            return $this->defaultImage;
        }

        if (str_starts_with($value, 'http')) {
            return $value;
        }

        return asset('storage/'.$value);
    }
}
