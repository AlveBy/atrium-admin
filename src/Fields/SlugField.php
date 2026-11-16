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

            // Из чего собирается адрес под полем. Заполняет контроллер по
            // карте `atrium.public_urls` — поле не знает своей модели,
            // а без модели неоткуда взять ни `/countries`, ни путь раздела.
            // Пусто = адрес сайта неизвестен, превью показывать нечего.
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
