<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drafts of the page editor, kept with the versions: the field a row is an
 * unpublished draft of (null for a version), who saved it, and the
 * fingerprint of the published value it was started from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_revisions', function (Blueprint $table): void {
            $table->string('draft', 64)->nullable();
            $table->string('author')->nullable();
            $table->string('base', 64)->nullable();
        });
    }

    public function down(): void
    {
        // A draft without its mark would pass for a version.
        DB::table('admin_revisions')->whereNotNull('draft')->delete();

        Schema::table('admin_revisions', function (Blueprint $table): void {
            $table->dropColumn(['draft', 'author', 'base']);
        });
    }
};
