<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The frame of the panel is the layout of the whole app, kept between visits.
 * A page that wraps itself in <AdminLayout> again would still work (the
 * wrapper steps aside), but it would put a component back around every page
 * that is rebuilt on each visit, and a new page copying it would spread that.
 * The browser tests (tests/Browser/Admin/PersistentLayoutTest) show what the
 * person sees; this keeps the source honest.
 */
final class PersistentLayoutTest extends TestCase
{
    private const JS = __DIR__.'/../../resources/js';

    public function test_no_page_wraps_itself_in_the_frame(): void
    {
        $wrapping = [];

        foreach ($this->pages() as $page) {
            if (str_contains((string) file_get_contents($page), 'AdminLayout')) {
                $wrapping[] = substr($page, strlen(self::JS) + 1);
            }
        }

        $this->assertSame([], $wrapping);
    }

    public function test_every_page_sets_its_title_with_head_except_the_sign_in_pages(): void
    {
        $without = [];

        foreach ($this->pages() as $page) {
            $source = (string) file_get_contents($page);

            if (! str_contains($page, '/Pages/Admin/Auth/') && ! str_contains($source, '<Head ')) {
                $without[] = substr($page, strlen(self::JS) + 1);
            }
        }

        $this->assertSame([], $without);
    }

    public function test_the_app_gives_every_page_the_frame_but_the_sign_in_pages(): void
    {
        $app = (string) file_get_contents(self::JS.'/app.js');

        $this->assertStringContainsString("layout: (name) => (name.startsWith('Admin/Auth/') ? null : AdminShell)", $app);
        $this->assertDirectoryExists(self::JS.'/Pages/Admin/Auth');
    }

    /**
     * @return list<string>
     */
    private function pages(): array
    {
        $pages = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::JS.'/Pages', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'vue') {
                $pages[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($pages);

        $this->assertNotSame([], $pages);

        return $pages;
    }
}
