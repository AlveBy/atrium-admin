<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Requests\Auth;

use Atrium\Admin\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email обязателен для заполнения.',
            'email.email' => 'Введите корректный email.',
            'password.required' => 'Пароль обязателен для заполнения.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $user = $this->validateCredentials();

        Auth::guard(config('atrium.auth.guard', 'admin'))
            ->login($user, $this->boolean('remember'));
    }

    /**
     * Проверить логин с паролем, никого не впуская.
     *
     * Отдельно от `authenticate()`, потому что между проверкой пароля и входом
     * встал второй шаг — код с почты. Раньше здесь был `attempt()`, который
     * сразу открывал сессию; теперь сессию открывает тот, кто дошёл до конца.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): AdminUser
    {
        $this->ensureIsNotRateLimited();

        $guard = config('atrium.auth.guard', 'admin');
        $provider = Auth::createUserProvider(config("auth.guards.{$guard}.provider"));

        $user = $provider?->retrieveByCredentials($this->only('email', 'password'));

        if ($provider === null || ! $user instanceof AdminUser || ! $provider->validateCredentials($user, $this->only('password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Ваш аккаунт деактивирован.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->value()).'|'.$this->ip());
    }
}
