<?php

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named set of permissions given to administrators.
 *
 * A permission is "{module}.{action}" — `articles.view`, `articles.delete`,
 * `api-tokens.use` (see Atrium\Admin\Auth\Permissions). An administrator with
 * several roles gets all of their permissions together.
 *
 * @property int $id
 * @property string $name
 * @property array<int,string> $permissions
 */
class AdminRole extends Model
{
    protected $table = 'admin_roles';

    protected $fillable = [
        'name',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<AdminUser, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(AdminUser::class, 'admin_role_user', 'admin_role_id', 'admin_user_id');
    }
}
