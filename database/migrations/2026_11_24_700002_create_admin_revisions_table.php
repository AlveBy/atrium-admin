<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versions of records: what a record's form held after a save, to compare
 * two and put an old one back. Who saved it and when is its entry in the
 * activity log; a version without one is the record as it was before its
 * history began.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->nullable()->index();
            // The module's key: "news".
            $table->string('module', 100);
            $table->unsignedBigInteger('record_id');
            // Text, not JSON: a JSON column of MySQL sorts the keys, and the
            // fields would no longer come in the order of the form.
            $table->longText('values');
            $table->timestamp('created_at')->nullable();

            $table->index(['module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_revisions');
    }
};
