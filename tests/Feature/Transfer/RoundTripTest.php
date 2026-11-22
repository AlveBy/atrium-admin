<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Tests\Fixtures\ApiAisle;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the export writes goes back in as it is.
 */
final class RoundTripTest extends TransferTestCase
{
    /**
     * @return array<string,array{string}>
     */
    public static function formats(): array
    {
        return ['csv' => ['csv'], 'xlsx' => ['xlsx']];
    }

    #[DataProvider('formats')]
    public function test_an_exported_list_imports_back_without_a_change(string $format): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        $top = ApiAisle::query()->create(['name' => 'Europe']);
        $child = ApiAisle::query()->create(['name' => 'Day trips', 'parent_id' => $top->id]);
        $parcel = Parcel::query()->create([
            'article' => 'A-1', 'name' => 'Parka', 'code' => 'parka', 'price' => '1299.50', 'active' => true, 'booking' => 'YES',
            'starts_at' => '2026-03-05 10:30:00', 'starts_on' => '2026-03-05', 'label' => 'hit', 'colors' => ['black', 'silver'],
            'features' => ['Warm', 'Light'], 'aisle_id' => $child->id, 'post_id' => $post->id, 'detail_text' => "Two\nlines; with = signs", 'sort' => 10,
        ]);
        Parcel::query()->create(['article' => 'A-2', 'name' => '=SUM(1+1)', 'active' => false, 'sort' => 500]);

        $before = Parcel::query()->orderBy('id')->get()->toArray();

        $response = $this->actingAs($this->admin(), 'admin')->get("/admin/parcels/export?format={$format}");
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'rt').'.'.$format;
        copy($response->baseResponse->getFile()->getPathname(), $path);

        $import = $this->run_($this->importOf('parcels', $path, ['mode' => 'update']), DataImport::CHECKING);
        $this->assertSame([2, 0, 2, 0], [$import->processed, $import->created, $import->updated, $import->failed], json_encode($import->errors));

        $import = $this->run_($this->importOf('parcels', $path, ['mode' => 'update']), DataImport::IMPORTING);
        $this->assertSame([2, 0, 2, 0], [$import->processed, $import->created, $import->updated, $import->failed], json_encode($import->errors));
        $this->assertSame($before, Parcel::query()->orderBy('id')->get()->toArray());
        $this->assertSame($parcel->id, Parcel::query()->where('article', 'A-1')->value('id'));
    }
}
