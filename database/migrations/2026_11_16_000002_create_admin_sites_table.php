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
        Schema::create('admin_sites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->json('domains')->nullable();
            // Content language codes of this site; empty — every active one.
            $table->json('languages')->nullable();
            $table->string('default_language', 12)->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort')->default(500);
            $table->timestamps();
        });

        // Which sites an administrator may work with; empty — all of them.
        Schema::table('admin_users', function (Blueprint $table) {
            $table->json('site_ids')->nullable();
        });

        // A panel always runs at least one site: the app itself.
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        DB::table('admin_sites')->insert([
            'code' => 'main',
            'name' => (string) config('app.name', 'Site'),
            'domains' => json_encode(is_string($host) && $host !== '' ? [strtolower($host)] : []),
            'active' => true,
            'is_default' => true,
            'sort' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('site_ids');
        });

        Schema::dropIfExists('admin_sites');
    }
};
