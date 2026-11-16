<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Models\AdminUser;
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
 * modules ticked when it was issued. The key itself is shown once: only its
 * hash is stored.
 */
class ApiTokenController extends Controller
{
    public function __construct(private readonly ApiResources $resources) {}

    public function index(Request $request): Response
    {
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', config('atrium.auth.model'))
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

        $modules = collect($this->resources->all())
            ->keys()
            ->map(fn (string $key): array => ['key' => $key, 'label' => $this->resources->label($key)])
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
            'abilities.*' => ['string', Rule::in($this->resources->abilities())],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ], [
            'abilities.required' => __('atrium::account.api_tokens.abilities_required'),
        ], [
            'name' => __('atrium::account.api_tokens.attributes.name'),
            'abilities' => __('atrium::account.api_tokens.attributes.abilities'),
            'expires_at' => __('atrium::account.api_tokens.attributes.expires_at'),
        ]);

        $token = $request->user('admin')->createToken(
            $validated['name'],
            array_values(array_unique($validated['abilities'])),
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at'])->endOfDay() : null,
        );

        return redirect()
            ->route('admin.api-tokens.index')
            ->with('api_token', $token->plainTextToken)
            ->with('success', __('atrium::account.api_tokens.created'));
    }

    public function destroy(int $id): RedirectResponse
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', config('atrium.auth.model'))
            ->whereKey($id)
            ->delete();

        return redirect()
            ->route('admin.api-tokens.index')
            ->with('success', __('atrium::account.api_tokens.revoked'));
    }
}
