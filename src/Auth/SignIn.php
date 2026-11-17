<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Auth;

use Atrium\Admin\Facades\Hook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where every way of signing in meets, for any guard.
 *
 * A first factor (the password, a code from the e-mail, a social account, a
 * passkey) finds the person and calls start(). A second-factor module may
 * step in there with challengeUsing(): it answers with its own page and calls
 * finish() once the person has passed it. finish() runs what the guard's
 * owner registered with finishUsing(): logging in, regenerating the session,
 * the redirect home.
 *
 * Nothing here knows about any particular factor, so the sign-in methods are
 * packages that can be swapped without touching the admin or the account.
 *
 * Login screens ask the filter `atrium.auth.methods` (array $methods,
 * string $guard) for the extra buttons to show: each one is
 * ['key', 'label', 'url', 'icon' => ?string, 'kind' => 'link'|'passkey'].
 */
class SignIn
{
    /** @var array<string,callable(Request,Authenticatable,bool,string):Response> */
    private array $finishers = [];

    /** @var (callable(Request,Authenticatable,string,string,bool):?Response)|null */
    private $challenge = null;

    /**
     * How the guard's owner signs a person in once every step is passed.
     *
     * @param  callable(Request,Authenticatable,bool,string):Response  $finisher  (request, user, remember, method)
     */
    public function finishUsing(string $guard, callable $finisher): void
    {
        $this->finishers[$guard] = $finisher;
    }

    /**
     * A second step between the first factor and the session. It returns its
     * own response (a page asking for a code) or null to let the person in.
     *
     * @param  (callable(Request,Authenticatable,string,string,bool):?Response)|null  $challenge  (request, user, guard, method, remember)
     */
    public function challengeUsing(?callable $challenge): void
    {
        $this->challenge = $challenge;
    }

    /**
     * The first factor has found the person: ask for the second, or let them in.
     */
    public function start(Request $request, Authenticatable $user, string $guard, string $method, bool $remember = false): Response
    {
        if ($this->challenge !== null) {
            $response = ($this->challenge)($request, $user, $guard, $method, $remember);

            if ($response !== null) {
                return $response;
            }
        }

        return $this->finish($request, $user, $guard, $method, $remember);
    }

    /**
     * Every step is passed: open the session the guard's way.
     */
    public function finish(Request $request, Authenticatable $user, string $guard, string $method, bool $remember = false): Response
    {
        $response = isset($this->finishers[$guard])
            ? ($this->finishers[$guard])($request, $user, $remember, $method)
            : $this->defaultFinish($user, $guard, $remember);

        Hook::fire('atrium.auth.signed_in', $user, $guard, $method);

        return $response;
    }

    /**
     * The extra sign-in buttons for a guard's login screen.
     *
     * @return list<array{key:string,label:string,url:string,icon?:string|null,kind?:string}>
     */
    public function methods(string $guard): array
    {
        return array_values((array) Hook::apply('atrium.auth.methods', [], $guard));
    }

    private function defaultFinish(Authenticatable $user, string $guard, bool $remember): Response
    {
        // The session guard renews the session id and the CSRF token itself.
        Auth::guard($guard)->login($user, $remember);

        return redirect()->intended('/');
    }
}
