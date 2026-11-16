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
 * The values of the modules' settings: one row per module, and per site for
 * a module whose settings are per site (site_id null: every site).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_module_settings', function (Blueprint $table) {
            $table->id();
            // The module's key: "shop".
            $table->string('module', 100);
            $table->foreignId('site_id')->nullable()->constrained('admin_sites')->cascadeOnDelete();
            $table->json('values');
            $table->timestamps();

            $table->unique(['module', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_module_settings');
    }
};
