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
 * Scheduled publishing: when a record switches itself on or off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_schedules', function (Blueprint $table): void {
            $table->id();
            // The module's key: "news".
            $table->string('module', 100);
            $table->unsignedBigInteger('record_id');
            $table->foreignId('site_id')->nullable()->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('unpublish_at')->nullable()->index();
            $table->foreignId('admin_user_id')->nullable();
            $table->timestamps();

            $table->unique(['module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_schedules');
    }
};
