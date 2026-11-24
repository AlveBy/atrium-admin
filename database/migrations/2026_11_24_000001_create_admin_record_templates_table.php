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
 * The template an editor picked for a record of any module that has a
 * public page, like the SEO data: one row per record, none for the default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_record_templates', function (Blueprint $table) {
            $table->id();
            $table->morphs('templatable');
            // The key of the template: the file name, "full-width".
            $table->string('template', 100);
            $table->timestamps();

            $table->unique(['templatable_type', 'templatable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_record_templates');
    }
};
