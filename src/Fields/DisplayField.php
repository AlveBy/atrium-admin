<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only HTML on the edit form, drawn from the record by the server:
 * the lines of an order, a summary, a link to a printable page.
 *
 *     DisplayField::make('lines')->label('Items')->html(fn (Order $order): string => view('shop::admin.lines', compact('order'))->render())
 *
 * The HTML is not escaped: build it from a Blade view, which escapes what it
 * prints. Nothing is submitted or validated; the field is not on the create
 * form, where there is no record yet.
 */
class DisplayField extends Field
{
    /** @var (Closure(Model):string)|null */
    protected ?Closure $html = null;

    protected bool $showOnCreate = false;

    public function getType(): string
    {
        return 'display';
    }

    /**
     * @param  Closure(Model):string  $html
     */
    public function html(Closure $html): static
    {
        $this->html = $html;

        return $this;
    }

    public function render(Model $record): string
    {
        return $this->html === null ? '' : (string) ($this->html)($record);
    }

    /**
     * @return array<int,mixed>
     */
    public function getValidationRules(?FieldContext $context = null): array
    {
        return [];
    }
}
