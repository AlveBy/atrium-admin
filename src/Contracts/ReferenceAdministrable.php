<?php

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

interface ReferenceAdministrable extends Administrable
{
    /**
     * Get display value for dropdowns and relations
     */
    public function getDisplayValue(): string;
}
