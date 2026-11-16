<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Contracts\HasSlug;
use Atrium\Admin\Services\SlugService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SlugRegenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'atrium:slug:regenerate
                            {model? : Model class name (e.g., App\\Models\\Article)}
                            {--all : Regenerate slugs for all models with HasSlug trait}
                            {--cleanup : Clean up old slug history entries}
                            {--cleanup-days=365 : Days to keep in history when cleaning up}';

    /**
     * The console command description.
     */
    protected $description = 'Regenerate URL slugs for models';

    public function __construct(
        protected SlugService $slugService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanupHistory();
        }

        if ($this->option('all')) {
            return $this->regenerateAll();
        }

        $modelClass = $this->argument('model');

        if (! $modelClass) {
            $this->error('Please specify a model class or use --all option');

            return self::FAILURE;
        }

        return $this->regenerateModel($modelClass);
    }

    /**
     * Regenerate slugs for specific model
     */
    protected function regenerateModel(string $modelClass): int
    {
        if (! class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");

            return self::FAILURE;
        }

        if (! in_array(HasSlug::class, class_implements($modelClass) ?: [])) {
            // Check if uses trait
            $traits = class_uses_recursive($modelClass);
            if (! isset($traits['Atrium\Admin\Traits\HasAdminSlug'])) {
                $this->error("Model {$modelClass} does not implement HasSlug or use HasAdminSlug trait");

                return self::FAILURE;
            }
        }

        $this->info("Regenerating slugs for {$modelClass}...");

        $count = $this->slugService->regenerateAll($modelClass);

        $this->info("Regenerated {$count} slugs");

        return self::SUCCESS;
    }

    /**
     * Regenerate slugs for all models
     */
    protected function regenerateAll(): int
    {
        $this->info('Scanning for models with HasSlug...');

        $models = $this->findModelsWithSlug();

        if (empty($models)) {
            $this->warn('No models found with HasSlug trait');

            return self::SUCCESS;
        }

        $this->info('Found '.count($models).' model(s)');

        $totalCount = 0;

        foreach ($models as $modelClass) {
            $this->info("Processing {$modelClass}...");
            $count = $this->slugService->regenerateAll($modelClass);
            $this->info("  - Regenerated {$count} slugs");
            $totalCount += $count;
        }

        $this->newLine();
        $this->info("Total: regenerated {$totalCount} slugs");

        return self::SUCCESS;
    }

    /**
     * Clean up old history entries
     */
    protected function cleanupHistory(): int
    {
        $days = (int) $this->option('cleanup-days');

        $this->info("Cleaning up slug history older than {$days} days...");

        $count = $this->slugService->cleanupHistory($days);

        $this->info("Deleted {$count} old history entries");

        return self::SUCCESS;
    }

    /**
     * Find all models with HasSlug trait
     */
    protected function findModelsWithSlug(): array
    {
        $models = [];
        $modelsPath = app_path('Models');

        if (! File::isDirectory($modelsPath)) {
            return $models;
        }

        $files = File::allFiles($modelsPath);

        foreach ($files as $file) {
            $relativePath = str_replace(
                [$modelsPath.DIRECTORY_SEPARATOR, '.php'],
                ['', ''],
                $file->getPathname()
            );

            $className = 'App\\Models\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

            if (! class_exists($className)) {
                continue;
            }

            // Check for HasAdminSlug trait
            $traits = class_uses_recursive($className);
            if (isset($traits['Atrium\Admin\Traits\HasAdminSlug'])) {
                $models[] = $className;
            }
        }

        return $models;
    }
}
