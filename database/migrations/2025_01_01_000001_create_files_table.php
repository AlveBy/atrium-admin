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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->morphs('fileable');
            $table->string('field')->index();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->integer('sort')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['fileable_type', 'fileable_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
