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
 * Tables of the fixture modules whose records are built of blocks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->json('layout')->nullable();
            $table->timestamps();
        });

        Schema::create('billboards', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->json('layout')->nullable();
            $table->json('aside')->nullable();
            $table->json('frozen')->nullable();
            $table->json('tucked')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billboards');
        Schema::dropIfExists('landings');
    }
};
