<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A format that is not a single word is not a format: a list in the query
 * string is a refusal, as any other format that does not exist is.
 */
final class ExportControllerFormatListTest extends TransferTestCase
{
    /**
     * @return array<string,array{string}>
     */
    public static function lists(): array
    {
        return [
            'a list of one known format' => ['format[]=csv'],
            'a list of two' => ['format[]=csv&format[]=xlsx'],
            'a keyed list' => ['format[csv]=xlsx'],
        ];
    }

    #[DataProvider('lists')]
    public function test_a_list_of_formats_is_refused(string $query): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/parcels/export?'.$query)->assertStatus(422);
    }

    public function test_a_single_known_format_is_still_taken(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin/parcels/export?format=csv')->assertOk();
    }
}
