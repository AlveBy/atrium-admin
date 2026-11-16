<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @phpstan-consistent-constructor */
abstract class Field implements Arrayable, JsonSerializable
{
    protected string $name;

    protected string $label = '';

    protected bool $required = false;

    protected bool $disabled = false;

    protected bool $readonly = false;

    protected ?string $hint = null;

    protected mixed $default = null;

    protected ?string $placeholder = null;

    protected array $rules = [];

    protected ?string $column = null;

    protected bool $showOnCreate = true;

    protected bool $showOnEdit = true;

    protected bool $showOnList = false;

    protected array $attributes = [];

    protected string $tab = 'params';

    protected int $tabOrder = 0;

    /**
     * Create a new field instance
     */
    public static function make(string $name): static
    {
        $field = new static;
        $field->name = $name;
        $field->column = $name;

        return $field;
    }

    /**
     * Set the field label
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the field as required
     */
    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Set the field as disabled
     */
    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Set the field as readonly
     */
    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;

        return $this;
    }

    /**
     * Set the field hint
     */
    public function hint(string $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    /**
     * Set the default value
     */
    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Set the placeholder
     */
    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Set validation rules
     */
    public function rules(array|string $rules): static
    {
        $this->rules = is_array($rules) ? $rules : [$rules];

        return $this;
    }

    /**
     * Add unique validation rule
     */
    public function unique(string $table, ?string $column = null): static
    {
        $column = $column ?? $this->name;
        $this->rules[] = "unique:{$table},{$column}";

        return $this;
    }

    /**
     * Set min length validation
     */
    public function minLength(int $length): static
    {
        $this->rules[] = "min:{$length}";

        return $this;
    }

    /**
     * Set the database column
     */
    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    /**
     * Show on create form
     */
    public function showOnCreate(bool $show = true): static
    {
        $this->showOnCreate = $show;

        return $this;
    }

    /**
     * Hide on create form
     */
    public function hideOnCreate(): static
    {
        $this->showOnCreate = false;

        return $this;
    }

    /**
     * Show on edit form
     */
    public function showOnEdit(bool $show = true): static
    {
        $this->showOnEdit = $show;

        return $this;
    }

    /**
     * Hide on edit form
     */
    public function hideOnEdit(): static
    {
        $this->showOnEdit = false;

        return $this;
    }

    /**
     * Only show on create form
     */
    public function onlyOnCreate(): static
    {
        $this->showOnCreate = true;
        $this->showOnEdit = false;

        return $this;
    }

    /**
     * Only show on edit form
     */
    public function onlyOnEdit(): static
    {
        $this->showOnCreate = false;
        $this->showOnEdit = true;

        return $this;
    }

    /**
     * Set additional attributes
     */
    public function withAttributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Set the tab for this field
     */
    public function tab(string $tab): static
    {
        $this->tab = $tab;

        return $this;
    }

    /**
     * Get the tab for this field
     */
    public function getTab(): string
    {
        return $this->tab;
    }

    /**
     * Set the order within the tab
     */
    public function tabOrder(int $order): static
    {
        $this->tabOrder = $order;

        return $this;
    }

    /**
     * Get the order within the tab
     */
    public function getTabOrder(): int
    {
        return $this->tabOrder;
    }

    /**
     * Get the field name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the field label.
     *
     * Cheap accessor on purpose: reading the label off toArray() would run
     * getFieldProps(), and for BelongsToManyField / TreeSelectField that means
     * a database query per field just to render a validation message.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Whether the field is declared required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get the database column
     */
    public function getColumn(): string
    {
        return $this->column ?? $this->name;
    }

    /**
     * Get the field type
     */
    abstract public function getType(): string;

    /**
     * Get the Vue component name
     */
    public function getComponent(): string
    {
        return $this->getType().'Field';
    }

    /**
     * Get validation rules.
     *
     * $context carries the request/record so that rules which depend on what
     * was actually submitted (upload vs. stored path, unique-ignore, option
     * drift) can be decided. It is optional so that existing call sites and
     * tests that ask a field for its rules in the abstract keep working.
     */
    public function getValidationRules(?FieldContext $context = null): array
    {
        return $this->baseValidationRules($context);
    }

    /**
     * The presence rule (required/nullable) plus whatever was set explicitly
     * via ->rules(), ->unique() or ->minLength().
     *
     * Subclasses whose parent contributes rules that do not apply to them —
     * GalleryField extends ImageField but holds an array of paths, not an
     * upload; BelongsToManyField extends BelongsToField but holds an array of
     * ids, not one id — call this instead of parent::getValidationRules() so
     * that an explicit ->rules() is still honoured. (The previous versions of
     * those two just hard-coded ['array'] and silently dropped ->rules().)
     */
    protected function baseValidationRules(?FieldContext $context = null): array
    {
        $rules = $this->rules;

        if ($this->required && ! in_array('required', $rules)) {
            array_unshift($rules, 'required');
        } elseif (! $this->required && ! in_array('nullable', $rules)) {
            array_unshift($rules, 'nullable');
        }

        return $rules;
    }

    /**
     * Check if field should be shown on create
     */
    public function shouldShowOnCreate(): bool
    {
        return $this->showOnCreate;
    }

    /**
     * Check if field should be shown on edit
     */
    public function shouldShowOnEdit(): bool
    {
        return $this->showOnEdit;
    }

    /**
     * Transform value before saving
     */
    public function transformValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Transform value for display
     */
    public function displayValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Get additional field props
     */
    protected function getFieldProps(): array
    {
        return [];
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return array_merge([
            'name' => $this->name,
            'type' => $this->getType(),
            'component' => $this->getComponent(),
            'label' => $this->label,
            'required' => $this->required,
            'disabled' => $this->disabled,
            'readonly' => $this->readonly,
            'hint' => $this->hint,
            'default' => $this->default,
            'placeholder' => $this->placeholder,
            'attributes' => $this->attributes,
            'tab' => $this->tab,
            'tabOrder' => $this->tabOrder,
            'ai' => method_exists($this, 'getAi') ? $this->getAi() : null,
        ], $this->getFieldProps());
    }

    /**
     * Convert to JSON
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
