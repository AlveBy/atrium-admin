<?php

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * The per-request context a field is being validated in.
 *
 * Three rules simply cannot be decided from the field declaration alone:
 *
 *  - upload vs. stored path. The admin image control is a stub that never
 *    produces an UploadedFile, so `image|mimes:…` may only be applied when the
 *    submitted value really is a file (see ImageField / FileField).
 *  - unique-ignore. On update a unique rule must exempt the row being edited.
 *  - option drift. A select whose stored value is no longer in the declared
 *    option list must stay editable (see SelectField).
 *
 * Passing the request/record down as one immutable object keeps
 * getValidationRules() a pure function of (field, context).
 */
final class FieldContext
{
    public const CREATE = 'create';

    public const EDIT = 'edit';

    /**
     * @param  string  $context  'create' or 'edit'
     * @param  Model|null  $record  the row being edited, null on create
     * @param  mixed  $value  the submitted value for this field
     * @param  bool  $present  whether the key was present in the request at all
     */
    public function __construct(
        public readonly string $context = self::CREATE,
        public readonly ?Model $record = null,
        public readonly mixed $value = null,
        public readonly bool $present = false,
    ) {}

    public static function create(mixed $value = null, bool $present = false): self
    {
        return new self(self::CREATE, null, $value, $present);
    }

    public static function edit(?Model $record, mixed $value = null, bool $present = false): self
    {
        return new self(self::EDIT, $record, $value, $present);
    }

    public function isCreate(): bool
    {
        return $this->context === self::CREATE;
    }

    public function isEdit(): bool
    {
        return $this->context === self::EDIT;
    }
}
