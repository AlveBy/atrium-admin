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

    /**
     * @param  bool|null  $gd  Whether the picture can be drawn; null asks PHP whether GD is there.
     */
    public function __construct(private readonly Encrypter $encrypter, private readonly Cache $cache, private readonly ?bool $gd = null) {}

    public function widget(): HtmlString
    {
        // A question is used up on the first answer: a kept page would give
        // every visitor the same one.
        app(RecordCache::class)->dontKeepPage();
        [$a, $b] = [random_int(1, 9), random_int(1, 9)];
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
        imagefill($image, 0, 0, $this->color($image, 225, 250));

        for ($i = 0; $i < 6; $i++) {
            imagesetthickness($image, random_int(1, 2));
            imageline($image, random_int(0, self::WIDTH), random_int(0, self::HEIGHT), random_int(0, self::WIDTH), random_int(0, self::HEIGHT), $this->color($image, 120, 190));
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
            $background = imagecolorallocate($glyph, 255, 255, 255);
            imagefill($glyph, 0, 0, $background);
            imagecolortransparent($glyph, $background);
            imagestring($glyph, 5, 0, 0, $char, $this->color($glyph, 20, 90));

            $glyph = imagerotate($glyph, random_int(-20, 20), $background);
            imagecolortransparent($glyph, $background);
            $size = random_int(24, 30);
            imagecopyresized($image, $glyph, $x, random_int(2, self::HEIGHT - $size - 2), 0, 0, (int) ($size * .6), $size, imagesx($glyph), imagesy($glyph));
            $x += (int) ($size * .6) + random_int(1, 4);
        }

        for ($i = 0; $i < 250; $i++) {
            imagesetpixel($image, random_int(0, self::WIDTH - 1), random_int(0, self::HEIGHT - 1), $this->color($image, 60, 200));
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function color(\GdImage $image, int $from, int $to): int
    {
        return (int) imagecolorallocate($image, random_int($from, $to), random_int($from, $to), random_int($from, $to));
    }

    private function ttl(): int
    {
        return max(1, (int) config('atrium.captcha.image.ttl', 10));
    }
}
