<?php

declare(strict_types=1);

namespace Atrium\Admin\Traits;

use Atrium\Admin\Modules\Soft;
use Atrium\Admin\Modules\SoftMorphTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Relations to a model of another module that may not be installed.
 *
 * With the module installed they are Laravel's own relations. Without it
 * they stay relations of the same kind that find nothing: `$product->brand`
 * is null, `$product->stores` is empty, `with()` and `whereHas()` work, and
 * no table of the missing module is queried. Keep the link columns and
 * pivot tables in the module that declares the relation, without foreign
 * keys to the other module's tables.
 *
 *     public function brand(): BelongsTo
 *     {
 *         return $this->softBelongsTo(Brand::class, 'brand_id');
 *     }
 *
 * @mixin Model
 */
trait HasSoftRelations
{
    /**
     * @param  class-string<Model>  $related
     * @return BelongsTo<covariant Model,$this>
     */
    protected function softBelongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        $relation ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        if (Soft::available($related)) {
            return $this->belongsTo($related, $foreignKey, $ownerKey, $relation);
        }

        return $this->belongsTo(static::class, $foreignKey ?? Str::snake($relation).'_id', $this->getKeyName(), $relation)->whereRaw('1 = 0');
    }

    /**
     * The pivot table belongs to this module: it is there with or without
     * the other one.
     *
     * @param  class-string<Model>  $related
     * @return BelongsToMany<covariant Model,$this>
     */
    protected function softBelongsToMany(string $related, string $table, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null, ?string $relation = null): BelongsToMany
    {
        $relation ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        if (Soft::available($related)) {
            return $this->belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey, null, null, $relation);
        }

        return $this->belongsToMany(static::class, $table, $foreignPivotKey, $relatedPivotKey ?? Str::snake(class_basename($related)).'_id', null, null, $relation)
            ->whereRaw('1 = 0');
    }

    /**
     * @param  class-string<Model>  $related
     * @return HasMany<covariant Model,$this>
     */
    protected function softHasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        if (Soft::available($related)) {
            return $this->hasMany($related, $foreignKey, $localKey);
        }

        return $this->hasMany(static::class, $this->getKeyName())->whereRaw('1 = 0');
    }

    /**
     * Attach to a record of any module (reviews, comments): `{name}_type`
     * holds the model class, `{name}_id` its key. A record of a module that
     * is not installed reads as null.
     *
     * @return MorphTo<covariant Model,$this>
     */
    protected function softMorphTo(?string $name = null, ?string $type = null, ?string $id = null): MorphTo
    {
        $name ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];

        [$type, $id] = $this->getMorphs(Str::snake($name), $type, $id);

        $class = $this->getAttributeFromArray($type);

        if (is_string($class) && $class !== '') {
            $target = static::getActualClassNameForMorph($class);

            if (Soft::available($target)) {
                $instance = $this->newRelatedInstance($target);

                return new SoftMorphTo($instance->newQuery(), $this, $id, $instance->getKeyName(), $type, $name);
            }

            return (new SoftMorphTo($this->newQuery(), $this, $id, $this->getKeyName(), $type, $name))->whereRaw('1 = 0');
        }

        // No record yet, or eager loading: the type is read per record.
        return new SoftMorphTo($this->newQuery()->setEagerLoads([]), $this, $id, null, $type, $name);
    }
}
