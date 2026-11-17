<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Support\CdnPurge;
use Illuminate\Console\Command;
use RuntimeException;

class CdnPurgeCommand extends Command
{
    protected $signature = 'atrium:cdn:purge
        {paths?* : Files by path (/storage/a.jpg) or full address}
        {--all : The whole CDN cache}';

    protected $description = 'Remove files from the CDN cache (Cloudflare or BunnyCDN)';

    public function handle(): int
    {
        /** @var array<int,string> $paths */
        $paths = $this->argument('paths');

        if (! $this->option('all') && $paths === []) {
            $this->error('Name the files, or pass --all.');

            return self::INVALID;
        }

        try {
            if ($this->option('all')) {
                CdnPurge::everything();
                $this->info('The CDN cache is purged.');

                return self::SUCCESS;
            }

            $urls = array_merge(...array_map(CdnPurge::addressesOf(...), $paths));
            CdnPurge::urls($urls);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Purged: '.implode(', ', $urls));

        return self::SUCCESS;
    }
}
