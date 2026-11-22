<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Transfer\Target;
use Atrium\Admin\Transfer\Targets;

/**
 * What comes first wins: a key named in full beats the same key in the
 * default language, the first target to claim a header keeps it, and a
 * target that answers to more headers keeps the ones it had.
 */
final class TargetsKeyAndSynonymOrderTest extends TransferTestCase
{
    public function test_a_key_that_is_a_target_itself_is_not_read_as_the_one_in_the_default_language(): void
    {
        Language::query()->create(['code' => 'ru', 'name' => 'Русский', 'active' => true, 'is_default' => false, 'sort' => 2]);
        app(ContentLanguages::class)->flush();
        Hook::filter('atrium.import.targets', static function (array $targets): array {
            $targets['name'] = new Target('name', 'Own name');

            return $targets;
        });

        $targets = Targets::for(Parcel::class);

        $this->assertNotNull($targets->get('name.en'));
        $this->assertSame('Own name', $targets->keyTarget('name')?->label);
        $this->assertSame('name', $targets->keyTarget('name')->key);
        $this->assertSame('name.en', $targets->keyTarget('name.en')?->key);
    }

    public function test_the_key_in_the_default_language_is_found_when_the_key_itself_is_not_a_target(): void
    {
        Language::query()->create(['code' => 'ru', 'name' => 'Русский', 'active' => true, 'is_default' => false, 'sort' => 2]);
        app(ContentLanguages::class)->flush();

        $this->assertSame('name.en', Targets::for(Parcel::class)->keyTarget('name')?->key);
    }

    public function test_the_first_target_to_name_a_header_keeps_it(): void
    {
        Hook::filter('atrium.import.targets', static function (array $targets): array {
            $targets['first'] = new Target('first', 'First', synonyms: ['dup', 'onlyfirst']);
            $targets['second'] = new Target('second', 'Second', synonyms: ['dup', 'onlysecond']);

            return $targets;
        });

        $this->assertSame(['first', 'second'], Targets::for(Parcel::class)->guess(['dup', 'onlysecond']));
        $this->assertSame(['second', 'first'], Targets::for(Parcel::class)->guess(['onlysecond', 'onlyfirst']));
        $this->assertSame(['first', null], Targets::for(Parcel::class)->guess(['dup', 'dup']));
    }

    public function test_a_target_that_answers_to_more_headers_keeps_every_one_it_had(): void
    {
        $target = new Target('k', 'K', synonyms: ['one', 'two', 'three']);

        $more = $target->withSynonyms(['four', 'five']);

        $this->assertSame(['one', 'two', 'three', 'four', 'five'], $more->synonyms);
        $this->assertSame(['one', 'two', 'three'], $target->synonyms);
        $this->assertSame(['one', 'two', 'three'], (new Target('k', 'K', synonyms: ['one', 'two', 'three']))->withSynonyms([])->synonyms);
        $this->assertSame(['five'], (new Target('k', 'K'))->withSynonyms(['five'])->synonyms);
    }
}
