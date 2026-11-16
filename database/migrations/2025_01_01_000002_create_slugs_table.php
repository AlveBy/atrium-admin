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
        Schema::create('slugs', function (Blueprint $table) {
            $table->id();
            $table->morphs('sluggable');
            $table->string('slug')->index();
            $table->string('full_path')->index();
            $table->timestamps();

            $table->unique(['slug', 'sluggable_type']);
            $table->unique(['full_path', 'sluggable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slugs');
    }
};
