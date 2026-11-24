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
 * The trash: records deleted in the panel stay in their own tables, with a
 * row here that hides them everywhere until they are restored or deleted
 * for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_trash', function (Blueprint $table): void {
            $table->id();
            // The module's key: "news".
            $table->string('module', 100);
            $table->unsignedBigInteger('record_id');
            $table->foreignId('site_id')->nullable()->index();
            $table->string('record_title')->nullable();
            $table->foreignId('admin_user_id')->nullable();
            $table->string('admin_name')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->unique(['module', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_trash');
    }
};
