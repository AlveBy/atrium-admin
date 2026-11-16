<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tabs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** @phpstan-consistent-constructor */
class Tab implements Arrayable, JsonSerializable
{
    protected string $name;

    protected string $label;

    protected ?string $icon = null;

    protected int $order = 0;

    protected bool $visible = true;

    protected ?string $badge = null;

    /**
     * Create a new tab instance
     */
    public static function make(string $name): static
    {
        $tab = new static;
        $tab->name = $name;
        $tab->label = $name;

        return $tab;
    }

    /**
     * Set the tab label
     */
    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the tab icon
     */
    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the tab order
     */
    public function order(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Set the tab visibility
     */
    public function visible(bool $visible = true): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Hide the tab
     */
    public function hidden(): static
    {
        $this->visible = false;

        return $this;
    }

    /**
     * Set the tab badge
     */
    public function badge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * Get the tab name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the tab label
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the tab order
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * Check if the tab is visible
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'icon' => $this->icon,
            'order' => $this->order,
            'visible' => $this->visible,
            'badge' => $this->badge,
        ];
    }

    /**
     * Convert to JSON
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
