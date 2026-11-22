<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Illuminate\Support\Facades\Schema;

/**
 * The scheduler runs `atrium:import --pending` every minute, also on a site
 * whose migrations have not run yet: it must not fail there.
 */
final class ImportPendingWithoutTableTest extends TransferTestCase
{
    public function test_a_site_that_has_not_migrated_has_nothing_pending_and_no_error(): void
    {
        Schema::drop('admin_import_keys');
        Schema::drop('admin_import_media');
        Schema::drop('admin_imports');

        $this->assertFalse(Schema::hasTable('admin_imports'));
        $this->artisan('atrium:import', ['--pending' => true])->assertExitCode(0);
    }

    public function test_a_site_that_has_migrated_looks_at_the_table(): void
    {
        $this->assertTrue(Schema::hasTable('admin_imports'));
        $this->artisan('atrium:import', ['--pending' => true])->assertExitCode(0);
    }
}
