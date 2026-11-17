<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Captcha\CaptchaDriver;
use Atrium\Admin\Captcha\CaptchaManager;
use Atrium\Admin\Captcha\ImageCaptcha;
use Atrium\Admin\Captcha\NoCaptcha;
use Atrium\Admin\Captcha\RecaptchaCaptcha;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;

final class CaptchaTest extends TestCase
{
    use PinsExtensionSurface;

    public function test_off_by_default_the_forms_draw_and_ask_nothing(): void
    {
        $captcha = $this->captcha();

        $this->assertFalse($captcha->enabled());
        $this->assertSame([], $captcha->rules());
        $this->assertSame('', trim(Blade::render('<x-atrium::captcha />')));
    }

    public function test_the_switched_off_driver_draws_nothing_and_lets_every_request_through(): void
    {
        $driver = new NoCaptcha;

        $this->assertSame('', $driver->widget()->toHtml());
        $this->assertTrue($driver->verify(Request::create('/form', 'POST')));
        $this->assertTrue($driver->verify(Request::create('/form', 'POST', ['captcha' => 'wrong', 'captcha_token' => 'forged'])));
    }

    public function test_the_image_captcha_asks_a_sum_drawn_as_a_picture(): void
    {
        $this->captcha('image');
        $html = Blade::render('<x-atrium::captcha class="mine" />');

        $this->assertStringContainsString('class="atrium-captcha mine"', $html);
        $this->assertStringContainsString('name="captcha_token"', $html);
        $this->assertStringContainsString('name="captcha"', $html);
        $this->assertStringContainsString('alt="Check: the sum to work out"', $html);

        $question = Crypt::decrypt($this->token($html));
        $this->assertMatchesRegularExpression('/^\d \+ \d$/', $question['q']);
        $this->assertStringNotContainsString($question['q'], $html, 'the sum is not in the page as text');

        preg_match('/<img src="([^"]+)"/', $html, $image);
        $response = $this->get(html_entity_decode($image[1]));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", (string) $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->get(route('atrium.captcha.image', ['t' => 'forged']))->assertNotFound();
        $this->get(route('atrium.captcha.image'))->assertNotFound();
    }

    public function test_an_image_question_is_answered_once_and_expires(): void
    {
        $captcha = $this->captcha('image');

        [$token, $answer] = $this->question($captcha);
        $this->assertTrue($captcha->verify($this->request($token, ' '.$answer.' ')));
        $this->assertFalse($captcha->verify($this->request($token, $answer)), 'used up');

        [$token, $answer] = $this->question($captcha);
        $this->assertFalse($captcha->verify($this->request($token, (string) ($answer + 1))));
        $this->assertFalse($captcha->verify($this->request($token, $answer)), 'a wrong try uses it up too');

        [$token] = $this->question($captcha);
        $this->assertFalse($captcha->verify($this->request($token, null)));
        $this->assertFalse($captcha->verify($this->request('forged', '5')));
        $this->assertFalse($captcha->verify(Request::create('/', 'POST')));

        [$token, $answer] = $this->question($captcha);
        $this->travel(11)->minutes();
        $this->assertFalse($captcha->verify($this->request($token, $answer)), 'expired');
    }

    public function test_without_gd_the_sum_is_written_as_text_and_no_picture_is_drawn(): void
    {
        $captcha = new ImageCaptcha(app(Encrypter::class), app(Cache::class), false);

        $html = (string) $captcha->widget();
        $token = $this->token($html);
        $question = Crypt::decrypt($token);

        $this->assertStringContainsString('<span>'.$question['q'].' = ?</span>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertNull($captcha->image($token));
        $this->assertTrue($captcha->verify($this->request($token, (string) $question['a'])), 'the answer still counts');
    }

    public function test_with_gd_the_sum_is_a_picture_and_not_text(): void
    {
        $captcha = new ImageCaptcha(app(Encrypter::class), app(Cache::class), true);

        $html = (string) $captcha->widget();
        $token = $this->token($html);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringNotContainsString('<span>'.Crypt::decrypt($token)['q'], $html);
        $this->assertStringStartsWith("\x89PNG", (string) $captcha->image($token));
        $this->assertNull($captcha->image('forged'));
    }

    public function test_the_rule_puts_the_error_on_the_captcha_field(): void
    {
        $captcha = $this->captcha('image');
        [$token, $answer] = $this->question($captcha);

        $this->app->instance('request', $this->request($token, (string) ($answer + 1)));
        $validator = Validator::make([], $captcha->rules());

        $this->assertTrue($validator->fails());
        $this->assertSame('The answer to the check is wrong. Try the new one.', $validator->errors()->first('captcha'));

        [$token, $answer] = $this->question($captcha);
        $this->app->instance('request', $this->request($token, $answer));
        $this->assertTrue(Validator::make([], $captcha->rules())->passes());
    }

    public function test_recaptcha_asks_google(): void
    {
        config(['atrium.captcha.recaptcha' => ['site_key' => 'site-key', 'secret_key' => 'secret-key']]);
        $captcha = $this->captcha('recaptcha');
        app()->setLocale('pt_BR');

        $this->assertInstanceOf(RecaptchaCaptcha::class, $captcha->driver());
        $widget = (string) $captcha->widget();
        $this->assertStringContainsString('https://www.google.com/recaptcha/api.js?hl=pt-BR', $widget);
        $this->assertStringContainsString('class="g-recaptcha" data-sitekey="site-key"', $widget);

        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::sequence()->push(['success' => true])->push(['success' => false])->pushFailedConnection()]);
        $request = Request::create('/', 'POST', ['g-recaptcha-response' => 'answer'], server: ['REMOTE_ADDR' => '10.0.0.7']);

        $this->assertTrue($captcha->verify($request));
        $this->assertFalse($captcha->verify($request));
        Http::assertSent(static fn (HttpRequest $sent): bool => $sent['secret'] === 'secret-key' && $sent['response'] === 'answer' && $sent['remoteip'] === '10.0.0.7');

        $this->assertFalse($captcha->verify($request), 'Google not answering is not a pass');
        $this->assertFalse($captcha->verify(Request::create('/', 'POST')));
    }

    public function test_recaptcha_without_keys_falls_back_to_the_image_one(): void
    {
        $this->assertInstanceOf(ImageCaptcha::class, $this->captcha('recaptcha')->driver());
    }

    public function test_a_captcha_of_its_own_comes_with_extend(): void
    {
        $captcha = $this->captcha('mine');
        $captcha->extend('mine', static fn (): CaptchaDriver => new class implements CaptchaDriver
        {
            public function widget(): HtmlString
            {
                return new HtmlString('<input name="captcha" value="">');
            }

            public function verify(Request $request): bool
            {
                return $request->input('captcha') === 'blue';
            }
        });

        $this->assertTrue($captcha->enabled());
        $this->assertTrue($captcha->verify(Request::create('/', 'POST', ['captcha' => 'blue'])));
        $this->assertStringContainsString('name="captcha"', Blade::render('<x-atrium::captcha />'));
    }

    private function captcha(?string $driver = null): CaptchaManager
    {
        config(['atrium.captcha.driver' => $driver]);
        $captcha = app(CaptchaManager::class);
        $captcha->forgetDrivers();

        return $captcha;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function question(CaptchaManager $captcha): array
    {
        $token = $this->token((string) $captcha->widget());

        return [$token, (string) Crypt::decrypt($token)['a']];
    }

    private function token(string $html): string
    {
        $this->assertSame(1, preg_match('/name="captcha_token" value="([^"]+)"/', $html, $match));

        return html_entity_decode($match[1]);
    }

    private function request(string $token, ?string $answer): Request
    {
        return Request::create('/', 'POST', array_filter(['captcha_token' => $token, 'captcha' => $answer], static fn ($value) => $value !== null));
    }

    public function test_the_recaptcha_widget_is_exactly_the_script_and_the_div(): void
    {
        app()->setLocale('pt_BR');

        $this->assertSame(
            '<script src="https://www.google.com/recaptcha/api.js?hl=pt-BR" async defer></script><div class="g-recaptcha" data-sitekey="a&quot;b"></div>',
            (new RecaptchaCaptcha('a"b', 'secret'))->widget()->toHtml(),
        );
    }

    public function test_recaptcha_waits_five_seconds_for_google_and_no_more(): void
    {
        $options = null;
        Http::fake(function (HttpRequest $request, array $given) use (&$options) {
            $options = $given;

            return Http::response(['success' => true]);
        });

        $this->assertTrue((new RecaptchaCaptcha('site', 'secret'))->verify(Request::create('/', 'POST', ['g-recaptcha-response' => 'answer'])));
        $this->assertSame(5, (int) $options['timeout']);
    }

    public function test_recaptcha_needs_both_keys_and_unset_keys_count_as_empty(): void
    {
        config(['atrium.captcha.recaptcha' => ['site_key' => 'site-key', 'secret_key' => '']]);
        $this->assertInstanceOf(ImageCaptcha::class, $this->captcha('recaptcha')->driver());

        config(['atrium.captcha.recaptcha' => ['site_key' => '', 'secret_key' => 'secret-key']]);
        $this->assertInstanceOf(ImageCaptcha::class, $this->captcha('recaptcha')->driver());

        config(['atrium.captcha.recaptcha' => ['secret_key' => 'secret-key']]);
        $this->assertInstanceOf(ImageCaptcha::class, $this->captcha('recaptcha')->driver());

        config(['atrium.captcha.recaptcha' => ['site_key' => 'site-key']]);
        $this->assertInstanceOf(ImageCaptcha::class, $this->captcha('recaptcha')->driver());

        config(['atrium.captcha.recaptcha' => ['site_key' => 'site-key', 'secret_key' => 'secret-key']]);
        $this->assertInstanceOf(RecaptchaCaptcha::class, $this->captcha('recaptcha')->driver());
    }
}
