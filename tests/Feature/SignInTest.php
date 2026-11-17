<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Auth\SignIn;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Http\Controllers\Auth\LoginController;
use Atrium\Admin\Http\Requests\Auth\LoginRequest;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where every way of signing in meets: the second step a module may put in
 * between, the guard's own way of opening the session, and the buttons of
 * the login screen.
 */
final class SignInTest extends TestCase
{
    /** @var list<array{string, mixed}> */
    private array $calls = [];

    private function signIn(): SignIn
    {
        return $this->app->make(SignIn::class);
    }

    private function request(): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app->make('session.store'));

        return $request;
    }

    private function recordFinish(string $guard): void
    {
        $this->signIn()->finishUsing($guard, function (Request $request, Authenticatable $user, bool $remember, string $method): Response {
            $this->calls[] = ['finish', [$user->getAuthIdentifier(), $remember, $method]];

            return new Response('finished');
        });
    }

    public function test_it_is_one_for_the_whole_application(): void
    {
        $this->assertSame($this->signIn(), $this->app->make(SignIn::class));
    }

    public function test_without_a_second_step_the_guard_s_owner_lets_the_person_in_and_everybody_hears_of_it(): void
    {
        $admin = $this->admin();
        $this->recordFinish('admin');
        Hook::on('atrium.auth.signed_in', function (Authenticatable $user, string $guard, string $method): void {
            $this->calls[] = ['signed_in', [$user->getAuthIdentifier(), $guard, $method]];
        });

        $response = $this->signIn()->start($this->request(), $admin, 'admin', 'password');

        $this->assertSame('finished', $response->getContent());
        // Not remembered unless asked.
        $this->assertSame([
            ['finish', [$admin->id, false, 'password']],
            ['signed_in', [$admin->id, 'admin', 'password']],
        ], $this->calls);
    }

    public function test_a_second_step_that_answers_keeps_the_person_out_until_it_is_passed(): void
    {
        $admin = $this->admin();
        $this->recordFinish('admin');
        $this->signIn()->challengeUsing(function (Request $request, Authenticatable $user, string $guard, string $method, bool $remember): Response {
            $this->calls[] = ['challenge', [$user->getAuthIdentifier(), $guard, $method, $remember]];

            return new Response('a code, please');
        });

        $response = $this->signIn()->start($this->request(), $admin, 'admin', 'code', true);

        $this->assertSame('a code, please', $response->getContent());
        $this->assertSame([['challenge', [$admin->id, 'admin', 'code', true]]], $this->calls);
    }

    public function test_a_second_step_with_nothing_to_ask_lets_the_person_through(): void
    {
        $admin = $this->admin();
        $this->recordFinish('admin');
        $this->signIn()->challengeUsing(fn (): ?Response => null);

        $this->assertSame('finished', $this->signIn()->start($this->request(), $admin, 'admin', 'password', true)->getContent());
        $this->assertSame([['finish', [$admin->id, true, 'password']]], $this->calls);
    }

    public function test_a_second_step_taken_away_no_longer_stands_in_the_way(): void
    {
        $admin = $this->admin();
        $this->recordFinish('admin');
        $this->signIn()->challengeUsing(fn (): Response => new Response('a code, please'));
        $this->signIn()->challengeUsing(null);

        $this->assertSame('finished', $this->signIn()->start($this->request(), $admin, 'admin', 'password')->getContent());
    }

    public function test_once_the_second_step_is_passed_finish_opens_the_session_not_remembered_by_default(): void
    {
        $admin = $this->admin();
        $this->recordFinish('admin');

        $this->assertSame('finished', $this->signIn()->finish($this->request(), $admin, 'admin', 'totp')->getContent());
        $this->assertSame([['finish', [$admin->id, false, 'totp']]], $this->calls);
    }

    public function test_a_guard_nobody_registered_logs_in_on_its_own_with_a_fresh_session(): void
    {
        $admin = $this->admin();
        $this->app['config']->set('auth.guards.backstage', ['driver' => 'session', 'provider' => 'admin_users']);
        $request = $this->request();
        $request->session()->start();
        $before = $request->session()->getId();
        $token = $request->session()->token();
        $this->app['session.store']->put('url.intended', url('/where-i-was'));

        $response = $this->signIn()->finish($request, $admin, 'backstage', 'password', true);

        $this->assertSame(url('/where-i-was'), $response->headers->get('Location'));
        $this->assertSame($admin->id, auth('backstage')->id());
        $this->assertNotSame($before, $request->session()->getId());
        // A new CSRF token too, so a token planted before the login is worthless.
        $this->assertNotSame($token, $request->session()->token());
        $this->assertNotNull(auth('backstage')->getRecallerName());
        $this->assertSame(60, strlen((string) $admin->fresh()->getRememberToken()));
    }

    public function test_with_nowhere_intended_a_guard_nobody_registered_goes_home(): void
    {
        $this->app['config']->set('auth.guards.backstage', ['driver' => 'session', 'provider' => 'admin_users']);

        $admin = $this->admin();
        $token = $admin->getRememberToken();

        $response = $this->signIn()->finish($this->request(), $admin, 'backstage', 'password');

        $this->assertSame(url('/'), $response->headers->get('Location'));
        // Not remembered unless asked: no new token.
        $this->assertSame($token, $admin->fresh()->getRememberToken());
    }

    public function test_the_login_screen_gets_the_buttons_of_the_modules_in_order_without_their_keys(): void
    {
        $this->assertSame([], $this->signIn()->methods('admin'));

        Hook::filter('atrium.auth.methods', fn (array $methods, string $guard): array => $guard === 'admin'
            ? [...$methods, 'github' => ['key' => 'social-github', 'label' => 'GitHub', 'url' => '/go/github', 'kind' => 'link']]
            : $methods);

        $button = ['key' => 'social-github', 'label' => 'GitHub', 'url' => '/go/github', 'kind' => 'link'];
        $this->assertSame([$button], $this->signIn()->methods('admin'));
        $this->assertSame([], $this->signIn()->methods('account'));

        $this->get('/admin/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Auth/Login')
            ->where('methods', [$button])
            ->where('branding', config('atrium.branding')));
    }

    public function test_the_login_form_and_the_login_are_open_to_the_router(): void
    {
        $this->assertInstanceOf(InertiaResponse::class, $this->app->make(LoginController::class)->showLoginForm());
        $this->admin();
        $request = LoginRequest::create('/admin/login', 'POST', ['email' => 'admin@example.com', 'password' => 'secret-password']);
        $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
        $request->setLaravelSession($this->app->make('session.store'));
        $request->validateResolved();

        $this->assertSame(302, $this->app->make(LoginController::class)->login($request)->getStatusCode());
        $this->assertAuthenticated('admin');
    }

    public function test_an_administrator_s_login_regenerates_the_session(): void
    {
        $this->admin();
        $this->get('/admin/login');
        $before = session()->getId();
        $token = session()->token();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'secret-password'])->assertRedirect('/admin');

        $this->assertNotSame($before, session()->getId());
        $this->assertNotSame($token, session()->token());
    }
}
