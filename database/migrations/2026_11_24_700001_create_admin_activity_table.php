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
 * The activity log: who created, changed, deleted or restored which record
 * of which module, when, from where, and which fields went from what to
 * what. The record's title and the administrator's name are kept as they
 * were, so the entry still reads after either is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity', function (Blueprint $table): void {
            $table->id();
            // The record's site; null for the modules all sites share.
            $table->foreignId('site_id')->nullable()->index();
            // The module's key: "news".
            $table->string('module', 100);
            $table->unsignedBigInteger('record_id');
            $table->string('record_title')->nullable();
            $table->string('event', 16);
            // Text, not JSON: a JSON column of MySQL sorts the keys, and the
            // fields would no longer come in the order they were changed.
            $table->longText('changes')->nullable();
            $table->foreignId('admin_user_id')->nullable()->index();
            $table->string('admin_name')->nullable();
            // admin, api, import, console, schedule
            $table->string('source', 16);
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity');
    }
};
