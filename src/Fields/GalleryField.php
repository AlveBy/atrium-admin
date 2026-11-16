<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class GalleryField extends ImageField
{
    protected int $maxFiles = 20;

    protected int $minFiles = 0;

    protected bool $sortable = true;

    /**
     * Whether maxFiles()/minFiles() were called, as opposed to the defaults.
     *
     * The default of 20 is a UI hint, not a business rule — turning it into a
     * validation rule would make already-saved galleries unsavable (migrated
     * Bitrix galleries often hold more photos than that).
     */
    protected bool $maxFilesExplicit = false;

    protected bool $minFilesExplicit = false;

    public function getType(): string
    {
        return 'gallery';
    }

    /**
     * Set max files
     */
    public function maxFiles(int $max): static
    {
        $this->maxFiles = $max;
        $this->maxFilesExplicit = true;

        return $this;
    }

    /**
     * Set min files
     */
    public function minFiles(int $min): static
    {
        $this->minFiles = $min;
        $this->minFilesExplicit = true;

        return $this;
    }

    /**
     * Enable/disable sorting
     */
    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return array_merge(parent::getFieldProps(), [
            'multiple' => true,
            'maxFiles' => $this->maxFiles,
            'minFiles' => $this->minFiles,
            'sortable' => $this->sortable,
        ]);
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        // Deliberately NOT parent::getValidationRules(): ImageField's
        // image/mimes/max(KB) rules describe one uploaded file, while a gallery
        // holds an array of stored paths. Going through the base keeps an
        // explicit ->rules() working, which the old hard-coded ['array'] did
        // not.
        $rules = $this->baseValidationRules($context);
        $rules[] = 'array';

        if ($this->minFilesExplicit && $this->minFiles > 0) {
            $rules[] = "min:{$this->minFiles}";
        }

        if ($this->maxFilesExplicit && $this->maxFiles > 0) {
            $rules[] = "max:{$this->maxFiles}";
        }

        return $rules;
    }
}
