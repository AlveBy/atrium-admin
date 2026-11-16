<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generation time of public pages.
 *
 * Both speed gadgets of the dashboard are computed from these rows: "Site
 * speed" (median) and "Performance monitor" (pages per second).
 *
 * Bitrix fetched these numbers from outside — the speed gadget posted the
 * licence key to its vendor and got p50 back, and the performance score was
 * written by the perfmon module after a manual test run. We have neither, so
 * we measure ourselves, locally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_page_timings', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255);
            $table->unsignedInteger('duration_ms');
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_page_timings');
    }
};
