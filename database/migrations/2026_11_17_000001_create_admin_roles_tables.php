<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles of administrators and the superadmin flag.
 *
 * Everyone who was an administrator before this migration becomes a
 * superadmin: until then every administrator could do everything, and an
 * upgrade must not lock anyone out of the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // ["articles.view", "articles.update", "api-tokens.use", …]
            $table->json('permissions');
            $table->timestamps();
        });

        Schema::create('admin_role_user', function (Blueprint $table) {
            $table->foreignId('admin_role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->primary(['admin_role_id', 'admin_user_id']);
        });

        Schema::table('admin_users', function (Blueprint $table) {
            $table->boolean('is_superadmin')->default(false)->after('is_active');
        });

        DB::table('admin_users')->update(['is_superadmin' => true]);
    }

    public function down(): void
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn('is_superadmin');
        });

        Schema::dropIfExists('admin_role_user');
        Schema::dropIfExists('admin_roles');
    }
};
