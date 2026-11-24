<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Puts a public demo back as it was (docs/demo.md): the database is emptied
 * and filled with the demo content again, the uploaded files are removed, and
 * the one visitor, `atrium.demo.email`, gets the "Demo" role.
 *
 * Only for a site that says it is a demo (`ATRIUM_DEMO=true`): everything in
 * the database goes, so on any other site the command does nothing.
 */
final class DemoResetCommand extends Command
{
    protected $signature = 'atrium:demo:reset';

    protected $description = 'Empty the demo site and fill it with the demo content again (only with ATRIUM_DEMO=true)';

    public function handle(Permissions $permissions): int
    {
        if (! config('atrium.demo.enabled')) {
            $this->error('This is not a demo site: set ATRIUM_DEMO=true to use this command. Nothing was changed.');

            return self::FAILURE;
        }

        $email = config()->string('atrium.demo.email');

        $this->removeUploads();

        // The first administrator is the one the installer creates; the demo
        // visitor replaces them below, and nobody knows this password.
        $status = $this->call('migrate:fresh', ['--force' => true]);
        $status = $status === self::SUCCESS ? $this->call('atrium:install', [
            '--demo' => true,
            '--name' => 'Demo',
            '--email' => $email,
            '--password' => Str::random(40),
        ]) : $status;

        if ($status !== self::SUCCESS) {
            return self::FAILURE;
        }

        $visitor = AdminUser::query()->where('email', $email)->sole();
        $visitor->forceFill(['is_superadmin' => false, 'password' => Hash::make(config()->string('atrium.demo.password'))])->save();
        $visitor->roles()->sync([AdminRole::query()->create(['name' => 'Demo', 'permissions' => $this->granted($permissions)])->id]);

        $this->components->info("The demo is back as it was: {$email}.");

        return self::SUCCESS;
    }

    /**
     * The content is the visitor's to edit, import and export, and to delete
     * and restore from the trash; the people, sites and languages only to
     * look at; no API tokens and no module settings. The import fetches no
     * images in a demo (Transfer\Images).
     *
     * @return array<int,string>
     */
    private function granted(Permissions $permissions): array
    {
        $readOnly = config()->array('atrium.demo.read_only', []);

        return array_values(array_filter($permissions->all(), static function (string $permission) use ($readOnly): bool {
            [$section, $action] = explode('.', $permission);

            return in_array($action, [...Permissions::ACTIONS, Permissions::IMPORT, Permissions::EXPORT, Permissions::RESTORE, Permissions::FORCE_DELETE], true)
                && ($action === Permissions::VIEW || ! in_array($section, $readOnly, true));
        }));
    }

    /**
     * What visitors uploaded lies on the public disk (the `.gitignore` that
     * keeps the folder in git stays); other disks are never touched.
     */
    private function removeUploads(): void
    {
        $disk = Storage::disk('public');

        foreach ($disk->directories() as $directory) {
            $disk->deleteDirectory($directory);
        }

        foreach ($disk->files() as $file) {
            if ($file !== '.gitignore') {
                $disk->delete($file);
            }
        }
    }
}
