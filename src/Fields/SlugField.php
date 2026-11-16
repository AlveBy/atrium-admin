<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

class SlugField extends Field
{
    protected string $fromField = 'name';

    protected bool $autoGenerate = true;

    protected bool $showPreview = true;

    protected ?string $baseUrl = null;

    public function getType(): string
    {
        return 'slug';
    }

    /**
     * Set source field for auto-generation
     */
    public function from(string $field): static
    {
        $this->fromField = $field;

        return $this;
    }

    /**
     * Enable/disable auto-generation
     */
    public function autoGenerate(bool $auto = true): static
    {
        $this->autoGenerate = $auto;

        return $this;
    }

    /**
     * Show/hide URL preview
     */
    public function showPreview(bool $show = true): static
    {
        $this->showPreview = $show;

        return $this;
    }

    /**
     * Set base URL for preview
     */
    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'fromField' => $this->fromField,
            'autoGenerate' => $this->autoGenerate,
            'showPreview' => $this->showPreview,
            'baseUrl' => $this->baseUrl ?? config('app.url'),

            // What the address under the field is built from. Filled in by the
            // controller from the `atrium.public_urls` map — the field doesn't know
            // its model, and without one there is nowhere to get `/countries` or
            // the section path from. Empty = public address unknown, no preview.
            'publicPrefix' => null,
            'parentField' => null,
            'parentPaths' => [],
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = parent::getValidationRules($context);
        $rules[] = 'string';
        $rules[] = 'max:255';
        $rules[] = 'regex:/^[a-z0-9\-]+$/';

        return $rules;
    }
}
