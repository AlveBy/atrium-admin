<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort')->default(500);
            $table->timestamps();
        });

        // A site always has one content language: the app's own.
        $code = str_replace('_', '-', strtolower((string) config('app.locale', 'en')));
        $names = (array) config('atrium.locales', []);

        DB::table('admin_languages')->insert([
            'code' => $code,
            'name' => $names[config('app.locale')] ?? strtoupper($code),
            'active' => true,
            'is_default' => true,
            'sort' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_languages');
    }
};
