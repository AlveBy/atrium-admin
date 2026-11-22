<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Atrium\Admin\Models\DataImport;
use Atrium\Admin\Resources\ResourceRegistry;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Moves a check or an import forward for a while, then saves its place.
 *
 * Whatever drives it — a queue job, the scheduler, the progress page in the
 * browser, the console — calls step() again until it says nothing is left.
 * Only one of them works on an import at a time; one that finds it busy
 * leaves at once.
 */
final class ImportRunner
{
    public function __construct(private readonly ResourceRegistry $resources, private readonly Hosts $hosts) {}

    /**
     * Work for up to $seconds. True when there is more to do.
     */
    public function step(DataImport $import, int $seconds): bool
    {
        $lock = Cache::lock('atrium-import:'.$import->id, $seconds + 60);

        if (! $lock->get()) {
            return true;
        }

        try {
            $import->refresh();

            return $import->isRunning() && $this->work($import, $seconds);
        } finally {
            $lock->release();
        }
    }

    private function work(DataImport $import, int $seconds): bool
    {
        $deadline = now()->addSeconds($seconds);

        try {
            $model = $this->resources->model($import->module) ?? throw ImportFailed::because('module', ['module' => $import->module]);
            $rows = new RowImporter($import, $model, Targets::for($model), new RecordWriter($model), $this->hosts);

            do {
                foreach (Rows::read($import->path(), $import->position, config('atrium.import.chunk')) as $row) {
                    $rows->import($row['number'], $row['cells']);
                    $import->position = $row['end'];

                    if (now() >= $deadline) {
                        break;
                    }
                }

                $import->save();
                $done = Rows::finished($import->path(), $import->position);
            } while (! $done && now() < $deadline);
        } catch (ImportFailed $failed) {
            $import->stop(DataImport::FAILED, $failed->getMessage());

            return false;
        } catch (Throwable $crash) {
            report($crash);
            $import->stop(DataImport::FAILED, __('atrium::import.failures.crashed'));

            return false;
        }

        if ($done) {
            $this->finish($import);
        }

        return ! $done;
    }

    private function finish(DataImport $import): void
    {
        if ($import->status === DataImport::CHECKING) {
            $import->stop(DataImport::CHECKED);

            return;
        }

        $import->stop(DataImport::DONE);
        $import->deleteFile();
    }
}
