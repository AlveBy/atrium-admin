<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\SlugHistory;
use Atrium\Admin\Tests\Fixtures\Guide;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * A page that moved answers its old address with a permanent redirect
 * instead of a 404.
 */
final class SlugRedirectTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('guides', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code');
            $table->string('folder')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('slug.redirect')->group(function (): void {
            Route::get('/', fn () => abort(404));

            Route::get('{path}', function (Request $request, string $path) {
                if ($request->query('status')) {
                    abort((int) $request->query('status'));
                }

                $guide = Guide::query()->where('active', true)->get()
                    ->first(fn (Guide $guide): bool => $guide->getFullPath() === $path);

                return $guide?->name ?? abort(404);
            })->where('path', '.*');
        });
    }

    private function guide(string $code, ?string $folder = null, bool $active = true): Guide
    {
        return Guide::query()->create(['name' => 'Guide '.$code, 'code' => $code, 'folder' => $folder, 'active' => $active]);
    }

    private function history(Guide $guide, string $slug, string $fullPath): void
    {
        SlugHistory::query()->create([
            'sluggable_type' => Guide::class,
            'sluggable_id' => $guide->id,
            'slug' => $slug,
            'full_path' => $fullPath,
        ]);
    }

    public function test_a_page_that_exists_is_served_as_it_is(): void
    {
        $guide = $this->guide('setup', 'guides');
        $this->history($guide, 'setup', 'guides/setup');

        $this->get('/guides/setup')->assertOk()->assertSeeText('Guide setup');
    }

    public function test_the_old_full_address_redirects_to_the_current_one(): void
    {
        $this->history($this->guide('setup', 'guides'), 'install', 'manuals/install');

        $this->get('/manuals/install')->assertStatus(301)->assertRedirect('/guides/setup');
        $this->get('/manuals/install/')->assertStatus(301)->assertRedirect('/guides/setup');
    }

    public function test_an_old_last_segment_is_replaced_by_the_current_code(): void
    {
        $this->history($this->guide('pricing'), 'prices', 'old/prices');

        $this->get('/shop/prices')->assertStatus(301)->assertRedirect('/shop/pricing');
        $this->get('/prices')->assertStatus(301)->assertRedirect('/pricing');
    }

    public function test_an_unknown_address_stays_a_404(): void
    {
        $this->history($this->guide('setup', 'guides'), 'install', 'manuals/install');

        $this->get('/manuals/uninstall')->assertNotFound();
        $this->get('/')->assertNotFound();
    }

    public function test_history_of_a_deleted_page_leads_nowhere(): void
    {
        $guide = $this->guide('setup', 'guides');
        $this->history($guide, 'install', 'manuals/install');
        $guide->delete();

        $this->get('/manuals/install')->assertNotFound();
        $this->get('/elsewhere/install')->assertNotFound();
    }

    public function test_a_hidden_page_does_not_redirect_to_itself(): void
    {
        $this->history($this->guide('hidden', 'guides', active: false), 'hidden', 'guides/hidden');
        $this->history($this->guide('draft', active: false), 'draft', 'old/draft-copy');

        $this->get('/guides/hidden')->assertNotFound();
        $this->get('/draft')->assertNotFound();
    }

    public function test_only_a_404_is_redirected(): void
    {
        $this->history($this->guide('setup', 'guides'), 'install', 'manuals/install');

        $this->get('/manuals/install?status=403')->assertForbidden();
        $this->get('/manuals/install?status=500')->assertStatus(500);
    }

    public function test_redirects_can_be_turned_off(): void
    {
        config(['atrium.slug.auto_redirect' => false]);
        $this->history($this->guide('setup', 'guides'), 'install', 'manuals/install');

        $this->get('/manuals/install')->assertNotFound();
    }
}
