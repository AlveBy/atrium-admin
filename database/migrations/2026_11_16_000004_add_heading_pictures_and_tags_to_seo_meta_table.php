<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rest of the SEO tab: the page heading (H1, apart from <title>), ALT,
 * TITLE and file name templates for preview and detail pictures, and tags.
 * The tab and SeoMeta already had these fields; the table did not, so saving
 * any of them failed.
 *
 * ALT/TITLE are text: on sites moved from Bitrix these templates run to
 * a thousand characters and more, and so do keywords.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_meta', function (Blueprint $table): void {
            $table->text('keywords')->nullable()->change();
            $table->string('page_title')->nullable();
            $table->text('preview_picture_alt')->nullable();
            $table->text('preview_picture_title')->nullable();
            $table->text('preview_picture_file_name')->nullable();
            $table->text('detail_picture_alt')->nullable();
            $table->text('detail_picture_title')->nullable();
            $table->text('detail_picture_file_name')->nullable();
            $table->string('tags', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_meta', function (Blueprint $table): void {
            $table->dropColumn([
                'page_title',
                'preview_picture_alt',
                'preview_picture_title',
                'preview_picture_file_name',
                'detail_picture_alt',
                'detail_picture_title',
                'detail_picture_file_name',
                'tags',
            ]);
        });

        Schema::table('seo_meta', function (Blueprint $table): void {
            $table->string('keywords')->nullable()->change();
        });
    }
};
