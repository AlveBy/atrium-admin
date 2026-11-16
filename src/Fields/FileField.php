<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Illuminate\Http\UploadedFile;

class FileField extends Field
{
    protected string $disk = 'public';

    protected string $directory = 'uploads';

    protected int $maxSize = 10240; // KB

    protected array $acceptedTypes = [];

    protected ?string $accept = null;

    public function getType(): string
    {
        return 'file';
    }

    /**
     * Set storage disk
     */
    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Set directory
     */
    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Set max file size in KB
     */
    public function maxSize(int $sizeKb): static
    {
        $this->maxSize = $sizeKb;

        return $this;
    }

    /**
     * Set accepted file types
     */
    public function acceptedTypes(array $types): static
    {
        $this->acceptedTypes = $types;

        return $this;
    }

    /**
     * Set accept attribute
     */
    public function accept(string $accept): static
    {
        $this->accept = $accept;

        return $this;
    }

    /**
     * Accept only documents
     */
    public function documents(): static
    {
        $this->acceptedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $this->accept = '.pdf,.doc,.docx';

        return $this;
    }

    /**
     * Accept only PDF
     */
    public function pdf(): static
    {
        $this->acceptedTypes = ['application/pdf'];
        $this->accept = '.pdf';

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'disk' => $this->disk,
            'directory' => $this->directory,
            'maxSize' => $this->maxSize,
            'acceptedTypes' => $this->acceptedTypes,
            'accept' => $this->accept,
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);

        // Same reasoning as ImageField: the admin control submits the stored
        // path, not an UploadedFile, so file rules only apply to a real upload.
        if ($context?->value instanceof UploadedFile) {
            $rules[] = 'file';
            $rules[] = "max:{$this->maxSize}";

            if (! empty($this->acceptedTypes)) {
                $mimes = array_map(function ($type) {
                    return str_replace('application/', '', $type);
                }, $this->acceptedTypes);
                $rules[] = 'mimes:'.implode(',', $mimes);
            }

            return $rules;
        }

        $rules[] = 'string';
        $rules[] = 'max:2048';

        return $rules;
    }
}
