<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

/**
 * Roles chosen for each site apart: one list of roles per site. The value is
 * the site's id => the ids of its roles.
 */
class SiteRolesField extends Field
{
    /** @var array<int,array{value:int,label:string}> */
    protected array $sites = [];

    /** @var array<int,array{value:int,label:string}> */
    protected array $roles = [];

    public function getType(): string
    {
        return 'siteRoles';
    }

    /**
     * @param  array<int,string>  $sites  id => name
     */
    public function sites(array $sites): static
    {
        $this->sites = self::options($sites);

        return $this;
    }

    /**
     * @param  array<int,string>  $roles  id => name
     */
    public function roles(array $roles): static
    {
        $this->roles = self::options($roles);

        return $this;
    }

    protected function getFieldProps(): array
    {
        return [
            'sites' => $this->sites,
            'roles' => $this->roles,
        ];
    }

    /**
     * @param  array<int,string>  $names
     * @return array<int,array{value:int,label:string}>
     */
    private static function options(array $names): array
    {
        $options = [];

        foreach ($names as $id => $name) {
            $options[] = ['value' => $id, 'label' => $name];
        }

        return $options;
    }
}
