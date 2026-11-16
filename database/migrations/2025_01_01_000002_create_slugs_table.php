<?php

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
