<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Mcp;

use Atrium\Admin\Mcp\Tools\CreateRecord;
use Atrium\Admin\Mcp\Tools\DeleteRecord;
use Atrium\Admin\Mcp\Tools\DescribeModule;
use Atrium\Admin\Mcp\Tools\GetRecord;
use Atrium\Admin\Mcp\Tools\ListModules;
use Atrium\Admin\Mcp\Tools\ListRecords;
use Atrium\Admin\Mcp\Tools\UpdateRecord;
use Laravel\Mcp\Server;

/**
 * The admin modules for AI assistants (Claude, ChatGPT, Cursor…), over the
 * same API and the same tokens: an assistant may do exactly what its token
 * allows, and its changes are saved like a manual edit.
 */
class AtriumServer extends Server
{
    protected string $name = 'Atrium';

    protected string $version = '0.1.0';

    protected string $instructions = <<<'MARKDOWN'
        This server edits the content of a website managed with Atrium: its admin modules (news, products, pages and so on) and their records.

        - Start with `list_modules`: it shows the modules this token may use and what it may do in each.
        - Before creating or changing records of a module, call `describe_module` to see its fields, which are required and what values they take.
        - Translatable fields take either a plain value (the default language) or an object keyed by language code, e.g. {"en": "Hello", "de": "Hallo"}.
        - `update_record` changes only the fields you send.
        - With several sites, pass `site` (the site code) to work with another site than the one this address belongs to.
        - When a call is refused, the message says why: a missing token ability, a missing permission of the token owner, or the invalid fields.
        MARKDOWN;

    protected array $tools = [
        ListModules::class,
        DescribeModule::class,
        ListRecords::class,
        GetRecord::class,
        CreateRecord::class,
        UpdateRecord::class,
        DeleteRecord::class,
    ];

    /**
     * Tools of other packages: they push their classes to atrium.mcp.tools
     * (alveby/atrium-ai adds generate_text).
     */
    protected function boot(): void
    {
        $this->tools = array_values(array_unique([...$this->tools, ...(array) config('atrium.mcp.tools', [])]));
    }
}
