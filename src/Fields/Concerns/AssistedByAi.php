<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields\Concerns;

/**
 * A "Generate" button next to a text field, drawn when the AI module
 * (alveby/atrium-ai) is installed and has a provider with a key. Without it
 * the option is carried along and nothing is shown.
 *
 * @example
 * TextareaField::make('preview_text')->ai('preview', from: 'detail_text')
 * TextField::make('picture_alt')->ai('alt', from: 'detail_picture')
 */
trait AssistedByAi
{
    /** @var array{task:string,from:array<int,string>}|null */
    protected ?array $ai = null;

    /**
     * @param  string  $task  describe, preview, seo_title, seo_description, seo_keywords, alt or translate
     * @param  string|array<int,string>|null  $from  the fields the text is made from; empty = the record's text fields
     */
    public function ai(string $task = 'describe', string|array|null $from = null): static
    {
        $this->ai = ['task' => $task, 'from' => array_values((array) $from)];

        return $this;
    }

    /**
     * @return array{task:string,from:array<int,string>}|null
     */
    public function getAi(): ?array
    {
        return $this->ai;
    }
}
