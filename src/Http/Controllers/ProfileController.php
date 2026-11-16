<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Admin/Profile/Edit', [
            'user' => $request->user('admin'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('admin');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admin_users,email,'.$user->id],
        ], [
            'name.required' => __('atrium::account.profile.validation.name_required'),
            'email.required' => __('atrium::account.profile.validation.email_required'),
            'email.email' => __('atrium::account.profile.validation.email_invalid'),
            'email.unique' => __('atrium::account.profile.validation.email_taken'),
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', __('atrium::account.profile.updated'));
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ], [
            'current_password.required' => __('atrium::account.profile.validation.current_password_required'),
            'current_password.current_password' => __('atrium::account.profile.validation.current_password_wrong'),
            'password.required' => __('atrium::account.profile.validation.password_required'),
            'password.confirmed' => __('atrium::account.profile.validation.password_mismatch'),
        ]);

        $request->user('admin')->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', __('atrium::account.profile.password_updated'));
    }
}
