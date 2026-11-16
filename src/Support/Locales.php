<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Interface languages of the panel: which ones exist, which one a request
 * gets, and the dictionary the Vue side reads through t().
 */
final class Locales
{
    public const SESSION_KEY = 'atrium.locale';

    /** Language every other one falls back to for a missing key. */
    public const FALLBACK = 'en';

    /**
     * @return array<string, string> code => name of the language in itself
     */
    public function available(): array
    {
        return (array) config('atrium.locales', [self::FALLBACK => 'English']);
    }

    public function isAvailable(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, $this->available());
    }

    /**
     * The admin's own choice, then the one picked on the sign-in page, then
     * the browser's, then the configured default.
     */
    public function resolve(Request $request, ?AdminUser $user): string
    {
        foreach ([$user?->locale, $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null] as $candidate) {
            if ($this->isAvailable($candidate)) {
                return $candidate;
            }
        }

        $codes = array_keys($this->available());
        $preferred = $request->getPreferredLanguage($codes);

        // Symfony falls back to the first listed code when nothing matches;
        // only trust it when the browser actually asked for that language.
        if ($preferred !== null && $this->browserAsked($request, $preferred)) {
            return $preferred;
        }

        $default = (string) config('atrium.locale', self::FALLBACK);

        return $this->isAvailable($default) ? $default : self::FALLBACK;
    }

    /**
     * Every package lang file for $locale, missing keys filled from English,
     * flattened to dot keys: ['common.save' => 'Save', ...].
     *
     * @return array<string, string>
     */
    public function dictionary(string $locale): array
    {
        $dictionary = [];

        foreach (array_diff($this->groups(), ['validation']) as $group) {
            $fallback = (array) trans("atrium::{$group}", [], self::FALLBACK);
            $own = (array) trans("atrium::{$group}", [], $locale);

            $dictionary[$group] = array_replace_recursive($fallback, $own);
        }

        /** @var array<string, string> */
        return Arr::dot($dictionary);
    }

    /**
     * Laravel ships validation messages in English only. Lines the application
     * has not translated itself are filled from the package, so an admin
     * working in Polish does not get "The name field is required.".
     */
    public function fillValidationGaps(string $locale): void
    {
        $translator = app('translator');
        $missing = [];

        foreach (Arr::dot((array) trans('atrium::validation', [], $locale)) as $key => $line) {
            if (! $translator->hasForLocale("validation.{$key}", $locale)) {
                $missing["validation.{$key}"] = $line;
            }
        }

        if ($missing !== []) {
            $translator->addLines($missing, $locale);
        }
    }

    /**
     * Lang file names, taken from the English set — it is the complete one.
     *
     * @return list<string>
     */
    public function groups(): array
    {
        $files = glob(dirname(__DIR__, 2).'/lang/'.self::FALLBACK.'/*.php') ?: [];

        return array_map(static fn (string $file): string => basename($file, '.php'), $files);
    }

    private function browserAsked(Request $request, string $locale): bool
    {
        $wanted = strtolower(str_replace('_', '-', $locale));
        $language = strtok($wanted, '-');

        foreach ($request->getLanguages() as $asked) {
            $asked = strtolower(str_replace('_', '-', $asked));

            if ($asked === $wanted || $asked === $language || strtok($asked, '-') === $language) {
                return true;
            }
        }

        return false;
    }
}
