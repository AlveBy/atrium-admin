<?php

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Modules\Soft;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Traits\HasSoftRelations;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An application model linked softly to the labels module: a label field,
 * a label column and relations that are there only with the module.
 *
 * @property int $id
 * @property string $name
 * @property int|null $label_id
 */
final class Gadget extends Model implements Administrable
{
    use HasSoftRelations, InteractsWithAdmin;

    protected $table = 'gadgets';

    protected $fillable = ['name', 'label_id'];

    /**
     * @return BelongsTo<covariant Model,$this>
     */
    public function label(): BelongsTo
    {
        return $this->softBelongsTo(Label::class, 'label_id');
    }

    /**
     * @return BelongsToMany<covariant Model,$this>
     */
    public function tags(): BelongsToMany
    {
        return $this->softBelongsToMany(Label::class, 'gadget_label', 'gadget_id', 'label_id');
    }

    /**
     * Gadgets that share this one's label id: a hasMany to a model that is
     * there only with the module.
     *
     * @return HasMany<covariant Model,$this>
     */
    public function labelsWithMyId(): HasMany
    {
        return $this->softHasMany(Label::class, 'id', 'label_id');
    }

    public static function getAdminLabel(): string
    {
        return 'Gadgets';
    }

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->label('Name')->required(),
            ...Soft::when(Label::class, fn (): array => [
                BelongsToField::make('label_id')->label('Label')->model(Label::class),
                BelongsToManyField::make('tags')->label('Tags')->model(Label::class),
            ]),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('name')->label('Name')->searchable(),
            ...Soft::when(Label::class, [TextColumn::make('label.name')->label('Label')->searchable()->sortable()]),
        ];
    }
}
