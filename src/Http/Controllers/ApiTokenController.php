<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The "API tokens" section: issue a key for an external program and revoke it.
 *
 * A token acts on behalf of the administrator who issued it, and only in the
 * modules ticked when it was issued — and never in more than the
 * administrator's own roles allow. The key itself is shown once: only its
 * hash is stored.
 *
 * An administrator sees and revokes their own tokens; a superadmin, everyone's.
 */
class ApiTokenController extends Controller
{
    public function __construct(
        private readonly ApiResources $resources,
        private readonly Permissions $permissions,
    ) {}

    public function index(Request $request): Response
    {
        $modes = $this->permissions->tokenModes($this->admin($request));

        $tokens = $this->visibleTokens($request)
            ->with('tokenable')
            ->latest()
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'owner' => $token->tokenable instanceof AdminUser ? $token->tokenable->name : null,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->format('d.m.Y H:i'),
                'expires_at' => $token->expires_at?->format('d.m.Y'),
                'expired' => $token->expires_at?->isPast() ?? false,
                'created_at' => $token->created_at?->format('d.m.Y H:i'),
            ]);

        $modules = collect($modes)
            ->map(fn (array $mode, string $key): array => ['key' => $key, 'label' => $this->resources->label($key), 'writable' => $mode['write']])
            ->sortBy('label')
            ->values();

        return Inertia::render('Admin/ApiTokens/Index', [
            'tokens' => $tokens,
            'modules' => $modules,
            'plainToken' => $request->session()->get('api_token'),
            'docsUrl' => route('admin.api.docs'),
            'openApiUrl' => route('admin.api.openapi'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in($this->allowedAbilities($request))],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ], [
            'abilities.required' => __('atrium::account.api_tokens.abilities_required'),
        ], [
            'name' => __('atrium::account.api_tokens.attributes.name'),
            'abilities' => __('atrium::account.api_tokens.attributes.abilities'),
            'expires_at' => __('atrium::account.api_tokens.attributes.expires_at'),
        ]);

        $token = $this->admin($request)->createToken(
            $validated['name'],
            array_values(array_unique($validated['abilities'])),
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at'])->endOfDay() : null,
        );

        return redirect()
            ->route('admin.api-tokens.index')
            ->with('api_token', $token->plainTextToken)
            ->with('success', __('atrium::account.api_tokens.created'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->visibleTokens($request)->whereKey($id)->delete();

        return redirect()
            ->route('admin.api-tokens.index')
            ->with('success', __('atrium::account.api_tokens.revoked'));
    }

    /**
     * @return Builder<PersonalAccessToken>
     */
    private function visibleTokens(Request $request): Builder
    {
        $user = $this->admin($request);

        return PersonalAccessToken::query()
            ->where('tokenable_type', config('atrium.auth.model'))
            ->unless($user->isSuperadmin(), fn (Builder $query) => $query->where('tokenable_id', $user->getKey()));
    }

    /**
     * @return array<int,string>
     */
    private function allowedAbilities(Request $request): array
    {
        $abilities = [];

        foreach ($this->permissions->tokenModes($this->admin($request)) as $key => $mode) {
            $abilities[] = $key.':read';

            if ($mode['write']) {
                $abilities[] = $key.':write';
            }
        }

        return $abilities;
    }

    private function admin(Request $request): AdminUser
    {
        $user = $request->user(config('atrium.auth.guard', 'admin'));

        abort_unless($user instanceof AdminUser, 403);

        return $user;
    }
}
