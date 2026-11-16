<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_sites', function (Blueprint $table) {
            // The path the site sits under on its domains: "site2" for
            // example.com/site2/. Empty — the site is at the root.
            $table->string('folder')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admin_sites', function (Blueprint $table) {
            $table->dropColumn('folder');
        });
    }
};
