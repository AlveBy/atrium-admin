<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Captcha\CaptchaManager;
use Atrium\Admin\Captcha\ImageCaptcha;
use Atrium\Admin\Tests\TestCase;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The built-in captcha in exact form: the numbers it draws, the markup it
 * hands out, how long a question lives and what its picture is made of. The
 * numbers come from a closure the test controls, so every call, its limits
 * and the pixels they lead to are the same on every run.
 */
final class ImageCaptchaExactTest extends TestCase
{
    /** @var list<array{int,int}> */
    private array $drawn = [];

    /** @var list<mixed> */
    private array $decrypted = [];

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    /**
     * Every call is noted. 'low' answers with the lower limit, 'high' with
     * the upper one, and an angle stays 0 in both: a turned picture is
     * resampled, and the resampling differs between GD builds.
     *
     * @return Closure(int,int):int
     */
    private function numbers(string $answer): Closure
    {
        return function (int $min, int $max) use ($answer): int {
            $this->drawn[] = [$min, $max];

            return $min < 0 ? 0 : ($answer === 'high' ? $max : $min);
        };
    }

    private function captcha(string $answer = 'low', bool $gd = true): ImageCaptcha
    {
        $encrypter = new class($this->decrypted) implements Encrypter
        {
            /** @param  list<mixed>  $decrypted */
            public function __construct(private array &$decrypted) {}

            public function encrypt($value, $serialize = true): string
            {
                return 'sealed:'.json_encode($value);
            }

            public function decrypt($payload, $unserialize = true): mixed
            {
                $this->decrypted[] = $payload;

                if (! is_string($payload) || ! str_starts_with($payload, 'sealed:')) {
                    throw new DecryptException('not ours');
                }

                return json_decode(substr($payload, 7), true);
            }

            public function getKey(): string
            {
                return 'key';
            }

            public function getAllKeys(): array
            {
                return ['key'];
            }

            public function getPreviousKeys(): array
            {
                return [];
            }
        };

        return new ImageCaptcha($encrypter, $this->app['cache']->store('array'), $gd, $this->numbers($answer));
    }

    /**
     * @return list<int>
     */
    private function pixels(string $png): array
    {
        $image = imagecreatefromstring($png);
        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertSame([150, 50], [imagesx($image), imagesy($image)]);

        $pixels = [];

        for ($y = 0; $y < 50; $y++) {
            for ($x = 0; $x < 150; $x++) {
                $pixels[] = imagecolorat($image, $x, $y) & 0xFFFFFF;
            }
        }

        return $pixels;
    }

    private function question(string $q = '3 + 4', int $a = 7, int $in = 60): string
    {
        return 'sealed:'.json_encode(['q' => $q, 'a' => $a, 'n' => 'nonce', 'e' => now()->addSeconds($in)->getTimestamp()]);
    }

    private function request(mixed $token, mixed $answer): Request
    {
        return Request::create('/form', 'POST', [ImageCaptcha::TOKEN => $token, CaptchaManager::FIELD => $answer]);
    }

    /**
     * @return array{list<int>,list<array{int,int}>}
     */
    private function picture(string $answer, string $q): array
    {
        $this->drawn = [];
        $png = $this->captcha($answer)->image($this->question($q));
        $this->assertIsString($png);

        return [$this->pixels($png), $this->drawn];
    }

    /**
     * @param  list<array{int,int}>  $calls
     * @return array<string,int>
     */
    private function counted(array $calls): array
    {
        $counts = array_count_values(array_map(static fn (array $call): string => $call[0].','.$call[1], $calls));
        ksort($counts);

        return $counts;
    }

    // ---- the widget ------------------------------------------------------------------------------

    /**
     * @return array<string,array{string,bool,int,int}>
     */
    public static function widgets(): array
    {
        return [
            'a picture of the lowest sum' => ['low', true, 1, 1],
            'a picture of the highest sum' => ['high', true, 9, 9],
            'text of the lowest sum' => ['low', false, 1, 1],
            'text of the highest sum' => ['high', false, 9, 9],
        ];
    }

    #[DataProvider('widgets')]
    public function test_the_widget_is_exactly_the_question_the_token_and_the_answer_field(string $answer, bool $gd, int $a, int $b): void
    {
        $this->travelTo('2026-03-04 10:00:00');
        $lengths = [];
        Str::createRandomStringsUsing(function (int $length) use (&$lengths): string {
            $lengths[] = $length;

            return str_repeat((string) count($lengths), $length);
        });

        $html = (string) $this->captcha($answer, $gd)->widget();

        $token = 'sealed:'.json_encode(['q' => "$a + $b", 'a' => $a + $b, 'n' => str_repeat('1', 20), 'e' => now()->addMinutes(10)->getTimestamp()]);
        $question = $gd
            ? '<img src="'.e(route('atrium.captcha.image', ['t' => $token])).'" width="150" height="50" alt="'.e(__('atrium::captcha.image')).'">'
            : "<span>$a + $b = ?</span>";

        $this->assertSame(
            '<span class="atrium-captcha-question">'.$question.'</span>'
            .'<input type="hidden" name="captcha_token" value="'.e($token).'">'
            .'<label for="atrium-captcha-222222">'.e(__('atrium::captcha.answer')).'</label>'
            .'<input type="text" id="atrium-captcha-222222" name="captcha" inputmode="numeric" autocomplete="off" required size="4">',
            $html,
        );
        $this->assertSame([20, 6], $lengths);
        $this->assertSame([[1, 9], [1, 9]], $this->drawn);
    }

    public function test_a_question_lives_for_the_configured_minutes(): void
    {
        $this->travelTo('2026-03-04 10:00:00');
        $expires = static fn (string $html): int => json_decode(substr(html_entity_decode(preg_match('/name="captcha_token" value="([^"]+)"/', $html, $m) === 1 ? $m[1] : ''), 7), true)['e'];

        // Nothing configured: ten minutes.
        config(['atrium.captcha' => []]);
        $this->assertSame(now()->addMinutes(10)->getTimestamp(), $expires((string) $this->captcha()->widget()));

        foreach ([0 => 1, 1 => 1, 2 => 2, 3 => 3, 25 => 25] as $configured => $minutes) {
            config(['atrium.captcha.image.ttl' => $configured]);

            $this->assertSame(now()->addMinutes($minutes)->getTimestamp(), $expires((string) $this->captcha()->widget()), "ttl {$configured}");
        }
    }

    // ---- the answer ------------------------------------------------------------------------------

    public function test_a_right_answer_is_taken_once_and_remembered_for_the_lifetime_of_the_question(): void
    {
        $this->travelTo('2026-03-04 10:00:00');
        config(['atrium.captcha.image.ttl' => 5]);
        $captcha = $this->captcha();
        $token = $this->question();

        $this->assertTrue($captcha->verify($this->request($token, '7')));
        $this->assertTrue($this->app['cache']->store('array')->get('atrium-captcha:nonce'), 'what is kept is a plain true under the nonce');

        $this->assertFalse($captcha->verify($this->request($token, '7')), 'used up');

        $this->travel(4)->minutes();
        $this->assertFalse($captcha->verify($this->request($token, '7')), 'still remembered a minute before the lifetime ends');

        $this->travel(2)->minutes();
        $this->assertNull($this->app['cache']->store('array')->get('atrium-captcha:nonce'), 'forgotten after the configured minutes');
    }

    public function test_a_wrong_answer_is_refused_and_uses_the_question_up(): void
    {
        $captcha = $this->captcha();
        $token = $this->question();

        $this->assertFalse($captcha->verify($this->request($token, '8')));
        $this->assertFalse($captcha->verify($this->request($token, '7')));
    }

    public function test_the_answer_is_trimmed_and_compared_as_text(): void
    {
        $captcha = $this->captcha();

        $this->assertTrue($captcha->verify($this->request($this->question(), " 7 \n")));
        $this->assertFalse($captcha->verify($this->request($this->question(a: 7), '07')));
        $this->assertFalse($captcha->verify($this->request($this->question(a: 7), '7.0')));
        $this->assertFalse($captcha->verify($this->request($this->question(a: 7), '')));
    }

    public function test_an_answer_that_is_not_text_is_refused_without_using_the_question_up(): void
    {
        $captcha = $this->captcha();
        $token = $this->question();

        $this->assertFalse($captcha->verify($this->request($token, ['7'])));
        $this->assertFalse($captcha->verify($this->request($token, null)));
        $this->assertTrue($captcha->verify($this->request($token, '7')), 'the question was still there');
    }

    /**
     * @return array<string,array{mixed}>
     */
    public static function badTokens(): array
    {
        return [
            'none' => [null],
            'empty' => [''],
            'a list' => [['sealed:{}']],
            'a number' => [7],
            'not ours' => ['forged'],
            'not an array' => ['sealed:"text"'],
            'no sum' => ['sealed:{"a":7,"n":"nonce","e":9999999999}'],
            'no answer' => ['sealed:{"q":"3 + 4","n":"nonce","e":9999999999}'],
            'an empty answer' => ['sealed:{"q":"3 + 4","a":null,"n":"nonce","e":9999999999}'],
            'no nonce' => ['sealed:{"q":"3 + 4","a":7,"e":9999999999}'],
            'no expiry' => ['sealed:{"q":"3 + 4","a":7,"n":"nonce"}'],
            'expired a second ago' => ['expired'],
        ];
    }

    #[DataProvider('badTokens')]
    public function test_a_token_that_is_not_a_live_question_is_refused_and_draws_nothing(mixed $token): void
    {
        $this->travelTo('2026-03-04 10:00:00');
        $token = $token === 'expired' ? $this->question(in: -1) : $token;
        $captcha = $this->captcha();

        $this->assertFalse($captcha->verify($this->request($token, '7')));
        $this->assertNull($captcha->image($token));
        $this->assertNull($this->app['cache']->store('array')->get('atrium-captcha:nonce'));
    }

    public function test_a_token_that_is_not_text_never_reaches_the_decryption(): void
    {
        $captcha = $this->captcha();

        foreach ([null, '', ['sealed:{}'], 7] as $token) {
            $this->assertFalse($captcha->verify($this->request($token, '7')));
            $this->assertNull($captcha->image($token));
        }

        $this->assertSame([], $this->decrypted);

        $captcha->image('forged');
        $this->assertSame(['forged'], $this->decrypted);
    }

    public function test_a_question_is_live_until_the_second_it_expires(): void
    {
        $this->travelTo('2026-03-04 10:00:00');
        $captcha = $this->captcha();

        $this->assertTrue($captcha->verify($this->request($this->question(in: 0), '7')), 'expires now: still good');
        $this->assertFalse($captcha->verify($this->request($this->question(in: -1), '7')));
    }

    // ---- the picture ------------------------------------------------------------------------------

    public function test_without_gd_there_is_no_picture(): void
    {
        $this->assertNull($this->captcha('low', false)->image($this->question()));
    }

    public function test_the_picture_is_the_sum_drawn_with_the_lowest_numbers(): void
    {
        [$pixels, $calls] = $this->picture('low', '3 + 4');

        // Noise lands on the corner, so the rest of the picture is the plain background and the five signs.
        $this->assertSame(0x3C3C3C, $pixels[0], 'the last noise dot, on the corner');
        $this->assertSame(0xE1E1E1, $pixels[149], 'the background');
        $this->assertSame(0xE1E1E1, $pixels[49 * 150 + 149], 'the background');
        $this->assertSame('4fa27fa9080c0c5a29b93ee66f0fc17e50cbbab9', sha1(implode(',', $pixels)));
        $this->assertSame([
            '-20,20' => 5, '0,149' => 250, '0,150' => 12, '0,49' => 250, '0,50' => 12, '1,2' => 6, '1,4' => 5,
            '120,190' => 18, '2,24' => 5, '20,90' => 15, '225,250' => 3, '24,30' => 5, '60,200' => 750,
        ], $this->counted($calls));
    }

    public function test_the_picture_is_the_sum_drawn_with_the_highest_numbers(): void
    {
        [$pixels, $calls] = $this->picture('high', '3 + 4');

        $this->assertSame(0xC8C8C8, $pixels[49 * 150 + 149], 'a noise dot on the far corner');
        $this->assertSame(0xFAFAFA, $pixels[0], 'the background');
        $this->assertSame('b2f8d0c0c4b65c7674ad20e277d627e9d4b05f64', sha1(implode(',', $pixels)));
        $this->assertSame([
            '-20,20' => 5, '0,149' => 250, '0,150' => 12, '0,49' => 250, '0,50' => 12, '1,2' => 6, '1,4' => 5,
            '120,190' => 18, '2,18' => 5, '20,90' => 15, '225,250' => 3, '24,30' => 5, '60,200' => 750,
        ], $this->counted($calls));
    }

    public function test_a_longer_sum_draws_a_sign_more_for_every_character_but_the_spaces(): void
    {
        [, $calls] = $this->picture('low', '3 + 4');
        [, $more] = $this->picture('low', '33 + 44');

        $this->assertSame(5, $this->counted($calls)['-20,20']);
        $this->assertSame(7, $this->counted($more)['-20,20']);
    }
}
