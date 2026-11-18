<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Captcha;

use Atrium\Admin\Cache\RecordCache;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * The built-in captcha, with no keys and no outside service: a sum such as
 * "7 + 4" drawn as a noisy picture. The question and its answer travel in
 * the form encrypted with the application key, so nothing is kept between
 * the page and the answer; each question is answered once, right or wrong,
 * and expires (atrium.captcha.image.ttl minutes).
 */
final class ImageCaptcha implements CaptchaDriver
{
    /** The hidden field with the encrypted question. */
    public const TOKEN = 'captcha_token';

    private const WIDTH = 150;

    private const HEIGHT = 50;

    /** The largest of the built-in fonts of GD: 9 by 15 pixels a sign. */
    private const FONT = 5;

    /**
     * @param  bool|null  $gd  Whether the picture can be drawn; null asks PHP whether GD is there.
     * @param  (\Closure(int,int):int)|null  $random  Where the numbers of the sum and of the drawing come from; null is random_int().
     */
    public function __construct(
        private readonly Encrypter $encrypter,
        private readonly Cache $cache,
        private readonly ?bool $gd = null,
        private readonly ?\Closure $random = null,
    ) {}

    public function widget(): HtmlString
    {
        // A question is used up on the first answer: a kept page would give
        // every visitor the same one.
        app(RecordCache::class)->dontKeepPage();
        [$a, $b] = [$this->random(1, 9), $this->random(1, 9)];
        $token = $this->encrypter->encrypt([
            'q' => "$a + $b",
            'a' => $a + $b,
            'n' => Str::random(20),
            'e' => now()->addMinutes($this->ttl())->getTimestamp(),
        ]);

        $id = 'atrium-captcha-'.Str::random(6);
        $question = $this->draws()
            ? '<img src="'.e(route('atrium.captcha.image', ['t' => $token])).'" width="'.self::WIDTH.'" height="'.self::HEIGHT.'" alt="'.e(__('atrium::captcha.image')).'">'
            : '<span>'.e("$a + $b = ?").'</span>';

        return new HtmlString(
            '<span class="atrium-captcha-question">'.$question.'</span>'
            .'<input type="hidden" name="'.self::TOKEN.'" value="'.e($token).'">'
            .'<label for="'.$id.'">'.e(__('atrium::captcha.answer')).'</label>'
            .'<input type="text" id="'.$id.'" name="'.CaptchaManager::FIELD.'" inputmode="numeric" autocomplete="off" required size="4">'
        );
    }

    public function verify(Request $request): bool
    {
        $question = $this->question($request->input(self::TOKEN));
        $answer = $request->input(CaptchaManager::FIELD);

        if ($question === null || ! is_string($answer)) {
            return false;
        }

        // Used up on the first try: only a few sums exist, a bot must not
        // try them all on one question.
        if (! $this->cache->add('atrium-captcha:'.$question['n'], true, now()->addMinutes($this->ttl()))) {
            return false;
        }

        return hash_equals((string) $question['a'], trim($answer));
    }

    /**
     * The picture of a question; null for a token that is not ours or has
     * expired.
     */
    public function image(mixed $token): ?string
    {
        $question = $this->question($token);

        return $question === null || ! $this->draws() ? null : $this->draw($question['q'].' = ?');
    }

    private function draws(): bool
    {
        return $this->gd ?? extension_loaded('gd');
    }

    /**
     * @return array{q:string,a:int,n:string,e:int}|null
     */
    private function question(mixed $token): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $question = $this->encrypter->decrypt($token);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($question) || ! isset($question['q'], $question['a'], $question['n'], $question['e']) || $question['e'] < now()->getTimestamp()) {
            return null;
        }

        return $question;
    }

    private function draw(string $text): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefilter($image, IMG_FILTER_COLORIZE, ...$this->rgb(225, 250));

        for ($i = 0; $i < 6; $i++) {
            imagesetthickness($image, $this->random(1, 2));
            imageline($image, $this->random(0, self::WIDTH), $this->random(0, self::HEIGHT), $this->random(0, self::WIDTH), $this->random(0, self::HEIGHT), $this->color($image, 120, 190));
        }

        // Each sign drawn small with the built-in font, then enlarged, tilted
        // and shifted on its own: no font file to ship.
        $x = 8;

        foreach (str_split($text) as $char) {
            if ($char === ' ') {
                $x += 6;

                continue;
            }

            $glyph = imagecreatetruecolor(9, 15);
            imagefilter($glyph, IMG_FILTER_NEGATE);
            $background = imagecolorallocate($glyph, 255, 255, 255);
            imagestring($glyph, self::FONT, 0, 0, $char, $this->color($glyph, 20, 90));

            $glyph = imagerotate($glyph, $this->random(-20, 20), $background);
            imagecolortransparent($glyph, $background);
            $size = $this->random(24, 30);
            imagecopyresized($image, $glyph, $x, $this->random(2, self::HEIGHT - $size - 2), 0, 0, (int) ($size * .6), $size, imagesx($glyph), imagesy($glyph));
            $x += (int) ($size * .6) + $this->random(1, 4);
        }

        for ($i = 0; $i < 250; $i++) {
            imagesetpixel($image, $this->random(0, self::WIDTH - 1), $this->random(0, self::HEIGHT - 1), $this->color($image, 60, 200));
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function color(\GdImage $image, int $from, int $to): int
    {
        return (int) imagecolorallocate($image, ...$this->rgb($from, $to));
    }

    /**
     * @return array{int,int,int}
     */
    private function rgb(int $from, int $to): array
    {
        return [$this->random($from, $to), $this->random($from, $to), $this->random($from, $to)];
    }

    private function random(int $min, int $max): int
    {
        return ($this->random ?? random_int(...))($min, $max);
    }

    private function ttl(): int
    {
        return max(1, (int) config('atrium.captcha.image.ttl', 10));
    }
}
