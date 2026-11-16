<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/**
 * Administrators. On top of the module permissions:
 *
 * - only a superadmin makes superadmins and hands out roles;
 * - a superadmin's account is out of reach for anyone else — whoever can set
 *   their password can sign in as them;
 * - the last active superadmin cannot be demoted, switched off or deleted,
 *   or nobody would be left to manage roles.
 */
class AdminUserController extends AdminController
{
    protected string $model = AdminUser::class;

    /**
     * Get Inertia page path prefix.
     */
    protected function getPagePrefix(): string
    {
        return 'Admin/Users';
    }

    public function edit(int $id): Response
    {
        $this->guardTarget($this->findItem($id));

        return parent::edit($id);
    }

    public function destroy(int $id): RedirectResponse
    {
        $item = $this->findItem($id);

        $this->guardTarget($item);

        if ($item instanceof AdminUser && $item->isSuperadmin() && $this->isLastSuperadmin($item)) {
            return back()->with('error', __('atrium::permissions.last_superadmin'));
        }

        return parent::destroy($id);
    }

    protected function saveItem(Request $request, string $context, Model&Administrable $item): Model&Administrable
    {
        $actor = $request->user(config('atrium.auth.guard', 'admin'));
        $promote = $actor instanceof AdminUser && $actor->isSuperadmin() && $request->has('is_superadmin')
            ? $request->boolean('is_superadmin')
            : null;

        if ($item instanceof AdminUser && $item->exists) {
            $this->guardTarget($item);

            $demoted = $promote === false || ($request->has('is_active') && ! $request->boolean('is_active'));

            if ($item->isSuperadmin() && $demoted && $this->isLastSuperadmin($item)) {
                throw ValidationException::withMessages(['is_superadmin' => __('atrium::permissions.last_superadmin')]);
            }
        }

        $item = parent::saveItem($request, $context, $item);

        // Not fillable: a request from anyone else must not be able to set it.
        if ($promote !== null) {
            $item->forceFill(['is_superadmin' => $promote])->save();
        }

        return $item;
    }

    /**
     * A superadmin's account is managed by superadmins only.
     */
    private function guardTarget(Model $item): void
    {
        $actor = request()->user(config('atrium.auth.guard', 'admin'));

        if ($item instanceof AdminUser && $item->isSuperadmin() && $actor instanceof AdminUser && ! $actor->isSuperadmin()) {
            abort(403, __('atrium::permissions.forbidden'));
        }
    }

    private function isLastSuperadmin(AdminUser $item): bool
    {
        return $this->model::query()
            ->where('is_superadmin', true)
            ->where('is_active', true)
            ->whereKeyNot($item->getKey())
            ->doesntExist();
    }
}
