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
        Schema::table('admin_sites', function (Blueprint $table) {
            // Counters: only the IDs, the snippets are built by the core.
            $table->string('google_tag_id', 32)->nullable();
            $table->string('yandex_metrika_id', 20)->nullable();
            $table->boolean('yandex_webvisor')->default(false);
            // Logged-in administrators are not counted as visitors.
            $table->boolean('counters_skip_admins')->default(true);

            // Ownership verification meta tags of search consoles.
            $table->string('google_verification')->nullable();
            $table->string('yandex_verification')->nullable();
            $table->string('bing_verification')->nullable();

            // Anything else, as is.
            $table->text('head_code')->nullable();
            $table->text('body_start_code')->nullable();
            $table->text('body_end_code')->nullable();

            // Empty — a sensible default with a link to the site's sitemap.
            $table->text('robots_txt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admin_sites', function (Blueprint $table) {
            $table->dropColumn([
                'google_tag_id', 'yandex_metrika_id', 'yandex_webvisor', 'counters_skip_admins',
                'google_verification', 'yandex_verification', 'bing_verification',
                'head_code', 'body_start_code', 'body_end_code', 'robots_txt',
            ]);
        });
    }
};
