<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Support\PublicFolder;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The web root of a project whose `public` was renamed for a hosting that
 * serves a fixed folder, as the console finds it before `.env` is read.
 */
final class PublicFolderTest extends TestCase
{
    private const FRONT_CONTROLLER = "<?php\n\$app = require_once __DIR__.'/../bootstrap/app.php';\n";

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/atrium-public-folder-'.getmypid().'-'.uniqid();
        mkdir($this->root.'/storage', 0777, true);
        mkdir($this->root.'/vendor', 0777, true);
    }

    protected function tearDown(): void
    {
        chmod($this->root, 0777);
        foreach (glob($this->root.'/*/index.php') ?: [] as $index) {
            chmod($index, 0666);
        }
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    private function folder(string $name, ?string $index = self::FRONT_CONTROLLER): void
    {
        mkdir($this->root.'/'.$name);

        if ($index !== null) {
            file_put_contents($this->root.'/'.$name.'/index.php', $index);
        }
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function names(): array
    {
        return [
            'www' => ['www'],
            'public_html' => ['public_html'],
            'htdocs' => ['htdocs'],
            'a name of the site' => ['example.com'],
            'Cyrillic' => ['сайт'],
        ];
    }

    #[DataProvider('names')]
    public function test_a_renamed_public_folder_is_found_by_its_index_php(string $name): void
    {
        $this->folder($name);

        $this->assertSame($this->root.'/'.$name, PublicFolder::find($this->root));
    }

    public function test_public_with_its_index_php_is_the_web_root(): void
    {
        $this->folder('public');

        $this->assertSame($this->root.'/public', PublicFolder::find($this->root));
    }

    public function test_public_wins_over_a_copy_of_it_under_another_name(): void
    {
        $this->folder('public');
        $this->folder('public_html');

        $this->assertSame($this->root.'/public', PublicFolder::find($this->root));
    }

    public function test_a_public_folder_without_index_php_does_not_hide_the_renamed_one(): void
    {
        $this->folder('public', null);
        $this->folder('www');

        $this->assertSame($this->root.'/www', PublicFolder::find($this->root));
    }

    public function test_an_index_php_that_does_not_start_the_application_is_not_a_web_root(): void
    {
        $this->folder('public_html', "<?php\necho 'Your site is hosted here';\n");
        $this->folder('www');

        $this->assertSame($this->root.'/www', PublicFolder::find($this->root));
    }

    public function test_an_unreadable_index_php_is_not_a_web_root(): void
    {
        $this->folder('public_html');
        chmod($this->root.'/public_html/index.php', 0);
        $this->folder('www');

        $this->assertSame($this->root.'/www', PublicFolder::find($this->root));
    }

    public function test_with_two_candidates_it_stays_public_rather_than_guess(): void
    {
        $this->folder('www');
        $this->folder('public_html');

        $this->assertSame($this->root.'/public', PublicFolder::find($this->root));
    }

    public function test_with_no_candidate_it_stays_public(): void
    {
        $this->folder('www', "<?php\necho 'placeholder';\n");

        $this->assertSame($this->root.'/public', PublicFolder::find($this->root));
    }

    public function test_an_unreadable_project_folder_leaves_it_public(): void
    {
        $this->folder('www');
        chmod($this->root, 0);

        $this->assertSame($this->root.'/public', PublicFolder::find($this->root));
    }

    public function test_index_php_deeper_in_the_project_is_not_looked_at(): void
    {
        mkdir($this->root.'/www');
        $this->folder('www/site');

        $this->assertSame($this->root.'/public', PublicFolder::find($this->root));
    }

    public function test_messages_name_the_file_from_the_project_folder(): void
    {
        $this->app->usePublicPath(base_path('public_html'));

        $this->assertSame('public_html'.DIRECTORY_SEPARATOR.'robots.txt', PublicFolder::shown('robots.txt'));
    }

    public function test_a_web_root_outside_the_project_is_named_in_full(): void
    {
        $this->app->usePublicPath($this->root.'/www');

        $this->assertSame($this->root.'/www'.DIRECTORY_SEPARATOR.'storage', PublicFolder::shown('storage'));
    }

    public function test_a_folder_that_only_starts_like_the_project_is_named_in_full(): void
    {
        $this->app->usePublicPath(base_path().'-copy/public');

        $this->assertSame(base_path().'-copy/public'.DIRECTORY_SEPARATOR.'storage', PublicFolder::shown('storage'));
    }
}
