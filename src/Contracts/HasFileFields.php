<?php

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface HasFileFields
{
    /**
     * Get files relationship
     */
    public function files(): MorphMany;

    /**
     * Get files by field name
     */
    public function getFilesByField(string $field): iterable;

    /**
     * Attach file to field
     */
    public function attachFile(string $field, string $path, array $metadata = []): void;

    /**
     * Detach file from field
     */
    public function detachFile(string $field, int $fileId): void;
}
